<?php

namespace Osimatic\Bank;

/**
 * Enumeration of delivery types for orders.
 * Represents the various ways ordered products can be delivered to or collected by the customer.
 */
enum DeliveryType: string
{
	/** Standard home/address delivery */
	case STANDARD = 'standard';

	/** Express home/address delivery */
	case EXPRESS = 'express';

	/** Pickup directly at the sender company's premises */
	case SENDER_PICKUP = 'sender_pickup';

	/** Pickup at a relay point (parcel locker or partner shop network) */
	case RELAY_PICKUP = 'relay_pickup';

	/**
	 * Parses a delivery type string into the corresponding enum value.
	 * Case-insensitive parsing.
	 * @param string|null $deliveryType The type string to parse (e.g., 'standard', 'express', 'sender_pickup', 'relay_pickup')
	 * @return self|null The corresponding DeliveryType enum, or null if input is null or invalid
	 */
	public static function parse(?string $deliveryType): ?self
	{
		if (null === $deliveryType) {
			return null;
		}

		$deliveryType = mb_strtolower($deliveryType);
		return self::tryFrom($deliveryType);
	}
}