<?php
/**
 * Admin Notices class for Hide Prices for Guests plugin
 *
 * @package Hide_Prices_For_Guests
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class HPFG_Admin_Notices
 * 
 * Handles admin notices for the Hide Prices for Guests plugin.
 */
class HPFG_Admin_Notices {

    /**
     * Constructor
     */
    public function __construct() {
        // Check if we need to show activation notice
        add_action( 'admin_notices', array( $this, 'activation_notice' ) );
        
        // Handle notice dismissal
        add_action( 'admin_init', array( $this, 'dismiss_notices' ) );
    }

    /**
     * Show activation notice
     */
    public function activation_notice() {
        // Check if notice has been dismissed
        if ( get_option( 'hpfg_activation_notice_dismissed' ) ) {
            return;
        }

        // Check if plugin was just activated
        if ( ! get_transient( 'hpfg_activation_notice' ) ) {
            return;
        }

        // Get settings page URL
        $settings_url = admin_url( 'admin.php?page=hide-prices-for-guests' );
        
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                printf(
                    /* translators: %s: Settings page URL */
                    esc_html__( 'Thank you for installing Hide Prices for Guests! Please visit the %1$ssettings page%2$s to configure the plugin.', 'hide-prices-for-guests' ),
                    '<a href="' . esc_url( $settings_url ) . '">',
                    '</a>'
                );
                ?>
            </p>
            <p>
                <a href="<?php echo esc_url( add_query_arg( 'hpfg-dismiss-notice', 'activation' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Dismiss this notice', 'hide-prices-for-guests' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Handle notice dismissal
     */
    public function dismiss_notices() {
        // Check if we're dismissing a notice
        if ( isset( $_GET['hpfg-dismiss-notice'] ) && 'activation' === $_GET['hpfg-dismiss-notice'] ) {
            // Verify nonce if you add one
            
            // Set option to dismiss notice
            update_option( 'hpfg_activation_notice_dismissed', true );
            
            // Remove transient
            delete_transient( 'hpfg_activation_notice' );
            
            // Redirect to remove query args
            wp_safe_redirect( remove_query_arg( 'hpfg-dismiss-notice' ) );
            exit;
        }
    }

    /**
     * Set activation notice
     */
    public static function set_activation_notice() {
        // Set transient for 30 days
        set_transient( 'hpfg_activation_notice', true, 30 * DAY_IN_SECONDS );
        
        // Reset dismissed flag
        delete_option( 'hpfg_activation_notice_dismissed' );
    }
} 