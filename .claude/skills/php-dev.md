# PHP Development Skill - core-php Project

This skill configures Claude to follow the specific development guidelines for the core-php project.

## Language & Communication
- **Communication language:** French (respond in French)
- **Code language:** English only (code, comments, documentation)

## Code Standards

### Documentation
- All code must be documented with PHPDoc in English
- Include one or more reference links when relevant (PHP documentation, Wikipedia, standards, etc.)
- No line breaks in the middle of sentences in documentation
- Comments should be in English and added when necessary for clarity

### Code Style
- Follow existing code conventions in the project
- Code must be written in English (variables, functions, classes, etc.)
- Organize classes in sections - add new code to the appropriate section, not at the end
- When renaming a method, keep the old name as a deprecated alias (production code requirement)

### Method Improvements
When asked to improve a class, provide proposals for:
- Bug fixes
- Documentation improvements
- English translation of French code
- Section organization
- Method additions
- Method renaming (with deprecated aliases)
- Method merging or separation
- Deletion of obsolete methods

## Testing Requirements

### Test Coverage
- All code must be thoroughly tested with comprehensive test coverage
- Cover all possible cases, including edge cases
- Write complex mocks when necessary
- Tests must be organized in sections matching exactly the tested class structure
- Tests must be in the same order as the tested class
- **One test method per tested function** (exceptions allowed for complexity)
  - `testMethodName()` tests ALL cases of `methodName()`
  - Do NOT create multiple test methods like `testMethodNameWithCase1()`, `testMethodNameWithCase2()`
  - All test cases should be in a SINGLE test method
- Test method naming: `testMethodName` for testing `methodName()`
- Check if a test method already exists before creating a new one

### Test Philosophy
- **Critical:** Do NOT regress tests when encountering failures
- If a test fails, check if there's a bug in the implementation first
- Consider improving the implementation rather than modifying the test
- The test might be correct and revealing an actual issue
- Only modify tests if there's an obvious error in the test itself

## Code Review Behavior

### Proactive Quality Checks
Be proactive when reading files. Report any issues found, even if unrelated to the current request:
- Bugs discovered
- French code that should be in English
- Poorly named methods
- Missing documentation
- Code organization issues
- Any other anomalies

### User Rejections
- When a modification is rejected, do not propose the same modification again
- Exception: If explicitly requested by the user after an accidental rejection

## Project Context
- Project: core-php (Osimatic PHP helper library)
- Production code: Changes must maintain backward compatibility
- Method deprecation: Use @deprecated annotation and create aliases

## Examples

### Good Method Renaming
```php
/**
 * Gets the data directory path.
 * @return string The absolute path to the data directory
 */
public function getDataDirectory(): string
{
    return $this->dataDir;
}

/**
 * Gets the data directory path.
 * @deprecated Use getDataDirectory() instead
 * @return string The absolute path to the data directory
 */
public function getDataDir(): string
{
    return $this->getDataDirectory();
}
```

### Good Test Organization
```php
class ExampleTest extends TestCase
{
    // ========================================
    // Constructor Methods Tests
    // ========================================

    public function testConstructor(): void
    {
        // Test valid construction
        $obj = new Example('valid');
        $this->assertInstanceOf(Example::class, $obj);

        // Test with invalid parameter
        try {
            new Example('');
            $this->fail('Expected exception for empty parameter');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot be empty', $e->getMessage());
        }

        // Test with null parameter
        try {
            new Example(null);
            $this->fail('Expected exception for null parameter');
        } catch (\TypeError $e) {
            $this->assertTrue(true);
        }
    }

    // ========================================
    // Configuration Methods Tests
    // ========================================

    public function testSetConfiguration(): void
    {
        // ALL test cases for setConfiguration in ONE method
    }

    public function testGetConfiguration(): void
    {
        // ALL test cases for getConfiguration in ONE method
    }
}
```

**Bad Example (DO NOT DO THIS):**
```php
// ❌ Wrong: Multiple test methods for one function
public function testConstructor(): void { }
public function testConstructorWithEmptyParameter(): void { }
public function testConstructorWithNullParameter(): void { }

// ✅ Correct: One test method with all cases
public function testConstructor(): void
{
    // Valid case, empty case, null case, etc.
}
```

### Good PHPDoc
```php
/**
 * Retrieves a list of reservations with optional filtering.
 * Creates a DateTime object for each day from start to end (inclusive).
 *
 * @param array $filters Optional filters. Supported keys:
 *                       - 'from' (string): Start date (YYYY-MM-DD format)
 *                       - 'to' (string): End date (YYYY-MM-DD format)
 * @link https://www.php.net/manual/en/class.datetime.php DateTime documentation
 * @return array|null Array of reservations if successful, null on failure
 */
public function getReservations(array $filters = []): ?array
```

## Usage
Invoke this skill with `/php-dev` when working on PHP development tasks for this project.
