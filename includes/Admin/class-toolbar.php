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

		if ( null === $scan_result ) {
			$this->add_unscanned_node( $wp_admin_bar, $post_id );
			return;
		}

		$score       = $scan_result->scores->overall;
		$status      = $scan_result->scores->status;
		$issue_count = $scan_result->get_issue_count();

		// Determine badge color class.
		$color_class = $this->get_color_class( $status );

		// Main parent node.
		$wp_admin_bar->add_node(
			array(
				'id'    => 'scalyn-qa-score',
				'title' => sprintf(
					'<span class="scalyn-qa-toolbar-badge %s">%s</span> <span class="scalyn-qa-toolbar-label">QA: %d%%</span>',
					esc_attr( $color_class ),
					esc_html( $this->get_status_icon( $status ) ),
					$score,
				),
				'href'  => admin_url( 'admin.php?page=' . Admin_Menu::PAGE_SLUGS['audits'] . '&post_id=' . $post_id ),
				'meta'  => array(
					'class' => 'scalyn-qa-toolbar-node',
					'title' => sprintf(
						/* translators: 1: Score percentage, 2: Number of issues. */
						__( 'QA Score: %1$d%% — %2$d issues', 'scalyn-qa-assistant' ),
						$score,
						$issue_count,
					),
				),
			),
		);

		// Child node: Score breakdown.
		$wp_admin_bar->add_node(
			array(
				'parent' => 'scalyn-qa-score',
				'id'     => 'scalyn-qa-score-details',
				'title'  => sprintf(
					'%s: %d%% | %s: %d%% | %s: %d%%',
					__( 'SEO', 'scalyn-qa-assistant' ),
					$scan_result->scores->seo,
					__( 'Content', 'scalyn-qa-assistant' ),
					$scan_result->scores->content,
					__( 'Func', 'scalyn-qa-assistant' ),
					$scan_result->scores->functionality,
				),
				'href'   => admin_url( 'admin.php?page=' . Admin_Menu::PAGE_SLUGS['audits'] . '&post_id=' . $post_id ),
			),
		);

		// Child node: Issue count.
		if ( $issue_count > 0 ) {
			$wp_admin_bar->add_node(
				array(
					'parent' => 'scalyn-qa-score',
					'id'     => 'scalyn-qa-issues',
					'title'  => sprintf(
						/* translators: %d: Number of issues. */
						_n( '%d issue found', '%d issues found', $issue_count, 'scalyn-qa-assistant' ),
						$issue_count,
					),
					'href'   => admin_url( 'admin.php?page=' . Admin_Menu::PAGE_SLUGS['audits'] . '&post_id=' . $post_id ),
				),
			);
		}

		// Child node: Rescan link.
		$wp_admin_bar->add_node(
			array(
				'parent' => 'scalyn-qa-score',
				'id'     => 'scalyn-qa-rescan',
				'title'  => __( 'Rescan Page', 'scalyn-qa-assistant' ),
				'href'   => '#',
				'meta'   => array(
					'class'    => 'scalyn-qa-rescan-link',
					'data-post-id' => $post_id,
					'onclick'  => 'return false;',
				),
			),
		);
	}

	/**
	 * Add a toolbar node for posts that haven't been scanned yet.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar The WordPress admin bar instance.
	 * @param int           $post_id      The post ID.
	 */
	private function add_unscanned_node( \WP_Admin_Bar $wp_admin_bar, int $post_id ): void {
		$wp_admin_bar->add_node(
			array(
				'id'    => 'scalyn-qa-score',
				'title' => sprintf(
					'<span class="scalyn-qa-toolbar-badge scalyn-qa-badge--gray">?</span> <span class="scalyn-qa-toolbar-label">%s</span>',
					esc_html__( 'QA: Not Scanned', 'scalyn-qa-assistant' ),
				),
				'href'  => '#',
				'meta'  => array(
					'class' => 'scalyn-qa-toolbar-node scalyn-qa-unscanned',
					'title' => __( 'This page has not been scanned yet.', 'scalyn-qa-assistant' ),
				),
			),
		);

		// Child node: Scan now link.
		$wp_admin_bar->add_node(
			array(
				'parent' => 'scalyn-qa-score',
				'id'     => 'scalyn-qa-rescan',
				'title'  => __( 'Scan Now', 'scalyn-qa-assistant' ),
				'href'   => '#',
				'meta'   => array(
					'class'    => 'scalyn-qa-rescan-link',
					'data-post-id' => $post_id,
					'onclick'  => 'return false;',
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
