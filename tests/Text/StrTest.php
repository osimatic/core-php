<?php

declare(strict_types=1);

namespace Tests\Text;

use Osimatic\Text\Str;
use PHPUnit\Framework\TestCase;

final class StrTest extends TestCase
{
	/* ===================== replaceListChar() ===================== */

	public function testReplaceListChar(): void
	{
		// Basic replacement (lowercase only by default)
		$result = Str::replaceListChar('Hello World', ['o' => '0', 'l' => '1']);
		$this->assertSame('He110 W0r1d', $result);

		// With uppercase replacement enabled
		$result = Str::replaceListChar('Hello WORLD', ['o' => '0', 'l' => '1'], replaceUppercaseChar: true);
		$this->assertSame('He110 W0R1D', $result);

		// With lowercase replacement enabled
		$result = Str::replaceListChar('Hello WORLD', ['O' => '0', 'L' => '1'], replaceLowercaseChar: true);
		$this->assertSame('He110 W0R1D', $result);
	}

	/* ===================== removeListeChar() ===================== */

	public function testRemoveListeChar(): void
	{
		// Basic removal (lowercase only by default)
		$result = Str::removeListeChar('Hello World', ['o', 'l']);
		$this->assertSame('He Wrd', $result);

		// With uppercase removal enabled
		$result = Str::removeListeChar('Hello WORLD', ['o', 'l'], replaceUppercaseChar: true);
		$this->assertSame('He WRD', $result);
	}

	/* ===================== sanitizeInput() ===================== */

	public function testSanitizeInput(): void
	{
		// ASCII string — unchanged
		$result = Str::sanitizeInput('hello world');
		$this->assertSame('hello world', $result);

		// Combining diacritical marks (e + combining acute) — composed to precomposed é by FORM_C
		$result = Str::sanitizeInput('e' . "\u{0301}"); // e with combining acute
		$this->assertSame('é', $result);

		// Precomposed accented characters — preserved by FORM_C normalization
		$result = Str::sanitizeInput('café');
		$this->assertSame('café', $result);

		// Mixed precomposed text — preserved by FORM_C
		$result = Str::sanitizeInput('Naïve façade');
		$this->assertSame('Naïve façade', $result);

		// French text with various accents — preserved by FORM_C normalization
		$result = Str::sanitizeInput('Éléphant français');
		$this->assertSame('Éléphant français', $result);

		// Curly quotes replaced with straight quotes by replaceAnnoyingChar()
		$result = Str::sanitizeInput('"Hello" – world');
		$this->assertSame('"Hello" - world', $result);

		// Em dash and en dash replaced with regular hyphen
		$result = Str::sanitizeInput('test — example – case');
		$this->assertSame('test - example - case', $result);

		// Empty string
		$result = Str::sanitizeInput('');
		$this->assertSame('', $result);
	}

	/* ===================== levenshtein() ===================== */

	public function testLevenshtein(): void
	{
		$this->assertSame(0, Str::levenshtein('test', 'test'));
		$this->assertSame(1, Str::levenshtein('test', 'tests'));
		$this->assertSame(1, Str::levenshtein('test', 'best')); // 1 substitution (t->b)
		$this->assertSame(3, Str::levenshtein('test', 'testing')); // 3 insertions
	}

	/* ===================== suggest() ===================== */

	public function testSuggest(): void
	{
		$dictionary = ['apple', 'application', 'apply', 'banana'];

		// Close match found
		$this->assertSame('apple', Str::suggest('aple', $dictionary));
		$this->assertSame('banana', Str::suggest('banane', $dictionary));

		// No match within distance
		$this->assertNull(Str::suggest('xyz', ['apple', 'banana']));
	}

	/* ===================== compare() ===================== */

	public function testCompare(): void
	{
		// Numeric comparison
		$this->assertSame(-1, Str::compare(5, 10));
		$this->assertSame(1, Str::compare(10, 5));
		$this->assertSame(0, Str::compare(5, 5));

		// String comparison
		$this->assertLessThan(0, Str::compare('apple', 'banana'));
		$this->assertGreaterThan(0, Str::compare('banana', 'apple'));
		$this->assertSame(0, Str::compare('test', 'test'));

		// Case sensitivity
		$this->assertNotSame(0, Str::compare('Test', 'test', caseSensitive: true));
		$this->assertSame(0, Str::compare('Test', 'test', caseSensitive: false));
	}

