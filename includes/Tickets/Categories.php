<?php
/**
 * Ticket categories.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Tickets;

defined( 'ABSPATH' ) || exit;

/**
 * Allowlisted categories for ticket forms.
 */
final class Categories {

	/**
	 * Category map id => label.
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		return array(
			'order'    => __( 'Order question', 'deskovi' ),
			'shipping' => __( 'Shipping / tracking', 'deskovi' ),
			'refund'   => __( 'Refund / return', 'deskovi' ),
			'cancel'   => __( 'Cancel request', 'deskovi' ),
			'product'  => __( 'Product question', 'deskovi' ),
			'other'    => __( 'Other', 'deskovi' ),
		);
	}

	/**
	 * Whether category is valid.
	 */
	public static function is_valid( string $category ): bool {
		return array_key_exists( $category, self::all() );
	}
}
