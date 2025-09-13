# Comprehensive Test Plan for 84EM Gravity Forms AI Analysis Plugin

## Test Coverage Target: 90%+

This document outlines the comprehensive testing strategy for achieving at least 90% code coverage without using mocks. All tests interact with real WordPress functions, database operations, and plugin features.

## Component Test Plans

### 1. Core/Encryption.php Test Plan

| Test Case | Objective | Inputs | Expected Output | Test Type |
|-----------|-----------|--------|-----------------|-----------|
| test_encrypt_valid_data | Verify data encryption works | Valid API key string | Encrypted string different from input | Positive |
| test_encrypt_empty_data | Test encryption with empty input | Empty string | Returns false | Negative |
| test_decrypt_valid_data | Verify decryption returns original | Encrypted data | Original plaintext | Positive |
| test_decrypt_empty_data | Test decryption with empty input | Empty string | Returns false | Negative |
| test_decrypt_invalid_data | Test decryption with corrupted data | Invalid encrypted string | Returns false | Negative |
| test_save_api_key | Test saving API key to database | Valid API key | Key saved encrypted in options | Positive |
| test_save_empty_api_key | Test saving empty API key | Empty string | Returns false | Negative |
| test_get_api_key | Test retrieving saved API key | - | Decrypted API key | Positive |
| test_get_nonexistent_api_key | Test getting key when none saved | - | Returns false | Negative |
| test_has_api_key | Test checking API key existence | - | Boolean result | Positive |
| test_delete_api_key | Test removing API key | - | Key removed from options | Positive |
| test_encryption_with_missing_auth_key | Test when AUTH_KEY not defined | Valid data | Exception thrown | Edge case |
| test_encryption_with_missing_auth_salt | Test when AUTH_SALT not defined | Valid data | Exception thrown | Edge case |
| test_encryption_with_default_auth_key | Test with default WP auth key | Valid data | Exception thrown | Security |
| test_encryption_consistency | Test encrypt/decrypt cycle | Various strings | Original data preserved | Integration |

### 2. Core/APIHandler.php Test Plan

| Test Case | Objective | Inputs | Expected Output | Test Type |
|-----------|-----------|--------|-----------------|-----------|
| test_analyze_success | Test successful API call | Valid prompt and data | Success with AI response | Positive |
| test_analyze_disabled | Test when AI is disabled | Any input | Error: AI disabled | Negative |
| test_analyze_no_api_key | Test without API key | Valid prompt | Error: No API key | Negative |
| test_analyze_rate_limiting | Test rate limit enforcement | Multiple rapid requests | Delays between requests | Positive |
| test_analyze_api_error | Test API error handling | Valid prompt (mock error) | Error with message | Negative |
| test_analyze_network_error | Test network failure | Valid prompt (mock timeout) | WP_Error handled | Negative |
| test_analyze_invalid_response | Test malformed API response | Valid prompt (bad response) | Error: Invalid format | Negative |
| test_test_connection | Test connection testing | - | Success or failure | Positive |
| test_prepare_message_all_vars | Test message with all variables | Full data array | Formatted message | Positive |
| test_prepare_message_partial_vars | Test with missing variables | Partial data | Message with blanks | Edge case |
| test_format_form_data | Test form data formatting | Various field types | Formatted string | Positive |
| test_format_form_data_empty | Test empty form data | Empty array | Empty string | Edge case |
| test_format_form_data_arrays | Test array field values | Array values | Comma-separated | Positive |
| test_logging_enabled | Test request logging | Valid request | Log entry created | Positive |
| test_logging_disabled | Test with logging off | Valid request | No log created | Negative |
| test_log_purging | Test old log deletion | Old log entries | Logs purged | Positive |
| test_ensure_log_table | Test table creation | - | Table exists | Positive |
| test_web_search_instruction | Test search prompt addition | Name and company | Includes search text | Positive |

### 3. Core/EntryProcessor.php Test Plan

