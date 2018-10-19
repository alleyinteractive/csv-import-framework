<?php
/**
 * Plugin Name:     CSV Import Framework
 * Plugin URI:      https://github.com/alleyinteractive/csv-import-framework/
 * Description:     A framework for building custom CSV imports
 * Author:          Alley Interactive
 * Author URI:      https://alley.co
 * Text Domain:     csv-import-framework
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         CSV_Import_Framework
 */

namespace CSV_Import_Framework;

// Define constants for use throughout the plugin.
define( __NAMESPACE__ . '\PATH', dirname( __FILE__ ) );
define( __NAMESPACE__ . '\URL', trailingslashit( plugins_url( '', __FILE__ ) ) );

const MENU_SLUG                 = 'csv-import';
const DEFAULT_IMPORT_CAPABILITY = 'manage_csv_imports';
const CRON_ACTION               = 'csvif-import-runner';
const UPLOAD_ACTION             = 'csvif-upload';
const IMPORT_OR_KILL_ACTION     = 'csvif-process-data';

// Main plugin controller.
require_once __DIR__ . '/inc/class-main.php';

// Helpers.
require_once __DIR__ . '/inc/helpers.php';

// Data Structures.
require_once __DIR__ . '/inc/data-structures.php';

// CSV Post.
require_once __DIR__ . '/inc/class-csv-post.php';

// Admin pages.
require_once __DIR__ . '/inc/admin.php';

// Cron integration.
require_once __DIR__ . '/inc/cron.php';

// UI Controller.
require_once __DIR__ . '/inc/ui-controller.php';

// Add hooks.
add_action( 'after_setup_theme', [ __NAMESPACE__ . '\Main', 'instance' ] );
add_action( 'csv_import_framework_cancel_import', __NAMESPACE__ . '\delete_csv_post', 100 );
add_action( 'csv_import_framework_complete_import', __NAMESPACE__ . '\delete_csv_post', 100 );
add_action( 'admin_post_' . UPLOAD_ACTION, __NAMESPACE__ . '\process_csv_upload' );
add_action( 'admin_post_' . IMPORT_OR_KILL_ACTION, __NAMESPACE__ . '\import_or_kill_data' );
add_action( CRON_ACTION, __NAMESPACE__ . '\cron_runner' );
add_action( 'admin_menu', __NAMESPACE__ . '\register_menu_items' );
