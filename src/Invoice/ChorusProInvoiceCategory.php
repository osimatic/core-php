<?php

namespace Osimatic\Invoice;

/**
 * The Chorus Pro invoicing structure category, as configured for a given recipient structure on the Chorus Pro portal. Determines which references are mandatory on submitted invoices.
 * @link https://communaute.chorus-pro.gouv.fr Chorus Pro documentation
 */
enum ChorusProInvoiceCategory: string
{
	// Engagement/purchase order number is mandatory on submitted invoices
	case TYPE_1 = 'TYPE_1';

	// Service code is mandatory, engagement number optional
	case TYPE_2 = 'TYPE_2';

	// No engagement number nor service code required
	case TYPE_3 = 'TYPE_3';
}