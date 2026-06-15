<?php
/**
 * Template: Settings — Page Audits Tab.
 *
 * Renders the page audit checks configuration, allowing users to
 * enable or disable individual QA checks that run during page scans.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.7
 *
 * @var array  $settings    Current plugin settings array.
 * @var array  $tabs        Tab navigation data (slug => [label, url, active]).
 * @var string $current_tab The current active tab slug.
 */

defined( 'ABSPATH' ) || exit;

$settings    = isset( $settings ) ? $settings : array();
$tabs        = isset( $tabs ) ? $tabs : array();
$current_tab = isset( $current_tab ) ? $current_tab : 'page-audits';

$page_audit = isset( $settings['page_audit_settings'] ) && is_array( $settings['page_audit_settings'] ) ? $settings['page_audit_settings'] : array();

// Enabled checks — all enabled by default.
$enabled_checks = $page_audit['enabled_checks'] ?? array();

$check_categories = array(
	'seo' => array(
		'label'  => __( 'SEO Checks', 'scalyn-qa-assistant' ),
		'checks' => array(
			'meta_title_exists'       => __( 'Meta Title', 'scalyn-qa-assistant' ),
			'meta_description_exists' => __( 'Meta Description', 'scalyn-qa-assistant' ),
			'image_alt_text'          => __( 'Image Alt Text', 'scalyn-qa-assistant' ),
			'featured_image_exists'   => __( 'Featured Image Set', 'scalyn-qa-assistant' ),
			'internal_links_present'  => __( 'Internal Links Present', 'scalyn-qa-assistant' ),
			'external_links_present'  => __( 'External Links Present', 'scalyn-qa-assistant' ),
		),
	),
	'content' => array(
		'label'  => __( 'Content Checks', 'scalyn-qa-assistant' ),
		'checks' => array(
			'h1_exists'               => __( 'H1 Heading Exists', 'scalyn-qa-assistant' ),
			'heading_hierarchy'       => __( 'Heading Hierarchy', 'scalyn-qa-assistant' ),
			'empty_headings'          => __( 'Empty Headings', 'scalyn-qa-assistant' ),
			'content_length'          => __( 'Content Length', 'scalyn-qa-assistant' ),
			'heading_capitalization'  => __( 'Heading Capitalization', 'scalyn-qa-assistant' ),
			'paragraph_punctuation'   => __( 'Paragraph Punctuation', 'scalyn-qa-assistant' ),
			'short_paragraphs'        => __( 'Paragraph Quality', 'scalyn-qa-assistant' ),
		),
	),
	'functionality' => array(
		'label'  => __( 'Functionality Checks', 'scalyn-qa-assistant' ),
		'checks' => array(
			'broken_links'      => __( 'Broken Link Check', 'scalyn-qa-assistant' ),
			'links_summary'     => __( 'Links Summary', 'scalyn-qa-assistant' ),
			'empty_buttons'     => __( 'Empty Buttons', 'scalyn-qa-assistant' ),
			'placeholder_links' => __( 'Placeholder Links', 'scalyn-qa-assistant' ),
			'form_has_submit'   => __( 'Form Submit Buttons', 'scalyn-qa-assistant' ),
			'popup_triggers'    => __( 'Popup Triggers', 'scalyn-qa-assistant' ),
		),
	),
);

// If no settings saved yet, all checks are enabled.
$has_saved = ! empty( $enabled_checks );
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

	<form id="scalyn-page-audit-settings-form">

		<?php foreach ( $check_categories as $cat_key => $category ) : ?>
			<div class="scalyn-card">
				<h2 class="scalyn-card-title"><?php echo esc_html( $category['label'] ); ?></h2>

				<div class="scalyn-template-checks">
					<fieldset>
						<legend class="screen-reader-text">
							<?php echo esc_html( $category['label'] ); ?>
						</legend>
						<?php foreach ( $category['checks'] as $check_id => $check_label ) : ?>
							<label class="scalyn-checkbox-label scalyn-template-check">
								<input
									type="checkbox"
									name="enabled_checks[]"
									value="<?php echo esc_attr( $check_id ); ?>"
									<?php checked( ! $has_saved || in_array( $check_id, $enabled_checks, true ) ); ?>
								>
								<?php echo esc_html( $check_label ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				</div>
			</div>
		<?php endforeach; ?>

		<div class="scalyn-form-actions" style="margin-top: 1rem;">
			<button type="submit" class="scalyn-btn">
				<?php esc_html_e( 'Save Settings', 'scalyn-qa-assistant' ); ?>
			</button>
		</div>
	</form>

</div>
