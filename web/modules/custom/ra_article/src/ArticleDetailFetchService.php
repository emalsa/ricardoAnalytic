<?php

namespace Drupal\ra_article;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\ClientInterface;

/**
 * Class ArticleDetailFetchService.
 */
class ArticleDetailFetchService implements ArticleDetailFetchServiceInterface {

  protected const STATE_FOR_FETCH = '';

  protected const STATE_SUCCESSFUL_FETCH = '';

  protected const STATE_ERROR_FETCH = '';


  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Drupal\Core\Database\Driver\mysql\Connection definition.
   *
   * @var \Drupal\Core\Database\Driver\mysql\Connection
   */
  protected $database;

  protected $logger;

  /**
   * Constructs a new ArticleDetailFetchService object.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    ClientInterface $httpClient,
    Connection $database,
    LoggerChannelInterface $logger) {
    $this->entityTypeManager = $entityTypeManager;
    $this->httpClient = $httpClient;
    $this->database = $database;
    $this->logger = $logger;
  }

  /**
   *
   */
  public function getArticleToFetch() {
    $nid = NULL;
    return $nid;
  }

  /**
   *
   */
  public function fetchArticleData($nid) {
    $this->database;
  }

}
