<?php

namespace Drupal\ra_article;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Goutte\Client;
use JonnyW\PhantomJs\Client as PhantomJS;

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
      $a = 1;
    }
  }

  /**
   *
   */
  protected function setTitle() {
    $this->client->getCrawler()->filter('h1')->each(function ($node) {
      $this->articleNode->setTitle($node->text());
    });
  }

  /**
   *
   */
  protected function setBody() {
    $this->client->getCrawler()->filter('div article')->each(function ($node) {
      $this->articleNode->body->value = $node->html();
      $this->articleNode->body->format = 'full_html';
    });

  }

  /**
   *
   */
  protected function processArticlePage() {
    $this->client->request('GET', $this->articleUrl)->html();
    if ($this->client->getResponse()->getStatusCode() === 200) {

      //      $this->setTitle();
      //      $this->setBody();
      //      $this->setPrice();


      $client = PhantomJS::getInstance();
      $client->getEngine()->setPath('/app/bin/phantomjs');
      $client->getEngine()->debug(true);

      $request = $client->getMessageFactory()->createRequest('https://google.ch', 'GET');
      $request->setTimeout(10000);

      $response = $client->getMessageFactory()->createResponse();
      $client->send($request, $response);
      if ($response->getStatus() === 200) {
        //        echo $response->getContent();
        $a = 1;
      }
      $client->getLog();
      //
      //      $this->articleNode->save();

      $a = 1;
    }
    else {
      // @Todo article not available
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
