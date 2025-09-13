<?php
/**
 * Base test case class for 84EM Gravity Forms AI Analysis
 *
 * @package EightyFourEM\GravityFormsAI\Tests
 */

namespace EightyFourEM\GravityFormsAI\Tests;

use WP_UnitTestCase;

/**
 * Base test case class
 */
class TestCase extends WP_UnitTestCase {

	/**
	 * Plugin instance
	 *
	 * @var \EightyFourEM\GravityFormsAI\Plugin
	 */
	protected $plugin;

	/**
	 * Test form data
	 *
	 * @var array
	 */
	protected $test_form;

	/**
	 * Test entry data
	 *
	 * @var array
	 */
	protected $test_entry;

	/**
	 * Set up test fixtures
	 */
	public function setUp(): void {
		parent::setUp();

		// Reset test data
		reset_test_data();

		// Initialize plugin
		$this->plugin = \EightyFourEM\GravityFormsAI\Plugin::get_instance();

		// Set default options
		update_option( '84em_gf_ai_enabled', true );
		update_option( '84em_gf_ai_model', 'claude-3-5-haiku-20241022' );
		update_option( '84em_gf_ai_max_tokens', 1000 );
		update_option( '84em_gf_ai_temperature', 0.7 );
		update_option( '84em_gf_ai_rate_limit', 2 );
		update_option( '84em_gf_ai_enable_logging', true );
		update_option( '84em_gf_ai_log_retention', 30 );
		update_option( '84em_gf_ai_default_prompt', 'Test prompt for {form_title}' );

		// Create test form
		$this->test_form = $this->create_test_form();

		// Create test entry
		$this->test_entry = $this->create_test_entry();

		// Set up WordPress test user
		$this->factory()->user->create( [
			'user_login' => 'test_admin',
			'user_pass' => 'password',
			'role' => 'administrator'
		] );
	}

	/**
	 * Tear down test fixtures
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Reset test data
		reset_test_data();
	}

	/**
	 * Create a test form
	 *
	 * @return array
	 */
	protected function create_test_form() {
		$form = [
			'id' => 1,
			'title' => 'Test Contact Form',
			'description' => 'Test form for unit tests',
			'fields' => [
				(object) [
					'id' => 1,
					'type' => 'text',
					'label' => 'First Name',
					'adminLabel' => '',
					'isRequired' => true,
					'adminOnly' => false
				],
				(object) [
					'id' => 2,
					'type' => 'text',
					'label' => 'Last Name',
					'adminLabel' => '',
					'isRequired' => true,
					'adminOnly' => false
				],
				(object) [
					'id' => 3,
					'type' => 'name',
					'label' => 'Full Name',
					'adminLabel' => '',
					'isRequired' => false,
					'adminOnly' => false,
					'inputs' => [
						['id' => '3.3', 'label' => 'First'],
						['id' => '3.6', 'label' => 'Last']
					]
				],
				(object) [
					'id' => 4,
					'type' => 'email',
					'label' => 'Email',
					'adminLabel' => '',
					'isRequired' => true,
					'adminOnly' => false
				],
				(object) [
					'id' => 5,
					'type' => 'text',
					'label' => 'Company',
					'adminLabel' => '',
					'isRequired' => false,
					'adminOnly' => false
				],
				(object) [
					'id' => 6,
					'type' => 'textarea',
					'label' => 'Message',
					'adminLabel' => '',
					'isRequired' => false,
					'adminOnly' => false
				],
				(object) [
					'id' => 7,
					'type' => 'checkbox',
					'label' => 'Services',
					'adminLabel' => '',
					'isRequired' => false,
					'adminOnly' => false,
					'choices' => [
						['text' => 'Web Design', 'value' => 'web_design'],
						['text' => 'SEO', 'value' => 'seo'],
						['text' => 'Marketing', 'value' => 'marketing']
					]
				],
				(object) [
					'id' => 8,
					'type' => 'address',
					'label' => 'Address',
					'adminLabel' => '',
					'isRequired' => false,
					'adminOnly' => false
				],
				(object) [
					'id' => 9,
					'type' => 'list',
					'label' => 'References',
					'adminLabel' => '',
					'isRequired' => false,
					'adminOnly' => false
				],
				(object) [
					'id' => 10,
					'type' => 'hidden',
					'label' => 'Hidden Field',
					'adminLabel' => '',
					'isRequired' => false,
					'adminOnly' => false
				],
				(object) [
					'id' => 11,
					'type' => 'html',
					'label' => 'HTML Block',
					'adminLabel' => '',
					'isRequired' => false,
					'adminOnly' => false
				],
				(object) [
					'id' => 12,
					'type' => 'section',
					'label' => 'Section Break',
					'adminLabel' => '',
					'isRequired' => false,
					'adminOnly' => false
				]
			]
		];

		// Add form to GFAPI
		if ( class_exists( 'GFAPI' ) ) {
			\GFAPI::add_form( $form );
		}

		return $form;
	}

