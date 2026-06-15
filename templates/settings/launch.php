<?php
/**
 * Template: Settings — Launch Checklist Tab.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.6
 */

defined( 'ABSPATH' ) || exit;

$settings    = isset( $settings ) ? $settings : array();
$tabs        = isset( $tabs ) ? $tabs : array();
$current_tab = isset( $current_tab ) ? $current_tab : 'launch';

$launch = isset( $settings['launch_settings'] ) && is_array( $settings['launch_settings'] ) ? $settings['launch_settings'] : array();

// PHP requirement defaults.
$thresholds = $launch['thresholds'] ?? array();
$php_version      = $thresholds['php_version'] ?? '8.3.14';
$memory_limit     = (int) ( $thresholds['memory_limit'] ?? 512 );
$max_execution    = (int) ( $thresholds['max_execution_time'] ?? 90 );
$max_input        = (int) ( $thresholds['max_input_time'] ?? 90 );
$post_max         = (int) ( $thresholds['post_max_size'] ?? 128 );
$upload_max       = (int) ( $thresholds['upload_max_size'] ?? 64 );

// Enabled checks — all enabled by default.
$enabled_checks = $launch['enabled_checks'] ?? array();

$check_categories = array(
	'seo' => array(
		'label'  => __( 'SEO Configuration', 'scalyn-qa-assistant' ),
		'checks' => array(
			'search_engine_visibility' => __( 'Search Engine Visibility', 'scalyn-qa-assistant' ),
			'seo_plugin_installed'     => __( 'SEO Plugin Installed', 'scalyn-qa-assistant' ),
			'sitemap_exists'           => __( 'Sitemap Exists', 'scalyn-qa-assistant' ),
			'llms_txt'                 => __( 'llms.txt', 'scalyn-qa-assistant' ),
		),
	),
	'analytics' => array(
		'label'  => __( 'Analytics', 'scalyn-qa-assistant' ),
		'checks' => array(
			'ga4_configured' => __( 'Google Analytics (GA4)', 'scalyn-qa-assistant' ),
			'gtm_configured' => __( 'Google Tag Manager', 'scalyn-qa-assistant' ),
		),
	),
	'technical' => array(
		'label'  => __( 'Technical', 'scalyn-qa-assistant' ),
		'checks' => array(
			'ssl_enabled'            => __( 'SSL Enabled', 'scalyn-qa-assistant' ),
			'favicon_exists'         => __( 'Favicon', 'scalyn-qa-assistant' ),
			'php_version'            => __( 'PHP Version', 'scalyn-qa-assistant' ),
			'php_memory_limit'       => __( 'PHP Memory Limit', 'scalyn-qa-assistant' ),
			'php_max_execution_time' => __( 'PHP Max Execution Time', 'scalyn-qa-assistant' ),
			'php_max_input_time'     => __( 'PHP Max Input Time', 'scalyn-qa-assistant' ),
			'php_post_max_size'      => __( 'PHP Post Max Size', 'scalyn-qa-assistant' ),
			'php_upload_max_size'    => __( 'PHP Upload Max Size', 'scalyn-qa-assistant' ),
		),
	),
	'content' => array(
		'label'  => __( 'Content', 'scalyn-qa-assistant' ),
		'checks' => array(
			'contact_page_exists'  => __( 'Contact Page', 'scalyn-qa-assistant' ),
			'privacy_policy_exists' => __( 'Privacy Policy', 'scalyn-qa-assistant' ),
		),
	),
	'plugin_health' => array(
		'label'  => __( 'Plugin Health', 'scalyn-qa-assistant' ),
		'checks' => array(
			'plugin_conflicts' => __( 'Plugin Conflicts', 'scalyn-qa-assistant' ),
			'security_plugin'  => __( 'Security Plugin', 'scalyn-qa-assistant' ),
			'cache_plugin'     => __( 'Cache Plugin', 'scalyn-qa-assistant' ),
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

	<form id="scalyn-launch-settings-form">

		<!-- PHP Requirements -->
		<div class="scalyn-card">
			<h2 class="scalyn-card-title"><?php esc_html_e( 'PHP Requirements', 'scalyn-qa-assistant' ); ?></h2>
			<p class="scalyn-card__subtitle"><?php esc_html_e( 'Set minimum PHP thresholds for the launch checklist. Checks will show a warning if the server value is below these.', 'scalyn-qa-assistant' ); ?></p>

			<table class="scalyn-form-table">
				<tr>
					<th scope="row"><label for="scalyn-threshold-php-version"><?php esc_html_e( 'PHP Version', 'scalyn-qa-assistant' ); ?></label></th>
					<td>
						<input type="text" id="scalyn-threshold-php-version" name="php_version" value="<?php echo esc_attr( $php_version ); ?>" class="scalyn-input" style="width:120px;" placeholder="8.3.14">
						<p class="scalyn-field-description"><?php printf( esc_html__( 'Current: %s', 'scalyn-qa-assistant' ), esc_html( PHP_VERSION ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="scalyn-threshold-memory"><?php esc_html_e( 'Memory Limit', 'scalyn-qa-assistant' ); ?></label></th>
					<td>
						<input type="number" id="scalyn-threshold-memory" name="memory_limit" value="<?php echo esc_attr( (string) $memory_limit ); ?>" min="64" max="4096" class="scalyn-input" style="width:100px;">
						<span><?php esc_html_e( 'MB', 'scalyn-qa-assistant' ); ?></span>
						<p class="scalyn-field-description"><?php printf( esc_html__( 'Current: %s', 'scalyn-qa-assistant' ), esc_html( ini_get( 'memory_limit' ) ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="scalyn-threshold-execution"><?php esc_html_e( 'Max Execution Time', 'scalyn-qa-assistant' ); ?></label></th>
					<td>
						<input type="number" id="scalyn-threshold-execution" name="max_execution_time" value="<?php echo esc_attr( (string) $max_execution ); ?>" min="30" max="600" class="scalyn-input" style="width:100px;">
						<span><?php esc_html_e( 'seconds', 'scalyn-qa-assistant' ); ?></span>
						<p class="scalyn-field-description"><?php printf( esc_html__( 'Current: %ss', 'scalyn-qa-assistant' ), esc_html( ini_get( 'max_execution_time' ) ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="scalyn-threshold-input"><?php esc_html_e( 'Max Input Time', 'scalyn-qa-assistant' ); ?></label></th>
					<td>
						<input type="number" id="scalyn-threshold-input" name="max_input_time" value="<?php echo esc_attr( (string) $max_input ); ?>" min="30" max="600" class="scalyn-input" style="width:100px;">
						<span><?php esc_html_e( 'seconds', 'scalyn-qa-assistant' ); ?></span>
						<p class="scalyn-field-description"><?php printf( esc_html__( 'Current: %ss', 'scalyn-qa-assistant' ), esc_html( ini_get( 'max_input_time' ) ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="scalyn-threshold-post"><?php esc_html_e( 'Post Max Size', 'scalyn-qa-assistant' ); ?></label></th>
					<td>
						<input type="number" id="scalyn-threshold-post" name="post_max_size" value="<?php echo esc_attr( (string) $post_max ); ?>" min="8" max="2048" class="scalyn-input" style="width:100px;">
						<span><?php esc_html_e( 'MB', 'scalyn-qa-assistant' ); ?></span>
						<p class="scalyn-field-description"><?php printf( esc_html__( 'Current: %s', 'scalyn-qa-assistant' ), esc_html( ini_get( 'post_max_size' ) ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="scalyn-threshold-upload"><?php esc_html_e( 'Upload Max Size', 'scalyn-qa-assistant' ); ?></label></th>
					<td>
						<input type="number" id="scalyn-threshold-upload" name="upload_max_size" value="<?php echo esc_attr( (string) $upload_max ); ?>" min="2" max="2048" class="scalyn-input" style="width:100px;">
						<span><?php esc_html_e( 'MB', 'scalyn-qa-assistant' ); ?></span>
						<p class="scalyn-field-description"><?php printf( esc_html__( 'Current: %s', 'scalyn-qa-assistant' ), esc_html( ini_get( 'upload_max_filesize' ) ) ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Enabled Checks -->
		<?php foreach ( $check_categories as $cat_key => $category ) : ?>
			<div class="scalyn-card">
				<h2 class="scalyn-card-title"><?php echo esc_html( $category['label'] ); ?></h2>

				<div class="scalyn-checks-grid">
					<?php foreach ( $category['checks'] as $check_id => $check_label ) :
						$is_enabled = $has_saved ? in_array( $check_id, $enabled_checks, true ) : true;
					?>
						<label class="scalyn-checkbox-label">
							<input
								type="checkbox"
								name="enabled_checks[]"
								value="<?php echo esc_attr( $check_id ); ?>"
								<?php checked( $is_enabled ); ?>
							>
							<?php echo esc_html( $check_label ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>

		<div class="scalyn-form-actions">
			<button type="submit" class="scalyn-btn" id="scalyn-save-launch-settings">
				<?php esc_html_e( 'Save Launch Settings', 'scalyn-qa-assistant' ); ?>
			</button>
		</div>
	</form>
</div>
