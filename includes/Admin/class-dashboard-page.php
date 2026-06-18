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
use Scalyn\QA\AI\AI_Manager;
use Scalyn\QA\AI\AI_Health_Monitor;

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
			'project_scores'          => $this->get_project_scores(),
			'pages_needing_attention' => $this->get_pages_needing_attention(),
			'recent_scans'            => $this->get_recent_scans(),
			'seo_plugin_status'       => $this->get_seo_plugin_status(),
			'launch_summary'          => $this->get_launch_summary(),
			'top_issues'              => $this->get_top_issues(),
			'scan_coverage'           => $this->get_scan_coverage(),
			'ai_status'               => $this->get_ai_status(),
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

		// Build ignored check IDs (launch-scoped ignores).
		$ignored_ids = array();
		$launch_ignores = \Scalyn\QA\Models\Ignore_Rule::get_by_context( 'launch' );
		foreach ( $launch_ignores as $rule ) {
			$ignored_ids[ $rule->check_id ] = true;
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
	 * Aggregate top failing checks across all scanned pages.
	 *
	 * @since 1.4.0
	 *
	 * @return array<int, array{id: string, label: string, count: int, category: string}>
	 */
	private function get_top_issues(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_ids = $wpdb->get_col(
			"SELECT DISTINCT post_id
			 FROM {$wpdb->postmeta}
			 WHERE meta_key = '_scalyn_qa_scan_results'",
		);

		if ( empty( $post_ids ) ) {
			return array();
		}

		$issue_counts = array();
		$issue_labels = array();
		$issue_cats   = array();

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			$post    = get_post( $post_id );
			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}

			$scan_data = get_post_meta( $post_id, '_scalyn_qa_scan_results', true );
			if ( ! is_array( $scan_data ) ) {
				continue;
			}

			foreach ( $scan_data as $category => $checks ) {
				if ( ! is_array( $checks ) ) {
					continue;
				}
				foreach ( $checks as $check ) {
					if ( ! is_array( $check ) ) {
						continue;
					}
					$status = $check['status'] ?? 'pass';
					if ( 'pass' === $status ) {
						continue;
					}
					$id    = $check['id'] ?? '';
					$label = $check['label'] ?? $id;
					if ( '' === $id ) {
						continue;
					}
					if ( ! isset( $issue_counts[ $id ] ) ) {
						$issue_counts[ $id ] = 0;
						$issue_labels[ $id ] = $label;
						$issue_cats[ $id ]   = $category;
					}
					++$issue_counts[ $id ];
				}
			}
		}

		arsort( $issue_counts );

		$top = array();
		$i   = 0;
		foreach ( $issue_counts as $id => $count ) {
			if ( ++$i > 10 ) {
				break;
			}
			$top[] = array(
				'id'       => $id,
				'label'    => $issue_labels[ $id ],
				'count'    => $count,
				'category' => $issue_cats[ $id ],
			);
		}

		return $top;
	}

	/**
	 * Get scan coverage — pages scanned vs total scannable pages.
	 *
	 * @since 1.4.0
	 *
	 * @return array{scanned: int, total: int}
	 */
	private function get_scan_coverage(): array {
		$settings   = get_option( 'scalyn_qa_settings', array() );
		$post_types = isset( $settings['post_types'] ) && is_array( $settings['post_types'] ) ? $settings['post_types'] : array( 'post', 'page' );

		$total = 0;
		foreach ( $post_types as $pt ) {
			$count_obj = wp_count_posts( $pt );
			$total    += (int) ( $count_obj->publish ?? 0 );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$scanned = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT pm.post_id)
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			 WHERE pm.meta_key = '_scalyn_qa_scores'
			 AND p.post_status = 'publish'",
		);

		return array(
			'scanned' => $scanned,
			'total'   => $total,
		);
	}

	/**
	 * Get AI provider status for the dashboard.
	 *
	 * @since 1.4.0
	 *
	 * @return array{enabled: bool, provider: string, status: string}
	 */
	private function get_ai_status(): array {
		$ai_manager = new AI_Manager();
		$result     = array(
			'enabled'  => $ai_manager->is_enabled(),
			'provider' => '',
			'status'   => 'not_configured',
		);

		if ( ! $result['enabled'] ) {
			return $result;
		}

		$chain = $ai_manager->get_priority_chain();
		if ( ! empty( $chain ) ) {
			$primary_key     = $chain[0];
			$result['provider'] = ucfirst( $primary_key );
			$health          = AI_Health_Monitor::get_health( $primary_key );
			$result['status'] = $health['status'] ?? 'unknown';
		}

		return $result;
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
