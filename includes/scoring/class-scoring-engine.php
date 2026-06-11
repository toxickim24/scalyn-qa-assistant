<?php
/**
 * Scoring Engine.
 *
 * Calculates QA scores from check results using severity-weighted scoring.
 *
 * @package Scalyn\QA\Scoring
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Scoring;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Models\Check_Item;
use Scalyn\QA\Models\Score;
use Scalyn\QA\Models\Scan_Result;

/**
 * Class Scoring_Engine
 *
 * Provides static methods to calculate per-category and project-wide QA scores
 * from arrays of Check_Item results, weighting each check by its severity.
 *
 * @since 1.0.0
 */
class Scoring_Engine {

	/**
	 * Severity weight map.
	 *
	 * @var array<string, int>
	 */
	private const SEVERITY_WEIGHTS = array(
		'critical' => 3,
		'warning'  => 2,
		'info'     => 1,
	);

	/**
	 * Calculate scores from categorized check results.
	 *
	 * For each category the algorithm is:
	 *   total_weight  = sum of severity weights for every check
	 *   earned_weight = sum of weights for 'pass' checks
	 *                 + 0.5 * sum of weights for 'warning' checks
	 *   score         = round( earned_weight / total_weight * 100 )
	 *
	 * Overall = weighted average using category weights (SEO 40 %, Content 35 %, Functionality 25 %).
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, Check_Item[]> $results Grouped results keyed by category.
	 * @return Score
	 */
	public static function calculate( array $results ): Score {
		$category_scores = array();

		foreach ( array( 'seo', 'content', 'functionality' ) as $category ) {
			$items = $results[ $category ] ?? array();
			$category_scores[ $category ] = self::calculate_category_score( $items );
		}

		$weights = self::get_category_weight( '' );
		$overall = (int) round(
			$category_scores['seo'] * ( $weights['seo'] ?? 0.40 )
			+ $category_scores['content'] * ( $weights['content'] ?? 0.35 )
			+ $category_scores['functionality'] * ( $weights['functionality'] ?? 0.25 )
		);

		$overall = max( 0, min( 100, $overall ) );

		$settings         = get_option( 'scalyn_qa_settings', array() );
		$green_threshold  = (int) ( $settings['green_threshold'] ?? 80 );
		$yellow_threshold = (int) ( $settings['yellow_threshold'] ?? 50 );

		$status = Score::calculate_status( $overall, $green_threshold, $yellow_threshold );

		return new Score(
			seo:           $category_scores['seo'],
			content:       $category_scores['content'],
			functionality: $category_scores['functionality'],
			overall:       $overall,
			status:        $status,
		);
	}

	/**
	 * Get project-wide scores across all scanned posts and pages.
	 *
	 * Queries every published post/page that has scan results stored in postmeta,
	 * then aggregates:
	 *   - SEO Ready %       = average of all per-page SEO scores
	 *   - QA Ready %        = average of all per-page Content + Functionality scores
	 *   - Launch Ready %    = from Launch_Checker results stored in wp_options
	 *   - Overall %         = weighted average of the three above
	 *
	 * @since 1.0.0
	 *
	 * @return array{seo_ready: int, qa_ready: int, launch_ready: int, overall: int}
	 */
	public static function get_project_scores(): array {
		$post_ids = self::get_scanned_post_ids();

		if ( empty( $post_ids ) ) {
			return array(
				'seo_ready'    => 0,
				'qa_ready'     => 0,
				'launch_ready' => 0,
				'overall'      => 0,
			);
		}

		$seo_scores           = array();
		$content_scores       = array();
		$functionality_scores = array();

		foreach ( $post_ids as $post_id ) {
			$scan_result = Scan_Result::load( $post_id );

			if ( null === $scan_result ) {
				continue;
			}

			$scores_array           = $scan_result->scores->to_array();
			$seo_scores[]           = $scores_array['seo'];
			$content_scores[]       = $scores_array['content'];
			$functionality_scores[] = $scores_array['functionality'];
		}

		$seo_ready = self::safe_average( $seo_scores );

		// QA Ready is the average of content and functionality scores combined.
		$qa_scores = array_merge( $content_scores, $functionality_scores );
		$qa_ready  = self::safe_average( $qa_scores );

		// Launch Ready comes from the Launch_Checker stored option.
		$launch_data  = get_option( 'scalyn_qa_launch_results', array() );
		$launch_ready = 0;

		if ( is_array( $launch_data ) && isset( $launch_data['score'] ) ) {
			$launch_ready = max( 0, min( 100, (int) $launch_data['score'] ) );
		}

		// Overall: weighted average of the three pillars.
		$overall = (int) round(
			$seo_ready * 0.35
			+ $qa_ready * 0.35
			+ $launch_ready * 0.30
		);

		$overall = max( 0, min( 100, $overall ) );

		return array(
			'seo_ready'    => $seo_ready,
			'qa_ready'     => $qa_ready,
			'launch_ready' => $launch_ready,
			'overall'      => $overall,
		);
	}

	/**
	 * Get the weight map for score categories.
	 *
	 * The $category parameter is accepted for API consistency but the full map
	 * is always returned. External code may use it to look up a single key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $category Unused — kept for interface consistency.
	 * @return array<string, float> Filterable weight map.
	 */
	public static function get_category_weight( string $category ): array {
		$defaults = array(
			'seo'           => 0.40,
			'content'       => 0.35,
			'functionality' => 0.25,
		);

		/**
		 * Filters the category weight map used when calculating the overall score.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, float> $weights Default weight map.
		 */
		return (array) apply_filters( 'scalyn_qa_score_weights', $defaults );
	}

	// ------------------------------------------------------------------
	// Private helpers
	// ------------------------------------------------------------------

	/**
	 * Calculate a single category score from an array of Check_Items.
	 *
	 * @param Check_Item[] $items Check items for one category.
	 * @return int Score 0-100.
	 */
	private static function calculate_category_score( array $items ): int {
		if ( empty( $items ) ) {
			return 100; // No checks = perfect.
		}

		$total_weight  = 0;
		$earned_weight = 0.0;

		foreach ( $items as $item ) {
			if ( ! $item instanceof Check_Item ) {
				continue;
			}

			$weight       = self::SEVERITY_WEIGHTS[ $item->severity ] ?? 1;
			$total_weight += $weight;

			if ( 'pass' === $item->status ) {
				$earned_weight += $weight;
			} elseif ( 'warning' === $item->status ) {
				$earned_weight += $weight * 0.5;
			}
			// 'fail' earns 0.
		}

		if ( 0 === $total_weight ) {
			return 100;
		}

		$score = (int) round( ( $earned_weight / $total_weight ) * 100 );

		return max( 0, min( 100, $score ) );
	}

	/**
	 * Return post IDs that have scan results stored.
	 *
	 * @return int[]
	 */
	private static function get_scanned_post_ids(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			"SELECT DISTINCT post_id
			 FROM {$wpdb->postmeta}
			 WHERE meta_key = '_scalyn_qa_scan_results'"
		);

		return array_map( 'intval', $ids );
	}

	/**
	 * Safely calculate an integer average from an array of numbers.
	 *
	 * @param int[] $values Numeric values.
	 * @return int Rounded average, or 0 for empty input.
	 */
	private static function safe_average( array $values ): int {
		if ( empty( $values ) ) {
			return 0;
		}

		return (int) round( array_sum( $values ) / count( $values ) );
	}
}
