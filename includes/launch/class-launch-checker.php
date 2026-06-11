<?php
/**
 * Launch Checker.
 *
 * Runs pre-launch QA checks to verify site readiness.
 *
 * @package Scalyn\QA\Launch
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Launch;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Integrations\SEO_Integration;
use Scalyn\QA\Models\Check_Item;

/**
 * Class Launch_Checker
 *
 * Performs a suite of pre-launch checks and stores the results.
 *
 * @since 1.0.0
 */
class Launch_Checker {

	/**
	 * Option key for storing launch check results.
	 *
	 * @var string
	 */
	private const RESULTS_OPTION = 'scalyn_qa_launch_results';

	/**
	 * Option key for storing the last scan timestamp.
	 *
	 * @var string
	 */
	private const LAST_SCAN_OPTION = 'scalyn_qa_launch_last_scan';

	/**
	 * Run all launch checks.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item[] Array of check results.
	 */
	/**
	 * Get launch checklist settings (thresholds + enabled checks).
	 */
	private function get_launch_settings(): array {
		$settings = get_option( 'scalyn_qa_launch_settings', array() );
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Get a PHP threshold value from settings.
	 */
	private function get_threshold( string $key, int $default ): int {
		$settings   = $this->get_launch_settings();
		$thresholds = $settings['thresholds'] ?? array();
		return (int) ( $thresholds[ $key ] ?? $default );
	}

	/**
	 * Check if a specific check is enabled in launch settings.
	 */
	private function is_check_enabled( string $check_id ): bool {
		$settings = $this->get_launch_settings();
		$enabled  = $settings['enabled_checks'] ?? null;

		// If no settings saved yet, all checks are enabled.
		if ( null === $enabled || ! is_array( $enabled ) ) {
			return true;
		}

		return in_array( $check_id, $enabled, true );
	}

	public function run_checks(): array {
		$all_checks = array(
			'search_engine_visibility' => $this->check_search_engine_visibility(),
			'seo_plugin_installed'     => $this->check_seo_plugin_installed(),
			'sitemap_exists'           => $this->check_sitemap_exists(),
			'llms_txt'                 => $this->check_llms_txt(),
			'ga4_configured'           => $this->check_ga4_configured(),
			'gtm_configured'           => $this->check_gtm_configured(),
			'ssl_enabled'              => $this->check_ssl_enabled(),
			'favicon_exists'           => $this->check_favicon_exists(),
			'contact_page_exists'      => $this->check_contact_page_exists(),
			'privacy_policy_exists'    => $this->check_privacy_policy_exists(),
			'plugin_conflicts'         => $this->check_plugin_conflicts(),
			'php_memory_limit'         => $this->check_php_memory_limit(),
			'php_max_execution_time'   => $this->check_php_max_execution_time(),
			'php_max_input_time'       => $this->check_php_max_input_time(),
			'php_post_max_size'        => $this->check_php_post_max_size(),
			'php_upload_max_size'      => $this->check_php_upload_max_size(),
			'security_plugin'          => $this->check_security_plugin(),
			'cache_plugin'             => $this->check_cache_plugin(),
		);

		// Filter to only enabled checks.
		$checks = array();
		foreach ( $all_checks as $check_id => $check_item ) {
			if ( $this->is_check_enabled( $check_id ) ) {
				$checks[] = $check_item;
			}
		}

		// Serialize results for storage.
		$serialized = array_map(
			static fn( Check_Item $item ): array => $item->to_array(),
			$checks,
		);

		update_option( self::RESULTS_OPTION, $serialized, false );
		update_option( self::LAST_SCAN_OPTION, time(), false );

		return $checks;
	}

	/**
	 * Check if an SEO plugin is installed and active.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item
	 */
	/**
	 * Check if search engines are discouraged from indexing the site.
	 *
	 * WordPress Settings → Reading → "Discourage search engines from indexing this site"
	 *
	 * @since 1.0.6
	 *
	 * @return Check_Item
	 */
	private function check_search_engine_visibility(): Check_Item {
		$discouraged = '0' !== get_option( 'blog_public', '1' );

		if ( $discouraged ) {
			return new Check_Item(
				id:        'search_engine_visibility',
				label:     __( 'Search Engine Visibility', 'scalyn-qa-assistant' ),
				status:    'fail',
				message:   __( 'Search engines are discouraged from indexing this site. Go to Settings → Reading to fix this before launch.', 'scalyn-qa-assistant' ),
				category:  'functionality',
				severity:  'critical',
				quick_fix: null,
				tooltip:   __( 'WordPress has a setting that tells search engines not to index your site. This must be disabled before launching or your site will not appear in Google.', 'scalyn-qa-assistant' ),
			);
		}

		return new Check_Item(
			id:        'search_engine_visibility',
			label:     __( 'Search Engine Visibility', 'scalyn-qa-assistant' ),
			status:    'pass',
			message:   __( 'Search engines are allowed to index this site.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'critical',
			quick_fix: null,
			tooltip:   __( 'WordPress is configured to allow search engine indexing. This is correct for a live site.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if llms.txt exists at the site root.
	 *
	 * llms.txt is a proposed standard for providing LLM-friendly content,
	 * similar to robots.txt for search engines.
	 *
	 * @since 1.0.6
	 *
	 * @return Check_Item
	 */
	private function check_llms_txt(): Check_Item {
		$url      = home_url( '/llms.txt' );
		$response = wp_remote_head( $url, array( 'timeout' => 5, 'sslverify' => true ) );

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			return new Check_Item(
				id:        'llms_txt',
				label:     __( 'llms.txt', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   __( 'llms.txt file found. LLMs can discover your site content.', 'scalyn-qa-assistant' ),
				category:  'functionality',
				severity:  'info',
				quick_fix: null,
				tooltip:   __( 'llms.txt is a standard that helps AI language models understand your site content, similar to how robots.txt helps search engines.', 'scalyn-qa-assistant' ),
			);
		}

		return new Check_Item(
			id:        'llms_txt',
			label:     __( 'llms.txt', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   __( 'No llms.txt found. Consider adding one to help AI models discover your content.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'info',
			quick_fix: null,
			tooltip:   __( 'llms.txt is a proposed standard (llmstxt.org) that provides a markdown file at your site root to help LLMs understand your site. It is optional but recommended for AI discoverability.', 'scalyn-qa-assistant' ),
		);
	}

	private function check_seo_plugin_installed(): Check_Item {
		$integration = SEO_Integration::detect();

		if ( null !== $integration ) {
			return new Check_Item(
				id:        'seo_plugin_installed',
				label:     'SEO Plugin Installed',
				status:    'pass',
				message:   sprintf(
					/* translators: %s: SEO plugin name. */
					__( '%s is active and configured.', 'scalyn-qa-assistant' ),
					$integration->get_plugin_name(),
				),
				category:  'seo',
				severity:  'critical',
				quick_fix: null,
				tooltip:   __( 'An SEO plugin is essential for managing meta tags, sitemaps, and structured data.', 'scalyn-qa-assistant' ),
				details:   array(
					'plugin_name' => $integration->get_plugin_name(),
					'plugin_slug' => $integration->get_plugin_slug(),
				),
			);
		}

		return new Check_Item(
			id:        'seo_plugin_installed',
			label:     'SEO Plugin Installed',
			status:    'fail',
			message:   __( 'No SEO plugin detected. Install Rank Math, Yoast SEO, or All in One SEO.', 'scalyn-qa-assistant' ),
			category:  'seo',
			severity:  'critical',
			quick_fix: 'install_seo_plugin',
			tooltip:   __( 'An SEO plugin is essential for managing meta tags, sitemaps, and structured data.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if an XML sitemap is accessible.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item
	 */
	private function check_sitemap_exists(): Check_Item {
		$site_url      = home_url();
		$sitemap_paths = array(
			'/sitemap_index.xml',
			'/sitemap.xml',
			'/wp-sitemap.xml',
		);

		foreach ( $sitemap_paths as $path ) {
			$url      = $site_url . $path;
			$response = wp_remote_head(
				$url,
				array(
					'timeout'   => 10,
					'sslverify' => false,
				),
			);

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				return new Check_Item(
					id:        'sitemap_exists',
					label:     'XML Sitemap',
					status:    'pass',
					message:   sprintf(
						/* translators: %s: Sitemap URL. */
						__( 'Sitemap found at %s.', 'scalyn-qa-assistant' ),
						esc_url( $url ),
					),
					category:  'seo',
					severity:  'warning',
					tooltip:   __( 'XML sitemaps help search engines discover and index your pages efficiently.', 'scalyn-qa-assistant' ),
					details:   array( 'sitemap_url' => $url ),
				);
			}
		}

		return new Check_Item(
			id:        'sitemap_exists',
			label:     'XML Sitemap',
			status:    'warning',
			message:   __( 'No XML sitemap found. Check your SEO plugin settings or add a sitemap.', 'scalyn-qa-assistant' ),
			category:  'seo',
			severity:  'warning',
			tooltip:   __( 'XML sitemaps help search engines discover and index your pages efficiently.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if Google Analytics 4 is configured.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item
	 */
	private function check_ga4_configured(): Check_Item {
		// Check common GA4 option keys in the database.
		$ga4_option_keys = array(
			'woocommerce_ga_id',
			'monsterinsights_site_profile',
			'exactmetrics_site_profile',
			'gadwp_options',
			'google_analytics_id',
			'ga_google_analytics_id',
		);

		foreach ( $ga4_option_keys as $option_key ) {
			$value = get_option( $option_key );
			if ( ! empty( $value ) ) {
				return new Check_Item(
					id:        'ga4_configured',
					label:     'Google Analytics',
					status:    'pass',
					message:   __( 'Google Analytics configuration detected.', 'scalyn-qa-assistant' ),
					category:  'functionality',
					severity:  'warning',
					tooltip:   __( 'Google Analytics tracks visitor behavior and helps measure website performance.', 'scalyn-qa-assistant' ),
					details:   array( 'detected_via' => 'option_' . $option_key ),
				);
			}
		}

		// Fallback: fetch homepage and search for GA4/GTM markers.
		$html = $this->fetch_homepage_html();

		if ( '' !== $html ) {
			$ga4_patterns = array( 'gtag(', 'G-', 'GTM-', 'googletagmanager' );

			foreach ( $ga4_patterns as $pattern ) {
				if ( false !== stripos( $html, $pattern ) ) {
					return new Check_Item(
						id:        'ga4_configured',
						label:     'Google Analytics',
						status:    'pass',
						message:   __( 'Google Analytics or Tag Manager code detected on the homepage.', 'scalyn-qa-assistant' ),
						category:  'functionality',
						severity:  'warning',
						tooltip:   __( 'Google Analytics tracks visitor behavior and helps measure website performance.', 'scalyn-qa-assistant' ),
						details:   array( 'detected_via' => 'html_pattern_' . $pattern ),
					);
				}
			}
		}

		return new Check_Item(
			id:        'ga4_configured',
			label:     'Google Analytics',
			status:    'warning',
			message:   __( 'Google Analytics not detected. Consider adding GA4 for visitor tracking.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'warning',
			tooltip:   __( 'Google Analytics tracks visitor behavior and helps measure website performance.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if Google Tag Manager is configured.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item
	 */
	private function check_gtm_configured(): Check_Item {
		// Check common GTM option keys.
		$gtm_option_keys = array(
			'gtm4wp-options',
			'google_tag_manager_id',
			'gtm_id',
		);

		foreach ( $gtm_option_keys as $option_key ) {
			$value = get_option( $option_key );
			if ( ! empty( $value ) ) {
				return new Check_Item(
					id:        'gtm_configured',
					label:     'Google Tag Manager',
					status:    'pass',
					message:   __( 'Google Tag Manager configuration detected.', 'scalyn-qa-assistant' ),
					category:  'functionality',
					severity:  'info',
					tooltip:   __( 'Google Tag Manager centralizes tracking code management and simplifies analytics setup.', 'scalyn-qa-assistant' ),
					details:   array( 'detected_via' => 'option_' . $option_key ),
				);
			}
		}

		// Fallback: check homepage HTML for GTM container code.
		$html = $this->fetch_homepage_html();

		if ( '' !== $html && false !== stripos( $html, 'GTM-' ) ) {
			return new Check_Item(
				id:        'gtm_configured',
				label:     'Google Tag Manager',
				status:    'pass',
				message:   __( 'Google Tag Manager container code detected on the homepage.', 'scalyn-qa-assistant' ),
				category:  'functionality',
				severity:  'info',
				tooltip:   __( 'Google Tag Manager centralizes tracking code management and simplifies analytics setup.', 'scalyn-qa-assistant' ),
				details:   array( 'detected_via' => 'html_pattern_GTM-' ),
			);
		}

		return new Check_Item(
			id:        'gtm_configured',
			label:     'Google Tag Manager',
			status:    'warning',
			message:   __( 'Google Tag Manager not detected. GTM is optional but recommended for tag management.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'info',
			tooltip:   __( 'Google Tag Manager centralizes tracking code management and simplifies analytics setup.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if SSL is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item
	 */
	private function check_ssl_enabled(): Check_Item {
		$is_ssl       = is_ssl();
		$site_url     = get_option( 'siteurl', '' );
		$url_is_https = is_string( $site_url ) && str_starts_with( $site_url, 'https://' );

		if ( $is_ssl && $url_is_https ) {
			return new Check_Item(
				id:        'ssl_enabled',
				label:     'SSL / HTTPS',
				status:    'pass',
				message:   __( 'SSL is enabled and the site URL uses HTTPS.', 'scalyn-qa-assistant' ),
				category:  'functionality',
				severity:  'critical',
				tooltip:   __( 'SSL encryption protects user data and is a Google ranking factor.', 'scalyn-qa-assistant' ),
			);
		}

		return new Check_Item(
			id:        'ssl_enabled',
			label:     'SSL / HTTPS',
			status:    'fail',
			message:   __( 'SSL is not fully configured. Ensure your site uses HTTPS.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'critical',
			tooltip:   __( 'SSL encryption protects user data and is a Google ranking factor.', 'scalyn-qa-assistant' ),
			details:   array(
				'is_ssl'       => $is_ssl,
				'url_is_https' => $url_is_https,
			),
		);
	}

	/**
	 * Check if a favicon / site icon is set.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item
	 */
	private function check_favicon_exists(): Check_Item {
		$icon_url = get_site_icon_url();

		if ( ! empty( $icon_url ) ) {
			return new Check_Item(
				id:        'favicon_exists',
				label:     'Favicon',
				status:    'pass',
				message:   __( 'Site icon (favicon) is configured.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'warning',
				tooltip:   __( 'A favicon improves brand recognition in browser tabs and bookmarks.', 'scalyn-qa-assistant' ),
				details:   array( 'icon_url' => $icon_url ),
			);
		}

		return new Check_Item(
			id:        'favicon_exists',
			label:     'Favicon',
			status:    'warning',
			message:   __( 'No site icon (favicon) found. Set one in Appearance > Customize > Site Identity.', 'scalyn-qa-assistant' ),
			category:  'content',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'A favicon improves brand recognition in browser tabs and bookmarks.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if a contact page exists.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item
	 */
	private function check_contact_page_exists(): Check_Item {
		global $wpdb;

		// Search for a published page with 'contact' in the slug or title.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$contact_page = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, post_title, post_name FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status = %s
				AND (post_name LIKE %s OR post_title LIKE %s)
				LIMIT 1",
				'page',
				'publish',
				'%contact%',
				'%contact%',
			),
		);

		if ( null !== $contact_page ) {
			return new Check_Item(
				id:        'contact_page_exists',
				label:     'Contact Page',
				status:    'pass',
				message:   sprintf(
					/* translators: %s: Page title. */
					__( 'Contact page found: "%s".', 'scalyn-qa-assistant' ),
					$contact_page->post_title,
				),
				category:  'content',
				severity:  'warning',
				tooltip:   __( 'A contact page builds trust and is expected by visitors and search engines.', 'scalyn-qa-assistant' ),
				details:   array(
					'page_id'    => (int) $contact_page->ID,
					'page_title' => $contact_page->post_title,
					'page_slug'  => $contact_page->post_name,
				),
			);
		}

		return new Check_Item(
			id:        'contact_page_exists',
			label:     'Contact Page',
			status:    'warning',
			message:   __( 'No contact page found. Consider creating one to improve visitor trust.', 'scalyn-qa-assistant' ),
			category:  'content',
			severity:  'warning',
			tooltip:   __( 'A contact page builds trust and is expected by visitors and search engines.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if a privacy policy page exists.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item
	 */
	private function check_privacy_policy_exists(): Check_Item {
		// First check the WordPress built-in privacy policy setting.
		$privacy_url = get_privacy_policy_url();

		if ( ! empty( $privacy_url ) ) {
			return new Check_Item(
				id:        'privacy_policy_exists',
				label:     'Privacy Policy',
				status:    'pass',
				message:   __( 'Privacy policy page is configured.', 'scalyn-qa-assistant' ),
				category:  'content',
				severity:  'warning',
				tooltip:   __( 'A privacy policy is legally required in many jurisdictions and builds user trust.', 'scalyn-qa-assistant' ),
				details:   array( 'privacy_url' => $privacy_url ),
			);
		}

		// Fallback: search for a published page with 'privacy' in the slug.
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$privacy_page = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, post_title, post_name FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status = %s
				AND post_name LIKE %s
				LIMIT 1",
				'page',
				'publish',
				'%privacy%',
			),
		);

		if ( null !== $privacy_page ) {
			return new Check_Item(
				id:        'privacy_policy_exists',
				label:     'Privacy Policy',
				status:    'pass',
				message:   sprintf(
					/* translators: %s: Page title. */
					__( 'Privacy policy page found: "%s".', 'scalyn-qa-assistant' ),
					$privacy_page->post_title,
				),
				category:  'content',
				severity:  'warning',
				tooltip:   __( 'A privacy policy is legally required in many jurisdictions and builds user trust.', 'scalyn-qa-assistant' ),
				details:   array(
					'page_id'    => (int) $privacy_page->ID,
					'page_title' => $privacy_page->post_title,
					'page_slug'  => $privacy_page->post_name,
				),
			);
		}

		return new Check_Item(
			id:        'privacy_policy_exists',
			label:     'Privacy Policy',
			status:    'warning',
			message:   __( 'No privacy policy page found. Create one under Settings > Privacy.', 'scalyn-qa-assistant' ),
			category:  'content',
			severity:  'warning',
			tooltip:   __( 'A privacy policy is legally required in many jurisdictions and builds user trust.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check for conflicting plugin combinations.
	 *
	 * @since 1.0.0
	 *
	 * @return Check_Item
	 */
	private function check_plugin_conflicts(): Check_Item {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$conflicts = array();

		// Check for multiple SEO plugins active simultaneously.
		$seo_plugins = array(
			'Rank Math'      => 'seo-by-rank-math/rank-math.php',
			'Yoast SEO'      => 'wordpress-seo/wp-seo.php',
			'All in One SEO' => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
		);

		$active_seo = array();
		foreach ( $seo_plugins as $name => $slug ) {
			if ( is_plugin_active( $slug ) ) {
				$active_seo[] = $name;
			}
		}

		if ( count( $active_seo ) > 1 ) {
			$conflicts[] = sprintf(
				/* translators: %s: Comma-separated list of active SEO plugin names. */
				__( 'Multiple SEO plugins active: %s. This can cause duplicate meta tags and conflicts.', 'scalyn-qa-assistant' ),
				implode( ', ', $active_seo ),
			);
		}

		// Check for multiple sitemap generators.
		$sitemap_plugins = array(
			'Google XML Sitemaps'  => 'google-sitemap-generator/sitemap.php',
			'XML Sitemap & Google News' => 'xml-sitemap-feed/xml-sitemap.php',
			'Jetpack Sitemaps'     => 'jetpack/jetpack.php',
		);

		$active_sitemap = array();
		foreach ( $sitemap_plugins as $name => $slug ) {
			if ( is_plugin_active( $slug ) ) {
				$active_sitemap[] = $name;
			}
		}

		// SEO plugins also generate sitemaps, so include active ones.
		$seo_with_sitemaps = array_merge( $active_seo, $active_sitemap );

		if ( count( $seo_with_sitemaps ) > 1 ) {
			$conflicts[] = sprintf(
				/* translators: %s: Comma-separated list of plugin names generating sitemaps. */
				__( 'Multiple plugins may generate sitemaps: %s. This can confuse search engines.', 'scalyn-qa-assistant' ),
				implode( ', ', $seo_with_sitemaps ),
			);
		}

		if ( ! empty( $conflicts ) ) {
			return new Check_Item(
				id:        'plugin_conflicts',
				label:     'Plugin Conflicts',
				status:    'warning',
				message:   implode( ' ', $conflicts ),
				category:  'functionality',
				severity:  'warning',
				tooltip:   __( 'Conflicting plugins can cause unexpected behavior, duplicate content, and SEO issues.', 'scalyn-qa-assistant' ),
				details:   array(
					'conflicts'          => $conflicts,
					'active_seo_plugins' => $active_seo,
				),
			);
		}

		return new Check_Item(
			id:        'plugin_conflicts',
			label:     'Plugin Conflicts',
			status:    'pass',
			message:   __( 'No known plugin conflicts detected.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'warning',
			tooltip:   __( 'Conflicting plugins can cause unexpected behavior, duplicate content, and SEO issues.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Fetch the homepage HTML.
	 *
	 * Caches the result within the request to avoid duplicate HTTP calls.
	 *
	 * @since 1.0.0
	 *
	 * @return string The homepage HTML body, or empty string on failure.
	 */
	private function fetch_homepage_html(): string {
		static $cached_html = null;

		if ( null !== $cached_html ) {
			return $cached_html;
		}

		$response = wp_remote_get(
			home_url( '/' ),
			array(
				'timeout'   => 15,
				'sslverify' => false,
			),
		);

		if ( is_wp_error( $response ) ) {
			$cached_html = '';
			return $cached_html;
		}

		$body = wp_remote_retrieve_body( $response );

		$cached_html = is_string( $body ) ? $body : '';

		return $cached_html;
	}

	/**
	 * Parse a PHP size string (e.g. '128M') to megabytes.
	 */
	private function parse_size_mb( string $size ): int {
		$size  = trim( $size );
		$value = (int) $size;
		$unit  = strtolower( substr( $size, -1 ) );

		return match ( $unit ) {
			'g' => $value * 1024,
			'm' => $value,
			'k' => (int) round( $value / 1024 ),
			default => $value,
		};
	}

	/**
	 * Check PHP memory limit (minimum 512MB).
	 */
	private function check_php_memory_limit(): Check_Item {
		$raw       = ini_get( 'memory_limit' );
		$mb        = $this->parse_size_mb( $raw ?: '0' );
		$threshold = $this->get_threshold( 'memory_limit', 512 );
		$pass      = $mb >= $threshold || -1 === (int) $raw;

		return new Check_Item(
			id:        'php_memory_limit',
			label:     __( 'PHP Memory Limit', 'scalyn-qa-assistant' ),
			status:    $pass ? 'pass' : 'warning',
			message:   $pass
				? sprintf( __( 'Memory limit is %s.', 'scalyn-qa-assistant' ), esc_html( $raw ) )
				: sprintf( __( 'Memory limit is %s. Recommended: %dM or higher.', 'scalyn-qa-assistant' ), esc_html( $raw ), $threshold ),
			category:  'functionality',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'WordPress recommends at least 256MB, but 512MB or more is ideal for plugins and page builders.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check PHP max execution time (minimum 90s).
	 */
	private function check_php_max_execution_time(): Check_Item {
		$value     = (int) ini_get( 'max_execution_time' );
		$threshold = $this->get_threshold( 'max_execution_time', 90 );
		$pass      = 0 === $value || $value >= $threshold;

		return new Check_Item(
			id:        'php_max_execution_time',
			label:     __( 'PHP Max Execution Time', 'scalyn-qa-assistant' ),
			status:    $pass ? 'pass' : 'warning',
			message:   $pass
				? sprintf( __( 'Max execution time is %ds.', 'scalyn-qa-assistant' ), $value )
				: sprintf( __( 'Max execution time is %ds. Recommended: %ds or higher.', 'scalyn-qa-assistant' ), $value, $threshold ),
			category:  'functionality',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'Scripts that run longer than this limit will be terminated. 90 seconds is recommended for complex operations.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check PHP max input time (minimum 90s).
	 */
	private function check_php_max_input_time(): Check_Item {
		$value     = (int) ini_get( 'max_input_time' );
		$threshold = $this->get_threshold( 'max_input_time', 90 );
		$pass      = -1 === $value || $value >= $threshold;

		return new Check_Item(
			id:        'php_max_input_time',
			label:     __( 'PHP Max Input Time', 'scalyn-qa-assistant' ),
			status:    $pass ? 'pass' : 'warning',
			message:   $pass
				? sprintf( __( 'Max input time is %ds.', 'scalyn-qa-assistant' ), $value )
				: sprintf( __( 'Max input time is %ds. Recommended: %ds or higher.', 'scalyn-qa-assistant' ), $value, $threshold ),
			category:  'functionality',
			severity:  'info',
			quick_fix: null,
			tooltip:   __( 'Maximum time PHP will spend parsing input data such as POST and file uploads.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check PHP post_max_size (minimum 128MB).
	 */
	private function check_php_post_max_size(): Check_Item {
		$raw       = ini_get( 'post_max_size' );
		$mb        = $this->parse_size_mb( $raw ?: '0' );
		$threshold = $this->get_threshold( 'post_max_size', 128 );
		$pass      = $mb >= $threshold;

		return new Check_Item(
			id:        'php_post_max_size',
			label:     __( 'PHP Post Max Size', 'scalyn-qa-assistant' ),
			status:    $pass ? 'pass' : 'warning',
			message:   $pass
				? sprintf( __( 'Post max size is %s.', 'scalyn-qa-assistant' ), esc_html( $raw ) )
				: sprintf( __( 'Post max size is %s. Recommended: %dM or higher.', 'scalyn-qa-assistant' ), esc_html( $raw ), $threshold ),
			category:  'functionality',
			severity:  'info',
			quick_fix: null,
			tooltip:   __( 'Maximum size of POST data that PHP will accept. Affects large form submissions and imports.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check PHP upload_max_filesize (minimum 64MB).
	 */
	private function check_php_upload_max_size(): Check_Item {
		$raw       = ini_get( 'upload_max_filesize' );
		$mb        = $this->parse_size_mb( $raw ?: '0' );
		$threshold = $this->get_threshold( 'upload_max_size', 64 );
		$pass      = $mb >= $threshold;

		return new Check_Item(
			id:        'php_upload_max_size',
			label:     __( 'PHP Upload Max Size', 'scalyn-qa-assistant' ),
			status:    $pass ? 'pass' : 'warning',
			message:   $pass
				? sprintf( __( 'Upload max size is %s.', 'scalyn-qa-assistant' ), esc_html( $raw ) )
				: sprintf( __( 'Upload max size is %s. Recommended: %dM or higher.', 'scalyn-qa-assistant' ), esc_html( $raw ), $threshold ),
			category:  'functionality',
			severity:  'info',
			quick_fix: null,
			tooltip:   __( 'Maximum file size for uploads. Should be at least 64MB for media-heavy sites.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if a security plugin is installed and active.
	 */
	private function check_security_plugin(): Check_Item {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$security_plugins = array(
			'wordfence/wordfence.php'                     => 'Wordfence',
			'sucuri-scanner/sucuri.php'                    => 'Sucuri',
			'better-wp-security/better-wp-security.php'   => 'Solid Security (iThemes)',
			'all-in-one-wp-security-and-firewall/wp-security.php' => 'All In One WP Security',
			'wp-simple-firewall/icwp-wpsf.php'            => 'Shield Security',
			'defender-security/wp-defender.php'            => 'Defender',
			'jetpack/jetpack.php'                         => 'Jetpack',
			'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' => 'Limit Login Attempts',
		);

		$found = array();
		foreach ( $security_plugins as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$found[] = $name;
			}
		}

		if ( ! empty( $found ) ) {
			return new Check_Item(
				id:        'security_plugin',
				label:     __( 'Security Plugin', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					__( 'Security plugin detected: %s.', 'scalyn-qa-assistant' ),
					esc_html( implode( ', ', $found ) ),
				),
				category:  'functionality',
				severity:  'warning',
				quick_fix: null,
				tooltip:   __( 'A security plugin helps protect your site from brute force attacks, malware, and vulnerabilities.', 'scalyn-qa-assistant' ),
			);
		}

		return new Check_Item(
			id:        'security_plugin',
			label:     __( 'Security Plugin', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   __( 'No security plugin detected. Consider installing Wordfence, Sucuri, or Solid Security.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'A security plugin adds firewall protection, malware scanning, and login security to your WordPress site.', 'scalyn-qa-assistant' ),
		);
	}

	/**
	 * Check if a caching plugin is installed and active.
	 */
	private function check_cache_plugin(): Check_Item {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$cache_plugins = array(
			'wp-super-cache/wp-cache.php'                 => 'WP Super Cache',
			'w3-total-cache/w3-total-cache.php'           => 'W3 Total Cache',
			'wp-fastest-cache/wpFastestCache.php'         => 'WP Fastest Cache',
			'litespeed-cache/litespeed-cache.php'         => 'LiteSpeed Cache',
			'wp-rocket/wp-rocket.php'                     => 'WP Rocket',
			'autoptimize/autoptimize.php'                 => 'Autoptimize',
			'cache-enabler/cache-enabler.php'             => 'Cache Enabler',
			'sg-cachepress/sg-cachepress.php'             => 'SG Optimizer',
			'breeze/breeze.php'                           => 'Breeze',
			'hummingbird-performance/wp-hummingbird.php'  => 'Hummingbird',
			'nitropack/main.php'                          => 'NitroPack',
		);

		$found = array();
		foreach ( $cache_plugins as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$found[] = $name;
			}
		}

		if ( ! empty( $found ) ) {
			return new Check_Item(
				id:        'cache_plugin',
				label:     __( 'Cache Plugin', 'scalyn-qa-assistant' ),
				status:    'pass',
				message:   sprintf(
					__( 'Cache plugin detected: %s.', 'scalyn-qa-assistant' ),
					esc_html( implode( ', ', $found ) ),
				),
				category:  'functionality',
				severity:  'warning',
				quick_fix: null,
				tooltip:   __( 'A caching plugin improves page load speed by serving static versions of your pages.', 'scalyn-qa-assistant' ),
			);
		}

		return new Check_Item(
			id:        'cache_plugin',
			label:     __( 'Cache Plugin', 'scalyn-qa-assistant' ),
			status:    'warning',
			message:   __( 'No caching plugin detected. Consider installing WP Rocket, LiteSpeed Cache, or WP Super Cache.', 'scalyn-qa-assistant' ),
			category:  'functionality',
			severity:  'warning',
			quick_fix: null,
			tooltip:   __( 'Caching significantly improves page load times and reduces server load. Essential for production sites.', 'scalyn-qa-assistant' ),
		);
	}
}
