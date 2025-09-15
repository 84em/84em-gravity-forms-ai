# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Plugin Overview

84EM Gravity Forms AI Analysis is a WordPress plugin that integrates Claude AI with Gravity Forms to analyze form submissions and store intelligent insights as markdown in entry meta.

## Build and Development Commands

### Initial Setup
```bash
npm install              # Install build dependencies
```

### Development
```bash
npm run watch           # Watch for changes and rebuild automatically
npm run build:css       # Build CSS only with source maps
npm run build:js        # Build JavaScript only with source maps
npm run build           # Build both CSS and JS
npm run clean           # Remove all minified files and source maps
```

### Production Build
```bash
./build.sh              # Creates production-ready ZIP files
```
This generates:
- `84em-gravity-forms-ai-{version}.zip` - Versioned release
- `84em-gravity-forms-ai.zip` - Latest version

## Architecture and Key Components

### Namespace Structure
All PHP classes use the namespace `EightyFourEM\GravityFormsAI\` with PSR-4 autoloading configured in the main plugin file.

### Core Processing Flow

1. **Entry Processing** (`includes/Core/EntryProcessor.php`)
   - Hooks into Gravity Forms entry detail sidebar via `gform_entry_detail_sidebar_middle`
   - Provides manual analysis trigger and delete functionality
   - Stores raw markdown in entry meta
   - Handles HTML export with client-side markdown conversion using Marked.js
   - Auto-includes all text fields when no mapping configured (smart defaults)
   - Falls back to global enable setting when form-specific setting not configured
   - Delete button with confirmation prompt removes all analysis data
   - Adds entry note when analysis is deleted for audit trail

2. **API Integration** (`includes/Core/APIHandler.php`)
   - Manages Claude API communication using WordPress HTTP API
   - Implements rate limiting based on configured delay
   - Logs requests/responses when logging is enabled
   - Automatically purges old logs based on retention setting (no cron needed)
   - Uses Claude Messages API with anthropic-version: 2023-06-01

3. **Security Layer** (`includes/Core/Encryption.php`)
   - Encrypts API keys using AES-256-CBC with WordPress AUTH_KEY and AUTH_SALT
   - Stores encrypted key in WordPress options table
   - Option name: `84em_gf_ai_encrypted_api_key`

4. **Admin Interface** (`includes/Admin/Settings.php`)
   - Three main pages: Settings, Advanced Settings, Logs
   - AJAX handlers for field mapping, API testing, and log details
   - Automatically loads minified assets in production, source files in development
   - Advanced Settings page is optional - plugin works with smart defaults

### Data Storage

**WordPress Options** (all prefixed with `84em_gf_ai_`):
- Global settings: `enabled`, `model`, `max_tokens`, `temperature`, `rate_limit`, `enable_logging`, `log_retention`
- Per-form settings (all optional with smart defaults):
  - `enabled_{form_id}` - Override global enable (null = use global)
  - `mapping_{form_id}` - Field selection (empty = all text fields)
  - `prompt_{form_id}` - Custom prompt (empty = use global)
- Encrypted API key: `encrypted_api_key`

**Database Table** (`{prefix}84em_gf_ai_logs`):
- Stores API request/response logs
- Auto-created on first use
- Automatically purged based on retention setting

**Entry Meta**:
- `84em_ai_analysis` - Stores the raw markdown analysis text
- `84em_ai_analysis_date` - Timestamp of analysis
- `84em_ai_analysis_error` - Error message if analysis fails
- `84em_ai_analysis_error_date` - Timestamp of failed analysis

### JavaScript Variable Naming
Use `eightyfourGfAi` for JavaScript variables (not `84emGfAi` which is invalid).

### CSS Class Naming
Use `eightyfourem-gf-ai-` prefix for CSS classes (not `84em-` which would be invalid).

## Critical Implementation Details

### Markdown to HTML Conversion
Uses **Marked.js** library for clean, reliable markdown conversion:
- Raw markdown is stored in entry meta
- Client-side conversion using Marked.js v12.0.0 (loaded from CDN)
- Consistent rendering between entry view and HTML downloads

### HTML Report Viewing
The `ajax_download_html()` method generates a standalone HTML file that:
- Opens in a new browser tab for viewing (not downloaded)
- Includes embedded Marked.js library for markdown conversion
- Converts markdown to HTML on page load using client-side JavaScript
- Features embedded CSS for both screen and print optimization
- Displays entry metadata (ID, form name, date)
- Shows submitter information extracted from form fields
- Both client-side and server-side generation supported

### Log Management
Logs are automatically purged when any AI analysis runs (no scheduled tasks):
- Deletes logs older than configured retention period (default: 30 days)
- Happens in `APIHandler::purge_old_logs()` after each API call

### Asset Loading Strategy
Settings.php checks for minified files existence:
```php
$css_file = file_exists(EIGHTYFOUREM_GF_AI_PATH . 'assets/css/admin.min.css') ? 'admin.min.css' : 'admin.css';
```
This allows development without building while using optimized assets in production.

## Available Claude Models

The plugin supports these models (defined in Settings.php):

**Current Models:**
- `claude-opus-4-1-20250805` - Claude Opus 4.1 - Latest, most capable
- `claude-opus-4-20250514` - Claude Opus 4 - Advanced capabilities
- `claude-sonnet-4-20250514` - Claude Sonnet 4 - 1M token context (Beta)
- `claude-3-7-sonnet-20250219` - Claude 3.7 Sonnet - Hybrid reasoning
- `claude-3-5-haiku-20241022` - Claude 3.5 Haiku - Fast, recommended default
- `claude-3-haiku-20240307` - Claude 3 Haiku - Previous fast model

**Deprecated Models (Still Functional):**
- `claude-3-5-sonnet-20241022` - Claude 3.5 Sonnet - Deprecated by Anthropic
- `claude-3-opus-20240229` - Claude 3 Opus - Deprecated by Anthropic

## AJAX Actions

All AJAX actions use nonce verification and capability checks:
- `84em_gf_ai_analyze_entry` - Manual entry analysis trigger (requires `gravityforms_view_entries`)
- `84em_gf_ai_download_html` - Generate HTML export
- `84em_gf_ai_delete_analysis` - Delete AI analysis from entry (requires `gravityforms_edit_entries`)
- `84em_gf_ai_save_field_mapping` - Save form field mappings
- `84em_gf_ai_test_api` - Test API connection
- `84em_gf_ai_get_log_details` - View detailed log entry

## Testing Approach

No automated tests are included. Manual testing should cover:
- API key encryption/decryption
- Various Gravity Forms field types
- HTML export generation
- Log retention and automatic purging
