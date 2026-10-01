<?php

namespace Osimatic\Invoice;

/**
 * Value object describing the outcome of a ChorusProClient::submit() call, to be persisted by the caller (e.g. on its order or invoice entity).
 */
final readonly class ChorusProSubmissionResult
{
	/**
	 * @param ChorusProSubmissionStatus $status The outcome of the submission
	 * @param string|null $submissionId The identifier returned by the Chorus Pro API, only set when the status is SUBMITTED
	 * @param string|null $error The error message, only set when the status is ERROR
	 * @param \DateTime $dateTime The date and time of the submission attempt
	 */
	public function __construct(
		public ChorusProSubmissionStatus $status,
		public ?string $submissionId = null,
		public ?string $error = null,
		public \DateTime $dateTime = new \DateTime(),
	) {}

	/**
	 * @return bool True if the invoice was successfully submitted to Chorus Pro
	 */
	public function isSubmitted(): bool
	{
		return ChorusProSubmissionStatus::SUBMITTED === $this->status;
	}
}