<?php

namespace Drupal\ra_rating;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Queue\QueueFactory;
use Goutte\Client;

/**
 * Class RatingCrawler.
 */
class RatingCrawler implements RatingCrawlerInterface {

  /**
   * Max rating item to process.
   */
  const MAX_ITEMS = 5;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The seller node object.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $sellerNode;

  /**
   * The sellers url api.
   *
   * @var string
   */
  protected $sellerUrlApi;

  /**
   * The http client.
   *
   * @var \Goutte\Client
   */
  protected $client;

  /**
   * If next rating page have to be processed.
   *
   * @var bool
   */
  protected $processNextPage;

  /**
   * The article queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queueArticle;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * {@inheritDoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, QueueFactory $queueFactory, LoggerChannelFactory $loggerChannelFactory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->client = new Client();
    $this->queueArticle = $queueFactory->get('article_queue');
    $this->logger = $loggerChannelFactory->get('ra_rating');
  }

  /**
   * Sets the seller and crawl ratings.
   *
   * @param int $sellerNodeId
   *   The seller node id.
   */
  public function initRatingsCrawler(int $sellerNodeId) {
    try {
      $this->setSeller($sellerNodeId);
      $this->processPage(1);
    }

      // $entities = $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => ['item', 'article']]);
      //       $this->entityTypeManager->getStorage('node')->delete($entities);
    catch (\Exception $e) {
      $this->logger->error($e);
      return;
    }

  }

  /**
   * Sets the seller.
   *
   * @param string $sellerNodeId
   *   The seller node id.
   */
  protected function setSeller(string $sellerNodeId) {
    $result = $this->entityTypeManager->getStorage('node')
      ->loadByProperties([
        'type' => 'seller',
        'nid' => $sellerNodeId,
      ]);

    if (empty($result)) {
      throw new \Exception('No Seller node found!');
    }

    $this->sellerNode = reset($result);
    // The username is required for the url and not numeric id.
    $this->sellerUrlApi = "https://www.ricardo.ch/api/mfa/ratings?sellerName={$this->sellerNode->field_seller_id->value}&ratingValue=&page=";
  }

  /**
   * Crawls the ratings page.
   */
  protected function processPage($page = 1) {
    $this->processNextPage = TRUE;
    $this->client->request('GET', $this->sellerUrlApi . "{$page}");
    if ($this->client->getResponse()->getStatusCode() !== 200) {
      return;
    }

    $data = json_decode($this->client->getResponse()->getContent());
    $itemsProcessed = 0;
    foreach ($data->list as $rating) {
      // If reaching ratings older than 1 year, then abort the crawling.
      if (strtotime($rating->creation_date) < strtotime('-1 year')) {
        $this->processNextPage = FALSE;
        $this->sellerNode->field_seller_init_process->value = 0;
        $this->sellerNode->save();
        break;
      }

      // Abort if rating item exists, because we have the older already.
      if ($this->ratingExists($rating->id, $rating->rating_from->id)) {
        if (!($this->sellerNode->field_seller_init_process->value)) {
          $this->processNextPage = FALSE;
          break;
        }
        // If all ratings have to be processed, then we only continue.
        continue;
      }

      // Break after reached max. items but if not "init process".
      if (!($this->sellerNode->field_seller_init_process->value) && $itemsProcessed > self::MAX_ITEMS) {
        $this->processNextPage = FALSE;
        break;
      }

      $this->processRating($rating);
      $itemsProcessed++;
    }

    // Next page.
    if ($this->processNextPage && $page <= $data->page) {
      $page++;
      $this->processPage($page);
    }

  }

  /**
   * Checks if the rating node already exists.
   *
   * @param  string  $rating_id
   *   The rating id.
   * @param  string  $rating_from_id
   *   The buyers id.
   *
   * @return bool
   *   True if exists, otherwise false
   */
  protected function ratingExists(string $rating_id, string $rating_from_id): bool {
    $rating = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'rating', '=')
      ->condition('field_rating_id', $rating_id, '=')
      ->condition('field_rating_buyer_id', $rating_from_id, '=')
      ->execute();

    return empty($rating);
  }

  /**
   * Creates the rating node with crawled data.
   *
   * @param object $rating
   *   The crawled rating data.
   */
  protected function processRating($rating) {
    // Old ratings are available, but the article id is not given.
    // We don't process further.
    if (!$rating->entity->details->id) {
      return;
    }
    $articleId = $rating->entity->details->id;

    /** @var \Drupal\node\NodeInterface $ratingNode */
    $ratingNode = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'rating',
      'title' => 'Rating for article: ' . $rating->entity->details->id . " - ({$rating->rating_from->nickname})",
      'field_rating_comment' => ['value' => $rating->comment],
      'field_rating_id' => $rating->id,
      'field_rating_type' => $rating->value,
      'field_rating_date' => date('Y-m-d\TH:i:s', strtotime($rating->creation_date)),
      'field_rating_buyer_id' => $rating->rating_from->id,
      'field_rating_buyer_username' => $rating->rating_from->nickname,
      'field_rating_seller_ref' => $this->sellerNode->id(),
      'field_rating_article_id' => $articleId,
    ]);

    $ratingNode->save();

    // @todo remove, only for debug (prevent creating queue article)
    // return;
    $articleNodeId = $this->updateOrCreateArticle($articleId, $ratingNode);
    $ratingNode->set('field_rating_article_ref', $articleNodeId);
    $ratingNode->save();
  }

  /**
   * Create or updates the article node from rating.
   *
   * @param int|string $articleId
   *   The article id.
   * @param \Drupal\node\NodeInterface $ratingNode
   *   The rating node.
   *
   * @return int|string
   *   The created article nid
   */
  protected function updateOrCreateArticle($articleId, $ratingNode) {
    $article = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'article', '=')
      ->condition('field_article_id', $articleId, '=')
      ->execute();

    // Create.
    if (empty($article)) {
      $article = $this->entityTypeManager->getStorage('node')->create([
        'type' => 'article',
        'field_article_id' => $articleId,
        'field_article_rating_ref' => $ratingNode->id(),
        'title' => "Article from rating process... ({$ratingNode->field_rating_buyer_username->value})",
        'field_article_is_processing' => 1,
      ]);
      $article->setRevisionLogMessage('Created because article was rated.');
    }
    // Edit.
    else {
      $article = reset($article);
      $article = $this->entityTypeManager->getStorage('node')->load($article);
      $article->set('field_article_rating_ref', $ratingNode->id());
      $article->set('field_article_is_processing', 1);
      $article->setRevisionLogMessage('Updated because article was rated now.');
      $article->setNewRevision();
    }

    $article->save();
    $data['article_id'] = $articleId;
    $this->queueArticle->createItem($data);

    return $article->id();
  }

}
