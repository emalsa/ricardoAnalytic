<?php

namespace Drupal\ra_seller;

use Drupal;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Exception;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Page;

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
   * \Drupal\Core\Config\ConfigManagerInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  protected $browserFactory;

  /**
   * @var \HeadlessChromium\Browser\ProcessAwareBrowser
   */
  protected $browser;


  /**
   * Constructs a new SellerCrawler object.
   *
   * @param  \Drupal\Core\Entity\EntityTypeManagerInterface  $entity_type_manager
   * @param  \Drupal\Core\Config\ConfigManagerInterface  $config_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigManagerInterface $config_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configManager = $config_manager;
    $this->browserFactory = new BrowserFactory('chromium-browser');
  }

  /**
   * Init crawler and get sellers page
   *
   * @param  int  $nid
   */
  public function initSellerCrawling(int $nid) {
    try {
      $this->sellerData = NULL;
      $this->node = $this->entityTypeManager->getStorage('node')->load($nid);
      $this->setSeller($this->node->field_seller_id->value);

      $this->browser = $this->browserFactory->createBrowser(['noSandbox' => TRUE]);
      $page = $this->browser->createPage();
      $page->navigate($this->sellerUrl)->waitForNavigation(Page::DOM_CONTENT_LOADED);
      $data = $page->evaluate('window.ricardo')->getReturnValue();
      if (isset($data['initialState']['userProfile'])) {
        $this->setSellerInformation($data['initialState']['userProfile']);
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
   * Set seller information.
   *
   * @param  array  $sellerData
   */
  protected function setSellerInformation(array $sellerData) {
    // Seller Id numeric
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
