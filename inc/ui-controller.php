<?php
/**
 * Importer UI Controller
 *
 * @package CSV_Import_Framework
 */

namespace CSV_Import_Framework;

const UPLOAD_ACTION = 'csvif-upload';
const IMPORT_OR_KILL_ACTION = 'csvif-process-data';

add_action( 'csv_import_framework_delete_data', __NAMESPACE__ . '\delete_csv_post', 100 );
add_action( 'admin_post_' . UPLOAD_ACTION, __NAMESPACE__ . '\process_csv_upload' );
add_action( 'admin_post_' . IMPORT_OR_KILL_ACTION, __NAMESPACE__ . '\import_or_kill_data' );

/**
 * Find an importer by its slug, check the current user's capabilities, and hook
 * up the relevant callbacks.
 *
 * @param string $slug Importer slug.
 * @return array|false Importer data on success, false on failure.
 */
function load_importer( $slug ) {
	$importer = Main::instance()->get_importer( $slug );
	if ( ! $importer ) {
		return false;
	}

	$importer = wp_parse_args( $importer, [
		'capability'       => DEFAULT_IMPORT_CAPABILITY,
		'preview_callback' => __NAMESPACE__ . '\default_preview',
		'cancel_callback'  => null,
		'import_callback'  => null,
	] );

	if ( ! current_user_can( $importer['capability'] ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to run CSV imports.', 'csv-import-framework' ) );
	}

	// Hook in the preview handler.
	add_action( 'csv_import_framework_preview', $importer['preview_callback'] );

	// Hook in the cancel handler, if present.
	if ( is_callable( $importer['cancel_callback'] ) ) {
		add_action( 'csv_import_framework_delete_data', $importer['cancel_callback'], 10, 2 );
	}

	// Hook in the import handler, if present.
	if ( is_callable( $importer['import_callback'] ) ) {
		add_action( 'csv_import_framework_import_data', $importer['import_callback'], 10, 2 );
	}

	return $importer;
}

/**
 * Dispatch an importer.
 */
function dispatch_importer() {
	$importer_slug = str_replace( 'import-content_page_csv-importer-', '', current_action() );
	$importer = load_importer( $importer_slug );
	if ( empty( $importer ) ) {
		wp_die( esc_html__( 'That importer was not found! Please go back and try again.', 'csv-import-framework' ) );
	}

	if ( ! empty( $_GET['csv_id'] ) ) {
		do_preview( absint( $_GET['csv_id'] ), $importer );
	} else {
		do_upload_form( $importer );
	}
}

/**
 * Render the upload form.
 *
 * @param array $importer Importer data.
 */
function do_upload_form( array $importer ) {
	// Setup template variables.
	$name = $importer['name'];
	$slug = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	$form_url = admin_url( 'admin-post.php' );
	$action = UPLOAD_ACTION;
	$message = '';
	if ( ! empty( $_GET['msg'] ) ) {
		switch ( $_GET['msg'] ) { // wpcs: sanitization ok.
			case 'cancel':
				$message = __( 'Import cancelled and data deleted', 'csv-import-framework' );
				break;

			case 'success':
				$message = __( 'Import is processing, it may take some time to complete.', 'csv-import-framework' );
				break;
		}
	}

	// Load the uploader template.
	include PATH . '/templates/upload.php';
}

/**
 * Process a CSV upload.
 */
function process_csv_upload() {
	check_admin_referer( 'upload-csv', 'upload_nonce' );

	if ( empty( $_FILES['csv_upload'] ) ) {
		wp_die( esc_html__( 'Unable to access uploaded file!', 'csv-import-framework' ) );
	}

	$file = $_FILES['csv_upload']; // wpcs: sanitization ok.

	// Overwrite the post action so `wp_handle_upload()` does additional checks.
	$_POST['action'] = 'wp_handle_upload';

	// This filter will save the CSV data and then ultimately kill the request.
	add_filter( 'pre_move_uploaded_file', __NAMESPACE__ . '\store_csv_upload', 10, 2 );

	$result = wp_handle_upload( $file, [
		'mimes' => [
			'csv' => 'text/csv',
		],
	] );
}

/**
 * Store an uploaded CSV file in a Post object and redirect.
 *
 * This is only hooked in following a csv upload and will kill the current
 * request, redirecting to the import preview.
 *
 * @throws \Exception If CSV data is invalid.
 *
 * @param string $move_new_file If null (default) move the file after the upload.
 * @param string $file          An array of data for a single file.
 */
function store_csv_upload( $move_new_file, $file ) {
	// Read the CSV into JSON to store, which should be more resiliant.
	$filehandle = fopen( $file['tmp_name'], 'r' );
	$header = fgetcsv( $filehandle );
	$col_expectation = count( $header );
	$json = [ $header ];
	$row = 1;
	while ( false !== ( $data = fgetcsv( $filehandle ) ) ) { // @codingStandardsIgnoreLine
		$row++;
		try {
			// Validate the data from the CSV row.
			if ( count( $data ) > $col_expectation ) {
				throw new \Exception( sprintf(
					/* translators: %1$d: row number, %2$d: number of columns in row, %3$d: expected number of columns */
					__( 'Row %1$d has %2$d columns, expecting %3$d or fewer columns', 'csv-import-framework' ),
					$row,
					count( $data ),
					$col_expectation
				) );
			}

			// Data looks good!
			$json[] = $data;
		} catch ( \Exception $e ) {
			wp_die( sprintf(
				'<h1>%s</h1><p>%s</p>',
				esc_html__( 'Error parsing CSV!', 'csv-import-framework' ),
				esc_html( $e->getMessage() )
			) );
		}
	} // End while().
	fclose( $filehandle );

	/**
	 * Filter the processed CSV data before saving.
	 *
	 * @param array $json CSV data ready to be converted to JSON.
	 * @param array $file Uploaded file.
	 */
	$json = apply_filters( 'csv_import_framework_pre_save_data', $json, $file );

	// Delete the uploaded file.
	unlink( $file['tmp_name'] );

	// Copy the contents of the file into a new post, then delete the file.
	$result = wp_insert_post(
		[
			'post_type'    => POST_TYPE,
			'post_title'   => sprintf( '%s - %s', $file['name'], time() ),
			'post_author'  => get_current_user_id(),
			'post_status'  => 'draft',
			// We addslashes() here because wp_insert_post() will run wp_unslash().
			'post_content' => addslashes( wp_json_encode( $json ) ),
		],
		true
	);

	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( sprintf(
			/* translators: %s: Error message */
			__( 'There was an error saving the CSV data: %s', 'csv-import-framework' ),
			$result->get_error_message()
		) ) );
	}

	// Success! Redirect to the next step in the process.
	$page = ! empty( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : ''; // wpcs: csrf ok.
	wp_redirect( get_page_url( $page, [ 'csv_id' => $result ] ) );
	exit;
}

