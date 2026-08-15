<?php

namespace Tests\Number;

use Osimatic\Number\Duration;
use Osimatic\Number\DurationDisplayMode;
use PHPUnit\Framework\TestCase;

final class DurationTest extends TestCase
{
	/* ===================== Intersection Between Time Slots ===================== */

	public function testGetIntersectionDurationBetweenTimestampSlots(): void
	{
		// Full overlap
		$duration = Duration::getIntersectionDurationBetweenTimestampSlots(
			100, 200,  // Slot 1: 100-200
			100, 200   // Slot 2: 100-200
		);
		$this->assertEquals(100, $duration);

		// Partial overlap
		$duration = Duration::getIntersectionDurationBetweenTimestampSlots(
			100, 200,  // Slot 1: 100-200
			150, 250   // Slot 2: 150-250
		);
		$this->assertEquals(50, $duration);

		// No overlap (slots are separate)
		$duration = Duration::getIntersectionDurationBetweenTimestampSlots(
			100, 200,  // Slot 1: 100-200
			300, 400   // Slot 2: 300-400
		);
		$this->assertEquals(0, $duration);

		// One slot inside another
		$duration = Duration::getIntersectionDurationBetweenTimestampSlots(
			100, 300,  // Slot 1: 100-300
			150, 200   // Slot 2: 150-200 (inside slot 1)
		);
		$this->assertEquals(50, $duration);
	}

	public function testGetIntersectionDurationBetweenTimeSlots(): void
	{
		$slot1Start = new \DateTime('2024-01-01 10:00:00');
		$slot1End = new \DateTime('2024-01-01 12:00:00');
		$slot2Start = new \DateTime('2024-01-01 11:00:00');
		$slot2End = new \DateTime('2024-01-01 13:00:00');

		// Overlapping slots (1 hour intersection)
		$duration = Duration::getIntersectionDurationBetweenTimeSlots($slot1Start, $slot1End, $slot2Start, $slot2End);
		$this->assertEquals(3600, $duration); // 1 hour = 3600 seconds

		// No overlap
		$slot3Start = new \DateTime('2024-01-01 14:00:00');
		$slot3End = new \DateTime('2024-01-01 15:00:00');
		$duration = Duration::getIntersectionDurationBetweenTimeSlots($slot1Start, $slot1End, $slot3Start, $slot3End);
		$this->assertEquals(0, $duration);
	}

	public function testGetDurationInSeconds(): void
	{
		$start = new \DateTime('2024-01-01 10:00:00');
		$end = new \DateTime('2024-01-01 11:00:00');

		// Normal case
		$duration = Duration::getDurationInSeconds($start, $end);
		$this->assertEquals(3600, $duration); // 1 hour

		// Reversed (end before start) without inverse
		$duration = Duration::getDurationInSeconds($end, $start);
		$this->assertEquals(0, $duration);

		// Reversed with inverse flag
		$duration = Duration::getDurationInSeconds($end, $start, true);
		$this->assertEquals(3600, $duration);
	}

	/* ===================== Counting Elements ===================== */

	public function testGetNbDays(): void
	{
		$this->assertEquals(0, Duration::getNbDays(0));
		$this->assertEquals(0, Duration::getNbDays(3600)); // 1 hour
		$this->assertEquals(1, Duration::getNbDays(86400)); // 1 day
		$this->assertEquals(2, Duration::getNbDays(172800)); // 2 days
		$this->assertEquals(1, Duration::getNbDays(93784)); // 1 day + extra
	}

	public function testGetNbHours(): void
	{
		$this->assertEquals(0, Duration::getNbHours(0));
		$this->assertEquals(1, Duration::getNbHours(3600)); // 1 hour
		$this->assertEquals(24, Duration::getNbHours(86400)); // 1 day
		$this->assertEquals(26, Duration::getNbHours(93784)); // 1 day + 2 hours + extra
	}

