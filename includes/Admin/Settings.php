<?php
/**
 * Admin Settings Page
 *
 * @package EightyFourEM\GravityFormsAI
 */

namespace EightyFourEM\GravityFormsAI\Admin;

use EightyFourEM\GravityFormsAI\Core\Encryption;

/**
 * Settings class
 */
class Settings {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_ajax_84em_gf_ai_save_field_mapping', [ $this, 'ajax_save_field_mapping' ] );
        add_action( 'wp_ajax_84em_gf_ai_test_api', [ $this, 'ajax_test_api' ] );
        add_action( 'wp_ajax_84em_gf_ai_get_log_details', [ $this, 'ajax_get_log_details' ] );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __( '84EM GF AI Analysis', '84em-gf-ai' ),
            __( 'GF AI Analysis', '84em-gf-ai' ),
            'manage_options',
            '84em-gf-ai-settings',
            [ $this, 'settings_page' ],
            'dashicons-analytics',
            85
        );

        add_submenu_page(
            '84em-gf-ai-settings',
            __( 'Settings', '84em-gf-ai' ),
            __( 'Settings', '84em-gf-ai' ),
            'manage_options',
            '84em-gf-ai-settings',
            [ $this, 'settings_page' ]
        );

        add_submenu_page(
            '84em-gf-ai-settings',
            __( 'Advanced Settings', '84em-gf-ai' ),
            __( 'Advanced Settings', '84em-gf-ai' ),
            'manage_options',
            '84em-gf-ai-mappings',
            [ $this, 'mappings_page' ]
        );

        add_submenu_page(
            '84em-gf-ai-settings',
            __( 'Logs', '84em-gf-ai' ),
            __( 'Logs', '84em-gf-ai' ),
            'manage_options',
            '84em-gf-ai-logs',
            [ $this, 'logs_page' ]
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // General settings
        register_setting( '84em_gf_ai_settings', '84em_gf_ai_enabled' );
        register_setting( '84em_gf_ai_settings', '84em_gf_ai_model' );
        register_setting( '84em_gf_ai_settings', '84em_gf_ai_max_tokens' );
        register_setting( '84em_gf_ai_settings', '84em_gf_ai_temperature' );
        register_setting( '84em_gf_ai_settings', '84em_gf_ai_rate_limit' );
        register_setting( '84em_gf_ai_settings', '84em_gf_ai_enable_logging' );
        register_setting( '84em_gf_ai_settings', '84em_gf_ai_log_retention' );
        register_setting( '84em_gf_ai_settings', '84em_gf_ai_delete_on_uninstall' );
        register_setting( '84em_gf_ai_settings', '84em_gf_ai_default_prompt' );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, '84em-gf-ai' ) === false && strpos( $hook, 'gf_entries' ) === false ) {
            return;
        }

        // Enqueue Marked.js from CDN for markdown parsing
        // Only load admin CSS/JS on our plugin pages
        if ( strpos( $hook, '84em-gf-ai' ) === false ) {
            return;
        }

        // Use minified versions if available (production), otherwise use source files (development)
        $css_file = file_exists( EIGHTYFOUREM_GF_AI_PATH . 'assets/css/admin.min.css' ) ? 'admin.min.css' : 'admin.css';
        $js_file = file_exists( EIGHTYFOUREM_GF_AI_PATH . 'assets/js/admin.min.js' ) ? 'admin.min.js' : 'admin.js';

        wp_enqueue_style(
            '84em-gf-ai-admin',
            EIGHTYFOUREM_GF_AI_URL . 'assets/css/' . $css_file,
            [],
            EIGHTYFOUREM_GF_AI_VERSION
        );

        wp_enqueue_script(
            '84em-gf-ai-admin',
            EIGHTYFOUREM_GF_AI_URL . 'assets/js/' . $js_file,
            [ 'jquery', 'wp-util' ],
            EIGHTYFOUREM_GF_AI_VERSION,
            true
        );

        wp_localize_script( '84em-gf-ai-admin', 'eightyfourGfAi', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( '84em-gf-ai-admin' ),
            'strings'  => [
                'saving'       => __( 'Saving...', '84em-gf-ai' ),
                'saved'        => __( 'Saved!', '84em-gf-ai' ),
                'error'        => __( 'Error occurred', '84em-gf-ai' ),
                'testing'      => __( 'Testing API...', '84em-gf-ai' ),
                'test_success' => __( 'API connection successful!', '84em-gf-ai' ),
                'test_failed'  => __( 'API connection failed', '84em-gf-ai' ),
            ],
        ] );
    }

    /**
     * Settings page
     */
    public function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Handle API key update
        if ( isset( $_POST['84em_gf_ai_update_api_key'] ) && wp_verify_nonce( $_POST['_wpnonce'], '84em_gf_ai_api_key' ) ) {
            $this->update_api_key();
        }

        $encryption  = new Encryption();
        $has_api_key = $encryption->has_api_key();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <?php settings_errors( '84em_gf_ai_messages' ); ?>

            <?php if ( isset( $_GET['settings-updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Settings saved.', '84em-gf-ai' ); ?></p>
                </div>
            <?php endif; ?>

            <div class="eightyfourem-gf-ai-settings">
                <h2 class="nav-tab-wrapper">
                    <a href="#general" class="nav-tab nav-tab-active"><?php esc_html_e( 'General', '84em-gf-ai' ); ?></a>
                    <a href="#api" class="nav-tab"><?php esc_html_e( 'API Configuration', '84em-gf-ai' ); ?></a>
                    <a href="#prompts" class="nav-tab"><?php esc_html_e( 'Prompts', '84em-gf-ai' ); ?></a>
                </h2>

                <form method="post" action="options.php">
                    <?php settings_fields( '84em_gf_ai_settings' ); ?>

                    <!-- General Settings Tab -->
                    <div id="general" class="tab-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="84em_gf_ai_enabled"><?php esc_html_e( 'Enable AI Analysis', '84em-gf-ai' ); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" id="84em_gf_ai_enabled" name="84em_gf_ai_enabled" value="1"
                                        <?php checked( get_option( '84em_gf_ai_enabled' ), 1 ); ?> />
                                    <p class="description">
                                        <?php esc_html_e( 'Enable AI analysis for form submissions.', '84em-gf-ai' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="84em_gf_ai_enable_logging"><?php esc_html_e( 'Enable Logging', '84em-gf-ai' ); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" id="84em_gf_ai_enable_logging" name="84em_gf_ai_enable_logging" value="1"
                                        <?php checked( get_option( '84em_gf_ai_enable_logging' ), 1 ); ?> />
                                    <p class="description">
                                        <?php esc_html_e( 'Log API requests and responses for debugging.', '84em-gf-ai' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="84em_gf_ai_log_retention"><?php esc_html_e( 'Log Retention', '84em-gf-ai' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="84em_gf_ai_log_retention" name="84em_gf_ai_log_retention"
                                           value="<?php echo esc_attr( get_option( '84em_gf_ai_log_retention', 30 ) ); ?>"
                                           min="1" max="365"/>
                                    <p class="description">
                                        <?php esc_html_e( 'Days to keep logs (1-365). Older logs are automatically deleted.', '84em-gf-ai' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="84em_gf_ai_delete_on_uninstall"><?php esc_html_e( 'Delete Data on Uninstall', '84em-gf-ai' ); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" id="84em_gf_ai_delete_on_uninstall" name="84em_gf_ai_delete_on_uninstall" value="1"
                                        <?php checked( get_option( '84em_gf_ai_delete_on_uninstall', 0 ), 1 ); ?> />
                                    <p class="description">
                                        <?php esc_html_e( 'Delete all plugin data (settings, logs, and analysis history) when the plugin is uninstalled. If unchecked, data will be preserved for reinstallation.', '84em-gf-ai' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="84em_gf_ai_rate_limit"><?php esc_html_e( 'Rate Limit', '84em-gf-ai' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="84em_gf_ai_rate_limit" name="84em_gf_ai_rate_limit"
                                           value="<?php echo esc_attr( get_option( '84em_gf_ai_rate_limit', 2 ) ); ?>"
                                           min="1" max="60"/>
                                    <p class="description">
                                        <?php esc_html_e( 'Seconds to wait between API requests.', '84em-gf-ai' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- API Configuration Tab -->
                    <div id="api" class="tab-content" style="display:none;">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="84em_gf_ai_model"><?php esc_html_e( 'Claude Model', '84em-gf-ai' ); ?></label>
                                </th>
                                <td>
                                    <select id="84em_gf_ai_model" name="84em_gf_ai_model">
                                        <?php
                                        $models        = [
                                            // Claude Opus Family (Most Capable)
                                            'claude-opus-4-1-20250805'   => 'Claude Opus 4.1 (Latest, Most Capable)',
                                            'claude-opus-4-20250514'     => 'Claude Opus 4 (Advanced)',

                                            // Claude Sonnet Family (Balanced)
                                            'claude-sonnet-4-20250514'   => 'Claude Sonnet 4 (1M Context - Beta)',
                                            'claude-3-7-sonnet-20250219' => 'Claude 3.7 Sonnet (Hybrid Reasoning)',

                                            // Claude Haiku Family (Fast)
                                            'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku (Fast, Recommended)',
                                            'claude-3-haiku-20240307'    => 'Claude 3 Haiku (Previous Fast)',

                                            // Deprecated Models (Still Functional)
                                            'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Deprecated)',
                                            'claude-3-opus-20240229'     => 'Claude 3 Opus (Deprecated)',
                                        ];
                                        $current_model = get_option( '84em_gf_ai_model', 'claude-3-5-haiku-20241022' );
                                        foreach ( $models as $value => $label ) {
                                            printf(
                                                '<option value="%s" %s>%s</option>',
                                                esc_attr( $value ),
                                                selected( $current_model, $value, false ),
                                                esc_html( $label )
                                            );
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="84em_gf_ai_max_tokens"><?php esc_html_e( 'Max Tokens', '84em-gf-ai' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="84em_gf_ai_max_tokens" name="84em_gf_ai_max_tokens"
                                           value="<?php echo esc_attr( get_option( '84em_gf_ai_max_tokens', 1000 ) ); ?>"
                                           min="100" max="4000"/>
                                    <p class="description">
                                        <?php esc_html_e( 'Maximum tokens for AI response (100-4000).', '84em-gf-ai' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="84em_gf_ai_temperature"><?php esc_html_e( 'Temperature', '84em-gf-ai' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="84em_gf_ai_temperature" name="84em_gf_ai_temperature"
                                           value="<?php echo esc_attr( get_option( '84em_gf_ai_temperature', 0.7 ) ); ?>"
                                           min="0" max="1" step="0.1"/>
                                    <p class="description">
                                        <?php esc_html_e( 'AI creativity level (0 = focused, 1 = creative).', '84em-gf-ai' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Prompts Tab -->
                    <div id="prompts" class="tab-content" style="display:none;">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="84em_gf_ai_default_prompt"><?php esc_html_e( 'Default Prompt', '84em-gf-ai' ); ?></label>
                                </th>
                                <td>
                                    <textarea id="84em_gf_ai_default_prompt" name="84em_gf_ai_default_prompt"
                                              rows="6" cols="60" class="large-text"><?php
                                        echo esc_textarea( get_option( '84em_gf_ai_default_prompt' ) );
                                        ?></textarea>
                                    <p class="description">
                                        <?php esc_html_e( 'Default prompt template for AI analysis. Available variables: {form_data}, {form_title}, {submitter_name}, {submitter_email}', '84em-gf-ai' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <?php submit_button(); ?>
                </form>

                <!-- API Key Management Section -->
                <div class="eightyfourem-gf-ai-api-key-section">
                    <h2><?php esc_html_e( 'API Key Management', '84em-gf-ai' ); ?></h2>

                    <?php if ( $has_api_key ) : ?>
                        <div class="notice notice-success inline">
                            <p><?php esc_html_e( 'API key is configured', '84em-gf-ai' ); ?></p>
                        </div>
                    <?php else : ?>
                        <div class="notice notice-warning inline">
                            <p><?php esc_html_e( 'No API key configured', '84em-gf-ai' ); ?></p>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="">
                        <?php wp_nonce_field( '84em_gf_ai_api_key' ); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="api_key"><?php esc_html_e( 'Claude API Key', '84em-gf-ai' ); ?></label>
                                </th>
                                <td>
                                    <input type="password" id="api_key" name="api_key" class="regular-text"
                                           placeholder="<?php echo $has_api_key ? '••••••••••••••••' : 'sk-ant-...'; ?>"/>
                                    <button type="submit" name="84em_gf_ai_update_api_key" class="button button-primary">
                                        <?php esc_html_e( 'Update API Key', '84em-gf-ai' ); ?>
                                    </button>
                                    <button type="button" id="test-api" class="button">
                                        <?php esc_html_e( 'Test Connection', '84em-gf-ai' ); ?>
                                    </button>
                                    <p class="description">
                                        <?php esc_html_e( 'Your API key is encrypted and stored securely.', '84em-gf-ai' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Form mappings page
     */
    public function mappings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! class_exists( 'GFAPI' ) ) {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Form Mappings', '84em-gf-ai' ); ?></h1>
                <div class="notice notice-error">
                    <p><?php esc_html_e( 'Gravity Forms is not active.', '84em-gf-ai' ); ?></p>
                </div>
            </div>
            <?php
            return;
        }

        $forms = \GFAPI::get_forms();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Advanced Settings - Per-Form Configuration', '84em-gf-ai' ); ?></h1>

            <div class="notice notice-info">
                <p><strong><?php esc_html_e( 'Optional Configuration:', '84em-gf-ai' ); ?></strong>
                <?php esc_html_e( 'By default, all forms use the global settings and analyze all user-submitted fields automatically. Use this page only if you need to:', '84em-gf-ai' ); ?></p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php esc_html_e( 'Disable AI analysis for specific forms', '84em-gf-ai' ); ?></li>
                    <li><?php esc_html_e( 'Limit which fields are analyzed', '84em-gf-ai' ); ?></li>
                    <li><?php esc_html_e( 'Set custom prompts for specific forms', '84em-gf-ai' ); ?></li>
                </ul>
            </div>

            <p><?php esc_html_e( 'Configure advanced settings for individual forms below:', '84em-gf-ai' ); ?></p>

            <div class="eightyfourem-gf-ai-mappings">
                <?php foreach ( $forms as $form ) :
                    $form_id = $form['id'];
                    $mapping = get_option( '84em_gf_ai_mapping_' . $form_id, [] );
                    $enabled = get_option( '84em_gf_ai_enabled_' . $form_id, false );
                    $custom_prompt = get_option( '84em_gf_ai_prompt_' . $form_id, '' );
                    ?>
                    <div class="form-mapping" data-form-id="<?php echo esc_attr( $form_id ); ?>">
                        <h2><?php echo esc_html( $form['title'] ); ?></h2>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e( 'Override Global Setting', '84em-gf-ai' ); ?></label>
                                </th>
                                <td>
                                    <select class="form-enabled-override">
                                        <option value=""><?php esc_html_e( 'Use Global Setting (Default)', '84em-gf-ai' ); ?></option>
                                        <option value="1" <?php selected( $enabled, 1 ); ?>><?php esc_html_e( 'Force Enable', '84em-gf-ai' ); ?></option>
                                        <option value="0" <?php selected( $enabled === '0' || $enabled === 0, true ); ?>><?php esc_html_e( 'Force Disable', '84em-gf-ai' ); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'Leave as default to use global AI analysis setting', '84em-gf-ai' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e( 'Limit Fields (Optional)', '84em-gf-ai' ); ?></label>
                                </th>
                                <td>
                                    <p class="description" style="margin-bottom: 10px;">
                                        <?php esc_html_e( 'By default, all user fields are analyzed. Select specific fields only if you want to limit the analysis.', '84em-gf-ai' ); ?>
                                    </p>
                                    <div class="field-checkboxes">
                                        <?php foreach ( $form['fields'] as $field ) :
                                            if ( in_array( $field->type, [ 'html', 'section', 'page', 'captcha', 'honeypot' ] ) ) {
                                                continue;
                                            }
                                            $field_id = $field->id;
                                            $checked  = in_array( $field_id, $mapping );
                                            ?>
                                            <label>
                                                <input type="checkbox" class="field-selector"
                                                       value="<?php echo esc_attr( $field_id ); ?>"
                                                    <?php checked( $checked ); ?> />
                                                <?php echo esc_html( $field->label ?: 'Field ' . $field_id ); ?>
                                                <span class="field-type">(<?php echo esc_html( $field->type ); ?>)</span>
                                            </label><br>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e( 'Custom Prompt', '84em-gf-ai' ); ?></label>
                                </th>
                                <td>
                                    <textarea class="form-prompt large-text" rows="4"
                                              placeholder="<?php echo esc_attr__( 'Leave empty to use default prompt', '84em-gf-ai' ); ?>"><?php
                                        echo esc_textarea( $custom_prompt );
                                        ?></textarea>
                                    <p class="description">
                                        <?php esc_html_e( 'Override the default prompt for this form.', '84em-gf-ai' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p>
                            <button type="button" class="button button-primary save-mapping">
                                <?php esc_html_e( 'Save Settings', '84em-gf-ai' ); ?>
                            </button>
                            <span class="spinner"></span>
                            <span class="save-message"></span>
                        </p>
                    </div>
                    <hr>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Logs page
     */
    public function logs_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . '84em_gf_ai_logs';

        // Check if logs table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;

        if ( ! $table_exists ) {
            // Create logs table
            $charset_collate = $wpdb->get_charset_collate();
            $sql             = "CREATE TABLE $table_name (
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

        // Handle log clearing
        if ( isset( $_POST['clear_logs'] ) && wp_verify_nonce( $_POST['_wpnonce'], '84em_gf_ai_clear_logs' ) ) {
            $wpdb->query( "TRUNCATE TABLE $table_name" );
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Logs cleared successfully.', '84em-gf-ai' ) . '</p></div>';
        }

        // Get logs with pagination
        $per_page     = 20;
        $current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $offset       = ( $current_page - 1 ) * $per_page;

        $total_logs = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
        $logs       = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ) );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'AI Analysis Logs', '84em-gf-ai' ); ?></h1>

            <?php if ( ! get_option( '84em_gf_ai_enable_logging' ) ) : ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e( 'Logging is currently disabled. Enable it in the settings to see logs.', '84em-gf-ai' ); ?></p>
                </div>
            <?php endif; ?>

            <?php
            $retention_days = get_option( '84em_gf_ai_log_retention', 30 );
            ?>
            <div class="notice notice-info">
                <p><?php printf( esc_html__( 'Logs older than %d days are automatically deleted when new analyses are performed.', '84em-gf-ai' ), $retention_days ); ?></p>
            </div>

            <form method="post" style="margin-bottom: 20px;">
                <?php wp_nonce_field( '84em_gf_ai_clear_logs' ); ?>
                <button type="submit" name="clear_logs" class="button"
                        onclick="return confirm('<?php echo esc_attr__( 'Are you sure you want to clear all logs?', '84em-gf-ai' ); ?>');">
                    <?php esc_html_e( 'Clear All Logs', '84em-gf-ai' ); ?>
                </button>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Date', '84em-gf-ai' ); ?></th>
                    <th><?php esc_html_e( 'Form', '84em-gf-ai' ); ?></th>
                    <th><?php esc_html_e( 'Entry', '84em-gf-ai' ); ?></th>
                    <th><?php esc_html_e( 'Status', '84em-gf-ai' ); ?></th>
                    <th><?php esc_html_e( 'Details', '84em-gf-ai' ); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ( empty( $logs ) ) : ?>
                    <tr>
                        <td colspan="5"><?php esc_html_e( 'No logs found.', '84em-gf-ai' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $logs as $log ) :
                        $form = \GFAPI::get_form( $log->form_id );
                        ?>
                        <tr>
                            <td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', strtotime( $log->created_at ) ) ); ?></td>
                            <td>
                                <?php if ( $form ) : ?>
                                    <a href="<?php echo admin_url( 'admin.php?page=gf_edit_forms&id=' . $log->form_id ); ?>">
                                        <?php echo esc_html( $form['title'] ); ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo esc_html( $log->form_id ); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo admin_url( 'admin.php?page=gf_entries&view=entry&id=' . $log->form_id . '&lid=' . $log->entry_id ); ?>">
                                    #<?php echo esc_html( $log->entry_id ); ?>
                                </a>
                            </td>
                            <td>
                                    <span class="status-<?php echo esc_attr( $log->status ); ?>">
                                        <?php echo esc_html( ucfirst( $log->status ) ); ?>
                                    </span>
                            </td>
                            <td>
                                <?php if ( $log->status === 'error' ) : ?>
                                    <span class="error-message"><?php echo esc_html( $log->error_message ); ?></span>
                                <?php else : ?>
                                    <button type="button" class="button button-small view-log-details"
                                            data-log-id="<?php echo esc_attr( $log->id ); ?>">
                                        <?php esc_html_e( 'View Details', '84em-gf-ai' ); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php
            // Pagination
            $total_pages = ceil( $total_logs / $per_page );
            if ( $total_pages > 1 ) {
                $pagination_args = [
                    'base'    => add_query_arg( 'paged', '%#%' ),
                    'format'  => '',
                    'current' => $current_page,
                    'total'   => $total_pages,
                ];
                echo '<div class="tablenav"><div class="tablenav-pages">';
                echo paginate_links( $pagination_args );
                echo '</div></div>';
            }
            ?>
        </div>

        <!-- Log Details Modal -->
        <div id="log-details-modal" style="display:none;">
            <div class="log-details-content"></div>
        </div>
        <?php
    }

    /**
     * Update API key
     */
    private function update_api_key() {
        if ( ! isset( $_POST['api_key'] ) || empty( $_POST['api_key'] ) ) {
            add_settings_error(
                '84em_gf_ai_messages',
                '84em_gf_ai_message',
                __( 'Please enter an API key.', '84em-gf-ai' ),
                'error'
            );
            return;
        }

        $encryption = new Encryption();
        $result     = $encryption->save_api_key( sanitize_text_field( $_POST['api_key'] ) );

        if ( $result ) {
            add_settings_error(
                '84em_gf_ai_messages',
                '84em_gf_ai_message',
                __( 'API key updated successfully.', '84em-gf-ai' ),
                'success'
            );
        }
        else {
            add_settings_error(
                '84em_gf_ai_messages',
                '84em_gf_ai_message',
                __( 'Failed to update API key.', '84em-gf-ai' ),
                'error'
            );
        }
    }

    /**
     * AJAX handler for saving field mapping
     */
    public function ajax_save_field_mapping() {
        check_ajax_referer( '84em-gf-ai-admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', '84em-gf-ai' ) );
        }

        $form_id = intval( $_POST['form_id'] );
        $enabled = $_POST['enabled'] === 'null' || $_POST['enabled'] === '' ? null : intval( $_POST['enabled'] );
        $fields  = isset( $_POST['fields'] ) ? array_map( 'intval', $_POST['fields'] ) : [];
        $prompt  = sanitize_textarea_field( $_POST['prompt'] );

        // Save settings - delete option if null to use global default
        if ( $enabled === null ) {
            delete_option( '84em_gf_ai_enabled_' . $form_id );
        } else {
            update_option( '84em_gf_ai_enabled_' . $form_id, $enabled );
        }

        // Only save field mapping if fields are selected
        if ( empty( $fields ) ) {
            delete_option( '84em_gf_ai_mapping_' . $form_id );
        } else {
            update_option( '84em_gf_ai_mapping_' . $form_id, $fields );
        }

        // Only save prompt if not empty
        if ( empty( $prompt ) ) {
            delete_option( '84em_gf_ai_prompt_' . $form_id );
        } else {
            update_option( '84em_gf_ai_prompt_' . $form_id, $prompt );
        }

        wp_send_json_success( [
            'message' => __( 'Settings saved successfully.', '84em-gf-ai' ),
        ] );
    }

    /**
     * AJAX handler for testing API connection
     */
    public function ajax_test_api() {
        check_ajax_referer( '84em-gf-ai-admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', '84em-gf-ai' ) );
        }

        $api_handler = new \EightyFourEM\GravityFormsAI\Core\APIHandler();
        $result      = $api_handler->test_connection();

        if ( $result['success'] ) {
            wp_send_json_success( [
                'message' => __( 'API connection successful!', '84em-gf-ai' ),
            ] );
        }
        else {
            wp_send_json_error( [
                'message' => $result['error'],
            ] );
        }
    }

    /**
     * AJAX handler for getting log details
     */
    public function ajax_get_log_details() {
        check_ajax_referer( '84em-gf-ai-admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', '84em-gf-ai' ) );
        }

        $log_id = intval( $_POST['log_id'] );
        if ( ! $log_id ) {
            wp_send_json_error( [
                'message' => __( 'Invalid log ID', '84em-gf-ai' ),
            ] );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . '84em_gf_ai_logs';

        // Get the log entry
        $log = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $log_id
        ) );

        if ( ! $log ) {
            wp_send_json_error( [
                'message' => __( 'Log entry not found', '84em-gf-ai' ),
            ] );
        }

        // Format the details for display
        $details = '<div class="log-details">';
        $details .= '<h3>' . __( 'Log Entry Details', '84em-gf-ai' ) . '</h3>';

        // Basic information
        $details .= '<div class="log-info">';
        $details .= '<p><strong>' . __( 'Date:', '84em-gf-ai' ) . '</strong> ' . esc_html( wp_date( 'Y-m-d H:i:s', strtotime( $log->created_at ) ) ) . '</p>';
        $details .= '<p><strong>' . __( 'Form ID:', '84em-gf-ai' ) . '</strong> ' . esc_html( $log->form_id ) . '</p>';
        $details .= '<p><strong>' . __( 'Entry ID:', '84em-gf-ai' ) . '</strong> ' . esc_html( $log->entry_id ) . '</p>';
        $details .= '<p><strong>' . __( 'Status:', '84em-gf-ai' ) . '</strong> <span class="status-' . esc_attr( $log->status ) . '">' . esc_html( ucfirst( $log->status ) ) . '</span></p>';

        if ( $log->error_message ) {
            $details .= '<p><strong>' . __( 'Error:', '84em-gf-ai' ) . '</strong> <span class="error-message">' . esc_html( $log->error_message ) . '</span></p>';
        }
        $details .= '</div>';

        // Request details
        if ( $log->request ) {
            $details .= '<div class="log-request">';
            $details .= '<h4>' . __( 'Request', '84em-gf-ai' ) . '</h4>';
            $details .= '<pre style="background: #f4f4f4; padding: 10px; overflow-x: auto; max-height: 300px;">';

            // Try to format JSON nicely
            $request_data = json_decode( $log->request, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $details .= esc_html( json_encode( $request_data, JSON_PRETTY_PRINT ) );
            } else {
                $details .= esc_html( $log->request );
            }

            $details .= '</pre>';
            $details .= '</div>';
        }

        // Response details
        if ( $log->response ) {
            $details .= '<div class="log-response">';
            $details .= '<h4>' . __( 'Response', '84em-gf-ai' ) . '</h4>';
            $details .= '<pre style="background: #f4f4f4; padding: 10px; overflow-x: auto; max-height: 300px;">';

            // Try to format JSON nicely
            $response_data = json_decode( $log->response, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $details .= esc_html( json_encode( $response_data, JSON_PRETTY_PRINT ) );
            } else {
                $details .= esc_html( $log->response );
            }

            $details .= '</pre>';
            $details .= '</div>';
        }

        $details .= '<div style="text-align: right; margin-top: 20px;">';
        $details .= '<button type="button" class="button" onclick="jQuery(\'#log-details-modal\').hide();">' . __( 'Close', '84em-gf-ai' ) . '</button>';
        $details .= '</div>';
        $details .= '</div>';

        wp_send_json_success( [
            'details' => $details,
        ] );
    }
}
