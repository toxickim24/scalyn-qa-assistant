<?php
/**
 * Partial: Single Check Item.
 *
 * Renders one QA check result row with status icon, label, message, severity,
 * quick fix action, and tooltip.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 *
 * @var array $item    Associative array with keys: id, label, status, message, severity, quick_fix, tooltip.
 * @var int   $post_id The post ID this check belongs to (optional, defaults to 0).
 */

defined( 'ABSPATH' ) || exit;

$item    = isset( $item ) ? $item : array();
$post_id = isset( $post_id ) ? (int) $post_id : 0;

$item_id    = isset( $item['id'] ) ? $item['id'] : '';
$label      = isset( $item['label'] ) ? $item['label'] : '';
$status     = isset( $item['status'] ) ? $item['status'] : 'fail';
$message    = isset( $item['message'] ) ? $item['message'] : '';
$severity   = isset( $item['severity'] ) ? $item['severity'] : 'info';
$quick_fix  = isset( $item['quick_fix'] ) ? $item['quick_fix'] : '';
$tooltip    = isset( $item['tooltip'] ) ? $item['tooltip'] : '';

$status_icons = array(
	'pass'    => 'dashicons-yes-alt',
	'warning' => 'dashicons-warning',
	'fail'    => 'dashicons-dismiss',
);

$icon_class = isset( $status_icons[ $status ] ) ? $status_icons[ $status ] : 'dashicons-marker';
?>
<div
	class="scalyn-check-item scalyn-check-item--<?php echo esc_attr( $status ); ?> scalyn-check-item--<?php echo esc_attr( $severity ); ?>"
	data-check-id="<?php echo esc_attr( $item_id ); ?>"
	data-status="<?php echo esc_attr( $status ); ?>"
	data-severity="<?php echo esc_attr( $severity ); ?>"
>
	<span class="scalyn-check-icon" aria-hidden="true">
		<span class="dashicons <?php echo esc_attr( $icon_class ); ?>"></span>
	</span>

	<div class="scalyn-check-content">
		<strong class="scalyn-check-label"><?php echo esc_html( $label ); ?></strong>
		<?php if ( ! empty( $message ) ) : ?>
			<span class="scalyn-check-message"><?php echo esc_html( $message ); ?></span>
		<?php endif; ?>
	</div>

	<div class="scalyn-check-actions">
		<?php if ( ! empty( $quick_fix ) ) : ?>
			<?php
			$action = $quick_fix;
			include SCALYN_QA_PLUGIN_DIR . 'templates/partials/quick-fix-button.php';
			?>
		<?php endif; ?>

		<?php if ( ! empty( $tooltip ) ) : ?>
			<?php
			$text = $tooltip;
			include SCALYN_QA_PLUGIN_DIR . 'templates/partials/tooltip.php';
			?>
		<?php endif; ?>

		<?php if ( 'pass' !== $status ) : ?>
		<button
			type="button"
			class="scalyn-btn scalyn-btn--small scalyn-btn--ghost scalyn-ignore-check"
			data-check-id="<?php echo esc_attr( $item_id ); ?>"
			data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
			title="<?php esc_attr_e( 'Ignore this check', 'scalyn-qa-assistant' ); ?>"
		>
			<span class="dashicons dashicons-hidden" aria-hidden="true"></span>
		</button>
		<?php endif; ?>
	</div>
</div>
