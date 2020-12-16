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
    $pdp = NULL;
    $this->browser = $this->browserFactory->createBrowser(['noSandbox' => TRUE]);
    $page = $this->browser->createPage();
    $page->navigate($this->articleUrl)->waitForNavigation(Page::DOM_CONTENT_LOADED, 45000);

    $data = $page->evaluate('window.ricardo')->getReturnValue();
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
        if ($ratingNode && $ratingNode->bundle() === 'item' && $ratingNode instanceof NodeInterface && !($ratingNode->field_rating_seller_ref->isEmpty())) {
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
        $this->articleNode->field_rating_seller_ref->target_id = $sellerNode->id();
      }
    }
  }

  /**
   * @param $data
   */
  protected function setStatus($data) {
    // Sold
    if ($data['article']['status']) {
      $this->articleNode->field_article_is_sold = 1;
      $this->articleNode->field_article_has_tags = 0; // Can now be tagged
    }
    else { // Active
      $this->articleNode->field_article_is_sold = 0;
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
