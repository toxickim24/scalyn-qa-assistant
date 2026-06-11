<?php
/**
 * Template: Settings — Templates Tab.
 *
 * Renders the template management interface for creating, editing,
 * duplicating, and deleting QA check templates.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 *
 * @var array  $settings    Current plugin settings array.
 * @var array  $tabs        Tab navigation data (slug => [label, url, active]).
 * @var string $current_tab The current active tab slug.
 */

defined( 'ABSPATH' ) || exit;

$settings    = isset( $settings ) ? $settings : array();
$tabs        = isset( $tabs ) ? $tabs : array();
$current_tab = isset( $current_tab ) ? $current_tab : 'templates';

// Retrieve stored templates (indexed array with 'id' field per item).
$templates_raw = isset( $settings['templates'] ) && is_array( $settings['templates'] ) ? $settings['templates'] : array();

// Build keyed array: id => template data.
$templates = array();
foreach ( $templates_raw as $tpl ) {
	if ( isset( $tpl['id'] ) ) {
		$templates[ $tpl['id'] ] = $tpl;
	}
}

if ( empty( $templates ) ) {
	$templates = array(
		'default' => array(
			'id'     => 'default',
			'name'   => __( 'Default Template', 'scalyn-qa-assistant' ),
			'checks' => array( 'meta_title_exists', 'meta_description_exists', 'heading_structure', 'image_alt_text', 'internal_links', 'broken_links', 'featured_image' ),
		),
	);
}

$active_template = isset( $settings['active_template'] ) ? $settings['active_template'] : 'default';

// All available checks.
$available_checks = array(
	'meta_title_exists'      => __( 'Meta Title Exists', 'scalyn-qa-assistant' ),
	'meta_description_exists' => __( 'Meta Description Exists', 'scalyn-qa-assistant' ),
	'meta_title_length'      => __( 'Meta Title Length', 'scalyn-qa-assistant' ),
	'meta_description_length' => __( 'Meta Description Length', 'scalyn-qa-assistant' ),
	'heading_structure'      => __( 'Heading Structure (H1-H6)', 'scalyn-qa-assistant' ),
	'image_alt_text'         => __( 'Image Alt Text', 'scalyn-qa-assistant' ),
	'internal_links'         => __( 'Internal Links Present', 'scalyn-qa-assistant' ),
	'external_links'         => __( 'External Links Present', 'scalyn-qa-assistant' ),
	'content_length'         => __( 'Minimum Content Length', 'scalyn-qa-assistant' ),
	'broken_links'           => __( 'Broken Link Check', 'scalyn-qa-assistant' ),
	'featured_image'         => __( 'Featured Image Set', 'scalyn-qa-assistant' ),
	'open_graph'             => __( 'Open Graph Tags', 'scalyn-qa-assistant' ),
	'readability'            => __( 'Readability Score', 'scalyn-qa-assistant' ),
	'form_action'            => __( 'Form Action Attributes', 'scalyn-qa-assistant' ),
	'button_text'            => __( 'Button Text Quality', 'scalyn-qa-assistant' ),
);