	/* ===================== truncateTextAtEnd() ===================== */

	public function testTruncateTextAtEnd(): void
	{
		// Standard truncation
		$result = Str::truncateTextAtEnd('This is a very long text', 12);
		$this->assertSame('This is a…', $result);
		$this->assertSame(10, mb_strlen($result));

		// Short string — no truncation
		$this->assertSame('Short', Str::truncateTextAtEnd('Short', 10));

		// Do not cut in the middle of a word
		$result = Str::truncateTextAtEnd('This is a test', 8, dontCutInMiddleOfWord: true);
		$this->assertSame('This is…', $result);
		$this->assertSame(8, mb_strlen($result));
	}

	/* ===================== truncateTextAtBeginning() ===================== */

	public function testTruncateTextAtBeginning(): void
	{
		$result = Str::truncateTextAtBeginning('This is a very long text', 10, dontCutInMiddleOfWord: true);
		$this->assertStringStartsWith('…', $result);
		$this->assertLessThanOrEqual(11, strlen($result)); // 10 + ellipsis
	}

	/* ===================== truncateTextInMiddle() ===================== */

	public function testTruncateTextInMiddle(): void
	{
		$result = Str::truncateTextInMiddle('This is a very long text to test', 15);
		$this->assertStringContainsString('[…]', $result);
	}

	/* ===================== ellipsize() ===================== */

	public function testEllipsize(): void
	{
		$result = Str::ellipsize('This is a very long text', 15);
		$this->assertLessThanOrEqual(20, strlen($result));
		$this->assertStringContainsString('&hellip;', $result);
	}

	/* ===================== isLowercase() ===================== */

	public function testIsLowercase(): void
	{
		$this->assertTrue(Str::isLowercase('hello'));
		$this->assertFalse(Str::isLowercase('Hello'));
		$this->assertFalse(Str::isLowercase('HELLO'));
	}

	/* ===================== isUppercase() ===================== */

	public function testIsUppercase(): void
	{
		$this->assertTrue(Str::isUppercase('HELLO'));
		$this->assertFalse(Str::isUppercase('Hello'));
		$this->assertFalse(Str::isUppercase('hello'));
	}

	/* ===================== hasLengthBetween() ===================== */

	public function testHasLengthBetween(): void
	{
		$this->assertTrue(Str::hasLengthBetween('test', 3, 5));
		$this->assertTrue(Str::hasLengthBetween('test', 4, 4));
		$this->assertFalse(Str::hasLengthBetween('test', 5, 10));
		$this->assertFalse(Str::hasLengthBetween('test', 1, 3));

		// Invalid range (min > max)
		$this->assertFalse(Str::hasLengthBetween('test', 10, 5));
	}

	/* ===================== isAlphabeticWithLength() ===================== */

	public function testIsAlphabeticWithLength(): void
	{
		$this->assertTrue(Str::isAlphabeticWithLength('test', 3, 5));
		$this->assertFalse(Str::isAlphabeticWithLength('test123', 3, 10));
		$this->assertFalse(Str::isAlphabeticWithLength('te', 3, 5));
	}

	/* ===================== isAlphanumericWithLength() ===================== */

	public function testIsAlphanumericWithLength(): void
	{
		$this->assertTrue(Str::isAlphanumericWithLength('test123', 5, 10));
		$this->assertFalse(Str::isAlphanumericWithLength('test-123', 5, 10));
		$this->assertFalse(Str::isAlphanumericWithLength('te', 5, 10));
	}

	/* ===================== isNumericWithLength() ===================== */

	public function testIsNumericWithLength(): void
	{
		$this->assertTrue(Str::isNumericWithLength('12345', 3, 10));
		$this->assertFalse(Str::isNumericWithLength('123a5', 3, 10));
		$this->assertFalse(Str::isNumericWithLength('12', 3, 10));

		// Leading zero handling
		$this->assertTrue(Str::isNumericWithLength('01234', 3, 10, canStartWithZero: true));
		$this->assertFalse(Str::isNumericWithLength('01234', 3, 10, canStartWithZero: false));
	}

	/* ===================== ctype_alpha_and_num() ===================== */

