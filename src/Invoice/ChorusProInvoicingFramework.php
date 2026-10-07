<?php

namespace Osimatic\Invoice;

/**
 * The Chorus Pro invoicing framework ("cadreDeFacturation"/"codeCadreFacturation"), as returned by the "recupererCadreFacturation" method for a FACTURE payment request.
 * @link https://communaute.chorus-pro.gouv.fr Chorus Pro documentation
 */
enum ChorusProInvoicingFramework: string
{
	// Standard invoice issued directly by the supplier
	case SUPPLIER_INVOICE = 'A1_FACTURE_FOURNISSEUR';

	// Supplier invoice that has already been paid
	case SUPPLIER_INVOICE_ALREADY_PAID = 'A2_FACTURE_FOURNISSEUR_DEJA_PAYEE';

	// Invoice issued by a subcontractor. Requires the validator ("codeStructureValideur") to be set.
	case SUBCONTRACTOR_INVOICE = 'A9_FACTURE_SOUSTRAITANT';

	// Invoice issued by a co-contractor. Requires the validator ("codeStructureValideur") to be set.
	case CO_CONTRACTOR_INVOICE = 'A12_FACTURE_COTRAITANT';
}