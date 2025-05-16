<?php
/**
 * Plugin Name: WooCommerce Deposits Hybrid
 * Plugin URI: https://github.com/namiokuzono/woocommerce-deposits-hybrid
 * Description: Extends WooCommerce Deposits to support hybrid deposit and payment plan options
 * Version: 1.0.0
 * Author: Woo Nami
 * Author URI: https://github.com/namiokuzono
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

// Debug logging function
function wc_deposits_hybrid_log( $message, $level = 'info' ) {
    $log_file = WC_DEPOSITS_HYBRID_PLUGIN_DIR . 'debug.log';
    $timestamp = current_time( 'mysql' );
    $log_message = sprintf( "[%s] [%s] %s\n", $timestamp, strtoupper( $level ), $message );
    error_log( $log_message, 3, $log_file );
}

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
        wc_deposits_hybrid_log( 'Plugin constructor called' );
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        wc_deposits_hybrid_log( 'Initializing hooks' );
        
        // Check if WooCommerce and WooCommerce Deposits are active
        add_action( 'plugins_loaded', array( $this, 'check_dependencies' ), 20 );
        
        // Initialize the plugin
        add_action( 'init', array( $this, 'init' ) );

        // Add HPOS compatibility
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

        // Add admin notices
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    }

    /**
     * Declare HPOS compatibility
     */
    public function declare_hpos_compatibility() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }

    /**
     * Check if required plugins are active
     */
    public function check_dependencies() {
        wc_deposits_hybrid_log( 'Checking dependencies' );

        if ( ! class_exists( 'WooCommerce' ) ) {
            wc_deposits_hybrid_log( 'WooCommerce not found', 'error' );
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        if ( ! class_exists( 'WC_Deposits' ) ) {
            wc_deposits_hybrid_log( 'WooCommerce Deposits not found', 'error' );
            add_action( 'admin_notices', array( $this, 'deposits_missing_notice' ) );
            return;
        }

        wc_deposits_hybrid_log( 'Dependencies met, loading plugin files' );
        
        // Only load plugin files if dependencies are met
        $this->includes();
        
        // Initialize managers
        $this->init_managers();
    }

    /**
     * Initialize managers
     */
    private function init_managers() {
        wc_deposits_hybrid_log( 'Initializing managers' );
        
        if ( class_exists( 'WC_Deposits_Hybrid_Product_Manager' ) ) {
            wc_deposits_hybrid_log( 'Initializing Product Manager' );
            new WC_Deposits_Hybrid_Product_Manager();
        }
        if ( class_exists( 'WC_Deposits_Hybrid_Order_Manager' ) ) {
            wc_deposits_hybrid_log( 'Initializing Order Manager' );
            new WC_Deposits_Hybrid_Order_Manager();
        }
    }

    /**
     * Include required files
     */
    private function includes() {
        wc_deposits_hybrid_log( 'Including required files' );
        
        // Include core classes
        require_once WC_DEPOSITS_HYBRID_PLUGIN_DIR . 'includes/class-wc-deposits-hybrid-product-manager.php';
        require_once WC_DEPOSITS_HYBRID_PLUGIN_DIR . 'includes/class-wc-deposits-hybrid-order-manager.php';
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        wc_deposits_hybrid_log( 'Initializing plugin' );
        
        // Load text domain
        load_plugin_textdomain( 'wc-deposits-hybrid', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            $this->woocommerce_missing_notice();
        }
        if ( ! class_exists( 'WC_Deposits' ) ) {
            $this->deposits_missing_notice();
        }
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
add_action( 'plugins_loaded', function() {
    wc_deposits_hybrid_log( 'Plugin loaded, initializing main instance' );
    WC_Deposits_Hybrid();
}, 30 ); 