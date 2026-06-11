<?php
/**
 * Heading Analyzer.
 *
 * Analyzes heading structure in post content for proper hierarchy,
 * correct H1 usage, and empty headings.
 *
 * @package Scalyn\QA\Analyzers
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Analyzers;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Models\Check_Item;

/**
 * Class Heading_Analyzer
 *
 * Checks heading tags for proper structure and accessibility.
 *
 * @since 1.0.0
 */
class Heading_Analyzer implements Analyzer_Interface {

	/**
	 * Get the unique identifier for this analyzer.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'headings';
	}

	/**
	 * Get the human-readable label for this analyzer.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Heading Analyzer', 'scalyn-qa-assistant' );
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
	 * Run all heading checks on a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to analyze.
	 * @return Check_Item[]
	 */
	public function analyze( int $post_id ): array {
		$content  = $this->get_rendered_content( $post_id );
		$headings = $this->extract_headings( $content );

		$checks   = array();
		$checks[] = $this->check_h1_exists( $headings );
		$checks[] = $this->check_heading_hierarchy( $headings );
		$checks[] = $this->check_empty_headings( $headings );

		return $checks;
	}

	/**
	 * Extract all headings from HTML content.
	 *
	 * Uses DOMDocument via HTML_Parser for reliable parsing instead of regex.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The HTML content to parse.
	 * @return array<int, array{tag: string, text: string, level: int}>
	 */
	public function extract_headings( string $content ): array {
		$parser       = new HTML_Parser( $content );
		$raw_headings = $parser->get_headings();
		$headings     = array();

		foreach ( $raw_headings as $heading ) {
			$headings[] = array(
				'tag'   => $heading['tag'],
				'text'  => $heading['text'],
				'level' => $heading['level'],
			);
		}

		return $headings;
	}

