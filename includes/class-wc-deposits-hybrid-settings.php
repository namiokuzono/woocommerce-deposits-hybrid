<?php
/**
 * Settings for Hybrid Deposits
 *
 * @package WC_Deposits_Hybrid
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Only load after WooCommerce is fully loaded
if ( ! class_exists( 'WC_Settings_Page' ) ) {
    return;
}

/**
 * WC_Deposits_Hybrid_Settings class
 */
class WC_Deposits_Hybrid_Settings extends WC_Settings_Page {
    /**
     * Constructor
     */
    public function __construct() {
        $this->id    = 'wc-deposits-hybrid';
        $this->label = __( 'Deposits Hybrid', 'wc-deposits-hybrid' );

        parent::__construct();
    }

    /**
     * Get settings array
     *
     * @return array
     */
    public function get_settings() {
        $settings = array(
            array(
                'title' => __( 'Debug Settings', 'wc-deposits-hybrid' ),
                'type'  => 'title',
                'desc'  => __( 'Configure debug settings for the Hybrid Deposits extension.', 'wc-deposits-hybrid' ),
                'id'    => 'wc_deposits_hybrid_debug_settings',
            ),
            array(
                'title'    => __( 'Debug Mode', 'wc-deposits-hybrid' ),
                'desc'     => __( 'Enable debug logging', 'wc-deposits-hybrid' ),
                'id'       => 'wc_deposits_hybrid_debug_mode',
                'default'  => 'no',
                'type'     => 'checkbox',
            ),
            array(
                'title'    => __( 'Debug Log File', 'wc-deposits-hybrid' ),
                'desc'     => __( 'Path to the debug log file', 'wc-deposits-hybrid' ),
                'id'       => 'wc_deposits_hybrid_debug_log_file',
                'default'  => WC_DEPOSITS_HYBRID_PLUGIN_DIR . 'debug.log',
                'type'     => 'text',
                'css'      => 'width: 400px;',
            ),
            array(
                'title'    => __( 'Debug Log Level', 'wc-deposits-hybrid' ),
                'desc'     => __( 'Minimum level of messages to log', 'wc-deposits-hybrid' ),
                'id'       => 'wc_deposits_hybrid_debug_level',
                'default'  => 'info',
                'type'     => 'select',
                'options'  => array(
                    'debug'   => __( 'Debug', 'wc-deposits-hybrid' ),
                    'info'    => __( 'Info', 'wc-deposits-hybrid' ),
                    'warning' => __( 'Warning', 'wc-deposits-hybrid' ),
                    'error'   => __( 'Error', 'wc-deposits-hybrid' ),
                ),
            ),
            array(
                'title'    => __( 'Debug Log Retention', 'wc-deposits-hybrid' ),
                'desc'     => __( 'Number of days to keep debug logs', 'wc-deposits-hybrid' ),
                'id'       => 'wc_deposits_hybrid_debug_retention',
                'default'  => '7',
                'type'     => 'number',
                'custom_attributes' => array(
                    'min'  => '1',
                    'max'  => '90',
                    'step' => '1',
                ),
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'wc_deposits_hybrid_debug_settings',
            ),
            array(
                'title' => __( 'Debug Tools', 'wc-deposits-hybrid' ),
                'type'  => 'title',
                'desc'  => __( 'Tools to help debug issues with the Hybrid Deposits extension.', 'wc-deposits-hybrid' ),
                'id'    => 'wc_deposits_hybrid_debug_tools',
            ),
            array(
                'title'    => __( 'Clear Debug Log', 'wc-deposits-hybrid' ),
                'desc'     => __( 'Clear the debug log file', 'wc-deposits-hybrid' ),
                'id'       => 'wc_deposits_hybrid_clear_log',
                'type'     => 'button',
                'button_text' => __( 'Clear Log', 'wc-deposits-hybrid' ),
                'callback' => array( $this, 'clear_debug_log' ),
            ),
            array(
                'title'    => __( 'Download Debug Log', 'wc-deposits-hybrid' ),
                'desc'     => __( 'Download the debug log file', 'wc-deposits-hybrid' ),
                'id'       => 'wc_deposits_hybrid_download_log',
                'type'     => 'button',
                'button_text' => __( 'Download Log', 'wc-deposits-hybrid' ),
                'callback' => array( $this, 'download_debug_log' ),
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'wc_deposits_hybrid_debug_tools',
            ),
        );

        return apply_filters( 'wc_deposits_hybrid_settings', $settings );
    }

    /**
     * Clear debug log
     */
    public function clear_debug_log() {
        $log_file = get_option( 'wc_deposits_hybrid_debug_log_file', WC_DEPOSITS_HYBRID_PLUGIN_DIR . 'debug.log' );
        if ( file_exists( $log_file ) && is_writable( $log_file ) ) {
            file_put_contents( $log_file, '' );
            WC_Admin_Settings::add_message( __( 'Debug log cleared successfully.', 'wc-deposits-hybrid' ) );
        } else {
            WC_Admin_Settings::add_error( __( 'Could not clear debug log. Please check file permissions.', 'wc-deposits-hybrid' ) );
        }
    }

    /**
     * Download debug log
     */
    public function download_debug_log() {
        $log_file = get_option( 'wc_deposits_hybrid_debug_log_file', WC_DEPOSITS_HYBRID_PLUGIN_DIR . 'debug.log' );
        if ( file_exists( $log_file ) ) {
            header( 'Content-Type: text/plain' );
            header( 'Content-Disposition: attachment; filename="wc-deposits-hybrid-debug.log"' );
            header( 'Content-Length: ' . filesize( $log_file ) );
            readfile( $log_file );
            exit;
        } else {
            WC_Admin_Settings::add_error( __( 'Debug log file not found.', 'wc-deposits-hybrid' ) );
        }
    }
}

return new WC_Deposits_Hybrid_Settings(); 