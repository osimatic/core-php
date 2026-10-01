<?php

namespace Osimatic\Invoice;

/**
 * The supplier's VAT regime sent to Chorus Pro in the "typeTva" field of the SAISIE_API payload.
 * It is a status of the supplier (not of an invoice line), hence it is configured on the ChorusProClient rather than deduced from the VAT rates.
 * The values must be verified against the PISTE API swagger before going to production.
 * @link https://communaute.chorus-pro.gouv.fr/submit-invoice/?lang=en Chorus Pro "Submit invoice" documentation
 */
enum ChorusProVatType: string
{
	// VAT due on invoicing (default regime)
	case VAT_ON_DEBIT = 'TVA_SUR_DEBIT';

	// Supplier not subject to VAT ("franchise en base de TVA")
	case VAT_FRANCHISE = 'FRANCHISE_EN_BASE';
}