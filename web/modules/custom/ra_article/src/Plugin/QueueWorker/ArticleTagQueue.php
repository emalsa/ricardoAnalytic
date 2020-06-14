<?php

namespace Drupal\ra_article\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the article_tag_queue queue worker.
 *
 * @QueueWorker (
 *   id = "article_tag_queue",
 *   title = @Translation("Tagging"),
 *   cron = {"time" = 30}
 * )
 */
class ArticleTagQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeEntityTypeManager, $termEntityTypeManager;

  protected $logger;

  /**
   * ArticleTagQueue constructor.
   *
   * @param  array  $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param  \Drupal\Core\Entity\EntityTypeManagerInterface  $entityTypeManager
   * @param  \Drupal\Core\Logger\LoggerChannelInterface  $logger
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, LoggerChannelInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->nodeEntityTypeManager = $entityTypeManager->getStorage('node');
    $this->termEntityTypeManager = $entityTypeManager->getStorage('taxonomy_term');
    $this->logger = $logger;
  }

  /**
   * Create.
   *
   * @param  \Symfony\Component\DependencyInjection\ContainerInterface  $container
   * @param  array  $configuration
   * @param  string  $plugin_id
   * @param  mixed  $plugin_definition
   *
   * @return \Drupal\ra_article\Plugin\QueueWorker\ArticleTagQueue
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('ra_article')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    try {
      $nid = $data['nid'];
      $node = $this->nodeEntityTypeManager->load($nid);
      if ($node instanceof NodeInterface && $node->bundle() === 'item_article') {
        $labelArray = explode(' ', $node->label());
        $node->set('field_item_article_tags', []);
        foreach ($labelArray as $label) {
          if (!empty($label)) {
            $label = strtolower($label);
            $tid = $this->getTermId($label);
            $node->field_item_article_tags->appendItem($tid);
          }
        }
        $node->field_item_is_tagged = 1;
        $node->save();
      }
    }
    catch (\Exception $exception) {
      $this->logger->error('Error on tagging article');
    }
  }

  /**
   * @param  string  $label
   *
   * @return int
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function getTermId(string $label): int {
    $term = $this->termEntityTypeManager->loadByProperties([
      'name' => $label,
      'vid' => 'tags',
    ]);

    if (empty($term)) {
      $term = $this->termEntityTypeManager->create([
        'name' => $label,
        'vid' => 'tags',
      ]);
      $term->save();
    }
    else {
      $term = reset($term);
    }

    return $term->id();
  }

}
