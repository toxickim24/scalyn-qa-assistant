<?php
/**
 * Template: Dashboard Overview.
 *
 * Main dashboard page showing project scores, pages needing attention,
 * recent scans, SEO plugin status, and launch summary.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 *
 * @var array       $project_scores          Project-wide scores: seo_ready, qa_ready, launch_ready, overall.
 * @var array       $pages_needing_attention  Top 10 lowest-scoring pages.
 * @var array       $recent_scans            Last 10 scanned pages.
 * @var string|null $seo_plugin_status       Active SEO plugin name or null.
 * @var array       $launch_summary          Launch check summary: pass, fail, warning, total, last_scan.
 */

defined( 'ABSPATH' ) || exit;

// Ensure variables have safe defaults.
$project_scores          = isset( $project_scores ) && is_array( $project_scores ) ? $project_scores : array();
$pages_needing_attention = isset( $pages_needing_attention ) && is_array( $pages_needing_attention ) ? $pages_needing_attention : array();
$recent_scans            = isset( $recent_scans ) && is_array( $recent_scans ) ? $recent_scans : array();
$seo_plugin_status       = isset( $seo_plugin_status ) ? $seo_plugin_status : null;
$launch_summary          = isset( $launch_summary ) && is_array( $launch_summary ) ? $launch_summary : array();

// Extract individual project scores with defaults.
$seo_ready    = isset( $project_scores['seo_ready'] ) ? (int) $project_scores['seo_ready'] : 0;
$qa_ready     = isset( $project_scores['qa_ready'] ) ? (int) $project_scores['qa_ready'] : 0;
$launch_ready = isset( $project_scores['launch_ready'] ) ? (int) $project_scores['launch_ready'] : 0;
$overall      = isset( $project_scores['overall'] ) ? (int) $project_scores['overall'] : 0;

// Launch summary defaults.
$launch_pass      = isset( $launch_summary['pass'] ) ? (int) $launch_summary['pass'] : 0;
$launch_fail      = isset( $launch_summary['fail'] ) ? (int) $launch_summary['fail'] : 0;
$launch_warning   = isset( $launch_summary['warning'] ) ? (int) $launch_summary['warning'] : 0;
$launch_total     = isset( $launch_summary['total'] ) ? (int) $launch_summary['total'] : 0;
$launch_last_scan = isset( $launch_summary['last_scan'] ) ? $launch_summary['last_scan'] : null;