	/**
	 * Create a test entry
	 *
	 * @return array
	 */
	protected function create_test_entry() {
		$entry = [
			'id' => 1,
			'form_id' => 1,
			'date_created' => current_time( 'mysql' ),
			'is_starred' => 0,
			'is_read' => 0,
			'ip' => '127.0.0.1',
			'source_url' => 'http://test.local/contact',
			'user_agent' => 'Mozilla/5.0 Test',
			'currency' => 'USD',
			'payment_status' => null,
			'payment_date' => null,
			'payment_amount' => null,
			'transaction_id' => null,
			'is_fulfilled' => null,
			'created_by' => 1,
			'transaction_type' => null,
			'status' => 'active',
			// Field values
			'1' => 'John',
			'2' => 'Doe',
			'3.3' => 'John',
			'3.6' => 'Doe',
			'4' => 'john.doe@example.com',
			'5' => 'Acme Corp',
			'6' => 'This is a test message for the AI analysis.',
			'7.1' => 'Web Design',
			'7.2' => '',
			'7.3' => 'Marketing',
			'8.1' => '123 Main St',
			'8.2' => 'Suite 100',
			'8.3' => 'New York',
			'8.4' => 'NY',
			'8.5' => '10001',
			'8.6' => 'United States',
			'9' => serialize( [
				['Reference 1'],
				['Reference 2']
			] ),
			'10' => 'hidden_value'
		];

		// Add entry to GFAPI
		if ( class_exists( 'GFAPI' ) ) {
			\GFAPI::add_entry( $entry );
		}

		return $entry;
	}

	/**
	 * Mock HTTP request for API testing
	 *
	 * @param mixed $response Response to return
	 * @param array $args Request arguments
	 * @param string $url Request URL
	 * @return mixed
	 */
	public function mock_http_request( $response, $args, $url ) {
		// Check if this is our API endpoint
		if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
			// Return mock response
			return [
				'response' => [
					'code' => 200,
					'message' => 'OK'
				],
				'body' => json_encode( [
					'content' => [
						[
							'text' => 'This is a mock AI response for testing.'
						]
					],
					'usage' => [
						'input_tokens' => 100,
						'output_tokens' => 50
					]
				] )
			];
		}

		return $response;
	}

	/**
	 * Mock HTTP request with error
	 *
	 * @param mixed $response Response to return
	 * @param array $args Request arguments
	 * @param string $url Request URL
	 * @return mixed
	 */
	public function mock_http_request_error( $response, $args, $url ) {
		if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
			return new \WP_Error( 'http_request_failed', 'Connection timeout' );
		}
		return $response;
	}

	/**
	 * Mock HTTP request with API error
	 *
	 * @param mixed $response Response to return
	 * @param array $args Request arguments
	 * @param string $url Request URL
	 * @return mixed
	 */
	public function mock_http_request_api_error( $response, $args, $url ) {
		if ( strpos( $url, 'api.anthropic.com' ) !== false ) {
			return [
				'response' => [
					'code' => 401,
					'message' => 'Unauthorized'
				],
				'body' => json_encode( [
					'error' => [
						'message' => 'Invalid API key'
					]
				] )
			];
		}
		return $response;
	}

	/**
	 * Set up mock API key
	 *
	 * @param string $api_key API key to use
	 */
	protected function set_mock_api_key( $api_key = 'test_api_key_123' ) {
		$encryption = new \EightyFourEM\GravityFormsAI\Core\Encryption();
		$encryption->save_api_key( $api_key );
	}

	/**
	 * Assert that an option exists and has expected value
	 *
	 * @param string $option_name Option name
	 * @param mixed $expected Expected value
	 */
	protected function assertOptionEquals( $option_name, $expected ) {
		$actual = get_option( $option_name );
		$this->assertEquals( $expected, $actual, "Option {$option_name} does not match expected value" );
	}

	/**
	 * Assert that entry meta exists and has expected value
	 *
	 * @param int $entry_id Entry ID
	 * @param string $meta_key Meta key
	 * @param mixed $expected Expected value
	 */
	protected function assertEntryMetaEquals( $entry_id, $meta_key, $expected ) {
		$actual = gform_get_meta( $entry_id, $meta_key );
		$this->assertEquals( $expected, $actual, "Entry meta {$meta_key} does not match expected value" );
	}

	/**
	 * Assert that a database table exists
	 *
	 * @param string $table_name Table name (without prefix)
	 */
	protected function assertTableExists( $table_name ) {
		global $wpdb;
		$full_table_name = $wpdb->prefix . $table_name;
		$exists = $wpdb->get_var( "SHOW TABLES LIKE '$full_table_name'" ) === $full_table_name;
		$this->assertTrue( $exists, "Table {$full_table_name} does not exist" );
	}

	/**
	 * Assert that a log entry exists in database
	 *
	 * @param array $criteria Search criteria
	 */
	protected function assertLogExists( $criteria ) {
		global $wpdb;
		$table_name = $wpdb->prefix . '84em_gf_ai_logs';

		$where_parts = [];
		$where_values = [];
		foreach ( $criteria as $key => $value ) {
			$where_parts[] = "$key = %s";
			$where_values[] = $value;
		}

		$where_clause = implode( ' AND ', $where_parts );
		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM $table_name WHERE $where_clause",
			$where_values
		);

		$count = $wpdb->get_var( $query );
		$this->assertGreaterThan( 0, $count, 'Log entry not found with given criteria' );
	}
}