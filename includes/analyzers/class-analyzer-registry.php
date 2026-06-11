<?php
/**
 * Analyzer Registry.
 *
 * Manages registration and execution of all QA analyzers.
 *
 * @package Scalyn\QA\Analyzers
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Analyzers;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Models\Check_Item;

/**
 * Class Analyzer_Registry
 *
 * Central registry for all QA analyzers. Handles registration, retrieval,
 * and orchestrated execution of analyzer checks.
 *
 * @since 1.0.0
 */
class Analyzer_Registry {

	/**
	 * Registered analyzers keyed by their ID.
	 *
	 * @var array<string, Analyzer_Interface>
	 */
	private array $analyzers = array();

	/**
	 * Register an analyzer.
	 *
	 * @since 1.0.0
	 *
	 * @param Analyzer_Interface $analyzer The analyzer to register.
	 * @return void
	 */
	public function register( Analyzer_Interface $analyzer ): void {
		$this->analyzers[ $analyzer->get_id() ] = $analyzer;
	}

	/**
	 * Get all registered analyzers.
	 *
	 * @since 1.0.0
	 *
	 * @return Analyzer_Interface[]
	 */
	public function get_all(): array {
		return $this->analyzers;
	}

	/**
	 * Get analyzers filtered by category.
	 *
	 * @since 1.0.0
	 *
	 * @param string $category The category to filter by ('seo', 'content', or 'functionality').
	 * @return Analyzer_Interface[]
	 */
	public function get_by_category( string $category ): array {
		return array_filter(
			$this->analyzers,
			static fn( Analyzer_Interface $analyzer ): bool => $analyzer->get_category() === $category,
		);
	}

	/**
	 * Run all registered analyzers on a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to analyze.
	 * @return array{seo: Check_Item[], content: Check_Item[], functionality: Check_Item[]}
	 */
	public function run_all( int $post_id ): array {
		$results = array(
			'seo'           => array(),
			'content'       => array(),
			'functionality' => array(),
		);

		foreach ( $this->analyzers as $analyzer ) {
			$category = $analyzer->get_category();
			$items    = $analyzer->analyze( $post_id );

			if ( isset( $results[ $category ] ) ) {
				$results[ $category ] = array_merge( $results[ $category ], $items );
			}
		}

		return $results;
	}

	/**
	 * Run all analyzers in a specific category on a post.
	 *
	 * @since 1.0.0
	 *
	 * @param string $category The category to run ('seo', 'content', or 'functionality').
	 * @param int    $post_id  The post ID to analyze.
	 * @return Check_Item[]
	 */
	public function run_category( string $category, int $post_id ): array {
		$results   = array();
		$analyzers = $this->get_by_category( $category );

		foreach ( $analyzers as $analyzer ) {
			$items   = $analyzer->analyze( $post_id );
			$results = array_merge( $results, $items );
		}

		return $results;
	}
}
