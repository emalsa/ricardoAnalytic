<?php

namespace Drupal\ra_seller;

use Drupal;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Exception;

/**
 * Class SellerCrawler.
 */
class SellerCrawler implements SellerCrawlerInterface {

  /**
   * @var string
   */
  protected $sellerUrl;

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
   * \Drupal\Core\Config\ConfigManagerInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;


  /**
   * Constructs a new SellerCrawler object.
   *
   * @param  \Drupal\Core\Entity\EntityTypeManagerInterface  $entity_type_manager
   * @param  \Drupal\Core\Config\ConfigManagerInterface  $config_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigManagerInterface $config_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configManager = $config_manager;
  }

  /**
   * {@inheritDoc}
   */
  public function initSellerCrawling(int $nid): void {
    try {
      $this->node = $this->entityTypeManager->getStorage('node')->load($nid);
      $this->setSeller($this->node->field_seller_id->value);

      try {
        $puppeteerUrl = "https://node-puppeteer-vimooyk3pq-uc.a.run.app";
        $response = \Drupal::httpClient()->post($puppeteerUrl, [
          "json" => [
            "token" => "data-explorer",
            "url" => $this->sellerUrl,
          ],
          "headers" => ["Content-Type" => "application/json"],
        ]);
        if (!$response || !$response->getStatusCode() === 200) {
          throw new \Exception('Status code node is not 200');
        }

        $data = json_decode($response->getBody(), TRUE);

      } catch
      (\Exception $e) {
        Drupal::logger('article_crawler')->error($e->getMessage());
        throw new \Exception($e->getMessage());
      }

      if (isset($data['initialState']['userProfile'])) {
        $this->setSellerInformation($data['initialState']['userProfile']);
        $this->node->field_seller_init_process = 0;
        $this->node->setNewRevision();
        $this->node->save();
      }
    }
    catch (Exception $e) {
      Drupal::logger('ra_seller')->error($e);
      return;
    }

  }

  /**
   * @param $sellerId
   *
   * @throws \Exception
   */
  protected function setSeller(string $sellerId) {
    if (!$sellerId) {
      throw new \Exception('No Seller Id is set');
    }
    $this->sellerUrl = "https://www.ricardo.ch/de/ratings/$sellerId";
  }

  /**
   * Set seller information.
   *
   * @param  array  $sellerData
   */
  protected function setSellerInformation(array $sellerData) {
    // The numeric seller ID
    $this->node->field_seller_id_numeric = $sellerData['ratings']['list'][0]['rating_to']['id'];

    // Address
    $postalCodeAndLocation = explode(' ', $sellerData['address']);
    $this->node->field_seller_postal_code = $postalCodeAndLocation[0];
    $this->node->field_seller_location = $postalCodeAndLocation[1];
    $this->node->field_seller_member_since = $sellerData['memberSince'];

    // Figures
    $this->node->field_seller_sold_items = $sellerData['articlesSold'];
  }


}
