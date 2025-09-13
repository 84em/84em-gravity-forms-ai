<?php
/**
 * Encryption handler for API keys
 *
 * @package EightyFourEM\GravityFormsAI
 */

namespace EightyFourEM\GravityFormsAI\Core;

/**
 * Encryption class
 */
class Encryption {

    /**
     * Encryption method
     *
     * @var string
     */
    private $method = 'AES-256-CBC';

    /**
     * Option name for storing encrypted API key
     *
     * @var string
     */
    private $option_name = '84em_gf_ai_encrypted_api_key';

    /**
     * Get encryption key
     *
     * @return string
     * @throws \Exception If WordPress security keys are not configured
     */
    private function get_encryption_key() {
        if ( ! defined( 'AUTH_KEY' ) || empty( AUTH_KEY ) || AUTH_KEY === 'put your unique phrase here' ) {
            throw new \Exception( 'WordPress AUTH_KEY is not properly configured. Please update your wp-config.php file with unique security keys.' );
        }
        return substr( hash( 'sha256', AUTH_KEY ), 0, 32 );
    }

    /**
     * Get initialization vector
     *
     * @return string
     * @throws \Exception If WordPress security keys are not configured
     */
    private function get_iv() {
        if ( ! defined( 'AUTH_SALT' ) || empty( AUTH_SALT ) || AUTH_SALT === 'put your unique phrase here' ) {
            throw new \Exception( 'WordPress AUTH_SALT is not properly configured. Please update your wp-config.php file with unique security keys.' );
        }
        return substr( hash( 'sha256', AUTH_SALT ), 0, 16 );
    }

    /**
     * Encrypt data
     *
     * @param  string  $data  Data to encrypt
     *
     * @return string|false Encrypted data or false on failure
     */
    public function encrypt( $data ) {
        if ( empty( $data ) ) {
            return false;
        }

        try {
            $encrypted = openssl_encrypt(
                $data,
                $this->method,
                $this->get_encryption_key(),
                0,
                $this->get_iv()
            );

            return $encrypted;
        } catch ( \Exception $e ) {
            // Log the error for administrators
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '84EM GF AI Encryption Error: ' . $e->getMessage() );
            }
            return false;
        }
    }

    /**
     * Decrypt data
     *
     * @param  string  $encrypted_data  Encrypted data
     *
     * @return string|false Decrypted data or false on failure
     */
    public function decrypt( $encrypted_data ) {
        if ( empty( $encrypted_data ) ) {
            return false;
        }

        try {
            $decrypted = openssl_decrypt(
                $encrypted_data,
                $this->method,
                $this->get_encryption_key(),
                0,
                $this->get_iv()
            );

            return $decrypted;
        } catch ( \Exception $e ) {
            // Log the error for administrators
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '84EM GF AI Decryption Error: ' . $e->getMessage() );
            }
            return false;
        }
    }

    /**
     * Save API key
     *
     * @param  string  $api_key  API key to save
     *
     * @return bool Success status
     */
    public function save_api_key( $api_key ) {
        if ( empty( $api_key ) ) {
            return false;
        }

        $encrypted = $this->encrypt( $api_key );
        if ( ! $encrypted ) {
            return false;
        }

        // Store the encrypted API key in WordPress options
        return update_option( $this->option_name, $encrypted );
    }

    /**
     * Get API key
     *
     * @return string|false Decrypted API key or false if not found
     */
    public function get_api_key() {
        $encrypted = get_option( $this->option_name );

        if ( ! $encrypted ) {
            return false;
        }

        return $this->decrypt( $encrypted );
    }

    /**
     * Check if API key exists
     *
     * @return bool
     */
    public function has_api_key() {
        $encrypted = get_option( $this->option_name );
        return ! empty( $encrypted );
    }

    /**
     * Delete API key
     *
     * @return bool Success status
     */
    public function delete_api_key() {
        return delete_option( $this->option_name );
    }
}