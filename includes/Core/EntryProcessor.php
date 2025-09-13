<?php
/**
 * Entry Processor - Handles form submissions and AI analysis
 *
 * @package EightyFourEM\GravityFormsAI
 */

namespace EightyFourEM\GravityFormsAI\Core;

/**
 * EntryProcessor class
 */
class EntryProcessor {

    /**
     * Constructor
     */
    public function __construct() {
        // Add AI analysis to entry detail page
        add_action( 'gform_entry_detail_sidebar_middle', [ $this, 'display_ai_analysis' ], 10, 2 );

        // AJAX handler for manual analysis
        add_action( 'wp_ajax_84em_gf_ai_analyze_entry', [ $this, 'ajax_analyze_entry' ] );

        // AJAX handler for HTML download
        add_action( 'wp_ajax_84em_gf_ai_download_html', [ $this, 'ajax_download_html' ] );
    }

    /**
     * Process entry after form submission
     *
     * @param  array  $entry  The entry object
     * @param  array  $form  The form object
     */
    public function process_entry( $entry, $form ) {
        // Check if AI analysis is enabled globally
        if ( ! get_option( '84em_gf_ai_enabled' ) ) {
            \GFAPI::add_note( $entry['id'], 0, __( 'AI Analysis', '84em-gf-ai' ), __( 'AI Analysis skipped: Global setting disabled', '84em-gf-ai' ) );
            return [ 'success' => false, 'error' => 'Global AI analysis is disabled' ];
        }

        // Check if AI analysis is enabled for this form
        // First check form-specific setting, then fall back to global setting
        $form_enabled = get_option( '84em_gf_ai_enabled_' . $form['id'], null );
        if ( $form_enabled === null ) {
            // No form-specific setting, use global setting
            $form_enabled = get_option( '84em_gf_ai_enabled' );
        }

        if ( ! $form_enabled ) {
            return [ 'success' => false, 'error' => 'AI analysis is disabled for this form' ];
        }

        // Get field mapping for this form
        $field_mapping = get_option( '84em_gf_ai_mapping_' . $form['id'], [] );

        // If no mapping exists, auto-include all relevant fields
        if ( empty( $field_mapping ) ) {
            $field_mapping = $this->get_all_analyzable_field_ids( $form );
        }

        // Collect form data based on mapping
        $form_data = $this->collect_form_data( $entry, $form, $field_mapping );

        // Prepare context data
        $context = [
            'form_id'           => $form['id'],
            'entry_id'          => $entry['id'],
            'form_title'        => $form['title'],
            'form_data'         => $form_data,
            'submitter_name'    => $this->extract_name( $entry, $form ),
            'submitter_email'   => $this->extract_email( $entry, $form ),
            'submitter_company' => $this->extract_company( $entry, $form ),
            'submission_date'   => $entry['date_created'],
        ];

        // Get custom prompt or use default
        $custom_prompt = get_option( '84em_gf_ai_prompt_' . $form['id'], '' );
        $prompt        = ! empty( $custom_prompt ) ? $custom_prompt : get_option( '84em_gf_ai_default_prompt' );

        // Allow filtering of the prompt before sending to API
        $prompt = apply_filters( '84em_gf_ai_analysis_prompt', $prompt, $context );

        // Perform AI analysis
        $api_handler = new APIHandler();
        $result      = $api_handler->analyze( $prompt, $context );

        if ( $result['success'] ) {
            // Allow filtering of the AI response before saving
            $analysis_result = apply_filters( '84em_gf_ai_analysis_result', $result['data'], $entry['id'], $form['id'] );

            // Save AI analysis as entry meta (raw markdown)
            gform_update_meta( $entry['id'], '84em_ai_analysis', $analysis_result );
            gform_update_meta( $entry['id'], '84em_ai_analysis_date', current_time( 'mysql' ) );

            // Fire action after successful analysis
            do_action( '84em_gf_ai_after_analysis', $entry['id'], $analysis_result, $form['id'] );

            return [ 'success' => true, 'data' => $analysis_result ];
        }
        else {
            // Log error in meta as well for consistency
            gform_update_meta( $entry['id'], '84em_ai_analysis_error', $result['error'] );
            gform_update_meta( $entry['id'], '84em_ai_analysis_error_date', current_time( 'mysql' ) );

            // Fire action when analysis fails
            do_action( '84em_gf_ai_analysis_failed', $entry['id'], $result['error'], $form['id'] );

            return [ 'success' => false, 'error' => $result['error'] ];
        }
    }

