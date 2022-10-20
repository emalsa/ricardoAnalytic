<?php

namespace Drupal\ra_admin\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'StatusBlock' block.
 *
 * @Block(
 *  id = "status_block",
 *  admin_label = @Translation("Status block"),
 * )
 */
class StatusBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Mapping bundle with workflow id.
   *
   * @var array
   */
  protected const WORKFLOW_ID_MAPPING = [
    'article' => 'editorial',
  ];

  /**
   * Status to ignore.
   *
   * @var array
   */
  protected const IGNORED_MODERATION_STATES = [
    'draft',
    'archived',
    'published',
  ];

  /**
   * The EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity nundle information.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityBundleInfo;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition) {
    $instance = new static($configuration, $pluginId, $pluginDefinition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityBundleInfo = $container->get('entity_type.bundle.info');
    $instance->connection = $container->get('database');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $formState) {
    $form = parent::blockForm($form, $formState);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $this->configuration['label'],
      '#maxlength' => 64,
      '#size' => 64,
      '#weight' => '0',
    ];

    $bundles = $this->entityBundleInfo->getBundleInfo('node');
    $bundleOptions = [];
    foreach ($bundles as $key => $bundle) {
      $bundleOptions[$key] = $bundle['label'];
    }

    $form['show_of_bundle'] = [
      '#type' => 'select',
      '#empty_option' => '- Select a value -',
      '#empty_value' => '',
      '#title' => $this->t('Which bundle:'),
      '#description' => $this->t('Which bundle should be displayed'),
      '#options' => $bundleOptions,
      '#default_value' => $this->configuration['show_of_bundle'],
    ];

    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $formState) {
    $this->configuration['label'] = $formState->getValue('label');
    $this->configuration['show_of_bundle'] = $formState->getValue('show_of_bundle');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $moderationStates = [];
    $bundle = $this->configuration['show_of_bundle'];
    $workflow = $this->entityTypeManager->getStorage('workflow')->load(self::WORKFLOW_ID_MAPPING[$bundle]);
    $allStates = $workflow->get('type_settings')['states'];

    foreach ($allStates as $key => $allState) {
      if (in_array($key, self::IGNORED_MODERATION_STATES)) {
        continue;
      }
      $moderationStates[$allState['weight']] = $key;
    }
    ksort($moderationStates);

    $query = $this->connection
      ->select('node', 'n')
      ->fields('n', ['nid']);

    $query
      ->join('content_moderation_state_field_data', 'cm', 'n.nid=cm.content_entity_id');
    $query
      ->fields('cm', ['moderation_state'])
      ->condition('n.type', $this->configuration['show_of_bundle'])
      ->condition('cm.moderation_state', $moderationStates, 'IN');
    $results = $query->execute()->fetchAllKeyed(0, 1);

    // Sort.
    $sortedResults = [];
    foreach ($results as $key => $item) {
      $sortedResults[$item][] = $key;
    }

    // Count.
    foreach ($sortedResults as $state => $value) {
      $sortedResults[$state] = count($value);
    }

    foreach ($moderationStates as $state) {
      $build['#content'][$state]['count'] = $sortedResults[$state] ?? '0';
      $build['#content'][$state]['label'] = $state;
    }

    // Sales items
    $query = $this->connection
      ->select('node', 'n')
      ->condition('n.type', 'sale');
    $countQuery = $query
      ->countQuery()
      ->execute()
      ->fetchField();

    $build['#content']['empty']['label'] = '';
    $build['#content']['empty']['count'] = '';

    $build['#content']['sales']['label'] = 'Sales';
    $build['#content']['sales']['count'] = $countQuery;

    $build['#theme'] = 'status_block';

    return $build;
  }

  /**
   * No cache here.
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
