<?php 

namespace Osimatic\Bank;

use Osimatic\Invoice\InvoiceProductInterface;

/**
 * Interface representing a shopping cart
 * Used during payment processing, particularly for 3D-Secure authentication
 */
interface ShoppingCartInterface
{
	/**
	 * Get the list of products in the cart
	 * @return InvoiceProductInterface[] Array of products
	 */
	//public function getProductList(): array;

	/**
	 * Set the list of products in the cart
	 * @param InvoiceProductInterface[] $productList Array of products to set
	 * @return void
	 */
	//public function setProductName(array $productList): void;

	/**
	 * Get the total quantity of items in the cart
	 * @return int The total number of items
	 */
	public function getTotalQuantity(): int;

	/**
	 * Set the total quantity of items in the cart
	 * @param int $totalQuantity The total number of items to set
	 * @return void
	 */
	public function setTotalQuantity(int $totalQuantity): void;

	/**
	 * Get the total price of the cart
	 * @return int The total price in cents (e.g., 1000 = 10.00 EUR)
	 */
	public function getTotalPrice(): int;

	/**
	 * Set the total price of the cart
	 * @param int $totalPrice The total price in cents to set (e.g., 1000 = 10.00 EUR)
	 * @return void
	 */
	public function setTotalPrice(int $totalPrice): void;

}