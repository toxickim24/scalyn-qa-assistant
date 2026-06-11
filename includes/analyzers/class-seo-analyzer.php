<?php
/**
 * SEO Analyzer.
 *
 * Performs SEO-related checks on a post including meta title, description,
 * featured image, alt text, and link analysis.
 *
 * @package Scalyn\QA\Analyzers
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Analyzers;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Models\Check_Item;

/**
 * Class SEO_Analyzer
 *
 * Analyzes posts for SEO best practices including meta tags, images, and links.
 *
 * @since 1.0.0
 */
class SEO_Analyzer implements Analyzer_Interface {

	/**
	 * Get the unique identifier for this analyzer.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'seo';
	}

	/**
	 * Get the human-readable label for this analyzer.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'SEO Analyzer', 'scalyn-qa-assistant' );
	}

	/**
	 * Get the category this analyzer belongs to.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'seo';
	}

	/**
	 * Run all SEO checks on a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to analyze.
	 * @return Check_Item[]
	 */
	public function analyze( int $post_id ): array {
		$content = $this->get_rendered_content( $post_id );
		$parser  = new HTML_Parser( $content );

		$checks   = array();
		$checks[] = $this->check_meta_title( $post_id );
		$checks[] = $this->check_meta_description( $post_id );
		$checks[] = $this->check_featured_image( $post_id );
		$checks[] = $this->check_image_alt_text( $parser );
		$checks[] = $this->check_internal_links( $parser );
		$checks[] = $this->check_external_links( $parser );

		return $checks;
	}

