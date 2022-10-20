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
 * Class ArticleDetailFetchService.
 */
class ArticleSaleService implements ArticleSaleServiceInterface {

  /**
   * The EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * Drupal\Core\Logger\LoggerChannelInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $loggerChannelRaArticleDetail;


  /**
   * Constructs a new ArticleDetailFetchService object.
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
    Connection $database) {
    $this->entityTypeManager = $entityTypeManager;
    $this->httpClient = $httpClient;
    $this->database = $database;
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
    $this->loggerChannelRaArticleDetail = $loggerChannelRaSellerArticles;
  }

  /**
   * {@inheritDoc}
   */
  public function createSaleNode(NodeInterface $article, $initialQuantity, $remainingQuantity, $price) {
    // Determine how many items has been sold and create Sale node if necessary.
    // Maybe we created some Sale node already in the further process.
    /** @var \Drupal\node\NodeStorage $nodeStorage */
    $nodeStorage = \Drupal::service('entity_type.manager')->getStorage('node');
    $existingSaleNodes = $nodeStorage->loadByProperties([
      'type' => 'sale',
      'field_sale_article_ref' => $article->id(),
    ]);

    if ($remainingQuantity > 0) {
      $soldItems = $initialQuantity - $remainingQuantity;
      $saleNodeToCreate = $soldItems - count($existingSaleNodes);
    }
    else {
      $saleNodeToCreate = 0;
    }
    for ($i = 0; $i < $saleNodeToCreate; $i++) {
      $sale = Node::create([
        'type' => 'sale',
        'title' => $article->getTitle(),
        'field_sale_article_ref' => $article,
        'field_sale_price' => (float) $price,
      ]);
      $sale->setRevisionUserId(1);
      $sale->setRevisionLogMessage('Created');
      $sale->save();
    }

  }


}
