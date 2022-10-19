<?php

namespace Drupal\ra_article;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\node\NodeInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;

/**
 * Class ArticleDetailFetchService.
 */
class ArticleDetailFetchService implements ArticleDetailFetchServiceInterface {

  /**
   * The Google Cloud service url.
   *
   * @var string
   */
  public const FETCHER_SERVICE_BASE_URL = 'https://ricardo-crawler-vimooyk3pq-wl.a.run.app/article';

  /**
   * The state iof article is open.
   *
   * @var string
   */
  public const STATE_FOR_OPEN = 'open';

  /**
   * The state to be fetched.
   *
   * @var string
   */
  public const STATE_FOR_SCRAPE = 'to_scrape';

  /**
   * The state after successful fetch.
   *
   * @var string
   */
  public const STATE_SUCCESSFUL_FETCH = 'to_process';

  /**
   * The state after failed fetch.
   *
   * @var string
   */
  public const STATE_ERROR_FETCH = 'failed';

  /**
   * The state of the closed auction.
   *
   * @var string
   */
  public const STATE_CLOSED = 'closed';
  /**
   * The EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * Drupal\Core\Logger\LoggerChannelInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $loggerChannelRaArticleDetail;

  /**
   * Constructs a new ArticleDetailFetchService object.
   *
   * @param  \GuzzleHttp\ClientInterface  $httpClient
   * @param  \Drupal\Core\Entity\EntityTypeManagerInterface  $entityTypeManager
   * @param  \Drupal\Core\Logger\LoggerChannelInterface  $loggerChannelRaSellerArticles
   * @param  \Drupal\Core\Database\Connection  $database
   */
  public function __construct(
    ClientInterface $httpClient,
    EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelInterface $loggerChannelRaSellerArticles,
    Connection $database
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->httpClient = $httpClient;
    $this->database = $database;
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
    $this->loggerChannelRaArticleDetail = $loggerChannelRaSellerArticles;
  }

  /**
   * {@inheritDoc}
   */
  public function getArticleNidsToFetch(): array {
    $query = $this->database->select('node_field_data', 'n');
    $query->fields('n', ['nid'])
      ->condition('n.type', 'article')
      ->condition('n.status', NodeInterface::PUBLISHED)
      ->condition('cm.moderation_state', self::STATE_FOR_SCRAPE)
      ->orderBy('n.changed', 'ASC')
      ->range(0, 2)
      ->join('content_moderation_state_field_data', 'cm', 'n.nid=cm.content_entity_id');

    $articleNids = $query->execute()->fetchAll();
    if (empty($articleNids)) {
      return [];
    }

    return $articleNids;
  }

  /**
   * {@inheritDoc}
   */
  public function fetchArticleDetail(array $articleNids): void {
    foreach ($articleNids as $articleNid) {
      // Load the node.
      $article = $this->entityTypeManager->getStorage('node')->load($articleNid->nid);
      if (!$article instanceof NodeInterface) {
        continue;
      }

      // Fetch the article data.
      try {
        $response = $this->httpClient->post(
          self::FETCHER_SERVICE_BASE_URL,
          [
            'headers' => [
              'Accept' => 'application/json',
            ],
            RequestOptions::JSON => [
              'type' => 'article',
              'url' => "https://www.ricardo.ch/de/a/{$article->get('field_article_id')->value}/",
            ],
          ]);
      }
      catch (\Exception $e) {
        $this->loggerChannelRaArticleDetail->error($e->getMessage());
        $article->setRevisionLogMessage('Scraped, changed to ' . self::STATE_ERROR_FETCH);
        $article->set('moderation_state', self::STATE_ERROR_FETCH)->save();
        continue;
      }

      if ($response->getStatusCode() != 200) {
        $this->loggerChannelRaArticleDetail->error('Returned a non 200 status code');
        $article->setRevisionLogMessage('Scraped, changed to ' . self::STATE_ERROR_FETCH);
        $article->set('moderation_state', self::STATE_ERROR_FETCH)->save();
        continue;
      }

      $data = json_decode($response->getBody()->getContents(), TRUE);
      if (empty($data)) {
        $this->loggerChannelRaArticleDetail->error('Empty data from response');
        $article->setRevisionLogMessage('Scraped, changed to ' . self::STATE_ERROR_FETCH);
        $article->set('moderation_state', self::STATE_ERROR_FETCH)->save();
        continue;
      }

      $article->set('field_article_raw_json', [
        'value' => serialize($data),
      ]);

      $initialQuantity = $data['props']['initialState']['pdp']['article']['offer']['initial_quantity'];
      $remainingQuantity = $data['props']['initialState']['pdp']['article']['offer']['remaining_quantity'];
      $scrapingAttempts = $article->get('field_article_scraping_attempts')->value + 1;

      $article->set('field_article_remaining_quantity', $remainingQuantity);
      $article->set('field_article_initial_quantity', $initialQuantity);
      $article->set('field_article_scraping_attempts', $scrapingAttempts);

      // Max. attempts reached or auction finished, then close it.
      if ($article->get('field_article_scraping_attempts')->value >= 4
        || $data['props']['initialState']['pdp']['article']['offer']['remaining_time'] < 0
      ) {
        $article->set('moderation_state', self::STATE_SUCCESSFUL_FETCH);
        $article->setRevisionLogMessage('Scraped, changed to ' . self::STATE_SUCCESSFUL_FETCH);
      }
      else {
        $article->set('moderation_state', self::STATE_FOR_OPEN);
        $article->setRevisionLogMessage('Scraped, changed to ' . self::STATE_FOR_OPEN);

        $endDate = $data['props']['initialState']['pdp']['article']['offer']['end_date'];
        $article->set('field_article_end_date', str_replace('Z', '', $endDate));
      }

      $article->save();
    }
  }

}
