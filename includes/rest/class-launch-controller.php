<?php
/**
 * Launch Controller.
 *
 * REST endpoints for pre-launch QA checks.
 *
 * @package Scalyn\QA\Rest
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\Rest;

defined( 'ABSPATH' ) || exit;

use Scalyn\QA\Launch\Launch_Checker;
use Scalyn\QA\Models\Check_Item;

/**
 * Class Launch_Controller
 *
 * Handles running and retrieving pre-launch site checks.
 *
 * @since 1.0.0
 */
class Launch_Controller extends REST_Controller {

	/**
	 * Option key for stored launch results.
	 *
	 * @var string
	 */
	private const RESULTS_OPTION = 'scalyn_qa_launch_results';

	/**
	 * Option key for last launch scan timestamp.
	 *
	 * @var string
	 */
	private const LAST_SCAN_OPTION = 'scalyn_qa_launch_last_scan';

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
			'/launch',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_launch_results' ),
				'permission_callback' => array( $this, 'can_manage' ),
			),
		);

		register_rest_route(
			$this->namespace,
			'/launch/scan',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_launch_scan' ),
				'permission_callback' => array( $this, 'can_manage' ),
			),
		);
	}

	/**
	 * Get stored launch check results.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_launch_results( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$results_data = get_option( self::RESULTS_OPTION, array() );
		$last_scan    = get_option( self::LAST_SCAN_OPTION, '' );

		if ( empty( $results_data ) || ! is_array( $results_data ) ) {
			return $this->error(
				'launch_not_scanned',
				__( 'No launch check results found. Run a scan first.', 'scalyn-qa-assistant' ),
				404,
			);
		}

		// Calculate summary counts.
		$summary = array(
			'pass'    => 0,
			'warning' => 0,
			'fail'    => 0,
			'total'   => count( $results_data ),
		);

		foreach ( $results_data as $item_data ) {
			$status = $item_data['status'] ?? '';

			if ( isset( $summary[ $status ] ) ) {
				++$summary[ $status ];
			}
		}

		// Calculate a simple score percentage.
		$total = $summary['total'];
		$score = 0;

		if ( $total > 0 ) {
			$score = (int) round( ( $summary['pass'] / $total ) * 100 );
		}

		return $this->success(
			array(
				'checks'    => $results_data,
				'summary'   => $summary,
				'score'     => $score,
				'scanned_at' => is_numeric( $last_scan )
					? gmdate( 'c', (int) $last_scan )
					: '',
			),
		);
	}

	/**
	 * Run launch checks.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public function run_launch_scan( \WP_REST_Request $request ): \WP_REST_Response {
		$checker = new Launch_Checker();
		$checks  = $checker->run_checks();

		$results_data = array_map(
			static fn( Check_Item $item ): array => $item->to_array(),
			$checks,
		);

		// Calculate summary counts.
		$summary = array(
			'pass'    => 0,
			'warning' => 0,
			'fail'    => 0,
			'total'   => count( $results_data ),
		);

		foreach ( $checks as $item ) {
			if ( isset( $summary[ $item->status ] ) ) {
				++$summary[ $item->status ];
			}
		}

		$score = 0;

		if ( $summary['total'] > 0 ) {
			$score = (int) round( ( $summary['pass'] / $summary['total'] ) * 100 );
		}

		return $this->success(
			array(
				'checks'     => $results_data,
				'summary'    => $summary,
				'score'      => $score,
				'scanned_at' => gmdate( 'c' ),
			),
		);
	}
}
