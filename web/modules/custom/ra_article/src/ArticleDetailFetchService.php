<?php

namespace Drupal\ra_article;

use Drupal\Core\Database\Connection;
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
   * Google Cloud service url
   *
   * @var string
   */
  public const FETCHER_SERVICE_BASE_URL = 'https://ricardo-crawler-vimooyk3pq-wl.a.run.app/article';

  protected const STATE_FOR_FETCH = '';

  protected const STATE_SUCCESSFUL_FETCH = '';

  protected const STATE_ERROR_FETCH = '';


  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Drupal\Core\Database\Driver\mysql\Connection definition.
   *
   * @var \Drupal\Core\Database\Driver\mysql\Connection
   */
  protected $database;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * Drupal\Core\Logger\LoggerChannelInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannelRaArticleDetail;

  /**
   * Constructs a new ArticleDetailFetchService object.
   */
  public function __construct(
    ClientInterface $httpClient,
    EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelInterface $loggerChannelRaSellerArticles,
    Connection $database) {
    $this->entityTypeManager = $entityTypeManager;
    $this->httpClient = $httpClient;
    $this->database = $database;
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
    $this->loggerChannelRaArticleDetail = $loggerChannelRaSellerArticles;
  }

  /**
   *
   */
  public function getArticleToFetch() {
    $query = $this->database->select('node_field_data', 'n');
    $query->fields('n', ['nid'])
      ->condition('n.type', 'article')
      ->condition('n.status', NodeInterface::PUBLISHED)
      ->condition('cm.moderation_state', 'to_scrape')
      ->range(0, 2)
      ->join('content_moderation_state_field_data', 'cm', 'n.nid=cm.content_entity_id');
    $articleNids = $query->execute()->fetchAll();
    if (empty($articleNids)) {
      return;
    }

    foreach ($articleNids as $articleNid) {
      $article = $this->entityTypeManager->getStorage('node')->load($articleNid->nid);
      if (!$article instanceof NodeInterface) {
        continue;
      }

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
        $article->setRevisionLogMessage('Scraped, changed to to_process');
        $article->set('moderation_state', 'failed')->save();
        continue;
      }

      if ($response->getStatusCode() != 200) {
        $this->loggerChannelRaArticleDetail->error('Returned a non 200 status code');
        $article->setRevisionLogMessage('Scraped, changed to to_process');
        $article->set('moderation_state', 'failed')->save();
        continue;
      }

      $data = json_decode($response->getBody()->getContents(), TRUE);
      if (empty($data)) {
        $this->loggerChannelRaArticleDetail->error('Empty data from response');
        $article->setRevisionLogMessage('Scraped, changed to to_process');
        $article->set('moderation_state', 'failed')->save();
        continue;
      }

      $article->set('field_article_raw_json', [
        'value' => serialize($data),
      ]);
      $article->set('moderation_state', 'to_process');
      $article->setRevisionLogMessage('Scraped, changed to to_process');
      $article->save();
    }
  }

}
