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
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeEntityTypeManager;

  /**
   * The term storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $termStorage;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelInterface $logger
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->nodeEntityTypeManager = $entityTypeManager->getStorage('node');
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
    $this->logger = $logger;
  }

  /**
   * {@inheritDoc}
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
      $node = $this->nodeStorage->load($nid);
      if ($node instanceof NodeInterface && $node->bundle() === 'article') {
        $labelArray = explode(' ', $node->label());
        $node->set('field_article_tags', []);
        foreach ($labelArray as $label) {
          if (!empty($label)) {
            $label = strtolower($label);
            $tid = $this->getTermId($label);
            $node->field_article_tags->appendItem($tid);
          }
        }
        $node->field_article_has_tags = 1;
        $node->save();
      }
    }
    catch (\Exception $exception) {
      $this->logger->error('Error on tagging article');
    }
  }

  /**
   * Gets the term id.
   *
   * @param string $label
   *   The terms name.
   *
   * @return int
   *   The term id
   */
  protected function getTermId(string $label): int {
    $term = $this->termStorage->loadByProperties([
      'name' => $label,
      'vid' => 'tags',
    ]);

    if (empty($term)) {
      $term = $this->termStorage->create([
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
