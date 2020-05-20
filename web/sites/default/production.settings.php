<?php
/**
 * @file
 * amazee.io Drupal 8 production environment configuration file.
 *
 * This file will only be included on production environments.
 *
 * It contains some defaults that the amazee.io team suggests, please edit them as required.
 */

$settings['hash_salt'] = '749ab2c0d06c42ae3b841b79e79875f02b3a042e43c92378cd28bd444c04d284';

// Don't show any error messages on the site (will still be shown in watchdog)
$config['system.logging']['error_level'] = 'hide';

// Expiration of cached pages on Varnish to 15 min
$config['system.performance']['cache']['page']['max_age'] = 900;

// Aggregate CSS files on
$config['system.performance']['css']['preprocess'] = 1;

// Aggregate JavaScript files on
$config['system.performance']['js']['preprocess'] = 1;

// Disabling stage file proxy on production, with that the module can be enabled even on production
$config['stage_file_proxy.settings']['origin'] = false;

$databases['default']['default'] = array(
  'driver' => 'mysql',
  'database' => 'ricardoanalytic',
  'username' => 'ricardoanalytic',
  'password' => 'MzZTt7mJM',
  'host' => 'localhost',
  'port' => 3306,
  'prefix' => '',
);

