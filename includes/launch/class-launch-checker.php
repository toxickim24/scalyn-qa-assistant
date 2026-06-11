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
	public function run_checks(): array {
		$checks = array(
			$this->check_seo_plugin_installed(),
			$this->check_sitemap_exists(),
			$this->check_ga4_configured(),
			$this->check_gtm_configured(),
			$this->check_ssl_enabled(),
			$this->check_favicon_exists(),
			$this->check_contact_page_exists(),
			$this->check_privacy_policy_exists(),
			$this->check_plugin_conflicts(),
		);

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
}
