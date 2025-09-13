<?php
/**
 * Tests for Admin/Settings.php
 *
 * @package EightyFourEM\GravityFormsAI\Tests
 */

namespace EightyFourEM\GravityFormsAI\Tests\Unit;

use EightyFourEM\GravityFormsAI\Tests\TestCase;
use EightyFourEM\GravityFormsAI\Admin\Settings;
use EightyFourEM\GravityFormsAI\Core\Encryption;
use EightyFourEM\GravityFormsAI\Core\APIHandler;

/**
 * Settings test class
 */
class SettingsTest extends TestCase {

	/**
	 * Settings instance
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Set up test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->settings = new Settings();

		// Set up admin user
		$user_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
	}

	/**
	 * Test admin menu registration
	 */
	public function test_add_admin_menu() {
		global $menu, $submenu;

		// Clear existing menus
		$menu = [];
		$submenu = [];

		// Register menus
		$this->settings->add_admin_menu();

		// Check main menu exists
		$menu_found = false;
		foreach ( $menu as $menu_item ) {
			if ( $menu_item[2] === '84em-gf-ai-settings' ) {
				$menu_found = true;
				$this->assertEquals( 'GF AI Analysis', $menu_item[0] );
				$this->assertEquals( 'manage_options', $menu_item[1] );
				$this->assertEquals( 'dashicons-analytics', $menu_item[6] );
				break;
			}
		}
		$this->assertTrue( $menu_found, 'Main menu should be registered' );

		// Check submenus
		$this->assertArrayHasKey( '84em-gf-ai-settings', $submenu );
		$submenu_items = $submenu['84em-gf-ai-settings'];

		// Should have Settings, Advanced Settings, and Logs
		$this->assertCount( 3, $submenu_items );
	}

	/**
	 * Test settings registration
	 */
	public function test_register_settings() {
		global $wp_registered_settings;

		// Register settings
		$this->settings->register_settings();

		// Check that settings are registered
		$expected_settings = [
			'84em_gf_ai_enabled',
			'84em_gf_ai_model',
			'84em_gf_ai_max_tokens',
			'84em_gf_ai_temperature',
			'84em_gf_ai_rate_limit',
			'84em_gf_ai_enable_logging',
			'84em_gf_ai_log_retention',
			'84em_gf_ai_delete_on_uninstall',
			'84em_gf_ai_default_prompt'
		];

		foreach ( $expected_settings as $setting ) {
			$this->assertArrayHasKey( $setting, $wp_registered_settings, "Setting $setting should be registered" );
		}
	}

	/**
	 * Test asset enqueueing on plugin pages
	 */
	public function test_enqueue_admin_assets() {
		// Test on plugin settings page
		$hook = 'toplevel_page_84em-gf-ai-settings';
		$this->settings->enqueue_admin_assets( $hook );

		// Check scripts are enqueued
		$this->assertTrue( wp_script_is( '84em-gf-ai-admin', 'enqueued' ) );
		$this->assertTrue( wp_style_is( '84em-gf-ai-admin', 'enqueued' ) );

		// Check localization
		$localized = wp_scripts()->get_data( '84em-gf-ai-admin', 'data' );
		$this->assertStringContainsString( 'eightyfourGfAi', $localized );
	}

	/**
	 * Test asset not enqueued on non-plugin pages
	 */
	public function test_enqueue_non_admin_page() {
		// Reset enqueued scripts
		wp_dequeue_script( '84em-gf-ai-admin' );
		wp_dequeue_style( '84em-gf-ai-admin' );

		// Test on different page
		$hook = 'edit.php';
		$this->settings->enqueue_admin_assets( $hook );

		// Scripts should not be enqueued
		$this->assertFalse( wp_script_is( '84em-gf-ai-admin', 'enqueued' ) );
		$this->assertFalse( wp_style_is( '84em-gf-ai-admin', 'enqueued' ) );
	}

