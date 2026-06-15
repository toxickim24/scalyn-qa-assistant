<?php
/**
 * Template: Launch Checklist Page.
 *
 * Renders the website launch readiness checklist with grouped checks and summary.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 *
 * @var \Scalyn\QA\Models\Check_Item[] $results   Array of Check_Item objects from launch scan.
 * @var array                          $counts    Array with pass, fail, warning, total keys.
 * @var int|null                       $last_scan Unix timestamp of last scan or null.
 * @var int                            $score     Launch readiness score (0-100).
 */

defined( 'ABSPATH' ) || exit;

$results          = isset( $results ) ? $results : array();
$counts           = isset( $counts ) ? $counts : array( 'pass' => 0, 'fail' => 0, 'warning' => 0, 'total' => 0 );
$last_scan        = isset( $last_scan ) ? $last_scan : null;
$score            = isset( $score ) ? (int) $score : 0;
$overall_score    = $score;
$category_scores  = isset( $category_scores ) && is_array( $category_scores ) ? $category_scores : array();

// Determine alert status.
if ( $score >= 80 ) {
	$alert_class  = 'scalyn-alert--green';
	$alert_label  = __( 'Launch Ready', 'scalyn-qa-assistant' );
} elseif ( $score >= 50 ) {
	$alert_class  = 'scalyn-alert--yellow';
	$alert_label  = __( 'Needs Review', 'scalyn-qa-assistant' );
} else {
	$alert_class  = 'scalyn-alert--red';
	$alert_label  = __( 'Not Ready', 'scalyn-qa-assistant' );
}

// Group checks by their ID prefix for category assignment.
$category_map = array(
	'search_engine_visibility' => 'seo',
	'seo_plugin_installed'     => 'seo',
	'sitemap_exists'           => 'seo',
	'llms_txt'                 => 'seo',
	'ga4_configured'       => 'analytics',
	'gtm_configured'       => 'analytics',
	'ssl_enabled'          => 'technical',
	'favicon_exists'       => 'technical',
	'php_version'          => 'technical',
	'contact_page_exists'  => 'content',
	'privacy_policy_exists' => 'content',
	'plugin_conflicts'     => 'plugin_health',
	'php_memory_limit'     => 'technical',
	'php_max_execution_time' => 'technical',
	'php_max_input_time'   => 'technical',
	'php_post_max_size'    => 'technical',
	'php_upload_max_size'  => 'technical',
	'security_plugin'      => 'plugin_health',
	'cache_plugin'         => 'plugin_health',
);

$category_labels = array(
	'seo'           => __( 'SEO Configuration', 'scalyn-qa-assistant' ),
	'analytics'     => __( 'Analytics', 'scalyn-qa-assistant' ),
	'technical'     => __( 'Technical', 'scalyn-qa-assistant' ),
	'content'       => __( 'Content', 'scalyn-qa-assistant' ),
	'plugin_health' => __( 'Plugin Health', 'scalyn-qa-assistant' ),
);

// Load launch-scoped ignore rules.
$launch_ignores = \Scalyn\QA\Models\Ignore_Rule::get_by_context( 'launch' );
$ignored_ids    = array();
foreach ( $launch_ignores as $rule ) {
	$ignored_ids[ $rule->check_id ] = $rule;
}

// Sort results into categories.
$grouped = array(
	'seo'           => array(),
	'analytics'     => array(),
	'technical'     => array(),
	'content'       => array(),
	'plugin_health' => array(),
);

$grouped_ignored = array(
	'seo'           => array(),
	'analytics'     => array(),
	'technical'     => array(),
	'content'       => array(),
	'plugin_health' => array(),
);

foreach ( $results as $check ) {
	$check_id = $check->id;
	$group    = isset( $category_map[ $check_id ] ) ? $category_map[ $check_id ] : 'technical';

	if ( isset( $ignored_ids[ $check_id ] ) ) {
		$grouped_ignored[ $group ][] = $check;
	} else {
		$grouped[ $group ][] = $check;
	}
}

