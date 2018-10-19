<?php
/**
 * Admin pages and functionality
 *
 * @package CSV_Import_Framework
 */

namespace CSV_Import_Framework;

/**
 * Setup the menu structures for the plugin.
 */
function register_menu_items() {
	global $submenu;

	$importers = Main::instance()->get_importers();
	if ( empty( $importers ) ) {
		return;
	}

	add_menu_page(
		__( 'Import Content', 'csv-import-framework' ),
		__( 'Import Content', 'csv-import-framework' ),
		'edit_posts',
		MENU_SLUG,
		'__return_false',
		'dashicons-migrate',
		null
	);

	foreach ( $importers as $importer ) {
		add_submenu_page(
			MENU_SLUG,
			$importer['name'],
			$importer['name'],
			$importer['capability'] ?? 'edit_posts',
			"csv-importer-{$importer['slug']}",
			__NAMESPACE__ . '\dispatch_importer'
		);
	}

	// The top-level menu item for 'Import Content' is bogus, so remove it.
	if ( isset( $submenu[ MENU_SLUG ] ) ) {
		array_shift( $submenu[ MENU_SLUG ] );
	}
}