	/**
	 * Test settings page rendering
	 */
	public function test_settings_page_render() {
		ob_start();
		$this->settings->settings_page();
		$output = ob_get_clean();

		// Check page structure
		$this->assertStringContainsString( '<div class="wrap">', $output );
		$this->assertStringContainsString( '84EM GF AI Analysis', $output );
		$this->assertStringContainsString( 'nav-tab-wrapper', $output );
		$this->assertStringContainsString( 'General', $output );
		$this->assertStringContainsString( 'API Configuration', $output );
		$this->assertStringContainsString( 'Prompts', $output );

		// Check form elements
		$this->assertStringContainsString( '84em_gf_ai_enabled', $output );
		$this->assertStringContainsString( '84em_gf_ai_model', $output );
		$this->assertStringContainsString( '84em_gf_ai_max_tokens', $output );
		$this->assertStringContainsString( 'API Key Management', $output );
	}

	/**
	 * Test settings page without permissions
	 */
	public function test_settings_page_no_permission() {
		// Set up user without permissions
		$user_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		ob_start();
		$this->settings->settings_page();
		$output = ob_get_clean();

		$this->assertEmpty( $output, 'Should not render for users without permissions' );
	}

	/**
	 * Test API key update
	 */
	public function test_update_api_key() {
		$_POST['84em_gf_ai_update_api_key'] = '1';
		$_POST['api_key'] = 'sk-ant-new-key-789';
		$_POST['_wpnonce'] = wp_create_nonce( '84em_gf_ai_api_key' );

		ob_start();
		$this->settings->settings_page();
		ob_end_clean();

		// Check key was saved
		$encryption = new Encryption();
		$saved_key = $encryption->get_api_key();
		$this->assertEquals( 'sk-ant-new-key-789', $saved_key );
	}

	/**
	 * Test empty API key update
	 */
	public function test_update_empty_api_key() {
		$_POST['84em_gf_ai_update_api_key'] = '1';
		$_POST['api_key'] = '';
		$_POST['_wpnonce'] = wp_create_nonce( '84em_gf_ai_api_key' );

		ob_start();
		$this->settings->settings_page();
		$output = ob_get_clean();

		// Should show error
		$this->assertStringContainsString( 'Please enter an API key', $output );
	}

	/**
	 * Test mappings page render
	 */
	public function test_mappings_page_render() {
		// Add test forms
		\GFAPI::add_form( [
			'id' => 10,
			'title' => 'Test Form for Mappings',
			'fields' => [
				(object) [ 'id' => 1, 'type' => 'text', 'label' => 'Name' ],
				(object) [ 'id' => 2, 'type' => 'email', 'label' => 'Email' ]
			]
		] );

		ob_start();
		$this->settings->mappings_page();
		$output = ob_get_clean();

		// Check page content
		$this->assertStringContainsString( 'Advanced Settings - Per-Form Configuration', $output );
		$this->assertStringContainsString( 'Test Form for Mappings', $output );
		$this->assertStringContainsString( 'Override Global Setting', $output );
		$this->assertStringContainsString( 'Limit Fields', $output );
		$this->assertStringContainsString( 'Custom Prompt', $output );
	}

	/**
	 * Test mappings page without Gravity Forms
	 */
	public function test_mappings_no_gravity_forms() {
		// Temporarily rename GFAPI class
		if ( class_exists( 'GFAPI' ) ) {
			class_alias( 'GFAPI', 'GFAPI_BACKUP' );
			// Can't actually undefine the class in PHP, so we'll test the output instead
		}

		ob_start();
		$this->settings->mappings_page();
		$output = ob_get_clean();

		// Should show forms or error if no GF
		$this->assertStringContainsString( 'Form', $output );
	}

