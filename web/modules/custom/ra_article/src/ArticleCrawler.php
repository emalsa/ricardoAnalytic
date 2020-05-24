<?php

namespace Drupal\ra_article;

use Drupal;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Exception;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Page;

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
   * @var \HeadlessChromium\Browser\ProcessAwareBrowser
   */
  protected $browser;

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $articleNode;

  /**
   * @var string
   */
  protected $articleUrl;

  protected $browserFactory;


  /**
   * Constructs a new ArticleCrawler object.
   *
   * @param  \Drupal\Core\Entity\EntityTypeManagerInterface  $entity_type_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->browserFactory = new BrowserFactory('chromium-browser');
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
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setArticle(string $articleId) {
    $result = $this->entityTypeManager->getStorage('node')
      ->loadByProperties([
        'type' => 'item_article',
        'field_article_id' => $articleId,
      ]);

    if (empty($result)) {
      $this->articleNode = $this->entityTypeManager->getStorage('node')
        ->create([
          'type' => 'item_article',
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
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \HeadlessChromium\Exception\CommunicationException
   * @throws \HeadlessChromium\Exception\CommunicationException\CannotReadResponse
   * @throws \HeadlessChromium\Exception\CommunicationException\InvalidResponse
   * @throws \HeadlessChromium\Exception\CommunicationException\ResponseHasError
   * @throws \HeadlessChromium\Exception\EvaluationFailed
   * @throws \HeadlessChromium\Exception\NavigationExpired
   * @throws \HeadlessChromium\Exception\NoResponseAvailable
   * @throws \HeadlessChromium\Exception\OperationTimedOut
   * @throws \Exception
   */
  protected function processArticlePage() {
    // Get data
    $this->browser = $this->browserFactory->createBrowser(['noSandbox' => TRUE]);
    $page = $this->browser->createPage();
    $page->navigate($this->articleUrl)->waitForNavigation(Page::DOM_CONTENT_LOADED, 45000);

    $data = $page->evaluate('window.ricardo')->getReturnValue();
    if (isset($data['initialState']['pdp'])) {
      $pdp = $data['initialState']['pdp'];
      $this->setSeller($pdp);
      $this->setStatus($pdp);
      $this->setTitle($pdp);
      $this->setBody($pdp);
      $this->setPrice($pdp);
      $this->setSoldDate($pdp);
    }
    else {
      if (!($this->articleNode->field_item_rating_ref->isEmpty())) {
        $ratingNodeId = $this->articleNode->field_item_rating_ref->target_id;
        /** @var \Drupal\node\NodeInterface $ratingNode */
        $ratingNode = $this->entityTypeManager->getStorage('node')->load($ratingNodeId);
        if ($ratingNode && $ratingNode->bundle() === 'item' && $ratingNode instanceof NodeInterface && !($ratingNode->field_item_seller_ref->isEmpty())) {
          $this->articleNode->field_item_seller_ref->target_id = $ratingNode->field_item_seller_ref->target_id;
        }
      }

      // Is sold
      $this->articleNode->field_item_is_sold = 1;
      $this->articleNode->setPublished(FALSE);
      $this->articleNode->setTitle('Article not found: ' . $this->articleUrl);
    }

    $this->articleNode->save();
  }

  /**
   * @param $data
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function setSeller($data) {
    if (isset($data['article']['user_id'])) {
      $sellerNode = $this->entityTypeManager
        ->getStorage('node')
        ->loadByProperties(['field_seller_id_numeric' => $data['article']['user_id']]);

      if ($sellerNode) {
        $sellerNode = reset($sellerNode);
        $this->articleNode->field_item_seller_ref->target_id = $sellerNode->id();
      }
    }
  }

  /**
   * @param $data
   */
  protected function setStatus($data) {
    // Sold
    if ($data['article']['status']) {
      $this->articleNode->field_item_is_sold = 1;
    }
    else { // Active
      $this->articleNode->field_item_is_sold = 0;
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
  protected function setBody($data) {
    if (isset($data['article']['description']['html'])) {
      $this->articleNode->body->value = $data['article']['description']['html'];
      $this->articleNode->body->format = 'full_html';
      return;
    }

    $this->articleNode->body->value = 'No body value: ' . $this->articleUrl;
    $this->articleNode->body->format = 'full_html';
  }

  /**
   * @param $data
   */
  protected function setPrice($data) {
    // Start price
    if (isset($data['bid']['data']['start_price'])) {
      $this->articleNode->field_item_start_price = $data['bid']['data']['start_price'];
    }

    // Sold price
    if ($this->articleNode->field_item_is_sold->value && isset($data['bid']['data']['last_bid'])) {
      $this->articleNode->field_item_sold_price = $data['bid']['data']['last_bid'];
    }
  }

  /**
   * @param $data
   */
  protected function setSoldDate($data) {
    if (isset($data['article']['end_date'])) {
      $this->articleNode->field_item_sold_date->value = date('Y-m-d\TH:i:s', strtotime($data['article']['end_date']));
    }
  }

}