    /**
     * Get all analyzable field IDs from a form
     *
     * @param  array  $form  The form object
     *
     * @return array Array of field IDs
     */
    private function get_all_analyzable_field_ids( $form ) {
        $field_ids = [];

        // System fields to exclude from analysis
        $excluded_types = [ 'html', 'section', 'page', 'captcha', 'honeypot', 'hidden' ];

        foreach ( $form['fields'] as $field ) {
            // Skip system fields and admin-only fields
            if ( in_array( $field->type, $excluded_types ) || $field->adminOnly ) {
                continue;
            }

            $field_ids[] = $field->id;
        }

        return $field_ids;
    }

    /**
     * Collect form data based on field mapping
     *
     * @param  array  $entry  The entry object
     * @param  array  $form  The form object
     * @param  array  $field_mapping  Field IDs to include
     *
     * @return array
     */
    private function collect_form_data( $entry, $form, $field_mapping ) {
        $data = [];

        foreach ( $form['fields'] as $field ) {
            if ( ! in_array( $field->id, $field_mapping ) ) {
                continue;
            }

            $field_value = $this->get_field_value( $entry, $field );
            if ( ! empty( $field_value ) ) {
                $field_label          = ! empty( $field->label ) ? $field->label : 'Field ' . $field->id;
                $data[ $field_label ] = $field_value;
            }
        }

        return $data;
    }

    /**
     * Get field value from entry
     *
     * @param  array  $entry  The entry object
     * @param  object  $field  The field object
     *
     * @return mixed
     */
    private function get_field_value( $entry, $field ) {
        $value = '';

        switch ( $field->type ) {
            case 'name':
                $value = trim(
                    rgar( $entry, $field->id . '.3' ) . ' ' .
                    rgar( $entry, $field->id . '.6' )
                );
                break;

            case 'address':
                $parts = [];
                for ( $i = 1; $i <= 6; $i ++ ) {
                    $part = rgar( $entry, $field->id . '.' . $i );
                    if ( ! empty( $part ) ) {
                        $parts[] = $part;
                    }
                }
                $value = implode( ', ', $parts );
                break;

            case 'checkbox':
                $choices = [];
                foreach ( $field->choices as $i => $choice ) {
                    $input_id = $field->id . '.' . ( $i + 1 );
                    if ( ! empty( $entry[ $input_id ] ) ) {
                        $choices[] = $choice['text'];
                    }
                }
                $value = implode( ', ', $choices );
                break;

            case 'list':
                $value = maybe_unserialize( rgar( $entry, $field->id ) );
                if ( is_array( $value ) ) {
                    $formatted = [];
                    foreach ( $value as $row ) {
                        if ( is_array( $row ) ) {
                            $formatted[] = implode( ' | ', $row );
                        }
                        else {
                            $formatted[] = $row;
                        }
                    }
                    $value = implode( '; ', $formatted );
                }
                break;

            case 'fileupload':
                $value = rgar( $entry, $field->id );
                if ( ! empty( $value ) ) {
                    $value = 'File: ' . basename( $value );
                }
                break;

            default:
                $value = rgar( $entry, $field->id );
                break;
        }

        return $value;
    }

    /**
     * Extract name from entry
     *
     * @param  array  $entry  The entry object
     * @param  array  $form  The form object
     *
     * @return string
     */
    private function extract_name( $entry, $form ) {
        foreach ( $form['fields'] as $field ) {
            if ( $field->type === 'name' ) {
                $first = rgar( $entry, $field->id . '.3' );
                $last  = rgar( $entry, $field->id . '.6' );
                return trim( $first . ' ' . $last );
            }
        }

        // Try to find a field with 'name' in the label
        foreach ( $form['fields'] as $field ) {
            if ( stripos( $field->label, 'name' ) !== false && $field->type === 'text' ) {
                return rgar( $entry, $field->id );
            }
        }

        return '';
    }

    /**
     * Extract email from entry
     *
     * @param  array  $entry  The entry object
     * @param  array  $form  The form object
     *
     * @return string
     */
    private function extract_email( $entry, $form ) {
        foreach ( $form['fields'] as $field ) {
            if ( $field->type === 'email' ) {
                return rgar( $entry, $field->id );
            }
        }
        return '';
    }

    /**
     * Extract company from entry
     *
     * @param  array  $entry  The entry object
     * @param  array  $form  The form object
     *
     * @return string
     */
    private function extract_company( $entry, $form ) {
        // Look for fields with 'company' or 'organization' in the label
        foreach ( $form['fields'] as $field ) {
            $label_lower = strtolower( $field->label );
            if ( strpos( $label_lower, 'company' ) !== false
                 || strpos( $label_lower, 'organization' ) !== false
                 || strpos( $label_lower, 'business' ) !== false ) {
                return rgar( $entry, $field->id );
            }
        }
        return '';
    }



