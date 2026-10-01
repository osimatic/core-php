<?php

namespace Osimatic\Invoice;

/**
 * The VAT category of an invoice line, as defined by the UNTDID 5305 code list used by EN 16931 (Factur-X / ZUGFeRD / UBL).
 * The VAT rate alone is not enough to describe the tax treatment of a line: a 0% rate can be a zero-rated operation, an exemption, a reverse charge, an export, etc.
 * @link https://docs.peppol.eu/poacc/billing/3.0/codelist/UNCL5305/ UNTDID 5305 duty or tax or fee category code
 */
enum VatCategory: string
{
	// Standard rate (rate greater than 0%)
	case STANDARD = 'S';

	// Zero-rated goods or services (taxable operation at 0%)
	case ZERO_RATED = 'Z';

	// Exempt from VAT (e.g. exempted by law, or seller under the "franchise en base" regime)
	case EXEMPT = 'E';

	// VAT reverse charge (autoliquidation)
	case REVERSE_CHARGE = 'AE';

	// Exempt VAT for intra-community supply of goods
	case INTRA_COMMUNITY = 'K';

	// Free export item, VAT not charged
	case EXPORT = 'G';

	// Services outside the scope of VAT
	case NOT_SUBJECT = 'O';
}