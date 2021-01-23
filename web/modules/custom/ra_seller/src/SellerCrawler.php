<?php

namespace Drupal\ra_seller;

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class SellerCrawler.
 */
class SellerCrawler implements SellerCrawlerInterface {

  /**
   * The sellers url.
   *
   * @var string
   */
  protected $sellerUrl;

  /**
   * The node object.
   *
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
   * Drupal\Core\Config\ConfigManagerInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * Constructs SellerCrawler object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The config manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigManagerInterface $configManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->configManager = $configManager;
  }

  /**
   * {@inheritDoc}
   */
  public function initSellerCrawling(int $nid): void {
    try {
      $this->node = $this->entityTypeManager->getStorage('node')->load($nid);
      $this->setSeller($this->node->field_seller_id->value);

      try {
        $puppeteerUrl = "https://node-puppeteer-vimooyk3pq-uc.a.run.app/puppeteer-seller";
        $response = \Drupal::httpClient()->post($puppeteerUrl, [
          "json" => [
            'timeout' => 9000,
            "token" => "data-explorer",
            "url" => $this->sellerUrl,
          ],
          "headers" => ["Content-Type" => "application/json"],
        ]);

        if (!$response || !$response->getStatusCode() === 200) {
          throw new \Exception('Status code node is not 200');
        }

        $data = json_decode($response->getBody(), TRUE);
      }
      catch (\Exception $e) {
        \Drupal::logger('seller_crawler')->error($e->getMessage());
        throw new \Exception($e->getMessage());
      }

      if (isset($data['initialState']['userProfile'])) {
        $this->setSellerInformation($data['initialState']['userProfile']);
        $this->node->field_seller_init_process = 0;
        $this->node->setNewRevision();
        $this->node->save();
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('ra_seller')->error($e);
      return;
    }

  }

  /**
   * Sets the necessary seller properties.
   *
   * @param string $sellerId
   *   The seller id.
   */
  protected function setSeller(string $sellerId) {
    if (!$sellerId) {
      throw new \Exception('No Seller Id is set');
    }
    $this->sellerUrl = "https://www.ricardo.ch/de/ratings/$sellerId";
  }

  /**
   * Sets the seller information.
   *
   * @param array $sellerData
   *   The crawled data.
   */
  protected function setSellerInformation(array $sellerData) {
    // The numeric seller ID.
    $this->node->field_seller_id_numeric = $sellerData['ratings']['list'][0]['rating_to']['id'];

    // Address.
    $postalCodeAndLocation = explode(' ', $sellerData['address']);
    $this->node->field_seller_postal_code = $postalCodeAndLocation[0];
    $this->node->field_seller_location = $postalCodeAndLocation[1];
    $this->node->field_seller_member_since = $sellerData['memberSince'];

    // Figures.
    $this->node->field_seller_sold_items = $sellerData['articlesSold'];
  }

}