	public function testGetNbMinutes(): void
	{
		$this->assertEquals(0, Duration::getNbMinutes(0));
		$this->assertEquals(1, Duration::getNbMinutes(60)); // 1 minute
		$this->assertEquals(60, Duration::getNbMinutes(3600)); // 1 hour
		$this->assertEquals(1563, Duration::getNbMinutes(93784)); // 1 day + 2 hours + 3 minutes + 4 seconds
	}

	public function testGetNbHoursRemaining(): void
	{
		// 1 day 2 hours 3 minutes 4 seconds = 93784 seconds
		$this->assertEquals(2, Duration::getNbHoursRemaining(93784));

		// Just 2 hours
		$this->assertEquals(2, Duration::getNbHoursRemaining(7200));

		// Less than 1 hour
		$this->assertEquals(0, Duration::getNbHoursRemaining(1800));

		// Exactly 1 day
		$this->assertEquals(0, Duration::getNbHoursRemaining(86400));
	}

	public function testGetNbMinutesRemaining(): void
	{
		// 1 day 2 hours 3 minutes 4 seconds = 93784 seconds
		$this->assertEquals(3, Duration::getNbMinutesRemaining(93784));

		// Just 3 minutes
		$this->assertEquals(3, Duration::getNbMinutesRemaining(180));

		// 1 hour 15 minutes
		$this->assertEquals(15, Duration::getNbMinutesRemaining(4500));

		// Less than 1 minute
		$this->assertEquals(0, Duration::getNbMinutesRemaining(30));
	}

	public function testGetNbSecondsRemaining(): void
	{
		// 1 day 2 hours 3 minutes 4 seconds = 93784 seconds
		$this->assertEquals(4, Duration::getNbSecondsRemaining(93784));

		// Just 4 seconds
		$this->assertEquals(4, Duration::getNbSecondsRemaining(4));

		// 1 minute 30 seconds
		$this->assertEquals(30, Duration::getNbSecondsRemaining(90));

		// Exactly 1 minute
		$this->assertEquals(0, Duration::getNbSecondsRemaining(60));
	}

	/* ===================== Duration Display ===================== */

	public function testFormat(): void
	{
		// --- Mode STANDARD (default) ---

		// Full format: hours, minutes, seconds
		$this->assertEquals('01:01.01', Duration::format(3661));

		// Without seconds
		$this->assertEquals('01:01', Duration::format(3661, DurationDisplayMode::STANDARD, false));

		// Zero duration
		$this->assertEquals('00:00.00', Duration::format(0));

		// --- Mode INPUT_TIME ---
		$this->assertEquals('01:01:01', Duration::format(3661, DurationDisplayMode::INPUT_TIME));

		// --- Mode CHRONO ---
		$this->assertEquals("01:01'01\"", Duration::format(3661, DurationDisplayMode::CHRONO));

		// --- Mode SECONDS ---
		$this->assertEquals('3661', Duration::format(3661, DurationDisplayMode::SECONDS));

		// --- Mode DECIMAL ---
		// Number::format uses locale-specific decimal separator; accept both '.' and ','
		$this->assertMatchesRegularExpression('/^1[.,]02$/', Duration::format(3661, DurationDisplayMode::DECIMAL));

		// --- Sign ($withSign=true) ---

		// Positive value: prefix "+"
		$this->assertEquals('+ 01:01.01', Duration::format(3661, DurationDisplayMode::STANDARD, true, true));

		// Zero value: prefix "+"
		$this->assertEquals('+ 00:00.00', Duration::format(0, DurationDisplayMode::STANDARD, true, true));

		// --- Negative durations ---

		// STANDARD: sign prefix "- " + absolute value formatted
		$this->assertEquals('- 01:01.01', Duration::format(-3661));

		// STANDARD with $withSign=true
		$this->assertEquals('- 01:01.01', Duration::format(-3661, DurationDisplayMode::STANDARD, true, true));

		// INPUT_TIME
		$this->assertEquals('- 01:01:01', Duration::format(-3661, DurationDisplayMode::INPUT_TIME));

		// SECONDS: no double sign
		$this->assertEquals('- 3661', Duration::format(-3661, DurationDisplayMode::SECONDS));

		// DECIMAL: no double sign
		$this->assertMatchesRegularExpression('/^- 1[.,]02$/', Duration::format(-3661, DurationDisplayMode::DECIMAL));
	}

