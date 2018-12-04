<?php
/**
 * Default preview
 *
 * @package CSV_Import_Framework
 */

// Check CSV headers against hard-coded values.
$diff             = [];
$importer_headers = $importer['headers'] ?? [];
if ( ! empty( $importer_headers ) ) {

	// Determine if there is anything different between the CSV's header and
	// the hard-coded header.
	$diff = array_diff( $importer['headers'], $header );
	if ( ! empty( $diff ) ) :
		?>
		<p><?php esc_html_e( 'If you continue to import, you may experience issues with the data.', 'csv-import-framework' ); ?></p>
		<ul style="margin-left: 2rem; list-style: disc;">
			<?php foreach ( $diff as $index => $label ) : ?>
				<li>
					<?php
					printf(
						// translators: %1$s importer headers, %2$s CSV header.
						esc_html__( 'Expected header label "%1$s", but found "%2$s".', 'csv-import-framework' ),
						esc_html( $importer['headers'][ $index ] ),
						esc_html( $header[ $index ] )
					);
					?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	endif;
}
?>

<table class="widefat striped">
	<thead>
		<tr>
			<th>
				<?php
				foreach ( $header as $index => $label ) {
					if ( isset( $diff[ $index ] ) ) {
						echo '</th><th style="color: red; font-weight: bold;">';
					} else {
						echo '</th><th>';
					}
					echo esc_html( $label );
				}
				?>
			</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $data as $row ) : ?>
			<tr><td><?php echo implode( '</td><td>', array_map( 'esc_html', $row ) ); ?></td></tr>
		<?php endforeach; ?>
	</tbody>
</table>
