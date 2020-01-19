<?php

namespace Drupal\dni_base\Plugin\Block;

use Drupal\Console\Bootstrap\Drupal;
use Drupal\Core\Block\BlockBase;


/**
 * Provides current user block
 *
 * @Block(
 *   id = "current_user_block",
 *   admin_label = "Current User",
 *   category = "dni_base",
 * )
 */
class CurrentUserBlock extends BlockBase {

  /**
   * @inheritDoc
   */
  public function build() {
    /** @var  $current_user */
    $current_user = \Drupal::service('current_user');
    $current_user->getAccount()->getUsername();
    return [
      '#markup' => 'current_user',
    ];
  }

}
