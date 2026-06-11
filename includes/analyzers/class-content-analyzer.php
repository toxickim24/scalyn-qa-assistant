<?php
/**
 * Content Analyzer.
 *
 * Performs content quality checks by delegating to sub-analyzers
 * such as the Heading Analyzer.
 *
 * @package Scalyn\QA\Analyzers
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Analyzers;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Models\Check_Item;

/**
 * Class Content_Analyzer
 *
 * Orchestrates content-level analysis by delegating to specialized sub-analyzers.
 *
 * @since 1.0.0
 */
class Content_Analyzer implements Analyzer_Interface {

	/**
	 * The heading analyzer instance.
	 *
	 * @var Heading_Analyzer
	 */
	private Heading_Analyzer $heading_analyzer;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->heading_analyzer = new Heading_Analyzer();
	}

	/**
	 * Get the unique identifier for this analyzer.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'content';
	}

	/**
	 * Get the human-readable label for this analyzer.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Content Analyzer', 'scalyn-qa-assistant' );
	}

	/**
	 * Get the category this analyzer belongs to.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'content';
	}

	/**
	 * Run all content checks on a post.
	 *
	 * Delegates to the Heading Analyzer and returns its results.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to analyze.
	 * @return Check_Item[]
	 */
	public function analyze( int $post_id ): array {
		return $this->heading_analyzer->analyze( $post_id );
	}
}
