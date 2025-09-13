<?php
/**
 * Uninstall Script for 84EM Gravity Forms AI Analysis
 *
 * This file is executed when the plugin is deleted through the WordPress admin.
 * It removes all plugin data from the database to ensure a clean uninstall.
 *
 * @package EightyFourEM\GravityFormsAI
 */

// Exit if uninstall not called from WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Define the option prefix
$option_prefix = '84em_gf_ai_';

// Check if user has opted to delete data on uninstall
$delete_on_uninstall = get_option( '84em_gf_ai_delete_on_uninstall', false );

// If the option is not set to delete data, exit early
if ( ! $delete_on_uninstall ) {
    // Optionally, you could still delete just the delete_on_uninstall option itself
    // delete_option( '84em_gf_ai_delete_on_uninstall' );
    return;
}

/**
 * User has opted to delete all plugin data
 * Proceed with complete cleanup
 */

// Delete all plugin options using a single query with LIKE
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like( $option_prefix ) . '%'
    )
);

// Drop the custom logs table if it exists
$table_name = $wpdb->prefix . '84em_gf_ai_logs';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

/**
 * Clean up Gravity Forms entry meta
 * Remove all AI analysis data stored in entry meta
 * Note: We check if the table exists since Gravity Forms might not be installed
 */
$gf_entry_meta_table = $wpdb->prefix . 'gf_entry_meta';
if ( $wpdb->get_var( "SHOW TABLES LIKE '{$gf_entry_meta_table}'" ) === $gf_entry_meta_table ) {
    // Define meta keys to remove
    $meta_keys = [
        '84em_ai_analysis',
        '84em_ai_analysis_date',
        '84em_ai_analysis_error',
        '84em_ai_analysis_error_date'
    ];

    // Delete all entry meta for our plugin
    foreach ( $meta_keys as $meta_key ) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$gf_entry_meta_table} WHERE meta_key = %s",
                $meta_key
            )
        );
    }
}