// Format the last scan time.
if ( null !== $last_scan && $last_scan > 0 ) {
	$time_diff = human_time_diff( $last_scan, time() );
	/* translators: %s: Human-readable time difference. */
	$last_scan_text = sprintf( __( '%s ago', 'scalyn-qa-assistant' ), $time_diff );
} else {
	$last_scan_text = __( 'Never', 'scalyn-qa-assistant' );
}
?>
<div class="scalyn-wrap">

	<!-- Header -->
	<div class="scalyn-page-header">
		<h1><?php esc_html_e( 'Website Launch Checklist', 'scalyn-qa-assistant' ); ?></h1>
		<div class="scalyn-page-header__actions">
			<button type="button" id="scalyn-launch-scan" class="scalyn-btn scalyn-btn--small">
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<?php esc_html_e( 'Run Check', 'scalyn-qa-assistant' ); ?>
			</button>
		</div>
		<p class="scalyn-page-header__meta">
			<?php
			printf( esc_html__( 'Last checked: %s', 'scalyn-qa-assistant' ), esc_html( $last_scan_text ) );
			?>
			<?php if ( $counts['total'] > 0 ) : ?>
				<span class="scalyn-meta__sep">|</span>
				<?php esc_html_e( 'Score:', 'scalyn-qa-assistant' ); ?>
				<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( \Scalyn\QA\Models\Score::calculate_status( $overall_score ) ); ?>">
					<?php echo esc_html( (string) $overall_score ); ?>
				</span>
				<span class="scalyn-meta__sep">|</span>
				<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( str_replace( 'scalyn-alert--', '', $alert_class ) ); ?>">
					<?php echo esc_html( $alert_label ); ?>
				</span>
				<span class="scalyn-meta__sep">|</span>
				<?php
				printf(
					esc_html__( '%1$d/%2$d checks passed', 'scalyn-qa-assistant' ),
					(int) $counts['pass'],
					(int) $counts['total'],
				);
				?>
			<?php endif; ?>
		</p>
	</div>

	<?php if ( $counts['total'] > 0 ) : ?>
		<!-- Score Summary -->
		<div class="scalyn-grid scalyn-grid--3">
			<?php
			$score_cards = array(
				'seo'           => __( 'SEO Configuration', 'scalyn-qa-assistant' ),
				'analytics'     => __( 'Analytics', 'scalyn-qa-assistant' ),
				'technical'     => __( 'Technical', 'scalyn-qa-assistant' ),
				'content'       => __( 'Content', 'scalyn-qa-assistant' ),
				'plugin_health' => __( 'Plugin Health', 'scalyn-qa-assistant' ),
			);

			foreach ( $score_cards as $cat_key => $cat_label ) :
				$label  = $cat_label;
				$score  = $category_scores[ $cat_key ] ?? 0;
				$status = \Scalyn\QA\Models\Score::calculate_status( $score );
				include SCALYN_QA_PLUGIN_DIR . 'templates/dashboard/widgets/score-summary.php';
			endforeach;

			$label  = __( 'Overall Score', 'scalyn-qa-assistant' );
			$score  = $overall_score;
			$status = \Scalyn\QA\Models\Score::calculate_status( $score );
			include SCALYN_QA_PLUGIN_DIR . 'templates/dashboard/widgets/score-summary.php';
			?>
		</div>
	<?php else : ?>
		<div class="scalyn-alert scalyn-alert--neutral">
			<span class="scalyn-alert__label">
				<?php esc_html_e( 'No scan data', 'scalyn-qa-assistant' ); ?>
			</span>
			<span class="scalyn-alert__detail">
				<?php esc_html_e( 'Click "Run Check" to scan your website for launch readiness.', 'scalyn-qa-assistant' ); ?>
			</span>
		</div>
	<?php endif; ?>

	<!-- Check Items grouped by category -->
	<?php foreach ( $grouped as $group_key => $group_checks ) : ?>
		<?php if ( empty( $group_checks ) && $counts['total'] === 0 ) : ?>
			<?php continue; ?>
		<?php endif; ?>
		<div class="scalyn-card" id="scalyn-launch-<?php echo esc_attr( $group_key ); ?>">
			<h2 class="scalyn-card-title">
				<?php echo esc_html( $category_labels[ $group_key ] ); ?>
				<?php if ( isset( $category_scores[ $group_key ] ) ) : ?>
					<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( \Scalyn\QA\Models\Score::calculate_status( $category_scores[ $group_key ] ) ); ?>">
						<?php echo esc_html( (string) $category_scores[ $group_key ] ); ?>
					</span>
				<?php endif; ?>
			</h2>

			<div class="scalyn-check-list">
				<?php if ( empty( $group_checks ) && empty( $grouped_ignored[ $group_key ] ) ) : ?>
					<p class="scalyn-card__empty">
						<?php esc_html_e( 'No checks in this category yet. Run a scan to populate results.', 'scalyn-qa-assistant' ); ?>
					</p>
				<?php else : ?>
					<?php foreach ( $group_checks as $check ) : ?>
						<?php
						$item    = $check->to_array();
						$post_id = 0;
						$c_status = isset( $item['status'] ) ? $item['status'] : 'fail';
						$accent  = ( 'warning' === $c_status || 'fail' === $c_status ) ? ' scalyn-check-item--accent' : '';
						?>
						<div class="scalyn-check-item scalyn-check-item--<?php echo esc_attr( $c_status ); ?><?php echo esc_attr( $accent ); ?>"
							data-check-id="<?php echo esc_attr( isset( $item['id'] ) ? $item['id'] : '' ); ?>"
							data-status="<?php echo esc_attr( $c_status ); ?>"
							data-severity="<?php echo esc_attr( isset( $item['severity'] ) ? $item['severity'] : 'info' ); ?>"
						>
							<?php
							$status_icons = array(
								'pass'    => 'dashicons-yes-alt',
								'warning' => 'dashicons-warning',
								'fail'    => 'dashicons-dismiss',
							);
							$icon_class = isset( $status_icons[ $c_status ] ) ? $status_icons[ $c_status ] : 'dashicons-marker';
							?>
							<span class="scalyn-check-icon" aria-hidden="true">
								<span class="dashicons <?php echo esc_attr( $icon_class ); ?>"></span>
							</span>

							<div class="scalyn-check-content">
								<strong class="scalyn-check-label"><?php echo esc_html( isset( $item['label'] ) ? $item['label'] : '' ); ?></strong>
								<?php if ( ! empty( $item['message'] ) ) : ?>
									<span class="scalyn-check-message"><?php echo esc_html( $item['message'] ); ?></span>
								<?php endif; ?>
							</div>

							<div class="scalyn-check-actions">
								<?php if ( ! empty( $item['quick_fix'] ) ) : ?>
									<?php
									$action = $item['quick_fix'];
									include SCALYN_QA_PLUGIN_DIR . 'templates/partials/quick-fix-button.php';
									?>
								<?php endif; ?>

								<?php if ( ! empty( $item['tooltip'] ) ) : ?>
									<?php
									$text = $item['tooltip'];
									include SCALYN_QA_PLUGIN_DIR . 'templates/partials/tooltip.php';
									?>
								<?php endif; ?>

								<?php if ( 'pass' !== $c_status ) : ?>
								<button
									type="button"
									class="scalyn-btn scalyn-btn--small scalyn-btn--ghost scalyn-ignore-check"
									data-check-id="<?php echo esc_attr( isset( $item['id'] ) ? $item['id'] : '' ); ?>"
									data-post-id="0"
									title="<?php esc_attr_e( 'Ignore this check', 'scalyn-qa-assistant' ); ?>"
								>
									<span class="dashicons dashicons-hidden" aria-hidden="true"></span>
								</button>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>

					<?php
					$cat_ignored = $grouped_ignored[ $group_key ] ?? array();
					if ( ! empty( $cat_ignored ) ) :
					?>
						<details class="scalyn-ignored-section">
							<summary class="scalyn-ignored-section__toggle">
								<?php
								printf(
									esc_html( _n(
										'%d ignored check',
										'%d ignored checks',
										count( $cat_ignored ),
										'scalyn-qa-assistant'
									) ),
									count( $cat_ignored )
								);
								?>
							</summary>
							<div class="scalyn-ignored-section__list">
								<?php foreach ( $cat_ignored as $check ) :
									$rule = $ignored_ids[ $check->id ] ?? null;
								?>
									<div class="scalyn-check-item scalyn-check-item--ignored" style="opacity:0.6;">
										<span class="scalyn-check-icon" aria-hidden="true"><span class="dashicons dashicons-hidden"></span></span>
										<div class="scalyn-check-content">
											<strong class="scalyn-check-label"><?php echo esc_html( $check->label ); ?></strong>
											<?php if ( $rule && ! empty( $rule->reason ) ) : ?>
												<span class="scalyn-check-message"><?php echo esc_html( $rule->reason ); ?></span>
											<?php endif; ?>
										</div>
										<div class="scalyn-check-actions">
											<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--ghost scalyn-remove-ignore" data-rule-id="<?php echo esc_attr( $rule ? $rule->id : '' ); ?>" title="<?php esc_attr_e( 'Restore', 'scalyn-qa-assistant' ); ?>">
												<span class="dashicons dashicons-visibility" aria-hidden="true"></span> <?php esc_html_e( 'Restore', 'scalyn-qa-assistant' ); ?>
											</button>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</details>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	<?php endforeach; ?>

	<?php
	// Collect all ignored checks across categories.
	$all_ignored = array();
	foreach ( $grouped_ignored as $cat_ignored_items ) {
		$all_ignored = array_merge( $all_ignored, $cat_ignored_items );
	}
	?>
	<?php if ( ! empty( $all_ignored ) ) : ?>
	<!-- Ignored Checks Section -->
	<div class="scalyn-card" id="scalyn-launch-ignored">
		<h2 class="scalyn-card-title">
			<?php esc_html_e( 'Ignored Checks', 'scalyn-qa-assistant' ); ?>
			<span class="scalyn-badge scalyn-badge--neutral"><?php echo esc_html( (string) count( $all_ignored ) ); ?></span>
		</h2>
		<table class="scalyn-table scalyn-table--compact">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Check', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'Type', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'Reason', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'Created By', 'scalyn-qa-assistant' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'scalyn-qa-assistant' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $all_ignored as $check ) :
					$rule = $ignored_ids[ $check->id ] ?? null;
				?>
					<tr data-rule-id="<?php echo esc_attr( $rule ? $rule->id : '' ); ?>">
						<td><code><?php echo esc_html( $check->id ); ?></code></td>
						<td>
							<span class="scalyn-badge scalyn-badge--neutral">
								<?php echo esc_html( $rule ? ucfirst( $rule->type ) : 'Global' ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $rule ? $rule->reason : '' ); ?></td>
						<td><?php echo esc_html( $rule ? $rule->created_by : '' ); ?></td>
						<td>
							<button
								type="button"
								class="scalyn-btn scalyn-btn--small scalyn-btn--ghost scalyn-remove-ignore"
								data-rule-id="<?php echo esc_attr( $rule ? $rule->id : '' ); ?>"
								title="<?php esc_attr_e( 'Remove ignore rule', 'scalyn-qa-assistant' ); ?>"
							>
								<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<!-- Loading overlay for AJAX scan -->
	<div id="scalyn-launch-loading" class="scalyn-loading" style="display: none;" aria-hidden="true">
		<span class="spinner is-active"></span>
		<span class="scalyn-loading__text"><?php esc_html_e( 'Running launch checks...', 'scalyn-qa-assistant' ); ?></span>
	</div>

</div>
