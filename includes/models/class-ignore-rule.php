<?php
/**
 * Ignore_Rule model.
 *
 * Represents a rule for suppressing specific QA checks.
 *
 * @package Scalyn\QA\Models
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Class Ignore_Rule
 *
 * Value object and storage manager for QA check ignore/suppress rules.
 *
 * @since 1.0.0
 */
class Ignore_Rule {

	/**
	 * Option key for global ignore rules.
	 *
	 * @var string
	 */
	private const OPTION_GLOBAL = 'scalyn_qa_global_ignores';

	/**
	 * Post meta key for per-post ignore rules.
	 *
	 * @var string
	 */
	private const META_IGNORES = '_scalyn_qa_ignore_rules';

	/**
	 * Valid rule types.
	 *
	 * @var string[]
	 */
	private const VALID_TYPES = array( 'check', 'page', 'global' );

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string  $id         Unique rule ID.
	 * @param string  $type       Rule type: 'check', 'page', or 'global'.
	 * @param string  $check_id   The check being ignored (e.g., 'meta_title_exists').
	 * @param ?int    $post_id    Post ID, or null for global rules.
	 * @param string  $reason     User-provided reason for ignoring.
	 * @param string  $created_by Username of the user who created the rule.
	 * @param string  $created_at ISO 8601 datetime of when the rule was created.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $type,
		public readonly string $check_id,
		public readonly ?int $post_id,
		public readonly string $reason,
		public readonly string $created_by,
		public readonly string $created_at,
	) {
	}

	/**
	 * Creates an Ignore_Rule from an associative array.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Associative array of rule data.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$type = $data['type'] ?? 'check';

		if ( ! in_array( $type, self::VALID_TYPES, true ) ) {
			$type = 'check';
		}

		return new self(
			id:         sanitize_key( $data['id'] ?? wp_generate_uuid4() ),
			type:       $type,
			check_id:   sanitize_key( $data['check_id'] ?? '' ),
			post_id:    isset( $data['post_id'] ) ? absint( $data['post_id'] ) : null,
			reason:     sanitize_text_field( $data['reason'] ?? '' ),
			created_by: sanitize_text_field( $data['created_by'] ?? '' ),
			created_at: sanitize_text_field( $data['created_at'] ?? '' ),
		);
	}

	/**
	 * Converts the Ignore_Rule to an associative array.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'id'         => $this->id,
			'type'       => $this->type,
			'check_id'   => $this->check_id,
			'post_id'    => $this->post_id,
			'reason'     => $this->reason,
			'created_by' => $this->created_by,
			'created_at' => $this->created_at,
		);
	}

	/**
	 * Checks whether this rule applies to a given check and post.
	 *
	 * @since 1.0.0
	 *
	 * @param string $check_id The check identifier to match against.
	 * @param int    $post_id  The post ID to match against.
	 * @return bool True if this rule suppresses the given check on the given post.
	 */
	public function matches( string $check_id, int $post_id ): bool {
		if ( $this->check_id !== $check_id ) {
			return false;
		}

		return match ( $this->type ) {
			'global' => true,
			'check'  => null === $this->post_id || $this->post_id === $post_id,
			'page'   => $this->post_id === $post_id,
			default  => false,
		};
	}

	/**
	 * Retrieves all ignore rules (global and per-post).
	 *
	 * @since 1.0.0
	 *
	 * @return self[]
	 */
	public static function get_all(): array {
		$rules = array();

		// Load global rules.
		$global_data = get_option( self::OPTION_GLOBAL, array() );

		if ( is_array( $global_data ) ) {
			foreach ( $global_data as $rule_data ) {
				if ( is_array( $rule_data ) ) {
					$rules[] = self::from_array( $rule_data );
				}
			}
		}

		return $rules;
	}

	/**
	 * Retrieves all ignore rules that apply to a specific post.
	 *
	 * This includes both global rules and post-specific rules.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The WordPress post ID.
	 * @return self[]
	 */
	public static function get_for_post( int $post_id ): array {
		$rules = array();

		// Load global rules.
		$global_data = get_option( self::OPTION_GLOBAL, array() );

		if ( is_array( $global_data ) ) {
			foreach ( $global_data as $rule_data ) {
				if ( is_array( $rule_data ) ) {
					$rules[] = self::from_array( $rule_data );
				}
			}
		}

		// Load post-specific rules.
		$post_data = get_post_meta( $post_id, self::META_IGNORES, true );

		if ( is_array( $post_data ) ) {
			foreach ( $post_data as $rule_data ) {
				if ( is_array( $rule_data ) ) {
					$rules[] = self::from_array( $rule_data );
				}
			}
		}

		return $rules;
	}

	/**
	 * Adds a new ignore rule to storage.
	 *
	 * Global rules are stored in wp_options, post-specific rules in wp_postmeta.
	 *
	 * @since 1.0.0
	 *
	 * @param self $rule The ignore rule to add.
	 * @return void
	 */
	public static function add( self $rule ): void {
		if ( 'global' === $rule->type ) {
			$global_data   = get_option( self::OPTION_GLOBAL, array() );
			$global_data   = is_array( $global_data ) ? $global_data : array();
			$global_data[] = $rule->to_array();

			update_option( self::OPTION_GLOBAL, $global_data, false );
		} elseif ( null !== $rule->post_id ) {
			$post_data   = get_post_meta( $rule->post_id, self::META_IGNORES, true );
			$post_data   = is_array( $post_data ) ? $post_data : array();
			$post_data[] = $rule->to_array();

			update_post_meta( $rule->post_id, self::META_IGNORES, $post_data );
		}
	}

	/**
	 * Removes an ignore rule by its ID.
	 *
	 * Searches both global rules and all posts with ignore rules.
	 *
	 * @since 1.0.0
	 *
	 * @param string $rule_id The unique rule ID to remove.
	 * @return bool True if the rule was found and removed, false otherwise.
	 */
	public static function remove( string $rule_id ): bool {
		// Try removing from global rules.
		$global_data = get_option( self::OPTION_GLOBAL, array() );

		if ( is_array( $global_data ) ) {
			$filtered = array_values(
				array_filter(
					$global_data,
					static fn( array $rule_data ): bool => ( $rule_data['id'] ?? '' ) !== $rule_id,
				),
			);

			if ( count( $filtered ) < count( $global_data ) ) {
				update_option( self::OPTION_GLOBAL, $filtered, false );
				return true;
			}
		}

		// Try removing from post meta by querying posts that have ignore rules.
		$post_ids = self::get_posts_with_ignore_rules();

		foreach ( $post_ids as $post_id ) {
			$post_data = get_post_meta( $post_id, self::META_IGNORES, true );

			if ( ! is_array( $post_data ) ) {
				continue;
			}

			$filtered = array_values(
				array_filter(
					$post_data,
					static fn( array $rule_data ): bool => ( $rule_data['id'] ?? '' ) !== $rule_id,
				),
			);

			if ( count( $filtered ) < count( $post_data ) ) {
				if ( empty( $filtered ) ) {
					delete_post_meta( $post_id, self::META_IGNORES );
				} else {
					update_post_meta( $post_id, self::META_IGNORES, $filtered );
				}

				return true;
			}
		}

		return false;
	}

	/**
	 * Checks whether a specific check is ignored for a given post.
	 *
	 * @since 1.0.0
	 *
	 * @param string $check_id The check identifier.
	 * @param int    $post_id  The WordPress post ID.
	 * @return bool True if the check is ignored for the given post.
	 */
	public static function is_ignored( string $check_id, int $post_id ): bool {
		$rules = self::get_for_post( $post_id );

		foreach ( $rules as $rule ) {
			if ( $rule->matches( $check_id, $post_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Retrieves post IDs that have ignore rules stored in post meta.
	 *
	 * @since 1.0.0
	 *
	 * @return int[]
	 */
	private static function get_posts_with_ignore_rules(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::META_IGNORES,
			),
		);

		return array_map( 'absint', $results );
	}
}
