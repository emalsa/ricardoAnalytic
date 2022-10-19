<?php

namespace Drupal\ra_article;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;

/**
 * Class SellerArticlesService.
 */
class SellerArticlesService implements SellerArticlesServiceInterface {

  /**
   * The limit pages to create.
   *
   * @var string
   */
  protected const LIMIT_PAGES = 15;

  /**
   * The item per pages.
   *
   * @var string
   */
  protected const ITEMS_PER_PAGE = 60;

  /**
   * GuzzleHttp\ClientInterface .
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The LoggerChannel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $loggerChannelRaSellerArticles;

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * The database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Constructs a new SellerArticlesService object.
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
    $this->httpClient = $httpClient;
    $this->entityTypeManager = $entityTypeManager;
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
    $this->loggerChannelRaSellerArticles = $loggerChannelRaSellerArticles;
    $this->database = $database;
  }

  /**
   * {@inheritDoc}
   */
  public function fetchSellerArticles(): void {
    $query = $this->database
      ->select('queue_ricardoanalytic', 'q')
      ->fields('q')
      ->range(0, 1);
    $result = $query->execute()->fetchAll();

    if (empty($result)) {
      $this->loggerChannelRaSellerArticles->notice('No seller articles to fetch.');
      return;
    }
    $result = reset($result);

    try {
      $response = $this->httpClient->post(
        ArticleDetailFetchService::FETCHER_SERVICE_BASE_URL, [
          'headers' => [
            'Accept' => 'application/json',
          ],
          RequestOptions::JSON => json_decode(unserialize($result->data), TRUE),
        ]
      );
    }
    catch (\Exception $e) {
      $this->loggerChannelRaSellerArticles->error($e->getMessage());
      $this->deleteQueueItem($result, 'singleItem');
      return;
    }

    if ($response->getStatusCode() != 200) {
      $this->loggerChannelRaSellerArticles->error($response->getBody()->getContents());
      $this->deleteQueueItem($result, 'singleItem');
      return;
    }

    $totalArticlesCount = 0;
    $existingArticlesCount = 0;
    $sellerNode = NULL;

    $data = json_decode($response->getBody()->getContents(), TRUE);
    if (empty($data)) {
      $this->loggerChannelRaSellerArticles->error('Empty data from response.');
      return;
    }

    // No results, delete all queue items of this seller.
    if (empty($data['initialState']['srp']['results'])) {
      $this->deleteQueueItem($result, 'all');
    }

    // Iterate through result to get articles data.
    foreach ($data['initialState']['srp']['results'] as $item) {
      // Get seller entity.
      $sellerNode = $this->getSellerEntity($item);
      if (!$sellerNode) {
        $this->deleteQueueItem($result, 'all');
        return;
      }

      // Check if article already exist and skip it if exists..
      $articleNode = $this->nodeStorage->loadByProperties(['field_article_id' => $item['id']]);
      $totalArticlesCount = $data['initialState']['srp']['totalArticlesCount'];
      if (!empty($articleNode)) {
        $existingArticlesCount++;
        continue;
      }

      $this->createNode($sellerNode, $item);
    }

    // Delete all seller article pages from the queue, if we have reached a page with articles we already have.
    $mode = $existingArticlesCount > 55 ? 'all' : 'singleItem';
    $this->deleteQueueItem($result, $mode);

    if (!$sellerNode instanceof NodeInterface) {
      return;
    }

    $this->updateSellerTotalCount($sellerNode, $totalArticlesCount);
  }

  /**
   * Creates the article node.
   *
   * @param  array  $item
   *   The result item from response.
   * @param  NodeInterface  $sellerEntity
   *   The seller node.
   */
  protected function createNode(NodeInterface $sellerEntity, array $item): void {
    $node = Node::create([
      'type' => 'article',
      'title' => $item['title'],
      'field_article_id' => $item['id'],
      'field_article_seller_ref' => $sellerEntity,
      'field_article_raw_json' => [
        'value' => serialize($item),
      ],
      // This is very cheap here...
      'field_article_end_date' => str_replace('Z', '', $item['endDate']),
    ]);
    $node->setRevisionUserId(1);
    $node->setRevisionLogMessage('Created');
    $node->save();
  }

