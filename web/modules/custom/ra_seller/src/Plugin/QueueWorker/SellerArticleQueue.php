<?php

namespace Drupal\ra_seller\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\QueueWorkerInterface;
use Drupal\Core\State\StateInterface;
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
class SellerArticleQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface, QueueWorkerInterface {

  /**
   * The puppeteer service url.
   *
   * @var string
   */
  // Protected const CRAWLER_UR = 'https://node-puppeteer-vimooyk3pq-uc.a.run.app/seller-articles';
  protected const CRAWLER_UR = 'https://ricardoanalytic_node_server:8080/seller-articles';

  /**
   * Time when we want to recrawle.
   *
   * @var string
   */
  public const SELLER_ARTICLE_RECRAWLE_THRESHOLD = '-10 hours';

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
  protected $nodeStorage;

  /**
   * The guzzle http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

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
  protected $sellerNode;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

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
    $this->url = "https://www.ricardo.ch/de/shop/{$this->sellerNode->label()}/offers/?sort=newest&page=";
    if ($this->process()) {
      $this->state->set('seller_article_last_process__' . $this->sellerNode->id(), time());
    }
  }

  /**
   * Processes each page of the seller and creates the article node.
   *
   * Until
   * - there is already a specific count of existing articles in our DB found.
   * - we reached the end of the pages.
   *
   * @param int $page
   *   The current page.
   * @param null|int $totalArticlesCount
   *   The total articles of this seller.
   *
   * @return bool
   *   FALSE if we have to reprocess it again soon. True if everything was fine.
   */
  protected function process(int $page = 1, ?int $totalArticlesCount = NULL) {
    $data = $this->fetchArticles($page);
    if (!$data) {
      $this->logger->warning($this->sellerNode->label() . ' || ' . 'returned empty data for page: ' . $page);
      return FALSE;
    }

    $articleExistCount = 0;
    // Iterate through results of the current page and determine if
    // the article have to be created.
    foreach ($data->results as $item) {
      if ($this->articleExists($item->id)) {
        $articleExistCount++;
        // continue;.
      }
      $articleExistCount = 0;
      $this->createArticle($item);
    }

    // If we have this count reached of articles which already exists,
    // we leave now.
    if ($articleExistCount >= self::MAX_EXISTING_ARTICLES_TO_CHECK) {
      return TRUE;
    }

    // We set $totalArticlesCount at the begin of the sellers article crawling,
    // since during the iteration a new article can be introduced.
    // The we would end in an endless loop.
    if (!$totalArticlesCount) {
      $totalArticlesCount = $data->totalArticlesCount;
    }

    // We reached the end of all articles if the value of
    // $nextSearchOffset === $totalArticlesCount.
    // If $nextSearchOffset is smaller than $totalArticlesCount, there are more
    // articles on the next page.
    if ($data->nextSearchOffset < $totalArticlesCount) {
      $page++;
      $this->process($page, $totalArticlesCount);
    }

    return TRUE;
  }

  /**
   * Triggers the crawler service to fetch the seller articles of given page.
   *
   * @param int $page
   *   The current page to fetch.
   *
   * @return null|object
   *   The fetched results
   */
  protected function fetchArticles(int $page) {
    try {
      $response = $this->httpClient->post(self::CRAWLER_UR, [
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
    $article->setRevisionLogMessage('Created the Article from seller page');
    $article->save();
  }

}
