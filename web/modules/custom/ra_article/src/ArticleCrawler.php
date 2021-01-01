<?php

namespace Drupal\ra_article;

use Drupal;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Exception;

/**
 * Class ArticleCrawler.
 */
class ArticleCrawler implements ArticleCrawlerInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $articleNode;

  /**
   * @var string
   */
  protected $articleUrl;


  /**
   * Constructs a new ArticleCrawler object.
   *
   * @param  \Drupal\Core\Entity\EntityTypeManagerInterface  $entity_type_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * @param $articleId
   */
  public function processArticle($articleId) {
    try {
      $this->setArticle($articleId);
      $this->processArticlePage();
    }
    catch (Exception $exception) {
      Drupal::logger('article_crawler')
        ->error($exception->getMessage() . ' - articleId: ' . $articleId);
    }
  }

  /**
   * @param  string  $articleId
   *
   */
  public function setArticle(string $articleId) {
    $result = $this->entityTypeManager->getStorage('node')
      ->loadByProperties([
        'type' => 'article',
        'field_article_id' => $articleId,
      ]);

    if (empty($result)) {
      $this->articleNode = $this->entityTypeManager->getStorage('node')
        ->create([
          'type' => 'article',
          'field_article_id' => $articleId,
          'title' => 'Article title not set. Will be changed when processing article self',
        ]);

      $this->articleNode->save();
    }
    else {
      $this->articleNode = reset($result);
    }

    $this->articleUrl = 'http://ricardo.ch/de/s/';
    $this->articleUrl = $this->articleUrl . $articleId;
  }

  /**
   *
   */
  protected function processArticlePage() {
    //@todo: add node container health check

    // Get the data
    try {
      $puppeteerUrl = "https://node-puppeteer-vimooyk3pq-uc.a.run.app";

      $response = \Drupal::httpClient()->post($puppeteerUrl, [
        "json" => [
          "token" => "data-explorer",
          "url" => $this->articleUrl,
        ],
        "headers" => ["Content-Type" => "application/json"],
      ]);
      if (!$response || !$response->getStatusCode() === 200) {
        throw new \Exception('Status code node is not 200');
      }

      $data = json_decode($response->getBody(), TRUE);

    } catch (\Exception $e) {
      Drupal::logger('article_crawler')->error($e->getMessage());
      throw new \Exception($e->getMessage());
    }

    // @todo: add handling when accessing node container failed.
    // maybe add a counter. Info:Will have a health for the node container to
    // prevent article will handled as error from ricardo when container can't
    // be accessed.

    if (isset($data['initialState']['pdp'])) {
      $data = $data['initialState']['pdp'];
      $this->setSeller($data);
      $this->setStatus($data);
      $this->setTitle($data);
      $this->setDescription($data);
      $this->setPrice($data);
      $this->setSoldDate($data);
    }
    else {
      if (!($this->articleNode->field_article_rating_ref->isEmpty())) {
        $ratingNodeId = $this->articleNode->field_article_rating_ref->target_id;
        /** @var \Drupal\node\NodeInterface $ratingNode */
        $ratingNode = $this->entityTypeManager->getStorage('node')->load($ratingNodeId);
        if ($ratingNode && $ratingNode->bundle() === 'rating' && $ratingNode instanceof NodeInterface && !($ratingNode->field_rating_seller_ref->isEmpty())) {
          $this->articleNode->field_rating_seller_ref->target_id = $ratingNode->field_rating_seller_ref->target_id;
        }
      }

      // Is sold
      $this->articleNode->field_article_is_sold = 1;
      $this->articleNode->setPublished(FALSE);
      $this->articleNode->setTitle('Article not found: ' . $this->articleUrl);
    }

    $this->articleNode->save();
  }

  /**
   * @param $data
   */
  protected function setSeller($data) {
    if (isset($data['article']['user_id'])) {
      $sellerNode = $this->entityTypeManager
        ->getStorage('node')
        ->loadByProperties(['field_seller_id_numeric' => $data['article']['user_id']]);

      if ($sellerNode) {
        $sellerNode = reset($sellerNode);
        $this->articleNode->field_article_seller_ref->target_id = $sellerNode->id();
      }
    }
  }

  /**
   * Status
   * - 1 is closed
   * - 0 is open
   * "1" doesn't mean if the article was sold at the end.
   *
   * @param $data
   */
  protected function setStatus($data) {
    //@todo: Status 1 means the offer is closed, but not if the article was sold.
    // for auction we have bid counts, but what is the behavior
    // for "Sofort-Kaufen"?
    if ($data['article']['status']) {
      $this->articleNode->field_article_is_sold = 1;
      $this->articleNode->field_article_is_processing = 0;
      $this->articleNode->field_article_has_tags = 0;
    }
    else { // Active
      $this->articleNode->field_article_is_sold = 0;
      $this->articleNode->field_article_is_processing = 1;
    }
  }

  /**
   * @param $data
   */
  protected function setTitle($data) {
    if (isset($data['article']['title'])) {
      $this->articleNode->setTitle($data['article']['title']);
      return;
    }

    $this->articleNode->setTitle('No title: ' . $this->articleUrl);
  }

  /**
   * @param $data
   */
  protected function setDescription($data) {
    if (isset($data['article']['description']['html'])) {
      $this->articleNode->field_article_description->value = $data['article']['description']['html'];
      $this->articleNode->field_article_description->format = 'full_html';
      return;
    }

    $this->articleNode->field_article_description->value = 'No description text: ' . $this->articleUrl;
    $this->articleNode->field_article_description->format = 'full_html';
  }

  /**
   * @param $data
   */
  protected function setPrice($data) {
    // Fixed price
    if ($data['article']['offer']['offer_type'] === 'fixed_price') {
      $this->articleNode->field_article_start_price = $data['article']['offer']['price'];
      // Sold price
      // @Todo: Handle fixed price if sold or not?
      if ($this->articleNode->field_article_is_sold->value) {
        $this->articleNode->field_article_final_price = $data['article']['offer']['price'];
      }
    }
    // Auction
    elseif ($data['article']['offer']['offer_type'] === 'auction') {
      // Start price
      if (isset($data['bid']['data']['start_price'])) {
        $this->articleNode->field_article_start_price = $data['bid']['data']['start_price'];
      }
      // Sold price
      if ($this->articleNode->field_article_is_sold->value && isset($data['bid']['data']['last_bid'])) {
        $this->articleNode->field_article_final_price = $data['bid']['data']['last_bid'];
      }
    }
  }

  /**
   * @param $data
   */
  protected function setSoldDate($data) {
    if (isset($data['article']['end_date'])) {
      $this->articleNode->field_article_sold_date->value = date('Y-m-d\TH:i:s', strtotime($data['article']['end_date']));
    }
  }

}