	public function testFormatAsText(): void
	{
		// Full format: hours, minutes, seconds
		$result = Duration::formatAsText(3661); // 1h 1min 1s
		$this->assertStringContainsString('1', $result);
		$this->assertMatchesRegularExpression('/h/', $result);
		$this->assertMatchesRegularExpression('/min/', $result);
		$this->assertMatchesRegularExpression('/s/', $result);

		// Without seconds
		$result = Duration::formatAsText(3661, false);
		$this->assertStringNotContainsString('s', $result);

		// Without minutes
		$result = Duration::formatAsText(3661, true, false);
		$this->assertStringNotContainsString('min', $result);

		// Negative duration (converted to positive)
		$result = Duration::formatAsText(-3661);
		$this->assertStringContainsString('1', $result);
	}

	// ========== Duration Display (Chrono Format) ==========

	public function testFormatNbHours(): void
	{
		// Standard mode: "10:20.30"
		$result = Duration::formatNbHours(37230); // 10h 20min 30s
		$this->assertEquals('10:20.30', $result);

		// Without seconds
		$result = Duration::formatNbHours(37230, DurationDisplayMode::STANDARD, false);
		$this->assertEquals('10:20', $result);

		// Chrono mode: "10:20'30\""
		$result = Duration::formatNbHours(37230, DurationDisplayMode::CHRONO);
		$this->assertStringContainsString("'", $result);
		$this->assertStringContainsString('"', $result);

		// Input time mode: "10:20:30"
		$result = Duration::formatNbHours(37230, DurationDisplayMode::INPUT_TIME);
		$this->assertEquals('10:20:30', $result);

		// Negative duration: single leading "-", absolute value formatted (not one negative component per group)
		$result = Duration::formatNbHours(-37230);
		$this->assertEquals('-10:20.30', $result);

		// Negative duration below 1 hour (regression case: was displaying as "-2:-35.00" instead of "-1:35.00")
		$result = Duration::formatNbHours(-5700); // -1h35min
		$this->assertEquals('-01:35.00', $result);

		// Negative duration, input time mode
		$result = Duration::formatNbHours(-5700, DurationDisplayMode::INPUT_TIME);
		$this->assertEquals('-01:35:00', $result);
	}

	public function testFormatNbMinutes(): void
	{
		// Standard mode: "20.30"
		$result = Duration::formatNbMinutes(1230); // 20min 30s
		$this->assertEquals('20.30', $result);

		// Chrono mode: "20'30\""
		$result = Duration::formatNbMinutes(1230, DurationDisplayMode::CHRONO);
		$this->assertStringContainsString("'", $result);
		$this->assertStringContainsString('"', $result);

		// Input time mode: "20:30"
		$result = Duration::formatNbMinutes(1230, DurationDisplayMode::INPUT_TIME);
		$this->assertEquals('20:30', $result);

		// Negative duration: single leading "-", absolute value formatted (not one negative component per group)
		$result = Duration::formatNbMinutes(-1230);
		$this->assertEquals('-20.30', $result);
	}

	/* ===================== Parsing ===================== */

	public function testCheck(): void
	{
		// Valid durations as integers (seconds)
		$this->assertTrue(Duration::isValid(3600));
		$this->assertTrue(Duration::isValid('3600'));
		$this->assertTrue(Duration::isValid('0'));

		// Valid durations as time format
		$this->assertTrue(Duration::isValid('10:30:45'));
		$this->assertTrue(Duration::isValid('01:00:00'));
	}

	public function testParse(): void
	{
		// Parse integers (seconds)
		$this->assertEquals(3600, Duration::parse(3600));
		$this->assertEquals(3600, Duration::parse('3600'));

		// Parse time format
		$this->assertEquals(37845, Duration::parse('10:30:45')); // 10*3600 + 30*60 + 45 = 37845

		// Invalid input returns 0
		$this->assertEquals(0, Duration::parse('invalid'));
		$this->assertEquals(0, Duration::parse(''));
	}

