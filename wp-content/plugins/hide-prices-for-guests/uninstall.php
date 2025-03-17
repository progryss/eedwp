<?php
/**
 * Uninstall Hide Prices for Guests
 *
 * @package Hide_Prices_For_Guests
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options
delete_option( 'hpfg_options' );

// Clear any cached data that might be stored
wp_cache_flush(); 