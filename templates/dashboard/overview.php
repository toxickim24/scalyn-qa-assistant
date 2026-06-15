<?php
/**
 * Template: Dashboard Overview.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$project_scores          = isset( $project_scores ) && is_array( $project_scores ) ? $project_scores : array();
$pages_needing_attention = isset( $pages_needing_attention ) && is_array( $pages_needing_attention ) ? $pages_needing_attention : array();
$recent_scans            = isset( $recent_scans ) && is_array( $recent_scans ) ? $recent_scans : array();
$seo_plugin_status       = isset( $seo_plugin_status ) ? $seo_plugin_status : null;
$launch_summary          = isset( $launch_summary ) && is_array( $launch_summary ) ? $launch_summary : array();

$seo_ready    = (int) ( $project_scores['seo_ready'] ?? 0 );
$qa_ready     = (int) ( $project_scores['qa_ready'] ?? 0 );
$launch_ready = (int) ( $project_scores['launch_ready'] ?? 0 );
$overall      = (int) ( $project_scores['overall'] ?? 0 );

$launch_pass      = (int) ( $launch_summary['pass'] ?? 0 );
$launch_fail      = (int) ( $launch_summary['fail'] ?? 0 );
$launch_warning   = (int) ( $launch_summary['warning'] ?? 0 );
$launch_total     = (int) ( $launch_summary['total'] ?? 0 );
$launch_last_scan = $launch_summary['last_scan'] ?? null;

$total_pages_attention = count( $pages_needing_attention );
$total_scans           = count( $recent_scans );

// Overall status
$overall_status = \Scalyn\QA\Models\Score::calculate_status( $overall );
$overall_label  = match ( $overall_status ) {
	'green'  => __( 'Looking Good', 'scalyn-qa-assistant' ),
	'yellow' => __( 'Needs Attention', 'scalyn-qa-assistant' ),
	'red'    => __( 'Action Required', 'scalyn-qa-assistant' ),
	default  => '',
};
?>
<div class="scalyn-wrap">
	<!-- Header -->
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

	<!-- Hero: Overall Score + Category Scores -->
	<div class="scalyn-dashboard-hero">
		<div class="scalyn-dashboard-hero__main">
			<div class="scalyn-score-circle scalyn-score-circle--large scalyn-score-circle--<?php echo esc_attr( $overall_status ); ?>"
				 style="--scalyn-score: <?php echo esc_attr( (string) $overall ); ?>">
				<span class="scalyn-score-circle__value"><?php echo esc_html( (string) $overall ); ?><span class="scalyn-score-circle__unit">%</span></span>
			</div>
			<div class="scalyn-dashboard-hero__meta">
				<span class="scalyn-dashboard-hero__label"><?php esc_html_e( 'Overall Score', 'scalyn-qa-assistant' ); ?></span>
				<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( $overall_status ); ?>"><?php echo esc_html( $overall_label ); ?></span>
				<span class="scalyn-dashboard-hero__formula"><?php esc_html_e( 'SEO 35% + QA 35% + Launch 30%', 'scalyn-qa-assistant' ); ?></span>
			</div>
		</div>
		<div class="scalyn-dashboard-hero__categories">
			<?php
			$categories = array(
				array(
					'label' => __( 'SEO', 'scalyn-qa-assistant' ),
					'score' => $seo_ready,
					'icon'  => 'dashicons-search',
					'desc'  => __( 'Average SEO score across all scanned pages', 'scalyn-qa-assistant' ),
				),
				array(
					'label' => __( 'QA', 'scalyn-qa-assistant' ),
					'score' => $qa_ready,
					'icon'  => 'dashicons-yes-alt',
					'desc'  => __( 'Average content & functionality score across all scanned pages', 'scalyn-qa-assistant' ),
				),
				array(
					'label' => __( 'Launch', 'scalyn-qa-assistant' ),
					'score' => $launch_ready,
					'icon'  => 'dashicons-migrate',
					'desc'  => __( 'Launch checklist readiness score', 'scalyn-qa-assistant' ),
				),
			);
			foreach ( $categories as $cat ) :
				$cat_status = \Scalyn\QA\Models\Score::calculate_status( $cat['score'] );
			?>
				<div class="scalyn-category-score" title="<?php echo esc_attr( $cat['desc'] ); ?>">
					<div class="scalyn-category-score__header">
						<span class="dashicons <?php echo esc_attr( $cat['icon'] ); ?>" aria-hidden="true"></span>
						<span class="scalyn-category-score__label"><?php echo esc_html( $cat['label'] ); ?></span>
					</div>
					<div class="scalyn-category-score__bar">
						<div class="scalyn-category-score__fill scalyn-category-score__fill--<?php echo esc_attr( $cat_status ); ?>" style="width:<?php echo esc_attr( (string) $cat['score'] ); ?>%"></div>
					</div>
					<span class="scalyn-category-score__value"><?php echo esc_html( (string) $cat['score'] ); ?>%</span>
					<span class="scalyn-category-score__desc"><?php echo esc_html( $cat['desc'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- Three Column: Site Status + Environment + Actions -->
	<div class="scalyn-grid scalyn-grid--3">
		<!-- Site Status -->
		<div class="scalyn-card">
			<h2 class="scalyn-card-title"><?php esc_html_e( 'Site Status', 'scalyn-qa-assistant' ); ?></h2>
			<div class="scalyn-kpi-list">
				<div class="scalyn-kpi">
					<div class="scalyn-kpi__icon scalyn-kpi__icon--<?php echo null !== $seo_plugin_status ? 'success' : 'danger'; ?>">
						<span class="dashicons <?php echo null !== $seo_plugin_status ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
					</div>
					<div class="scalyn-kpi__content">
						<span class="scalyn-kpi__value"><?php echo null !== $seo_plugin_status ? esc_html( $seo_plugin_status ) : esc_html__( 'Not Installed', 'scalyn-qa-assistant' ); ?></span>
						<span class="scalyn-kpi__label"><?php esc_html_e( 'SEO Plugin', 'scalyn-qa-assistant' ); ?></span>
					</div>
				</div>
				<div class="scalyn-kpi">
					<div class="scalyn-kpi__icon scalyn-kpi__icon--<?php echo $launch_fail > 0 ? 'danger' : ( $launch_warning > 0 ? 'warning' : 'success' ); ?>">
						<span class="dashicons dashicons-migrate" aria-hidden="true"></span>
					</div>
					<div class="scalyn-kpi__content">
						<span class="scalyn-kpi__value"><?php echo esc_html( $launch_pass . '/' . $launch_total ); ?></span>
						<span class="scalyn-kpi__label"><?php esc_html_e( 'Launch Checks Passed', 'scalyn-qa-assistant' ); ?></span>
					</div>
				</div>
				<div class="scalyn-kpi">
					<div class="scalyn-kpi__icon scalyn-kpi__icon--<?php echo is_ssl() ? 'success' : 'danger'; ?>">
						<span class="dashicons <?php echo is_ssl() ? 'dashicons-lock' : 'dashicons-unlock'; ?>" aria-hidden="true"></span>
					</div>
					<div class="scalyn-kpi__content">
						<span class="scalyn-kpi__value"><?php echo is_ssl() ? 'HTTPS' : 'HTTP'; ?></span>
						<span class="scalyn-kpi__label"><?php esc_html_e( 'SSL Status', 'scalyn-qa-assistant' ); ?></span>
					</div>
				</div>
				<div class="scalyn-kpi">
					<div class="scalyn-kpi__icon scalyn-kpi__icon--primary">
						<span class="dashicons dashicons-clock" aria-hidden="true"></span>
					</div>
					<div class="scalyn-kpi__content">
						<span class="scalyn-kpi__value"><?php
						if ( null !== $launch_last_scan ) {
							$ts = is_numeric( $launch_last_scan ) ? (int) $launch_last_scan : strtotime( $launch_last_scan );
							echo esc_html( human_time_diff( $ts, time() ) . ' ' . __( 'ago', 'scalyn-qa-assistant' ) );
						} else {
							esc_html_e( 'Never', 'scalyn-qa-assistant' );
						}
						?></span>
						<span class="scalyn-kpi__label"><?php esc_html_e( 'Last Launch Check', 'scalyn-qa-assistant' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<!-- Environment -->
		<div class="scalyn-card">
			<h2 class="scalyn-card-title"><?php esc_html_e( 'Environment', 'scalyn-qa-assistant' ); ?></h2>
			<div class="scalyn-kpi-list">
				<div class="scalyn-kpi">
					<div class="scalyn-kpi__icon scalyn-kpi__icon--primary">
						<span class="dashicons dashicons-wordpress" aria-hidden="true"></span>
					</div>
					<div class="scalyn-kpi__content">
						<span class="scalyn-kpi__value"><?php echo esc_html( get_bloginfo( 'version' ) ); ?></span>
						<span class="scalyn-kpi__label"><?php esc_html_e( 'WordPress', 'scalyn-qa-assistant' ); ?></span>
					</div>
				</div>
				<div class="scalyn-kpi">
					<div class="scalyn-kpi__icon scalyn-kpi__icon--<?php echo version_compare( phpversion(), '8.2', '>=' ) ? 'success' : 'warning'; ?>">
						<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
					</div>
					<div class="scalyn-kpi__content">
						<span class="scalyn-kpi__value"><?php echo esc_html( phpversion() ); ?></span>
						<span class="scalyn-kpi__label"><?php esc_html_e( 'PHP Version', 'scalyn-qa-assistant' ); ?></span>
					</div>
				</div>
				<div class="scalyn-kpi">
					<div class="scalyn-kpi__icon scalyn-kpi__icon--neutral">
						<span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span>
					</div>
					<div class="scalyn-kpi__content">
						<span class="scalyn-kpi__value"><?php echo esc_html( wp_get_theme()->get( 'Name' ) ); ?></span>
						<span class="scalyn-kpi__label"><?php esc_html_e( 'Active Theme', 'scalyn-qa-assistant' ); ?></span>
					</div>
				</div>
				<div class="scalyn-kpi">
					<div class="scalyn-kpi__icon scalyn-kpi__icon--neutral">
						<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
					</div>
					<div class="scalyn-kpi__content">
						<?php
						$settings   = get_option( 'scalyn_qa_settings', array() );
						$post_types = isset( $settings['post_types'] ) && is_array( $settings['post_types'] ) ? $settings['post_types'] : array( 'post', 'page' );
						?>
						<span class="scalyn-kpi__value"><?php echo esc_html( implode( ', ', array_map( 'ucfirst', $post_types ) ) ); ?></span>
						<span class="scalyn-kpi__label"><?php esc_html_e( 'Scanned Post Types', 'scalyn-qa-assistant' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<!-- Quick Actions -->
		<div class="scalyn-card">
			<h2 class="scalyn-card-title"><?php esc_html_e( 'Quick Actions', 'scalyn-qa-assistant' ); ?></h2>
			<div class="scalyn-actions-list">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=scalyn-qa-audits' ) ); ?>" class="scalyn-action-link">
					<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
					<span><?php esc_html_e( 'View All Page Audits', 'scalyn-qa-assistant' ); ?></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=scalyn-qa-launch' ) ); ?>" class="scalyn-action-link">
					<span class="dashicons dashicons-migrate" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Run Launch Checklist', 'scalyn-qa-assistant' ); ?></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=scalyn-qa-settings&tab=ai-providers' ) ); ?>" class="scalyn-action-link">
					<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Configure AI Providers', 'scalyn-qa-assistant' ); ?></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=scalyn-qa-knowledge' ) ); ?>" class="scalyn-action-link">
					<span class="dashicons dashicons-book" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Knowledge Center', 'scalyn-qa-assistant' ); ?></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=scalyn-qa-system-info' ) ); ?>" class="scalyn-action-link">
					<span class="dashicons dashicons-info" aria-hidden="true"></span>
					<span><?php esc_html_e( 'System Information', 'scalyn-qa-assistant' ); ?></span>
				</a>
			</div>
		</div>
	</div>

	<!-- Two Column: Pages Needing Attention + Recent Scans -->
	<div class="scalyn-grid scalyn-grid--2">
		<?php
		$pages = $pages_needing_attention;
		include SCALYN_QA_PLUGIN_DIR . 'templates/dashboard/widgets/pages-attention.php';
		?>
		<?php
		$scans = $recent_scans;
		include SCALYN_QA_PLUGIN_DIR . 'templates/dashboard/widgets/recent-scans.php';
		?>
	</div>
</div>
