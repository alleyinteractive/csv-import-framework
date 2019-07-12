<?php
/**
 * Assorted helpers
 *
 * @package CSV_Import_Framework
 */

namespace CSV_Import_Framework;

/**
 * Find an importer by its slug, check the current user's capabilities, and hook
 * up the relevant callbacks.
 *
 * @param string $slug Importer slug. Can also be admin page or page action
 *                     {@see parse_importer_slug()}.
 * @return array|false Importer data on success, false on failure.
 */
function load_importer( $slug ) {
	$slug     = parse_importer_slug( $slug );
	$importer = Main::instance()->get_importer( $slug );
	if ( ! $importer ) {
		return false;
	}

	$importer = wp_parse_args(
		$importer,
		[
			'capability'       => DEFAULT_IMPORT_CAPABILITY,
			'preview_callback' => __NAMESPACE__ . '\default_preview',
			'cancel_callback'  => null,
			'import_callback'  => null,
		]
	);

	if ( ! current_user_can( $importer['capability'] ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to run CSV imports.', 'csv-import-framework' ) );
	}

	// Hook in the preview handler.
	add_action( 'csv_import_framework_preview', $importer['preview_callback'], 10, 2 );

	// Hook in the cancel handler, if present.
	if ( is_callable( $importer['cancel_callback'] ) ) {
		add_action( 'csv_import_framework_cancel_import', $importer['cancel_callback'], 10, 2 );
	}

	// Hook in the import handler, if present.
	if ( is_callable( $importer['import_callback'] ) ) {
		add_action( 'csv_import_framework_import_data', $importer['import_callback'], 10, 3 );
	}

	return $importer;
}

/**
 * Parse the importer slug from a page or action.
 *
 * @param string $page_or_action Page key or action name.
 * @return string Import slug.
 */
function parse_importer_slug( $page_or_action ) {
	return str_replace(
		[ 'import-content_page_', 'csv-importer-' ],
		'',
		$page_or_action
	);
}

/**
 * Helper to get a page URL against admin.php.
 *
 * @param string $page       Page to load (page param in URL).
 * @param array  $query_args Optional. Additional query args for URL.
 * @return string Admin page URL.
 */
function get_page_url( $page, array $query_args = [] ) {
	$query_args['page'] = $page;
	return add_query_arg( $query_args, admin_url( 'admin.php' ) );
}

/**
 * Delete a CSV Import post object.
 *
 * @param \WP_Post $post Post object.
 * @return bool True on success, false on failure.
 */
function delete_csv_post( \WP_Post $post ) {
	$csv_post = CSV_Post::load( $post );
	if ( $csv_post ) {
		return $csv_post->delete();
	}
	return false;
}
