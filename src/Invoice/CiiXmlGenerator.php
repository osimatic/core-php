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
readonly class CiiXmlGenerator
{
	/**
	 * Extended Factur-X profile.
	 * The French CTC extension (zffxextendedctcfr) is not used by default because its URN is not recognized by atgp/factur-x (ProfileResolutionException in FacturXGenerator).
	 */
	public const string PROVIDER_UNIQUE_ID_EXTENDED = 'zffxextended';

	/**
	 * UNTDID 1001 code for "Commercial invoice", mandatory on every profile.
	 */
	private const string DOCUMENT_TYPE_INVOICE = '380';

	/**
	 * Tax type code for value added tax.
	 */
	private const string TAX_TYPE_VAT = 'VAT';

	/**
	 * UN/CEFACT Recommendation 20 code "one" (C62), used for every line since InvoiceProductInterface does not expose a unit of measure.
	 * Semantically wrong for hours, days, kg, etc.: add a unit code to InvoiceProductInterface before invoicing such lines.
	 */
	private const string DEFAULT_UNIT_CODE = 'C62';

	public function __construct(
		private string $providerUniqueId = self::PROVIDER_UNIQUE_ID_EXTENDED,
		private LoggerInterface $logger = new NullLogger(),
	) {
		self::configureInvoiceSuite();
	}

	/**
	 * Restricts format provider discovery to the invoicesuite namespace.
	 * Without this, InvoiceSuiteDocumentBuilder scans every class known to the Composer classloader (thousands of them) to discover format providers, which is slow and has been observed to crash the PHP process on unrelated classes.
	 * Note: InvoiceSuiteSettings is static, so this changes global state for the whole PHP process.
	 */
	private static function configureInvoiceSuite(): void
	{
		\horstoeko\invoicesuite\InvoiceSuiteSettings::setDiscoveryNamespaces(['horstoeko\\invoicesuite']);
	}

	/**
	 * Generates the CII XML document.
	 * Any failure (missing seller or buyer, invalid provider, builder error) is logged and results in null.
	 * @param InvoiceInterface $invoice
	 * @return string|null The generated CII XML content, or null on failure
	 */
	public function generate(InvoiceInterface $invoice): ?string
	{
		try {
			$seller = $invoice->getSeller();
			$buyer = $invoice->getBuyer();

			if (null === $seller || null === $buyer) {
				throw new \InvalidArgumentException('Seller and buyer are required to generate a CII invoice.');
			}

			$builder = InvoiceSuiteDocumentBuilder::createByProviderUniqueId($this->providerUniqueId);

			$builder->setDocumentNo($invoice->getInvoiceNumber());
			$builder->setDocumentType(self::DOCUMENT_TYPE_INVOICE);
			$builder->setDocumentDate($invoice->getDate());
			$builder->setDocumentCurrency($invoice->getCurrency());
			$builder->setDocumentSellerName($seller->getName());
			$builder->setDocumentSellerId($seller->getRegistrationNumber());
			$builder->setDocumentBuyerName($buyer->getName());
			$builder->setDocumentBuyerId($buyer->getRegistrationNumber());
			$builder->setDocumentBuyerOrderReference($invoice->getCustomerOrderReference());

			foreach ($invoice->getProductsList() as $index => $product) {
				$builder->addDocumentPosition((string) ($index + 1));
				$builder->setDocumentPositionProductDetails(newProductName: $product->getLabel());
				$builder->setDocumentPositionQuantities($product->getQuantity(), self::DEFAULT_UNIT_CODE);
				// Gross price equals net price because line-level discounts/charges are not modeled yet: update both if they are added to InvoiceProductInterface
				$builder->setDocumentPositionGrossPrice($product->getUnitPrice());
				$builder->setDocumentPositionNetPrice($product->getUnitPrice());
				$builder->setDocumentPositionTax($product->getVatCategory()->value, self::TAX_TYPE_VAT, newTaxPercent: $product->getVatRate());
			}

			// Header-level VAT breakdown, one entry per distinct VAT rate (mandatory in addition to the per-line tax set above)
			foreach (VatBreakdown::fromInvoice($invoice) as $vatBreakdown) {
				$builder->addDocumentTax($vatBreakdown->category->value, self::TAX_TYPE_VAT, $vatBreakdown->baseExclTax, $vatBreakdown->vatAmount, $vatBreakdown->rate);
			}

			// Charge and discount totals are 0 because document-level allowances/charges are not modeled yet: update if they are added to InvoiceInterface
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
			$this->logger->error('Failed to build the CII XML document: '.$e->getMessage(), ['exception' => $e]);
			return null;
		}
	}
}