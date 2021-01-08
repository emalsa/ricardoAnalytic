<?php

namespace Drupal\ra_article;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Class ArticleCrawler.
 */
class ArticleCrawler implements ArticleCrawlerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The node object.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $articleNode;

  /**
   * The article url.
   *
   * @var string
   */
  protected string $articleUrl;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritDoc}
   */
  public function processArticle(string $articleId) {
    try {
      $this->setArticle($articleId);
      $this->processArticlePage();
    }
    catch (\Exception $exception) {
      \Drupal::logger('article_crawler')
        ->error('articleId: - ' . $articleId . '--' . $exception->getMessage());
    }
  }

  /**
   * Queries the article from given article id.
   *
   * @param string $articleId
   *   The ricardo's article id.
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
   * Send data to the crawler and process data.
   */
  protected function processArticlePage() {
    // @todo add node container health check
    // Get the data
    try {
      $puppeteerUrl = "https://node-puppeteer-vimooyk3pq-uc.a.run.app/puppeteer";
      $response = \Drupal::httpClient()->post($puppeteerUrl, [
        "json" => [
          'timeout' => 6000,
          "token" => "data-explorer",
          "url" => $this->articleUrl,
        ],
        "headers" => ["Content-Type" => "application/json"],
      ]);

      if (!$response || !$response->getStatusCode() === 200) {
        throw new \Exception('Status code node is not 200');
      }

      $data = json_decode($response->getBody(), TRUE);
      $this->articleNode->field_article_has_crawling_error = 0;

    }
    catch (\Exception $e) {
      $this->articleNode->field_article_has_crawling_error = 1;
      $this->articleNode->field_article_counter_crawling->value = $this->articleNode->field_article_counter_crawling->value + 1;
      $this->articleNode->save();
      throw new \Exception($e->getMessage());
    }

    // @todo add handling when accessing node container failed.
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
      // No data.
      $this->articleNode->field_article_is_sold = 0;
      $this->articleNode->field_article_is_processing = 0;
      $this->articleNode->setPublished(FALSE);
      $this->articleNode->setTitle('Article not found (no RDP data provided): ' . $this->articleUrl);
    }

    $this->articleNode->save();
  }

  /**
   * Set the seller in the article.
   *
   * @param array $data
   *   The articles data.
   */
  protected function setSeller(array $data) {
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
   * Sets the status (closed, open).
   *
   * - 1 is closed
   * - 0 is open
   * "1" doesn't mean if the article was sold at the end.
   *
   * @param array $data
   *   The articles data.
   */
  protected function setStatus(array $data) {
    // @todo Status 1 means the offer is closed, but it doesn't say
    // if the article was sold or not.
    // for auction we have bid counts, but what is the behavior
    // for "Sofort-Kaufen"?
    if ($data['article']['status']) {
      $this->articleNode->field_article_is_sold = 1;
      $this->articleNode->field_article_is_processing = 0;
      $this->articleNode->field_article_has_tags = 0;
    }
    // Active.
    else {
      $this->articleNode->field_article_is_sold = 0;
      $this->articleNode->field_article_is_processing = 1;
    }
  }

  /**
   * Sets the title.
   *
   * @param array $data
   *   The articles data.
   */
  protected function setTitle(array $data) {
    if (isset($data['article']['title'])) {
      $this->articleNode->setTitle($data['article']['title']);
      return;
    }

    $this->articleNode->setTitle('No title: ' . $this->articleUrl);
  }

  /**
   * Sets the article description.
   *
   * @param array $data
   *   The articles data.
   */
  protected function setDescription(array $data) {
    if (isset($data['article']['description']['html'])) {
      $this->articleNode->field_article_description->value = $data['article']['description']['html'];
      $this->articleNode->field_article_description->format = 'full_html';
      return;
    }

    $this->articleNode->field_article_description->value = 'No description text: ' . $this->articleUrl;
    $this->articleNode->field_article_description->format = 'full_html';
  }

  /**
   * Sets the price.
   *
   * @param array $data
   *   The articles data.
   */
  protected function setPrice(array $data) {
    // Fixed price.
    if ($data['article']['offer']['offer_type'] === 'fixed_price') {
      $this->articleNode->field_article_start_price = $data['article']['offer']['price'];
      // Sold price.
      // @todo Handle fixed price if sold or not?
      if ($this->articleNode->field_article_is_sold->value) {
        $this->articleNode->field_article_final_price = $data['article']['offer']['price'];
      }
    }
    // Auction.
    elseif ($data['article']['offer']['offer_type'] === 'auction') {
      // Start price.
      if (isset($data['bid']['data']['start_price'])) {
        $this->articleNode->field_article_start_price = $data['bid']['data']['start_price'];
      }
      // Sold price.
      if ($this->articleNode->field_article_is_sold->value && isset($data['bid']['data']['last_bid'])) {
        $this->articleNode->field_article_final_price = $data['bid']['data']['last_bid'];
      }
    }
  }

  /**
   * Sets the sold date (end date).
   *
   * @param array $data
   *   The articles data.
   */
  protected function setSoldDate(array $data) {
    if (isset($data['article']['end_date'])) {
      $this->articleNode->field_article_sold_date->value = date('Y-m-d\TH:i:s', strtotime($data['article']['end_date']));
    }
  }

}