	public function testCtypeAlphaAndNum(): void
	{
		$this->assertTrue(Str::ctype_alpha_and_num('test123'));
		$this->assertFalse(Str::ctype_alpha_and_num('123456'));
		$this->assertFalse(Str::ctype_alpha_and_num('test-123'));
	}

	/* ===================== getStringWithBlank() ===================== */

	public function testGetStringWithBlank(): void
	{
		// Blanks appended at the end (default)
		$result = Str::getStringWithBlank('test', 10);
		$this->assertSame('test      ', $result);
		$this->assertSame(10, strlen($result));

		// Blanks prepended at the beginning
		$result = Str::getStringWithBlank('test', 10, addBlankInBeginning: true);
		$this->assertSame('      test', $result);
		$this->assertSame(10, strlen($result));
	}

	/* ===================== getStringWithSameChar() ===================== */

	public function testGetStringWithSameChar(): void
	{
		$this->assertSame('aaaaa', Str::getStringWithSameChar('a', 5));
		$this->assertSame('-----', Str::getStringWithSameChar('-', 5));
	}

	/* ===================== getNumberOccurrencesOfPreciseChar() ===================== */

	public function testGetNumberOccurrencesOfPreciseChar(): void
	{
		$this->assertSame(2, Str::getNumberOccurrencesOfPreciseChar('hello', 'l')); // 'hello' has 2 'l'
		$this->assertSame(1, Str::getNumberOccurrencesOfPreciseChar('hello', 'h'));
		$this->assertSame(0, Str::getNumberOccurrencesOfPreciseChar('hello', 'x'));
	}

	/* ===================== getNumberOccurrencesOfListChar() ===================== */

	public function testGetNumberOccurrencesOfListChar(): void
	{
		// Array input
		$this->assertSame(3, Str::getNumberOccurrencesOfListChar('hello', ['l', 'o'])); // 2 'l' + 1 'o' = 3
		$this->assertSame(2, Str::getNumberOccurrencesOfListChar('hello', ['h', 'e']));

		// String input
		$this->assertSame(3, Str::getNumberOccurrencesOfListChar('hello', 'lo')); // 2 'l' + 1 'o' = 3
	}

	/* ===================== containsOnlySameChar() ===================== */

	public function testContainsOnlySameChar(): void
	{
		$this->assertTrue(Str::containsOnlySameChar('aaaaa'));
		$this->assertTrue(Str::containsOnlySameChar('11111'));
		$this->assertFalse(Str::containsOnlySameChar('aaaba'));
	}

	/* ===================== containsOnlyDifferentChar() ===================== */

	public function testContainsOnlyDifferentChar(): void
	{
		$this->assertTrue(Str::containsOnlyDifferentChar('abcde'));
		$this->assertFalse(Str::containsOnlyDifferentChar('abcdea'));
		$this->assertFalse(Str::containsOnlyDifferentChar('aabcd'));
	}

	/* ===================== nbCharUniqueMinimum() ===================== */

	public function testNbCharUniqueMinimum(): void
	{
		$this->assertTrue(Str::nbCharUniqueMinimum('abcde', 3));
		$this->assertTrue(Str::nbCharUniqueMinimum('aabbcc', 3));
		$this->assertFalse(Str::nbCharUniqueMinimum('aabbcc', 5));
	}

	/* ===================== nbCharUniqueMaximum() ===================== */

	public function testNbCharUniqueMaximum(): void
	{
		$this->assertTrue(Str::nbCharUniqueMaximum('aabbcc', 5));
		$this->assertFalse(Str::nbCharUniqueMaximum('abcdefg', 5));
	}

	/* ===================== pluralize() ===================== */

	public function testPluralize(): void
	{
		// Three-form pattern
		$this->assertSame('Aucun item', Str::pluralize('{Aucun item|1 item|{#} items}', 0));
		$this->assertSame('1 item', Str::pluralize('{Aucun item|1 item|{#} items}', 1));
		$this->assertSame('5 items', Str::pluralize('{Aucun item|1 item|{#} items}', 5));

		// Two-form pattern
		$this->assertSame('1 item', Str::pluralize('{1 item|{#} items}', 1));
		$this->assertSame('5 items', Str::pluralize('{1 item|{#} items}', 5));
	}