    /**
     * Display AI analysis on entry detail page
     *
     * @param  array  $form  The form object
     * @param  array  $entry  The entry object
     */
    public function display_ai_analysis( $form, $entry ) {
        $analysis      = gform_get_meta( $entry['id'], '84em_ai_analysis' );
        $analysis_date = gform_get_meta( $entry['id'], '84em_ai_analysis_date' );

        ?>
        <div class="postbox">
            <h3 class="hndle">
                <span><?php esc_html_e( 'AI Analysis', '84em-gf-ai' ); ?></span>
            </h3>
            <div class="inside">
                <?php if ( $analysis ) : ?>
                    <p style="margin-bottom: 10px;">
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php esc_html_e( 'AI analysis completed', '84em-gf-ai' ); ?>
                    </p>
                    <?php if ( $analysis_date ) : ?>
                        <p class="analysis-date" style="margin-bottom: 15px;">
                            <small>
                                <?php
                                printf(
                                    esc_html__( 'Analyzed: %s', '84em-gf-ai' ),
                                    esc_html( wp_date( 'F j, Y g:i a', strtotime( $analysis_date ) ) )
                                );
                                ?>
                            </small>
                        </p>
                    <?php endif; ?>
                    <p style="color: #666; font-style: italic; margin-bottom: 15px;">
                        <?php esc_html_e( 'Analysis completed. Click "View Report" to see the full formatted analysis in a new tab.', '84em-gf-ai' ); ?>
                    </p>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" class="button reanalyze-entry" data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>" data-form-id="<?php echo esc_attr( $form['id'] ); ?>">
                            <?php esc_html_e( 'Re-analyze', '84em-gf-ai' ); ?>
                        </button>
                        <button type="button" class="button save-as-html" data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>" data-form-id="<?php echo esc_attr( $form['id'] ); ?>">
                            <span class="dashicons dashicons-visibility" style="vertical-align: middle; margin-right: 3px;"></span>
                            <?php esc_html_e( 'View Report', '84em-gf-ai' ); ?>
                        </button>
                    </div>
                <?php else : ?>
                    <p><?php esc_html_e( 'No AI analysis available.', '84em-gf-ai' ); ?></p>
                    <button type="button" class="button analyze-entry" data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>" data-form-id="<?php echo esc_attr( $form['id'] ); ?>">
                        <?php esc_html_e( 'Analyze Now', '84em-gf-ai' ); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>


        <script>
            jQuery( document ).ready( function ( $ ) {
                $( '.analyze-entry, .reanalyze-entry' ).on( 'click', function ( e ) {
                    e.preventDefault();

                    var button = $( this );
                    var entryId = button.data( 'entry-id' );
                    var formId = button.data( 'form-id' );
                    var originalText = button.text();

                    button.prop( 'disabled', true ).text( '<?php echo esc_js( __( 'Analyzing...', '84em-gf-ai' ) ); ?>' );

                    $.post( ajaxurl, {
                        action   : '84em_gf_ai_analyze_entry',
                        entry_id : entryId,
                        form_id  : formId,
                        nonce    : '<?php echo wp_create_nonce( '84em_gf_ai_analyze' ); ?>'
                    }, function ( response ) {
                        if ( response.success ) {
                            location.reload();
                        }
                        else {
                            alert( response.data.message || '<?php echo esc_js( __( 'Analysis failed', '84em-gf-ai' ) ); ?>' );
                            button.prop( 'disabled', false ).text( originalText );
                        }
                    } ).fail( function ( jqXHR, textStatus, errorThrown ) {
                        alert( '<?php echo esc_js( __( 'Request failed. Please try again.', '84em-gf-ai' ) ); ?>' );
                        button.prop( 'disabled', false ).text( originalText );
                    } );
                } );

                $( '.save-as-html' ).on( 'click', function ( e ) {
                    e.preventDefault();

                    var button = $( this );
                    var entryId = button.data( 'entry-id' );
                    var formId = button.data( 'form-id' );
                    var originalHtml = button.html();

                    button.prop( 'disabled', true ).html( '<span class="spinner is-active" style="float: none; margin: 0;"></span> <?php echo esc_js( __( 'Generating HTML...', '84em-gf-ai' ) ); ?>' );

                    $.ajax( {
                        url     : ajaxurl,
                        type    : 'POST',
                        data    : {
                            action   : '84em_gf_ai_download_html',
                            entry_id : entryId,
                            form_id  : formId,
                            nonce    : '<?php echo wp_create_nonce( '84em_gf_ai_html' ); ?>'
                        },
                        success : function ( response ) {
                            if ( response.success && response.data.html ) {
                                // Open in new tab instead of downloading
                                var blob = new Blob( [response.data.html], {type : 'text/html'} );
                                var url = window.URL.createObjectURL( blob );
                                window.open( url, '_blank' );

                                // Clean up blob URL after a delay
                                setTimeout( function() {
                                    window.URL.revokeObjectURL( url );
                                }, 1000 );

                                button.prop( 'disabled', false ).html( originalHtml );
                            }
                            else {
                                alert( response.data.message || '<?php echo esc_js( __( 'Failed to generate HTML', '84em-gf-ai' ) ); ?>' );
                                button.prop( 'disabled', false ).html( originalHtml );
                            }
                        },
                        error   : function () {
                            alert( '<?php echo esc_js( __( 'Request failed. Please try again.', '84em-gf-ai' ) ); ?>' );
                            button.prop( 'disabled', false ).html( originalHtml );
                        }
                    } );
                } );
            } );
        </script>
        <?php
    }

