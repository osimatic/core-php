<?php

namespace Osimatic\Invoice;

use horstoeko\invoicesuite\InvoiceSuiteDocumentBuilder;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Builds a Cross Industry Invoice (CII) XML document (ZUGFeRD/Factur-X, EN16931-based) from an invoice.
 * This is a minimal mapping covering only the fields readily available on InvoiceInterface; it has not been validated against a real e-invoicing platform yet and should be reviewed and extended as needed.
 * @link https://github.com/horstoeko/invoicesuite horstoeko/invoicesuite documentation
 */
class CiiXmlGenerator
{
	// "Extended" ZUGFeRD/Factur-X profile with the French CTC extension (AFNOR XP Z12-012)
	public const string PROVIDER_UNIQUE_ID_EXTENDED_CTC_FR = 'zffxextendedctcfr';

	// UN/CEFACT Recommendation 20 generic "unit" code, used as a default since InvoiceProductInterface does not expose a unit of measure
	private const string DEFAULT_UNIT_CODE = 'C62';

	public function __construct(
		private readonly string $providerUniqueId = self::PROVIDER_UNIQUE_ID_EXTENDED_CTC_FR,
		private readonly LoggerInterface $logger = new NullLogger(),
	) {}

	/**
	 * @param InvoiceInterface $invoice
	 * @return string|null The generated CII XML content, or null on failure
	 */
	public function generate(InvoiceInterface $invoice): ?string
	{
		$seller = $invoice->getSeller();
		$buyer = $invoice->getBuyer();

		try {
			$builder = InvoiceSuiteDocumentBuilder::createByProviderUniqueId($this->providerUniqueId);

			$builder->setDocumentNo($invoice->getInvoiceNumber());
			$builder->setDocumentDate($invoice->getDate());
			$builder->setDocumentCurrency($invoice->getCurrency());
			$builder->setDocumentSellerName($seller?->getName());
			$builder->setDocumentSellerId($seller?->getRegistrationNumber());
			$builder->setDocumentBuyerName($buyer?->getName());
			$builder->setDocumentBuyerId($buyer?->getRegistrationNumber());
			$builder->setDocumentBuyerOrderReference($invoice->getCustomerOrderReference());

			foreach ($invoice->getProductsList() as $product) {
				$builder->addDocumentPosition();
				$builder->setDocumentPositionProductDetails(newProductName: $product->getLabel());
				$builder->setDocumentPositionQuantities($product->getQuantity(), self::DEFAULT_UNIT_CODE);
				$builder->setDocumentPositionGrossPrice($product->getUnitPrice());
				$builder->setDocumentPositionNetPrice($product->getUnitPrice());
				$builder->setDocumentPositionTax('S', 'VAT', newTaxPercent: $invoice->getBillingTaxRate());
			}

			$builder->setDocumentSummation(
				newNetAmount: $invoice->getTotalExclTax(),
				newChargeTotalAmount: 0.0,
				newDiscountTotalAmount: 0.0,
				newTaxBasisAmount: $invoice->getTotalExclTax(),
				newTaxTotalAmount: $invoice->getTotalVat(),
				newGrossAmount: $invoice->getTotalInclTax(),
				newDueAmount: $invoice->getTotalInclTax(),
			);

			return $builder->getContent();
		}
		catch (\Throwable $e) {
			$this->logger->error('Failed to build the CII XML document: '.$e->getMessage());
			return null;
		}
	}
}