	/* ===================== underscore() ===================== */

	public function testUnderscore(): void
	{
		$this->assertSame('hello_world', Str::underscore('Hello World'));
		$this->assertSame('test_string', Str::underscore('Test  String'));
	}

	/* ===================== humanize() ===================== */

	public function testHumanize(): void
	{
		$this->assertSame('Hello World', Str::humanize('hello_world'));
		$this->assertSame('Test String', Str::humanize('test_string'));
	}

	/* ===================== toSnakeCase() ===================== */

	public function testToSnakeCase(): void
	{
		$this->assertSame('hello_world', Str::toSnakeCase('HelloWorld'));
		$this->assertSame('test_string', Str::toSnakeCase('TestString'));
		$this->assertSame('my_class_name', Str::toSnakeCase('MyClassName'));
	}

	/* ===================== toCamelCase() ===================== */

	public function testToCamelCase(): void
	{
		$this->assertSame('helloWorld', Str::toCamelCase('hello_world'));
		$this->assertSame('testString', Str::toCamelCase('test_string'));
	}

	/* ===================== removeSpaces() ===================== */

	public function testRemoveSpaces(): void
	{
		// Default: spaces removed
		$this->assertSame('HelloWorld', Str::removeSpaces('Hello World'));
		$this->assertSame('Test', Str::removeSpaces('  Test  '));

		// With replacement character
		$this->assertSame('Hello-World', Str::removeSpaces('Hello World', '-'));
	}

	/* ===================== removeNonBreakingSpaces() ===================== */

	public function testRemoveNonBreakingSpaces(): void
	{
		$str = "Hello\xE2\x80\xAFWorld";
		$result = Str::removeNonBreakingSpaces($str);
		$this->assertSame('Hello World', $result);
	}

	/* ===================== removeLineBreak() ===================== */

	public function testRemoveLineBreak(): void
	{
		$str = "Hello\nWorld";
		$result = Str::removeLineBreak($str);
		$this->assertSame('HelloWorld', $result);
	}

	/* ===================== normalizeBreaks() ===================== */

	public function testNormalizeBreaks(): void
	{
		// Default: normalize to \r\n
		$result = Str::normalizeBreaks("Hello\nWorld\rTest\r\nEnd");
		$this->assertSame("Hello\r\nWorld\r\nTest\r\nEnd", $result);

		// Custom break type
		$result = Str::normalizeBreaks("Hello\nWorld\rTest", ' ');
		$this->assertSame('Hello World Test', $result);
	}

	/* ===================== removePunctuation() ===================== */

	public function testRemovePunctuation(): void
	{
		$result = Str::removePunctuation('Hello, World! How are you?');
		$this->assertSame('Hello World How are you', $result);

		// Double spaces created after punctuation removal must be cleaned
		$result = Str::removePunctuation('bonjour : je suis Benoit');
		$this->assertSame('bonjour je suis Benoit', $result);
		$this->assertStringNotContainsString('  ', $result);
	}

	/* ===================== removeEmoji() ===================== */

	public function testRemoveEmoji(): void
	{
		// Basic emoji
		$this->assertSame('Hello ', Str::removeEmoji('Hello 😀'));

		// Multiple emojis
		$this->assertSame('Hello  World', Str::removeEmoji('Hello 🎉 World'));

		// Flag emoji (regional indicators)
		$this->assertSame('Hello ', Str::removeEmoji('Hello 🇫🇷'));

		// ZWJ sequence (family emoji composed of multiple code points)
		$this->assertSame('Hello ', Str::removeEmoji('Hello 👨‍👩‍👧'));

		// Variation selector (text rendered as emoji)
		$this->assertSame('Hello ', Str::removeEmoji('Hello ☀️'));

		// No emoji — string should be unchanged
		$this->assertSame('Hello World', Str::removeEmoji('Hello World'));

		// Empty string
		$this->assertSame('', Str::removeEmoji(''));
	}

	/* ===================== replaceAnnoyingChar() ===================== */

	public function testReplaceAnnoyingChar(): void
	{
		$str = "Hello\xe2\x80\x93World"; // En dash
		$result = Str::replaceAnnoyingChar($str);
		$this->assertSame('Hello-World', $result);
	}

