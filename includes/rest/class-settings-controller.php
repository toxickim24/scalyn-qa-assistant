<?php
/**
 * Settings REST Controller.
 *
 * Handles all settings, template, and wizard REST API endpoints.
 *
 * @package Scalyn\QA\Rest
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Rest;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\AI\AI_Manager;
use Scalyn\QA\Debug_Logger;
use Scalyn\QA\Updates\GitHub_Updater;

/**
 * Class Settings_Controller
 *
 * Provides endpoints for managing plugin settings, scan templates,
 * and the setup wizard.
 *
 * @since 1.0.0
 */
class Settings_Controller extends REST_Controller {

	/**
	 * Option key for general settings.
	 *
	 * @var string
	 */
	private const SETTINGS_OPTION = 'scalyn_qa_settings';

	/**
	 * Option key for scan templates.
	 *
	 * @var string
	 */
	private const TEMPLATES_OPTION = 'scalyn_qa_templates';

	/**
	 * Option key for wizard dismissed state.
	 *
	 * @var string
	 */
	private const WIZARD_DISMISSED_OPTION = 'scalyn_qa_wizard_dismissed';

	/**
	 * Option key for AI configuration.
	 *
	 * @var string
	 */
	private const AI_CONFIG_OPTION = 'scalyn_qa_ai_config';

	/**
	 * Option key for global ignores.
	 *
	 * @var string
	 */
	private const GLOBAL_IGNORES_OPTION = 'scalyn_qa_global_ignores';

