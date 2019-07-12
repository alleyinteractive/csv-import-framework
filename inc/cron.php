<?php
/**
 * Cron functionality
 *
 * @package CSV_Import_Framework
 */

namespace CSV_Import_Framework;

/**
 * Run the importer cron task.
 *
 * @param int $csv_id  Post ID of the stored CSV data.
 * @param int $user_id ID of user who triggered this process.
 */
function cron_runner( $csv_id, $user_id ) {
	$csv_post = CSV_Post::load( $csv_id );
	if ( ! $csv_post ) {
		// Log the error.
		return;
	}

	// Backup the current user.
	$previous_user_id = get_current_user_id();

	// Set the current user to the user who triggered the import.
	wp_set_current_user( $user_id );

	// Load and validate the importer, as well as hook in the callbacks.
	$importer = load_importer( $csv_post->get_importer_slug() );
	if ( empty( $importer ) ) {
		// Log the error.
		return;
	}

	$complete_import = false;
	if ( $csv_post->has_more_rows() ) {
		$batch_size = get_cron_batch_size( $importer );
		$rows       = $csv_post->get_next_batch( $batch_size );

		/**
		 * Trigger the import process for a batch of rows from the CSV import.
		 *
		 * @param array    $rows     Rows of CSV data.
		 * @param array    $header   Header rows from CSV data.
		 * @param CSV_Post $csv_post CSV Post object.
		 */
		do_action(
			'csv_import_framework_import_data',
			$rows,
			$csv_post->get_header(),
			$csv_post
		);

		$csv_post->move_pointer_by( $batch_size );
		if ( $csv_post->has_more_rows() ) {
			schedule_runner( $csv_post->get_id() );
		} else {
			$complete_import = true;
		}
	} else {
		$complete_import = true;
	}

	if ( $complete_import ) {
		/**
		 * Mark the import as completed.
		 *
		 * @param \WP_Post $post WP_Post object for the CSV Post.
		 */
		do_action( 'csv_import_framework_complete_import', $csv_post->get_post() );
	}

	wp_set_current_user( $previous_user_id );
}

/**
 * Schedule the next cron iteration.
 *
 * @param int $csv_id Post ID of the stored CSV data.
 */
function schedule_runner( $csv_id ) {
	if ( ! wp_next_scheduled( CRON_ACTION, [ $csv_id, get_current_user_id() ] ) ) {
		wp_schedule_single_event( time() + 5, CRON_ACTION, [ $csv_id, get_current_user_id() ] );
	}
}

/**
 * Unschedule the next cron iteration.
 *
 * @param int $csv_id Post ID of the stored CSV data.
 */
function unschedule_runner( $csv_id ) {
	$timestamp = wp_next_scheduled( CRON_ACTION, [ $csv_id, get_current_user_id() ] );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, CRON_ACTION, [ $csv_id, get_current_user_id() ] );
	}
}

/**
 * Get the cron batch size. This is filtered and can be manipualted on a
 * case-by-case basis.
 *
 * @param string $importer Importer slug. Used in the filter.
 * @return int Batch size to run imports.
 */
function get_cron_batch_size( $importer ) {
	static $batch_size;
	if ( ! $batch_size ) {
		/**
		 * Set the batch size that imports use.
		 *
		 * @param int    $batch_size Import batch size.
		 * @param string $importer   Importer slug.
		 */
		$batch_size = apply_filters( 'csv_import_framework_cron_batch_size', 100, $importer );
	}
	return $batch_size;
}
