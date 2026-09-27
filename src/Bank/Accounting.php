<?php

namespace Osimatic\Bank;

use Osimatic\Invoice\BillingTax;
use Osimatic\Location\Country;
use Osimatic\Text\CSVGenerator;

/**
 * Accounting export utility class
 * Generates CSV accounting files from transaction data for bookkeeping software
 * Handles VAT calculations and account mappings based on customer location and VAT status
 */
class Accounting
{
	/**
	 * Account mapping configuration for different customer types
	 * Keys: 'france', 'france_dom', 'inside_ue', 'outside_ue'
	 * @var array
	 */
	protected array $accounts = [];

	/**
	 * Set account mapping configuration
	 * @param array $accounts Account mapping array with keys: 'france', 'france_dom', 'inside_ue', 'outside_ue'
	 * @return self Returns this instance for method chaining
	 */
	public function setAccounts(array $accounts): self
	{
		$this->accounts = $accounts;

		return $this;
	}

	/**
	 * Generate accounting CSV file from transaction list
	 * Creates a CSV file with debit/credit entries for each transaction
	 * Automatically handles VAT lines based on customer location and VAT status
	 * @param string $filePath Path where the CSV file will be saved
	 * @param AccountingTransaction[] $transactionList Array of transactions to export
	 */
	public function generateFile(string $filePath, array $transactionList): void
	{
		// Header
		$tableHead = [
			'DATES',
			'JOURNAL',
			'COMPTES',
			'NUM FACTURE',
			'NOMS',
			'DEBIT (ttc)',
			'CREDIT (ht et tva)',
			'CODE MONNAIE',
		];

		// Transaction list
		$tableBody = [];
		foreach ($transactionList as $transaction) {
			$codeMonnaie = $this->getCurrencyCode($transaction->getCurrency());
			$accountKey = $this->getAccountKey($transaction->getCustomerCountry(), $transaction->getCustomerPostCode(), $transaction->getCustomerVatNumber());
			$addTvaLine = !empty(BillingTax::getBillingTaxRate($transaction->getCustomerCountry(), $transaction->getCustomerPostCode(), $transaction->getCustomerVatNumber(), 'FR'));

			$transactionDate = $transaction->getDateTime();

			// Debit line
			$account = $transaction->getDebitAccount() ?? $this->accounts[$accountKey]['debit'] ?? '';
			$tableBody[] = [
				$transactionDate->format('d/m/Y'),
				'VT',
				$account,
				$transaction->getInvoiceNumber(),
				$transaction->getCustomerIdentity(),
				$transaction->getAmountInclTax(),
				0,
				$codeMonnaie,
			];

			// Credit line (excl. tax)
			$account = $this->accounts[$accountKey]['credit_excl_tax'] ?? '';
			$tableBody[] = [
				$transactionDate->format('d/m/Y'),
				'VT',
				$account,
				$transaction->getInvoiceNumber(),
				$transaction->getCustomerIdentity(),
				0,
				$transaction->getAmountExclTax(),
				$codeMonnaie,
			];

			// Credit line (VAT)
			if ($addTvaLine) {
				$account = $this->accounts[$accountKey]['credit_vat'] ?? '';
				$tableBody[] = [
					$transactionDate->format('d/m/Y'),
					'VT',
					$account,
					$transaction->getInvoiceNumber(),
					$transaction->getCustomerIdentity(),
					0,
					$transaction->getAmountVat(),
					$codeMonnaie,
				];
			}
		}

		(new CSVGenerator())->generateFile($filePath, (new \Osimatic\Data\Table($tableHead, $tableBody, []))->getTableData());
	}

	/**
	 * Determine the account mapping key based on customer location and VAT status
	 * Returns the appropriate key to lookup accounts in the $accounts array
	 * @param string|null $customerCountry Customer's country code (ISO 3166-1 alpha-2)
	 * @param string|null $customerZipCode Customer's postal code
	 * @param string|null $vatNumber Customer's VAT number
	 * @return string Account key: 'france', 'france_dom', 'inside_ue', or 'outside_ue'
	 */
	private function getAccountKey(?string $customerCountry, ?string $customerZipCode=null, ?string $vatNumber=null): string
	{
		if (Country::isCountryInFranceOverseas($customerCountry, $customerZipCode)) {
			return 'france_dom';
		}
		if ('FR' === $customerCountry) {
			return 'france';
		}
		if (Country::isCountryInEuropeanUnion($customerCountry)) {
			if (empty($vatNumber)) { // If VAT number not provided, sale treated as France (VAT = 20%)
				return 'france';
			}
			return 'inside_ue';
		}
		return 'outside_ue';
	}

	/**
	 * Convert currency ISO code to accounting software currency code
	 * @param string $currency ISO 4217 currency code
	 * @return string Accounting currency code ('G' for GBP, 'E' for EUR/others)
	 */
	private function getCurrencyCode(string $currency): string
	{
		if ('GBP' === $currency) {
			return 'G';
		}
		return 'E';
	}

}