| Test Case | Objective | Inputs | Expected Output | Test Type |
|-----------|-----------|--------|-----------------|-----------|
| test_process_entry_success | Test successful processing | Valid entry and form | Analysis saved to meta | Positive |
| test_process_entry_disabled_global | Test with global AI disabled | Valid entry | Returns error | Negative |
| test_process_entry_disabled_form | Test with form AI disabled | Valid entry | Returns error | Negative |
| test_process_entry_no_mapping | Test auto-field selection | Entry with no mapping | All fields analyzed | Positive |
| test_process_entry_with_mapping | Test specific field mapping | Entry with field list | Only mapped fields | Positive |
| test_process_entry_custom_prompt | Test custom form prompt | Entry with custom prompt | Custom prompt used | Positive |
| test_get_all_analyzable_fields | Test field filtering | Form with various fields | Excludes system fields | Positive |
| test_collect_form_data | Test data collection | Entry with all field types | Formatted data array | Positive |
| test_get_field_value_text | Test text field extraction | Text field | Field value | Positive |
| test_get_field_value_name | Test name field extraction | Name field | Combined name | Positive |
| test_get_field_value_email | Test email extraction | Email field | Email value | Positive |
| test_get_field_value_address | Test address extraction | Address field | Formatted address | Positive |
| test_get_field_value_checkbox | Test checkbox extraction | Checkbox field | Selected values | Positive |
| test_get_field_value_list | Test list field extraction | List field | Formatted list | Positive |
| test_get_field_value_file | Test file upload extraction | File field | File name | Positive |
| test_extract_name | Test name detection | Form with name field | Extracted name | Positive |
| test_extract_email | Test email detection | Form with email field | Extracted email | Positive |
| test_extract_company | Test company detection | Form with company field | Company name | Positive |
| test_display_ai_analysis | Test sidebar display | Entry with analysis | HTML output | Integration |
| test_ajax_analyze_entry | Test manual analysis | AJAX request | Success response | Integration |
| test_ajax_analyze_no_permission | Test permission check | Unauthorized request | Error response | Security |
| test_ajax_download_html | Test HTML export | Entry with analysis | HTML document | Integration |
| test_ajax_download_no_analysis | Test export without data | Entry without analysis | Error response | Negative |
| test_apply_filters | Test WordPress filters | Entry processing | Filters applied | Integration |
| test_do_actions | Test WordPress actions | Entry processing | Actions fired | Integration |
| test_error_meta_storage | Test error storage | Failed analysis | Error in meta | Negative |

### 4. Admin/Settings.php Test Plan

| Test Case | Objective | Inputs | Expected Output | Test Type |
|-----------|-----------|--------|-----------------|-----------|
| test_add_admin_menu | Test menu registration | - | Menu items added | Positive |
| test_register_settings | Test settings registration | - | Settings registered | Positive |
| test_enqueue_admin_assets | Test asset loading | Admin page context | Scripts/styles loaded | Positive |
| test_enqueue_non_admin_page | Test asset loading elsewhere | Non-plugin page | No assets loaded | Negative |
| test_settings_page_render | Test settings page HTML | - | Valid HTML output | Positive |
| test_settings_page_no_permission | Test unauthorized access | Non-admin user | Access denied | Security |
| test_update_api_key | Test API key update | New API key | Key encrypted and saved | Positive |
| test_update_empty_api_key | Test empty API key update | Empty string | Error message | Negative |
| test_mappings_page_render | Test mappings page | Forms exist | Form list displayed | Positive |
| test_mappings_no_gravity_forms | Test without GF active | No GF | Error message | Negative |
| test_logs_page_render | Test logs page | Log entries | Table with logs | Positive |
| test_logs_pagination | Test log pagination | Many logs | Paginated display | Positive |
| test_clear_logs | Test log clearing | Clear request | Logs deleted | Positive |
| test_ajax_save_field_mapping | Test field mapping save | Form mapping data | Settings saved | Integration |
| test_ajax_save_no_permission | Test unauthorized save | Non-admin request | Access denied | Security |
| test_ajax_test_api | Test API connection test | - | Success/failure | Integration |
| test_ajax_get_log_details | Test log detail retrieval | Log ID | Log details JSON | Integration |
| test_ajax_invalid_log_id | Test invalid log request | Bad ID | Error response | Negative |
| test_settings_sanitization | Test input sanitization | Various inputs | Sanitized values | Security |
| test_nonce_verification | Test CSRF protection | Invalid nonce | Request rejected | Security |
| test_model_selection | Test model dropdown | - | All models available | Positive |
| test_settings_defaults | Test default values | No settings | Defaults applied | Positive |

### 5. Main Plugin File Test Plan

| Test Case | Objective | Inputs | Expected Output | Test Type |
|-----------|-----------|--------|-----------------|-----------|
| test_plugin_singleton | Test singleton pattern | Multiple calls | Same instance | Positive |
| test_plugin_activation | Test activation hook | - | Default options set | Positive |
| test_plugin_deactivation | Test deactivation hook | - | Cleanup performed | Positive |
| test_check_dependencies | Test GF dependency check | No GF | Warning displayed | Negative |
| test_check_dependencies_met | Test with GF active | GF active | No warning | Positive |
| test_load_textdomain | Test internationalization | - | Text domain loaded | Positive |
| test_add_settings_link | Test plugin page link | - | Settings link added | Positive |
| test_autoloader | Test class autoloading | Class name | Class loaded | Positive |
| test_autoloader_invalid_class | Test non-plugin class | Other namespace | Not loaded | Negative |
| test_constants_defined | Test plugin constants | - | All constants set | Positive |
| test_load_components | Test component initialization | GF active | Components loaded | Positive |
| test_no_load_without_gf | Test without GF | No GF | Components not loaded | Negative |
| test_set_default_options | Test option initialization | - | All defaults set | Positive |
| test_preserve_existing_options | Test option preservation | Existing options | Not overwritten | Positive |
| test_plugin_version | Test version constant | - | Version defined | Positive |

