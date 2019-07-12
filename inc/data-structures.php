<?php
/**
 * Data Structures
 *
 * @package CSV_Import_Framework
 */

namespace CSV_Import_Framework;

/**
 * Hidden post type to hold the uploaded CSV data.
 */
const POST_TYPE = 'csvif-data';

/**
 * Setup data structures used by the plugin.
 */
function setup_data_structures() {
	// Register the hidden post type that holds the csv data.
	register_post_type(
		POST_TYPE,
		[
			'public'     => false,
			'can_export' => false,
		]
	);
}
add_action( 'init', __NAMESPACE__ . '\setup_data_structures' );
