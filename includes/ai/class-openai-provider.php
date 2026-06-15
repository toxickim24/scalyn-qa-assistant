<?php
/**
 * OpenAI Provider.
 *
 * AI provider implementation for the OpenAI API (GPT models).
 *
 * @package Scalyn\QA\AI
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Scalyn\QA\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class OpenAI_Provider
 *
 * Implements the AI_Provider contract for OpenAI's Chat Completions API.
 *
 * @since 1.0.0
 */
class OpenAI_Provider extends AI_Provider {

	/**
	 * OpenAI Chat Completions endpoint.
	 *
	 * @var string
	 */
	private const API_URL = 'https://api.openai.com/v1/chat/completions';

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'OpenAI';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'openai';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_models(): array {
		return array(
			'gpt-4o-mini'    => 'GPT-4o Mini',
			'gpt-4o'         => 'GPT-4o',
			'gpt-4.1-mini'   => 'GPT-4.1 Mini',
			'gpt-4.1-nano'   => 'GPT-4.1 Nano',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function generate( string $prompt, int $max_tokens = 300 ): string {
		$model = $this->model ?: 'gpt-4o-mini';

		$body = wp_json_encode(
			array(
				'model'       => $model,
				'messages'    => array(
					array(
						'role'    => 'system',
						'content' => 'You are an expert SEO copywriter and editor. Follow the instructions precisely and respond only with the requested output.',
					),
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				),
				'temperature' => 0.7,
				'max_tokens'  => $max_tokens,
			)
		);

		if ( false === $body ) {
			throw new \RuntimeException( 'Failed to encode request body for OpenAI.' );
		}

		$response = wp_remote_post(
			self::API_URL,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => $body,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException(
				'OpenAI request failed: ' . $response->get_error_message()
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( $status < 200 || $status >= 300 ) {
			$error_message = $data['error']['message'] ?? 'Unknown error';
			throw new \RuntimeException(
				sprintf( 'OpenAI API error (%d): %s', $status, $error_message )
			);
		}

		$content = $data['choices'][0]['message']['content'] ?? '';

		if ( ! is_string( $content ) || '' === $content ) {
			throw new \RuntimeException( 'OpenAI returned an empty response.' );
		}

		return trim( $content );
	}

	/**
	 * {@inheritDoc}
	 */
	public function test(): array {
		try {
			$result = $this->generate( 'Say "Connection successful" and nothing else.' );

			return array(
				'success' => true,
				'message' => sprintf(
					/* translators: %s: The AI model response text. */
					__( 'OpenAI connection successful. Response: %s', 'scalyn-qa-assistant' ),
					mb_substr( $result, 0, 100 )
				),
			);
		} catch ( \Throwable $e ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: The error message. */
					__( 'OpenAI connection failed: %s', 'scalyn-qa-assistant' ),
					$e->getMessage()
				),
			);
		}
	}
}
