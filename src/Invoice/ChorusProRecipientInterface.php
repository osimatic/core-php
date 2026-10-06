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
	 * Gets the Chorus Pro service code, required when the invoice reference requirement is SERVICE_CODE_REQUIRED.
	 * @return string|null
	 */
	public function getChorusProServiceCode(): ?string;

	/**
	 * Gets the reference(s) required on invoices submitted to Chorus Pro for this recipient.
	 * @return ChorusProInvoiceReferenceRequirement|null
	 */
	public function getChorusProInvoiceReferenceRequirement(): ?ChorusProInvoiceReferenceRequirement;
}