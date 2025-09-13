<?php
/**
 * Integration tests for complete plugin flow
 *
 * @package EightyFourEM\GravityFormsAI\Tests
 */

namespace EightyFourEM\GravityFormsAI\Tests\Integration;

use EightyFourEM\GravityFormsAI\Tests\TestCase;
use EightyFourEM\GravityFormsAI\Plugin;
use EightyFourEM\GravityFormsAI\Core\Encryption;
use EightyFourEM\GravityFormsAI\Core\APIHandler;
use EightyFourEM\GravityFormsAI\Core\EntryProcessor;
use EightyFourEM\GravityFormsAI\Admin\Settings;

/**
 * Integration test class
 */
class IntegrationTest extends TestCase {

	/**
	 * Set up integration test
	 */
	public function setUp(): void {
		parent::setUp();

		// Ensure plugin is fully initialized
		Plugin::get_instance();

		// Set up API key and enable AI
		$this->set_mock_api_key( 'sk-ant-integration-test-key' );
		update_option( '84em_gf_ai_enabled', true );
		update_option( '84em_gf_ai_enable_logging', true );
	}

	/**
	 * Test complete form submission flow
	 */
	public function test_full_submission_flow() {
		// Create a form
		$form = [
			'id' => 100,
			'title' => 'Integration Test Form',
			'fields' => [
				(object) [ 'id' => 1, 'type' => 'text', 'label' => 'Name', 'adminOnly' => false ],
				(object) [ 'id' => 2, 'type' => 'email', 'label' => 'Email', 'adminOnly' => false ],
				(object) [ 'id' => 3, 'type' => 'textarea', 'label' => 'Message', 'adminOnly' => false ]
			]
		];
		\GFAPI::add_form( $form );

		// Create an entry
		$entry = [
			'id' => 100,
			'form_id' => 100,
			'date_created' => current_time( 'mysql' ),
			'1' => 'Integration Test User',
			'2' => 'integration@test.com',
			'3' => 'This is a test message for integration testing.'
		];
		\GFAPI::add_entry( $entry );

		// Mock API response
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );

		// Process the entry
		$processor = new EntryProcessor();
		$result = $processor->process_entry( $entry, $form );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

		// Verify the complete flow
		$this->assertTrue( $result['success'], 'Processing should succeed' );
		$this->assertArrayHasKey( 'data', $result );

		// Check entry meta was saved
		$analysis = gform_get_meta( 100, '84em_ai_analysis' );
		$this->assertNotEmpty( $analysis, 'Analysis should be saved to meta' );
		$this->assertEquals( 'This is a mock AI response for testing.', $analysis );

		// Check analysis date was saved
		$date = gform_get_meta( 100, '84em_ai_analysis_date' );
		$this->assertNotEmpty( $date, 'Analysis date should be saved' );

