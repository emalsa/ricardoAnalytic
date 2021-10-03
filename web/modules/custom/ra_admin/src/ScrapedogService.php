<?php

namespace Drupal\ra_admin;

use Drupal\Core\Database\Driver\mysql\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\ClientInterface;

/**
 * Class ScrapedogService.
 */
class ScrapedogService implements ScrapedogServiceInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Database\Driver\mysql\Connection definition.
   *
   * @var \Drupal\Core\Database\Driver\mysql\Connection
   */
  protected $database;

  /**
   * Drupal\Core\Logger\LoggerChannelInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Constructs a new ScrapedogService object.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    Connection $database,
    LoggerChannelInterface $loggerChannel,
    ClientInterface $httpClient
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->database = $database;
    $this->logger = $loggerChannel;
    $this->httpClient = $httpClient;
  }

  /**
   * {@inheritDoc}
   */
  public function getScrapedogApiKey() {
    $apiKey = NULL;
    return $apiKey;
  }

  /**
   * {@inheritDoc}
   */
  public function updateScrapedogRemainingCredit($nid) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if ($node->get('field_scrapingdog_api_key')->isEmpty()) {
      return;
    }
    $apiKey = $node->get('field_scrapingdog_api_key')->value;

    $client = $this->httpClient->get("https://api.scrapingdog.com/account?api_key=$apiKey");
    $content = json_decode($client->getBody()->getContents(), TRUE);
    $remainingCredits = $content['requestLimit'] - $content['requestUsed'];
    $node->set('field_scrapingdog_credits', $remainingCredits);
    $node->save();
  }

}
