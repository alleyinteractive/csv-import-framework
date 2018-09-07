<?php
/**
 * Preview wrapper
 *
 * @package  CSV_Import_Framework
 */

?>

<div class="wrap">
	<h1><?php esc_html_e( 'Importing:', 'csv-import-framework' ); ?> <?php echo esc_html( $name ); ?></h1>

	<form method="post" action="<?php echo esc_url( $form_url ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>" />
		<input type="hidden" name="slug" value="<?php echo esc_attr( $slug ); ?>" />
		<input type="hidden" name="csv_id" value="<?php echo esc_attr( $csv_id ); ?>" />
		<?php wp_nonce_field( 'import-or-kill', 'process_data_nonce' ); ?>

		<p class="submit">
			<?php submit_button( __( 'Import Content', 'csv-import-framework' ), 'primary', 'import', false ); ?>
			<?php submit_button( __( 'Cancel', 'csv-import-framework' ), 'delete', 'cancel', false ); ?>
		</p>

		<?php do_action( 'csv_import_framework_preview', $post ); ?>

		<p class="submit">
			<?php submit_button( __( 'Import Content', 'csv-import-framework' ), 'primary', 'import', false ); ?>
			<?php submit_button( __( 'Cancel', 'csv-import-framework' ), 'delete', 'cancel', false ); ?>
		</p>
	</form>
</div>
