<?php
/**
 * PHPUnit bootstrap file for 84EM Gravity Forms AI Analysis
 *
 * @package EightyFourEM\GravityFormsAI\Tests
 */

// Define test constants
define( 'EIGHTYFOUREM_GF_AI_TESTS_ROOT', dirname( __FILE__ ) . '/' );
define( 'EIGHTYFOUREM_GF_AI_PLUGIN_ROOT', dirname( dirname( __FILE__ ) ) . '/' );

// Define WordPress security keys if not already defined (needed for Encryption class)
if ( ! defined( 'AUTH_KEY' ) ) {
	define( 'AUTH_KEY', 'test-auth-key-for-phpunit-testing-only' );
}
if ( ! defined( 'AUTH_SALT' ) ) {
	define( 'AUTH_SALT', 'test-auth-salt-for-phpunit-testing-only' );
}

// Load Composer autoloader (includes PHPUnit Polyfills)
$composer_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
	require_once $composer_autoload;
} else {
	echo "Could not find Composer autoload file. Please run 'composer install' first." . PHP_EOL;
	exit( 1 );
}

// Give access to tests_add_filter() function
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL;
	exit( 1 );
}

// Give access to tests_add_filter() function
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested
 */
function _manually_load_plugin() {
	// Load Gravity Forms if available (mock if not)
	$gf_path = WP_PLUGIN_DIR . '/gravityforms/gravityforms.php';
	if ( file_exists( $gf_path ) ) {
		require $gf_path;
	} else {
		// Create mock GF classes for testing
		if ( ! class_exists( 'GFForms' ) ) {
			class GFForms {
				public static $version = '2.8.0';
			}
		}

		if ( ! class_exists( 'GFAPI' ) ) {
			class GFAPI {
				private static $forms = [];
				private static $entries = [];
				private static $entry_meta = [];
				private static $notes = [];

				public static function get_forms() {
					return self::$forms;
				}

				public static function get_form( $form_id ) {
					return isset( self::$forms[ $form_id ] ) ? self::$forms[ $form_id ] : false;
				}

				public static function add_form( $form ) {
					$form_id = isset( $form['id'] ) ? $form['id'] : count( self::$forms ) + 1;
					$form['id'] = $form_id;
					self::$forms[ $form_id ] = $form;
					return $form_id;
				}

				public static function get_entry( $entry_id ) {
					if ( isset( self::$entries[ $entry_id ] ) ) {
						return self::$entries[ $entry_id ];
					}
					return new WP_Error( 'not_found', 'Entry not found' );
				}

				public static function get_entries( $form_id, $search_criteria = [], $sorting = [], $paging = [] ) {
					$entries = [];
					foreach ( self::$entries as $entry ) {
						if ( $entry['form_id'] == $form_id ) {
							$entries[] = $entry;
						}
					}
					return $entries;
				}

				public static function add_entry( $entry ) {
					$entry_id = isset( $entry['id'] ) ? $entry['id'] : count( self::$entries ) + 1;
					$entry['id'] = $entry_id;
					self::$entries[ $entry_id ] = $entry;
					return $entry_id;
				}

				public static function update_entry( $entry ) {
					if ( isset( $entry['id'] ) && isset( self::$entries[ $entry['id'] ] ) ) {
						self::$entries[ $entry['id'] ] = $entry;
						return true;
					}
					return false;
				}

				public static function add_note( $entry_id, $user_id, $user_name, $note ) {
					if ( ! isset( self::$notes[ $entry_id ] ) ) {
						self::$notes[ $entry_id ] = [];
					}
					self::$notes[ $entry_id ][] = [
						'id' => count( self::$notes[ $entry_id ] ) + 1,
						'entry_id' => $entry_id,
						'user_id' => $user_id,
						'user_name' => $user_name,
						'note' => $note,
						'date_created' => current_time( 'mysql' )
					];
				}

				public static function get_notes( $search_criteria = [] ) {
					$all_notes = [];
					foreach ( self::$notes as $entry_notes ) {
						$all_notes = array_merge( $all_notes, $entry_notes );
					}
					return $all_notes;
				}

				// Reset test data
				public static function reset() {
					self::$forms = [];
					self::$entries = [];
					self::$entry_meta = [];
					self::$notes = [];
				}
			}
		}

		if ( ! class_exists( 'GFCommon' ) ) {
			class GFCommon {
				public static function get_lead_field_display( $field, $value, $currency = '' ) {
					return $value;
				}
			}
		}

		// Mock Gravity Forms functions
		if ( ! function_exists( 'gform_get_meta' ) ) {
			function gform_get_meta( $entry_id, $meta_key ) {
				global $_gf_entry_meta;
				if ( ! isset( $_gf_entry_meta ) ) {
					$_gf_entry_meta = [];
				}
				return isset( $_gf_entry_meta[ $entry_id ][ $meta_key ] ) ? $_gf_entry_meta[ $entry_id ][ $meta_key ] : false;
			}
		}

		if ( ! function_exists( 'gform_update_meta' ) ) {
			function gform_update_meta( $entry_id, $meta_key, $meta_value ) {
				global $_gf_entry_meta;
				if ( ! isset( $_gf_entry_meta ) ) {
					$_gf_entry_meta = [];
				}
				if ( ! isset( $_gf_entry_meta[ $entry_id ] ) ) {
					$_gf_entry_meta[ $entry_id ] = [];
				}
				$_gf_entry_meta[ $entry_id ][ $meta_key ] = $meta_value;
				return true;
			}
		}

		if ( ! function_exists( 'gform_delete_meta' ) ) {
			function gform_delete_meta( $entry_id, $meta_key = '' ) {
				global $_gf_entry_meta;
				if ( ! isset( $_gf_entry_meta ) ) {
					return false;
				}
				if ( empty( $meta_key ) ) {
					unset( $_gf_entry_meta[ $entry_id ] );
				} else {
					unset( $_gf_entry_meta[ $entry_id ][ $meta_key ] );
				}
				return true;
			}
		}

		if ( ! function_exists( 'rgar' ) ) {
			function rgar( $array, $key, $default = null ) {
				if ( ! is_array( $array ) ) {
					return $default;
				}

				if ( isset( $array[ $key ] ) ) {
					return $array[ $key ];
				}

				// Handle nested keys (e.g., "1.3")
				if ( strpos( $key, '.' ) !== false ) {
					$keys = explode( '.', $key );
					$value = $array;
					foreach ( $keys as $k ) {
						if ( isset( $value[ $k ] ) ) {
							$value = $value[ $k ];
						} else {
							return $default;
						}
					}
					return $value;
				}

				return $default;
			}
		}
	}

	// Load our plugin
	require EIGHTYFOUREM_GF_AI_PLUGIN_ROOT . '84em-gravity-forms-ai.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment
require "{$_tests_dir}/includes/bootstrap.php";

// Include test base class
require_once EIGHTYFOUREM_GF_AI_TESTS_ROOT . 'class-test-case.php';

/**
 * Create test database tables if needed
 */
function create_test_tables() {
	global $wpdb;

	// Create the logs table
	$table_name = $wpdb->prefix . '84em_gf_ai_logs';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
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

// Create test tables
create_test_tables();

// Helper function to reset test data
function reset_test_data() {
	global $wpdb, $_gf_entry_meta;

	// Clear options
	$options = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '84em_gf_ai_%'" );
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Clear logs table
	$table_name = $wpdb->prefix . '84em_gf_ai_logs';
	$wpdb->query( "TRUNCATE TABLE $table_name" );

	// Clear entry meta
	$_gf_entry_meta = [];

	// Reset GFAPI if it's our mock
	if ( method_exists( 'GFAPI', 'reset' ) ) {
		GFAPI::reset();
	}
}