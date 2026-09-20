<?php

namespace Osimatic\Bank;

/**
 * Enumeration of invoice document types.
 * Represents the various billing document types that can be issued for an order.
 */
enum InvoiceType: string
{
	/** Regular invoice */
	case INVOICE = 'invoice';

	/** Quotation / quote, not yet a binding invoice */
	case QUOTATION = 'quotation';

	/** Pro forma invoice, preliminary bill sent before goods/services are delivered */
	case PRO_FORMA = 'pro_forma';

	/**
	 * Gets the localized label for the invoice type.
	 * Returns the French label for display purposes.
	 * @return string The localized label
	 */
	public function getLabel(): string
	{
		return match ($this) {
			self::QUOTATION => 'Devis', // 'invoice_type.quotation'
			self::PRO_FORMA => 'Proforma', // 'invoice_type.pro_forma'
			self::INVOICE => 'Facture', // 'invoice_type.invoice'
		};
	}

	/**
	 * Parses an invoice type string into the corresponding enum value.
	 * Handles alternative spellings (e.g. "proforma", "quote").
	 * Case-insensitive parsing.
	 * @param string|null $invoiceType The type string to parse (e.g., 'invoice', 'proforma', 'quote')
	 * @return self|null The corresponding InvoiceType enum, or null if input is null or invalid
	 */
	public static function parse(?string $invoiceType): ?self
	{
		if (null === $invoiceType) {
			return null;
		}

		$invoiceType = mb_strtolower($invoiceType);
		if ('proforma' === $invoiceType) {
			return self::PRO_FORMA;
		}
		if ('quote' === $invoiceType) {
			return self::QUOTATION;
		}

		return self::tryFrom($invoiceType);
	}
}