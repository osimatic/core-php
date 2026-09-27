<?php

namespace Osimatic\Invoice;

/**
 * Interface for an invoice buyer that may be a French public administration required to receive its invoices via Chorus Pro.
 */
interface ChorusProRecipientInterface
{
	/**
	 * Whether this recipient is a French public administration that must receive invoices via Chorus Pro.
	 * @return bool
	 */
	public function isChorusProRecipient(): bool;

	/**
	 * Gets the SIRET of the "service exécutant" (executing service), which may differ from the recipient's main SIRET.
	 * @return string|null
	 */
	public function getChorusProServiceSiret(): ?string;

	/**
	 * Gets the Chorus Pro service code, required for the TYPE_2 invoicing category.
	 * @return string|null
	 */
	public function getChorusProServiceCode(): ?string;

	/**
	 * Gets the Chorus Pro invoicing structure category, which determines which references are mandatory.
	 * @return ChorusProInvoiceCategory|null
	 */
	public function getChorusProInvoiceCategory(): ?ChorusProInvoiceCategory;
}