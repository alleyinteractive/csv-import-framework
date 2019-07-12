<?php
/**
 * CSV Post class
 *
 * @package CSV_Import_Framework
 */

namespace CSV_Import_Framework;

/**
 * Interact with CSV posts.
 */
class CSV_Post {
	/**
	 * Post object.
	 *
	 * @var \WP_Post
	 */
	protected $post;

	/**
	 * CSV Data.
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * CSV Headers.
	 *
	 * @var array
	 */
	protected $header;

	/**
	 * Import meta.
	 *
	 * @var array
	 */
	protected $import_meta;

	/**
	 * CSV_Post constructor.
	 *
	 * @param \WP_Post $post Post object for a CSV post.
	 */
	public function __construct( $post ) {
		$this->post          = $post;
		$this->data          = json_decode( $this->post->post_content, true );
		$this->header        = array_shift( $this->data );
		$default_import_meta = [
			'current_row' => 0,
			'running'     => false,
		];
		$this->import_meta   = wp_parse_args(
			get_post_meta( $this->post->ID, 'import_meta', true ),
			$default_import_meta
		);
	}

	/**
	 * Load a Post or ID into a new CSV_Post object. Failable constructor.
	 *
	 * @param \WP_Post|int $post Post ID or object.
	 * @return false|\CSV_Import_Framework\CSV_Post Object on success, false on
	 *                                              failure.
	 */
	public static function load( $post ) {
		$post = get_post( $post );
		if ( ! $post instanceof \WP_Post || POST_TYPE !== $post->post_type ) {
			return false;
		}

		return new CSV_Post( $post );
	}

	/**
	 * Create a WP_Post to store CSV data.
	 *
	 * @param string $title    CSV data title.
	 * @param array  $data     CSV data as an array.
	 * @param string $importer Importer slug.
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	public static function create( $title, $data, $importer ) {
		$post_args = [
			'post_type'    => POST_TYPE,
			'post_title'   => $title,
			'post_author'  => get_current_user_id(),
			'post_status'  => 'draft',
			// We addslashes() here because wp_insert_post() will run wp_unslash().
			'post_content' => addslashes( wp_json_encode( $data ) ),
			'meta_input'   => compact( 'importer' ),
		];
		return wp_insert_post( $post_args, true );
	}

	/**
	 * Delete the WP_Post object for this CSV Post.
	 *
	 * @return bool {@see wp_delete_post()}.
	 */
	public function delete() {
		return (bool) wp_delete_post( $this->post->ID );
	}

	/**
	 * Update import meta on the post object.
	 *
	 * @param array $new_values New values to override what's currently saved.
	 */
	public function update_import_meta( $new_values ) {
		$this->import_meta = array_merge( $this->import_meta, $new_values );
		update_post_meta( $this->post->ID, 'import_meta', $this->import_meta );
	}

	/**
	 * Get import meta as either the whole array or just one key.
	 *
	 * @param null|string $key Optional. If present, just that key's value will
	 *                         be returned.
	 * @return array|mixed If $key is null, the whole import meta array is
	 *                     returned. Otherwise, the value for the given key.
	 */
	public function get_import_meta( $key = null ) {
		return $key ? $this->import_meta[ $key ] : $this->import_meta;
	}

	/**
	 * Get the Post ID for the CSV Post.
	 *
	 * @return int Post ID
	 */
	public function get_id() {
		return $this->post->ID;
	}

	/**
	 * Get the CSV Post object.
	 *
	 * @return \WP_Post
	 */
	public function get_post() {
		return $this->post;
	}

	/**
	 * Get the CSV data, less the header row.
	 *
	 * @return array
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Get the CSV header.
	 *
	 * @return array
	 */
	public function get_header() {
		return $this->header;
	}

	/**
	 * Does this post have any more CSV rows to import?
	 *
	 * @return bool
	 */
	public function has_more_rows() {
		return $this->import_meta['running']
			&& $this->import_meta['current_row'] < count( $this->data );
	}

	/**
	 * Get a batch of rows from the CSV data, starting from the current row as
	 * stored in import meta.
	 *
	 * @param int $batch_size Batch size to process.
	 * @return array
	 */
	public function get_next_batch( $batch_size ) {
		return $this->get_rows(
			$this->get_import_meta( 'current_row' ),
			$batch_size
		);
	}

	/**
	 * Move the import process pointer.
	 *
	 * @param int $size Amount to move the pointer by.
	 */
	public function move_pointer_by( $size ) {
		$this->move_pointer_to( $this->import_meta['current_row'] + $size );
	}

	/**
	 * Move the import process pointer to a specific row.
	 *
	 * @param int $position Row to which to move the pointer.
	 */
	public function move_pointer_to( $position ) {
		$this->update_import_meta(
			[
				'current_row' => $position,
			]
		);
	}

	/**
	 * Toggle the running state.
	 *
	 * @param bool $state Running state (true is running, false is stopped).
	 */
	public function toggle_running( $state ) {
		$this->update_import_meta(
			[
				'running' => $state,
			]
		);
	}

	/**
	 * Start the import and reset the pointer.
	 */
	public function start_import() {
		$this->toggle_running( true );
		$this->move_pointer_to( 0 );
	}

	/**
	 * End the import and reset the pointer.
	 */
	public function end_import() {
		$this->toggle_running( false );
		$this->move_pointer_to( 0 );
	}

	/**
	 * Get a slice of CSV data rows.
	 *
	 * @param int $start  Starting position.
	 * @param int $length Maximum number of rows to slice.
	 * @return array CSV data rows.
	 */
	public function get_rows( $start, $length ) {
		return array_slice( $this->data, $start, $length );
	}

	/**
	 * Get the slug of the CSV post's intended importer.
	 *
	 * @return string Importer slug.
	 */
	public function get_importer_slug() {
		return get_post_meta( $this->post->ID, 'importer', true );
	}
}
