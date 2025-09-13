<?php
/**
 * Claude API Handler
 *
 * @package EightyFourEM\GravityFormsAI
 */

namespace EightyFourEM\GravityFormsAI\Core;

/**
 * APIHandler class
 */
class APIHandler {

    /**
     * API endpoint
     *
     * @var string
     */
    private $api_url = 'https://api.anthropic.com/v1/messages';

    /**
     * API version
     *
     * @var string
     */
    private $api_version = '2023-06-01';

    /**
     * Last request time
     *
     * @var int
     */
    private static $last_request_time = 0;

    /**
     * Make API request
     *
     * @param  string  $prompt  The prompt to send
     * @param  array  $data  Additional data for context
     *
     * @return array Response array with 'success' and 'data' or 'error'
     */
    public function analyze( $prompt, $data = [] ) {
        // Check if enabled
        if ( ! get_option( '84em_gf_ai_enabled' ) ) {
            return [
                'success' => false,
                'error'   => __( 'AI analysis is disabled.', '84em-gf-ai' ),
            ];
        }

        // Get API key
        $encryption = new Encryption();
        $api_key    = $encryption->get_api_key();

        if ( ! $api_key ) {
            return [
                'success' => false,
                'error'   => __( 'API key not configured.', '84em-gf-ai' ),
            ];
        }

        // Apply rate limiting
        $this->apply_rate_limit();

        // Prepare the message
        $message = $this->prepare_message( $prompt, $data );

        // Prepare request body
        $body = [
            'model'       => get_option( '84em_gf_ai_model', 'claude-3-5-haiku-20241022' ),
            'max_tokens'  => intval( get_option( '84em_gf_ai_max_tokens', 1000 ) ),
            'temperature' => floatval( get_option( '84em_gf_ai_temperature', 0.7 ) ),
            'messages'    => [
                [
                    'role'    => 'user',
                    'content' => $message,
                ],
            ],
            'system'      => 'You are an AI assistant analyzing form submissions for a business. Provide insights about the submitter, their company, and potential business opportunities. Search for publicly available information when possible. Format your response in clear sections with headers.',
        ];

        // Make the request
        $response = wp_remote_post( $this->api_url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $api_key,
                'anthropic-version' => $this->api_version,
            ],
            'body'    => json_encode( $body ),
        ] );

        // Log the request if enabled
        if ( get_option( '84em_gf_ai_enable_logging' ) ) {
            $this->log_request( $body, $response, $data );

            // Purge old logs based on retention setting
            $this->purge_old_logs();
        }

        // Handle errors
        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $response_data = json_decode( $response_body, true );

        if ( $response_code !== 200 ) {
            $error_message = isset( $response_data['error']['message'] )
                ? $response_data['error']['message']
                : __( 'API request failed.', '84em-gf-ai' );

            return [
                'success' => false,
                'error'   => $error_message,
                'code'    => $response_code,
            ];
        }

        // Extract the content
        if ( isset( $response_data['content'][0]['text'] ) ) {
            return [
                'success' => true,
                'data'    => $response_data['content'][0]['text'],
                'usage'   => isset( $response_data['usage'] ) ? $response_data['usage'] : null,
            ];
        }

        return [
            'success' => false,
            'error'   => __( 'Invalid API response format.', '84em-gf-ai' ),
        ];
    }

    /**
     * Test API connection
     *
     * @return array
     */
    public function test_connection() {
        $test_prompt = 'Hello, this is a test message. Please respond with "Connection successful" if you receive this.';

        $result = $this->analyze( $test_prompt, [
            'test'     => true,
            'form_id'  => 0,
            'entry_id' => 0,
        ] );

        return $result;
    }

    /**
     * Prepare message from prompt and data
     *
     * @param  string  $prompt  Base prompt
     * @param  array  $data  Form data
     *
     * @return string
     */
    private function prepare_message( $prompt, $data ) {
        // Replace variables in prompt
        $replacements = [
            '{form_data}'         => isset( $data['form_data'] ) ? $this->format_form_data( $data['form_data'] ) : '',
            '{form_title}'        => isset( $data['form_title'] ) ? $data['form_title'] : '',
            '{submitter_name}'    => isset( $data['submitter_name'] ) ? $data['submitter_name'] : '',
            '{submitter_email}'   => isset( $data['submitter_email'] ) ? $data['submitter_email'] : '',
            '{submitter_company}' => isset( $data['submitter_company'] ) ? $data['submitter_company'] : '',
            '{submission_date}'   => isset( $data['submission_date'] ) ? $data['submission_date'] : current_time( 'mysql' ),
        ];

        $message = str_replace(
            array_keys( $replacements ),
            array_values( $replacements ),
            $prompt
        );

        // Add instruction for web search if we have identifying information
        if ( ! empty( $data['submitter_name'] ) || ! empty( $data['submitter_company'] ) ) {
            $message .= "\n\nPlease search for publicly available information about ";

            if ( ! empty( $data['submitter_name'] ) && ! empty( $data['submitter_company'] ) ) {
                $message .= $data['submitter_name'] . " from " . $data['submitter_company'];
            }
            elseif ( ! empty( $data['submitter_name'] ) ) {
                $message .= $data['submitter_name'];
            }
            else {
                $message .= $data['submitter_company'];
            }

            $message .= " and include relevant findings in your analysis.";
        }

        return $message;
    }

    /**
     * Format form data for prompt
     *
     * @param  array  $form_data  Form field data
     *
     * @return string
     */
    private function format_form_data( $form_data ) {
        if ( empty( $form_data ) || ! is_array( $form_data ) ) {
            return '';
        }

        $formatted = "Form Submission Data:\n";
        foreach ( $form_data as $field_label => $field_value ) {
            if ( is_array( $field_value ) ) {
                $field_value = implode( ', ', $field_value );
            }
            $formatted .= "- " . $field_label . ": " . $field_value . "\n";
        }

        return $formatted;
    }

    /**
     * Apply rate limiting
     */
    private function apply_rate_limit() {
        $rate_limit = intval( get_option( '84em_gf_ai_rate_limit', 2 ) );

        if ( $rate_limit > 0 && self::$last_request_time > 0 ) {
            $time_since_last = time() - self::$last_request_time;
            if ( $time_since_last < $rate_limit ) {
                sleep( $rate_limit - $time_since_last );
            }
        }

        self::$last_request_time = time();
    }

    /**
     * Log API request and response
     *
     * @param  array  $request  Request data
     * @param  mixed  $response  Response data
     * @param  array  $context  Context data
     */
    private function log_request( $request, $response, $context ) {
        global $wpdb;
        $table_name = $wpdb->prefix . '84em_gf_ai_logs';

        // Ensure table exists
        $this->ensure_log_table();

        $status        = 'success';
        $error_message = null;
        $response_text = '';

        if ( is_wp_error( $response ) ) {
            $status        = 'error';
            $error_message = $response->get_error_message();
        }
        else {
            $response_code = wp_remote_retrieve_response_code( $response );
            if ( $response_code !== 200 ) {
                $status        = 'error';
                $response_body = wp_remote_retrieve_body( $response );
                $response_data = json_decode( $response_body, true );
                $error_message = isset( $response_data['error']['message'] )
                    ? $response_data['error']['message']
                    : 'HTTP ' . $response_code;
            }
            else {
                $response_body = wp_remote_retrieve_body( $response );
                $response_text = $response_body;
            }
        }

        // Remove sensitive data from request before logging
        $request_to_log = $request;
        unset( $request_to_log['api_key'] );

        $wpdb->insert(
            $table_name,
            [
                'form_id'       => isset( $context['form_id'] ) ? intval( $context['form_id'] ) : 0,
                'entry_id'      => isset( $context['entry_id'] ) ? intval( $context['entry_id'] ) : 0,
                'status'        => $status,
                'request'       => json_encode( $request_to_log ),
                'response'      => $response_text,
                'error_message' => $error_message,
                'created_at'    => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    /**
     * Ensure log table exists
     */
    private function ensure_log_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . '84em_gf_ai_logs';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                form_id mediumint(9) NOT NULL,
                entry_id mediumint(9) NOT NULL,
                status varchar(20) NOT NULL,
                request text,
                response text,
                error_message text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY form_id (form_id),
                KEY entry_id (entry_id),
                KEY created_at (created_at)
            ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        }
    }

    /**
     * Purge old logs based on retention setting
     */
    private function purge_old_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . '84em_gf_ai_logs';

        // Get retention period in days
        $retention_days = intval( get_option( '84em_gf_ai_log_retention', 30 ) );

        // Calculate the cutoff date
        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( '-' . $retention_days . ' days' ) );

        // Delete old logs
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < %s",
            $cutoff_date
        ) );
    }
}
