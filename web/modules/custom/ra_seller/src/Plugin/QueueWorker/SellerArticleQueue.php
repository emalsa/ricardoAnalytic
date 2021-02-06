<?php

namespace Drupal\ra_seller\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\State\StateInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the seller_article_queue queue worker.
 *
 * @QueueWorker (
 *   id = "seller_article_queue",
 *   title = @Translation("Fetch new article from seller"),
 *   cron = {"time" = 60}
 * )
 */
class SellerArticleQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The puppeteer service url.
   *
   * @var string
   */
  // Protected const PUPPETEER_URL = 'https://node-puppeteer-vimooyk3pq-uc.a.run.app/seller-articles';
  protected const PUPPETEER_URL = 'http://ricardoanalytic_node_server:8080/seller-articles';


  /**
   * Time when we want to recrawle.
   *
   * @var string
   */
  public const ARTICLE_RECRAWLE_THRESHOLD = '-10 hours';

  /**
   * After reaching this count of existing article, we abort.
   *
   * @var int
   */
  protected const MAX_EXISTING_ARTICLES_TO_CHECK = 25;

  /**
   * The node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected NodeStorageInterface $nodeStorage;

  /**
   * The guzzle http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $httpClient;

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The url of the seller.
   *
   * @var string
   */
  protected string $url;

  /**
   * The seller node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $sellerNode;

  protected LoggerChannelInterface $logger;

  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, StateInterface $state, LoggerChannelInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->httpClient = \Drupal::httpClient();
    $this->state = $state;
    $this->logger = $logger;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('state'),
      $container->get('logger.factory')->get('seller_article_queue')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    // Get the seller node.
    $this->sellerNode = $this->nodeStorage->load($data['nid']);
    if (!$this->isToRecrawle()) {
      return;
    }

    $this->url = "https://www.ricardo.ch/de/shop/{$this->sellerNode->label()}/offers/?sort=newest&page=";
    if ($this->process()) {
      $this->state->set('seller_article_last_process__' . $this->sellerNode->id(), time());
    }
  }

  /**
   * Determines if we should crawl this seller.
   *
   * @return bool
   *   True if to crawl.
   */
  protected function isToRecrawle(): bool {
    $last = $this->state->get('seller_article_last_process__' . $this->sellerNode->id());
    if ($last > strtotime(self::ARTICLE_RECRAWLE_THRESHOLD)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Processes each page of the seller and creates the article node.
   *
   * Until
   * - there are some amount of existing articles
   * - we reached the end of the pages.
   *
   * @param int $page
   *   The current page.
   * @param null|int $totalArticlesCount
   *   Total articles of this seller.
   *
   * @return bool
   *   False if we have to reprocess it again soon.
   */
  protected function process(int $page = 1, ?int $totalArticlesCount = NULL): bool {
    $data = $this->fetchArticles($page);
    if (!$data) {
      $this->logger->warning($this->sellerNode->label() . ' || ' . 'returned empty data for page: ' . $page);
      return FALSE;
    }

    $articleExistCount = 0;
    // Iterate through results of current page and
    // determine if article have to be created.
    foreach ($data->results as $item) {
      if ($this->articleExists($item->id)) {
        $articleExistCount++;
        continue;
      }
      $articleExistCount = 0;
      $this->createArticle($item);
    }

    // If we have this count reached of articles which already exists,
    // we leave now.
    if ($articleExistCount >= self::MAX_EXISTING_ARTICLES_TO_CHECK) {
      return TRUE;
    }

    // We reached the end of all articles if the value of
    // $nextSearchOffset is the same as $totalArticlesCount.
    // If $nextSearchOffset is smaller than $totalArticlesCount,
    // there are more articles on the next page.
    // We passing check only once for $totalArticlesCount, since during the
    // iteration a new article can be introduced.
    // So we would end in an endless loop.
    if (!$totalArticlesCount) {
      $totalArticlesCount = $data->totalArticlesCount;
    }
    $nextSearchOffset = $data->nextSearchOffset;
    if ($nextSearchOffset < $totalArticlesCount) {
      $page++;
      $this->process($page, $totalArticlesCount);
    }

    return TRUE;
  }

  /**
   * Triggers the puppeteer service to fetch the seller articles of given page.
   *
   * @param int $page
   *   The current page to fetch.
   *
   * @return null|object
   *   The fetched results
   */
  protected function fetchArticles(int $page): ?object {
    try {
      $response = $this->httpClient->post(self::PUPPETEER_URL, [
        'json' => [
          'timeout' => 6000,
          'token' => 'data-explorer',
          'url' => $this->url . $page,
        ],
        'headers' => ['Content-Type' => 'application/json'],
      ]);

      if (!$response || $response->getStatusCode() !== 200) {
        throw new \Exception('Status code node was not 200');
      }

      $data = json_decode($response->getBody());
      return $data->initialState->srp ?? NULL;

    }
    catch (\Exception $e) {
      $this->logger->error($this->url . $page . ' || ' . $e->getMessage());
      return NULL;
    }

  }

  /**
   * Checks if article already exists.
   *
   * @param string $articleId
   *   The article id on ricardo's side.
   *
   * @return bool
   *   True if node with this article id already exists
   */
  protected function articleExists(string $articleId): bool {
    $article = $this->nodeStorage
      ->getQuery()
      ->condition('type', 'article', '=')
      ->condition('field_article_id', $articleId, '=')
      ->execute();

    return !empty($article);
  }

  /**
   * Creates the article node from given data.
   *
   * @param object $item
   *   The fetched data from ricardo.
   */
  protected function createArticle(object $item): void {
    $article = $this->nodeStorage->create([
      'type' => 'article',
      'field_article_id' => $item->id,
      'title' => $item->title,
      'field_article_is_processing' => 1,
      'field_article_seller_ref' => $this->sellerNode->id(),
      'field_article_start_date' => date('Y-m-d\TH:i:s', strtotime($item->startDate)),
      'field_article_end_date' => date('Y-m-d\TH:i:s', strtotime($item->endDate)),
      'field_article_url_alias' => $item->url,
      'field_article_category_id' => $item->categoryId,
    ]);
    $article->setRevisionLogMessage('Create');
    $article->save();
  }

}
