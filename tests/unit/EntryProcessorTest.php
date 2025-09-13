<?php
/**
 * Tests for Core/EntryProcessor.php
 *
 * @package EightyFourEM\GravityFormsAI\Tests
 */

namespace EightyFourEM\GravityFormsAI\Tests\Unit;

use EightyFourEM\GravityFormsAI\Tests\TestCase;
use EightyFourEM\GravityFormsAI\Core\EntryProcessor;
use EightyFourEM\GravityFormsAI\Core\Encryption;

/**
 * EntryProcessor test class
 */
class EntryProcessorTest extends TestCase {

	/**
	 * EntryProcessor instance
	 *
	 * @var EntryProcessor
	 */
	private $processor;

	/**
	 * Set up test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->processor = new EntryProcessor();

		// Set up mock API key
		$this->set_mock_api_key( 'sk-ant-test-key-123' );

		// Enable AI analysis
		update_option( '84em_gf_ai_enabled', true );
	}

	/**
	 * Test successful entry processing
	 */
	public function test_process_entry_success() {
		// Mock the API response
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );

		$result = $this->processor->process_entry( $this->test_entry, $this->test_form );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

		$this->assertTrue( $result['success'], 'Entry processing should succeed' );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertEquals( 'This is a mock AI response for testing.', $result['data'] );