	/* ===================== reduceMultiples() ===================== */

	public function testReduceMultiples(): void
	{
		// Default: reduce consecutive duplicates
		$result = Str::reduceMultiples('Fred, Bill,, Joe, Jimmy', ',');
		$this->assertSame('Fred, Bill, Joe, Jimmy', $result);

		// With trim: also strip leading/trailing occurrences
		$result = Str::reduceMultiples(',,Fred, Bill,, Joe, Jimmy,,', ',', trim: true);
		$this->assertSame('Fred, Bill, Joe, Jimmy', $result);
	}

	/* ===================== increment() ===================== */

	public function testIncrement(): void
	{
		// Default separator (_)
		$this->assertSame('file_1', Str::increment('file'));
		$this->assertSame('file_2', Str::increment('file_1'));
		$this->assertSame('file_3', Str::increment('file_2'));

		// Custom separator
		$this->assertSame('file-1', Str::increment('file', '-'));
		$this->assertSame('file-2', Str::increment('file-1', '-'));
	}

	/* ===================== repeater() ===================== */

	public function testRepeater(): void
	{
		$this->assertSame('testtest', Str::repeater('test', 2));
		$this->assertSame('aaaaa', Str::repeater('a', 5));
		$this->assertSame('', Str::repeater('test', 0));
	}

	/* ===================== wrapWord() ===================== */

	public function testWrapWord(): void
	{
		// Short text — should not be wrapped
		$this->assertSame('Short text', Str::wrapWord('Short text', 50));

		// Long text — should be wrapped with newlines
		$result = Str::wrapWord('This is a very long text that needs to be wrapped at a certain character limit to ensure readability', 40);
		$this->assertStringContainsString("\n", $result);
		foreach (explode("\n", $result) as $line) {
			$this->assertLessThanOrEqual(41, strlen($line)); // Allow some flexibility
		}

		// URLs should not be wrapped
		$result = Str::wrapWord('Check this link http://example.com/very/long/url/that/should/not/be/wrapped please', 20);
		$this->assertStringContainsString('http://example.com/very/long/url/that/should/not/be/wrapped', $result);

		// Content within {unwrap}{/unwrap} tags should not be wrapped
		$result = Str::wrapWord('This is text {unwrap}this is a very long sentence that should not be wrapped at all{/unwrap} more text', 20);
		$this->assertStringContainsString('this is a very long sentence that should not be wrapped at all', $result);

		// Multiple spaces should be reduced to a single space
		$result = Str::wrapWord('Text  with    multiple     spaces', 50);
		$this->assertStringNotContainsString('  ', $result);
		$this->assertSame('Text with multiple spaces', $result);

		// Different line break types should be normalized
		$result = Str::wrapWord("Line1\r\nLine2\rLine3\nLine4", 50);
		$this->assertCount(4, explode("\n", $result));

		// Very long words exceeding charlim should be split
		$result = Str::wrapWord('Thisisaverylongwordthatexceedsthecharacterlimit', 20);
		$this->assertGreaterThan(1, count(explode("\n", $result)));
	}

	/* ===================== censorWord() ===================== */

	public function testCensorWord(): void
	{
		// Basic censoring
		$result = Str::censorWord('This is a bad word', ['bad']);
		$this->assertSame('This is a #### word', $result);

		// Custom replacement
		$result = Str::censorWord('This is a bad word', ['bad'], '***');
		$this->assertSame('This is a *** word', $result);

		// Multiple words censored
		$result = Str::censorWord('This is a bad word and another ugly thing', ['bad', 'ugly']);
		$this->assertSame('This is a #### word and another #### thing', $result);

		// Wildcard matching
		$result = Str::censorWord('This is a badword and another bad thing', ['bad*']);
		$this->assertSame('This is a #### and another #### thing', $result);

		// Case-insensitive by default
		$result = Str::censorWord('This is a BAD word and a Bad thing', ['bad']);
		$this->assertSame('This is a #### word and a #### thing', $result);

		// Empty censored list — string unchanged
		$result = Str::censorWord('This is a bad word', []);
		$this->assertSame('This is a bad word', $result);

		// Empty replacement — should use hash characters matching word length
		$result = Str::censorWord('This is a bad word', ['bad'], '');
		$this->assertStringContainsString('###', $result);

		// Words at start and end of sentence
		$result = Str::censorWord('bad word in the middle and ugly', ['bad', 'ugly']);
		$this->assertStringContainsString('####', $result);
		$this->assertStringStartsWith('####', $result);
		$this->assertStringEndsWith('####', $result);
	}