/**
 * Render the preview page (wrapper).
 *
 * @param int   $post_id  CSV Import post ID.
 * @param array $importer Importer data.
 */
function do_preview( $post_id, array $importer ) {
	$post = get_post( $post_id );
	if ( ! $post instanceof \WP_Post ) {
		wp_die( esc_html__( 'Invalid CSV ID', 'csv-import-framework' ) );
	}
	$name = $importer['name'];
	$slug = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	$form_url = admin_url( 'admin-post.php' );
	$action = IMPORT_OR_KILL_ACTION;
	$csv_id = $post->ID;

	// Load the preview wrapper template.
	include PATH . '/templates/preview-wrapper.php';
}

/**
 * Render the default content preview, which is just an HTML table of the data
 * to be imported.
 *
 * @param \WP_Post $post Post object.
 */
function default_preview( \WP_Post $post ) {
	$data = json_decode( $post->post_content, true );
	$header = array_shift( $data );

	// Load the default preview table view.
	include PATH . '/templates/default-preview.php';
}

/**
 * Process the data import decision, which is either to import or cancel.
 */
function import_or_kill_data() {
	check_admin_referer( 'import-or-kill', 'process_data_nonce' );

	if (
		empty( $_POST['csv_id'] ) || empty( $_POST['slug'] )
		|| ( empty( $_POST['cancel'] ) && empty( $_POST['import'] ) )
	) {
		wp_die( esc_html__( 'That request is not valid, please go back and try again.', 'csv-import-framework' ) );
	}

	$post = get_post( absint( $_POST['csv_id'] ) );
	if ( ! $post instanceof \WP_Post || POST_TYPE !== $post->post_type ) {
		wp_die( esc_html__( 'Something went wrong, that is an invalid CSV import ID. Please try again.', 'csv-import-framework' ) );
	}

	$page = sanitize_text_field( wp_unslash( $_POST['slug'] ) );

	if ( ! empty( $_POST['cancel'] ) ) {
		do_action( 'csv_import_framework_delete_data', $post, $page );
		$msg = 'cancel';
	} elseif ( ! empty( $_POST['import'] ) ) {
		do_action( 'csv_import_framework_import_data', $post, $page );
		$msg = 'success';
	}

	wp_redirect( get_page_url( $page, compact( 'msg' ) ) );
	exit;
}

/**
 * Delete a CSV Import post object.
 *
 * @param \WP_Post $post Post object.
 * @return bool True on success, false on failure.
 */
function delete_csv_post( \WP_Post $post ) {
	if ( POST_TYPE === $post->post_type ) {
		return (bool) wp_delete_post( $post->ID );
	}
	return false;
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