	/**
	 * Check if a meta title is set.
	 *
	 * Checks Rank Math, Yoast, AIOSEO, and falls back to the post title.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to check.
	 * @return Check_Item
	 */
	private function check_meta_title( int $post_id ): Check_Item {
		$tooltip = __( 'A unique meta title helps search engines understand your page. Aim for 50-60 characters.', 'scalyn-qa-assistant' );
		$source  = '';
		$title   = '';

		// Rank Math.
		$rank_math_title = get_post_meta( $post_id, 'rank_math_title', true );
		if ( is_string( $rank_math_title ) && '' !== $rank_math_title ) {
			$title  = $rank_math_title;
			$source = 'Rank Math';
		}

		// Yoast.
		if ( '' === $title ) {
			$yoast_title = get_post_meta( $post_id, '_yoast_wpseo_title', true );
			if ( is_string( $yoast_title ) && '' !== $yoast_title ) {
				$title  = $yoast_title;
				$source = 'Yoast SEO';
			}
		}

		// AIOSEO.
		if ( '' === $title ) {
			$aioseo_title = get_post_meta( $post_id, '_aioseo_title', true );
			if ( is_string( $aioseo_title ) && '' !== $aioseo_title ) {
				$title  = $aioseo_title;
				$source = 'AIOSEO';
			}
		}

		// Fallback: post title.
		if ( '' === $title ) {
			$post_title = get_the_title( $post_id );
			if ( '' !== $post_title ) {
				$char_count = mb_strlen( $post_title );
				return new Check_Item(
					id:        'meta_title_exists',
					label:     __( 'Meta Title', 'scalyn-qa-assistant' ),
					status:    'warning',
					message:   sprintf(
						/* translators: %d: character count */
						__( 'Using the page title as meta title (%d characters). Consider setting a custom SEO title.', 'scalyn-qa-assistant' ),
						$char_count,
					),
					category:  'seo',
					severity:  'warning',
					quick_fix: 'generate_ai_meta',
					tooltip:   $tooltip,
					details:   array(
						'title'       => $post_title,
						'char_count'  => $char_count,
						'source'      => 'post_title',
					),
				);
			}

			return new Check_Item(
				id:        'meta_title_exists',
				label:     __( 'Meta Title', 'scalyn-qa-assistant' ),
				status:    'fail',
				message:   __( 'No meta title found. This page has no title set.', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'critical',
				quick_fix: 'generate_ai_meta',
				tooltip:   $tooltip,
			);
		}

		$char_count = mb_strlen( $title );

		return new Check_Item(
			id:        'meta_title_exists',
			label:     __( 'Meta Title', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   sprintf(
				/* translators: 1: source plugin name, 2: character count */
				__( 'Meta title set via %1$s (%2$d characters).', 'scalyn-qa-assistant' ),
				$source,
				$char_count,
			),
			category:  'seo',
			severity:  'info',
			quick_fix: 'generate_ai_meta',
			tooltip:   $tooltip,
			details:   array(
				'title'      => $title,
				'char_count' => $char_count,
				'source'     => $source,
			),
		);
	}

	/**
	 * Check if a meta description is set.
	 *
	 * Checks Rank Math, Yoast, AIOSEO in order.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to check.
	 * @return Check_Item
	 */
	private function check_meta_description( int $post_id ): Check_Item {
		$tooltip     = __( 'A meta description summarizes your page for search results. Aim for 120-160 characters.', 'scalyn-qa-assistant' );
		$source      = '';
		$description = '';

		// Rank Math.
		$rank_math_desc = get_post_meta( $post_id, 'rank_math_description', true );
		if ( is_string( $rank_math_desc ) && '' !== $rank_math_desc ) {
			$description = $rank_math_desc;
			$source      = 'Rank Math';
		}

		// Yoast.
		if ( '' === $description ) {
			$yoast_desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
			if ( is_string( $yoast_desc ) && '' !== $yoast_desc ) {
				$description = $yoast_desc;
				$source      = 'Yoast SEO';
			}
		}

		// AIOSEO.
		if ( '' === $description ) {
			$aioseo_desc = get_post_meta( $post_id, '_aioseo_description', true );
			if ( is_string( $aioseo_desc ) && '' !== $aioseo_desc ) {
				$description = $aioseo_desc;
				$source      = 'AIOSEO';
			}
		}

		if ( '' === $description ) {
			return new Check_Item(
				id:        'meta_description_exists',
				label:     __( 'Meta Description', 'scalyn-qa-assistant' ),
				status:    'fail',
				message:   __( 'No meta description found. Add a description to improve search result appearance.', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'critical',
				quick_fix: 'generate_ai_meta',
				tooltip:   $tooltip,
			);
		}

		$char_count = mb_strlen( $description );

		return new Check_Item(
			id:        'meta_description_exists',
			label:     __( 'Meta Description', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   sprintf(
				/* translators: 1: source plugin name, 2: character count */
				__( 'Meta description set via %1$s (%2$d characters).', 'scalyn-qa-assistant' ),
				$source,
				$char_count,
			),
			category:  'seo',
			severity:  'info',
			quick_fix: 'generate_ai_meta',
			tooltip:   $tooltip,
			details:   array(
				'description' => $description,
				'char_count'  => $char_count,
				'source'      => $source,
			),
		);
	}

	/**
	 * Check if a featured image is set.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to check.
	 * @return Check_Item
	 */
	private function check_featured_image( int $post_id ): Check_Item {
		$tooltip = __( 'Featured images improve social sharing and visual search results.', 'scalyn-qa-assistant' );

		if ( has_post_thumbnail( $post_id ) ) {
			return new Check_Item(
				id:        'featured_image_exists',
				label:     __( 'Featured Image', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'Featured image is set.', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'info',
				quick_fix: 'upload_featured_image',
				tooltip:   $tooltip,
			);
		}

		return new Check_Item(
			id:        'featured_image_exists',
			label:     __( 'Featured Image', 'scalyn-qa-assistant' ),
			status:    'fail',
			message:   __( 'No featured image set. Add one to improve social sharing and visual appeal.', 'scalyn-qa-assistant' ),
			category:  'seo',
			severity:  'critical',
			quick_fix: 'upload_featured_image',
			tooltip:   $tooltip,
		);
	}

	/**
	 * Check that all images have alt text.
	 *
	 * @since 1.0.0
	 *
	 * @param HTML_Parser $parser The HTML parser instance.
	 * @return Check_Item
	 */
	private function check_image_alt_text( HTML_Parser $parser ): Check_Item {
		$tooltip = __( 'Alt text improves accessibility and helps search engines understand your images.', 'scalyn-qa-assistant' );
		$images  = $parser->get_images();

		if ( 0 === count( $images ) ) {
			return new Check_Item(
				id:        'image_alt_text',
				label:     __( 'Image Alt Text', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'No images found in the content.', 'scalyn-qa-assistant' ),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		$total_images = count( $images );
		$missing_alt  = array();

		foreach ( $images as $image ) {
			if ( ! $image['has_alt'] ) {
				$src = $image['src'];

				if ( mb_strlen( $src ) > 80 ) {
					$src = mb_substr( $src, 0, 80 ) . '...';
				}

				$missing_alt[] = $src;
			}
		}

		$missing_count = count( $missing_alt );

		if ( 0 === $missing_count ) {
			return new Check_Item(
				id:        'image_alt_text',
				label:     __( 'Image Alt Text', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					/* translators: %d: total image count */
					__( 'All %d images have alt text.', 'scalyn-qa-assistant' ),
					$total_images,
				),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
			);
		}

		if ( $missing_count === $total_images ) {
			return new Check_Item(
				id:        'image_alt_text',
				label:     __( 'Image Alt Text', 'scalyn-qa-assistant' ),
				status:    'fail',
				message:   sprintf(
					/* translators: %d: number of images */
					__( 'None of the %d images have alt text.', 'scalyn-qa-assistant' ),
					$total_images,
				),
				category:  'seo',
				severity:  'warning',
				quick_fix: null,
				tooltip:   $tooltip,
				details:   array( 'missing_alt_images' => $missing_alt ),
			);
		}

		return new Check_Item(
			id:        'image_alt_text',
			label:     __( 'Image Alt Text', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   sprintf(
				/* translators: 1: missing count, 2: total count */
				__( '%1$d of %2$d images are missing alt text.', 'scalyn-qa-assistant' ),
				$missing_count,
				$total_images,
			),
			category:  'seo',
			severity:  'warning',
			quick_fix: null,
			tooltip:   $tooltip,
			details:   array( 'missing_alt_images' => $missing_alt ),
		);
	}

	/**
	 * Check for internal links in the content.
	 *
	 * @since 1.0.0
	 *
	 * @param HTML_Parser $parser The HTML parser instance.
	 * @return Check_Item
	 */
	private function check_internal_links( HTML_Parser $parser ): Check_Item {
		$tooltip = __( 'Internal links help search engines discover and rank your other pages.', 'scalyn-qa-assistant' );
		$links   = $this->categorize_links( $parser );

		$internal_count = count( $links['internal'] );

		if ( $internal_count > 0 ) {
			return new Check_Item(
				id:        'internal_links_present',
				label:     __( 'Internal Links', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					/* translators: %d: number of internal links */
					_n(
						'%d internal link found.',
						'%d internal links found.',
						$internal_count,
						'scalyn-qa-assistant',
					),
					$internal_count,
				),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
				details:   array( 'internal_links' => $links['internal'] ),
			);
		}

		return new Check_Item(
			id:        'internal_links_present',
			label:     __( 'Internal Links', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   __( 'No internal links found. Consider linking to related content on your site.', 'scalyn-qa-assistant' ),
			category:  'seo',
			severity:  'warning',
			quick_fix: null,
			tooltip:   $tooltip,
		);
	}

	/**
	 * Check for external links in the content.
	 *
	 * @since 1.0.0
	 *
	 * @param HTML_Parser $parser The HTML parser instance.
	 * @return Check_Item
	 */
	private function check_external_links( HTML_Parser $parser ): Check_Item {
		$tooltip = __( 'External links to authoritative sources can enhance content credibility.', 'scalyn-qa-assistant' );
		$links   = $this->categorize_links( $parser );

		$external_count = count( $links['external'] );

		if ( $external_count > 0 ) {
			return new Check_Item(
				id:        'external_links_present',
				label:     __( 'External Links', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					/* translators: %d: number of external links */
					_n(
						'%d external link found.',
						'%d external links found.',
						$external_count,
						'scalyn-qa-assistant',
					),
					$external_count,
				),
				category:  'seo',
				severity:  'info',
				quick_fix: null,
				tooltip:   $tooltip,
				details:   array( 'external_links' => $links['external'] ),
			);
		}

		return new Check_Item(
			id:        'external_links_present',
			label:     __( 'External Links', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   __( 'No external links found. This is fine but linking to authoritative sources may help.', 'scalyn-qa-assistant' ),
			category:  'seo',
			severity:  'info',
			quick_fix: null,
			tooltip:   $tooltip,
		);
	}

	/**
	 * Categorize all links as internal or external using HTML_Parser.
	 *
	 * Results are cached per request to avoid re-parsing.
	 *
	 * @since 1.0.0
	 *
	 * @param HTML_Parser $parser The HTML parser instance.
	 * @return array{internal: string[], external: string[]}
	 */
	private function categorize_links( HTML_Parser $parser ): array {
		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		$internal   = array();
		$external   = array();
		$all_links  = $parser->get_links();

		if ( 0 === count( $all_links ) ) {
			$cache = array(
				'internal' => $internal,
				'external' => $external,
			);
			return $cache;
		}

		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );

		foreach ( $all_links as $link ) {
			$url = trim( $link['url'] );

			// Skip non-HTTP links.
			if ( str_starts_with( $url, 'mailto:' ) || str_starts_with( $url, 'tel:' ) || str_starts_with( $url, '#' ) || str_starts_with( $url, 'javascript:' ) ) {
				continue;
			}

			$parsed_host = wp_parse_url( $url, PHP_URL_HOST );

			// Relative URLs are internal.
			if ( null === $parsed_host || false === $parsed_host ) {
				$internal[] = $url;
				continue;
			}

			if ( is_string( $site_host ) && strcasecmp( $parsed_host, $site_host ) === 0 ) {
				$internal[] = $url;
			} else {
				$external[] = $url;
			}
		}

		$cache = array(
			'internal' => $internal,
			'external' => $external,
		);

		return $cache;
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
