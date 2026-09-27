<?php

namespace Osimatic\Invoice;

/**
 * Interface for an invoice that can track the result of its submission to Chorus Pro.
 */
interface ChorusProSubmissionTrackingInterface
{
	/**
	 * @param ChorusProSubmissionStatus $status
	 */
	public function setChorusProSubmissionStatus(ChorusProSubmissionStatus $status): void;

	/**
	 * @param string|null $submissionId The identifier returned by the Chorus Pro API for this submission
	 */
	public function setChorusProSubmissionId(?string $submissionId): void;

	/**
	 * @param \DateTime|null $dateTime
	 */
	public function setChorusProSubmissionDateTime(?\DateTime $dateTime): void;

	/**
	 * @param string|null $error The last error message, for manual follow-up
	 */
	public function setChorusProSubmissionError(?string $error): void;
}