	/* ===================== removeAccents() ===================== */

	public function testRemoveAccents(): void
	{
		$this->assertSame('eeee', Str::removeAccents('éèêë'));
		$this->assertSame('aaaa', Str::removeAccents('àâäã'));
		$this->assertSame('AEOoe', Str::removeAccents('ÆØœ'));

		// String without accents should be unchanged
		$this->assertSame('Hello World', Str::removeAccents('Hello World'));
	}

	/* ===================== toURLFriendly() ===================== */

	public function testToURLFriendly(): void
	{
		$this->assertSame('hello-world', Str::toURLFriendly('Hello World'));
		$this->assertSame('cafe-paris', Str::toURLFriendly('Café Paris'));
		$this->assertSame('test-123', Str::toURLFriendly('Test 123'));
	}

	/* ===================== mb_ucfirst() ===================== */

	public function testMbUcfirst(): void
	{
		$this->assertSame('Hello', Str::mb_ucfirst('hello'));
		$this->assertSame('École', Str::mb_ucfirst('école'));
	}

	/* ===================== DEPRECATED METHODS (Backward Compatibility) ===================== */

	/**
	 * @deprecated Tests for checkLowercase() - Use isLowercase() instead
	 */
	public function testCheckLowercaseDeprecated(): void
	{
		$this->assertTrue(Str::checkLowercase('hello'));
		$this->assertFalse(Str::checkLowercase('Hello'));
		$this->assertFalse(Str::checkLowercase('HELLO'));
	}

	/**
	 * @deprecated Tests for checkUppercase() - Use isUppercase() instead
	 */
	public function testCheckUppercaseDeprecated(): void
	{
		$this->assertTrue(Str::checkUppercase('HELLO'));
		$this->assertFalse(Str::checkUppercase('Hello'));
		$this->assertFalse(Str::checkUppercase('hello'));
	}

	/**
	 * @deprecated Tests for checkLength() - Use hasLengthBetween() instead
	 */
	public function testCheckLengthDeprecated(): void
	{
		$this->assertTrue(Str::checkLength('test', 3, 5));
		$this->assertTrue(Str::checkLength('test', 4, 4));
		$this->assertFalse(Str::checkLength('test', 5, 10));
		$this->assertFalse(Str::checkLength('test', 1, 3));
	}

	/**
	 * @deprecated Tests for checkForAlphabeticCharacters() - Use isAlphabeticWithLength() instead
	 */
	public function testCheckForAlphabeticCharactersDeprecated(): void
	{
		$this->assertTrue(Str::checkForAlphabeticCharacters('test', 3, 5));
		$this->assertFalse(Str::checkForAlphabeticCharacters('test123', 3, 10));
		$this->assertFalse(Str::checkForAlphabeticCharacters('te', 3, 5));
	}

	/**
	 * @deprecated Tests for checkForAlphanumericCharacters() - Use isAlphanumericWithLength() instead
	 */
	public function testCheckForAlphanumericCharactersDeprecated(): void
	{
		$this->assertTrue(Str::checkForAlphanumericCharacters('test123', 5, 10));
		$this->assertFalse(Str::checkForAlphanumericCharacters('test-123', 5, 10));
		$this->assertFalse(Str::checkForAlphanumericCharacters('te', 5, 10));
	}

	/**
	 * @deprecated Tests for checkForNumericCharacters() - Use isNumericWithLength() instead
	 */
	public function testCheckForNumericCharactersDeprecated(): void
	{
		$this->assertTrue(Str::checkForNumericCharacters('12345', 3, 10));
		$this->assertFalse(Str::checkForNumericCharacters('123a5', 3, 10));
		$this->assertFalse(Str::checkForNumericCharacters('12', 3, 10));

		// Leading zero handling
		$this->assertTrue(Str::checkForNumericCharacters('01234', 3, 10, canStartWithZero: true));
		$this->assertFalse(Str::checkForNumericCharacters('01234', 3, 10, canStartWithZero: false));
	}
}