### 6. JavaScript Test Plan

| Test Case | Objective | Inputs | Expected Output | Test Type |
|-----------|-----------|--------|-----------------|-----------|
| test_tab_navigation | Test tab switching | Tab click | Content switched | UI |
| test_hash_navigation | Test URL hash handling | Hash in URL | Correct tab shown | UI |
| test_field_mapping_save | Test mapping AJAX save | Form fields | Save success | Integration |
| test_api_test_button | Test API test function | Button click | Test performed | Integration |
| test_select_all_fields | Test select all | Click select all | All checked | UI |
| test_select_none_fields | Test deselect all | Click select none | None checked | UI |
| test_log_details_modal | Test log modal | View details click | Modal shown | UI |
| test_modal_close | Test modal closing | ESC key or click | Modal hidden | UI |
| test_auto_save | Test auto-save feature | Field change | Save after delay | UI |
| test_character_counter | Test prompt counter | Text input | Count updated | UI |
| test_clear_logs_confirm | Test confirmation dialog | Clear logs click | Confirm shown | UI |
| test_copy_to_clipboard | Test copy function | Copy button | Text copied | UI |

### 7. Integration Test Plan

| Test Case | Objective | Inputs | Expected Output | Test Type |
|-----------|-----------|--------|-----------------|-----------|
| test_full_submission_flow | Test complete form submission | Form submission | Analysis completed | E2E |
| test_manual_reanalysis | Test re-analyze button | Existing entry | New analysis | E2E |
| test_html_export_flow | Test export feature | Entry with analysis | HTML generated | E2E |
| test_settings_to_analysis | Test settings impact | Changed settings | Behavior updated | E2E |
| test_form_specific_settings | Test per-form config | Form settings | Form-specific behavior | E2E |
| test_logging_lifecycle | Test log creation/deletion | Multiple analyses | Logs managed | E2E |
| test_api_failure_recovery | Test error handling | API failure | Graceful degradation | E2E |
| test_permission_flow | Test user permissions | Various user roles | Proper access control | Security |
| test_filter_hook_integration | Test WP integration | Filters/actions | Properly integrated | E2E |
| test_database_transactions | Test data integrity | Concurrent operations | Data consistent | E2E |

## Test Execution Strategy

### Setup Requirements
1. WordPress Test Suite installed
2. MySQL test database configured
3. PHPUnit 9.x installed
4. Gravity Forms mock or actual installation
5. Test data fixtures prepared

### Test Organization
```
tests/
├── bootstrap.php           # Test bootstrap
├── class-test-case.php     # Base test class
├── TEST-PLAN.md           # This document
├── unit/
│   ├── test-encryption.php
│   ├── test-api-handler.php
│   ├── test-entry-processor.php
│   ├── test-settings.php
│   └── test-plugin.php
├── integration/
│   └── test-integration.php
└── fixtures/
    └── test-data.php
```

### Coverage Metrics
- **Target Coverage**: 90%+
- **Critical Paths**: 100% coverage required
- **Security Functions**: 100% coverage required
- **Error Handling**: 95%+ coverage required
- **UI/Admin**: 85%+ coverage acceptable

### Test Execution Commands
```bash
# Run all tests
phpunit

# Run with coverage report
phpunit --coverage-html coverage-report

# Run specific test suite
phpunit --testsuite unit
phpunit --testsuite integration

# Run specific test file
phpunit tests/unit/test-encryption.php

# Run with verbose output
phpunit -v
```

## Security Testing Focus

1. **Input Validation**: All user inputs properly sanitized
2. **SQL Injection**: Database queries properly prepared
3. **XSS Prevention**: Output properly escaped
4. **CSRF Protection**: Nonces verified on all actions
5. **Permission Checks**: Capabilities verified
6. **API Key Security**: Encryption and secure storage

## Performance Testing Considerations

1. **Database Queries**: Efficient queries, proper indexing
2. **API Rate Limiting**: Proper delay implementation
3. **Memory Usage**: Large data set handling
4. **Caching**: Proper cache utilization
5. **Bulk Operations**: Efficient batch processing

## Continuous Integration Setup

```yaml
# .github/workflows/tests.yml example
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.0'
          coverage: xdebug
      - name: Install dependencies
        run: composer install
      - name: Setup WordPress Tests
        run: bash bin/install-wp-tests.sh wordpress_test root root localhost latest
      - name: Run tests
        run: phpunit --coverage-clover=coverage.xml
      - name: Upload coverage
        uses: codecov/codecov-action@v2
```

## Success Criteria

1. ✅ All tests pass consistently
2. ✅ Code coverage ≥ 90%
3. ✅ No critical security issues
4. ✅ Performance benchmarks met
5. ✅ WordPress coding standards followed
6. ✅ No memory leaks or resource issues
7. ✅ Compatible with PHP 7.4 - 8.2
8. ✅ Compatible with WordPress 6.0+
9. ✅ Compatible with Gravity Forms 2.5+