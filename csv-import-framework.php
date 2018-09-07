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

define( __NAMESPACE__ . '\PATH', dirname( __FILE__ ) );
define( __NAMESPACE__ . '\URL', trailingslashit( plugins_url( '', __FILE__ ) ) );

// Main plugin controller.
require_once __DIR__ . '/inc/class-main.php';

// Data Structures.
require_once __DIR__ . '/inc/data-structures.php';

// Admin pages.
require_once __DIR__ . '/inc/admin.php';

// UI Controller.
require_once __DIR__ . '/inc/ui-controller.php';
