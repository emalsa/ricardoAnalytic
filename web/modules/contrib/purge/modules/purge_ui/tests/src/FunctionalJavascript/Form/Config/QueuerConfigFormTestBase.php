<?php

namespace Drupal\Tests\purge_ui\FunctionalJavascript\Form\Config;

/**
 * Testbase for \Drupal\purge_ui\Form\QueuerConfigFormBase derivatives.
 */
abstract class QueuerConfigFormTestBase extends PluginConfigFormTestBase {

  /**
   * {@inheritdoc}
   */
  protected $route = 'purge_ui.queuer_config_form';

  /**
   * {@inheritdoc}
   */
  public function setUp($switch_to_memory_queue = TRUE): void {
    parent::setUp($switch_to_memory_queue);
    if ($this->dialogRouteTest) {
      $this->route = 'purge_ui.queuer_config_dialog_form';
    }

    // Set the expected route title for the test subject.
    $label = $this->purgeQueuers->getPlugins()[$this->pluginId]['label'];
    $this->routeTitle = sprintf("Configure %s", $label);
    $this->pluginLabel = $label;
  }

  /**
   * {@inheritdoc}
   */
  protected function initializePlugin(): void {
    $this->initializeQueuersService([$this->pluginId]);
  }

}
