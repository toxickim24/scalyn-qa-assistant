<?php
/**
 * Dashboard Page.
 *
 * Renders the main Scalyn QA dashboard overview.
 *
 * @package Scalyn\QA\Admin
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Admin;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Scoring\Scoring_Engine;
use Scalyn\QA\Models\Scan_Result;
use Scalyn\QA\Integrations\SEO_Integration;

/**
 * Class Dashboard_Page
 *
 * Renders the QA dashboard overview template with project scores,
 * pages needing attention, and recent scan data.
 *
 * @since 1.0.0
 */
class Dashboard_Page {

	/**
	 * Render the dashboard page.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		$data = array(
			'project_scores'        => $this->get_project_scores(),
			'pages_needing_attention' => $this->get_pages_needing_attention(),
			'recent_scans'          => $this->get_recent_scans(),
			'seo_plugin_status'     => $this->get_seo_plugin_status(),
			'launch_summary'        => $this->get_launch_summary(),
		);

		$this->load_template( 'dashboard/overview.php', $data );
	}

	/**
	 * Get project-wide scores from the Scoring Engine.
	 *
	 * @since 1.0.0
	 *
	 * @return array{seo_ready: int, qa_ready: int, launch_ready: int, overall: int}
	 */
	private function get_project_scores(): array {
		return Scoring_Engine::get_project_scores();
	}

	/**
	 * Get the top 10 lowest-scoring posts that need attention.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{post_id: int, title: string, score: int, status: string, edit_url: string}>
	 */
	private function get_pages_needing_attention(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_ids = $wpdb->get_col(
			"SELECT DISTINCT post_id
			 FROM {$wpdb->postmeta}
			 WHERE meta_key = '_scalyn_qa_scores'",
		);

		if ( empty( $post_ids ) ) {
			return array();
		}

		$pages = array();

		foreach ( $post_ids as $post_id ) {
			$post_id     = (int) $post_id;
			$scores_data = get_post_meta( $post_id, '_scalyn_qa_scores', true );

			if ( ! is_array( $scores_data ) || ! isset( $scores_data['overall'] ) ) {
				continue;
			}

			$post = get_post( $post_id );

			if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
				continue;
			}

			$pages[] = array(
				'post_id'  => $post_id,
				'title'    => get_the_title( $post_id ),
				'score'    => (int) $scores_data['overall'],
				'status'   => $scores_data['status'] ?? 'red',
				'edit_url' => get_edit_post_link( $post_id, 'raw' ) ?? '',
			);
		}

		// Sort by score ascending (lowest first).
		usort(
			$pages,
			static fn( array $a, array $b ): int => $a['score'] <=> $b['score'],
		);

		// Return only the top 10.
		return array_slice( $pages, 0, 10 );
	}

	/**
	 * Get the 10 most recently scanned posts.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{post_id: int, title: string, score: int, status: string, scanned_at: string, edit_url: string}>
	 */
	private function get_recent_scans(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			"SELECT pm.post_id, pm.meta_value AS last_scan
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			 WHERE pm.meta_key = '_scalyn_qa_last_scan'
			 AND p.post_status = 'publish'
			 ORDER BY pm.meta_value DESC
			 LIMIT 10",
		);

		if ( empty( $results ) ) {
			return array();
		}

		$scans = array();

		foreach ( $results as $row ) {
			$post_id     = (int) $row->post_id;
			$scores_data = get_post_meta( $post_id, '_scalyn_qa_scores', true );

			if ( ! is_array( $scores_data ) ) {
				continue;
			}

			$scans[] = array(
				'post_id'    => $post_id,
				'title'      => get_the_title( $post_id ),
				'score'      => (int) ( $scores_data['overall'] ?? 0 ),
				'status'     => $scores_data['status'] ?? 'red',
				'scanned_at' => $row->last_scan,
				'edit_url'   => get_edit_post_link( $post_id, 'raw' ) ?? '',
			);
		}

		return $scans;
	}

	/**
	 * Detect the active SEO plugin and return its name.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null The SEO plugin name, or null if none detected.
	 */
	private function get_seo_plugin_status(): ?string {
		$integration = SEO_Integration::detect();

		if ( null !== $integration ) {
			return $integration->get_plugin_name();
		}

		return null;
	}

	/**
	 * Get a summary of the latest launch check results.
	 *
	 * @since 1.0.0
	 *
	 * @return array{pass: int, fail: int, warning: int, total: int, last_scan: int|null}
	 */
	private function get_launch_summary(): array {
		$results   = get_option( 'scalyn_qa_launch_results', array() );
		$last_scan = get_option( 'scalyn_qa_launch_last_scan', null );

		$summary = array(
			'pass'      => 0,
			'fail'      => 0,
			'warning'   => 0,
			'total'     => 0,
			'last_scan' => $last_scan ? (int) $last_scan : null,
		);

		if ( ! is_array( $results ) ) {
			return $summary;
		}

		// Build ignored check IDs (global ignores).
		$ignored_ids = array();
		$global_ignores = \Scalyn\QA\Models\Ignore_Rule::get_all();
		foreach ( $global_ignores as $rule ) {
			if ( 'global' === $rule->type || null === $rule->post_id || 0 === $rule->post_id ) {
				$ignored_ids[ $rule->check_id ] = true;
			}
		}

		foreach ( $results as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['status'] ) ) {
				continue;
			}

			// Skip ignored checks.
			if ( isset( $ignored_ids[ $item['id'] ?? '' ] ) ) {
				continue;
			}

			++$summary['total'];

			match ( $item['status'] ) {
				'pass'    => ++$summary['pass'],
				'fail'    => ++$summary['fail'],
				'warning' => ++$summary['warning'],
				default   => null,
			};
		}

		return $summary;
	}

	/**
	 * Load a template file with the given data extracted into scope.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template Relative template path (from templates/ directory).
	 * @param array  $data     Data to extract into the template scope.
	 */
	private function load_template( string $template, array $data = array() ): void {
		$template_path = SCALYN_QA_PLUGIN_DIR . 'templates/' . $template;

		if ( ! file_exists( $template_path ) ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: Template file path. */
						__( 'Template not found: %s', 'scalyn-qa-assistant' ),
						$template,
					),
				),
			);
			return;
		}

		// Extract data so variables are accessible in the template.
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $data, EXTR_SKIP );

		include $template_path;
	}
}
