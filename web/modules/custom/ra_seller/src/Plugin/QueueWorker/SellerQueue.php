<?php

namespace Drupal\ra_seller\Plugin\QueueWorker;

use Drupal\Core\Annotation\QueueWorker;
use Drupal\Core\Annotation\Translation;
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

  /** @var \Drupal\ra_seller\SellerCrawlerInterface */
  protected $sellerCrawler;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, SellerCrawlerInterface $sellerCrawler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->sellerCrawler = $sellerCrawler;
  }

  /**
   * Creates an instance of the plugin.
   *
   * @param  \Symfony\Component\DependencyInjection\ContainerInterface  $container
   *   The container to pull out services used in the plugin.
   * @param  array  $configuration
   *   A configuration array containing information about the plugin instance.
   * @param  string  $plugin_id
   *   The plugin ID for the plugin instance.
   * @param  mixed  $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
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
  public function processItem($data) {
    $this->sellerCrawler->initSellerCrawling($data['nid']);
  }

}
