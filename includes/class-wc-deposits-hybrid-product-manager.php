<?php
/**
 * Product Manager for Hybrid Deposits
 *
 * @package WC_Deposits_Hybrid
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * WC_Deposits_Hybrid_Product_Manager class
 */
class WC_Deposits_Hybrid_Product_Manager {
    /**
     * Constructor
     */
    public function __construct() {
        // Add hybrid deposit type
        add_filter( 'wc_deposits_deposit_types', array( $this, 'add_hybrid_deposit_type' ) );
        
        // Add product options
        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_hybrid_options' ) );
        
        // Save product options
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_hybrid_options' ) );
        
        // Modify deposit amount calculation
        add_filter( 'wc_deposits_deposit_amount', array( $this, 'calculate_hybrid_deposit_amount' ), 10, 3 );
        
        // Add payment plan options
        add_filter( 'wc_deposits_payment_plan_options', array( $this, 'add_hybrid_payment_plan_options' ), 10, 2 );

        // Add NRD option
        add_filter( 'wc_deposits_deposit_selected_type', array( $this, 'handle_deposit_selection' ), 10, 2 );
    }

    /**
     * Add hybrid deposit type
     *
     * @param array $types Existing deposit types
     * @return array
     */
    public function add_hybrid_deposit_type( $types ) {
        $types['hybrid'] = __( 'Hybrid Deposit & Plan', 'wc-deposits-hybrid' );
        return $types;
    }

    /**
     * Add hybrid options to product data panel
     */
    public function add_hybrid_options() {
        global $post;

        echo '<div class="options_group show_if_hybrid">';
        
        // Initial deposit percentage
        woocommerce_wp_text_input(
            array(
                'id'          => '_wc_deposit_hybrid_initial_percent',
                'label'       => __( 'Initial Deposit (%)', 'wc-deposits-hybrid' ),
                'description' => __( 'Percentage of total price to be paid as initial deposit', 'wc-deposits-hybrid' ),
                'type'        => 'number',
                'custom_attributes' => array(
                    'step' => 'any',
                    'min'  => '0',
                    'max'  => '100',
                ),
            )
        );

        // Non-refundable deposit option
        woocommerce_wp_checkbox(
            array(
                'id'          => '_wc_deposit_hybrid_nrd',
                'label'       => __( 'Non-Refundable Deposit', 'wc-deposits-hybrid' ),
                'description' => __( 'Make the initial deposit non-refundable', 'wc-deposits-hybrid' ),
            )
        );

        // Payment plan options
        $payment_plans = WC_Deposits_Plans_Manager::get_plan_ids();
        if ( ! empty( $payment_plans ) ) {
            woocommerce_wp_checkbox(
                array(
                    'id'          => '_wc_deposit_hybrid_allow_plans',
                    'label'       => __( 'Allow Payment Plans', 'wc-deposits-hybrid' ),
                    'description' => __( 'Allow customers to choose a payment plan for the remaining balance', 'wc-deposits-hybrid' ),
                )
            );

            woocommerce_wp_multi_checkbox(
                array(
                    'id'          => '_wc_deposit_hybrid_plans',
                    'label'       => __( 'Available Payment Plans', 'wc-deposits-hybrid' ),
                    'description' => __( 'Select which payment plans are available for this product', 'wc-deposits-hybrid' ),
                    'options'     => $payment_plans,
                )
            );
        }

        echo '</div>';
    }

    /**
     * Save hybrid options
     *
     * @param int $post_id Post ID
     */
    public function save_hybrid_options( $post_id ) {
        $initial_percent = isset( $_POST['_wc_deposit_hybrid_initial_percent'] ) ? wc_clean( wp_unslash( $_POST['_wc_deposit_hybrid_initial_percent'] ) ) : '';
        $is_nrd = isset( $_POST['_wc_deposit_hybrid_nrd'] ) ? 'yes' : 'no';
        $allow_plans = isset( $_POST['_wc_deposit_hybrid_allow_plans'] ) ? 'yes' : 'no';
        $plans = isset( $_POST['_wc_deposit_hybrid_plans'] ) ? array_map( 'absint', (array) $_POST['_wc_deposit_hybrid_plans'] ) : array();

        update_post_meta( $post_id, '_wc_deposit_hybrid_initial_percent', $initial_percent );
        update_post_meta( $post_id, '_wc_deposit_hybrid_nrd', $is_nrd );
        update_post_meta( $post_id, '_wc_deposit_hybrid_allow_plans', $allow_plans );
        update_post_meta( $post_id, '_wc_deposit_hybrid_plans', $plans );
    }

    /**
     * Calculate hybrid deposit amount
     *
     * @param float $amount Deposit amount
     * @param int   $product_id Product ID
     * @param array $args Additional arguments
     * @return float
     */
    public function calculate_hybrid_deposit_amount( $amount, $product_id, $args ) {
        if ( 'hybrid' !== WC_Deposits_Product_Manager::get_deposit_type( $product_id ) ) {
            return $amount;
        }

        $initial_percent = get_post_meta( $product_id, '_wc_deposit_hybrid_initial_percent', true );
        if ( ! $initial_percent ) {
            return $amount;
        }

        $product = wc_get_product( $product_id );
        $price = $product->get_price();
        
        return ( $price * $initial_percent ) / 100;
    }

    /**
     * Add hybrid payment plan options
     *
     * @param array $options Payment plan options
     * @param int   $product_id Product ID
     * @return array
     */
    public function add_hybrid_payment_plan_options( $options, $product_id ) {
        if ( 'hybrid' !== WC_Deposits_Product_Manager::get_deposit_type( $product_id ) ) {
            return $options;
        }

        $allow_plans = get_post_meta( $product_id, '_wc_deposit_hybrid_allow_plans', true );
        if ( 'yes' !== $allow_plans ) {
            return $options;
        }

        $available_plans = get_post_meta( $product_id, '_wc_deposit_hybrid_plans', true );
        if ( empty( $available_plans ) ) {
            return $options;
        }

        $all_plans = WC_Deposits_Plans_Manager::get_plan_ids();
        return array_intersect_key( $all_plans, array_flip( $available_plans ) );
    }

    /**
     * Handle deposit selection
     *
     * @param string $type Selected deposit type
     * @param int    $product_id Product ID
     * @return string
     */
    public function handle_deposit_selection( $type, $product_id ) {
        if ( 'hybrid' !== WC_Deposits_Product_Manager::get_deposit_type( $product_id ) ) {
            return $type;
        }

        // If NRD is enabled and no payment plan is selected, use deposit type
        $is_nrd = get_post_meta( $product_id, '_wc_deposit_hybrid_nrd', true );
        if ( 'yes' === $is_nrd && empty( $_POST['wc_deposit_payment_plan'] ) ) {
            return 'deposit';
        }

        return $type;
    }
}

// Initialize the product manager
new WC_Deposits_Hybrid_Product_Manager(); 