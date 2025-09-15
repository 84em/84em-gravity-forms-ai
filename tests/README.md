# 84EM Gravity Forms AI - Test Suite

## Setup Instructions

### 1. Install Dependencies

```bash
composer install
```

### 2. Install WordPress Test Suite

Run the installation script with your local database credentials:

```bash
./bin/install-wp-tests.sh local root root localhost latest true
```

Note: We use `true` at the end to skip database creation since we're using the existing 'local' database.

### 3. Configure Database Password

If your local MySQL uses a password, update the test configuration:

```bash
# Edit /tmp/wordpress-tests-lib/wp-tests-config.php
# Change the DB_PASSWORD line to match your local setup
define( 'DB_PASSWORD', 'root' );  # Or your actual password
```

## Running Tests

### Run All Tests

```bash
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
./vendor/bin/phpunit
```

### Run Specific Test Suite

```bash
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
./vendor/bin/phpunit --testsuite unit
./vendor/bin/phpunit --testsuite integration
```

### Run Specific Test File

```bash
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
./vendor/bin/phpunit tests/SimpleTest.php
```

### Run Tests by Filter

```bash
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
# Run only delete-related tests
./vendor/bin/phpunit --filter "delete"
# Run tests with readable output
./vendor/bin/phpunit --testdox
```

### Run Tests with Coverage Report

```bash
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
./vendor/bin/phpunit --coverage-html coverage-report
```

Then open `coverage-report/index.html` in your browser.

## Test Structure

- `tests/bootstrap.php` - WordPress test bootstrap file
- `tests/class-test-case.php` - Base test case class with helper methods
- `tests/unit/` - Unit tests for individual components (files must end with `Test.php`)
- `tests/integration/` - Integration tests for end-to-end workflows
- `tests/SimpleTest.php` - Basic test to verify PHPUnit setup
- `phpunit.xml.dist` - PHPUnit configuration file

## Test Coverage

### Unit Tests

#### EntryProcessorTest.php (Enhanced with Delete Functionality)
- Entry processing with AI analysis
- Field mapping and data collection
- Custom prompts and filters
- AJAX entry analysis
- **Delete Analysis Tests (NEW):**
  - `test_ajax_delete_analysis_success` - Verifies successful deletion with admin permissions
  - `test_ajax_delete_analysis_no_permission` - Tests permission checks for unauthorized users
  - `test_ajax_delete_analysis_invalid_nonce` - Validates security nonce verification
  - `test_ajax_delete_analysis_invalid_entry` - Handles non-existent entry deletion attempts
  - `test_ajax_delete_removes_error_meta` - Ensures all meta data is cleaned up
  - `test_display_shows_delete_button_with_analysis` - Confirms delete button appears when analysis exists
  - `test_display_no_delete_button_without_analysis` - Verifies no delete button when no analysis present
  - `test_display_includes_delete_confirmation` - Checks JavaScript confirmation dialog implementation

#### APIHandlerTest.php
- Claude API communication
- Rate limiting
- Request/response logging
- Error handling

#### EncryptionTest.php
- API key encryption/decryption
- Security key validation

#### SettingsTest.php
- Admin settings pages
- Field mapping UI
- Log management

### Integration Tests
- End-to-end form submission processing
- Complete analysis workflow
- Settings persistence

## Writing Tests

Tests should extend `WP_UnitTestCase` for basic tests or `EightyFourEM\GravityFormsAI\Tests\TestCase` for tests that need our custom helper methods.

Example:

```php
class MyTest extends WP_UnitTestCase {
    public function test_something() {
        $this->assertTrue( true );
    }
}
```

## Troubleshooting

### Database Connection Errors

If you get database connection errors, make sure:
1. MySQL is running locally
2. The 'local' database exists
3. The password in `/tmp/wordpress-tests-lib/wp-tests-config.php` is correct

### Class Not Found Errors

PHPUnit expects test class names to match the file name. For example:
- File: `tests/MyTest.php` (note: ends with `Test.php`)
- Class: `class MyTest extends WP_UnitTestCase`

Important: Test files must end with `Test.php` (not start with `test-`) for PHPUnit to discover them.

### Constant Already Defined Warnings

These warnings are normal and can be ignored. They occur because constants are defined in both the test configuration and the WordPress bootstrap.