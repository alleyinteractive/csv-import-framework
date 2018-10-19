<?php
/**
 * Main plugin class
 *
 * @package CSV_Import_Framework
 */

namespace CSV_Import_Framework;

/**
 * Setup the main functionality of the plugin.
 */
class Main {

	/**
	 * Store the singleton instance.
	 *
	 * @var Main
	 */
	private static $instance;

	/**
	 * Store registered importers.
	 *
	 * @var array
	 */
	protected $importers = [];

	/**
	 * Build the object.
	 *
	 * @access private
	 */
	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	/**
	 * Get the Singleton instance.

	 * @return Main
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Main();
			self::$instance->setup();
		}
		return self::$instance;
	}

	/**
	 * Register hooks and filters, etc.
	 */
	public function setup() {
		$this->importers = $this->register_importers();
		add_filter( 'map_meta_cap', [ $this, 'import_capability' ], 10, 2 );
	}

	/**
	 * Register custom importers.
	 *
	 * @return array Custom importers. See docs for
	 *               `csv_import_framework_register_importers` filter for more
	 *               detailed breakdown.
	 */
	protected function register_importers() {
		/**
		 * Filter the list of registered importers.
		 *
		 * @param array $importers {
		 *     Array of registered importers. The following args are for each
		 *     individual importer.
		 *
		 *     @type string   $name             Custom importer name.
		 *     @type string   $slug             Custom importer slug.
		 *     @type string   $capability       Optional. Capability required to run
		 *                                      this import. Defaults to
		 *                                      'manage_csv_imports', which requires
		 *                                      the ability to edit_posts and
		 *                                      upload_files.
		 *     @type callable $preview_callback Optional. Callback for previewing the
		 *                                      data. Defaults to rendering an HTML
		 *                                      table.
		 *     @type callable $cancel_callback  Optional. Callback for cancelling the
		 *                                      import. Regardless of passing a
		 *                                      callable here or not, the data is
		 *                                      deleted after this fires (though that
		 *                                      can be unhooked if desired).
		 *     @type callable $import_callback  Callback for importing the data. If
		 *                                      nothing is passed here or hooked into
		 *                                      the action itself, the importer is
		 *                                      useless.
		 * }
		 */
		return apply_filters( 'csv_import_framework_register_importers', [] );
	}

	/**
	 * Get the array of registered importers.
	 *
	 * @return array {@see Main::register_importers}.
	 */
	public function get_importers() {
		return $this->importers;
	}

	/**
	 * Get an importer by slug.
	 *
	 * @param string $slug Importer slug.
	 * @return array|false Importer data on success, false on failure.
	 */
	public function get_importer( $slug ) {
		foreach ( $this->get_importers() as $importer ) {
			if ( $importer['slug'] === $slug ) {
				return $importer;
			}
		}

		return false;
	}

	/**
	 * Map meta capabilities for running imports.
	 *
	 * @param array  $required_caps Returns the user's actual capabilities.
	 * @param string $cap           Capability name.
	 * @return array
	 */
	public function import_capability( $required_caps, $cap ) {
		if ( DEFAULT_IMPORT_CAPABILITY === $cap ) {
			$required_caps[] = 'upload_files';
			$required_caps[] = 'edit_posts';
		}
		return $required_caps;
	}
}
