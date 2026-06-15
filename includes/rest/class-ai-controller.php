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

		// POST /ai/generate-alt/{post_id} — generate alt text for images.
		register_rest_route(
			$this->namespace,
			'/ai/generate-alt/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_alt_texts' ),
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

		// POST /ai/apply-alt/{post_id} — apply alt text to an image.
		register_rest_route(
			$this->namespace,
			'/ai/apply-alt/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply_alt_text' ),
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

		// POST /ai/review/{post_id}/update — update review issue statuses.
		register_rest_route(
			$this->namespace,
			'/ai/review/(?P<post_id>\d+)/update',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_review_issues' ),
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
	 * Generate AI alt text for images missing it.
	 *
	 * @since 1.0.7
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate_alt_texts( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission to edit this post.', 'scalyn-qa-assistant' ), 403 );
		}

		$ai_manager = new AI_Manager();

		if ( ! $ai_manager->is_enabled() ) {
			return $this->error( 'ai_not_enabled', __( 'AI features are not enabled.', 'scalyn-qa-assistant' ), 400 );
		}

		try {
			$result = $ai_manager->generate_alt_texts( $post_id );
		} catch ( \Throwable $e ) {
			return $this->error( 'alt_text_failed', $e->getMessage(), 500 );
		}

		return $this->success( $result );
	}

	/**
	 * Apply AI-generated alt text to an image attachment.
	 *
	 * @since 1.0.7
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function apply_alt_text( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id  = absint( $request->get_param( 'post_id' ) );
		$params   = $request->get_json_params();
		$src      = $params['src'] ?? '';
		$alt_text = sanitize_text_field( $params['alt_text'] ?? '' );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission.', 'scalyn-qa-assistant' ), 403 );
		}

		if ( empty( $src ) || empty( $alt_text ) ) {
			return $this->error( 'missing_params', __( 'Image src and alt_text are required.', 'scalyn-qa-assistant' ), 400 );
		}

		// Find the attachment ID by URL.
		$attachment_id = attachment_url_to_postid( $src );

		if ( 0 === $attachment_id ) {
			// Try with the upload dir stripped — handle relative or resized URLs.
			$upload_dir = wp_get_upload_dir();
			$relative   = str_replace( $upload_dir['baseurl'] . '/', '', $src );
			// Try finding by partial match in guid.
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$attachment_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid LIKE %s LIMIT 1",
					'%' . $wpdb->esc_like( $relative ) . '%',
				),
			);
		}

		if ( $attachment_id > 0 ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

			return $this->success( array(
				'applied'       => true,
				'attachment_id' => $attachment_id,
				'alt_text'      => $alt_text,
			) );
		}

		// Fallback: update alt text directly in post content.
		$post    = get_post( $post_id );
		$content = $post->post_content ?? '';

		if ( ! empty( $content ) ) {
			// Find the img tag and add/update alt attribute.
			$escaped_src = preg_quote( $src, '/' );
			$content = preg_replace(
				'/(<img[^>]*src=["\']' . $escaped_src . '["\'][^>]*?)(\s*\/?>)/i',
				'$1 alt="' . esc_attr( $alt_text ) . '"$2',
				$content,
			);

			wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => $content,
			) );
		}

		return $this->success( array(
			'applied'       => true,
			'attachment_id' => 0,
			'alt_text'      => $alt_text,
			'method'        => 'content_update',
		) );
	}

	/**
	 * Update review issue statuses (resolved/ignored).
	 *
	 * @since 1.0.7
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_review_issues( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( ! $this->can_edit_post( $post_id ) ) {
			return $this->error( 'forbidden', __( 'You do not have permission to edit this post.', 'scalyn-qa-assistant' ), 403 );
		}

		$saved = get_post_meta( $post_id, '_scalyn_qa_content_review', true );

		if ( ! is_array( $saved ) ) {
			return $this->error( 'no_review', __( 'No review data found for this post.', 'scalyn-qa-assistant' ), 404 );
		}

		$params = $request->get_json_params();
		$issues = $params['issues'] ?? null;

		if ( ! is_array( $issues ) ) {
			return $this->error( 'invalid_issues', __( 'Issues array is required.', 'scalyn-qa-assistant' ), 400 );
		}

		$saved['issues'] = $issues;
		update_post_meta( $post_id, '_scalyn_qa_content_review', $saved );

		return $this->success( array( 'updated' => true ) );
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
