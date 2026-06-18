<?php
/**
 * Toolbar.
 *
 * Adds QA score information to the WordPress admin bar on the front end.
 *
 * @package Scalyn\QA\Admin
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Admin;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Traits\Singleton;
use Scalyn\QA\Traits\Has_Hooks;
use Scalyn\QA\Models\Scan_Result;

/**
 * Class Toolbar
 *
 * Adds a QA score badge, issue count, and rescan link to the WordPress
 * admin bar when viewing a post/page on the front end.
 *
 * @since 1.0.0
 */
final class Toolbar {

	use Singleton;
	use Has_Hooks;

	/**
	 * Register all WordPress hooks.
	 */
	protected function register_hooks(): void {
		add_action( 'admin_bar_menu', array( $this, 'add_toolbar_node' ), 100 );
	}

	/**
	 * Add the Scalyn QA node to the admin bar.
	 *
	 * Only displayed on the front end for users who can edit posts, and only
	 * when viewing a singular post/page that has been scanned.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar The WordPress admin bar instance.
	 */
	public function add_toolbar_node( \WP_Admin_Bar $wp_admin_bar ): void {
		// Only show on front-end pages when the admin bar is visible.
		if ( ! is_admin_bar_showing() || is_admin() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// Only display on singular views.
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();

		if ( 0 === $post_id ) {
			return;
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		// Check if the toolbar is enabled in settings.
		$settings = get_option( 'scalyn_qa_settings', array() );

		if ( is_array( $settings ) && isset( $settings['enable_toolbar'] ) && false === $settings['enable_toolbar'] ) {
			return;
		}

		// Load score data for the current post.
		$scan_result = Scan_Result::load( $post_id );
		$score       = null !== $scan_result ? $scan_result->scores->overall : 0;
		$status      = null !== $scan_result ? $scan_result->scores->status : 'gray';
		$color_class = $this->get_color_class( $status );
		$icon        = $this->get_status_icon( $status );

		// Single toolbar node: QA Inspector toggle with score badge.
		$wp_admin_bar->add_node(
			array(
				'id'    => 'scalyn-qa-score',
				'title' => sprintf(
					'<span class="scalyn-qa-toolbar-badge %s">%s</span> <span class="dashicons dashicons-visibility" style="font-size:16px;width:16px;height:16px;line-height:16px;vertical-align:middle;margin-right:2px;"></span><span class="scalyn-qa-toolbar-label">%s</span>',
					esc_attr( $color_class ),
					esc_html( $icon ),
					esc_html__( 'QA Inspector', 'scalyn-qa-assistant' ),
				),
				'href'  => '#',
				'meta'  => array(
					'class'   => 'scalyn-qa-toolbar-node scalyn-qa-inspector-toggle',
					'title'   => __( 'Toggle QA Inspector', 'scalyn-qa-assistant' ),
					'onclick' => 'return false;',
				),
			),
		);
	}


	/**
	 * Get the CSS class name for a given status.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status The score status (green, yellow, red).
	 * @return string CSS class name.
	 */
	private function get_color_class( string $status ): string {
		return match ( $status ) {
			'green'  => 'scalyn-qa-badge--green',
			'yellow' => 'scalyn-qa-badge--yellow',
			'red'    => 'scalyn-qa-badge--red',
			default  => 'scalyn-qa-badge--gray',
		};
	}

	/**
	 * Get a status icon character for the toolbar badge.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status The score status (green, yellow, red).
	 * @return string Unicode icon character.
	 */
	private function get_status_icon( string $status ): string {
		return match ( $status ) {
			'green'  => "\xe2\x9c\x93", // checkmark
			'yellow' => '!',
			'red'    => "\xc3\x97", // multiplication sign (x)
			default  => '?',
		};
	}
}
