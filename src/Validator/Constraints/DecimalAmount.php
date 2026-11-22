<?php

namespace Osimatic\Validator\Constraints;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * Validates a non-negative decimal amount given as a string (dot or comma separator), without any float conversion.
 * @link https://www.php.net/manual/en/function.preg-match.php
 */
#[\Attribute]
class DecimalAmount extends Constraint
{
	public string $message;

	/** Number of decimal digits allowed (e.g. 2 for cents) */
	public int $decimals;

	/** Minimum amount in minor units (e.g. 1 for 0.01), or null for no minimum */
	public ?int $minMinorUnits;

	/** Maximum amount in minor units (e.g. 99999999 for 999999.99), or null for no maximum */
	public ?int $maxMinorUnits;

	#[HasNamedArguments]
	public function __construct(?string $message=null, int $decimals=2, ?int $minMinorUnits=null, ?int $maxMinorUnits=null, ?array $groups=null, mixed $payload=null)
	{
		parent::__construct([], $groups, $payload);

		$this->message = $message ?? 'decimal_amount.invalid';
		$this->decimals = $decimals;
		$this->minMinorUnits = $minMinorUnits;
		$this->maxMinorUnits = $maxMinorUnits;
	}
}