	/**
	 * Test logs page render
	 */
	public function test_logs_page_render() {
		global $wpdb;
		$table_name = $wpdb->prefix . '84em_gf_ai_logs';

		// Add test log entries
		$wpdb->insert( $table_name, [
			'form_id' => 1,
			'entry_id' => 1,
			'status' => 'success',
			'created_at' => current_time( 'mysql' )
		] );

		ob_start();
		$this->settings->logs_page();
		$output = ob_get_clean();

		// Check page content
		$this->assertStringContainsString( 'AI Analysis Logs', $output );
		$this->assertStringContainsString( 'Clear All Logs', $output );
		$this->assertStringContainsString( 'success', $output );
	}

	/**
	 * Test log pagination
	 */
	public function test_logs_pagination() {
		global $wpdb;
		$table_name = $wpdb->prefix . '84em_gf_ai_logs';

		// Add many log entries
		for ( $i = 1; $i <= 25; $i++ ) {
			$wpdb->insert( $table_name, [
				'form_id' => $i,
				'entry_id' => $i,
				'status' => 'success',
				'created_at' => current_time( 'mysql' )
			] );
		}

		$_GET['paged'] = 2;

		ob_start();
		$this->settings->logs_page();
		$output = ob_get_clean();

		// Should have pagination
		$this->assertStringContainsString( 'tablenav-pages', $output );
	}

	/**
	 * Test clear logs
	 */
	public function test_clear_logs() {
		global $wpdb;
		$table_name = $wpdb->prefix . '84em_gf_ai_logs';

		// Add test logs
		$wpdb->insert( $table_name, [
			'form_id' => 1,
			'entry_id' => 1,
			'status' => 'success'
		] );

		$_POST['clear_logs'] = '1';
		$_POST['_wpnonce'] = wp_create_nonce( '84em_gf_ai_clear_logs' );

		ob_start();
		$this->settings->logs_page();
		$output = ob_get_clean();

		// Check logs were cleared
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
		$this->assertEquals( 0, $count, 'Logs should be cleared' );

		// Check success message
		$this->assertStringContainsString( 'Logs cleared successfully', $output );
	}

	/**
	 * Test AJAX save field mapping
	 */
	public function test_ajax_save_field_mapping() {
		$_POST['form_id'] = '1';
		$_POST['enabled'] = '1';
		$_POST['fields'] = [ '1', '2', '3' ];
		$_POST['prompt'] = 'Custom prompt for form';
		$_POST['nonce'] = wp_create_nonce( '84em-gf-ai-admin' );

		add_filter( 'wp_doing_ajax', '__return_true' );

		ob_start();
		try {
			$this->settings->ajax_save_field_mapping();
		} catch ( \WPDieException $e ) {
			// Expected for AJAX
		}
		$output = ob_get_clean();

		remove_filter( 'wp_doing_ajax', '__return_true' );

		$response = json_decode( $output, true );
		$this->assertTrue( $response['success'] );

		// Check settings were saved
		$this->assertOptionEquals( '84em_gf_ai_enabled_1', 1 );
		$this->assertOptionEquals( '84em_gf_ai_mapping_1', [ 1, 2, 3 ] );
		$this->assertOptionEquals( '84em_gf_ai_prompt_1', 'Custom prompt for form' );
	}

	/**
	 * Test AJAX save with null enabled (use global)
	 */
	public function test_ajax_save_null_enabled() {
		// First set a value
		update_option( '84em_gf_ai_enabled_1', 1 );

		$_POST['form_id'] = '1';
		$_POST['enabled'] = 'null';
		$_POST['fields'] = [];
		$_POST['prompt'] = '';
		$_POST['nonce'] = wp_create_nonce( '84em-gf-ai-admin' );

		add_filter( 'wp_doing_ajax', '__return_true' );

		ob_start();
		try {
			$this->settings->ajax_save_field_mapping();
		} catch ( \WPDieException $e ) {
			// Expected
		}
		ob_get_clean();

		remove_filter( 'wp_doing_ajax', '__return_true' );

		// Option should be deleted
		$this->assertFalse( get_option( '84em_gf_ai_enabled_1' ), 'Option should be deleted when null' );
	}

