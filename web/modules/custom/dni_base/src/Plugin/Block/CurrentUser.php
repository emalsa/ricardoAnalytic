<?php

/**
 * @file
 * Contains Drupal\example\Plugin\Block\CurrentUser.
 */

namespace Drupal\dni_base\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'CurrentUser' block.
 *
 * @Block(
 *  id = "current_user",
 *  admin_label = @Translation("current_user")
 * )
 */
class CurrentUser extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => 'current_user',
    ];
  }

  /**
   * Overrides \Drupal\block\BlockBase::blockForm().
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['welcome_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Welcome text'),
      '#description' => $this->t(''),
      '#default_value' => isset($this->configuration['welcome_text']) ? $this->configuration['welcome_text'] : '',
    ];
    return $form;
  }

  /**
   * Overrides \Drupal\block\BlockBase::blockSubmit().
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['welcome_text'] = $form_state->getValue('welcome_text');
  }
}
