<?php
/**
 * Tests for Core/Encryption.php
 *
 * @package EightyFourEM\GravityFormsAI\Tests
 */

namespace EightyFourEM\GravityFormsAI\Tests\Unit;

use EightyFourEM\GravityFormsAI\Tests\TestCase;
use EightyFourEM\GravityFormsAI\Core\Encryption;

/**
 * Encryption test class
 */
class EncryptionTest extends TestCase {

	/**
	 * Encryption instance
	 *
	 * @var Encryption
	 */
	private $encryption;

	/**
	 * Set up test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->encryption = new Encryption();
	}

	/**
	 * Test encrypting valid data
	 */
	public function test_encrypt_valid_data() {
		$plaintext = 'sk-ant-api03-test-key-123456789';
		$encrypted = $this->encryption->encrypt( $plaintext );

		$this->assertNotFalse( $encrypted, 'Encryption should not return false for valid data' );
		$this->assertNotEquals( $plaintext, $encrypted, 'Encrypted data should differ from plaintext' );
		$this->assertIsString( $encrypted, 'Encrypted data should be a string' );
	}

	/**
	 * Test encrypting empty data
	 */
	public function test_encrypt_empty_data() {
		$encrypted = $this->encryption->encrypt( '' );
		$this->assertFalse( $encrypted, 'Encryption should return false for empty data' );
	}

	/**
	 * Test decrypting valid data
	 */
	public function test_decrypt_valid_data() {
		$plaintext = 'sk-ant-api03-test-key-123456789';
		$encrypted = $this->encryption->encrypt( $plaintext );
		$decrypted = $this->encryption->decrypt( $encrypted );

		$this->assertEquals( $plaintext, $decrypted, 'Decrypted data should match original plaintext' );
	}

	/**
	 * Test decrypting empty data
	 */
	public function test_decrypt_empty_data() {
		$decrypted = $this->encryption->decrypt( '' );
		$this->assertFalse( $decrypted, 'Decryption should return false for empty data' );
	}

	/**
	 * Test decrypting invalid data
	 */
	public function test_decrypt_invalid_data() {
		$invalid_data = 'not-a-valid-encrypted-string!@#$%';
		$decrypted = $this->encryption->decrypt( $invalid_data );
		$this->assertFalse( $decrypted, 'Decryption should return false for invalid encrypted data' );
	}

	/**
	 * Test saving API key
	 */
	public function test_save_api_key() {
		$api_key = 'sk-ant-api03-test-key-123456789';
		$result = $this->encryption->save_api_key( $api_key );

		$this->assertTrue( $result, 'Saving API key should return true' );

		// Verify it's stored encrypted
		$stored = get_option( '84em_gf_ai_encrypted_api_key' );
		$this->assertNotEmpty( $stored, 'Encrypted API key should be stored in options' );
		$this->assertNotEquals( $api_key, $stored, 'Stored key should be encrypted' );
	}

	/**
	 * Test saving empty API key
	 */
	public function test_save_empty_api_key() {
		$result = $this->encryption->save_api_key( '' );
		$this->assertFalse( $result, 'Saving empty API key should return false' );
	}

	/**
	 * Test getting saved API key
	 */
	public function test_get_api_key() {
		$api_key = 'sk-ant-api03-test-key-123456789';
		$this->encryption->save_api_key( $api_key );

		$retrieved = $this->encryption->get_api_key();
		$this->assertEquals( $api_key, $retrieved, 'Retrieved API key should match original' );
	}

	/**
	 * Test getting non-existent API key
	 */
	public function test_get_nonexistent_api_key() {
		delete_option( '84em_gf_ai_encrypted_api_key' );
		$retrieved = $this->encryption->get_api_key();
		$this->assertFalse( $retrieved, 'Getting non-existent API key should return false' );
	}

	/**
	 * Test checking if API key exists
	 */
	public function test_has_api_key() {
		// Test without key
		delete_option( '84em_gf_ai_encrypted_api_key' );
		$this->assertFalse( $this->encryption->has_api_key(), 'Should return false when no key exists' );

		// Test with key
		$this->encryption->save_api_key( 'sk-ant-api03-test-key-123456789' );
		$this->assertTrue( $this->encryption->has_api_key(), 'Should return true when key exists' );
	}

	/**
	 * Test deleting API key
	 */
	public function test_delete_api_key() {
		// Save a key first
		$this->encryption->save_api_key( 'sk-ant-api03-test-key-123456789' );
		$this->assertTrue( $this->encryption->has_api_key(), 'Key should exist before deletion' );

		// Delete it
		$result = $this->encryption->delete_api_key();
		$this->assertTrue( $result, 'Deletion should return true' );
		$this->assertFalse( $this->encryption->has_api_key(), 'Key should not exist after deletion' );
	}

