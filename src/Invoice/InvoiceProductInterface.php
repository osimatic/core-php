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
}