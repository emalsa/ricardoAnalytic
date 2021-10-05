<?php

namespace Drupal\ra_article;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\ra_admin\ScrapedogServiceInterface;
use GuzzleHttp\ClientInterface;

/**
 * Class SellerArticlesService.
 */
class SellerArticlesService implements SellerArticlesServiceInterface {

  protected const LIMIT_PAGES = 15;

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Logger\LoggerChannelInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannelRaSellerArticles;

  /**
   * The scrapedog service.
   *
   * @var \Drupal\ra_admin\ScrapedogServiceInterface
   */
  protected $scrapedogService;

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   * The entity storage
   */
  protected $nodeStorage;

  /**
   * Constructs a new SellerArticlesService object.
   */
  public function __construct(
    ClientInterface $httpClient,
    EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelInterface $loggerChannelRaSellerArticles,
    ScrapedogServiceInterface $scrapedogService) {
    $this->httpClient = $httpClient;
    $this->entityTypeManager = $entityTypeManager;
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
    $this->loggerChannelRaSellerArticles = $loggerChannelRaSellerArticles;
    $this->scrapedogService = $scrapedogService;
  }

  /**
   *
   */
  public function createFetchUrls() {
    $entityQuery = $this->nodeStorage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'seller')
      ->condition('status', NodeInterface::PUBLISHED);
    $sellerNids = $entityQuery->execute();

    foreach ($sellerNids as $nid) {
      $sellerEntity = $this->nodeStorage->load($nid);
      $username = $sellerEntity->label();
      $totalArticlesCount = $sellerEntity->get('field_seller_open_articles_count')->value;
      // New seller added. We fetch the first "self::LIMIT_PAGES" initial.
      // The total articles count will be updated.
      if (!$totalArticlesCount || $totalArticlesCount == 0) {
        $pages = 10;
      }
      else {
        $pages = ceil($totalArticlesCount / 60);
        $pages = $pages > self::LIMIT_PAGES ? self::LIMIT_PAGES : $pages;
      }

      $connection = \Drupal::database();
      for ($i = 1; $i <= $pages; $i++) {
        $apiKey = '6159a65470f306228e3ce3d5';
        $url = "https://api.scrapingdog.com/scrape?api_key=$apiKey&url=https://www.ricardo.ch/de/shop/$username/offers/?sort=newest&page=$i&dynamic=false";
        $connection->insert('queue_ricardoanalytic')->fields([
          'nid',
          'type',
          'data',
          'created',
        ])->values([
          'nid' => $nid,
          'type' => 'seller-articles',
          'data' => serialize('{"url":"' . $url . '","pagetype":"seller-articles"}'),
          'created' => time(),
        ])->execute();
      }
    }
  }

  /**
   *
   */
  public function fetchSellerArticles() {
    $connection = \Drupal::database();
    $query = $connection
      ->select('queue_ricardoanalytic', 'q')
      ->fields('q')
      ->range(0, 1);
    $result = $query->execute()->fetchAll();
    if (empty($result)) {
      return;
    }

    $result = reset($result);
    $response = $this->httpClient->post(
      'https://ricardo-crawler-vimooyk3pq-rj.a.run.app/ricardo-crawler',
      [
        'headers' => [
          'Accept' => 'application/json',
        ],
        'body' => unserialize($result->data),
      ]);
    if ($response->getStatusCode() != 200) {
      // $this->loggerChannelRaSellerArticles->error($response->getBody()->getContents(),);
      return;
    }

    $data = json_decode($response->getBody()->getContents(), TRUE);
    if (empty($data)) {
      return;
    }

    foreach ($data['initialState']['srp']['results'] as $item) {
      $articleNode = $this->nodeStorage->loadByProperties(['field_article_id' => $item['id']]);
      $sellerNode = $this->getSellerNode($item);
      if (!empty($articleNode)) {
        continue;
      }

      $this->createNode($sellerNode, $item);
    }

    $this->updateSellerTotalCount($sellerNode, $data);
    $this->deleteQueueItem($result);
  }

  /**
   * @param $item
   *   The result item from response
   * @param $sellerEntity
   *   The seller node.
   */
  protected function createNode($sellerEntity, $item) {
    Node::create([
      'type' => 'article',
      'title' => $item['title'],
      'field_article_id' => $item['id'],
      'field_article_seller_ref' => $sellerEntity,
      'field_article_raw_json' => [
        'value' => serialize($item),
      ],
      // This is very cheap here....
      'field_article_end_date' => str_replace('Z', '', $item['endDate']),
    ])->save();
  }

  /**
   * Update total count offers of the seller.
   *
   * @param \Drupal\node\NodeInterface $sellerEntity
   *   The seller entity.
   * @param $data
   *   The response data.
   */
  protected function updateSellerTotalCount(NodeInterface $sellerEntity, $data) {
    $sellerEntity->set('field_seller_open_articles_count', $data['initialState']['srp']['totalArticlesCount']);
    $sellerEntity->save();
  }

  /**
   * @param $result
   *   The query result
   */
  protected function deleteQueueItem($result) {
    $connection = \Drupal::database();
    $connection->delete('queue_ricardoanalytic')
      ->condition('id', $result->id)
      ->condition('nid', $result->nid)
      ->execute();
  }

  /**
   * @param $item
   *
   * @return array|false|mixed
   */
  protected function getSellerNode($item) {
    $sellerNode = $this->nodeStorage->loadByProperties([
      'type' => 'seller',
      'field_seller_sellerid' => $item['sellerId'],
    ]);

    return empty($sellerNode) ? [] : reset($sellerNode);
  }

}
