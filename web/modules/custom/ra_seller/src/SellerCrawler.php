<?php

namespace Drupal\ra_seller;

use Drupal;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\CachedStorage;
use Exception;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class SellerCrawler.
 */
class SellerCrawler implements SellerCrawlerInterface {

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

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Config\CachedStorage definition.
   *
   * @var \Drupal\Core\Config\CachedStorage
   */
  protected $configStorage;

  /**
   * Constructs a new SellerCrawler object.
   *
   * @param  \Drupal\Core\Entity\EntityTypeManagerInterface  $entity_type_manager
   * @param  \Drupal\Core\Config\ConfigManagerInterface  $config_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigManagerInterface $config_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configStorage = $config_manager;
  }

  /**
   * @param $sellerId
   *
   * @throws \Exception
   */
  protected function setSeller(string $sellerId) {
    if ($sellerId) {
      $this->sellerId = $sellerId;
      $this->sellerUrl = "https://www.ricardo.ch/de/ratings/$sellerId";
      $this->sellerUrlApi = "https://www.ricardo.ch/marketplace-spa/api/ratings/to/$sellerId/?page=1";
    }
    else {
      throw new Exception('No Seller Id is set');
    }
  }


  /**
   * Init crawler and get sellers page
   *
   * @param  int  $nid
   */
  public function initSellerCrawling(int $nid) {
    try {
      $this->node = $this->entityTypeManager->getStorage('node')->load($nid);
      $this->setSeller($this->node->field_seller_id->value);

      $client = new Client();
      $crawler = $client->request('GET', $this->sellerUrl);
      $this->setSellerInformation($crawler);
      $this->setSellerIdNumeric($client);
    } catch (Exception $e) {
      Drupal::logger('ra_seller')->error($e);
      return;
    }

    try {
      $this->node->setNewRevision();
      $this->node->save();
    } catch (Drupal\Core\Entity\EntityStorageException $e) {
      Drupal::logger('ra_seller')->error($e);
    }

  }

  /**
   * Get seller id numeric (ratings_to id)
   *
   * @param  \Goutte\Client  $client
   */
  protected function setSellerIdNumeric(Client $client) {
    $client->request('GET', 'https://www.ricardo.ch/marketplace-spa/api/ratings/to/keller001');
    if ($client->getResponse()->getStatus() === 200) {
      $response = $client->getResponse()->getContent();
      $response = json_decode($response);
      if (!empty($response->list) && isset($response->list[0]->rating_to->id)) {
        $this->node->field_seller_id_numeric = $response->list[0]->rating_to->id;
      }
    }
  }

  /**
   * Crawl seller information.
   *
   * @param  \Symfony\Component\DomCrawler\Crawler  $crawler
   */
  protected function setSellerInformation(Crawler $crawler) {
    $sellerInformation = $crawler->filter('p.text--3bhjl')->each(function (Crawler $htmlNode) {
      return $htmlNode->text();
    });
    $this->setLocationAndMemberSinceYear($sellerInformation[0]);
    $this->setSellerFigures($sellerInformation[1]);
  }

  /**
   * Set location and membership start year.
   *
   * @param  string  $sellerInformation
   */
  protected function setLocationAndMemberSinceYear(string $sellerInformation) {
    if (substr($sellerInformation, 0, 5) === 'place') {
      $sellerInformation = substr($sellerInformation, 5); // remove 'place'
      $sellerInformation = explode(' ', $sellerInformation);

      $this->node->field_postal_code = $sellerInformation[0];
      $this->node->field_location = $sellerInformation[1];
      $this->node->field_member_since = $sellerInformation[5];
    }
  }

  /**
   * Set key figures (items sold, ...).
   *
   * @param  string  $sellerInformation
   */
  protected function setSellerFigures(string $sellerInformation) {
    $sellerInformation = explode(' ', $sellerInformation);
    $soldItems = str_replace("'", '', $sellerInformation[0]);
    $this->node->field_items_sold = $soldItems;
  }
}
