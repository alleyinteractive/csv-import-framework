<?php
/**
 * Default preview
 *
 * @package CSV_Import_Framework
 */

?>

<table class="widefat striped">
	<thead>
		<tr><th><?php echo implode( '</th><th>', array_map( 'esc_html', $header ) ); ?></th></tr>
	</thead>
	<tbody>
		<?php foreach ( $data as $row ) : ?>
			<tr><td><?php echo implode( '</td><td>', array_map( 'esc_html', $row ) ); ?></td></tr>
		<?php endforeach; ?>
	</tbody>
</table>
