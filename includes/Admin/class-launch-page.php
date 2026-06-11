<?php
/**
 * Launch Page.
 *
 * Renders the launch readiness checklist.
 *
 * @package Scalyn\QA\Admin
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Admin;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Models\Check_Item;
use Scalyn\QA\Models\Ignore_Rule;

/**
 * Class Launch_Page
 *
 * Renders the launch checklist template with stored results and pass/fail/warning counts.
 *
 * @since 1.0.0
 */
class Launch_Page {

	/**
	 * Render the launch checklist page.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		$results   = $this->get_launch_results();
		$last_scan = get_option( 'scalyn_qa_launch_last_scan', null );

		// Filter out ignored checks for count/score calculation.
		$global_ignores = Ignore_Rule::get_all();
		$ignored_ids    = array();
		foreach ( $global_ignores as $rule ) {
			if ( 'global' === $rule->type || 0 === $rule->post_id ) {
				$ignored_ids[ $rule->check_id ] = true;
			}
		}

		$active_results = array_filter(
			$results,
			static fn( Check_Item $item ): bool => ! isset( $ignored_ids[ $item->id ] ),
		);

		$counts = $this->calculate_counts( $active_results );

		$data = array(
			'results'   => $results,
			'counts'    => $counts,
			'last_scan' => $last_scan ? (int) $last_scan : null,
			'score'     => $this->calculate_score( $counts ),
		);

		$this->load_template( 'launch/checklist.php', $data );
	}

	/**
	 * Get the stored launch check results as Check_Item objects.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item[]
	 */
	private function get_launch_results(): array {
		$stored = get_option( 'scalyn_qa_launch_results', array() );

		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return array();
		}

		$items = array();

		foreach ( $stored as $item_data ) {
			if ( ! is_array( $item_data ) ) {
				continue;
			}

			$items[] = Check_Item::from_array( $item_data );
		}

		return $items;
	}

	/**
	 * Calculate pass/fail/warning counts from check results.
	 *
	 * @since 1.0.0
	 *
	 * @param Check_Item[] $results Array of check items.
	 * @return array{pass: int, fail: int, warning: int, total: int}
	 */
	private function calculate_counts( array $results ): array {
		$counts = array(
			'pass'    => 0,
			'fail'    => 0,
			'warning' => 0,
			'total'   => count( $results ),
		);

		foreach ( $results as $item ) {
			if ( ! $item instanceof Check_Item ) {
				continue;
			}

			match ( $item->status ) {
				'pass'    => ++$counts['pass'],
				'fail'    => ++$counts['fail'],
				'warning' => ++$counts['warning'],
				default   => null,
			};
		}

		return $counts;
	}

	/**
	 * Calculate the launch readiness score from counts.
	 *
	 * @since 1.0.0
	 *
	 * @param array{pass: int, fail: int, warning: int, total: int} $counts Status counts.
	 * @return int Score 0-100.
	 */
	private function calculate_score( array $counts ): int {
		if ( 0 === $counts['total'] ) {
			return 0;
		}

		// Pass = full credit, warning = half credit, fail = zero.
		$earned = $counts['pass'] + ( $counts['warning'] * 0.5 );
		$score  = (int) round( ( $earned / $counts['total'] ) * 100 );

		return max( 0, min( 100, $score ) );
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

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $data, EXTR_SKIP );

		include $template_path;
	}
}