    /**
     * AJAX handler for manual entry analysis
     */
    public function ajax_analyze_entry() {
        check_ajax_referer( '84em_gf_ai_analyze', 'nonce' );

        if ( ! current_user_can( 'gravityforms_view_entries' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [
                'message' => __( 'Insufficient permissions', '84em-gf-ai' ),
            ] );
        }

        $entry_id = intval( $_POST['entry_id'] );
        $form_id  = intval( $_POST['form_id'] );

        $entry = \GFAPI::get_entry( $entry_id );
        $form  = \GFAPI::get_form( $form_id );

        if ( is_wp_error( $entry ) || is_wp_error( $form ) ) {
            wp_send_json_error( [
                'message' => __( 'Invalid entry or form', '84em-gf-ai' ),
            ] );
        }

        // Process the entry
        $result = $this->process_entry( $entry, $form );

        if ( $result && $result['success'] ) {
            wp_send_json_success( [
                'message'  => __( 'Analysis completed successfully', '84em-gf-ai' ),
                'analysis' => isset( $result['data'] ) ? $result['data'] : '',
            ] );
        }
        else {
            $error_message = isset( $result['error'] ) ? $result['error'] : __( 'Analysis failed', '84em-gf-ai' );
            wp_send_json_error( [
                'message' => $error_message,
            ] );
        }
    }

