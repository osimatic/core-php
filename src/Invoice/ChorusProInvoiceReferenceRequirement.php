<?php

namespace Osimatic\Invoice;

/**
 * The reference(s) required on invoices submitted to Chorus Pro, as configured for a given recipient structure on the Chorus Pro portal.
 * @link https://communaute.chorus-pro.gouv.fr Chorus Pro documentation
 */
enum ChorusProInvoiceReferenceRequirement: string
{
	// Engagement/purchase order number is mandatory on submitted invoices
	case ENGAGEMENT_REQUIRED = 'ENGAGEMENT_REQUIRED';

	// Service code is mandatory, engagement number optional
	case SERVICE_CODE_REQUIRED = 'SERVICE_CODE_REQUIRED';

	// No engagement number nor service code required
	case NONE = 'NONE';
}