	/**
	 * Check that exactly one H1 tag exists.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array{tag: string, text: string, level: int}> $headings Extracted headings.
	 * @return Check_Item
	 */
	private function check_h1_exists( array $headings ): Check_Item {
		$tooltip  = __( 'Every page should have exactly one H1 tag as the main heading.', 'scalyn-qa-assistant' );
		$h1_count = 0;

		foreach ( $headings as $heading ) {
			if ( 1 === $heading['level'] ) {
				++$h1_count;
			}
		}

		if ( 1 === $h1_count ) {
			return new Check_Item(
				id:        'h1_exists',
				label:     __( 'H1 Heading', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'Page has exactly one H1 heading.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'info',
				quick_fix: 'jump_to_heading',
				tooltip:   $tooltip,
			);
		}

		if ( 0 === $h1_count ) {
			return new Check_Item(
				id:        'h1_exists',
				label:     __( 'H1 Heading', 'scalyn-qa-assistant' ),
				status:    'fail',
				message:   __( 'No H1 heading found. Every page should have exactly one H1.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'critical',
				quick_fix: 'jump_to_heading',
				tooltip:   $tooltip,
			);
		}

		return new Check_Item(
			id:        'h1_exists',
			label:     __( 'H1 Heading', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   sprintf(
				/* translators: %d: number of H1 tags found */
				__( 'Multiple H1 headings found (%d). Use only one H1 per page.', 'scalyn-qa-assistant' ),
				$h1_count,
			),
			category:  'content',
			severity:  'warning',
			quick_fix: 'jump_to_heading',
			tooltip:   $tooltip,
			details:   array( 'h1_count' => $h1_count ),
		);
	}

	/**
	 * Check that headings follow a proper hierarchy without skipping levels.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array{tag: string, text: string, level: int}> $headings Extracted headings.
	 * @return Check_Item
	 */
	private function check_heading_hierarchy( array $headings ): Check_Item {
		$tooltip = __( 'Headings should follow a logical hierarchy (H1->H2->H3) without skipping levels.', 'scalyn-qa-assistant' );

		if ( count( $headings ) === 0 ) {
			return new Check_Item(
				id:        'heading_hierarchy',
				label:     __( 'Heading Hierarchy', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No headings found to check hierarchy.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'info',
				quick_fix: 'jump_to_heading',
				tooltip:   $tooltip,
			);
		}

		$skipped_levels = array();
		$previous_level = 0;

		foreach ( $headings as $heading ) {
			$current_level = $heading['level'];

			if ( $previous_level > 0 && $current_level > $previous_level + 1 ) {
				$skip_description = sprintf(
					/* translators: 1: previous heading tag, 2: current heading tag */
					__( 'H%1$d to H%2$d', 'scalyn-qa-assistant' ),
					$previous_level,
					$current_level,
				);
				$skipped_levels[] = $skip_description;
			}

			$previous_level = $current_level;
		}

		if ( count( $skipped_levels ) === 0 ) {
			return new Check_Item(
				id:        'heading_hierarchy',
				label:     __( 'Heading Hierarchy', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'Heading hierarchy is correct. No levels are skipped.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'info',
				quick_fix: 'jump_to_heading',
				tooltip:   $tooltip,
			);
		}

		return new Check_Item(
			id:        'heading_hierarchy',
			label:     __( 'Heading Hierarchy', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   sprintf(
				/* translators: %s: list of skipped heading levels */
				__( 'Heading levels are skipped: %s. Maintain a logical order.', 'scalyn-qa-assistant' ),
				implode( ', ', $skipped_levels ),
			),
			category:  'content',
			severity:  'warning',
			quick_fix: 'jump_to_heading',
			tooltip:   $tooltip,
			details:   array( 'skipped_levels' => $skipped_levels ),
		);
	}

	/**
	 * Check for headings with empty text content.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array{tag: string, text: string, level: int}> $headings Extracted headings.
	 * @return Check_Item
	 */
	private function check_empty_headings( array $headings ): Check_Item {
		$tooltip       = __( 'Empty headings create poor document structure and confuse screen readers.', 'scalyn-qa-assistant' );
		$empty_tags    = array();

		foreach ( $headings as $heading ) {
			if ( '' === $heading['text'] ) {
				$empty_tags[] = strtoupper( $heading['tag'] );
			}
		}

		$empty_count = count( $empty_tags );

		if ( 0 === $empty_count ) {
			return new Check_Item(
				id:        'empty_headings',
				label:     __( 'Empty Headings', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No empty headings found.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'info',
				quick_fix: 'jump_to_heading',
				tooltip:   $tooltip,
			);
		}

		return new Check_Item(
			id:        'empty_headings',
			label:     __( 'Empty Headings', 'scalyn-qa-assistant' ),
			status:    'fail',
			message:   sprintf(
				/* translators: 1: count of empty headings, 2: list of heading levels */
				__( '%1$d empty heading(s) found: %2$s. Add meaningful text to all headings.', 'scalyn-qa-assistant' ),
				$empty_count,
				implode( ', ', $empty_tags ),
			),
			category:  'content',
			severity:  'critical',
			quick_fix: 'jump_to_heading',
			tooltip:   $tooltip,
			details:   array(
				'empty_count' => $empty_count,
				'empty_tags'  => $empty_tags,
			),
		);
	}

	/**
	 * Get the rendered content for a post.
	 *
	 * Supports Elementor page builder content when available.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID.
	 * @return string The rendered HTML content.
	 */
	private function get_rendered_content( int $post_id ): string {
		// Check for Elementor-built content.
		if ( class_exists( '\Elementor\Plugin' ) ) {
			$elementor = \Elementor\Plugin::$instance;
			if ( $elementor && method_exists( $elementor->db, 'is_built_with_elementor' ) && $elementor->db->is_built_with_elementor( $post_id ) ) {
				$elementor_content = $elementor->frontend->get_builder_content( $post_id, true );
				if ( '' !== $elementor_content ) {
					return $elementor_content;
				}
			}
		}

		$raw_content = get_post_field( 'post_content', $post_id );

		if ( is_wp_error( $raw_content ) || ! is_string( $raw_content ) ) {
			return '';
		}

		/** This filter is documented in wp-includes/post-template.php */
		return (string) apply_filters( 'the_content', $raw_content );
	}
}