    /**
     * AJAX handler for HTML download
     */
    public function ajax_download_html() {
        // Check nonce
        if ( ! check_ajax_referer( '84em_gf_ai_html', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed', '84em-gf-ai' ) ] );
        }

        // Get entry ID
        $entry_id = isset( $_POST['entry_id'] ) ? intval( $_POST['entry_id'] ) : 0;
        if ( ! $entry_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid entry ID', '84em-gf-ai' ) ] );
        }

        // Get entry and form
        $entry = \GFAPI::get_entry( $entry_id );
        if ( is_wp_error( $entry ) ) {
            wp_send_json_error( [ 'message' => __( 'Entry not found', '84em-gf-ai' ) ] );
        }

        $form = \GFAPI::get_form( $entry['form_id'] );
        if ( ! $form ) {
            wp_send_json_error( [ 'message' => __( 'Form not found', '84em-gf-ai' ) ] );
        }

        // Get the analysis (raw markdown)
        $analysis      = gform_get_meta( $entry_id, '84em_ai_analysis' );
        $analysis_date = gform_get_meta( $entry_id, '84em_ai_analysis_date' );

        if ( ! $analysis ) {
            wp_send_json_error( [ 'message' => __( 'No analysis found for this entry', '84em-gf-ai' ) ] );
        }

        // Get submitter info from the entry
        $submitter_name  = '';
        $submitter_email = '';
        $company         = '';

        // Try to find name, email, and company fields
        foreach ( $form['fields'] as $field ) {
            if ( $field->type == 'name' && empty( $submitter_name ) ) {
                $submitter_name = trim( \GFCommon::get_lead_field_display( $field, $entry[ $field->id ], $entry['currency'] ) );
            }
            elseif ( $field->type == 'email' && empty( $submitter_email ) ) {
                $submitter_email = trim( $entry[ $field->id ] );
            }
            elseif ( ( stripos( $field->label, 'company' ) !== false || stripos( $field->label, 'organization' ) !== false ) && empty( $company ) ) {
                $company = trim( $entry[ $field->id ] );
            }
        }

        // Pass raw markdown to JavaScript for formatting
        // The JavaScript will convert it using Marked.js
        $markdown_json = wp_json_encode( $analysis );

        // Generate HTML for viewing with embedded Marked.js
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . esc_html( sprintf( __( 'AI Analysis Report - Entry #%d | 84EM', '84em-gf-ai' ), $entry_id ) ) . '</title>
    <script src="https://cdn.jsdelivr.net/npm/marked@12.0.0/marked.min.js"></script>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none !important; }
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .header {
            border-bottom: 2px solid #e1e1e1;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
            margin-bottom: 10px;
        }
        .title {
            font-size: 28px;
            font-weight: 600;
            color: #23282d;
            margin: 20px 0 10px 0;
        }
        .meta {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .meta-item {
            margin-right: 20px;
            display: inline-block;
        }
        .content {
            margin-top: 30px;
        }
        .content h1 { font-size: 24px; margin-top: 30px; margin-bottom: 15px; color: #23282d; }
        .content h2 { font-size: 20px; margin-top: 25px; margin-bottom: 12px; color: #23282d; }
        .content h3 { font-size: 18px; margin-top: 20px; margin-bottom: 10px; color: #23282d; }
        .content h4 { font-size: 16px; margin-top: 18px; margin-bottom: 8px; color: #23282d; }
        .content p { margin: 10px 0; }
        .content ul, .content ol { margin: 10px 0; padding-left: 30px; }
        .content li { margin: 5px 0; }
        .content strong { font-weight: 600; }
        .content em { font-style: italic; }
        .content code {
            background: #f1f1f1;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: Consolas, Monaco, monospace;
            font-size: 0.9em;
        }
        .content blockquote {
            border-left: 4px solid #ddd;
            margin: 15px 0;
            padding: 10px 20px;
            color: #666;
            background: #f9f9f9;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #e1e1e1;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">84EM AI Analysis</div>
        <h1 class="title">' . esc_html( sprintf( __( 'Form Submission Analysis', '84em-gf-ai' ) ) ) . '</h1>
        <div class="meta">
            <span class="meta-item"><strong>' . esc_html__( 'Entry ID:', '84em-gf-ai' ) . '</strong> #' . esc_html( $entry_id ) . '</span>
            <span class="meta-item"><strong>' . esc_html__( 'Form:', '84em-gf-ai' ) . '</strong> ' . esc_html( $form['title'] ) . '</span>
            <span class="meta-item"><strong>' . esc_html__( 'Analysis Date:', '84em-gf-ai' ) . '</strong> ' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $analysis_date ) ) ) . '</span>
        </div>';

        if ( $submitter_name || $submitter_email || $company ) {
            $html .= '<div class="meta" style="margin-top: 10px;">';
            if ( $submitter_name ) {
                $html .= '<span class="meta-item"><strong>' . esc_html__( 'Submitter:', '84em-gf-ai' ) . '</strong> ' . esc_html( $submitter_name ) . '</span>';
            }
            if ( $submitter_email ) {
                $html .= '<span class="meta-item"><strong>' . esc_html__( 'Email:', '84em-gf-ai' ) . '</strong> ' . esc_html( $submitter_email ) . '</span>';
            }
            if ( $company ) {
                $html .= '<span class="meta-item"><strong>' . esc_html__( 'Company:', '84em-gf-ai' ) . '</strong> ' . esc_html( $company ) . '</span>';
            }
            $html .= '</div>';
        }

        $html .= '</div>
    <div class="content" id="analysis-content">
        <!-- Content will be inserted by JavaScript -->
    </div>
    <div class="footer">
        <p>' . esc_html( sprintf( __( 'Generated on %s by 84EM Gravity Forms AI Analysis', '84em-gf-ai' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) ) . '</p>
    </div>

    <script>
        // Configure Marked.js
        marked.setOptions({
            breaks: true,
            gfm: true,
            headerIds: false,
            mangle: false,
            smartLists: true,
            smartypants: true
        });

        // Get the markdown content
        const markdownContent = ' . $markdown_json . ';

        // Convert markdown to HTML
        const htmlContent = marked.parse(markdownContent);

        // Insert the converted HTML into the content div
        document.getElementById("analysis-content").innerHTML = htmlContent;
    </script>
</body>
</html>';

        // Set headers for download
        $filename = sprintf( 'ai-analysis-entry-%d-%s.html', $entry_id, date( 'Y-m-d' ) );

        // Send the HTML as a downloadable file
        wp_send_json_success( array(
            'html'     => $html,
            'filename' => $filename,
        ) );
    }
}
