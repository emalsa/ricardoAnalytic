<?php

namespace Drupal\ra_article;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Goutte\Client;

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

  protected $client;

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $articleNode;

  protected $articleUrl = "http://ricardo.ch/de/s/";

  /**
   * Constructs a new ArticleCrawler object.
   *
   * @param  \Drupal\Core\Entity\EntityTypeManagerInterface  $entity_type_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->client = new Client();

  }

  /**
   * @param $articleId
   */
  public function processArticle($articleId) {
    try {
      $this->setArticle($articleId);
      $this->processArticlePage();
    } catch (\Exception $exception) {

    }
  }

  /**
   *
   */
  protected function processArticlePage() {
    $this->client->request('GET', $this->articleUrl);
    if ($this->client->getResponse()->getStatusCode === 200) {
      $data = json_decode($this->client->getResponse()->getContent());
    }

  }

  /**
   * @param  string  $articleId
   */
  public function setArticle(string $articleId) {
    $result = $this->entityTypeManager->getStorage('node')->loadByProperties(
      [
        'type' => 'item_article',
        'field_article_id' => $articleId,
      ]);

    if (empty($result)) {
      $this->articleNode = $this->entityTypeManager->getStorage('node')->create([
        'type' => 'item_article',
        'field_article_id' => $articleId,
        'title' => "Article title not set. Will be changed when processing article self",
      ]);
      $this->articleNode->save();
    }
    else {
      $this->articleNode = reset($result);
    }

    $this->articleUrl = $this->articleUrl . $articleId;
  }

}