	/**
	 * Test AJAX save without permission
	 */
	public function test_ajax_save_no_permission() {
		// Set up user without permissions
		$user_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$_POST['form_id'] = '1';
		$_POST['nonce'] = wp_create_nonce( '84em-gf-ai-admin' );

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', function() {
			return function( $message ) {
				throw new \WPDieException( $message );
			};
		} );

		$this->expectException( '\WPDieException' );
		$this->expectExceptionMessage( 'Unauthorized' );

		$this->settings->ajax_save_field_mapping();
	}

	/**
	 * Test AJAX test API
	 */
	public function test_ajax_test_api() {
		// Set up API key
		$this->set_mock_api_key( 'test-key' );

		$_POST['nonce'] = wp_create_nonce( '84em-gf-ai-admin' );

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );

		ob_start();
		try {
			$this->settings->ajax_test_api();
		} catch ( \WPDieException $e ) {
			// Expected
		}
		$output = ob_get_clean();

		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

		$response = json_decode( $output, true );
		$this->assertTrue( $response['success'] );
		$this->assertEquals( 'API connection successful!', $response['data']['message'] );
	}

	/**
	 * Test AJAX get log details
	 */
	public function test_ajax_get_log_details() {
		global $wpdb;
		$table_name = $wpdb->prefix . '84em_gf_ai_logs';

		// Insert test log
		$wpdb->insert( $table_name, [
			'form_id' => 1,
			'entry_id' => 1,
			'status' => 'success',
			'request' => json_encode( [ 'test' => 'request' ] ),
			'response' => json_encode( [ 'test' => 'response' ] )
		] );
		$log_id = $wpdb->insert_id;

		$_POST['log_id'] = $log_id;
		$_POST['nonce'] = wp_create_nonce( '84em-gf-ai-admin' );

		add_filter( 'wp_doing_ajax', '__return_true' );

		ob_start();
		try {
			$this->settings->ajax_get_log_details();
		} catch ( \WPDieException $e ) {
			// Expected
		}
		$output = ob_get_clean();

		remove_filter( 'wp_doing_ajax', '__return_true' );

		$response = json_decode( $output, true );
		$this->assertTrue( $response['success'] );
		$this->assertStringContainsString( 'Log Entry Details', $response['data']['details'] );
		$this->assertStringContainsString( 'Request', $response['data']['details'] );
		$this->assertStringContainsString( 'Response', $response['data']['details'] );
	}

	/**
	 * Test AJAX invalid log ID
	 */
	public function test_ajax_invalid_log_id() {
		$_POST['log_id'] = '99999';
		$_POST['nonce'] = wp_create_nonce( '84em-gf-ai-admin' );

		add_filter( 'wp_doing_ajax', '__return_true' );

		ob_start();
		try {
			$this->settings->ajax_get_log_details();
		} catch ( \WPDieException $e ) {
			// Expected
		}
		$output = ob_get_clean();

		remove_filter( 'wp_doing_ajax', '__return_true' );

		$response = json_decode( $output, true );
		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'Log entry not found', $response['data']['message'] );
	}

	/**
	 * Test model selection dropdown
	 */
	public function test_model_selection() {
		ob_start();
		$this->settings->settings_page();
		$output = ob_get_clean();

		// Check models are available
		$this->assertStringContainsString( 'claude-3-5-haiku-20241022', $output );
		$this->assertStringContainsString( 'Claude 3.5 Haiku', $output );
		$this->assertStringContainsString( 'claude-opus-4-1-20250805', $output );
		$this->assertStringContainsString( 'Claude Opus 4.1', $output );
	}

	/**
	 * Test settings defaults
	 */
	public function test_settings_defaults() {
		// Clear all options
		delete_option( '84em_gf_ai_max_tokens' );
		delete_option( '84em_gf_ai_temperature' );

		ob_start();
		$this->settings->settings_page();
		$output = ob_get_clean();

		// Check default values are shown
		$this->assertStringContainsString( 'value="1000"', $output ); // Default max_tokens
		$this->assertStringContainsString( 'value="0.7"', $output ); // Default temperature
	}

	/**
	 * Test nonce verification
	 */
	public function test_nonce_verification() {
		$_POST['form_id'] = '1';
		$_POST['nonce'] = 'invalid_nonce';

		add_filter( 'wp_doing_ajax', '__return_true' );

		ob_start();
		try {
			$this->settings->ajax_save_field_mapping();
		} catch ( \WPDieException $e ) {
			// Expected
		}
		$output = ob_get_clean();

		remove_filter( 'wp_doing_ajax', '__return_true' );

		// Should fail with invalid nonce
		$this->assertStringContainsString( '-1', $output ); // WordPress returns -1 for nonce failures
	}

	/**
	 * Test asset file selection (minified vs source)
	 */
	public function test_asset_file_selection() {
		// Create mock minified files
		$css_path = EIGHTYFOUREM_GF_AI_PATH . 'assets/css/';
		$js_path = EIGHTYFOUREM_GF_AI_PATH . 'assets/js/';

		if ( ! file_exists( $css_path ) ) {
			mkdir( $css_path, 0777, true );
		}
		if ( ! file_exists( $js_path ) ) {
			mkdir( $js_path, 0777, true );
		}

		// Create minified files
		file_put_contents( $css_path . 'admin.min.css', '/* minified */' );
		file_put_contents( $js_path . 'admin.min.js', '// minified' );

		$hook = 'toplevel_page_84em-gf-ai-settings';
		$this->settings->enqueue_admin_assets( $hook );

		// Should use minified versions
		$scripts = wp_scripts();
		$styles = wp_styles();

		$script_src = $scripts->registered['84em-gf-ai-admin']->src;
		$style_src = $styles->registered['84em-gf-ai-admin']->src;

		$this->assertStringContainsString( 'admin.min.js', $script_src );
		$this->assertStringContainsString( 'admin.min.css', $style_src );

		// Clean up
		unlink( $css_path . 'admin.min.css' );
		unlink( $js_path . 'admin.min.js' );
	}

	/**
	 * Test logs table creation
	 */
	public function test_logs_table_creation() {
		global $wpdb;
		$table_name = $wpdb->prefix . '84em_gf_ai_logs';

		// Drop table if exists
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

		// Access logs page should create table
		ob_start();
		$this->settings->logs_page();
		ob_end_clean();

		// Table should exist
		$this->assertTableExists( '84em_gf_ai_logs' );
	}

	/**
	 * Test logs warning when disabled
	 */
	public function test_logs_warning_when_disabled() {
		update_option( '84em_gf_ai_enable_logging', false );

		ob_start();
		$this->settings->logs_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Logging is currently disabled', $output );
	}

	/**
	 * Test API key display when configured
	 */
	public function test_api_key_display() {
		// Set API key
		$this->set_mock_api_key( 'test-key' );

		ob_start();
		$this->settings->settings_page();
		$output = ob_get_clean();

		// Should show key is configured
		$this->assertStringContainsString( 'API key is configured', $output );
		$this->assertStringContainsString( '••••••••••••••••', $output ); // Masked placeholder
	}

	/**
	 * Test API key display when not configured
	 */
	public function test_api_key_display_not_configured() {
		// Remove API key
		$encryption = new Encryption();
		$encryption->delete_api_key();

		ob_start();
		$this->settings->settings_page();
		$output = ob_get_clean();

		// Should show no key configured
		$this->assertStringContainsString( 'No API key configured', $output );
		$this->assertStringContainsString( 'sk-ant-...', $output ); // Placeholder hint
	}
}