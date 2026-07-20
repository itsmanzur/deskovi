<?php
/**
 * Signed HTTP client for Deskovi SaaS.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Connection;

/**
 * JSON POST helper with Deskovi signature headers.
 */
final class SignedHttpClient {

	private RequestSigner $signer;
	private SiteIdentity $identity;

	public function __construct( ?RequestSigner $signer = null, ?SiteIdentity $identity = null ) {
		$this->signer   = $signer ?? new RequestSigner();
		$this->identity = $identity ?? new SiteIdentity();
	}

	/**
	 * Unsigned JSON POST (connect exchange).
	 *
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>|\WP_Error
	 */
	public function post_json( string $url, array $body ) {
		$valid = SaasConfig::validate_for_request();
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$raw = wp_json_encode( $body );
		if ( false === $raw ) {
			return new \WP_Error(
				'itsdesk_json_encode',
				__( 'Could not encode request body.', 'deskovi' ),
				array( 'status' => 500 )
			);
		}

		$response = $this->remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => $raw,
			)
		);

		return $this->decode_response( $response );
	}

	/**
	 * Signed JSON POST.
	 *
	 * @param array<string, mixed> $connection Public connection state (needs site_uuid).
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>|\WP_Error
	 */
	public function post_signed( array $connection, string $path, array $body, string $idempotency_key ) {
		$valid = SaasConfig::validate_for_request();
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$site_uuid = (string) ( $connection['site_uuid'] ?? '' );
		$url       = SaasConfig::api_v1() . $path;
		$api_path  = '/api/v1' . $path;

		$raw = wp_json_encode( $body );
		if ( false === $raw ) {
			return new \WP_Error(
				'itsdesk_json_encode',
				__( 'Could not encode request body.', 'deskovi' ),
				array( 'status' => 500 )
			);
		}

		$signed = $this->signer->sign(
			$this->identity->get(),
			$site_uuid,
			'POST',
			$api_path,
			$raw,
			$idempotency_key
		);
		if ( is_wp_error( $signed ) ) {
			return $signed;
		}

		$response = $this->remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'              => 'application/json',
					'Accept'                    => 'application/json',
					'X-Deskovi-Timestamp'       => $signed['timestamp'],
					'X-Deskovi-Nonce'           => $signed['nonce'],
					'X-Deskovi-Body-Hash'       => $signed['body_hash'],
					'X-Deskovi-Site-Id'         => $signed['site_id'],
					'X-Deskovi-Signature'       => $signed['signature'],
					'X-Deskovi-Idempotency-Key' => $signed['idempotency_key'],
				),
				'body'    => $raw,
			)
		);

		return $this->decode_response( $response );
	}

	/**
	 * Prefer wp_safe_remote_post; fall back when insecure local URLs are explicitly allowed.
	 *
	 * @param array<string, mixed> $args
	 * @return array|\WP_Error
	 */
	private function remote_post( string $url, array $args ) {
		if ( SaasConfig::allows_insecure_url() ) {
			return wp_remote_post( $url, $args );
		}

		return wp_safe_remote_post( $url, $args );
	}

	/**
	 * @param array|\WP_Error $response
	 * @return array<string, mixed>|\WP_Error
	 */
	private function decode_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'itsdesk_http_error',
				$response->get_error_message(),
				array( 'status' => 502 )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			$data = array( 'message' => $body !== '' ? $body : __( 'Empty SaaS response.', 'deskovi' ) );
		}

		if ( $code < 200 || $code >= 300 ) {
			$message = isset( $data['message'] ) ? (string) $data['message'] : __( 'SaaS request failed.', 'deskovi' );
			return new \WP_Error(
				'itsdesk_saas_error',
				$message,
				array(
					'status' => $code >= 400 ? $code : 502,
					'body'   => $data,
				)
			);
		}

		return $data;
	}
}