	/* ===================== Decimal Conversion ===================== */

	public function testConvertToNbDecimalHours(): void
	{
		// 1 hour = 1.0 decimal hours
		$this->assertEquals(1.0, Duration::convertToNbDecimalHours(3600));

		// 1.5 hours
		$this->assertEquals(1.5, Duration::convertToNbDecimalHours(5400));

		// 30 minutes = 0.5 hours
		$this->assertEquals(0.5, Duration::convertToNbDecimalHours(1800));

		// 0 seconds
		$this->assertEquals(0.0, Duration::convertToNbDecimalHours(0));
	}

	public function testConvertToNbDecimalMinutes(): void
	{
		// 1 minute = 1.0 decimal minutes
		$this->assertEquals(1.0, Duration::convertToNbDecimalMinutes(60));

		// 1.5 minutes
		$this->assertEquals(1.5, Duration::convertToNbDecimalMinutes(90));

		// 30 seconds = 0.5 minutes
		$this->assertEquals(0.5, Duration::convertToNbDecimalMinutes(30));

		// 0 seconds
		$this->assertEquals(0.0, Duration::convertToNbDecimalMinutes(0));
	}

	/* ===================== Rounding ===================== */

	public function testRound(): void
	{
		// No rounding (precision 0)
		$this->assertEquals(3784, Duration::round(3784, 0));

		// Round to 5 minutes (300 seconds) - closest
		$duration = 3784; // 1h 3min 4sec (184 sec > 150 sec, so round up)
		$rounded = Duration::round($duration, 5, 'close');
		$this->assertEquals(3900, $rounded); // Should round up to 1h 5min

		// Round to 5 minutes - example that rounds down
		$duration = 3660; // 1h 1min 0sec (60 sec < 150 sec, so round down)
		$rounded = Duration::round($duration, 5, 'close');
		$this->assertEquals(3600, $rounded); // Should round down to 1h 0min

		// Round up to 5 minutes
		$duration = 3900; // 1h 5min 0sec
		$rounded = Duration::round($duration, 5, 'up');
		$this->assertEquals(3900, $rounded); // Already at 5min precision

		// Round down to 5 minutes
		$duration = 3784; // 1h 3min 4sec
		$rounded = Duration::round($duration, 5, 'down');
		$this->assertEquals(3600, $rounded); // Round down to 1h 0min

		// Negative duration, round to 5 minutes - closest (mirrors the 3784 => 3900 case)
		$rounded = Duration::round(-3784, 5, 'close');
		$this->assertEquals(-3900, $rounded);

		// Negative duration, round 'down' (floor towards more negative, i.e. away from zero)
		$rounded = Duration::round(-3784, 5, 'down');
		$this->assertEquals(-3900, $rounded);

		// Negative duration, round 'up' (ceil towards zero)
		$rounded = Duration::round(-3784, 5, 'up');
		$this->assertEquals(-3600, $rounded);
	}

	/* ===================== Min/Max Validation ===================== */

	public function testCheckMinAndMax(): void
	{
		// Within range
		$this->assertTrue(Duration::checkMinAndMax(100, 50, 150));

		// Below min
		$this->assertFalse(Duration::checkMinAndMax(40, 50, 150));

		// Above max
		$this->assertFalse(Duration::checkMinAndMax(200, 50, 150));

		// Only min check
		$this->assertTrue(Duration::checkMinAndMax(100, 50, 0));
		$this->assertFalse(Duration::checkMinAndMax(40, 50, 0));

		// Only max check
		$this->assertTrue(Duration::checkMinAndMax(100, 0, 150));
		$this->assertFalse(Duration::checkMinAndMax(200, 0, 150));

		// No constraints (both 0) - returns false
		$this->assertFalse(Duration::checkMinAndMax(100, 0, 0));
	}
}