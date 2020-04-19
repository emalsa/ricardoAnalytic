<?php

namespace Drupal\ra_item;

use Drupal;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Exception;
use Goutte\Client;

/**
 * Class ItemRatingCrawler.
 */
class ItemRatingCrawler implements ItemRatingCrawlerInterface {

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
   * @var \Drupal\node\NodeInterface
   */
  protected $itemNode;

  /**
   * @var string
   */
  protected $sellerId;

  /**
   * @var string
   */
  protected $sellerUrl;

  /**
   * @var string
   */
  protected $sellerUrlApi;

  protected $client;

  protected $nextPage = 1;

  protected $processNextPage = TRUE;


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
   * @param $sellerId
   *
   * @throws \Exception
   */
  protected function setSeller(string $sellerNodeId) {
    if ($sellerNodeId) {

      $result = $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'seller', 'nid' => $sellerNodeId]);
      $this->sellerNode = reset($result);

      $this->sellerId = $this->sellerNode->field_seller_id_numeric->value;
      // The username and not numeric id is required for the url.
      $this->sellerUrlApi = "https://www.ricardo.ch/marketplace-spa/api/ratings/to/{$this->sellerNode->field_seller_id->value}?page=";
    }
    else {
      throw new Exception('No Seller Id is set');
    }
  }

  public function initItemRatingsCrawler(int $sellerNodeId) {
    try {
      $this->setSeller($sellerNodeId);
      $this->processPage();
      //
      //      foreach ($this->entityTypeManager->getStorage('node')->loadByProperties(['type' => ['item', 'item_article']]) as $node) {
      //        $node->delete();
      //      }

    } catch (Exception $e) {
      Drupal::logger('ra_item')->error($e);
      return;
    }
  }

  protected function processPage() {
    $this->client->request('GET', $this->sellerUrlApi . "{$this->nextPage}");
    if ($this->client->getResponse()->getStatusCode() === 200) {
      $data = json_decode($this->client->getResponse()->getContent());
      $i = 0;
      foreach ($data->list as $rating) {

        $i++;
        if ($i > 1) {
          break;
        }

        if ($this->ratingItemExists($rating->id, $rating->rating_from->id)) {
          continue;
        }

        $this->processRatingItem($rating);
      }
    }
  }

  /**
   * @param $rating
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function processRatingItem($rating) {
    $ratingNode = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'item',
      'title' => 'rating: ' . $rating->entity->details->id . ' - ' . $rating->id,
      'body' => ['value' => $rating->comment],
      'field_rating_id' => $rating->id,
      'field_item_is_sold' => TRUE,
      'field_item_rating' => $rating->value,
      'field_item_rating_date' => date('Y-m-d\TH:i:s', strtotime($rating->creation_date)),
      'field_buyer_id' => $rating->rating_from->id,
      'field_item_buyer' => $rating->rating_from->nickname,
      'field_item_seller_ref' => $this->sellerNode->id(),
      'field_article_id' => $rating->entity->details->id,

    ]);
    $ratingNode->save();
    $articleNodeId = $this->getOrCreateArticle($rating->entity->details->id, $ratingNode);
    $ratingNode->set('field_item_rating_article_ref', $articleNodeId);
    $ratingNode->save();
  }

  protected function getOrCreateArticle($articleId, $ratingNode) {
    /** @var \Drupal\node\NodeInterface $article */
    $article = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'item_article', '=')
      ->condition('field_article_id', $articleId, '=')
      ->execute();

    if (empty($article)) {
      $article = $this->entityTypeManager->getStorage('node')->create([
        'type' => 'item_article',
        'field_article_id' => $articleId,
        'field_item_rating_ref' => $ratingNode->id(),
        'title' => "Article title not set currently",
      ]);
      $article->save();
    }
    else {
      $article = reset($article);
      $article = $this->entityTypeManager->getStorage('node')->load($article);
      $article->set('field_item_rating_ref', "1110");
      $article->save();
    }
    //@Todo: Add to article queue, but on both cases?

    return $article->id();
  }

  protected function ratingItemExists($rating_id, $rating_from_id) {
    $rating = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'item', '=')
      ->condition('field_rating_id', $rating_id, '=')
      ->condition('field_buyer_id', $rating_from_id, '=')
      ->execute();

    if (empty($rating)) {
      return FALSE;
    }

    return TRUE;
  }

}