		// Check that analysis was saved to meta
		$this->assertEntryMetaEquals( 1, '84em_ai_analysis', 'This is a mock AI response for testing.' );
		$this->assertNotEmpty( gform_get_meta( 1, '84em_ai_analysis_date' ) );
	}

	/**
	 * Test processing with global AI disabled
	 */
	public function test_process_entry_disabled_global() {
		update_option( '84em_gf_ai_enabled', false );

		$result = $this->processor->process_entry( $this->test_entry, $this->test_form );

		$this->assertFalse( $result['success'], 'Processing should fail when globally disabled' );
		$this->assertEquals( 'Global AI analysis is disabled', $result['error'] );
	}

	/**
	 * Test processing with form-specific AI disabled
	 */
	public function test_process_entry_disabled_form() {
		// Set form-specific setting to disabled
		update_option( '84em_gf_ai_enabled_1', '0' );

		$result = $this->processor->process_entry( $this->test_entry, $this->test_form );

		$this->assertFalse( $result['success'], 'Processing should fail when form-specifically disabled' );
		$this->assertEquals( 'AI analysis is disabled for this form', $result['error'] );
	}

	/**
	 * Test processing with no field mapping (auto-selection)
	 */
	public function test_process_entry_no_mapping() {
		// No mapping set, should auto-include all analyzable fields
		delete_option( '84em_gf_ai_mapping_1' );

		add_filter( 'pre_http_request', function( $response, $args, $url ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				$body = json_decode( $args['body'], true );
				$message = $body['messages'][0]['content'];

				// Should include various fields but not system fields
				$this->assertStringContainsString( 'First Name: John', $message );
				$this->assertStringContainsString( 'Email: john.doe@example.com', $message );
				$this->assertStringNotContainsString( 'HTML Block', $message );
				$this->assertStringNotContainsString( 'Section Break', $message );

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body' => json_encode( [
						'content' => [ [ 'text' => 'Analyzed' ] ]
					] )
				];
			}
			return $response;
		}, 10, 3 );

		$result = $this->processor->process_entry( $this->test_entry, $this->test_form );

		$this->assertTrue( $result['success'] );
	}

	/**
	 * Test processing with specific field mapping
	 */
	public function test_process_entry_with_mapping() {
		// Set specific field mapping
		update_option( '84em_gf_ai_mapping_1', [ 1, 4 ] ); // Only First Name and Email

		add_filter( 'pre_http_request', function( $response, $args, $url ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				$body = json_decode( $args['body'], true );
				$message = $body['messages'][0]['content'];

				// Should only include mapped fields
				$this->assertStringContainsString( 'First Name: John', $message );
				$this->assertStringContainsString( 'Email: john.doe@example.com', $message );
				// Should not include unmapped fields
				$this->assertStringNotContainsString( 'Company', $message );
				$this->assertStringNotContainsString( 'Message', $message );

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body' => json_encode( [
						'content' => [ [ 'text' => 'Analyzed' ] ]
					] )
				];
			}
			return $response;
		}, 10, 3 );

		$result = $this->processor->process_entry( $this->test_entry, $this->test_form );

		$this->assertTrue( $result['success'] );
	}

	/**
	 * Test processing with custom prompt
	 */
	public function test_process_entry_custom_prompt() {
		$custom_prompt = 'Custom analysis for {form_title}: {form_data}';
		update_option( '84em_gf_ai_prompt_1', $custom_prompt );

		add_filter( 'pre_http_request', function( $response, $args, $url ) use ( $custom_prompt ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				$body = json_decode( $args['body'], true );
				$message = $body['messages'][0]['content'];

				// Should use custom prompt
				$this->assertStringContainsString( 'Custom analysis for Test Contact Form', $message );

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body' => json_encode( [
						'content' => [ [ 'text' => 'Custom analyzed' ] ]
					] )
				];
			}
			return $response;
		}, 10, 3 );

		$result = $this->processor->process_entry( $this->test_entry, $this->test_form );

		$this->assertTrue( $result['success'] );
	}

	/**
	 * Test getting all analyzable fields
	 */
	public function test_get_all_analyzable_fields() {
		// Use reflection to test private method
		$reflection = new \ReflectionClass( $this->processor );
		$method = $reflection->getMethod( 'get_all_analyzable_field_ids' );
		$method->setAccessible( true );

		$field_ids = $method->invoke( $this->processor, $this->test_form );

		// Should include regular fields
		$this->assertContains( 1, $field_ids ); // Text field
		$this->assertContains( 4, $field_ids ); // Email field
		$this->assertContains( 6, $field_ids ); // Textarea field

		// Should exclude system fields
		$this->assertNotContains( 10, $field_ids ); // Hidden field
		$this->assertNotContains( 11, $field_ids ); // HTML block
		$this->assertNotContains( 12, $field_ids ); // Section break
	}

	/**
	 * Test collecting form data
	 */
	public function test_collect_form_data() {
		$reflection = new \ReflectionClass( $this->processor );
		$method = $reflection->getMethod( 'collect_form_data' );
		$method->setAccessible( true );

		$data = $method->invoke( $this->processor, $this->test_entry, $this->test_form, [ 1, 4, 5 ] );

		$this->assertArrayHasKey( 'First Name', $data );
		$this->assertEquals( 'John', $data['First Name'] );
		$this->assertArrayHasKey( 'Email', $data );
		$this->assertEquals( 'john.doe@example.com', $data['Email'] );
		$this->assertArrayHasKey( 'Company', $data );
		$this->assertEquals( 'Acme Corp', $data['Company'] );
	}

	/**
	 * Test field value extraction for text fields
	 */
	public function test_get_field_value_text() {
		$reflection = new \ReflectionClass( $this->processor );
		$method = $reflection->getMethod( 'get_field_value' );
		$method->setAccessible( true );

		$field = $this->test_form['fields'][0]; // First Name text field
		$value = $method->invoke( $this->processor, $this->test_entry, $field );

		$this->assertEquals( 'John', $value );
	}

	/**
	 * Test field value extraction for name fields
	 */
	public function test_get_field_value_name() {
		$reflection = new \ReflectionClass( $this->processor );
		$method = $reflection->getMethod( 'get_field_value' );
		$method->setAccessible( true );

		$field = $this->test_form['fields'][2]; // Full Name field
		$value = $method->invoke( $this->processor, $this->test_entry, $field );

		$this->assertEquals( 'John Doe', $value );
	}

	/**
	 * Test field value extraction for email fields
	 */
	public function test_get_field_value_email() {
		$reflection = new \ReflectionClass( $this->processor );
		$method = $reflection->getMethod( 'get_field_value' );
		$method->setAccessible( true );

		$field = $this->test_form['fields'][3]; // Email field
		$value = $method->invoke( $this->processor, $this->test_entry, $field );

		$this->assertEquals( 'john.doe@example.com', $value );
	}

	/**
	 * Test field value extraction for address fields
	 */
	public function test_get_field_value_address() {
		$reflection = new \ReflectionClass( $this->processor );
		$method = $reflection->getMethod( 'get_field_value' );
		$method->setAccessible( true );

		$field = $this->test_form['fields'][7]; // Address field
		$value = $method->invoke( $this->processor, $this->test_entry, $field );

		$this->assertEquals( '123 Main St, Suite 100, New York, NY, 10001, United States', $value );
	}

	/**
	 * Test field value extraction for checkbox fields
	 */
	public function test_get_field_value_checkbox() {
		$reflection = new \ReflectionClass( $this->processor );
		$method = $reflection->getMethod( 'get_field_value' );
		$method->setAccessible( true );

		$field = $this->test_form['fields'][6]; // Services checkbox field
		$value = $method->invoke( $this->processor, $this->test_entry, $field );

		$this->assertEquals( 'Web Design, Marketing', $value );
	}

	/**
	 * Test field value extraction for list fields
	 */
	public function test_get_field_value_list() {
		$reflection = new \ReflectionClass( $this->processor );
		$method = $reflection->getMethod( 'get_field_value' );
		$method->setAccessible( true );

		$field = $this->test_form['fields'][8]; // References list field
		$value = $method->invoke( $this->processor, $this->test_entry, $field );

		$this->assertEquals( 'Reference 1; Reference 2', $value );
	}

	/**
	 * Test name extraction
	 */
	public function test_extract_name() {
		$reflection = new \ReflectionClass( $this->processor );
		$method = $reflection->getMethod( 'extract_name' );
		$method->setAccessible( true );

		$name = $method->invoke( $this->processor, $this->test_entry, $this->test_form );

		$this->assertEquals( 'John Doe', $name );
	}

	/**
	 * Test email extraction
	 */
	public function test_extract_email() {
		$reflection = new \ReflectionClass( $this->processor );
		$method = $reflection->getMethod( 'extract_email' );
		$method->setAccessible( true );

		$email = $method->invoke( $this->processor, $this->test_entry, $this->test_form );

		$this->assertEquals( 'john.doe@example.com', $email );
	}

	/**
	 * Test company extraction
	 */
	public function test_extract_company() {
		$reflection = new \ReflectionClass( $this->processor );
		$method = $reflection->getMethod( 'extract_company' );
		$method->setAccessible( true );

		$company = $method->invoke( $this->processor, $this->test_entry, $this->test_form );

		$this->assertEquals( 'Acme Corp', $company );
	}

	/**
	 * Test AJAX entry analysis
	 */
	public function test_ajax_analyze_entry() {
		// Set up user with proper permissions
		$user_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Set up POST data
		$_POST['entry_id'] = 1;
		$_POST['form_id'] = 1;
		$_POST['nonce'] = wp_create_nonce( '84em_gf_ai_analyze' );

		// Mock AJAX request
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );
		add_filter( 'wp_doing_ajax', '__return_true' );

		// Capture JSON output
		ob_start();
		try {
			$this->processor->ajax_analyze_entry();
		} catch ( \WPDieException $e ) {
			// Expected for AJAX responses
		}
		$output = ob_get_clean();

		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );
		remove_filter( 'wp_doing_ajax', '__return_true' );

		$response = json_decode( $output, true );
		$this->assertTrue( $response['success'] );
		$this->assertEquals( 'Analysis completed successfully', $response['data']['message'] );
	}

	/**
	 * Test AJAX entry analysis without permission
	 */
	public function test_ajax_analyze_no_permission() {
		// Set up user without permissions
		$user_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$_POST['entry_id'] = 1;
		$_POST['form_id'] = 1;
		$_POST['nonce'] = wp_create_nonce( '84em_gf_ai_analyze' );

		add_filter( 'wp_doing_ajax', '__return_true' );

		ob_start();
		try {
			$this->processor->ajax_analyze_entry();
		} catch ( \WPDieException $e ) {
			// Expected
		}
		$output = ob_get_clean();

		remove_filter( 'wp_doing_ajax', '__return_true' );

		$response = json_decode( $output, true );
		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'Insufficient permissions', $response['data']['message'] );
	}

	/**
	 * Test error meta storage
	 */
	public function test_error_meta_storage() {
		// Mock API error
		add_filter( 'pre_http_request', [ $this, 'mock_http_request_error' ], 10, 3 );

		$result = $this->processor->process_entry( $this->test_entry, $this->test_form );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_request_error' ] );

		$this->assertFalse( $result['success'] );

		// Check error was stored in meta
		$this->assertEntryMetaEquals( 1, '84em_ai_analysis_error', 'Connection timeout' );
		$this->assertNotEmpty( gform_get_meta( 1, '84em_ai_analysis_error_date' ) );
	}

	/**
	 * Test filters are applied
	 */
	public function test_apply_filters() {
		$filter_called = false;

		// Add test filter
		add_filter( '84em_gf_ai_analysis_prompt', function( $prompt, $context ) use ( &$filter_called ) {
			$filter_called = true;
			return $prompt . ' [FILTERED]';
		}, 10, 2 );

		add_filter( 'pre_http_request', function( $response, $args, $url ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				$body = json_decode( $args['body'], true );
				$message = $body['messages'][0]['content'];

				// Check that filter was applied
				$this->assertStringContainsString( '[FILTERED]', $message );

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body' => json_encode( [
						'content' => [ [ 'text' => 'Filtered response' ] ]
					] )
				];
			}
			return $response;
		}, 10, 3 );

		$this->processor->process_entry( $this->test_entry, $this->test_form );

		$this->assertTrue( $filter_called, 'Filter should be called' );
	}

	/**
	 * Test actions are fired
	 */
	public function test_do_actions() {
		$action_called = false;

		// Add test action
		add_action( '84em_gf_ai_after_analysis', function( $entry_id, $analysis, $form_id ) use ( &$action_called ) {
			$action_called = true;
			$this->assertEquals( 1, $entry_id );
			$this->assertEquals( 1, $form_id );
			$this->assertNotEmpty( $analysis );
		}, 10, 3 );

		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );

		$this->processor->process_entry( $this->test_entry, $this->test_form );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

		$this->assertTrue( $action_called, 'Action should be fired' );
	}

	/**
	 * Test form-specific setting with null (use global)
	 */
	public function test_form_setting_null_uses_global() {
		// Set global to enabled
		update_option( '84em_gf_ai_enabled', true );
		// Form-specific is not set (null)
		delete_option( '84em_gf_ai_enabled_1' );

		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );

		$result = $this->processor->process_entry( $this->test_entry, $this->test_form );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

		$this->assertTrue( $result['success'], 'Should use global setting when form-specific is null' );
	}

	/**
	 * Test processing with file upload field
	 */
	public function test_get_field_value_file() {
		// Add a file upload field to test form
		$file_field = (object) [
			'id' => 13,
			'type' => 'fileupload',
			'label' => 'Resume',
			'adminOnly' => false
		];
		$this->test_form['fields'][] = $file_field;

		// Add file value to entry
		$this->test_entry['13'] = 'http://example.com/uploads/resume.pdf';

		$reflection = new \ReflectionClass( $this->processor );
		$method = $reflection->getMethod( 'get_field_value' );
		$method->setAccessible( true );

		$value = $method->invoke( $this->processor, $this->test_entry, $file_field );

		$this->assertEquals( 'File: resume.pdf', $value );
	}

	/**
	 * Test name extraction fallback to text field
	 */
	public function test_extract_name_fallback() {
		// Create form without name field but with text field labeled "name"
		$form = [
			'id' => 2,
			'title' => 'Test Form 2',
			'fields' => [
				(object) [
					'id' => 1,
					'type' => 'text',
					'label' => 'Your Name',
					'adminOnly' => false
				]
			]
		];

		$entry = [
			'id' => 2,
			'form_id' => 2,
			'1' => 'Jane Smith'
		];

		$reflection = new \ReflectionClass( $this->processor );
		$method = $reflection->getMethod( 'extract_name' );
		$method->setAccessible( true );

		$name = $method->invoke( $this->processor, $entry, $form );

		$this->assertEquals( 'Jane Smith', $name );
	}
}