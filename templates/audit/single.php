<?php
/**
 * Template: Single Audit View.
 *
 * Detailed audit report for a single page/post showing all QA checks,
 * scores, AI meta suggestions, notes, and snapshot history.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 *
 * @var \WP_Post                           $post          The post being audited.
 * @var int                                $post_id       The post ID.
 * @var \Scalyn\QA\Models\Scan_Result|null $scan_result   The latest scan result or null.
 * @var \Scalyn\QA\Models\Ignore_Rule[]    $ignore_rules  Active ignore rules for this post.
 * @var \Scalyn\QA\Models\Snapshot[]       $snapshots     Historical snapshots for this post.
 * @var array                              $notes         QA notes for this post.
 * @var string                             $audit_url     URL back to the audit list.
 */

defined( 'ABSPATH' ) || exit;

// Ensure safe defaults.
$post         = isset( $post ) ? $post : null;
$post_id      = isset( $post_id ) ? (int) $post_id : 0;
$scan_result  = isset( $scan_result ) ? $scan_result : null;
$ignore_rules = isset( $ignore_rules ) && is_array( $ignore_rules ) ? $ignore_rules : array();
$snapshots    = isset( $snapshots ) && is_array( $snapshots ) ? $snapshots : array();
$notes        = isset( $notes ) && is_array( $notes ) ? $notes : array();
$audit_url    = isset( $audit_url ) ? $audit_url : admin_url( 'admin.php?page=scalyn-qa-audits' );

// Guard against missing post.
if ( ! $post instanceof \WP_Post ) {
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'Post not found.', 'scalyn-qa-assistant' )
	);
	return;
}

// Extract scores and results from scan_result.
$has_scan   = null !== $scan_result;
$scores     = $has_scan ? $scan_result->scores : null;
$results    = $has_scan ? $scan_result->results : array();
$scanned_at = $has_scan ? $scan_result->scanned_at : '';

// Score values.
$seo_score    = $scores ? $scores->seo : 0;
$cont_score   = $scores ? $scores->content : 0;
$func_score   = $scores ? $scores->functionality : 0;
$overall      = $scores ? $scores->overall : 0;
$status       = $scores ? $scores->status : 'red';

// Individual category results.
$seo_checks     = isset( $results['seo'] ) && is_array( $results['seo'] ) ? $results['seo'] : array();
$content_checks = isset( $results['content'] ) && is_array( $results['content'] ) ? $results['content'] : array();
$func_checks    = isset( $results['functionality'] ) && is_array( $results['functionality'] ) ? $results['functionality'] : array();

// Sort checks: fail first, warning second, pass last.
$sort_checks = static function ( $a, $b ): int {
	$order = array( 'fail' => 0, 'warning' => 1, 'pass' => 2 );
	$a_val = $order[ $a->status ] ?? 3;
	$b_val = $order[ $b->status ] ?? 3;
	return $a_val <=> $b_val;
};
usort( $seo_checks, $sort_checks );
usort( $content_checks, $sort_checks );
usort( $func_checks, $sort_checks );

// Build a lookup of ignored check IDs — both per-post and global.
$ignored_check_ids = array();
foreach ( $ignore_rules as $rule ) {
	$ignored_check_ids[ $rule->check_id ] = $rule;
}
// Also include audit-scoped global ignores.
$audit_ignores = \Scalyn\QA\Models\Ignore_Rule::get_by_context( 'audit' );
foreach ( $audit_ignores as $rule ) {
	if ( 'global' === $rule->type || ( null === $rule->post_id || 0 === $rule->post_id ) ) {
		$ignored_check_ids[ $rule->check_id ] = $rule;
	}
}

// Compute human-readable scan time.
$scan_time_display = '';
if ( ! empty( $scanned_at ) ) {
	$scan_timestamp = strtotime( $scanned_at );
	if ( false !== $scan_timestamp ) {
		$scan_time_display = sprintf(
			/* translators: %s: Human-readable time difference. */
			esc_html__( '%s ago', 'scalyn-qa-assistant' ),
			human_time_diff( $scan_timestamp, time() )
		);
	}
}

// Determine trend.
$trend = \Scalyn\QA\Models\Snapshot::get_trend( $post_id );

