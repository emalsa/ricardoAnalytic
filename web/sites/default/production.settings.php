<?php
/**
 * @file
 * amazee.io Drupal 8 production environment configuration file.
 *
 * This file will only be included on production environments.
 *
 * It contains some defaults that the amazee.io team suggests, please edit them
 *   as required.
 */

$databases['default']['default'] = [
  'driver' => 'mysql',
  'database' => 'ricardoanalytic',
  'username' => 'ricardoanalytic',
  'password' => 'MzZTt7mJM',
  'host' => '127.0.0.1',
  'port' => 3306,
  'prefix' => '',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
];

$settings['config_sync_directory'] = '../config/sync';

$settings['trusted_host_patterns'] = [
  '^nicastro\.io$',
  '^.+\.nicastro\.io$',
  '^nicastro\.io$',
  '^.+\.nicastro\.io$',
];

$settings['hash_salt'] = 'whatever-i-like-29292929';
$settings['file_temp_path'] = '/tmp';
$settings['file_public_path'] = 'sites/default/files';
$settings['file_public_base_url'] = 'https://ricardoanalytic.nicastro.io/sites/default/files';


// Don't show any error messages on the site (will still be shown in watchdog)
$config['system.logging']['error_level'] = 'all';

// Expiration of cached pages on Varnish to 15 min
$config['system.performance']['cache']['page']['max_age'] = 300;

// Aggregate CSS files on
$config['system.performance']['css']['preprocess'] = 1;

// Aggregate JavaScript files on
$config['system.performance']['js']['preprocess'] = 1;

// Disabling stage file proxy on production, with that the module can be enabled even on production
$config['stage_file_proxy.settings']['origin'] = FALSE;