// Quick stats.
$total_pages_attention = count( $pages_needing_attention );
$total_scans           = count( $recent_scans );
?>
<div class="scalyn-wrap">
	<div class="scalyn-branded-header">
		<div class="scalyn-branded-header__left">
			<span class="scalyn-branded-header__icon">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M5.5 10c0-1.4 1-2.5 2.2-2.5.8 0 1.5.4 2 1l.3.4.3-.4c.5-.6 1.2-1 2-1C13.5 7.5 14.5 8.6 14.5 10s-1 2.5-2.2 2.5c-.8 0-1.5-.4-2-1l-.3-.4-.3.4c-.5.6-1.2 1-2 1C6.5 12.5 5.5 11.4 5.5 10zM3 10c0 2.6 2 4.7 4.7 4.7 1.3 0 2.5-.6 3.3-1.5.8.9 2 1.5 3.3 1.5C16.9 14.7 19 12.6 19 10s-2-4.7-4.7-4.7c-1.3 0-2.5.6-3.3 1.5-.8-.9-2-1.5-3.3-1.5C5.1 5.3 3 7.4 3 10z"/></svg>
			</span>
			<div class="scalyn-branded-header__text">
				<h1 class="scalyn-branded-header__title"><?php esc_html_e( 'Scalyn QA', 'scalyn-qa-assistant' ); ?></h1>
				<p class="scalyn-branded-header__description"><?php esc_html_e( 'Website QA & SEO', 'scalyn-qa-assistant' ); ?></p>
			</div>
		</div>
		<span class="scalyn-version"><?php echo esc_html( 'v' . SCALYN_QA_VERSION ); ?></span>
	</div>

	<!-- Score Cards Row -->
	<div class="scalyn-grid scalyn-grid--4">
		<?php
		// SEO Ready card.
		$label  = __( 'SEO Ready', 'scalyn-qa-assistant' );
		$score  = $seo_ready;
		$status = \Scalyn\QA\Models\Score::calculate_status( $score );
		include SCALYN_QA_PLUGIN_DIR . 'templates/dashboard/widgets/score-summary.php';

		// QA Ready card.
		$label  = __( 'QA Ready', 'scalyn-qa-assistant' );
		$score  = $qa_ready;
		$status = \Scalyn\QA\Models\Score::calculate_status( $score );
		include SCALYN_QA_PLUGIN_DIR . 'templates/dashboard/widgets/score-summary.php';

		// Launch Ready card.
		$label  = __( 'Launch Ready', 'scalyn-qa-assistant' );
		$score  = $launch_ready;
		$status = \Scalyn\QA\Models\Score::calculate_status( $score );
		include SCALYN_QA_PLUGIN_DIR . 'templates/dashboard/widgets/score-summary.php';

		// Overall card.
		$label  = __( 'Overall', 'scalyn-qa-assistant' );
		$score  = $overall;
		$status = \Scalyn\QA\Models\Score::calculate_status( $score );
		include SCALYN_QA_PLUGIN_DIR . 'templates/dashboard/widgets/score-summary.php';
		?>
	</div>

	<!-- Two Column Layout: Pages Needing Attention + SEO/Launch Summary -->
	<div class="scalyn-grid scalyn-grid--2">
		<!-- Left: Pages Requiring Attention -->
		<?php
		$pages = $pages_needing_attention;
		include SCALYN_QA_PLUGIN_DIR . 'templates/dashboard/widgets/pages-attention.php';
		?>

		<!-- Right: SEO Plugin Status + Launch Summary -->
		<div class="scalyn-card-group">
			<!-- SEO Plugin Status -->
			<div class="scalyn-card">
				<h2 class="scalyn-card-title"><?php esc_html_e( 'SEO Plugin Status', 'scalyn-qa-assistant' ); ?></h2>
				<?php if ( null !== $seo_plugin_status ) : ?>
					<div class="scalyn-status-row">
						<span class="scalyn-status-indicator scalyn-status-indicator--active"></span>
						<span>
							<?php
							printf(
								/* translators: %s: SEO plugin name. */
								esc_html__( '%s detected and integrated.', 'scalyn-qa-assistant' ),
								esc_html( $seo_plugin_status )
							);
							?>
						</span>
					</div>
				<?php else : ?>
					<div class="scalyn-status-row">
						<span class="scalyn-status-indicator scalyn-status-indicator--inactive"></span>
						<span><?php esc_html_e( 'No SEO plugin detected. Install Yoast SEO, Rank Math, or AIOSEO for enhanced checks.', 'scalyn-qa-assistant' ); ?></span>
					</div>
				<?php endif; ?>
			</div>

			<!-- Launch Summary -->
			<div class="scalyn-card">
				<h2 class="scalyn-card-title"><?php esc_html_e( 'Launch Summary', 'scalyn-qa-assistant' ); ?></h2>
				<?php if ( $launch_total > 0 ) : ?>
					<div class="scalyn-launch-summary">
						<div class="scalyn-launch-stat">
							<span class="scalyn-launch-stat__value scalyn-text--green"><?php echo esc_html( $launch_pass ); ?></span>
							<span class="scalyn-launch-stat__label"><?php esc_html_e( 'Passed', 'scalyn-qa-assistant' ); ?></span>
						</div>
						<div class="scalyn-launch-stat">
							<span class="scalyn-launch-stat__value scalyn-text--yellow"><?php echo esc_html( $launch_warning ); ?></span>
							<span class="scalyn-launch-stat__label"><?php esc_html_e( 'Warnings', 'scalyn-qa-assistant' ); ?></span>
						</div>
						<div class="scalyn-launch-stat">
							<span class="scalyn-launch-stat__value scalyn-text--red"><?php echo esc_html( $launch_fail ); ?></span>
							<span class="scalyn-launch-stat__label"><?php esc_html_e( 'Failed', 'scalyn-qa-assistant' ); ?></span>
						</div>
						<div class="scalyn-launch-stat">
							<span class="scalyn-launch-stat__value"><?php echo esc_html( $launch_total ); ?></span>
							<span class="scalyn-launch-stat__label"><?php esc_html_e( 'Total Checks', 'scalyn-qa-assistant' ); ?></span>
						</div>
					</div>
					<?php if ( null !== $launch_last_scan ) : ?>
						<p class="scalyn-meta">
							<?php
							printf(
								/* translators: %s: Human-readable time difference. */
								esc_html__( 'Last checked: %s ago', 'scalyn-qa-assistant' ),
								esc_html( human_time_diff( (int) $launch_last_scan, current_time( 'timestamp' ) ) )
							);
							?>
						</p>
					<?php endif; ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=scalyn-qa-launch' ) ); ?>" class="scalyn-btn scalyn-btn--small">
						<?php esc_html_e( 'View Launch Checklist', 'scalyn-qa-assistant' ); ?>
					</a>
				<?php else : ?>
					<p class="scalyn-empty"><?php esc_html_e( 'No launch checks have been run yet.', 'scalyn-qa-assistant' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=scalyn-qa-launch' ) ); ?>" class="scalyn-btn scalyn-btn--small">
						<?php esc_html_e( 'Run Launch Check', 'scalyn-qa-assistant' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Two Column Layout: Recent Scans + Quick Stats -->
	<div class="scalyn-grid scalyn-grid--2">
		<!-- Left: Recent Scans -->
		<?php
		$scans = $recent_scans;
		include SCALYN_QA_PLUGIN_DIR . 'templates/dashboard/widgets/recent-scans.php';
		?>

		<!-- Right: Quick Stats -->
		<div class="scalyn-card">
			<h2 class="scalyn-card-title"><?php esc_html_e( 'Quick Stats', 'scalyn-qa-assistant' ); ?></h2>
			<div class="scalyn-stats-grid">
				<div class="scalyn-stat-item">
					<span class="scalyn-stat-item__value"><?php echo esc_html( $total_pages_attention ); ?></span>
					<span class="scalyn-stat-item__label"><?php esc_html_e( 'Pages Need Attention', 'scalyn-qa-assistant' ); ?></span>
				</div>
				<div class="scalyn-stat-item">
					<span class="scalyn-stat-item__value"><?php echo esc_html( $total_scans ); ?></span>
					<span class="scalyn-stat-item__label"><?php esc_html_e( 'Recent Scans', 'scalyn-qa-assistant' ); ?></span>
				</div>
				<div class="scalyn-stat-item">
					<span class="scalyn-stat-item__value">
						<?php echo esc_html( $overall ); ?><small>%</small>
					</span>
					<span class="scalyn-stat-item__label"><?php esc_html_e( 'Overall Score', 'scalyn-qa-assistant' ); ?></span>
				</div>
				<div class="scalyn-stat-item">
					<span class="scalyn-stat-item__value">
						<?php
						if ( null !== $seo_plugin_status ) {
							echo '<span class="dashicons dashicons-yes-alt scalyn-text--green"></span>';
						} else {
							echo '<span class="dashicons dashicons-minus scalyn-text--muted"></span>';
						}
						?>
					</span>
					<span class="scalyn-stat-item__label"><?php esc_html_e( 'SEO Plugin', 'scalyn-qa-assistant' ); ?></span>
				</div>
			</div>
		</div>
	</div>
</div>
