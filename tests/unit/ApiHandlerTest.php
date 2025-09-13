<?php
/**
 * Tests for Core/APIHandler.php
 *
 * @package EightyFourEM\GravityFormsAI\Tests
 */

namespace EightyFourEM\GravityFormsAI\Tests\Unit;

use EightyFourEM\GravityFormsAI\Tests\TestCase;
use EightyFourEM\GravityFormsAI\Core\APIHandler;
use EightyFourEM\GravityFormsAI\Core\Encryption;

/**
 * APIHandler test class
 */
class ApiHandlerTest extends TestCase {

	/**
	 * APIHandler instance
	 *
	 * @var APIHandler
	 */
	private $api_handler;

	/**
	 * Set up test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->api_handler = new APIHandler();

		// Set up mock API key
		$this->set_mock_api_key( 'sk-ant-test-key-123' );

		// Enable AI analysis
		update_option( '84em_gf_ai_enabled', true );
	}

	/**
	 * Test successful API analysis
	 */
	public function test_analyze_success() {
		// Mock the HTTP request
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );

		$result = $this->api_handler->analyze( 'Test prompt', [
			'form_id' => 1,
			'entry_id' => 1,
			'form_title' => 'Test Form',
			'form_data' => [ 'Name' => 'John Doe' ]
		] );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