	/**
	 * Test encryption consistency with various strings
	 */
	public function test_encryption_consistency() {
		$test_strings = [
			'simple',
			'with spaces in it',
			'with-special-chars!@#$%^&*()',
			'with_underscores_and_numbers_123',
			'{"json":"data","nested":{"key":"value"}}',
			'multiline\nstring\nwith\nbreaks',
			'very_long_string_' . str_repeat( 'a', 1000 ),
		];

		foreach ( $test_strings as $original ) {
			$encrypted = $this->encryption->encrypt( $original );
			$this->assertNotFalse( $encrypted, "Encryption failed for: $original" );

			$decrypted = $this->encryption->decrypt( $encrypted );
			$this->assertEquals( $original, $decrypted, "Encryption/decryption cycle failed for: $original" );
		}
	}

	/**
	 * Test encryption with special characters in API key
	 */
	public function test_encrypt_special_characters() {
		$special_keys = [
			'sk-ant-api03-aBc123XyZ',
			'key-with-dashes-and-numbers-123',
			'key_with_underscores_456',
			'key.with.dots.789',
		];

		foreach ( $special_keys as $key ) {
			$result = $this->encryption->save_api_key( $key );
			$this->assertTrue( $result, "Failed to save key: $key" );

			$retrieved = $this->encryption->get_api_key();
			$this->assertEquals( $key, $retrieved, "Failed to retrieve key: $key" );
		}
	}

	/**
	 * Test multiple encrypt/decrypt cycles
	 */
	public function test_multiple_encryption_cycles() {
		$original = 'test-data-for-multiple-cycles';

		// First cycle
		$encrypted1 = $this->encryption->encrypt( $original );
		$decrypted1 = $this->encryption->decrypt( $encrypted1 );
		$this->assertEquals( $original, $decrypted1, 'First cycle failed' );

		// Second cycle with same data
		$encrypted2 = $this->encryption->encrypt( $original );
		$decrypted2 = $this->encryption->decrypt( $encrypted2 );
		$this->assertEquals( $original, $decrypted2, 'Second cycle failed' );

		// Encrypted results might differ due to padding but both should decrypt correctly
		$this->assertEquals( $decrypted1, $decrypted2, 'Both cycles should produce same decrypted result' );
	}

	/**
	 * Test that encrypted data is base64 encoded
	 */
	public function test_encrypted_format() {
		$plaintext = 'test-data';
		$encrypted = $this->encryption->encrypt( $plaintext );

		// Check if it's valid base64
		$decoded = base64_decode( $encrypted, true );
		$this->assertNotFalse( $decoded, 'Encrypted data should be valid base64' );

		// Re-encoding should give same result
		$reencoded = base64_encode( $decoded );
		$this->assertEquals( $encrypted, $reencoded, 'Base64 encoding should be consistent' );
	}

	/**
	 * Test API key update
	 */
	public function test_update_api_key() {
		$old_key = 'sk-ant-old-key-123';
		$new_key = 'sk-ant-new-key-456';

		// Save old key
		$this->encryption->save_api_key( $old_key );
		$this->assertEquals( $old_key, $this->encryption->get_api_key(), 'Old key should be saved' );

		// Update to new key
		$this->encryption->save_api_key( $new_key );
		$this->assertEquals( $new_key, $this->encryption->get_api_key(), 'New key should replace old key' );
	}

	/**
	 * Test encryption with null input
	 */
	public function test_encrypt_null_input() {
		$encrypted = $this->encryption->encrypt( null );
		$this->assertFalse( $encrypted, 'Encrypting null should return false' );
	}

	/**
	 * Test decryption with null input
	 */
	public function test_decrypt_null_input() {
		$decrypted = $this->encryption->decrypt( null );
		$this->assertFalse( $decrypted, 'Decrypting null should return false' );
	}

	/**
	 * Test saving null API key
	 */
	public function test_save_null_api_key() {
		$result = $this->encryption->save_api_key( null );
		$this->assertFalse( $result, 'Saving null API key should return false' );
	}

	/**
	 * Test encryption error handling with debug mode
	 */
	public function test_encryption_error_logging() {
		// Enable debug mode
		if ( ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', true );
		}

		// Try to decrypt invalid data
		$this->encryption->decrypt( 'invalid-encrypted-data' );

		// In a real test environment, we would check error logs
		// For this test, we just ensure it doesn't throw an exception
		$this->assertTrue( true, 'Error handling should not throw exceptions' );
	}

	/**
	 * Test that option name is correct
	 */
	public function test_option_name() {
		$api_key = 'test-key';
		$this->encryption->save_api_key( $api_key );

		// Check that the option exists with the correct name
		$option_value = get_option( '84em_gf_ai_encrypted_api_key' );
		$this->assertNotFalse( $option_value, 'Option should exist with correct name' );
		$this->assertNotEmpty( $option_value, 'Option should have a value' );
	}
}