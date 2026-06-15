<?php
/**
 * Content Analyzer.
 *
 * Performs content quality checks including heading structure,
 * content length, capitalization, punctuation, and paragraph quality.
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
 * Orchestrates content-level analysis by delegating to specialized sub-analyzers
 * and running its own text quality checks.
 *
 * @since 1.0.0
 */
class Content_Analyzer implements Analyzer_Interface {

	/**
	 * Minimum recommended word count.
	 *
	 * @var int
	 */
	private const MIN_WORD_COUNT = 300;

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
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to analyze.
	 * @return Check_Item[]
	 */
	public function analyze( int $post_id ): array {
		$checks = $this->heading_analyzer->analyze( $post_id );

		$content    = $this->get_rendered_content( $post_id );
		$plain_text = wp_strip_all_tags( $content );

		$checks[] = $this->check_content_length( $plain_text );
		$checks[] = $this->check_heading_capitalization( $content );
		$checks[] = $this->check_paragraph_punctuation( $content );
		$checks[] = $this->check_short_paragraphs( $content );

		return $checks;
	}

	/**
	 * Check if content meets minimum word count.
	 */
	private function check_content_length( string $plain_text ): Check_Item {
		$words      = preg_split( '/\s+/', trim( $plain_text ), -1, PREG_SPLIT_NO_EMPTY );
		$word_count = count( $words );
		$pass       = $word_count >= self::MIN_WORD_COUNT;

		return new Check_Item(
			id:        'content_length',
			label:     __( 'Content Length', 'scalyn-qa-assistant' ),
			status:    $pass ? 'pass' : 'warning',
			message:   $pass
				? sprintf(
					__( 'Content has %d words.', 'scalyn-qa-assistant' ),
					$word_count,
				)
				: sprintf(
					__( 'Content has only %d words. Aim for at least %d words for better SEO.', 'scalyn-qa-assistant' ),
					$word_count,
					self::MIN_WORD_COUNT,
				),
			category:  'content',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'Pages with more content tend to rank better. 300+ words is a good baseline.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check that headings use proper capitalization (not all lowercase).
	 */
	private function check_heading_capitalization( string $content ): Check_Item {
		$parser   = new HTML_Parser( $content );
		$headings = $parser->get_headings();

		if ( empty( $headings ) ) {
			return new Check_Item(
				id:        'heading_capitalization',
				label:     __( 'Heading Capitalization', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No headings to check.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'info',
				quick_fix: null,
				tooltip:   __( 'Headings should use title case or sentence case, not all lowercase.', 'scalyn-qa-assistant' ),
			);
		}

		$bad_headings = array();
		foreach ( $headings as $heading ) {
			$text = trim( $heading['text'] ?? '' );
			if ( '' === $text ) {
				continue;
			}
			// Flag if the first character is lowercase (not sentence/title case).
			$first_char = mb_substr( $text, 0, 1 );
			if ( $first_char !== mb_strtoupper( $first_char ) && preg_match( '/[a-z]/u', $first_char ) ) {
				$tag = strtoupper( $heading['tag'] ?? 'H?' );
				$bad_headings[] = $tag . ': "' . ( mb_strlen( $text ) > 50 ? mb_substr( $text, 0, 50 ) . '...' : $text ) . '"';
			}
		}

		$pass = empty( $bad_headings );

		return new Check_Item(
			id:        'heading_capitalization',
			label:     __( 'Heading Capitalization', 'scalyn-qa-assistant' ),
			status:    $pass ? 'pass' : 'warning',
			message:   $pass
				? __( 'All headings are properly capitalized.', 'scalyn-qa-assistant' )
				: sprintf(
					__( '%d heading(s) start with a lowercase letter: %s', 'scalyn-qa-assistant' ),
					count( $bad_headings ),
					implode( '; ', array_slice( $bad_headings, 0, 3 ) ),
				),
			category:  'content',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'Headings should use title case or sentence case for a professional appearance.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check that paragraphs end with proper punctuation.
	 */
	private function check_paragraph_punctuation( string $content ): Check_Item {
		// Extract paragraph text from <p> tags.
		if ( ! preg_match_all( '/<p[^>]*>(.*?)<\/p>/si', $content, $matches ) ) {
			return new Check_Item(
				id:        'paragraph_punctuation',
				label:     __( 'Paragraph Punctuation', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No paragraphs to check.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'info',
				quick_fix: null,
				tooltip:   __( 'Paragraphs should end with proper punctuation (period, question mark, exclamation mark, or colon).', 'scalyn-qa-assistant' ),
			);
		}

		$bad_paragraphs = array();
		foreach ( $matches[1] as $paragraph_html ) {
			$text = trim( wp_strip_all_tags( $paragraph_html ) );

			// Skip empty, very short (likely a label/button), or paragraphs that are just a link/image.
			if ( mb_strlen( $text ) < 20 ) {
				continue;
			}

			$last_char = mb_substr( $text, -1 );

			// Valid ending punctuation.
			if ( ! in_array( $last_char, array( '.', '!', '?', ':', ';', '"', "'", "\u{201D}", ')' ), true ) ) {
				$preview          = mb_strlen( $text ) > 60 ? mb_substr( $text, 0, 60 ) . '...' : $text;
				$bad_paragraphs[] = '"' . $preview . '"';
			}
		}

		$pass = empty( $bad_paragraphs );

		return new Check_Item(
			id:        'paragraph_punctuation',
			label:     __( 'Paragraph Punctuation', 'scalyn-qa-assistant' ),
			status:    $pass ? 'pass' : 'warning',
			message:   $pass
				? __( 'All paragraphs end with proper punctuation.', 'scalyn-qa-assistant' )
				: sprintf(
					__( '%d paragraph(s) missing ending punctuation: %s', 'scalyn-qa-assistant' ),
					count( $bad_paragraphs ),
					implode( '; ', array_slice( $bad_paragraphs, 0, 2 ) ),
				),
			category:  'content',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'Paragraphs should end with a period, question mark, exclamation mark, or other closing punctuation.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check for consecutive very short paragraphs (1 sentence each).
	 */
	private function check_short_paragraphs( string $content ): Check_Item {
		if ( ! preg_match_all( '/<p[^>]*>(.*?)<\/p>/si', $content, $matches ) ) {
			return new Check_Item(
				id:        'short_paragraphs',
				label:     __( 'Paragraph Quality', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No paragraphs to check.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'info',
				quick_fix: null,
				tooltip:   __( 'Avoid many consecutive single-sentence paragraphs. Group related sentences together.', 'scalyn-qa-assistant' ),
			);
		}

		$consecutive    = 0;
		$max_streak     = 0;
		$total_short    = 0;

		foreach ( $matches[1] as $paragraph_html ) {
			$text = trim( wp_strip_all_tags( $paragraph_html ) );

			if ( mb_strlen( $text ) < 10 ) {
				continue; // Skip empty or trivial content.
			}

			// Count sentences (split by . ! ?).
			$sentences = preg_split( '/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
			$sentence_count = count( array_filter( $sentences, fn( $s ) => mb_strlen( trim( $s ) ) > 5 ) );

			if ( $sentence_count <= 1 && mb_strlen( $text ) < 80 ) {
				++$consecutive;
				++$total_short;
				$max_streak = max( $max_streak, $consecutive );
			} else {
				$consecutive = 0;
			}
		}

		$pass = $max_streak < 4;

		return new Check_Item(
			id:        'short_paragraphs',
			label:     __( 'Paragraph Quality', 'scalyn-qa-assistant' ),
			status:    $pass ? 'pass' : 'warning',
			message:   $pass
				? __( 'Paragraph structure looks good.', 'scalyn-qa-assistant' )
				: sprintf(
					__( 'Found %d consecutive single-sentence paragraphs. Consider combining related sentences for better readability.', 'scalyn-qa-assistant' ),
					$max_streak,
				),
			category:  'content',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'Multiple consecutive short paragraphs can make content feel choppy. Group related ideas together.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Get the rendered content for a post.
	 *
	 * @param int $post_id The post ID.
	 * @return string The rendered HTML content.
	 */
	private function get_rendered_content( int $post_id ): string {
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

		return (string) apply_filters( 'the_content', $raw_content );
	}
}
