<?php
/**
 * Plugin Name: WooCommerce Deposits Hybrid
 * Plugin URI: https://yourwebsite.com/wc-deposits-hybrid
 * Description: Extends WooCommerce Deposits to support hybrid deposit and payment plan options
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: wc-deposits-hybrid
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.8.3
 *
 * @package WC_Deposits_Hybrid
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define( 'WC_DEPOSITS_HYBRID_VERSION', '1.0.0' );
define( 'WC_DEPOSITS_HYBRID_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_DEPOSITS_HYBRID_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class
 */
class WC_Deposits_Hybrid {
    /**
     * Single instance of the class
     *
     * @var WC_Deposits_Hybrid
     */
    protected static $instance = null;

    /**
     * Main instance
     *
     * @return WC_Deposits_Hybrid
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        $this->includes();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Check if WooCommerce and WooCommerce Deposits are active
        add_action( 'plugins_loaded', array( $this, 'check_dependencies' ) );
        
        // Initialize the plugin
        add_action( 'init', array( $this, 'init' ) );
    }

    /**
     * Check if required plugins are active
     */
    public function check_dependencies() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        if ( ! class_exists( 'WC_Deposits' ) ) {
            add_action( 'admin_notices', array( $this, 'deposits_missing_notice' ) );
            return;
        }
    }

    /**
     * Include required files
     */
    private function includes() {
        // Include core classes
        require_once WC_DEPOSITS_HYBRID_PLUGIN_DIR . 'includes/class-wc-deposits-hybrid-product-manager.php';
        require_once WC_DEPOSITS_HYBRID_PLUGIN_DIR . 'includes/class-wc-deposits-hybrid-order-manager.php';
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain( 'wc-deposits-hybrid', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e( 'WooCommerce Deposits Hybrid requires WooCommerce to be installed and active.', 'wc-deposits-hybrid' ); ?></p>
        </div>
        <?php
    }

    /**
     * Deposits missing notice
     */
    public function deposits_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e( 'WooCommerce Deposits Hybrid requires WooCommerce Deposits to be installed and active.', 'wc-deposits-hybrid' ); ?></p>
        </div>
        <?php
    }
}

/**
 * Returns the main instance of WC_Deposits_Hybrid
 *
 * @return WC_Deposits_Hybrid
 */
function WC_Deposits_Hybrid() {
    return WC_Deposits_Hybrid::instance();
}

// Initialize the plugin
WC_Deposits_Hybrid(); 