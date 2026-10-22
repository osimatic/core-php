# Claude Development Guidelines - core-php

This file contains the development guidelines for the core-php project. Claude will automatically follow these rules when working on this codebase.

## 🗣️ Language Requirements

- **Communication:** French (je te parle en français, parle-moi en français)
- **Code:** English only (code, variables, functions, classes, comments, documentation)

## 📝 Documentation Standards

- All code must have PHPDoc documentation in English
- Include reference links when relevant (PHP docs, Wikipedia, standards, etc.)
- Format: No line breaks in the middle of sentences
- Comments should be in English and added when necessary

Example:
```php
/**
 * Retrieves detailed information about a specific reservation.
 * Returns comprehensive data including guest info and pricing.
 *
 * @param int $reservationId The unique identifier of the reservation
 * @link https://docs.example.com/reservations Reservation API documentation
 * @return array|null The reservation details if successful, null on failure
 */
```

## 🏗️ Code Organization

- Classes are organized in sections (Constants, Properties, Constructor, Methods by category, etc.)
- Add new code to the appropriate section, **not at the end**
- Follow existing code conventions
- Keep related methods together

## 🔄 Method Renaming

When renaming a method (production code requirement):
1. Create the new method with the improved name
2. Keep the old method as a deprecated alias
3. Document both methods properly

```php
public function getDataDirectory(): string { /* implementation */ }

/**
 * @deprecated Use getDataDirectory() instead
 */
public function getDataDir(): string
{
    return $this->getDataDirectory();
}
```

## 🧪 Testing Requirements

### Test Organization
- Tests must mirror the class structure **exactly**
- Same sections, same order as the tested class
- **One test method per tested function** (exceptions for complexity)
  - `testMethodName()` tests **ALL** cases of `methodName()`
  - Do NOT create `testMethodNameWithCase1()`, `testMethodNameWithCase2()`
  - All test cases must be in a SINGLE test method
- Naming: `testMethodName()` tests `methodName()`
- Check if test already exists before creating

### Test Quality
- **Comprehensive coverage:** Test all possible cases, including edge cases
- Write complex mocks when necessary
- **Critical:** Do NOT regress tests on failure
  - If a test fails, check implementation first
  - The test might be correct and revealing a bug
  - Only modify test if there's an obvious error in the test itself

Example test structure:
```php
class JsonDBTest extends TestCase
{
    // ========================================
    // Constructor & Configuration Tests
    // ========================================

    public function testConstructor(): void { }
    public function testSetDataDirectory(): void { }

    // ========================================
    // File Operations Methods Tests
    // ========================================

    public function testRead(): void { }
    public function testWrite(): void { }
}
```

## 🔍 Code Review Philosophy

### Be Proactive
When reading files, report **any** issues found, even if unrelated to the current task:
- Bugs
- French code (should be English)
- Poorly named methods
- Missing documentation
- Organization problems
- Code smells

### User Feedback
- If a modification is rejected, **do not propose it again** (unless explicitly asked)
- Listen to feedback and adjust approach

## 🎯 Class Improvement Requests

When asked to "improve a class", provide proposals for:
1. **Bug fixes** - Any issues found
2. **Documentation** - Missing or incomplete PHPDoc
3. **Translation** - French → English
4. **Organization** - Better section structure
5. **Method additions** - Useful missing methods
6. **Method renaming** - Better names (with deprecated aliases)
7. **Method refactoring** - Merge, split, or simplify
8. **Cleanup** - Remove obsolete methods

## ✅ Best Practices

### Method Design
- Validate parameters early
- Return null on failure for optional operations
- Return bool for success/failure operations
- Use type hints strictly
- Log errors appropriately

### Error Handling
```php
if ($id <= 0) {
    $this->logger->error('Invalid ID. Must be a positive integer.');
    return null;
}
```

### Documentation Links
Always include relevant reference links:
- PHP manual for PHP classes/functions
- Wikipedia for concepts/patterns
- Official API docs for integrations
- Standards (RFC, ISO) when applicable

## 🚀 Project Context

- **Project:** core-php (Osimatic PHP helper library)
- **Status:** Production code
- **PHP Version:** 8.0+
- **Testing:** PHPUnit
- **Requirements:** Backward compatibility must be maintained

## 📚 Example Patterns

### Class Structure
```php
class Example
{
    // ========================================
    // Constants
    // ========================================

    public const string API_URL = 'https://api.example.com';

    // ========================================
    // Properties
    // ========================================

    private string $apiKey;

    // ========================================
    // Constructor & Configuration
    // ========================================

    public function __construct(string $apiKey) { }

    // ========================================
    // Public Methods
    // ========================================

    public function getData(): ?array { }

    // ========================================
    // Helper Methods
    // ========================================

    private function sendRequest(): ?array { }
}
```

### Test Structure
```php
class ExampleTest extends TestCase
{
    // Match class sections exactly

    // ========================================
    // Constructor & Configuration Tests
    // ========================================

    public function testConstructor(): void
    {
        // Valid case
        $obj = new Example('valid');
        $this->assertInstanceOf(Example::class, $obj);

        // Invalid case
        try {
            new Example('');
            $this->fail('Expected exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true);
        }

        // Edge case
        // ... all other cases in this ONE method
    }

    // ========================================
    // Public Methods Tests
    // ========================================

    public function testGetData(): void
    {
        // All test cases for getData() in ONE method
    }
}
```

**❌ WRONG - Multiple test methods:**
```php
public function testConstructor(): void { }
public function testConstructorWithInvalidParameter(): void { }
public function testConstructorWithEdgeCase(): void { }
```

**✅ CORRECT - One test method with all cases:**
```php
public function testConstructor(): void
{
    // Valid case
    // Invalid case
    // Edge case
    // All cases in ONE method
}
```

---

**Note:** These guidelines ensure code quality, maintainability, and consistency across the core-php project. All contributors (human or AI) should follow these standards.
