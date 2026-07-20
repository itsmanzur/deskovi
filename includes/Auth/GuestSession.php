<?php
/**
 * Guest support session (order + email OTP).
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Auth;

use Itsdesk\Connection\ActivityLogger;

/**
 * Short-lived verified guest cookie for ticket bridge.
 */
final class GuestSession {

	public const COOKIE = 'itsdesk_guest';
	public const OTP_TTL = 600;
	public const SESSION_TTL = DAY_IN_SECONDS;
	public const RATE_MAX = 5;
	public const RATE_WINDOW = 900;

	/**
	 * Whether a valid guest session cookie is present.
	 */
	public function is_authenticated(): bool {
		return null !== $this->current();
	}

	/**
	 * Current session payload or null.
	 *
	 * @return array{email: string, order_id: int, exp: int}|null
	 */
	public function current(): ?array {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return null;
		}

		$raw = sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::COOKIE ] ) );
		$parts = explode( '.', $raw, 2 );
		if ( 2 !== count( $parts ) ) {
			return null;
		}

		[ $payload_b64, $sig ] = $parts;
		$expected = $this->sign( $payload_b64 );
		if ( ! hash_equals( $expected, $sig ) ) {
			return null;
		}

		$json = base64_decode( strtr( $payload_b64, '-_', '+/' ), true );
		if ( false === $json ) {
			return null;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		$email    = isset( $data['email'] ) ? sanitize_email( (string) $data['email'] ) : '';
		$order_id = isset( $data['order_id'] ) ? absint( $data['order_id'] ) : 0;
		$exp      = isset( $data['exp'] ) ? (int) $data['exp'] : 0;

		if ( '' === $email || $order_id <= 0 || $exp < time() ) {
			return null;
		}

		return array(
			'email'    => $email,
			'order_id' => $order_id,
			'exp'      => $exp,
		);
	}

	/**
	 * Start OTP verification.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function start( int $order_id, string $email ) {
		$email = sanitize_email( $email );
		if ( $order_id <= 0 || ! is_email( $email ) ) {
			return new \WP_Error(
				'itsdesk_guest_invalid',
				__( 'Order number and a valid billing email are required.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		$rate = $this->rate_limit_key( $email );
		$count = (int) get_transient( $rate );
		if ( $count >= self::RATE_MAX ) {
			return new \WP_Error(
				'itsdesk_guest_rate',
				__( 'Too many verification attempts. Try again later.', 'deskovi' ),
				array( 'status' => 429 )
			);
		}
		set_transient( $rate, $count + 1, self::RATE_WINDOW );

		if ( ! function_exists( 'wc_get_order' ) ) {
			return new \WP_Error(
				'itsdesk_guest_wc',
				__( 'WooCommerce is required.', 'deskovi' ),
				array( 'status' => 500 )
			);
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new \WP_Error(
				'itsdesk_guest_order',
				__( 'Order not found.', 'deskovi' ),
				array( 'status' => 404 )
			);
		}

		$billing = strtolower( (string) $order->get_billing_email() );
		if ( $billing !== strtolower( $email ) ) {
			return new \WP_Error(
				'itsdesk_guest_mismatch',
				__( 'Email does not match this order.', 'deskovi' ),
				array( 'status' => 403 )
			);
		}

		$code = (string) wp_rand( 100000, 999999 );
		set_transient(
			$this->otp_key( $order_id, $email ),
			array(
				'hash' => wp_hash_password( $code ),
				'email'=> $email,
				'order_id' => $order_id,
			),
			self::OTP_TTL
		);

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Your Deskovi support code', 'deskovi' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$body = sprintf(
			/* translators: %s: OTP code */
			__( "Your one-time support verification code is: %s\n\nIt expires in 10 minutes.", 'deskovi' ),
			$code
		);

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail
		$sent = wp_mail( $email, $subject, $body );

		( new ActivityLogger() )->log(
			'Guest',
			$sent
				? __( 'Guest OTP sent', 'deskovi' )
				: __( 'Guest OTP mail failed (check mail config)', 'deskovi' ),
			$sent ? 'OK' : 'Failed',
			array( 'order_id' => $order_id )
		);

		return array(
			'ok'         => true,
			'expires_in' => self::OTP_TTL,
			'mail_sent'  => (bool) $sent,
		);
	}

	/**
	 * Confirm OTP and set session cookie.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function confirm( int $order_id, string $email, string $code ) {
		$email = sanitize_email( $email );
		$code  = preg_replace( '/\D/', '', $code ) ?? '';
		if ( $order_id <= 0 || ! is_email( $email ) || strlen( $code ) < 4 ) {
			return new \WP_Error(
				'itsdesk_guest_invalid',
				__( 'Invalid verification request.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		$stored = get_transient( $this->otp_key( $order_id, $email ) );
		if ( ! is_array( $stored ) || empty( $stored['hash'] ) ) {
			return new \WP_Error(
				'itsdesk_guest_otp_expired',
				__( 'Verification code expired. Request a new one.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		if ( ! wp_check_password( $code, (string) $stored['hash'] ) ) {
			return new \WP_Error(
				'itsdesk_guest_otp_bad',
				__( 'Incorrect verification code.', 'deskovi' ),
				array( 'status' => 403 )
			);
		}

		delete_transient( $this->otp_key( $order_id, $email ) );

		$exp = time() + self::SESSION_TTL;
		$payload = array(
			'email'    => $email,
			'order_id' => $order_id,
			'exp'      => $exp,
		);
		$b64 = rtrim( strtr( base64_encode( wp_json_encode( $payload ) ), '+/', '-_' ), '=' );
		$token = $b64 . '.' . $this->sign( $b64 );

		$secure = is_ssl();
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
		setcookie(
			self::COOKIE,
			$token,
			array(
				'expires'  => $exp,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => $secure,
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ self::COOKIE ] = $token;

		( new ActivityLogger() )->log(
			'Guest',
			__( 'Guest session verified', 'deskovi' ),
			'OK',
			array( 'order_id' => $order_id )
		);

		return array(
			'ok'       => true,
			'email'    => $email,
			'order_id' => $order_id,
			'expires'  => gmdate( 'c', $exp ),
		);
	}

	/**
	 * Clear guest cookie.
	 */
	public function clear(): void {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
		setcookie(
			self::COOKIE,
			'',
			array(
				'expires'  => time() - YEAR_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		unset( $_COOKIE[ self::COOKIE ] );
	}

	private function otp_key( int $order_id, string $email ): string {
		return 'itsdesk_guest_otp_' . md5( strtolower( $email ) . '|' . $order_id );
	}

	private function rate_limit_key( string $email ): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		return 'itsdesk_guest_rl_' . md5( $ip . '|' . strtolower( $email ) );
	}

	private function sign( string $payload_b64 ): string {
		return hash_hmac( 'sha256', $payload_b64, wp_salt( 'auth' ) );
	}
}
