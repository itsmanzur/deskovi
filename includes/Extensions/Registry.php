<?php
/**
 * Read-only accessors for extension registries that add-ons contribute to.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Extensions;

defined( 'ABSPATH' ) || exit;

/**
 * Registries are plain filters evaluated lazily at the point of use, so an
 * add-on never has to worry about registration timing. Nothing is stored here.
 */
final class Registry {

	/**
	 * Inbound ticket sources (for example a mailbox poller). Each entry is
	 * keyed by a stable source id; the value is an add-on defined definition
	 * array. Free ships none.
	 *
	 * @return array<string, mixed>
	 */
	public static function inbound_sources(): array {
		/**
		 * Filters the registered inbound ticket sources.
		 *
		 * @param array<string, mixed> $sources Source id => definition.
		 */
		return (array) apply_filters( 'itsdesk_inbound_sources', array() );
	}
}
