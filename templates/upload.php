<?php
/**
 * Upload CSV template file
 *
 * @package  CSV_Import_Framework
 */

?>

<div class="wrap">
	<?php if ( ! empty( $message ) ) : ?>
		<div class="updated"><p><?php echo esc_html( $message ); ?></p></div>
	<?php endif; ?>

	<h1><?php esc_html_e( 'Importing:', 'csv-import-framework' ); ?> <?php echo esc_html( $name ); ?></h1>

	<form enctype="multipart/form-data" method="post" action="<?php echo esc_url( $form_url ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>" />
		<input type="hidden" name="slug" value="<?php echo esc_attr( $slug ); ?>" />
		<?php wp_nonce_field( 'upload-csv', 'upload_nonce' ); ?>

		<p>
			<label for="csv-upload-field"><?php esc_html_e( 'Select CSV file to upload', 'csv-import-framework' ); ?></label>
			<input type="file" name="csv_upload" id="csv-upload-field" />
		</p>

		<?php submit_button( __( 'Upload File', 'csv-import-framework' ) ); ?>
	</form>
</div>

<?php
include( ABSPATH . 'wp-admin/admin-footer.php' );
