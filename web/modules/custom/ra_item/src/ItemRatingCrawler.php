<?php

namespace Drupal\ra_item;

use Drupal;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Exception;

/**
 * Class ItemRatingCrawler.
 */
class ItemRatingCrawler implements ItemRatingCrawlerInterface {

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
   * @var \Drupal\node\NodeInterface
   */
  protected $sellerNode;

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

  public function initItemRatingsCrawler(int $sellerNodeId) {
    try {
      $this->sellerNode = $this->entityTypeManager->getStorage('node')->load($sellerNodeId);


    } catch (Exception $e) {
      Drupal::logger('ra_item')->error($e);
      return;
    }
  }

}