  /**
   * Updates the total count offers of the given seller.
   *
   * @param  \Drupal\node\NodeInterface  $sellerEntity
   *   The seller node.
   * @param  int  $totalArticlesCount
   *   Total available articles of this seller.
   */
  protected function updateSellerTotalCount(NodeInterface $sellerEntity, int $totalArticlesCount) {
    $sellerEntity->set('field_seller_open_articles_count', $totalArticlesCount);
    $sellerEntity->save();
  }

  /**
   * Deletes the queue item.
   *
   * @param $result
   *   The query result.
   * @param  string  $mode
   *   The mode to delete.
   */
  protected function deleteQueueItem($result, string $mode = 'singleItem') {
    $query = $this->database->delete('queue_ricardoanalytic');
    if ($mode === 'singleItem') {
      $query->condition('id', $result->id);
      $query->execute();
      return;
    }
    $query->condition('nid', $result->nid);
    $query->execute();
  }

  /**
   * Get the seller entity.
   *
   * @param  array  $item
   *   The data of the seller.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The seller node or NULL on error.
   */
  protected function getSellerEntity(array $item): NodeInterface|null {
    $sellerId = $item['sellerId'];
    $sellerNode = $this->nodeStorage->loadByProperties([
      'type' => 'seller',
      'field_seller_sellerid' => $sellerId,
    ]);

    if (empty($sellerNode)) {
      $this->loggerChannelRaSellerArticles->error("No seller entity with seller-Id: $sellerId found");
      return NULL;
    }

    $sellerNode = reset($sellerNode);
    if (!$sellerNode instanceof NodeInterface) {
      $this->loggerChannelRaSellerArticles->error("Seller entity not NodeInterface seller-Id: $sellerId found");
      return NULL;
    }

    return $sellerNode;
  }

  /**
   * {@inheritDoc}
   */
  public function createSellerArticleQueue(): void {
    $entityQuery = $this->nodeStorage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'seller')
      ->condition('status', NodeInterface::PUBLISHED);
    $sellerNids = $entityQuery->execute();

    if (empty($sellerNids)) {
      return;
    }

    foreach ($sellerNids as $nid) {
      $sellerEntity = $this->nodeStorage->load($nid);
      $totalArticlesCount = $sellerEntity->get('field_seller_open_articles_count')->value;

      // New seller added: Fetch the first 'self::LIMIT_PAGES' initial.
      // The total articles count will be updated after the first run.
      if (!$totalArticlesCount || $totalArticlesCount == 0) {
        $pages = 1;
      }
      else {
        $pages = ceil($totalArticlesCount / self::ITEMS_PER_PAGE);
        $pages = min($pages, self::LIMIT_PAGES);
      }

      // Initial added seller set to 10 page and remove the init flag.
      if ($sellerEntity->get('field_seller_is_initial_create')->value) {
        $pages = 10;
        $sellerEntity->set('field_seller_is_initial_create', 0);
        $sellerEntity->save();
      }

      // Creates the pages.
      for ($i = 1; $i <= $pages; $i++) {
        $url = "https://www.ricardo.ch/de/shop/{$sellerEntity->label()}/offers/?sort=newest&page=$i&dynamic=false";
        $this->database->insert('queue_ricardoanalytic')->fields([
          'nid',
          'type',
          'data',
          'created',
        ])->values([
          'nid' => $nid,
          'type' => 'seller-articles',
          'data' => serialize('{"type":"seller-articles","url":"' . $url . '"}'),
          'created' => time(),
        ])->execute();
      }
    }
  }

}
