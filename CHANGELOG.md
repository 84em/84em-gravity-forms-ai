# Changelog

All notable changes to the 84EM Gravity Forms Entry AI Analysis plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.2] - 2025-09-15

### Added
- Comprehensive test coverage for delete functionality (8 new tests)
- Tests for AJAX delete handler with various scenarios
- Tests for delete button UI rendering conditions
- Tests for permission and security checks
- Updated test documentation in tests/README.md

### Testing
- `test_ajax_delete_analysis_success` - Verifies successful deletion
- `test_ajax_delete_analysis_no_permission` - Tests permission checks
- `test_ajax_delete_analysis_invalid_nonce` - Validates nonce security
- `test_ajax_delete_analysis_invalid_entry` - Handles invalid entries
- `test_ajax_delete_removes_error_meta` - Ensures complete cleanup
- `test_display_shows_delete_button_with_analysis` - Button display logic
- `test_display_no_delete_button_without_analysis` - Conditional rendering
- `test_display_includes_delete_confirmation` - Confirmation dialog

## [1.1.1] - 2025-09-15

### Added
- Delete button in AI Analysis metabox to remove analysis data from entries
- Confirmation dialog before deleting analysis to prevent accidental removal
- Audit trail via entry notes when analysis is deleted
- New AJAX handler `84em_gf_ai_delete_analysis` with proper permission checks
- New action hook `84em_gf_ai_analysis_deleted` fired after deletion
- Trash icon indicator for delete button with red color styling

### Changed
- Updated button layout in AI Analysis metabox to accommodate delete action
- Enhanced security with `gravityforms_edit_entries` capability requirement for deletion

## [1.1.0] - 2025-09-13

### Added
- Comprehensive PHPUnit test suite with 127+ test methods
- Test infrastructure including bootstrap, base test case, and helper methods
- Unit tests for all core components (Encryption, APIHandler, EntryProcessor, Settings, Plugin)
- Integration tests for end-to-end workflows
- WordPress test environment configuration
- Composer dependency management for testing
- PHPUnit Polyfills support for WordPress testing
- Test documentation and setup instructions
- Code coverage reporting capabilities
- Test installation script for WordPress test suite

### Changed
- Updated .gitignore to exclude test cache files and coverage reports

### Security
- Added AUTH_KEY and AUTH_SALT validation in Encryption class
- Improved error handling for missing WordPress security keys

### Testing
- Achieved 90%+ code coverage across all components
- All tests run without mocks, using real WordPress functions
- Tests validate security, error handling, and edge cases

## [1.0.0] - 2025-09-13

### Added
- Initial release of 84EM Gravity Forms Entry AI Analysis plugin
- Claude AI integration for analyzing Gravity Forms submissions
- Secure API key encryption using AES-256-CBC
- Markdown-based analysis storage in entry meta
- HTML report generation with client-side markdown conversion
- Admin interface with three configuration pages (Settings, Advanced Settings, Logs)
- Smart field mapping with auto-detection of analyzable fields
- Rate limiting for API requests
- Comprehensive logging system with automatic purging
- Support for multiple Claude AI models including:
  - Claude Opus 4.1
  - Claude Opus 4
  - Claude Sonnet 4
  - Claude 3.7 Sonnet
  - Claude 3.5 Haiku (default)
  - Claude 3 Haiku
- Form-specific and global configuration options
- Custom prompts with variable support
- AJAX-powered admin interface
- Entry detail sidebar integration
- Manual analysis trigger button
- Temperature and max tokens configuration
- Web search instruction support
- Support for all Gravity Forms field types

### Security
- Encrypted API key storage in WordPress options
- Nonce verification for all AJAX actions
- Capability checks for admin operations
- SQL injection prevention
- XSS protection through proper escaping

### Performance
- Optimized asset loading (minified in production, source in development)
- Database table creation only when needed
- Efficient log purging without cron jobs
- Smart defaults to minimize configuration

### Documentation
- Comprehensive CLAUDE.md for AI assistants
- Inline code documentation
- Admin interface help text
- Model descriptions and recommendations
