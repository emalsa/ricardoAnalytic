<?php

namespace Drupal\ra_rating;

use Drupal;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Exception;
use Goutte\Client;

/**
 * Class RatingCrawler.
 */
class RatingCrawler implements RatingCrawlerInterface {

  const MAX_ITEM_TO_PROCESS = 2;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Config\ConfigManagerInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $sellerNode;

  /**
   * @var string
   */
  protected $sellerId;

  /**
   * @var string
   */
  protected $sellerUrlApi;

  protected $client;

  protected $page = 1;

  protected $processNextPage;


  /**
   * Constructs a new SellerCrawler object.
   *
   * @param  \Drupal\Core\Entity\EntityTypeManagerInterface  $entity_type_manager
   * @param  \Drupal\Core\Config\ConfigManagerInterface  $config_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigManagerInterface $config_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configManager = $config_manager;
    $this->client = new Client();
  }

  /**
   *
   * @param  int  $sellerNodeId
   */
  public function initRatingsCrawler(int $sellerNodeId) {
    try {
      $this->setSeller($sellerNodeId);
      $this->processPage();
      //       $entities = $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => ['item', 'article']]);
      //       $this->entityTypeManager->getStorage('node')->delete($entities);

    }
    catch (Exception $e) {
      Drupal::logger('ra_rating')->error($e);
      return;
    }
  }

  /**
   * @param  string  $sellerNodeId
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  protected function setSeller(string $sellerNodeId) {
    if ($sellerNodeId) {
      $result = $this->entityTypeManager->getStorage('node')
        ->loadByProperties(
          [
            'type' => 'seller',
            'nid' => $sellerNodeId,
          ]);

      $this->sellerNode = reset($result);
      $this->sellerId = $this->sellerNode->field_seller_id_numeric->value;

      /// The username is required for the url and not numeric id.
      $this->sellerUrlApi = "https://www.ricardo.ch/api/mfa/ratings?sellerName={$this->sellerNode->field_seller_id->value}&ratingValue=&page=";

    }
    else {
      throw new Exception('No Seller Id is set');
    }
  }

  /**
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function processPage() {
    $this->processNextPage = TRUE;
    $this->client->request('GET', $this->sellerUrlApi . "{$this->page}");

    if ($this->client->getResponse()->getStatusCode() === 200) {
      $data = json_decode($this->client->getResponse()->getContent());
      $i = 0;

      foreach ($data->list as $rating) {
        // If we reaching ratings older than 1 year, then we abort the full crawling.
        if (strtotime($rating->creation_date) < strtotime('-1 year')) {
          $this->processNextPage = FALSE;
          $this->sellerNode->field_seller_init_process->value = 0;
          $this->sellerNode->save();
          break;
        }

        if ($this->ratingExists($rating->id, $rating->rating_from->id)) {
          // Abort if rating item exists, because we have the older already.
          if (!($this->sellerNode->field_seller_init_process->value)) {
            $this->processNextPage = FALSE;
            break;
          }
          else { //  if all ratings have to be processed, then we only continue foreach.
            continue;
          }
        }

        // Break after reached max. item (if not "init process")
        if (!($this->sellerNode->field_seller_init_process->value) && $i > self::MAX_ITEM_TO_PROCESS) {
          $this->processNextPage = FALSE;
          break;
        }

        $this->processRating($rating);
        $i++;
      }

      // Next page
      if ($this->processNextPage && $this->page <= $data->page) {
        $this->page++;
        $this->processPage();
      }

    }

  }

  /**
   * @param  string  $rating_id
   * @param  string  $rating_from_id
   *
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function ratingExists(string $rating_id, string $rating_from_id) {
    $rating = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'rating', '=')
      ->condition('field_rating_id', $rating_id, '=')
      ->condition('field_rating_buyer_id', $rating_from_id, '=')
      ->execute();

    if (empty($rating)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * @param $rating
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function processRating($rating) {
    $articleId = $rating->entity->details->id;

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
      'field_rating_article_id' => ($articleId) ? $articleId : 'not available',
    ]);

    $ratingNode->save();

    //@todo remove, only for debug (prevent creating queue article)
    // return;

    // Old ratings are available, but the article id is not given. We don't process further.
    if (!$articleId) {
      return;
    }

    $articleNodeId = $this->updateOrCreateArticle($articleId, $ratingNode);
    $ratingNode->set('field_rating_article_ref', $articleNodeId);
    $ratingNode->save();
  }

  /**
   * @param $articleId
   * @param $ratingNode
   *
   * @return int|string|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function updateOrCreateArticle($articleId, $ratingNode) {
    /** @var \Drupal\node\NodeInterface $article */
    $article = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'article', '=')
      ->condition('field_article_id', $articleId, '=')
      ->execute();

    // Create
    if (empty($article)) {
      $article = $this->entityTypeManager->getStorage('node')->create([
        'type' => 'article',
        'field_article_id' => $articleId,
        'field_article_rating_ref' => $ratingNode->id(),
        'title' => "Article from rating process... ({$ratingNode->field_rating_buyer_username->value})",
        'field_rating_article_is_processing' => 1,
        'field_rating_article_is_sold' => 0,
      ]);
      $article->save();
    }
    else { // Edit
      $article = reset($article);
      $article = $this->entityTypeManager->getStorage('node')->load($article);
      $article->set('field_article_rating_ref', $ratingNode->id());
      $article->setNewRevision();
      $article->save();
    }

    /** @var QueueFactory $queue_factory */
    $queue_service = Drupal::service('queue');
    /** @var QueueInterface $queue_item */
    $queue_item = $queue_service->get('article_queue');

    $data['article_id'] = $articleId;
    $queue_item->createItem($data);

    return $article->id();
  }

}