// Current template data.
$current_template_data = isset( $templates[ $active_template ] ) ? $templates[ $active_template ] : reset( $templates );
// Checks can be either ['check_id' => true] (old) or ['check_id', 'check_id'] (API format).
$raw_checks     = isset( $current_template_data['checks'] ) && is_array( $current_template_data['checks'] ) ? $current_template_data['checks'] : array();
$current_checks = array();
foreach ( $raw_checks as $key => $value ) {
	if ( is_string( $key ) ) {
		// Old format: associative.
		$current_checks[ $key ] = $value;
	} else {
		// API format: flat array of check IDs.
		$current_checks[ $value ] = true;
	}
}
?>
<div class="scalyn-wrap">

	<div class="scalyn-page-header">
		<div class="scalyn-page-header__intro">
			<h1><?php esc_html_e( 'Settings', 'scalyn-qa-assistant' ); ?></h1>
			<p class="scalyn-page-header__description"><?php esc_html_e( 'Configure scanning, scoring, AI providers, and plugin behavior.', 'scalyn-qa-assistant' ); ?></p>
		</div>
	</div>

	<!-- Tab Navigation -->
	<div class="scalyn-tabs" role="tablist">
		<?php foreach ( $tabs as $tab_slug => $tab ) : ?>
			<a
				href="<?php echo esc_url( $tab['url'] ); ?>"
				class="scalyn-tab <?php echo $tab['active'] ? 'scalyn-tab--active' : ''; ?>"
				role="tab"
				aria-selected="<?php echo $tab['active'] ? 'true' : 'false'; ?>"
			>
				<?php echo esc_html( $tab['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<!-- Template Management -->
	<div class="scalyn-card" id="scalyn-tab-panel-templates" role="tabpanel">
		<h2 class="scalyn-card__title"><?php esc_html_e( 'QA Check Templates', 'scalyn-qa-assistant' ); ?></h2>
		<p class="scalyn-card__description">
			<?php esc_html_e( 'Templates define which QA checks run during a scan. Create custom templates for different content types or workflows.', 'scalyn-qa-assistant' ); ?>
		</p>

		<!-- Active Template Selector -->
		<div class="scalyn-template-selector">
			<label for="scalyn-active-template" class="scalyn-label">
				<?php esc_html_e( 'Active Template:', 'scalyn-qa-assistant' ); ?>
			</label>
			<select id="scalyn-active-template" name="active_template" class="scalyn-select">
				<?php foreach ( $templates as $template_slug => $template_data ) : ?>
					<option
						value="<?php echo esc_attr( $template_slug ); ?>"
						<?php selected( $active_template, $template_slug ); ?>
					>
						<?php echo esc_html( $template_data['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<div class="scalyn-template-actions">
				<button
					type="button"
					id="scalyn-template-create"
					class="scalyn-btn scalyn-btn--secondary"
				>
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'Create New', 'scalyn-qa-assistant' ); ?>
				</button>
				<button
					type="button"
					id="scalyn-template-duplicate"
					class="scalyn-btn scalyn-btn--secondary"
				>
					<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
					<?php esc_html_e( 'Duplicate', 'scalyn-qa-assistant' ); ?>
				</button>
				<button
					type="button"
					id="scalyn-template-delete"
					class="scalyn-btn scalyn-btn--danger"
					<?php echo 'default' === $active_template ? 'disabled' : ''; ?>
				>
					<span class="dashicons dashicons-trash" aria-hidden="true"></span>
					<?php esc_html_e( 'Delete', 'scalyn-qa-assistant' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Template Details -->
	<div class="scalyn-card" id="scalyn-template-details">
		<h2 class="scalyn-card__title">
			<?php
			printf(
				/* translators: %s: Template name. */
				esc_html__( 'Checks in "%s"', 'scalyn-qa-assistant' ),
				esc_html( $current_template_data['name'] ),
			);
			?>
		</h2>
		<p class="scalyn-card__description">
			<?php esc_html_e( 'Enable or disable individual checks for this template. Only checked items will run during QA scans.', 'scalyn-qa-assistant' ); ?>
		</p>

		<form id="scalyn-template-form" data-template="<?php echo esc_attr( $active_template ); ?>">

			<!-- Template Name -->
			<div class="scalyn-template-name-row">
				<label for="scalyn-template-name" class="scalyn-label">
					<?php esc_html_e( 'Template Name:', 'scalyn-qa-assistant' ); ?>
				</label>
				<input
					type="text"
					id="scalyn-template-name"
					name="template_name"
					value="<?php echo esc_attr( $current_template_data['name'] ); ?>"
					class="scalyn-input"
					<?php echo 'default' === $active_template ? 'readonly' : ''; ?>
				>
			</div>

			<!-- Check Items -->
			<div class="scalyn-template-checks">
				<fieldset>
					<legend class="screen-reader-text">
						<?php esc_html_e( 'Available QA Checks', 'scalyn-qa-assistant' ); ?>
					</legend>
					<?php foreach ( $available_checks as $check_id => $check_label ) : ?>
						<label class="scalyn-checkbox-label scalyn-template-check">
							<input
								type="checkbox"
								name="checks[<?php echo esc_attr( $check_id ); ?>]"
								value="1"
								<?php checked( ! empty( $current_checks[ $check_id ] ) ); ?>
							>
							<?php echo esc_html( $check_label ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
			</div>

			<div class="scalyn-form-actions">
				<button type="submit" class="scalyn-btn" id="scalyn-save-template">
					<?php esc_html_e( 'Save Template', 'scalyn-qa-assistant' ); ?>
				</button>
				<span class="scalyn-save-status" id="scalyn-template-status" aria-live="polite"></span>
			</div>
		</form>
	</div>

	<!-- Create New Template Dialog (hidden) -->
	<div id="scalyn-template-dialog" class="scalyn-dialog" style="display: none;" aria-hidden="true">
		<div class="scalyn-dialog__overlay"></div>
		<div class="scalyn-dialog__content">
			<h3><?php esc_html_e( 'Create New Template', 'scalyn-qa-assistant' ); ?></h3>
			<label for="scalyn-new-template-name" class="scalyn-label">
				<?php esc_html_e( 'Template Name:', 'scalyn-qa-assistant' ); ?>
			</label>
			<input
				type="text"
				id="scalyn-new-template-name"
				class="scalyn-input"
				placeholder="<?php esc_attr_e( 'My Custom Template', 'scalyn-qa-assistant' ); ?>"
			>
			<div class="scalyn-dialog__actions">
				<button type="button" class="scalyn-btn" id="scalyn-dialog-confirm">
					<?php esc_html_e( 'Create', 'scalyn-qa-assistant' ); ?>
				</button>
				<button type="button" class="scalyn-btn scalyn-btn--secondary" id="scalyn-dialog-cancel">
					<?php esc_html_e( 'Cancel', 'scalyn-qa-assistant' ); ?>
				</button>
			</div>
		</div>
	</div>

</div>