		// Check log was created
		$this->assertLogExists( [
			'form_id' => 100,
			'entry_id' => 100,
			'status' => 'success'
		] );
	}

	/**
	 * Test manual re-analysis flow
	 */
	public function test_manual_reanalysis() {
		// Set up form and entry
		$form = $this->create_test_form();
		$entry = $this->create_test_entry();

		// First analysis
		add_filter( 'pre_http_request', function( $response, $args, $url ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body' => json_encode( [
						'content' => [ [ 'text' => 'First analysis result' ] ]
					] )
				];
			}
			return $response;
		}, 10, 3 );

		$processor = new EntryProcessor();
		$result1 = $processor->process_entry( $entry, $form );

		// Verify first analysis
		$this->assertTrue( $result1['success'] );
		$this->assertEquals( 'First analysis result', gform_get_meta( 1, '84em_ai_analysis' ) );

		// Second analysis (re-analysis)
		add_filter( 'pre_http_request', function( $response, $args, $url ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body' => json_encode( [
						'content' => [ [ 'text' => 'Second analysis result' ] ]
					] )
				];
			}
			return $response;
		}, 10, 3 );

		$result2 = $processor->process_entry( $entry, $form );

		// Verify re-analysis
		$this->assertTrue( $result2['success'] );
		$this->assertEquals( 'Second analysis result', gform_get_meta( 1, '84em_ai_analysis' ) );

		// Should have 2 log entries
		global $wpdb;
		$table_name = $wpdb->prefix . '84em_gf_ai_logs';
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table_name WHERE form_id = %d AND entry_id = %d",
			1, 1
		) );
		$this->assertEquals( 2, $count, 'Should have 2 log entries for re-analysis' );
	}

	/**
	 * Test HTML export flow
	 */
	public function test_html_export_flow() {
		// Set up entry with analysis
		$entry = $this->create_test_entry();
		$form = $this->create_test_form();

		// Add analysis to entry
		gform_update_meta( 1, '84em_ai_analysis', '# Test Analysis\n\nThis is **markdown** content with:\n- Bullet points\n- More items\n\n## Section 2\nAdditional content here.' );
		gform_update_meta( 1, '84em_ai_analysis_date', current_time( 'mysql' ) );

		// Set up admin user
		$user_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Prepare AJAX request
		$_POST['entry_id'] = 1;
		$_POST['form_id'] = 1;
		$_POST['nonce'] = wp_create_nonce( '84em_gf_ai_html' );

		add_filter( 'wp_doing_ajax', '__return_true' );

		// Execute HTML export
		$processor = new EntryProcessor();
		ob_start();
		try {
			$processor->ajax_download_html();
		} catch ( \WPDieException $e ) {
			// Expected for AJAX
		}
		$output = ob_get_clean();

		remove_filter( 'wp_doing_ajax', '__return_true' );

		// Parse response
		$response = json_decode( $output, true );

		// Verify HTML was generated
		$this->assertTrue( $response['success'], 'HTML export should succeed' );
		$this->assertArrayHasKey( 'html', $response['data'] );
		$this->assertArrayHasKey( 'filename', $response['data'] );

		$html = $response['data']['html'];

		// Check HTML structure
		$this->assertStringContainsString( '<!DOCTYPE html>', $html );
		$this->assertStringContainsString( '<title>', $html );
		$this->assertStringContainsString( 'AI Analysis Report', $html );
		$this->assertStringContainsString( 'Entry #1', $html );

		// Check Marked.js is included
		$this->assertStringContainsString( 'marked.min.js', $html );
		$this->assertStringContainsString( 'marked.parse', $html );

		// Check metadata
		$this->assertStringContainsString( 'Test Contact Form', $html );
		$this->assertStringContainsString( 'john.doe@example.com', $html );
	}

	/**
	 * Test settings impact on analysis behavior
	 */
	public function test_settings_to_analysis() {
		// Test with different settings
		$test_cases = [
			[
				'model' => 'claude-3-5-haiku-20241022',
				'max_tokens' => 500,
				'temperature' => 0.3
			],
			[
				'model' => 'claude-opus-4-1-20250805',
				'max_tokens' => 2000,
				'temperature' => 0.9
			]
		];

		foreach ( $test_cases as $settings ) {
			// Update settings
			update_option( '84em_gf_ai_model', $settings['model'] );
			update_option( '84em_gf_ai_max_tokens', $settings['max_tokens'] );
			update_option( '84em_gf_ai_temperature', $settings['temperature'] );

			// Mock API request to verify settings are used
			add_filter( 'pre_http_request', function( $response, $args, $url ) use ( $settings ) {
				if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
					$body = json_decode( $args['body'], true );

					// Verify settings are applied
					$this->assertEquals( $settings['model'], $body['model'] );
					$this->assertEquals( $settings['max_tokens'], $body['max_tokens'] );
					$this->assertEquals( $settings['temperature'], $body['temperature'] );

					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body' => json_encode( [
							'content' => [ [ 'text' => 'Response with settings' ] ]
						] )
					];
				}
				return $response;
			}, 10, 3 );

			$processor = new EntryProcessor();
			$result = $processor->process_entry( $this->test_entry, $this->test_form );

			$this->assertTrue( $result['success'], 'Processing should succeed with custom settings' );
		}
	}

	/**
	 * Test form-specific settings override
	 */
	public function test_form_specific_settings() {
		// Set global settings
		update_option( '84em_gf_ai_enabled', true );
		update_option( '84em_gf_ai_default_prompt', 'Global prompt' );

		// Set form-specific settings
		update_option( '84em_gf_ai_enabled_1', '1' );
		update_option( '84em_gf_ai_prompt_1', 'Form-specific prompt for {form_title}' );
		update_option( '84em_gf_ai_mapping_1', [ 1, 4 ] ); // Only specific fields

		// Mock API to verify form-specific settings are used
		add_filter( 'pre_http_request', function( $response, $args, $url ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				$body = json_decode( $args['body'], true );
				$message = $body['messages'][0]['content'];

				// Should use form-specific prompt
				$this->assertStringContainsString( 'Form-specific prompt', $message );
				$this->assertStringContainsString( 'Test Contact Form', $message );

				// Should only include mapped fields
				$this->assertStringContainsString( 'First Name', $message );
				$this->assertStringContainsString( 'Email', $message );
				$this->assertStringNotContainsString( 'Company', $message ); // Not mapped

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body' => json_encode( [
						'content' => [ [ 'text' => 'Processed with form settings' ] ]
					] )
				];
			}
			return $response;
		}, 10, 3 );

		$processor = new EntryProcessor();
		$result = $processor->process_entry( $this->test_entry, $this->test_form );

		$this->assertTrue( $result['success'] );
	}

	/**
	 * Test logging lifecycle
	 */
	public function test_logging_lifecycle() {
		global $wpdb;
		$table_name = $wpdb->prefix . '84em_gf_ai_logs';

		// Enable logging with short retention
		update_option( '84em_gf_ai_enable_logging', true );
		update_option( '84em_gf_ai_log_retention', 1 ); // 1 day

		// Create old log entry
		$wpdb->insert( $table_name, [
			'form_id' => 999,
			'entry_id' => 999,
			'status' => 'success',
			'created_at' => date( 'Y-m-d H:i:s', strtotime( '-2 days' ) )
		] );

		// Create recent log entry
		$wpdb->insert( $table_name, [
			'form_id' => 998,
			'entry_id' => 998,
			'status' => 'success',
			'created_at' => current_time( 'mysql' )
		] );

		// Process new entry (triggers log purging)
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );

		$processor = new EntryProcessor();
		$processor->process_entry( $this->test_entry, $this->test_form );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

		// Old log should be deleted
		$old_exists = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE form_id = 999" );
		$this->assertEquals( 0, $old_exists, 'Old log should be purged' );

		// Recent log should remain
		$recent_exists = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE form_id = 998" );
		$this->assertEquals( 1, $recent_exists, 'Recent log should remain' );

		// New log should be created
		$new_exists = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE form_id = 1" );
		$this->assertEquals( 1, $new_exists, 'New log should be created' );
	}

	/**
	 * Test API failure recovery
	 */
	public function test_api_failure_recovery() {
		$attempt = 0;

		// Mock API to fail first, then succeed
		add_filter( 'pre_http_request', function( $response, $args, $url ) use ( &$attempt ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				$attempt++;

				if ( $attempt === 1 ) {
					// First attempt fails
					return new \WP_Error( 'http_request_failed', 'Connection timeout' );
				} else {
					// Second attempt succeeds
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body' => json_encode( [
							'content' => [ [ 'text' => 'Success after retry' ] ]
						] )
					];
				}
			}
			return $response;
		}, 10, 3 );

		$processor = new EntryProcessor();

		// First attempt should fail
		$result1 = $processor->process_entry( $this->test_entry, $this->test_form );
		$this->assertFalse( $result1['success'], 'First attempt should fail' );

		// Check error was logged
		$error = gform_get_meta( 1, '84em_ai_analysis_error' );
		$this->assertEquals( 'Connection timeout', $error );

		// Second attempt should succeed
		$result2 = $processor->process_entry( $this->test_entry, $this->test_form );
		$this->assertTrue( $result2['success'], 'Second attempt should succeed' );

		// Check analysis was saved
		$analysis = gform_get_meta( 1, '84em_ai_analysis' );
		$this->assertEquals( 'Success after retry', $analysis );

		// Error should still be present (not cleared automatically)
		$error_after = gform_get_meta( 1, '84em_ai_analysis_error' );
		$this->assertNotEmpty( $error_after );
	}

	/**
	 * Test permission flow for different user roles
	 */
	public function test_permission_flow() {
		$roles = [
			'administrator' => true,
			'editor' => false,
			'author' => false,
			'contributor' => false,
			'subscriber' => false
		];

		foreach ( $roles as $role => $should_succeed ) {
			// Create user with role
			$user_id = $this->factory()->user->create( [ 'role' => $role ] );
			wp_set_current_user( $user_id );

			// Try to access settings
			ob_start();
			$settings = new Settings();
			$settings->settings_page();
			$output = ob_get_clean();

			if ( $should_succeed ) {
				$this->assertNotEmpty( $output, "$role should see settings page" );
				$this->assertStringContainsString( '84EM GF AI Analysis', $output );
			} else {
				$this->assertEmpty( $output, "$role should not see settings page" );
			}

			// Try AJAX analysis
			$_POST['entry_id'] = 1;
			$_POST['form_id'] = 1;
			$_POST['nonce'] = wp_create_nonce( '84em_gf_ai_analyze' );

			add_filter( 'wp_doing_ajax', '__return_true' );
			add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );

			$processor = new EntryProcessor();
			ob_start();
			try {
				$processor->ajax_analyze_entry();
			} catch ( \WPDieException $e ) {
				// Expected
			}
			$ajax_output = ob_get_clean();

			remove_filter( 'wp_doing_ajax', '__return_true' );
			remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

			$ajax_response = json_decode( $ajax_output, true );

			// Only admins and users with gravityforms_view_entries should succeed
			if ( $role === 'administrator' ) {
				$this->assertTrue( $ajax_response['success'], "$role should be able to analyze" );
			} else {
				$this->assertFalse( $ajax_response['success'], "$role should not be able to analyze" );
			}
		}
	}

	/**
	 * Test WordPress filter and action integration
	 */
	public function test_filter_hook_integration() {
		$filter_calls = [];
		$action_calls = [];

		// Add test filters
		add_filter( '84em_gf_ai_analysis_prompt', function( $prompt, $context ) use ( &$filter_calls ) {
			$filter_calls[] = 'prompt_filter';
			return $prompt . ' [FILTERED]';
		}, 10, 2 );

		add_filter( '84em_gf_ai_analysis_result', function( $result, $entry_id, $form_id ) use ( &$filter_calls ) {
			$filter_calls[] = 'result_filter';
			return $result . ' [RESULT_FILTERED]';
		}, 10, 3 );

		// Add test actions
		add_action( '84em_gf_ai_after_analysis', function( $entry_id, $result, $form_id ) use ( &$action_calls ) {
			$action_calls[] = 'after_analysis';
		}, 10, 3 );

		add_action( '84em_gf_ai_analysis_failed', function( $entry_id, $error, $form_id ) use ( &$action_calls ) {
			$action_calls[] = 'analysis_failed';
		}, 10, 3 );

		// Process entry successfully
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );

		$processor = new EntryProcessor();
		$result = $processor->process_entry( $this->test_entry, $this->test_form );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

		// Check filters were called
		$this->assertContains( 'prompt_filter', $filter_calls );
		$this->assertContains( 'result_filter', $filter_calls );

		// Check action was called
		$this->assertContains( 'after_analysis', $action_calls );

		// Check filtered result was saved
		$analysis = gform_get_meta( 1, '84em_ai_analysis' );
		$this->assertStringContainsString( '[RESULT_FILTERED]', $analysis );

		// Test failure action
		add_filter( 'pre_http_request', [ $this, 'mock_http_request_error' ], 10, 3 );

		$processor->process_entry( $this->test_entry, $this->test_form );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_request_error' ] );

		// Check failure action was called
		$this->assertContains( 'analysis_failed', $action_calls );
	}

	/**
	 * Test database transaction integrity
	 */
	public function test_database_transactions() {
		global $wpdb;
		$table_name = $wpdb->prefix . '84em_gf_ai_logs';

		// Simulate concurrent operations
		$operations = [];

		for ( $i = 1; $i <= 5; $i++ ) {
			// Create form and entry
			$form = [
				'id' => 200 + $i,
				'title' => "Concurrent Form $i",
				'fields' => [
					(object) [ 'id' => 1, 'type' => 'text', 'label' => 'Field' ]
				]
			];
			\GFAPI::add_form( $form );

			$entry = [
				'id' => 200 + $i,
				'form_id' => 200 + $i,
				'1' => "Value $i"
			];
			\GFAPI::add_entry( $entry );

			// Process entry
			add_filter( 'pre_http_request', function( $response, $args, $url ) use ( $i ) {
				if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
					return [
						'response' => [ 'code' => 200, 'message' => 'OK' ],
						'body' => json_encode( [
							'content' => [ [ 'text' => "Analysis for entry $i" ] ]
						] )
					];
				}
				return $response;
			}, 10, 3 );

			$processor = new EntryProcessor();
			$result = $processor->process_entry( $entry, $form );

			$operations[] = [
				'entry_id' => 200 + $i,
				'result' => $result['success']
			];
		}

		// Verify all operations succeeded
		foreach ( $operations as $op ) {
			$this->assertTrue( $op['result'], "Operation for entry {$op['entry_id']} should succeed" );

			// Check meta was saved correctly
			$analysis = gform_get_meta( $op['entry_id'], '84em_ai_analysis' );
			$this->assertNotEmpty( $analysis );
		}

		// Check logs were created correctly
		$log_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE form_id >= 201 AND form_id <= 205" );
		$this->assertEquals( 5, $log_count, 'Should have 5 log entries' );

		// Check data integrity
		$logs = $wpdb->get_results( "SELECT * FROM $table_name WHERE form_id >= 201 AND form_id <= 205" );
		foreach ( $logs as $log ) {
			$this->assertEquals( 'success', $log->status );
			$this->assertNotEmpty( $log->request );
			$this->assertNotEmpty( $log->response );
		}
	}

	/**
	 * Test rate limiting in real scenario
	 */
	public function test_rate_limiting_real_scenario() {
		// Set rate limit to 1 second
		update_option( '84em_gf_ai_rate_limit', 1 );

		$times = [];

		// Make multiple requests
		for ( $i = 1; $i <= 3; $i++ ) {
			$start = microtime( true );

			add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );

			$api_handler = new APIHandler();
			$api_handler->analyze( "Request $i", [] );

			remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

			$times[] = microtime( true );
		}

		// Check delays between requests
		for ( $i = 1; $i < count( $times ); $i++ ) {
			$delay = $times[ $i ] - $times[ $i - 1 ];
			$this->assertGreaterThanOrEqual( 0.9, $delay, 'Should have at least 1 second delay' ); // Allow small variance
		}
	}

	/**
	 * Test encryption key rotation
	 */
	public function test_encryption_key_rotation() {
		$encryption = new Encryption();

		// Save initial key
		$old_key = 'sk-ant-old-key-abc123';
		$encryption->save_api_key( $old_key );

		// Process with old key
		add_filter( 'pre_http_request', function( $response, $args, $url ) use ( $old_key ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				// Verify old key is used
				$this->assertEquals( $old_key, $args['headers']['x-api-key'] );

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body' => json_encode( [
						'content' => [ [ 'text' => 'With old key' ] ]
					] )
				];
			}
			return $response;
		}, 10, 3 );

		$processor = new EntryProcessor();
		$result1 = $processor->process_entry( $this->test_entry, $this->test_form );
		$this->assertTrue( $result1['success'] );

		// Rotate to new key
		$new_key = 'sk-ant-new-key-xyz789';
		$encryption->save_api_key( $new_key );

		// Process with new key
		add_filter( 'pre_http_request', function( $response, $args, $url ) use ( $new_key ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				// Verify new key is used
				$this->assertEquals( $new_key, $args['headers']['x-api-key'] );

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body' => json_encode( [
						'content' => [ [ 'text' => 'With new key' ] ]
					] )
				];
			}
			return $response;
		}, 10, 3 );

		$result2 = $processor->process_entry( $this->test_entry, $this->test_form );
		$this->assertTrue( $result2['success'] );
	}

	/**
	 * Test complete plugin lifecycle
	 */
	public function test_complete_plugin_lifecycle() {
		// 1. Activation
		$plugin = Plugin::get_instance();
		$plugin->activate();

		// Check defaults are set
		$this->assertNotFalse( get_option( '84em_gf_ai_model' ) );

		// 2. Configuration
		$encryption = new Encryption();
		$encryption->save_api_key( 'sk-ant-lifecycle-test' );
		update_option( '84em_gf_ai_enabled', true );

		// 3. Form creation and submission
		$form = [
			'id' => 500,
			'title' => 'Lifecycle Test Form',
			'fields' => [
				(object) [ 'id' => 1, 'type' => 'text', 'label' => 'Test Field' ]
			]
		];
		\GFAPI::add_form( $form );

		$entry = [
			'id' => 500,
			'form_id' => 500,
			'1' => 'Test Value'
		];
		\GFAPI::add_entry( $entry );

		// 4. Process entry
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );

		$processor = new EntryProcessor();
		$result = $processor->process_entry( $entry, $form );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

		$this->assertTrue( $result['success'] );

		// 5. Export HTML
		gform_update_meta( 500, '84em_ai_analysis', 'Lifecycle test analysis' );

		$_POST['entry_id'] = 500;
		$_POST['form_id'] = 500;
		$_POST['nonce'] = wp_create_nonce( '84em_gf_ai_html' );

		$user_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		add_filter( 'wp_doing_ajax', '__return_true' );

		ob_start();
		try {
			$processor->ajax_download_html();
		} catch ( \WPDieException $e ) {
			// Expected
		}
		$output = ob_get_clean();

		remove_filter( 'wp_doing_ajax', '__return_true' );

		$response = json_decode( $output, true );
		$this->assertTrue( $response['success'] );

		// 6. View logs
		global $wpdb;
		$table_name = $wpdb->prefix . '84em_gf_ai_logs';
		$log_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE form_id = 500" );
		$this->assertGreaterThan( 0, $log_count );

		// 7. Deactivation (data should persist)
		$plugin->deactivate();

		// Check data persists
		$this->assertNotFalse( get_option( '84em_gf_ai_model' ) );
		$this->assertTrue( $encryption->has_api_key() );
		$this->assertNotEmpty( gform_get_meta( 500, '84em_ai_analysis' ) );
	}
}