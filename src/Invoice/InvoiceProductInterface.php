<?php

namespace Osimatic\Invoice;

/**
 * Interface for invoice product/line item information
 * Represents a single product or service line in an invoice
 */
interface InvoiceProductInterface
{
	/**
	 * Get the label of the product/service line
	 * @return string|null The line item's label/description
	 */
	public function getLabel(): ?string;

	/**
	 * Get the unit price of the product
	 * @return float The price per unit
	 */
	public function getUnitPrice(): float;

	/**
	 * Get the quantity of the product
	 * @return float The number of units
	 */
	public function getQuantity(): float;

	/**
	 * Get the VAT/tax rate applied to this product line
	 * @return float The tax rate as a percentage (e.g., 20.0 for 20%, 0.0 for no VAT)
	 */
	public function getVatRate(): float;

	/**
	 * Get the VAT category of this product line
	 * @return VatCategory The tax treatment of the line (standard, zero-rated, exempt, reverse charge, etc.). A category other than STANDARD is expected to come with a VAT rate of 0.
	 */
	public function getVatCategory(): VatCategory;
}