<?php
/**
 * Storefront widget asset loading.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Widget;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues lazy launcher only when enabled and not on cart/checkout.
 */
final class Frontend {

	public const SCRIPT_HANDLE = 'itsdesk-widget';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
	}

	/**
	 * Whether the storefront loader may load.
	 */
	public function should_load(): bool {
		$settings = ( new Settings() )->get();
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return false;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return false;
		}

		$script = ITSDESK_PATH . 'assets/widget/loader.js';
		return is_readable( $script );
	}

	/**
	 * Enqueue loader + localize bootstrap config.
	 */
	public function enqueue(): void {
		if ( ! $this->should_load() ) {
			return;
		}

		$asset_file = ITSDESK_PATH . 'assets/widget/loader.asset.php';
		$asset      = is_readable( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array(),
				'version'      => ITSDESK_VERSION,
			);

		$deps = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] )
			? $asset['dependencies']
			: array();
		// Standalone Web Component — drop WP script deps if any leaked from build.
		$deps = array_values(
			array_filter(
				$deps,
				static function ( $dep ) {
					return is_string( $dep ) && 0 !== strpos( $dep, 'wp-' ) && 'react' !== $dep && 'react-dom' !== $dep && 'react-jsx-runtime' !== $dep;
				}
			)
		);

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			ITSDESK_URL . 'assets/widget/loader.js',
			$deps,
			$asset['version'] ?? ITSDESK_VERSION,
			true
		);

		$settings  = ( new Settings() )->get();
		$logged_in = is_user_logged_in();
		$guest     = ( new \Itsdesk\Auth\GuestSession() )->current();

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'itsdeskWidget',
			array(
				'restRoot'            => esc_url_raw( rest_url() ),
				'nonce'               => $logged_in ? wp_create_nonce( 'wp_rest' ) : '',
				'loggedIn'            => $logged_in,
				'guestAuthenticated'  => null !== $guest,
				'guestOrderId'        => $guest ? (int) $guest['order_id'] : 0,
				'loginUrl'            => wp_login_url(),
				'placement'           => (string) ( $settings['placement'] ?? 'bottom-right' ),
				'theme'               => (string) ( $settings['theme'] ?? 'system' ),
				'launcherLabel'       => (string) ( $settings['launcher_label'] ?? '' ),
				'assetUrl'            => esc_url_raw( ITSDESK_URL . 'assets/widget/' ),
				'i18n'                => array(
					'support'         => __( 'Support', 'deskovi' ),
					'close'           => __( 'Close', 'deskovi' ),
					'tickets'         => __( 'Your tickets', 'deskovi' ),
					'newTicket'       => __( 'New ticket', 'deskovi' ),
					'subject'         => __( 'Subject', 'deskovi' ),
					'message'         => __( 'Message', 'deskovi' ),
					'category'        => __( 'Category', 'deskovi' ),
					'order'           => __( 'Related order', 'deskovi' ),
					'orderNone'       => __( 'No order', 'deskovi' ),
					'send'            => __( 'Send', 'deskovi' ),
					'reply'           => __( 'Reply', 'deskovi' ),
					'back'            => __( 'Back', 'deskovi' ),
					'loading'         => __( 'Loading…', 'deskovi' ),
					'empty'           => __( 'No tickets yet.', 'deskovi' ),
					'loginTitle'      => __( 'Get support', 'deskovi' ),
					'loginBody'       => __( 'Log in, or continue with your order number and billing email.', 'deskovi' ),
					'loginAction'     => __( 'Log in', 'deskovi' ),
					'guestAction'     => __( 'Continue with order', 'deskovi' ),
					'guestOrder'      => __( 'Order number', 'deskovi' ),
					'guestEmail'      => __( 'Billing email', 'deskovi' ),
					'guestCode'       => __( 'Verification code', 'deskovi' ),
					'guestSendCode'   => __( 'Send code', 'deskovi' ),
					'guestConfirm'    => __( 'Verify', 'deskovi' ),
					'guestSent'       => __( 'Check your email for a one-time code.', 'deskovi' ),
					'error'           => __( 'Something went wrong. Please try again.', 'deskovi' ),
					'create'          => __( 'Create ticket', 'deskovi' ),
					'open'            => __( 'Open', 'deskovi' ),
					'unread'          => __( 'Unread', 'deskovi' ),
					'orders'          => __( 'Your orders', 'deskovi' ),
					'orderDetail'     => __( 'Order details', 'deskovi' ),
					'tracking'        => __( 'Tracking', 'deskovi' ),
					'noTracking'      => __( 'No tracking number yet.', 'deskovi' ),
					'viewOrder'       => __( 'View order / invoice', 'deskovi' ),
					'requestReturn'   => __( 'Request return', 'deskovi' ),
					'requestCancel'   => __( 'Request cancel', 'deskovi' ),
					'emptyOrders'     => __( 'No recent orders.', 'deskovi' ),
				),
			)
		);
	}
}