$trend_icons = array(
	'improving' => 'dashicons-arrow-up-alt',
	'declining' => 'dashicons-arrow-down-alt',
	'stable'    => 'dashicons-minus',
);

$trend_labels = array(
	'improving' => __( 'Improving', 'scalyn-qa-assistant' ),
	'declining' => __( 'Declining', 'scalyn-qa-assistant' ),
	'stable'    => __( 'Stable', 'scalyn-qa-assistant' ),
);

$trend_icon  = isset( $trend_icons[ $trend ] ) ? $trend_icons[ $trend ] : 'dashicons-minus';
$trend_label = isset( $trend_labels[ $trend ] ) ? $trend_labels[ $trend ] : __( 'Stable', 'scalyn-qa-assistant' );

// Check if AI is configured.
$ai_manager    = new \Scalyn\QA\AI\AI_Manager();
$ai_configured = $ai_manager->is_enabled() && null !== $ai_manager->get_primary_provider();

// Load saved AI meta drafts (latest).
$ai_drafts      = get_post_meta( $post_id, '_scalyn_qa_ai_drafts', true );
$latest_ai_meta = null;
if ( is_array( $ai_drafts ) && ! empty( $ai_drafts ) ) {
	$latest_ai_meta = end( $ai_drafts );
}

// Edit post link.
$edit_post_url = get_edit_post_link( $post_id, 'raw' );
$permalink     = get_permalink( $post_id );
?>
<div class="scalyn-wrap">
	<!-- Header -->
	<div class="scalyn-page-header">
		<h1><?php echo esc_html( $post->post_title ); ?></h1>
		<div class="scalyn-page-header__actions">
			<a href="<?php echo esc_url( $audit_url ); ?>" class="scalyn-btn scalyn-btn--small">
				<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Back to Audits', 'scalyn-qa-assistant' ); ?>
			</a>
			<button
				type="button"
				id="scalyn-rescan"
				class="scalyn-btn"
				data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
			>
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<?php esc_html_e( 'Rescan', 'scalyn-qa-assistant' ); ?>
			</button>
			<?php if ( $ai_configured ) : ?>
				<button
					type="button"
					id="scalyn-generate-all-ai"
					class="scalyn-btn scalyn-btn--ai"
					data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
				>
					<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span>
					<?php esc_html_e( 'Generate with AI', 'scalyn-qa-assistant' ); ?>
				</button>
			<?php endif; ?>
			<button type="button" id="scalyn-add-note" class="scalyn-btn scalyn-btn--secondary" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>">
				<span class="dashicons dashicons-edit" aria-hidden="true"></span>
				<?php esc_html_e( 'Add Note', 'scalyn-qa-assistant' ); ?>
			</button>
			<?php if ( ! empty( $edit_post_url ) ) : ?>
				<a href="<?php echo esc_url( $edit_post_url ); ?>" class="scalyn-btn scalyn-btn--secondary" target="_blank" rel="noopener noreferrer">
					<span class="dashicons dashicons-admin-post" aria-hidden="true"></span>
					<?php esc_html_e( 'Edit Post', 'scalyn-qa-assistant' ); ?>
				</a>
			<?php endif; ?>
			<?php if ( ! empty( $permalink ) ) : ?>
				<a href="<?php echo esc_url( $permalink ); ?>" class="scalyn-btn scalyn-btn--ghost" target="_blank" rel="noopener noreferrer">
					<span class="dashicons dashicons-external" aria-hidden="true"></span>
					<?php esc_html_e( 'View Page', 'scalyn-qa-assistant' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<p class="scalyn-page-header__meta">
			<?php if ( ! empty( $scan_time_display ) ) : ?>
				<?php
				printf(
					/* translators: %s: Human-readable time since last scan. */
					esc_html__( 'Last Scanned: %s', 'scalyn-qa-assistant' ),
					esc_html( $scan_time_display )
				);
				?>
				<span class="scalyn-meta__sep">|</span>
			<?php endif; ?>
			<?php esc_html_e( 'Score:', 'scalyn-qa-assistant' ); ?>
			<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( $status ); ?>">
				<?php echo esc_html( (string) $overall ); ?>
			</span>
			<span class="scalyn-meta__sep">|</span>
			<?php esc_html_e( 'Trend:', 'scalyn-qa-assistant' ); ?>
			<span class="scalyn-trend scalyn-trend--<?php echo esc_attr( $trend ); ?>">
				<span class="dashicons <?php echo esc_attr( $trend_icon ); ?>" aria-hidden="true"></span>
				<?php echo esc_html( $trend_label ); ?>
			</span>
		</p>
	</div>

	<?php if ( ! $has_scan ) : ?>
		<!-- No scan data available -->
		<div class="scalyn-card">
			<div class="scalyn-empty-state">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<h2><?php esc_html_e( 'No Scan Data Available', 'scalyn-qa-assistant' ); ?></h2>
				<p><?php esc_html_e( 'This page has not been scanned yet. Click "Rescan" to perform the first audit.', 'scalyn-qa-assistant' ); ?></p>
				<button
					type="button"
					class="scalyn-btn scalyn-rescan"
					data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
				>
					<?php esc_html_e( 'Run First Scan', 'scalyn-qa-assistant' ); ?>
				</button>
			</div>
		</div>
	<?php else : ?>
		<!-- Score Summary Row -->
		<div class="scalyn-grid scalyn-grid--4">
			<?php
			$label  = __( 'SEO Score', 'scalyn-qa-assistant' );
			$score  = $seo_score;
			$status = \Scalyn\QA\Models\Score::calculate_status( $score );
			include SCALYN_QA_PLUGIN_DIR . 'templates/dashboard/widgets/score-summary.php';

			$label  = __( 'Content Score', 'scalyn-qa-assistant' );
			$score  = $cont_score;
			$status = \Scalyn\QA\Models\Score::calculate_status( $score );
			include SCALYN_QA_PLUGIN_DIR . 'templates/dashboard/widgets/score-summary.php';

			$label  = __( 'Functionality Score', 'scalyn-qa-assistant' );
			$score  = $func_score;
			$status = \Scalyn\QA\Models\Score::calculate_status( $score );
			include SCALYN_QA_PLUGIN_DIR . 'templates/dashboard/widgets/score-summary.php';

			$label  = __( 'Overall Score', 'scalyn-qa-assistant' );
			$score  = $overall;
			$status = $scores->status;
			include SCALYN_QA_PLUGIN_DIR . 'templates/dashboard/widgets/score-summary.php';
			?>
		</div>

		<!-- SEO Checks Section -->
		<div class="scalyn-card" id="scalyn-section-seo">
			<h2 class="scalyn-card-title">
				<?php esc_html_e( 'SEO Checks', 'scalyn-qa-assistant' ); ?>
				<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( \Scalyn\QA\Models\Score::calculate_status( $seo_score ) ); ?>">
					<?php echo esc_html( (string) $seo_score ); ?>
				</span>
			</h2>
			<div class="scalyn-check-list">
				<?php if ( empty( $seo_checks ) ) : ?>
					<p class="scalyn-empty"><?php esc_html_e( 'No SEO checks available.', 'scalyn-qa-assistant' ); ?></p>
				<?php else : ?>
					<?php foreach ( $seo_checks as $check ) : ?>
						<?php
						// Skip ignored checks.
						if ( isset( $ignored_check_ids[ $check->id ] ) ) {
							continue;
						}

						$item = $check->to_array();
						include SCALYN_QA_PLUGIN_DIR . 'templates/partials/check-item.php';
						?>
					<?php endforeach; ?>

					<?php
					// Show ignored checks in a collapsed section.
					$ignored_seo = array_filter(
						$seo_checks,
						function ( $check ) use ( $ignored_check_ids ) {
							return isset( $ignored_check_ids[ $check->id ] );
						}
					);

					if ( ! empty( $ignored_seo ) ) :
						?>
						<details class="scalyn-ignored-section">
							<summary class="scalyn-ignored-section__toggle">
								<?php
								printf(
									/* translators: %d: Number of ignored checks. */
									esc_html( _n(
										'%d ignored check',
										'%d ignored checks',
										count( $ignored_seo ),
										'scalyn-qa-assistant'
									) ),
									count( $ignored_seo )
								);
								?>
							</summary>
							<div class="scalyn-ignored-section__list">
								<?php foreach ( $ignored_seo as $check ) :
									$rule = $ignored_check_ids[ $check->id ] ?? null;
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

		<!-- Content Checks Section -->
		<div class="scalyn-card" id="scalyn-section-content">
			<h2 class="scalyn-card-title">
				<?php esc_html_e( 'Content Checks', 'scalyn-qa-assistant' ); ?>
				<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( \Scalyn\QA\Models\Score::calculate_status( $cont_score ) ); ?>">
					<?php echo esc_html( (string) $cont_score ); ?>
				</span>
			</h2>
			<div class="scalyn-check-list">
				<?php if ( empty( $content_checks ) ) : ?>
					<p class="scalyn-empty"><?php esc_html_e( 'No content checks available.', 'scalyn-qa-assistant' ); ?></p>
				<?php else : ?>
					<?php foreach ( $content_checks as $check ) : ?>
						<?php
						if ( isset( $ignored_check_ids[ $check->id ] ) ) {
							continue;
						}

						$item = $check->to_array();
						include SCALYN_QA_PLUGIN_DIR . 'templates/partials/check-item.php';
						?>
					<?php endforeach; ?>

					<?php
					$ignored_content = array_filter(
						$content_checks,
						function ( $check ) use ( $ignored_check_ids ) {
							return isset( $ignored_check_ids[ $check->id ] );
						}
					);

					if ( ! empty( $ignored_content ) ) :
						?>
						<details class="scalyn-ignored-section">
							<summary class="scalyn-ignored-section__toggle">
								<?php
								printf(
									/* translators: %d: Number of ignored checks. */
									esc_html( _n(
										'%d ignored check',
										'%d ignored checks',
										count( $ignored_content ),
										'scalyn-qa-assistant'
									) ),
									count( $ignored_content )
								);
								?>
							</summary>
							<div class="scalyn-ignored-section__list">
								<?php foreach ( $ignored_content as $check ) : ?>
									<?php
									$item = $check->to_array();
									include SCALYN_QA_PLUGIN_DIR . 'templates/partials/check-item.php';
									?>
								<?php endforeach; ?>
							</div>
						</details>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>

		<!-- Functionality Checks Section -->
		<div class="scalyn-card" id="scalyn-section-functionality">
			<h2 class="scalyn-card-title">
				<?php esc_html_e( 'Functionality Checks', 'scalyn-qa-assistant' ); ?>
				<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( \Scalyn\QA\Models\Score::calculate_status( $func_score ) ); ?>">
					<?php echo esc_html( (string) $func_score ); ?>
				</span>
			</h2>
			<div class="scalyn-check-list">
				<?php if ( empty( $func_checks ) ) : ?>
					<p class="scalyn-empty"><?php esc_html_e( 'No functionality checks available.', 'scalyn-qa-assistant' ); ?></p>
				<?php else : ?>
					<?php foreach ( $func_checks as $check ) : ?>
						<?php
						if ( isset( $ignored_check_ids[ $check->id ] ) ) {
							continue;
						}

						$item = $check->to_array();
						include SCALYN_QA_PLUGIN_DIR . 'templates/partials/check-item.php';
						?>
					<?php endforeach; ?>

					<?php
					$ignored_func = array_filter(
						$func_checks,
						function ( $check ) use ( $ignored_check_ids ) {
							return isset( $ignored_check_ids[ $check->id ] );
						}
					);

					if ( ! empty( $ignored_func ) ) :
						?>
						<details class="scalyn-ignored-section">
							<summary class="scalyn-ignored-section__toggle">
								<?php
								printf(
									/* translators: %d: Number of ignored checks. */
									esc_html( _n(
										'%d ignored check',
										'%d ignored checks',
										count( $ignored_func ),
										'scalyn-qa-assistant'
									) ),
									count( $ignored_func )
								);
								?>
							</summary>
							<div class="scalyn-ignored-section__list">
								<?php foreach ( $ignored_func as $check ) :
									$rule = $ignored_check_ids[ $check->id ] ?? null;
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

		<?php if ( $ai_configured ) : ?>
			<!-- AI Content Review Section -->
			<?php $saved_review = get_post_meta( $post_id, '_scalyn_qa_content_review', true ); ?>
			<div class="scalyn-card" id="scalyn-ai-review-section">
				<h2 class="scalyn-card-title">
					<span class="dashicons dashicons-editor-spellcheck" aria-hidden="true"></span>
					<?php esc_html_e( 'AI Content Review', 'scalyn-qa-assistant' ); ?>
					<?php if ( ! empty( $saved_review['reviewed_at'] ) ) : ?>
						<?php
						$review_ts = strtotime( $saved_review['reviewed_at'] );
						if ( false !== $review_ts ) :
						?>
							<small style="font-weight:normal;font-size:0.75rem;color:var(--scalyn-text-muted);">
							<?php
							printf(
								esc_html__( 'Last reviewed: %s ago', 'scalyn-qa-assistant' ),
								esc_html( human_time_diff( $review_ts, time() ) )
							);
							?>
							</small>
						<?php endif; ?>
					<?php endif; ?>
				</h2>

				<?php if ( empty( $saved_review ) ) : ?>
					<div id="scalyn-review-empty" class="scalyn-empty-state" style="padding:1rem 0;">
						<p class="scalyn-empty"><?php esc_html_e( 'No AI content review yet. Click "Generate with AI" to check spelling, grammar, and readability.', 'scalyn-qa-assistant' ); ?></p>
					</div>
				<?php endif; ?>

				<div id="scalyn-review-results" style="display:none;">
					<!-- Summary -->
					<div class="scalyn-ai-result" id="scalyn-review-summary">
						<div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.5rem;">
							<h3 class="scalyn-ai-result__label" style="margin:0;"><?php esc_html_e( 'Writing Quality', 'scalyn-qa-assistant' ); ?></h3>
							<span id="scalyn-review-score-badge" class="scalyn-badge"></span>
						</div>
						<p id="scalyn-review-summary-text" class="scalyn-ai-result__text"></p>
					</div>

					<!-- Issues Table -->
					<div id="scalyn-review-issues-wrap" style="display:none;">
						<table class="scalyn-table scalyn-table--compact">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Type', 'scalyn-qa-assistant' ); ?></th>
									<th><?php esc_html_e( 'Severity', 'scalyn-qa-assistant' ); ?></th>
									<th><?php esc_html_e( 'Issue', 'scalyn-qa-assistant' ); ?></th>
									<th><?php esc_html_e( 'Suggestion', 'scalyn-qa-assistant' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'scalyn-qa-assistant' ); ?></th>
								</tr>
							</thead>
							<tbody id="scalyn-review-issues-body">
							</tbody>
						</table>
					</div>

					<!-- Regenerate -->
					<div class="scalyn-ai-result__footer" style="margin-top:0.75rem;">
						<button type="button" id="scalyn-review-regenerate" class="scalyn-btn scalyn-btn--secondary" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>">
							<span class="dashicons dashicons-update" aria-hidden="true"></span>
							<?php esc_html_e( 'Review Again', 'scalyn-qa-assistant' ); ?>
						</button>
					</div>
				</div>

				<div id="scalyn-review-error" class="scalyn-notice scalyn-notice--error" style="display:none;">
					<p id="scalyn-review-error-text"></p>
				</div>
			</div>
			<?php if ( is_array( $saved_review ) && ! empty( $saved_review['summary'] ) ) : ?>
				<script type="application/json" id="scalyn-saved-review-data"><?php echo wp_json_encode( $saved_review ); ?></script>
			<?php endif; ?>
		<?php endif; ?>

	<?php if ( null !== $latest_ai_meta ) : ?>
		<script type="application/json" id="scalyn-saved-ai-meta"><?php echo wp_json_encode( $latest_ai_meta ); ?></script>
	<?php endif; ?>

	<?php
	$saved_alt_texts = get_post_meta( $post_id, '_scalyn_qa_ai_alt_texts', true );
	if ( is_array( $saved_alt_texts ) && ! empty( $saved_alt_texts['results'] ) ) :
	?>
		<script type="application/json" id="scalyn-saved-ai-alt-texts"><?php echo wp_json_encode( $saved_alt_texts ); ?></script>
	<?php endif; ?>
	<?php endif; ?>

	<!-- Notes Section -->
	<div class="scalyn-card" id="scalyn-notes-section">
		<h2 class="scalyn-card-title">
			<?php esc_html_e( 'QA Notes', 'scalyn-qa-assistant' ); ?>
			<?php if ( ! empty( $notes ) ) : ?>
				<span class="scalyn-badge scalyn-badge--neutral"><?php echo esc_html( (string) count( $notes ) ); ?></span>
			<?php endif; ?>
		</h2>

		<!-- Add Note Form (hidden by default, toggled by Add Note button) -->
		<div id="scalyn-note-form" class="scalyn-note-form" style="display:none;">
			<textarea
				id="scalyn-note-content"
				class="scalyn-textarea"
				rows="3"
				placeholder="<?php esc_attr_e( 'Write a QA note...', 'scalyn-qa-assistant' ); ?>"
			></textarea>
			<div class="scalyn-note-form__actions">
				<button type="button" id="scalyn-save-note" class="scalyn-btn scalyn-btn--small" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>">
					<?php esc_html_e( 'Save Note', 'scalyn-qa-assistant' ); ?>
				</button>
				<button type="button" id="scalyn-cancel-note" class="scalyn-btn scalyn-btn--small scalyn-btn--ghost">
					<?php esc_html_e( 'Cancel', 'scalyn-qa-assistant' ); ?>
				</button>
			</div>
		</div>

		<div id="scalyn-notes-list">
			<?php if ( empty( $notes ) ) : ?>
				<p class="scalyn-empty" id="scalyn-notes-empty">
					<?php esc_html_e( 'No QA notes yet. Click "Add Note" to create one.', 'scalyn-qa-assistant' ); ?>
				</p>
			<?php else : ?>
				<table class="scalyn-table scalyn-table--compact">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Note', 'scalyn-qa-assistant' ); ?></th>
							<th style="width:100px;"><?php esc_html_e( 'Author', 'scalyn-qa-assistant' ); ?></th>
							<th style="width:130px;white-space:nowrap;"><?php esc_html_e( 'Date', 'scalyn-qa-assistant' ); ?></th>
							<th style="width:50px;"></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $notes as $index => $note ) :
							$note_content = $note['content'] ?? '';
							$note_author  = $note['author'] ?? $note['user_name'] ?? '';
							$note_date    = $note['created_at'] ?? $note['date'] ?? '';
							$note_time    = '';
							if ( $note_date ) {
								$ts = strtotime( $note_date );
								if ( $ts ) {
									$note_time = human_time_diff( $ts, time() ) . ' ' . __( 'ago', 'scalyn-qa-assistant' );
								}
							}
							?>
							<tr>
								<td><?php echo esc_html( $note_content ); ?></td>
								<td><?php echo esc_html( $note_author ); ?></td>
								<td style="white-space:nowrap;" title="<?php echo esc_attr( $note_date ); ?>"><?php echo esc_html( $note_time ); ?></td>
								<td>
									<button
										type="button"
										class="scalyn-btn scalyn-btn--small scalyn-btn--ghost scalyn-delete-note"
										data-index="<?php echo esc_attr( (string) $index ); ?>"
										data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
										title="<?php esc_attr_e( 'Delete', 'scalyn-qa-assistant' ); ?>"
									>
										<span class="dashicons dashicons-trash" aria-hidden="true"></span>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>

	<!-- Snapshots Section -->
	<div class="scalyn-card" id="scalyn-snapshots-section">
		<h2 class="scalyn-card-title">
			<?php esc_html_e( 'Audit Snapshots', 'scalyn-qa-assistant' ); ?>
			<?php if ( ! empty( $snapshots ) ) : ?>
				<span class="scalyn-badge scalyn-badge--neutral"><?php echo esc_html( (string) count( $snapshots ) ); ?></span>
			<?php endif; ?>
			<span class="scalyn-trend scalyn-trend--<?php echo esc_attr( $trend ); ?>">
				<span class="dashicons <?php echo esc_attr( $trend_icon ); ?>" aria-hidden="true"></span>
				<?php echo esc_html( $trend_label ); ?>
			</span>
		</h2>

		<?php if ( empty( $snapshots ) ) : ?>
			<p class="scalyn-empty">
				<?php esc_html_e( 'No snapshots yet. Snapshots are created automatically each time you scan.', 'scalyn-qa-assistant' ); ?>
			</p>
		<?php else : ?>
			<table class="scalyn-table scalyn-table--compact">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'scalyn-qa-assistant' ); ?></th>
						<th><?php esc_html_e( 'SEO', 'scalyn-qa-assistant' ); ?></th>
						<th><?php esc_html_e( 'Content', 'scalyn-qa-assistant' ); ?></th>
						<th><?php esc_html_e( 'Func.', 'scalyn-qa-assistant' ); ?></th>
						<th><?php esc_html_e( 'Overall', 'scalyn-qa-assistant' ); ?></th>
						<th><?php esc_html_e( 'Pass', 'scalyn-qa-assistant' ); ?></th>
						<th><?php esc_html_e( 'Warn', 'scalyn-qa-assistant' ); ?></th>
						<th><?php esc_html_e( 'Fail', 'scalyn-qa-assistant' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					// Show snapshots in reverse chronological order.
					$snapshots_reversed = array_reverse( $snapshots );

					foreach ( $snapshots_reversed as $snapshot ) :
						$snap_scores  = $snapshot->scores;
						$snap_summary = $snapshot->summary;
						$snap_date    = $snapshot->created_at;

						$snap_time_display = '';
						if ( ! empty( $snap_date ) ) {
							$snap_timestamp = strtotime( $snap_date );
							if ( false !== $snap_timestamp ) {
								$snap_time_display = sprintf(
									/* translators: %s: Human-readable time difference. */
									esc_html__( '%s ago', 'scalyn-qa-assistant' ),
									human_time_diff( $snap_timestamp, time() )
								);
							}
						}

						$snap_pass    = isset( $snap_summary['pass'] ) ? (int) $snap_summary['pass'] : 0;
						$snap_warning = isset( $snap_summary['warning'] ) ? (int) $snap_summary['warning'] : 0;
						$snap_fail    = isset( $snap_summary['fail'] ) ? (int) $snap_summary['fail'] : 0;
						?>
						<tr>
							<td>
								<span title="<?php echo esc_attr( $snap_date ); ?>">
									<?php echo esc_html( $snap_time_display ); ?>
								</span>
							</td>
							<td>
								<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( \Scalyn\QA\Models\Score::calculate_status( $snap_scores->seo ) ); ?>">
									<?php echo esc_html( (string) $snap_scores->seo ); ?>
								</span>
							</td>
							<td>
								<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( \Scalyn\QA\Models\Score::calculate_status( $snap_scores->content ) ); ?>">
									<?php echo esc_html( (string) $snap_scores->content ); ?>
								</span>
							</td>
							<td>
								<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( \Scalyn\QA\Models\Score::calculate_status( $snap_scores->functionality ) ); ?>">
									<?php echo esc_html( (string) $snap_scores->functionality ); ?>
								</span>
							</td>
							<td>
								<span class="scalyn-badge scalyn-badge--<?php echo esc_attr( $snap_scores->status ); ?>">
									<?php echo esc_html( (string) $snap_scores->overall ); ?>
								</span>
							</td>
							<td><span class="scalyn-text--green"><?php echo esc_html( (string) $snap_pass ); ?></span></td>
							<td><span class="scalyn-text--yellow"><?php echo esc_html( (string) $snap_warning ); ?></span></td>
							<td><span class="scalyn-text--red"><?php echo esc_html( (string) $snap_fail ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

	</div>

	<!-- Ignored Rules Summary -->
	<?php if ( ! empty( $ignore_rules ) ) : ?>
		<div class="scalyn-card" id="scalyn-ignore-rules-section">
			<h2 class="scalyn-card-title">
				<?php esc_html_e( 'Ignored Checks', 'scalyn-qa-assistant' ); ?>
				<span class="scalyn-badge scalyn-badge--neutral"><?php echo esc_html( (string) count( $ignore_rules ) ); ?></span>
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
					<?php foreach ( $ignore_rules as $rule ) : ?>
						<tr data-rule-id="<?php echo esc_attr( $rule->id ); ?>">
							<td><code><?php echo esc_html( $rule->check_id ); ?></code></td>
							<td>
								<span class="scalyn-badge scalyn-badge--neutral">
									<?php echo esc_html( ucfirst( $rule->type ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $rule->reason ); ?></td>
							<td><?php echo esc_html( $rule->created_by ); ?></td>
							<td>
								<button
									type="button"
									class="scalyn-btn scalyn-btn--small scalyn-btn--ghost scalyn-remove-ignore"
									data-rule-id="<?php echo esc_attr( $rule->id ); ?>"
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
</div>
