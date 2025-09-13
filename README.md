# 84EM Gravity Forms AI Analysis

WordPress plugin that integrates Claude AI with Gravity Forms to provide intelligent analysis of form submissions.

## Features

- 🤖 AI-powered analysis of form submissions using Claude AI
- 📝 Clean markdown storage in entry meta (no duplicate entry notes)
- 🎯 Customizable field selection per form
- 💾 Secure encrypted API key storage
- 📊 Comprehensive logging system
- 💬 Custom prompts per form or globally
- 📥 View analysis report in new browser tab with client-side markdown rendering
- 🔄 Manual analysis/re-analysis from entry detail page
- ✨ Uses Marked.js for consistent markdown-to-HTML conversion

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Gravity Forms plugin (active)
- Claude API key from Anthropic

## Installation

### From ZIP File

1. Download the latest release ZIP file
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin"
4. Select the ZIP file and click "Install Now"
5. Activate the plugin

### For Development

1. Clone this repository to `wp-content/plugins/`
2. Run `npm install` to install build dependencies
3. Run `./build.sh` to create production build

## Development

### Project Structure

```
84em-gravity-forms-ai/
├── assets/
│   ├── css/
│   │   └── admin.css             # Admin styles (source)
│   └── js/
│       ├── admin.js              # Admin scripts (source)
│       └── markdown-formatter.js # Markdown to HTML converter
├── includes/
│   ├── Admin/
│   │   └── Settings.php          # Admin settings interface
│   └── Core/
│       ├── APIHandler.php        # Claude API integration
│       ├── Encryption.php        # API key encryption
│       └── EntryProcessor.php    # Form submission processing
├── languages/                    # Translation files
├── 84em-gravity-forms-ai.php     # Main plugin file
├── CLAUDE.md                     # AI assistant instructions
├── MARKDOWN-FORMATTING.md        # Markdown implementation docs
├── readme.txt                    # WordPress.org readme
├── package.json                  # NPM dependencies
├── build.sh                      # Build script
└── .gitignore                   # Git ignore rules
```

### Build Process

The plugin includes a build system for creating production-ready distributions:

#### Install Dependencies
```bash
npm install
```

#### Build Commands

```bash
# Build everything (CSS + JS)
npm run build

# Build CSS only
npm run build:css

# Build JavaScript only
npm run build:js

# Clean build artifacts
npm run clean

# Watch for changes (development)
npm run watch
```

#### Create Distribution Package
```bash
./build.sh
```

This will:
1. Install npm dependencies (if needed)
2. Minify CSS and JavaScript files
3. Create source maps for debugging
4. Generate a versioned ZIP file (e.g., `84em-gravity-forms-ai-1.0.0.zip`)
5. Create a latest ZIP file (`84em-gravity-forms-ai.zip`)

### Development Workflow

1. **Make changes** to source files:
   - CSS: `assets/css/admin.css`
   - JS: `assets/js/admin.js`
   - PHP: Files in `includes/` directory

2. **Test locally** with source files (plugin automatically uses non-minified versions if available)

3. **Build for production**:
   ```bash
   ./build.sh
   ```

4. **Deploy** the generated ZIP file

### Code Standards

- **PHP**: Follow WordPress Coding Standards
- **JavaScript**: Use jQuery patterns consistent with WordPress admin
- **CSS**: Use BEM-like naming with `eightyfourem-gf-ai-` prefix
- **Security**: Always escape output, sanitize input, use nonces

### Testing

1. Enable WP_DEBUG in `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

2. Test with various Gravity Forms field types
3. Verify API key encryption/decryption
4. Check markdown storage in entry meta
5. Test HTML export with Marked.js conversion
6. Verify client-side markdown rendering

## Configuration

### Initial Setup

1. Navigate to **GF AI Analysis** in WordPress admin
2. Enter your Claude API key in the API Key Management section
3. Configure global settings:
   - Enable/disable AI analysis
   - Select Claude model
   - Set max tokens and temperature
   - Configure rate limiting

That's it! The plugin will now automatically analyze all text fields in your form submissions.

### Advanced Settings (Optional)

The plugin works out of the box with smart defaults. If you need to customize specific forms:

1. Go to **GF AI Analysis → Advanced Settings**
2. For each form, you can optionally:
   - Override the global enable/disable setting
   - Select specific fields to analyze (defaults to all text fields)
   - Set a custom AI prompt for that form

### Available Claude Models

**Current Models:**
- **Claude Opus 4.1** (`claude-opus-4-1-20250805`) - Latest, most capable model
- **Claude Opus 4** (`claude-opus-4-20250514`) - Advanced capabilities
- **Claude Sonnet 4** (`claude-sonnet-4-20250514`) - 1M token context (Beta)
- **Claude 3.7 Sonnet** (`claude-3-7-sonnet-20250219`) - Hybrid reasoning
- **Claude 3.5 Haiku** (`claude-3-5-haiku-20241022`) - Fast, recommended for most use cases
- **Claude 3 Haiku** (`claude-3-haiku-20240307`) - Previous fast model

**Deprecated Models (Still Functional):**
- **Claude 3.5 Sonnet** (`claude-3-5-sonnet-20241022`) - Deprecated
- **Claude 3 Opus** (`claude-3-opus-20240229`) - Deprecated

## API Integration

The plugin uses the Claude API with the following features:

- **Encryption**: API keys are encrypted using AES-256-CBC before storage in WordPress options
- **Storage**: Encrypted keys stored securely in WordPress options table
- **Rate Limiting**: Configurable delay between requests
- **Error Handling**: Comprehensive error logging and retry logic
- **Timeout**: 30-second timeout for API requests
- **Markdown Storage**: Raw markdown stored in entry meta for flexible rendering
- **Client-Side Rendering**: Marked.js v12.0.0 converts markdown to HTML in the browser

## Hooks & Filters

### Filters

```php
// Modify the prompt before sending to API
add_filter('84em_gf_ai_analysis_prompt', function($prompt, $context) {
    // $context contains: form_id, entry_id, form_title, form_data, submitter info
    // Customize prompt based on context
    return $prompt;
}, 10, 2);

// Filter the AI response before saving
add_filter('84em_gf_ai_analysis_result', function($result, $entry_id, $form_id) {
    // Process or modify the AI analysis result
    return $result;
}, 10, 3);
```

### Actions

```php
// After successful analysis
add_action('84em_gf_ai_after_analysis', function($entry_id, $result, $form_id) {
    // Custom processing after successful analysis
    // For example, send notification, update other systems, etc.
}, 10, 3);

// When analysis fails
add_action('84em_gf_ai_analysis_failed', function($entry_id, $error, $form_id) {
    // Handle analysis failures
    // For example, log to external service, send alert, etc.
}, 10, 3);
```

## Troubleshooting

### API Key Issues
- Verify key starts with `sk-ant-`
- Check if key has proper permissions
- Use "Test Connection" button to verify

### Analysis Not Working
- Check if AI analysis is enabled globally
- Verify form has AI analysis enabled
- Ensure fields are selected for analysis
- Check logs for error messages

### Performance Issues
- Adjust rate limiting in settings
- Reduce max tokens for faster responses
- Consider using Claude 3.5 Haiku for speed and cost efficiency

## Support

For issues or questions:
1. Check the Logs page in admin for error details
2. Enable WP_DEBUG for additional information
3. Contact 84EM support with log details

## License

GPL v2 or later

## Credits

Developed by [84EM](https://84em.com)

---

*This plugin requires an active Gravity Forms license and Claude API key from Anthropic.*