	/**
	 * Option key for settings backup (pre-import snapshot).
	 *
	 * @var string
	 */
	private const BACKUP_OPTION = 'scalyn_qa_settings_backup';

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes(): void {
		// GET & POST /settings.
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		// POST /settings/templates.
		register_rest_route(
			$this->namespace,
			'/settings/templates',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_template' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		// PUT & DELETE /settings/templates/{id}.
		register_rest_route(
			$this->namespace,
			'/settings/templates/(?P<template_id>[\\w-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_template' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_template' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		// POST /settings/templates/{id}/duplicate.
		register_rest_route(
			$this->namespace,
			'/settings/templates/(?P<template_id>[\\w-]+)/duplicate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate_template' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		// POST /wizard/install.
		register_rest_route(
			$this->namespace,
			'/wizard/install',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'wizard_install' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		// GET /settings/export.
		register_rest_route(
			$this->namespace,
			'/settings/export',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export_settings' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		// POST /settings/import.
		register_rest_route(
			$this->namespace,
			'/settings/import',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import_settings' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		// GET /settings/backup.
		register_rest_route(
			$this->namespace,
			'/settings/backup',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_backup' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		// POST /settings/rollback.
		register_rest_route(
			$this->namespace,
			'/settings/rollback',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rollback_settings' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		// POST & DELETE /wizard/dismiss.
		register_rest_route(
			$this->namespace,
			'/wizard/dismiss',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'wizard_dismiss' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'wizard_reset' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		// POST /updates/check — manual GitHub update check.
		register_rest_route(
			$this->namespace,
			'/updates/check',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'check_github_updates' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		// POST /updates/save-token — save GitHub settings (owner, repo, token).
		register_rest_route(
			$this->namespace,
			'/updates/save-token',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_github_token' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		// GET & DELETE /debug/log.
		register_rest_route(
			$this->namespace,
			'/debug/log',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_debug_log' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
					'args'                => array(
						'limit'    => array(
							'default'           => 100,
							'sanitize_callback' => 'absint',
						),
						'category' => array(
							'default'           => null,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_debug_log' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);
	}

	// ------------------------------------------------------------------
	// Permission callback
	// ------------------------------------------------------------------

	/**
	 * Permission callback for all routes — requires manage_options.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function check_manage_permission(): bool {
		return $this->can_manage();
	}

	// ------------------------------------------------------------------
	// Settings endpoints
	// ------------------------------------------------------------------

	/**
	 * GET /settings — retrieve all settings with masked AI keys.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_settings( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$settings = get_option( self::SETTINGS_OPTION, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// Include AI config with masked keys.
		$ai_manager = new AI_Manager();
		$ai_config  = $ai_manager->get_config();

		if ( ! empty( $ai_config['providers'] ) && is_array( $ai_config['providers'] ) ) {
			foreach ( $ai_config['providers'] as $provider_key => $provider_config ) {
				if ( ! empty( $provider_config['api_key'] ) ) {
					$decrypted = AI_Manager::decrypt_key( $provider_config['api_key'] );
					$ai_config['providers'][ $provider_key ]['api_key'] = $this->mask_key( $decrypted );
				}
			}
		}

		$settings['ai_config'] = $ai_config;

		return $this->success( $settings );
	}

	/**
	 * POST /settings — save general settings and AI config.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_settings( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$params   = $request->get_json_params();
		$settings = get_option( self::SETTINGS_OPTION, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		if ( ! is_array( $params ) ) {
			return $this->error( 'invalid_body', __( 'Request body must be a JSON object.', 'scalyn-qa-assistant' ), 400 );
		}

		// Sanitize and merge general settings.
		if ( array_key_exists( 'auto_scan_on_save', $params ) ) {
			$settings['auto_scan_on_save'] = (bool) $params['auto_scan_on_save'];
		}

		if ( array_key_exists( 'post_types', $params ) ) {
			$settings['post_types'] = $this->sanitize_post_types( $params['post_types'] );
		}

		if ( array_key_exists( 'score_green', $params ) ) {
			$settings['score_green'] = $this->sanitize_score( (int) $params['score_green'] );
		}

		if ( array_key_exists( 'score_yellow', $params ) ) {
			$score_yellow = $this->sanitize_score( (int) $params['score_yellow'] );
			$score_green  = $settings['score_green'] ?? 80;

			if ( $score_yellow >= $score_green ) {
				return $this->error(
					'invalid_score_yellow',
					__( 'Yellow threshold must be less than green threshold.', 'scalyn-qa-assistant' ),
					400
				);
			}

			$settings['score_yellow'] = $score_yellow;
		}

		if ( array_key_exists( 'link_timeout', $params ) ) {
			$settings['link_timeout'] = max( 1, min( 30, (int) $params['link_timeout'] ) );
		}

		if ( array_key_exists( 'link_cache_hours', $params ) ) {
			$settings['link_cache_hours'] = max( 1, min( 168, (int) $params['link_cache_hours'] ) );
		}

		if ( array_key_exists( 'active_template', $params ) ) {
			$settings['active_template'] = sanitize_text_field( (string) $params['active_template'] );
		}

		if ( array_key_exists( 'delete_data_on_uninstall', $params ) ) {
			$settings['delete_data_on_uninstall'] = (bool) $params['delete_data_on_uninstall'];
		}

		if ( array_key_exists( 'max_ai_requests_per_day', $params ) ) {
			$settings['max_ai_requests_per_day'] = max( 0, (int) $params['max_ai_requests_per_day'] );
		}

		if ( array_key_exists( 'debug_mode', $params ) ) {
			$settings['debug_mode'] = (bool) $params['debug_mode'];
		}

		if ( array_key_exists( 'enable_ai', $params ) ) {
			$settings['enable_ai'] = (bool) $params['enable_ai'];
		}

		// Save general settings.
		update_option( self::SETTINGS_OPTION, $settings, false );

		// Handle launch checklist settings.
		if ( array_key_exists( 'launch_settings', $params ) && is_array( $params['launch_settings'] ) ) {
			$ls = $params['launch_settings'];
			$launch_data = array(
				'thresholds' => array(
					'memory_limit'       => max( 64, (int) ( $ls['thresholds']['memory_limit'] ?? 512 ) ),
					'max_execution_time' => max( 30, (int) ( $ls['thresholds']['max_execution_time'] ?? 90 ) ),
					'max_input_time'     => max( 30, (int) ( $ls['thresholds']['max_input_time'] ?? 90 ) ),
					'post_max_size'      => max( 8, (int) ( $ls['thresholds']['post_max_size'] ?? 128 ) ),
					'upload_max_size'    => max( 2, (int) ( $ls['thresholds']['upload_max_size'] ?? 64 ) ),
				),
				'enabled_checks' => isset( $ls['enabled_checks'] ) && is_array( $ls['enabled_checks'] )
					? array_map( 'sanitize_key', $ls['enabled_checks'] )
					: array(),
			);
			update_option( 'scalyn_qa_launch_settings', $launch_data, false );
		}

		// Handle AI config separately via AI_Manager.
		if ( array_key_exists( 'ai_config', $params ) && is_array( $params['ai_config'] ) ) {
			// Also sync enable_ai to general settings from ai_config.enabled
			if ( isset( $params['ai_config']['enabled'] ) ) {
				$settings['enable_ai'] = (bool) $params['ai_config']['enabled'];
				update_option( self::SETTINGS_OPTION, $settings, false );
			}
			$this->save_ai_config( $params['ai_config'] );
		}

		return $this->success( $settings );
	}

	// ------------------------------------------------------------------
	// Template endpoints
	// ------------------------------------------------------------------

	/**
	 * POST /settings/templates — create a new template.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_template( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$params = $request->get_json_params();

		if ( empty( $params['name'] ) || ! is_string( $params['name'] ) ) {
			return $this->error( 'missing_name', __( 'Template name is required.', 'scalyn-qa-assistant' ), 400 );
		}

		if ( ! isset( $params['checks'] ) || ! is_array( $params['checks'] ) ) {
			return $this->error( 'missing_checks', __( 'Template checks array is required.', 'scalyn-qa-assistant' ), 400 );
		}

		$template = array(
			'id'         => wp_generate_uuid4(),
			'name'       => sanitize_text_field( $params['name'] ),
			'checks'     => array_map( 'sanitize_key', $params['checks'] ),
			'created_at' => gmdate( 'c' ),
		);

		$templates   = $this->get_templates();
		$templates[] = $template;

		update_option( self::TEMPLATES_OPTION, $templates, false );

		return $this->success( $template, 201 );
	}

	/**
	 * PUT /settings/templates/{id} — update a template.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_template( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$template_id = sanitize_text_field( $request->get_param( 'template_id' ) );
		$params      = $request->get_json_params();
		$templates   = $this->get_templates();
		$index       = $this->find_template_index( $templates, $template_id );

		if ( null === $index ) {
			return $this->error( 'not_found', __( 'Template not found.', 'scalyn-qa-assistant' ), 404 );
		}

		if ( isset( $params['name'] ) && is_string( $params['name'] ) ) {
			$templates[ $index ]['name'] = sanitize_text_field( $params['name'] );
		}

		if ( isset( $params['checks'] ) && is_array( $params['checks'] ) ) {
			$templates[ $index ]['checks'] = array_map( 'sanitize_key', $params['checks'] );
		}

		update_option( self::TEMPLATES_OPTION, $templates, false );

		return $this->success( $templates[ $index ] );
	}

	/**
	 * POST /settings/templates/{id}/duplicate — duplicate a template.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function duplicate_template( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$template_id = sanitize_text_field( $request->get_param( 'template_id' ) );
		$templates   = $this->get_templates();
		$index       = $this->find_template_index( $templates, $template_id );

		if ( null === $index ) {
			return $this->error( 'not_found', __( 'Template not found.', 'scalyn-qa-assistant' ), 404 );
		}

		$source = $templates[ $index ];

		$duplicate = array(
			'id'         => wp_generate_uuid4(),
			'name'       => sanitize_text_field( $source['name'] ) . ' (Copy)',
			'checks'     => $source['checks'],
			'created_at' => gmdate( 'c' ),
		);

		$templates[] = $duplicate;

		update_option( self::TEMPLATES_OPTION, $templates, false );

		return $this->success( $duplicate, 201 );
	}

	/**
	 * DELETE /settings/templates/{id} — delete a template.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_template( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$template_id = sanitize_text_field( $request->get_param( 'template_id' ) );
		$templates   = $this->get_templates();
		$index       = $this->find_template_index( $templates, $template_id );

		if ( null === $index ) {
			return $this->error( 'not_found', __( 'Template not found.', 'scalyn-qa-assistant' ), 404 );
		}

		array_splice( $templates, $index, 1 );

		update_option( self::TEMPLATES_OPTION, $templates, false );

		return $this->success( array( 'deleted' => true ) );
	}

	// ------------------------------------------------------------------
	// Wizard endpoints
	// ------------------------------------------------------------------

	/**
	 * POST /wizard/install — install and activate an SEO plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function wizard_install( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$params = $request->get_json_params();
		$plugin = isset( $params['plugin'] ) ? sanitize_key( $params['plugin'] ) : '';

		$allowed = array( 'rank-math', 'yoast', 'skip' );

		if ( ! in_array( $plugin, $allowed, true ) ) {
			return $this->error(
				'invalid_plugin',
				__( 'Invalid plugin choice. Must be "rank-math", "yoast", or "skip".', 'scalyn-qa-assistant' ),
				400
			);
		}

		// If skipping, just dismiss.
		if ( 'skip' === $plugin ) {
			update_option( self::WIZARD_DISMISSED_OPTION, true, false );
			return $this->success( array( 'skipped' => true ) );
		}

		// Map plugin choice to WordPress.org slug.
		$slug_map = array(
			'rank-math' => 'seo-by-rank-math',
			'yoast'     => 'wordpress-seo',
		);

		$slug = $slug_map[ $plugin ];
		$url  = "https://downloads.wordpress.org/plugin/{$slug}.latest-stable.zip";

		// Include required WordPress files for plugin installation.
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );

		$result = $upgrader->install( $url );

		if ( is_wp_error( $result ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Scalyn QA wizard install failed: ' . $result->get_error_message() );
			return $this->error(
				'install_failed',
				__( 'Plugin installation failed. Please try again or install manually.', 'scalyn-qa-assistant' ),
				500
			);
		}

		if ( false === $result ) {
			return $this->error(
				'install_failed',
				__( 'Plugin installation failed.', 'scalyn-qa-assistant' ),
				500
			);
		}

		// Find and activate the installed plugin.
		$plugin_file = $this->find_plugin_file( $slug );

		if ( $plugin_file ) {
			$activate_result = activate_plugin( $plugin_file );

			if ( is_wp_error( $activate_result ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Scalyn QA wizard activation failed: ' . $activate_result->get_error_message() );
				return $this->error(
					'activation_failed',
					__( 'Plugin activation failed. Please activate it manually from the Plugins page.', 'scalyn-qa-assistant' ),
					500
				);
			}
		}

		// Dismiss the wizard.
		update_option( self::WIZARD_DISMISSED_OPTION, true, false );

		$plugin_names = array(
			'rank-math' => 'Rank Math SEO',
			'yoast'     => 'Yoast SEO',
		);

		return $this->success(
			array(
				'installed' => true,
				'plugin'    => $plugin_names[ $plugin ],
				'activated' => (bool) $plugin_file,
			)
		);
	}

	/**
	 * POST /wizard/dismiss — dismiss the setup wizard.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function wizard_dismiss( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		update_option( self::WIZARD_DISMISSED_OPTION, true, false );

		return $this->success( array( 'dismissed' => true ) );
	}

	/**
	 * DELETE /wizard/dismiss — reset (un-dismiss) the setup wizard.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function wizard_reset( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		delete_option( self::WIZARD_DISMISSED_OPTION );

		return $this->success( array( 'reset' => true ) );
	}

	// ------------------------------------------------------------------
	// Debug log endpoints
	// ------------------------------------------------------------------

	/**
	 * GET /debug/log — retrieve debug log entries.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_debug_log( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$limit    = (int) $request->get_param( 'limit' );
		$category = $request->get_param( 'category' );

		if ( $limit <= 0 ) {
			$limit = 100;
		}

		// Treat empty string as null (no filter).
		if ( '' === $category || null === $category ) {
			$category = null;
		}

		$entries = Debug_Logger::get_log( $limit, $category );

		return $this->success( array( 'entries' => $entries ) );
	}

	/**
	 * DELETE /debug/log — clear all debug log entries.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function clear_debug_log( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		Debug_Logger::clear();

		return $this->success( array( 'cleared' => true ) );
	}

	// ------------------------------------------------------------------
	// Export / Import endpoints
	// ------------------------------------------------------------------

	/**
	 * GET /settings/export — export all settings as JSON.
	 *
	 * API keys are masked in the export to prevent accidental exposure.
	 *
	 * @since 1.1.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function export_settings( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$settings = get_option( self::SETTINGS_OPTION, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// Collect AI config with masked keys.
		$ai_manager = new AI_Manager();
		$ai_config  = $ai_manager->get_config();

		if ( ! empty( $ai_config['providers'] ) && is_array( $ai_config['providers'] ) ) {
			foreach ( $ai_config['providers'] as $provider_key => $provider_config ) {
				if ( ! empty( $provider_config['api_key'] ) ) {
					$decrypted = AI_Manager::decrypt_key( $provider_config['api_key'] );
					$ai_config['providers'][ $provider_key ]['api_key'] = $this->mask_key( $decrypted );
				}
			}
		}

		$templates      = get_option( self::TEMPLATES_OPTION, array() );
		$global_ignores = get_option( self::GLOBAL_IGNORES_OPTION, array() );

		$export = array(
			'plugin_version' => defined( 'SCALYN_QA_VERSION' ) ? SCALYN_QA_VERSION : '1.0.0',
			'export_date'    => gmdate( 'c' ),
			'settings'       => $settings,
			'ai_config'      => $ai_config,
			'templates'      => is_array( $templates ) ? $templates : array(),
			'global_ignores' => is_array( $global_ignores ) ? $global_ignores : array(),
		);

		return $this->success( $export );
	}

	/**
	 * POST /settings/import — import settings from a JSON export.
	 *
	 * API keys are NOT imported because they are masked in the export.
	 *
	 * @since 1.1.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function import_settings( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$params = $request->get_json_params();

		if ( ! is_array( $params ) ) {
			return $this->error( 'invalid_body', __( 'Request body must be a JSON object.', 'scalyn-qa-assistant' ), 400 );
		}

		// Validate structure — must have at least one importable key.
		$valid_keys = array( 'settings', 'ai_config', 'templates', 'global_ignores' );
		$has_data   = false;

		foreach ( $valid_keys as $key ) {
			if ( isset( $params[ $key ] ) && is_array( $params[ $key ] ) ) {
				$has_data = true;
				break;
			}
		}

		if ( ! $has_data ) {
			return $this->error(
				'invalid_import',
				__( 'Import file does not contain valid settings data.', 'scalyn-qa-assistant' ),
				400
			);
		}

		// Create backup before import.
		$current_user = wp_get_current_user();
		$backup       = array(
			'settings'       => get_option( self::SETTINGS_OPTION, array() ),
			'ai_config'      => get_option( self::AI_CONFIG_OPTION, array() ),
			'templates'      => get_option( self::TEMPLATES_OPTION, array() ),
			'global_ignores' => get_option( self::GLOBAL_IGNORES_OPTION, array() ),
			'created_at'     => gmdate( 'c' ),
			'created_by'     => $current_user->display_name,
			'reason'         => 'Pre-import backup',
		);
		update_option( self::BACKUP_OPTION, $backup, false );

		$imported = array();

		// Import general settings.
		if ( isset( $params['settings'] ) && is_array( $params['settings'] ) ) {
			$current_settings = get_option( self::SETTINGS_OPTION, array() );

			if ( ! is_array( $current_settings ) ) {
				$current_settings = array();
			}

			// Merge imported settings over current ones.
			$merged = array_merge( $current_settings, $params['settings'] );
			update_option( self::SETTINGS_OPTION, $merged, false );
			$imported[] = 'settings';
		}

		// Import templates.
		if ( isset( $params['templates'] ) && is_array( $params['templates'] ) ) {
			update_option( self::TEMPLATES_OPTION, $params['templates'], false );
			$imported[] = 'templates';
		}

		// Import global ignores.
		if ( isset( $params['global_ignores'] ) && is_array( $params['global_ignores'] ) ) {
			update_option( self::GLOBAL_IGNORES_OPTION, $params['global_ignores'], false );
			$imported[] = 'global_ignores';
		}

		// AI config: import structure but NOT API keys (they are masked).
		if ( isset( $params['ai_config'] ) && is_array( $params['ai_config'] ) ) {
			$ai_manager      = new AI_Manager();
			$existing_config = $ai_manager->get_config();

			$import_config = $params['ai_config'];

			// Preserve existing API keys — do not import masked keys.
			if ( isset( $import_config['providers'] ) && is_array( $import_config['providers'] ) ) {
				foreach ( $import_config['providers'] as $provider_key => $provider_data ) {
					if ( isset( $provider_data['api_key'] ) ) {
						// Replace with existing encrypted key (skip masked import).
						$import_config['providers'][ $provider_key ]['api_key'] =
							$existing_config['providers'][ $provider_key ]['api_key'] ?? '';
					}
				}
			}

			$ai_manager->save_config( $import_config );
			$imported[] = 'ai_config (API keys preserved)';
		}

		return $this->success(
			array(
				'imported' => $imported,
				'count'    => count( $imported ),
			)
		);
	}

	// ------------------------------------------------------------------
	// Backup / Rollback endpoints
	// ------------------------------------------------------------------

	/**
	 * GET /settings/backup — return backup metadata.
	 *
	 * Returns only the metadata (created_at, created_by, reason) without
	 * the full settings data payload.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_backup( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$backup = get_option( self::BACKUP_OPTION, null );

		if ( empty( $backup ) || ! is_array( $backup ) ) {
			return $this->error(
				'no_backup',
				__( 'No settings backup exists.', 'scalyn-qa-assistant' ),
				404
			);
		}

		return $this->success(
			array(
				'created_at' => $backup['created_at'] ?? '',
				'created_by' => $backup['created_by'] ?? '',
				'reason'     => $backup['reason'] ?? '',
			)
		);
	}

	/**
	 * POST /settings/rollback — restore settings from backup.
	 *
	 * Restores all four option values from the backup and clears the
	 * backup after a successful restore.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rollback_settings( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$backup = get_option( self::BACKUP_OPTION, null );

		if ( empty( $backup ) || ! is_array( $backup ) ) {
			return $this->error(
				'no_backup',
				__( 'No settings backup exists to rollback to.', 'scalyn-qa-assistant' ),
				404
			);
		}

		$restored = array();

		// Restore general settings.
		if ( isset( $backup['settings'] ) && is_array( $backup['settings'] ) ) {
			update_option( self::SETTINGS_OPTION, $backup['settings'], false );
			$restored[] = 'settings';
		}

		// Restore AI config.
		if ( isset( $backup['ai_config'] ) && is_array( $backup['ai_config'] ) ) {
			update_option( self::AI_CONFIG_OPTION, $backup['ai_config'], false );
			$restored[] = 'ai_config';
		}

		// Restore templates.
		if ( isset( $backup['templates'] ) && is_array( $backup['templates'] ) ) {
			update_option( self::TEMPLATES_OPTION, $backup['templates'], false );
			$restored[] = 'templates';
		}

		// Restore global ignores.
		if ( isset( $backup['global_ignores'] ) && is_array( $backup['global_ignores'] ) ) {
			update_option( self::GLOBAL_IGNORES_OPTION, $backup['global_ignores'], false );
			$restored[] = 'global_ignores';
		}

		// Clear the backup after successful restore.
		delete_option( self::BACKUP_OPTION );

		return $this->success(
			array(
				'rolled_back' => true,
				'restored'    => $restored,
				'count'       => count( $restored ),
				'backup_date' => $backup['created_at'] ?? '',
			)
		);
	}

	// ------------------------------------------------------------------
	// GitHub update endpoints
	// ------------------------------------------------------------------

	/**
	 * POST /updates/check — trigger a manual GitHub update check.
	 *
	 * @since 1.3.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function check_github_updates( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$updater = new GitHub_Updater();
		$result  = $updater->manual_check();

		return $this->success( $result );
	}

	/**
	 * POST /updates/save-token — save GitHub owner, repo, and token.
	 *
	 * @since 1.3.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_github_token( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$params   = $request->get_json_params();
		$settings = get_option( self::SETTINGS_OPTION, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// Sanitize owner and repo.
		if ( isset( $params['github_owner'] ) ) {
			$settings['github_owner'] = sanitize_text_field( (string) $params['github_owner'] );
		}

		if ( isset( $params['github_repo'] ) ) {
			$settings['github_repo'] = sanitize_text_field( (string) $params['github_repo'] );
		}

		// Handle token — encrypt if it's a new (non-masked) value.
		if ( isset( $params['github_token'] ) ) {
			$raw_token = (string) $params['github_token'];

			if ( '' === $raw_token ) {
				// Empty means clear the token.
				$settings['github_token'] = '';
			} elseif ( preg_match( '/^\*+/', $raw_token ) ) {
				// Looks like a masked value — keep existing token.
			} else {
				// New token — encrypt it.
				$settings['github_token'] = AI_Manager::encrypt_key( $raw_token );
			}
		}

		update_option( self::SETTINGS_OPTION, $settings, false );

		return $this->success( array( 'saved' => true ) );
	}

	// ------------------------------------------------------------------
	// Private helpers
	// ------------------------------------------------------------------

	/**
	 * Mask an API key, showing only the last 4 characters.
	 *
	 * @param string $key The plain-text key.
	 * @return string Masked key or empty string.
	 */
	private function mask_key( string $key ): string {
		$len = strlen( $key );

		if ( '' === $key ) {
			return '';
		}

		if ( $len <= 8 ) {
			return str_repeat( '•', $len );
		}

		// Show first 7 + dots + last 4: sk-proj•••••••Rw_kA
		return substr( $key, 0, 7 ) . str_repeat( '•', 8 ) . substr( $key, -4 );
	}

	/**
	 * Sanitize post_types input.
	 *
	 * @param mixed $post_types Raw input.
	 * @return array Sanitized array of post type keys.
	 */
	private function sanitize_post_types( mixed $post_types ): array {
		if ( ! is_array( $post_types ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'sanitize_key', $post_types ) ) );
	}

	/**
	 * Sanitize a score value to be between 0 and 100.
	 *
	 * @param int $score The raw score.
	 * @return int Clamped score.
	 */
	private function sanitize_score( int $score ): int {
		return max( 0, min( 100, $score ) );
	}

	/**
	 * Get all templates from options.
	 *
	 * @return array
	 */
	private function get_templates(): array {
		$templates = get_option( self::TEMPLATES_OPTION, array() );

		if ( ! is_array( $templates ) ) {
			$templates = array();
		}

		// Seed default template if none exist.
		if ( empty( $templates ) ) {
			$templates = array(
				array(
					'id'         => 'default',
					'name'       => 'Default Template',
					'checks'     => array(
						'meta_title_exists',
						'meta_description_exists',
						'heading_structure',
						'image_alt_text',
						'internal_links',
						'content_length',
						'broken_links',
						'featured_image',
					),
					'created_at' => gmdate( 'c' ),
				),
			);
			update_option( self::TEMPLATES_OPTION, $templates, false );
		}

		return $templates;
	}

	/**
	 * Find a template index by ID.
	 *
	 * @param array  $templates All templates.
	 * @param string $id        The template ID to find.
	 * @return int|null The index or null if not found.
	 */
	private function find_template_index( array $templates, string $id ): ?int {
		foreach ( $templates as $index => $template ) {
			if ( isset( $template['id'] ) && $template['id'] === $id ) {
				return $index;
			}
		}

		return null;
	}

	/**
	 * Save AI configuration via AI_Manager.
	 *
	 * Handles masking detection: if a key is all asterisks (masked),
	 * the existing stored key is preserved.
	 *
	 * @param array $ai_config The AI configuration from the request.
	 */
	private function save_ai_config( array $ai_config ): void {
		$ai_manager     = new AI_Manager();
		$existing_config = $ai_manager->get_config();

		// Sanitize top-level AI config fields.
		$sanitized = array();

		if ( array_key_exists( 'enabled', $ai_config ) ) {
			$sanitized['enabled'] = (bool) $ai_config['enabled'];
		} elseif ( isset( $existing_config['enabled'] ) ) {
			$sanitized['enabled'] = $existing_config['enabled'];
		}

		if ( array_key_exists( 'primary', $ai_config ) ) {
			$sanitized['primary'] = sanitize_key( (string) $ai_config['primary'] );
		} elseif ( isset( $existing_config['primary'] ) ) {
			$sanitized['primary'] = $existing_config['primary'];
		}

		if ( array_key_exists( 'fallback', $ai_config ) ) {
			$sanitized['fallback'] = sanitize_key( (string) $ai_config['fallback'] );
		} elseif ( isset( $existing_config['fallback'] ) ) {
			$sanitized['fallback'] = $existing_config['fallback'];
		}

		if ( array_key_exists( 'secondary_fallback', $ai_config ) ) {
			$sanitized['secondary_fallback'] = sanitize_key( (string) $ai_config['secondary_fallback'] );
		} elseif ( isset( $existing_config['secondary_fallback'] ) ) {
			$sanitized['secondary_fallback'] = $existing_config['secondary_fallback'];
		}

		// Handle providers.
		$sanitized['providers'] = $existing_config['providers'] ?? array();

		if ( isset( $ai_config['providers'] ) && is_array( $ai_config['providers'] ) ) {
			foreach ( $ai_config['providers'] as $provider_key => $provider_data ) {
				$provider_key = sanitize_key( $provider_key );

				if ( ! is_array( $provider_data ) ) {
					continue;
				}

				if ( ! isset( $sanitized['providers'][ $provider_key ] ) ) {
					$sanitized['providers'][ $provider_key ] = array();
				}

				// Handle API key — preserve existing if masked value sent.
				if ( isset( $provider_data['api_key'] ) ) {
					$raw_key = (string) $provider_data['api_key'];

					// Skip empty keys or masked/placeholder values — keep existing.
					if ( '' === $raw_key
						|| str_contains( $raw_key, '•' )
						|| str_contains( $raw_key, '*' )
						|| '__configured__' === $raw_key
					) {
						// Keep existing encrypted key unchanged.
					} else {
						// New real key provided — encrypt and store it.
						$sanitized['providers'][ $provider_key ]['api_key'] = AI_Manager::encrypt_key( $raw_key );
					}
				}

				// Handle model.
				if ( isset( $provider_data['model'] ) ) {
					$sanitized['providers'][ $provider_key ]['model'] = sanitize_text_field( (string) $provider_data['model'] );
				}

				// Handle custom endpoint URL.
				if ( isset( $provider_data['endpoint'] ) ) {
					$endpoint = esc_url_raw( (string) $provider_data['endpoint'] );
					$sanitized['providers'][ $provider_key ]['endpoint'] = $endpoint;
				}

				// Handle custom headers (JSON string or array).
				if ( isset( $provider_data['custom_headers'] ) ) {
					if ( is_string( $provider_data['custom_headers'] ) ) {
						$parsed = json_decode( $provider_data['custom_headers'], true );
						$sanitized['providers'][ $provider_key ]['custom_headers'] = is_array( $parsed ) ? array_map( 'sanitize_text_field', $parsed ) : [];
					} elseif ( is_array( $provider_data['custom_headers'] ) ) {
						$sanitized['providers'][ $provider_key ]['custom_headers'] = array_map( 'sanitize_text_field', $provider_data['custom_headers'] );
					}
				}

				// Handle model name for custom endpoints (free text).
				if ( isset( $provider_data['model_name'] ) ) {
					$sanitized['providers'][ $provider_key ]['model_name'] = sanitize_text_field( (string) $provider_data['model_name'] );
				}
			}
		}

		$ai_manager->save_config( $sanitized );
	}

	/**
	 * Find the main plugin file for an installed plugin by its slug.
	 *
	 * @param string $slug The plugin directory slug.
	 * @return string|null The plugin file path relative to plugins dir, or null.
	 */
	private function find_plugin_file( string $slug ): ?string {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();

		foreach ( $all_plugins as $file => $data ) {
			if ( str_starts_with( $file, $slug . '/' ) ) {
				return $file;
			}
		}

		return null;
	}
}