		$this->assertTrue( $result['success'], 'API call should succeed' );
		$this->assertArrayHasKey( 'data', $result, 'Result should have data key' );
		$this->assertEquals( 'This is a mock AI response for testing.', $result['data'] );
		$this->assertArrayHasKey( 'usage', $result, 'Result should have usage data' );
	}

	/**
	 * Test analysis when AI is disabled
	 */
	public function test_analyze_disabled() {
		update_option( '84em_gf_ai_enabled', false );

		$result = $this->api_handler->analyze( 'Test prompt', [] );

		$this->assertFalse( $result['success'], 'Analysis should fail when disabled' );
		$this->assertEquals( 'AI analysis is disabled.', $result['error'] );
	}

	/**
	 * Test analysis without API key
	 */
	public function test_analyze_no_api_key() {
		// Remove API key
		$encryption = new Encryption();
		$encryption->delete_api_key();

		$result = $this->api_handler->analyze( 'Test prompt', [] );

		$this->assertFalse( $result['success'], 'Analysis should fail without API key' );
		$this->assertEquals( 'API key not configured.', $result['error'] );
	}

	/**
	 * Test rate limiting
	 */
	public function test_analyze_rate_limiting() {
		// Set rate limit to 2 seconds
		update_option( '84em_gf_ai_rate_limit', 2 );

		// Mock HTTP requests
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );

		// First request
		$start_time = microtime( true );
		$this->api_handler->analyze( 'First request', [] );

		// Second request should be delayed
		$this->api_handler->analyze( 'Second request', [] );
		$elapsed_time = microtime( true ) - $start_time;

		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

		// Should have waited at least 2 seconds
		$this->assertGreaterThanOrEqual( 2, $elapsed_time, 'Rate limiting should enforce delay' );
	}

	/**
	 * Test API error handling
	 */
	public function test_analyze_api_error() {
		add_filter( 'pre_http_request', [ $this, 'mock_http_request_api_error' ], 10, 3 );

		$result = $this->api_handler->analyze( 'Test prompt', [] );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_request_api_error' ] );

		$this->assertFalse( $result['success'], 'Analysis should fail on API error' );
		$this->assertEquals( 'Invalid API key', $result['error'] );
		$this->assertEquals( 401, $result['code'] );
	}

	/**
	 * Test network error handling
	 */
	public function test_analyze_network_error() {
		add_filter( 'pre_http_request', [ $this, 'mock_http_request_error' ], 10, 3 );

		$result = $this->api_handler->analyze( 'Test prompt', [] );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_request_error' ] );

		$this->assertFalse( $result['success'], 'Analysis should fail on network error' );
		$this->assertEquals( 'Connection timeout', $result['error'] );
	}

	/**
	 * Test invalid API response format
	 */
	public function test_analyze_invalid_response() {
		// Mock invalid response
		add_filter( 'pre_http_request', function( $response, $args, $url ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body' => json_encode( [ 'invalid' => 'response' ] )
				];
			}
			return $response;
		}, 10, 3 );

		$result = $this->api_handler->analyze( 'Test prompt', [] );

		$this->assertFalse( $result['success'], 'Analysis should fail with invalid response' );
		$this->assertEquals( 'Invalid API response format.', $result['error'] );
	}

	/**
	 * Test connection testing
	 */
	public function test_test_connection() {
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );

		$result = $this->api_handler->test_connection();

		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

		$this->assertTrue( $result['success'], 'Connection test should succeed' );
	}

	/**
	 * Test message preparation with all variables
	 */
	public function test_prepare_message_all_vars() {
		add_filter( 'pre_http_request', function( $response, $args, $url ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				// Capture the request body to verify message formatting
				$body = json_decode( $args['body'], true );
				$message = $body['messages'][0]['content'];

				// Check that variables were replaced
				$this->assertStringContainsString( 'John Doe', $message );
				$this->assertStringContainsString( 'john@example.com', $message );
				$this->assertStringContainsString( 'Acme Corp', $message );
				$this->assertStringContainsString( 'Test Form', $message );

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body' => json_encode( [
						'content' => [ [ 'text' => 'Processed' ] ]
					] )
				];
			}
			return $response;
		}, 10, 3 );

		$prompt = 'Analyze {form_title} from {submitter_name} at {submitter_email} from {submitter_company}';
		$data = [
			'form_title' => 'Test Form',
			'submitter_name' => 'John Doe',
			'submitter_email' => 'john@example.com',
			'submitter_company' => 'Acme Corp',
			'form_data' => [ 'Field' => 'Value' ]
		];

		$this->api_handler->analyze( $prompt, $data );
	}

	/**
	 * Test message preparation with partial variables
	 */
	public function test_prepare_message_partial_vars() {
		add_filter( 'pre_http_request', function( $response, $args, $url ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				$body = json_decode( $args['body'], true );
				$message = $body['messages'][0]['content'];

				// Check that available variables were replaced
				$this->assertStringContainsString( 'Test Form', $message );
				// Empty variables should be replaced with empty string
				$this->assertStringNotContainsString( '{submitter_name}', $message );

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body' => json_encode( [
						'content' => [ [ 'text' => 'Processed' ] ]
					] )
				];
			}
			return $response;
		}, 10, 3 );

		$prompt = 'Analyze {form_title} from {submitter_name}';
		$data = [
			'form_title' => 'Test Form',
			// submitter_name is missing
		];

		$this->api_handler->analyze( $prompt, $data );
	}

	/**
	 * Test form data formatting
	 */
	public function test_format_form_data() {
		add_filter( 'pre_http_request', function( $response, $args, $url ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				$body = json_decode( $args['body'], true );
				$message = $body['messages'][0]['content'];

				// Check form data formatting
				$this->assertStringContainsString( 'Form Submission Data:', $message );
				$this->assertStringContainsString( '- Name: John Doe', $message );
				$this->assertStringContainsString( '- Email: john@example.com', $message );

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body' => json_encode( [
						'content' => [ [ 'text' => 'Processed' ] ]
					] )
				];
			}
			return $response;
		}, 10, 3 );

		$data = [
			'form_data' => [
				'Name' => 'John Doe',
				'Email' => 'john@example.com'
			]
		];

		$this->api_handler->analyze( '{form_data}', $data );
	}

	/**
	 * Test form data formatting with arrays
	 */
	public function test_format_form_data_arrays() {
		add_filter( 'pre_http_request', function( $response, $args, $url ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				$body = json_decode( $args['body'], true );
				$message = $body['messages'][0]['content'];

				// Array values should be comma-separated
				$this->assertStringContainsString( '- Services: Web Design, SEO', $message );

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body' => json_encode( [
						'content' => [ [ 'text' => 'Processed' ] ]
					] )
				];
			}
			return $response;
		}, 10, 3 );

		$data = [
			'form_data' => [
				'Services' => [ 'Web Design', 'SEO' ]
			]
		];

		$this->api_handler->analyze( '{form_data}', $data );
	}

	/**
	 * Test empty form data
	 */
	public function test_format_form_data_empty() {
		add_filter( 'pre_http_request', function( $response, $args, $url ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				$body = json_decode( $args['body'], true );
				$message = $body['messages'][0]['content'];

				// Should handle empty form data gracefully
				$this->assertStringNotContainsString( 'Form Submission Data:', $message );

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body' => json_encode( [
						'content' => [ [ 'text' => 'Processed' ] ]
					] )
				];
			}
			return $response;
		}, 10, 3 );

		$this->api_handler->analyze( '{form_data}', [ 'form_data' => [] ] );
	}

	/**
	 * Test logging when enabled
	 */
	public function test_logging_enabled() {
		global $wpdb;
		$table_name = $wpdb->prefix . '84em_gf_ai_logs';

		// Enable logging
		update_option( '84em_gf_ai_enable_logging', true );

		// Mock successful request
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );

		$this->api_handler->analyze( 'Test prompt', [
			'form_id' => 1,
			'entry_id' => 2
		] );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

		// Check that log was created
		$this->assertLogExists( [
			'form_id' => 1,
			'entry_id' => 2,
			'status' => 'success'
		] );
	}

	/**
	 * Test logging when disabled
	 */
	public function test_logging_disabled() {
		global $wpdb;
		$table_name = $wpdb->prefix . '84em_gf_ai_logs';

		// Disable logging
		update_option( '84em_gf_ai_enable_logging', false );

		// Mock successful request
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );

		$this->api_handler->analyze( 'Test prompt', [
			'form_id' => 3,
			'entry_id' => 4
		] );

		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

		// Check that no log was created
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE form_id = 3 AND entry_id = 4" );
		$this->assertEquals( 0, $count, 'No log should be created when logging is disabled' );
	}

	/**
	 * Test log purging
	 */
	public function test_log_purging() {
		global $wpdb;
		$table_name = $wpdb->prefix . '84em_gf_ai_logs';

		// Set retention to 1 day
		update_option( '84em_gf_ai_log_retention', 1 );
		update_option( '84em_gf_ai_enable_logging', true );

		// Insert an old log entry directly
		$wpdb->insert(
			$table_name,
			[
				'form_id' => 99,
				'entry_id' => 99,
				'status' => 'success',
				'created_at' => date( 'Y-m-d H:i:s', strtotime( '-2 days' ) )
			]
		);

		// Insert a recent log entry
		$wpdb->insert(
			$table_name,
			[
				'form_id' => 100,
				'entry_id' => 100,
				'status' => 'success',
				'created_at' => current_time( 'mysql' )
			]
		);

		// Make a request to trigger purging
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );
		$this->api_handler->analyze( 'Test', [] );
		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

		// Old log should be deleted
		$old_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE form_id = 99" );
		$this->assertEquals( 0, $old_count, 'Old log should be purged' );

		// Recent log should remain
		$recent_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE form_id = 100" );
		$this->assertEquals( 1, $recent_count, 'Recent log should not be purged' );
	}

	/**
	 * Test log table creation
	 */
	public function test_ensure_log_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . '84em_gf_ai_logs';

		// Drop table if exists
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

		// Enable logging and make a request
		update_option( '84em_gf_ai_enable_logging', true );
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );
		$this->api_handler->analyze( 'Test', [] );
		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

		// Table should be created
		$this->assertTableExists( '84em_gf_ai_logs' );
	}

	/**
	 * Test web search instruction
	 */
	public function test_web_search_instruction() {
		add_filter( 'pre_http_request', function( $response, $args, $url ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				$body = json_decode( $args['body'], true );
				$message = $body['messages'][0]['content'];

				// Should include search instruction
				$this->assertStringContainsString( 'Please search for publicly available information', $message );
				$this->assertStringContainsString( 'John Doe from Acme Corp', $message );

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body' => json_encode( [
						'content' => [ [ 'text' => 'Processed' ] ]
					] )
				];
			}
			return $response;
		}, 10, 3 );

		$data = [
			'submitter_name' => 'John Doe',
			'submitter_company' => 'Acme Corp'
		];

		$this->api_handler->analyze( 'Test prompt', $data );
	}

	/**
	 * Test different model configurations
	 */
	public function test_different_models() {
		$models = [
			'claude-3-5-haiku-20241022',
			'claude-opus-4-1-20250805',
			'claude-3-haiku-20240307'
		];

		add_filter( 'pre_http_request', function( $response, $args, $url ) use ( &$captured_model ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				$body = json_decode( $args['body'], true );
				$captured_model = $body['model'];

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body' => json_encode( [
						'content' => [ [ 'text' => 'Response' ] ]
					] )
				];
			}
			return $response;
		}, 10, 3 );

		foreach ( $models as $model ) {
			update_option( '84em_gf_ai_model', $model );
			$captured_model = null;

			$this->api_handler->analyze( 'Test', [] );

			$this->assertEquals( $model, $captured_model, "Model $model should be used in request" );
		}
	}

	/**
	 * Test temperature and max tokens settings
	 */
	public function test_temperature_and_tokens() {
		update_option( '84em_gf_ai_temperature', 0.5 );
		update_option( '84em_gf_ai_max_tokens', 2000 );

		add_filter( 'pre_http_request', function( $response, $args, $url ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				$body = json_decode( $args['body'], true );

				$this->assertEquals( 0.5, $body['temperature'], 'Temperature should match setting' );
				$this->assertEquals( 2000, $body['max_tokens'], 'Max tokens should match setting' );

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body' => json_encode( [
						'content' => [ [ 'text' => 'Response' ] ]
					] )
				];
			}
			return $response;
		}, 10, 3 );

		$this->api_handler->analyze( 'Test', [] );
	}

	/**
	 * Test system prompt
	 */
	public function test_system_prompt() {
		add_filter( 'pre_http_request', function( $response, $args, $url ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				$body = json_decode( $args['body'], true );

				$this->assertArrayHasKey( 'system', $body, 'System prompt should be included' );
				$this->assertStringContainsString( 'AI assistant analyzing form submissions', $body['system'] );

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body' => json_encode( [
						'content' => [ [ 'text' => 'Response' ] ]
					] )
				];
			}
			return $response;
		}, 10, 3 );

		$this->api_handler->analyze( 'Test', [] );
	}

	/**
	 * Test API headers
	 */
	public function test_api_headers() {
		$encryption = new Encryption();
		$test_key = 'sk-ant-test-key-456';
		$encryption->save_api_key( $test_key );

		add_filter( 'pre_http_request', function( $response, $args, $url ) use ( $test_key ) {
			if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
				// Check headers
				$this->assertArrayHasKey( 'headers', $args );
				$this->assertEquals( 'application/json', $args['headers']['Content-Type'] );
				$this->assertEquals( $test_key, $args['headers']['x-api-key'] );
				$this->assertEquals( '2023-06-01', $args['headers']['anthropic-version'] );

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body' => json_encode( [
						'content' => [ [ 'text' => 'Response' ] ]
					] )
				];
			}
			return $response;
		}, 10, 3 );

		$this->api_handler->analyze( 'Test', [] );
	}
}