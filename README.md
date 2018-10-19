CSV Import Framework
====================

This plugin provides a framework for importing CSV data into WordPress. Included in that framework are:

* A CSV Uploader
* A simple preview renderer
* An asynchronous/background batched import process
* Numerous hooks to customize every facet of the process

## Instructions

To create a CSV importer, hook into the `csv_import_framework_register_importers` filter and add your importer to the provided array. As a simple example,

```php
add_filter(
	'csv_import_framework_register_importers',
	function ( $importers ) {
		$importers[] = [
			'name' => 'Sample Importer',
			'slug' => 'sample-importer',
		];
		return $importers;
	}
);
```

This will register an importer, create a page in the admin and a menu item in the Admin menu, allows administrators to upload CSV files, and will preview the uploaded CSV data. The one thing this doesn't do yet is actually process the data after the administrator confirms they want to import. To process the data, there is an action that fires, `csv_import_framework_import_data`, when a batch of data is ready to be imported. Since you'll always want to do this, as a shortcut, you can pass a callable in the key `'import_callback'` in the args when registering your importer. Updating the last example, here's what that might look like:

```php
add_filter(
	'csv_import_framework_register_importers',
	function ( $importers ) {
		$importers[] = [
			'name'            => 'Sample Importer',
			'slug'            => 'sample-importer',
			'import_callback' => function( $rows, $header ) {
				foreach ( $rows as $meta ) {
					// Remove the first column, which in this case is the title.
					array_shift( $header );
					$post_title = array_shift( $meta );

					// Build the meta as an array of [header => cell value].
					$meta_input = array_combine( $header, $meta );

					// Insert the post.
					$post_id = wp_insert_post(
						[
							'post_title'  => $post_title,
							'post_type'   => 'post',
							'post_author' => get_current_user_id(),
							'post_status' => 'draft',
							'meta_input'  => $meta_input,
						]
					);
				}
			}
		];
		return $importers;
	}
);
```

## Reference

### Importer Arguments

_String_ `$name` Custom importer name.
_String_ `$slug` Custom importer slug.
_String_ `$capability` Optional. Capability required to run this import. Defaults to 'manage_csv_imports', which requires the ability to edit_posts and upload_files.
_Callable_ `$preview_callback` Optional. Callback for previewing the data. Defaults to rendering an HTML table.
_Callable_ `$cancel_callback` Optional. Callback for cancelling the import. Regardless of passing a callable here or not, the data is deleted after this fires (though that can be unhooked if desired).
_Callable_ `$import_callback` Callback for importing the data. If nothing is passed here or hooked into the action itself, the importer is useless.

### Actions and Filters

**Action** `csv_import_framework_import_data`: Trigger the import process for a batch of rows from the CSV import.

#### Params

* `array`    `$rows`     Rows of CSV data.
* `array`    `$header`   Header rows from CSV data.
* `CSV_Post` `$csv_post` CSV Post object.


**Action** `csv_import_framework_complete_import`: Mark the import as completed.

#### Params

* `WP_Post` `$post` WP_Post object for the CSV data.


**Action** `csv_import_framework_cancel_import`: Trigger the cancel/delete process for a CSV import.

#### Params

* `\WP_Post` `$post` Post object for the CSV data.
* `string`   `$page` The current import page, which contains the importer slug.


**Filter** `csv_import_framework_preview`: Trigger the preview for an importer.

#### Params

* `\WP_Post` `$post` Post object for the CSV data.


**Filter** `csv_import_framework_register_importers`: Filter the list of registered importers.

#### Params

* `array` `$importers` Array of registered importers. See above for importer args.


**Filter** `csv_import_framework_cron_batch_size`: Set the batch size that imports use.

#### Params

* `int`    `$batch_size` Import batch size.
* `string` `$importer`   Importer slug.


**Filter** `csv_import_framework_pre_save_data`: Filter the processed CSV data before saving.

#### Params

* `array` `$json` CSV data ready to be converted to JSON.
* `array` `$file` Uploaded file.
