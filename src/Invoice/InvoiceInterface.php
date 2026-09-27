<?php

namespace Osimatic\Invoice;

use Osimatic\Bank\PaymentMethod;
use Osimatic\Organization\OrganizationInterface;

/**
 * Interface for invoice information
 * Represents a complete invoice document with buyer, seller, products, and payment details
 * Based on schema.org/Invoice
 */
interface InvoiceInterface
{
	/**
	 * Get the seller organization
	 * @return OrganizationInterface|null The organization selling the goods/services
	 */
	public function getSeller(): ?OrganizationInterface;

	/**
	 * Set the seller organization
	 * @param OrganizationInterface|null $seller The organization selling the goods/services
	 */
	public function setSeller(?OrganizationInterface $seller): void;

	/**
	 * Get the buyer organization
	 * @return OrganizationInterface|null The organization purchasing the goods/services
	 */
	public function getBuyer(): ?OrganizationInterface;

	/**
	 * Set the buyer organization
	 * @param OrganizationInterface|null $buyer The organization purchasing the goods/services
	 */
	public function setBuyer(?OrganizationInterface $buyer): void;

	/**
	 * Get the order reference
	 * @return string The reference of the associated order
	 */
	public function getOrderReference(): string;

	/**
	 * Set the order reference
	 * @param string $orderReference The reference of the associated order
	 */
	public function setOrderReference(string $orderReference): void;

	/**
	 * Get the invoice number
	 * @return string The unique invoice number
	 */
	public function getInvoiceNumber(): string;

	/**
	 * Set the invoice number
	 * @param string $invoiceNumber The unique invoice number
	 */
	public function setInvoiceNumber(string $invoiceNumber): void;

	/**
	 * Get the invoice date
	 * @return \DateTime The date the invoice was issued
	 */
	public function getDate(): \DateTime;

	/**
	 * Set the invoice date
	 * @param \DateTime $date The date the invoice was issued
	 */
	public function setDate(\DateTime $date): void;

	/**
	 * Get the billing city
	 * @return string The city where billing occurred
	 */
	public function getBillingCity(): string;

	/**
	 * Set the billing city
	 * @param string $billingCity The city where billing occurred
	 */
	public function setBillingCity(string $billingCity): void;

	/**
	 * Get the list of products/line items on the invoice
	 * @return InvoiceProductInterface[] Array of invoice line items
	 */
	public function getProductsList(): array;

	/**
	 * Set the list of products/line items on the invoice
	 * @param InvoiceProductInterface[] $productsList Array of invoice line items
	 */
	public function setProductsList(array $productsList): void;

	/**
	 * Get the total amount excluding tax
	 * @return float The total before tax/VAT
	 */
	public function getTotalExclTax(): float;

	/**
	 * Set the total amount excluding tax
	 * @param float $totalExclTax The total before tax/VAT
	 */
	public function setTotalExclTax(float $totalExclTax): void;

	/**
	 * Get the total VAT/tax amount
	 * @return float The VAT/tax amount
	 */
	public function getTotalVat(): float;

	/**
	 * Set the total VAT/tax amount
	 * @param float $totalVat The VAT/tax amount
	 */
	public function setTotalVat(float $totalVat): void;

	/**
	 * Get the total amount including tax
	 * @return float The total after tax/VAT
	 */
	public function getTotalInclTax(): float;

	/**
	 * Set the total amount including tax
	 * @param float $totalInclTax The total after tax/VAT
	 */
	public function setTotalInclTax(float $totalInclTax): void;

	/**
	 * Get the currency code
	 * @return string The three-letter ISO 4217 currency code (e.g., "EUR", "USD")
	 */
	public function getCurrency(): string;

	/**
	 * Set the currency code
	 * @param string $currency The three-letter ISO 4217 currency code (e.g., "EUR", "USD")
	 */
	public function setCurrency(string $currency): void;

	/**
	 * Get the billing tax rate
	 * @return float The tax rate as a percentage (e.g., 20.0 for 20%)
	 */
	public function getBillingTaxRate(): float;

	/**
	 * Set the billing tax rate
	 * @param float $billingTaxRate The tax rate as a percentage (e.g., 20.0 for 20%)
	 */
	public function setBillingTaxRate(float $billingTaxRate): void;

	/**
	 * Get the validation date
	 * @return \DateTime|null The date the invoice was validated/approved
	 */
	public function getValidationDate(): ?\DateTime;

	/**
	 * Set the validation date
	 * @param \DateTime|null $validationDate The date the invoice was validated/approved
	 */
	public function setValidationDate(?\DateTime $validationDate): void;

	/**
	 * Get the payment status
	 * @return string The current payment status (e.g., "paid", "pending", "overdue")
	 */
	public function getPaymentStatus(): string;

	/**
	 * Set the payment status
	 * @param string $paymentStatus The current payment status (e.g., "paid", "pending", "overdue")
	 */
	public function setPaymentStatus(string $paymentStatus): void;

	/**
	 * Get the payment date
	 * @return \DateTime|null The date the invoice was paid
	 */
	public function getPaymentDate(): ?\DateTime;

	/**
	 * Set the payment date
	 * @param \DateTime|null $paymentDate The date the invoice was paid
	 */
	public function setPaymentDate(?\DateTime $paymentDate): void;

	/**
	 * Get the payment method
	 * @return PaymentMethod|null The method used for payment (e.g., bank card, transfer, check)
	 */
	public function getPaymentMethod(): ?PaymentMethod;

	/**
	 * Set the payment method
	 * @param PaymentMethod|null $paymentMethod The method used for payment (e.g., bank card, transfer, check)
	 */
	public function setPaymentMethod(?PaymentMethod $paymentMethod): void;

	/**
	 * Get the bank card authorization number
	 * @return string|null The authorization number from the payment processor
	 */
	public function getBankCardAuthorizationNumber(): ?string;

	/**
	 * Set the bank card authorization number
	 * @param string|null $bankCardAuthorizationNumber The authorization number from the payment processor
	 */
	public function setBankCardAuthorizationNumber(?string $bankCardAuthorizationNumber): void;

	/**
	 * Get the delivery type
	 * @return string|null The type of delivery (e.g., "standard", "express", "pickup")
	 */
	public function getDeliveryType(): ?string;

	/**
	 * Set the delivery type
	 * @param string|null $deliveryType The type of delivery (e.g., "standard", "express", "pickup")
	 */
	public function setDeliveryType(?string $deliveryType): void;

	/**
	 * Get the customer order reference (e.g. a purchase order or engagement number given by the buyer)
	 * @return string|null The customer's own reference for this order/invoice
	 */
	public function getCustomerOrderReference(): ?string;

	/**
	 * Set the customer order reference (e.g. a purchase order or engagement number given by the buyer)
	 * @param string|null $customerOrderReference The customer's own reference for this order/invoice
	 */
	public function setCustomerOrderReference(?string $customerOrderReference): void;

}