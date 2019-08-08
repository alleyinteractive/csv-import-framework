<?php
/**
 * Importer UI Controller
 *
 * @package CSV_Import_Framework
 */

namespace CSV_Import_Framework;

/**
 * Dispatch an importer.
 */
function dispatch_importer() {
	$importer = load_importer( current_action() );
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
	$page = ! empty( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : ''; // wpcs: csrf ok.

	// Read the CSV into JSON to store, which should be more resiliant.
	ini_set( 'auto_detect_line_endings', true );
	$filehandle = fopen( $file['tmp_name'], 'r' );
	$header = fgetcsv( $filehandle );
	ini_set( 'auto_detect_line_endings', false );
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
	$result = CSV_Post::create(
		sprintf( '%s - %s', $file['name'], time() ), // title.
		$json, // data.
		parse_importer_slug( $page ) // importer slug.
	);

	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( sprintf(
			/* translators: %s: Error message */
			__( 'There was an error saving the CSV data: %s', 'csv-import-framework' ),
			$result->get_error_message()
		) ) );
	}

	/**
	 * Fire an action after we have saved the CSV.
	 *
	 * @param int    $result CSV_Post ID.
	 * @param string $page   Page to redirect to.
	 */
	do_action( 'csv_import_framework_post_save_data', $result, $page );

	// Success! Redirect to the next step in the process.
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
	$headers = $importer['headers'] ?? '';

	// Load the preview wrapper template.
	include PATH . '/templates/preview-wrapper.php';
}

/**
 * Render the default content preview, which is just an HTML table of the data
 * to be imported.
 *
 * @param \WP_Post $post Post object.
 * @param array    $importer Importer.
 */
function default_preview( \WP_Post $post, array $importer ) {
	$data   = json_decode( $post->post_content, true );
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

	$csv_post = CSV_Post::load( absint( $_POST['csv_id'] ) );
	if ( ! $csv_post ) {
		wp_die( esc_html__( 'Something went wrong, that is an invalid CSV import ID. Please try again.', 'csv-import-framework' ) );
	}

	$page = sanitize_text_field( wp_unslash( $_POST['slug'] ) );
	$importer = load_importer( $page );
	if ( empty( $importer ) ) {
		wp_die( esc_html__( 'That importer was not found! Please go back and try again.', 'csv-import-framework' ) );
	}

	if ( ! empty( $_POST['cancel'] ) ) {
		/**
		 * Trigger the cancel/delete process for a CSV import.
		 *
		 * @param \WP_Post $post Post object for the CSV data.
		 * @param string   $page The current import page, which contains the
		 *                       importer slug.
		 */
		do_action( 'csv_import_framework_cancel_import', $csv_post->get_post(), $page );
		$msg = 'cancel';
	} elseif ( ! empty( $_POST['import'] ) ) {
		schedule_runner( $csv_post->get_id() );
		$csv_post->start_import();
		$msg = 'success';
	}

	wp_redirect( get_page_url( $page, compact( 'msg' ) ) );
	exit;
}
