<?php

namespace Drupal\ra_admin\Plugin\Block;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
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
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Bundle information.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityBundleInfo;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityBundleInfo = $container->get('entity_type.bundle.info');
    $instance->connection = $container->get('database');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $ajaxValues = $form_state instanceof SubformStateInterface ? $form_state->getCompleteFormState()->getValues() : $form_state->getValues();

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
      '#ajax' => [
        'callback' => [$this, 'myAjaxCallback'],
        'method' => 'html',
        'disable-refocus' => FALSE,
        'event' => 'change',
        'wrapper' => 'states-to-update',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Load states...'),
        ],
      ],
    ];

    $selectedBundle = $ajaxValues['settings']['show_of_bundle'] ?? '';
    $workflowsStates = [];
    $workflowId = NULL;
    if ($form_state->getTriggeringElement() && $selectedBundle && $bundles[$selectedBundle]['workflow']) {
      $workflowId = $bundles[$selectedBundle]['workflow'];
    }
    elseif (!$form_state->getTriggeringElement() && $this->configuration['show_of_type_revision'] && $this->configuration['show_of_bundle']) {
      $workflowId = $bundles[$this->configuration['show_of_bundle']]['workflow'];

    }

    if ($workflowId) {
      /** @var \Drupal\workflows\Entity\Workflow $workflow */
      $workflow = $this->entityTypeManager->getStorage('workflow')->load($workflowId);
      $allStates = $workflow->get('type_settings')['states'];
      foreach ($allStates as $key => $allState) {
        $workflowsStates[$key] = $allState['label'];
      }
    }

    $form['show_of_type_revision'] = [
      '#empty_option' => '- Select a value -',
      '#empty_value' => '',
      '#type' => 'select',
      '#title' => $this->t('Show of type revision:'),
      '#description' => $this->t('Which article should be displayed'),
      '#default_value' => $this->configuration['show_of_type_revision'],
      '#options' => $workflowsStates,
      '#prefix' => '<div id="states-to-update">',
      '#suffix' => '</div>',
    ];

    return $form;

  }

  /**
   *
   */
  public function myAjaxCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#states-to-update', $form['settings']['show_of_type_revision']));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['label'] = $form_state->getValue('label');
    $this->configuration['show_of_bundle'] = $form_state->getValue('show_of_bundle');
    $this->configuration['show_of_type_revision'] = $form_state->getValue('show_of_type_revision');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $query = $this->connection
      ->select('node', 'n')
      ->fields('n', ['n.nid']);
    $query
      ->join('content_moderation_state_field_data', 'cm', 'n.nid=cm.content_entity_id');
    $query
      ->condition('n.type', $this->configuration['show_of_bundle'])
      ->condition('cm.moderation_state', $this->configuration['show_of_type_revision']);
    $total = $query->countQuery()->execute()->fetchField();

    $build['#theme'] = 'status_block';
    $build['#content']['bundle'] = $this->configuration['show_of_bundle'];
    $build['#content']['moderation_state'] = $this->configuration['show_of_type_revision'];
    $build['#content']['total'] = $total;
    return $build;
  }

  /**
   * No cache here.
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
