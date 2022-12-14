<?php

namespace Drupal\purge_ui\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\purge\Plugin\Purge\Processor\ProcessorsServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Add a processor.
 */
class ProcessorAddForm extends ConfigFormBase {
  use CloseDialogTrait;

  /**
   * The 'purge.processors' service.
   *
   * @var \Drupal\purge\Plugin\Purge\Processor\ProcessorsServiceInterface
   */
  protected $purgeProcessors;

  /**
   * Construct a ProcessorAddForm object.
   *
   * @param \Drupal\purge\Plugin\Purge\Processor\ProcessorsServiceInterface $purge_processors
   *   The purge processors service.
   */
  final public function __construct(ProcessorsServiceInterface $purge_processors) {
    $this->purgeProcessors = $purge_processors;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('purge.processors'));
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'purge_ui.processor_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $definitions = $this->purgeProcessors->getPlugins();
    $form = parent::buildForm($form, $form_state);
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    // List all available processors.
    $options = [];
    foreach ($this->purgeProcessors->getPluginsAvailable() as $plugin_id) {
      $options[$plugin_id] = $this->t("@label: @description", [
        '@label' => $definitions[$plugin_id]['label'],
        '@description' => $definitions[$plugin_id]['description'],
      ]);
    }
    $form['id'] = [
      '#default_value' => count($options) ? key($options) : NULL,
      '#type' => 'radios',
      '#options' => $options,
    ];

    // Update the buttons and bind callbacks.
    if (count($options)) {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#button_type' => 'primary',
        '#value' => $this->t("Add"),
        '#ajax' => ['callback' => '::addProcessor'],
      ];
    }

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#weight' => -10,
      '#ajax' => ['callback' => '::closeDialog'],
    ];
    return $form;
  }

  /**
   * Add the processor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response object.
   */
  public function addProcessor(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $id = $form_state->getValue('id');
    $response->addCommand(new CloseModalDialogCommand());
    if (in_array($id, $this->purgeProcessors->getPluginsAvailable())) {
      $enabled = $this->purgeProcessors->getPluginsEnabled();
      $enabled[] = $id;
      $this->purgeProcessors->setPluginsEnabled($enabled);
      $response->addCommand(new ReloadConfigFormCommand('edit-queue'));
    }
    return $response;
  }

}
