<?php

namespace Osimatic\Invoice;

/**
 * Value object representing one line of the VAT breakdown of an invoice, i.e. the taxable base and the VAT amount for a given VAT category and VAT rate.
 * @link https://en.wikipedia.org/wiki/Value-added_tax Value-added tax
 */
final readonly class VatBreakdown
{
	/**
	 * @param VatCategory $category The VAT category (standard, zero-rated, exempt, reverse charge, etc.)
	 * @param float $rate The VAT rate as a percentage (e.g., 20.0 for 20%)
	 * @param float $baseExclTax The taxable base (total excluding tax) of the product lines in this category and at this rate
	 * @param float $vatAmount The VAT amount for this category and rate
	 */
	public function __construct(
		public VatCategory $category,
		public float $rate,
		public float $baseExclTax,
		public float $vatAmount,
	) {}

	/**
	 * Groups the product lines of an invoice by VAT category and VAT rate and sums, for each group, the taxable base and the corresponding VAT amount.
	 * Amounts are rounded to 2 decimals per group, so the sum of the VAT amounts may differ by a few cents from InvoiceInterface::getTotalVat() if that total was computed differently.
	 * @param InvoiceInterface $invoice
	 * @return VatBreakdown[] One entry per distinct category and rate, sorted by rate in descending order
	 */
	public static function fromInvoice(InvoiceInterface $invoice): array
	{
		$groups = [];
		foreach ($invoice->getProductsList() as $product) {
			$category = $product->getVatCategory();
			$rate = $product->getVatRate();
			$key = $category->value.'|'.$rate;
			$groups[$key] ??= ['category' => $category, 'rate' => $rate, 'base' => 0.0];
			$groups[$key]['base'] += $product->getUnitPrice() * $product->getQuantity();
		}

		$breakdown = array_map(
			static fn (array $group) => new self($group['category'], $group['rate'], round($group['base'], 2), round($group['base'] * $group['rate'] / 100, 2)),
			array_values($groups)
		);

		usort($breakdown, static fn (self $a, self $b) => $b->rate <=> $a->rate);

		return $breakdown;
	}
}