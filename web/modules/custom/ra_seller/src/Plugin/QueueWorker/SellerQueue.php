<?php

namespace Drupal\ra_seller\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\ra_seller\SellerCrawlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the seller_queue queue worker.
 *
 * @QueueWorker (
 *   id = "seller_queue",
 *   title = @Translation("Seller information"),
 *   cron = {"time" = 30}
 * )
 */
class SellerQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The seller crawler service.
   *
   * @var \Drupal\ra_seller\SellerCrawlerInterface
   */
  protected SellerCrawlerInterface $sellerCrawler;

  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, SellerCrawlerInterface $sellerCrawler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->sellerCrawler = $sellerCrawler;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ra_seller.seller_crawler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $this->sellerCrawler->initSellerCrawling($data['nid']);
  }

}
