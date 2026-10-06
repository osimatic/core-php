<?php

namespace Osimatic\Invoice;

/**
 * Interface for an invoice that tracks its own submission status to Chorus Pro, so that ChorusProClient::submit() can detect an invoice already submitted or accepted (and skip resubmitting it), and persist the outcome of a submission directly onto the invoice.
 */
interface ChorusProInvoiceInterface
{
	/**
	 * Gets the status of a previous submission of this invoice to Chorus Pro, if any.
	 * @return ChorusProSubmissionStatus|null
	 */
	public function getChorusProSubmissionStatus(): ?ChorusProSubmissionStatus;

	/**
	 * Sets the status of a submission of this invoice to Chorus Pro.
	 * @param ChorusProSubmissionStatus|null $status
	 */
	public function setChorusProSubmissionStatus(?ChorusProSubmissionStatus $status): void;

	/**
	 * Gets the identifier returned by Chorus Pro for a previous submission of this invoice, if any.
	 * @return string|null
	 */
	public function getChorusProSubmissionId(): ?string;

	/**
	 * Sets the identifier returned by Chorus Pro for a submission of this invoice.
	 * @param string|null $submissionId
	 */
	public function setChorusProSubmissionId(?string $submissionId): void;

	/**
	 * Gets the date and time of a previous submission of this invoice to Chorus Pro, if any.
	 * @return \DateTime|null
	 */
	public function getChorusProSubmissionDateTime(): ?\DateTime;

	/**
	 * Sets the date and time of a submission of this invoice to Chorus Pro.
	 * @param \DateTime|null $dateTime
	 */
	public function setChorusProSubmissionDateTime(?\DateTime $dateTime): void;

	/**
	 * Gets the error message of a previous submission of this invoice to Chorus Pro, if any.
	 * @return string|null
	 */
	public function getChorusProSubmissionError(): ?string;

	/**
	 * Sets the error message of a submission of this invoice to Chorus Pro.
	 * @param string|null $error
	 */
	public function setChorusProSubmissionError(?string $error): void;
}