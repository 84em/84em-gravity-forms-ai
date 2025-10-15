<?php
/**
 * Plugin Name: 84EM Gravity Forms Entry AI Analysis
 * Plugin URI: https://84em.com
 * Description: Analyzes Gravity Forms submissions using Claude AI and stores insights as markdown in entry meta
 * Version: 1.1.3
 * Author: 84EM
 * Author URI: https://84em.com
 * License: GPL v2 or later
 * Text Domain: 84em-gf-ai
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

namespace EightyFourEM\GravityFormsAI;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'EIGHTYFOUREM_GF_AI_VERSION', '1.1.3' );
define( 'EIGHTYFOUREM_GF_AI_FILE', __FILE__ );
define( 'EIGHTYFOUREM_GF_AI_PATH', plugin_dir_path( __FILE__ ) );
define( 'EIGHTYFOUREM_GF_AI_URL', plugin_dir_url( __FILE__ ) );
define( 'EIGHTYFOUREM_GF_AI_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader for plugin classes
spl_autoload_register( function ( $class ) {
    $prefix   = 'EightyFourEM\\GravityFormsAI\\';
    $base_dir = EIGHTYFOUREM_GF_AI_PATH . 'includes/';

    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
} );

/**
 * Main plugin class
 */
class Plugin {

    /**
     * Instance of this class
     *
     * @var Plugin
     */
    private static $instance = null;

    /**
     * Get instance of this class
     *
     * @return Plugin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->check_dependencies();
        $this->init_hooks();
        $this->load_components();
    }

    /**
     * Check plugin dependencies
     */
    private function check_dependencies() {
        add_action( 'admin_notices', [ $this, 'check_gravity_forms' ] );
    }

    /**
     * Check if Gravity Forms is active
     */
    public function check_gravity_forms() {
        if ( ! class_exists( 'GFForms' ) ) {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e( '84EM Gravity Forms AI Analysis requires Gravity Forms to be installed and activated.', '84em-gf-ai' ); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook( EIGHTYFOUREM_GF_AI_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( EIGHTYFOUREM_GF_AI_FILE, [ $this, 'deactivate' ] );

        // Load textdomain
        add_action( 'init', [ $this, 'load_textdomain' ] );

        // Add settings link on plugins page
        add_filter( 'plugin_action_links_' . EIGHTYFOUREM_GF_AI_BASENAME, [ $this, 'add_settings_link' ] );
    }

    /**
     * Load plugin components
     */
    private function load_components() {
        if ( ! class_exists( 'GFForms' ) ) {
            return;
        }

        // Load core components
        new Admin\Settings();
        new Core\EntryProcessor();
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $this->set_default_options();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
    }


    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = [
            '84em_gf_ai_enabled'        => false,
            '84em_gf_ai_model'          => 'claude-3-5-haiku-20241022',
            '84em_gf_ai_max_tokens'     => 1000,
            '84em_gf_ai_temperature'    => 0.7,
            '84em_gf_ai_rate_limit'     => 2, // seconds between requests
            '84em_gf_ai_enable_logging' => true,
            '84em_gf_ai_log_retention'  => 30, // days to keep logs
            '84em_gf_ai_default_prompt' => 'Analyze this form submission and provide insights about the submitter. Search for relevant information about the person or company if available. Focus on professional background, company details, and potential business needs.',
        ];

        foreach ( $defaults as $option => $value ) {
            if ( get_option( $option ) === false ) {
                update_option( $option, $value );
            }
        }
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            '84em-gf-ai',
            false,
            dirname( EIGHTYFOUREM_GF_AI_BASENAME ) . '/languages'
        );
    }

    /**
     * Add settings link on plugins page
     *
     * @param  array  $links
     *
     * @return array
     */
    public function add_settings_link( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=84em-gf-ai-settings' ) . '">' .
                         esc_html__( 'Settings', '84em-gf-ai' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }
}

// Initialize the plugin
add_action( 'plugins_loaded', function () {
    Plugin::get_instance();
} );
