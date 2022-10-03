<?php
/**
 * Admin View: Page - Status Report.
 *
 * @package ACO\Includes\Admin\Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<table class="wc_status_table widefat" cellspacing="0">
	<thead>
	<tr>
		<th colspan="6" data-export-label="Avarda Checkout Request Log">
			<h2><?php esc_html_e( 'Avarda Checkout', 'avarda-checkout-for-woocommerce' ); ?><?php echo wc_help_tip( esc_html__( 'Avarda Checkout System Status.', 'avarda-checkout-for-woocommerce' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></h2>
		</th>
	</tr>

	<?php
	$db_logs = get_option( 'krokedil_debuglog_aco', array() );
	if ( ! empty( $db_logs ) ) {
		$db_logs = array_reverse( json_decode( $db_logs, true ) );
		?>
			<tr>
				<td ><strong><?php esc_html_e( 'Time', 'avarda-checkout-for-woocommerce' ); ?></strong></td>
				<td class="help"></td>
				<td ><strong><?php esc_html_e( 'Request', 'avarda-checkout-for-woocommerce' ); ?></strong></td>
				<td ><strong><?php esc_html_e( 'Response Code', 'avarda-checkout-for-woocommerce' ); ?></strong></td>
				<td ><strong><?php esc_html_e( 'Response Message', 'avarda-checkout-for-woocommerce' ); ?></strong></td>
			</tr>
		</thead>
		<tbody>
		<?php
		foreach ( $db_logs as $log ) {

			$timestamp      = isset( $log['timestamp'] ) ? $log['timestamp'] : '';
			$log_title      = isset( $log['title'] ) ? $log['title'] : '';
			$code           = isset( $log['response']['code'] ) ? $log['response']['code'] : '';
			$body           = isset( $log['response']['body'] ) ? wp_json_encode( $log['response']['body'] ) : '';
			$error_code     = isset( $log['response']['body']['errors'] ) ? 'Error code: ' . $log['response']['body']['errors'] . '.' : '';
			$error_messages = isset( $log['response']['body']['generalError'] ) ? 'Error messages: ' . wp_json_encode( $log['response']['body']['generalError'] ) : '';

			?>
			<tr>
				<td><?php echo esc_html( $timestamp ); ?></td>
				<td class="help"></td>
				<td><?php echo esc_html( $log_title ); ?><span style="display: none;">, Response code: <?php echo esc_html( $code ); ?>, Response message: <?php echo esc_html( $body ); ?></span</td>
				<td><?php echo esc_html( $code ); ?></td>
				<td><?php echo esc_html( $body ); ?></td>
			</tr>
			<?php
		}
	} else {
		?>
		</thead>
		<tbody>
			<tr>
				<td colspan="5" data-export-label="No ACO errors"><?php esc_html_e( 'No error logs', 'avarda-checkout-for-woocommerce' ); ?></td>
			</tr>
		<?php
	}
	?>
		</tbody>
	</table>
