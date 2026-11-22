<?php

namespace Osimatic\Validator\Constraints;

use Osimatic\Number\Number;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class DecimalAmountValidator extends ConstraintValidator
{
	/**
	 * Checks if the passed value is a valid decimal amount within the configured bounds.
	 * @param mixed $value The value that should be validated
	 * @param Constraint $constraint The constraint for the validation
	 */
	public function validate(mixed $value, Constraint $constraint): void
	{
		if (null === $value || '' === $value) {
			return;
		}

		/** @var DecimalAmount $constraint */
		$amountMinor = Number::parseMinorUnits((string) $value, $constraint->decimals);
		if (null === $amountMinor
			|| (null !== $constraint->minMinorUnits && $amountMinor < $constraint->minMinorUnits)
			|| (null !== $constraint->maxMinorUnits && $amountMinor > $constraint->maxMinorUnits)) {
			$this->context->buildViolation($constraint->message)
				->setParameter('{{value}}', (string) $value)
				->addViolation();
		}
	}
}