<?php
/**
 * AI Controller.
 *
 * REST endpoints for AI-powered meta generation, application, and testing.
 *
 * @package Scalyn\QA\Rest
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Rest;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\AI\AI_Manager;
use Scalyn\QA\AI\AI_Health_Monitor;
use Scalyn\QA\AI\AI_Provider_Registry;
use Scalyn\QA\Integrations\SEO_Integration;

/**
 * Class AI_Controller
 *
 * Handles AI meta generation, application to SEO plugins,
 * connection testing, and draft retrieval.
 *
 * @since 1.0.0
 */
class AI_Controller extends REST_Controller {

	/**
	 * Valid generation types.
	 *
	 * @var string[]
	 */
	private const VALID_TYPES = array( 'both', 'title', 'description' );

	/**
	 * Post meta key for AI drafts.
	 *
	 * @var string
	 */
	private const META_DRAFTS = '_scalyn_qa_ai_drafts';

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/ai/generate/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_meta' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && absint( $v ) > 0,
					),
					'type'    => array(
						'default'           => 'both',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static fn( $v ): bool => in_array( $v, self::VALID_TYPES, true ),
					),
				),
			),
		);

		register_rest_route(
			$this->namespace,
			'/ai/apply/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply_meta' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id'     => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && absint( $v ) > 0,
					),
					'title'       => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'description' => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			),
		);

		register_rest_route(
			$this->namespace,
			'/ai/test',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_connection' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'provider' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static fn( $v ): bool => AI_Provider_Registry::has( (string) $v ),
					),
				),
			),
		);

		register_rest_route(
			$this->namespace,
			'/ai/log',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_ai_log' ),
				'permission_callback' => array( $this, 'can_manage' ),
			),
		);

		register_rest_route(
			$this->namespace,
			'/ai/drafts/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_drafts' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && absint( $v ) > 0,
					),
				),
			),
		);

		register_rest_route(
			$this->namespace,
			'/ai/health',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_health' ),
				'permission_callback' => array( $this, 'can_manage' ),
			),
		);

		// POST /ai/review/{post_id} — AI content review.
		register_rest_route(
			$this->namespace,
			'/ai/review/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'review_content' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && absint( $v ) > 0,
					),
				),
			),
		);
	}

	/**
	 * Review content for spelling, grammar, and readability using AI.
	 *
	 * @since 1.0.7
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function review_content( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission to edit this post.', 'scalyn-qa-assistant' ), 403 );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $this->error( 'post_not_found', __( 'Post not found.', 'scalyn-qa-assistant' ), 404 );
		}

		$ai_manager = new AI_Manager();

		if ( ! $ai_manager->is_enabled() ) {
			return $this->error(
				'ai_not_enabled',
				__( 'AI features are not enabled. Configure an AI provider in Settings.', 'scalyn-qa-assistant' ),
				400,
			);
		}

		try {
			$result = $ai_manager->review_content( $post_id );
		} catch ( \Throwable $e ) {
			return $this->error( 'review_failed', $e->getMessage(), 500 );
		}

		if ( empty( $result['summary'] ) && empty( $result['issues'] ) ) {
			return $this->error(
				'review_empty',
				__( 'AI content review returned no results. Try again or check your AI provider settings.', 'scalyn-qa-assistant' ),
				500,
			);
		}

		return $this->success( $result );
	}

	/**
	 * Generate AI meta title and/or description for a post.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate_meta( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$type    = sanitize_text_field( $request->get_param( 'type' ) ?: 'both' );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission to edit this post.', 'scalyn-qa-assistant' ), 403 );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $this->error( 'post_not_found', __( 'Post not found.', 'scalyn-qa-assistant' ), 404 );
		}

		$ai_manager = new AI_Manager();

		if ( ! $ai_manager->is_enabled() ) {
			return $this->error(
				'ai_not_enabled',
				__( 'AI features are not enabled. Configure an AI provider in settings.', 'scalyn-qa-assistant' ),
				400,
			);
		}

		try {
			$result = $ai_manager->generate_meta( $post_id, $type );
		} catch ( \RuntimeException $e ) {
			return $this->error(
				'ai_rate_limit_exceeded',
				$e->getMessage(),
				429,
			);
		}

		if ( empty( $result['title'] ) && empty( $result['description'] ) ) {
			return $this->error(
				'ai_generation_failed',
				__( 'AI generation failed. Check your API key and provider configuration.', 'scalyn-qa-assistant' ),
				500,
			);
		}

		return $this->success(
			array(
				'title'       => $result['title'],
				'description' => $result['description'],
				'provider'    => $result['provider'],
				'model'       => $result['model'],
			),
			201,
		);
	}

	/**
	 * Apply meta title and/or description to a post via the active SEO plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function apply_meta( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id     = absint( $request->get_param( 'post_id' ) );
		$title       = sanitize_text_field( $request->get_param( 'title' ) ?? '' );
		$description = sanitize_text_field( $request->get_param( 'description' ) ?? '' );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission to edit this post.', 'scalyn-qa-assistant' ), 403 );
		}

		if ( '' === $title && '' === $description ) {
			return $this->error(
				'no_meta_provided',
				__( 'At least a title or description must be provided.', 'scalyn-qa-assistant' ),
				400,
			);
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $this->error( 'post_not_found', __( 'Post not found.', 'scalyn-qa-assistant' ), 404 );
		}

		$integration = SEO_Integration::detect();

		if ( null === $integration ) {
			return $this->error(
				'no_seo_plugin',
				__( 'No supported SEO plugin detected. Install Rank Math, Yoast SEO, or All in One SEO.', 'scalyn-qa-assistant' ),
				400,
			);
		}

		$applied = array();

		if ( '' !== $title ) {
			$integration->set_meta_title( $post_id, $title );
			$applied[] = 'title';
		}

		if ( '' !== $description ) {
			$integration->set_meta_description( $post_id, $description );
			$applied[] = 'description';
		}

		return $this->success(
			array(
				'applied'     => $applied,
				'plugin_name' => $integration->get_plugin_name(),
				'plugin_slug' => $integration->get_plugin_slug(),
				'post_id'     => $post_id,
			),
		);
	}

	/**
	 * Test an AI provider connection.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public function test_connection( \WP_REST_Request $request ): \WP_REST_Response {
		$provider = sanitize_text_field( $request->get_param( 'provider' ) );

		$ai_manager = new AI_Manager();
		$result     = $ai_manager->test_connection( $provider );

		return $this->success(
			array(
				'provider' => $provider,
				'success'  => $result['success'],
				'message'  => $result['message'],
			),
		);
	}

	/**
	 * GET /ai/log — retrieve AI usage log.
	 *
	 * @since 1.1.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public function get_ai_log( \WP_REST_Request $request ): \WP_REST_Response {
		$ai_manager = new AI_Manager();
		$log        = $ai_manager->get_log( 30 );

		return $this->success(
			array(
				'entries' => $log,
				'count'   => count( $log ),
			),
		);
	}

	/**
	 * Get saved AI drafts for a post.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_drafts( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $this->error( 'post_not_found', __( 'Post not found.', 'scalyn-qa-assistant' ), 404 );
		}

		$drafts = get_post_meta( $post_id, self::META_DRAFTS, true );
		$drafts = is_array( $drafts ) ? $drafts : array();

		// Sanitize each draft entry.
		$sanitized = array_map(
			static fn( array $draft ): array => array(
				'title'       => sanitize_text_field( $draft['title'] ?? '' ),
				'description' => sanitize_text_field( $draft['description'] ?? '' ),
				'provider'    => sanitize_text_field( $draft['provider'] ?? '' ),
				'model'       => sanitize_text_field( $draft['model'] ?? '' ),
				'created_at'  => sanitize_text_field( $draft['created_at'] ?? '' ),
			),
			array_filter( $drafts, 'is_array' ),
		);

		return $this->success(
			array(
				'post_id' => $post_id,
				'drafts'  => array_values( $sanitized ),
			),
		);
	}

	/**
	 * GET /ai/health — retrieve health and usage stats for all registered providers.
	 *
	 * @since 1.4.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public function get_health( \WP_REST_Request $request ): \WP_REST_Response {
		$ai_manager = new AI_Manager();
		$config     = $ai_manager->get_config();
		$providers  = AI_Provider_Registry::get_all();
		$result     = [];

		foreach ( $providers as $slug => $registration ) {
			$health          = AI_Health_Monitor::get_health( $slug );
			$provider_config = $config['providers'][ $slug ] ?? [];
			$has_api_key     = ! empty( $provider_config['api_key'] );

			$result[] = [
				'slug'              => $slug,
				'name'              => $registration['name'],
				'category'          => $registration['category'],
				'is_configured'     => $has_api_key,
				'status'            => $health['status'],
				'last_success'      => $health['last_success'],
				'last_failure'      => $health['last_failure'],
				'last_error'        => $health['last_error'],
				'total_requests'    => $health['total_requests'],
				'success_rate'      => AI_Health_Monitor::get_success_rate( $slug ),
				'avg_response_time' => AI_Health_Monitor::get_avg_response_time( $slug ),
			];
		}

		return $this->success(
			[
				'providers' => $result,
				'count'     => count( $result ),
			],
		);
	}
}
