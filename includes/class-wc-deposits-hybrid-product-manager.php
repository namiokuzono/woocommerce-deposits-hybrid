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

        // Add JavaScript to handle payment plan display
        add_action( 'admin_footer', array( $this, 'add_payment_plan_script' ) );

        // Enqueue frontend template for hybrid deposit options
        add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'show_hybrid_deposit_options' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
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
        $payment_plans = array();
        if ( class_exists( 'WC_Deposits_Plans_Manager' ) ) {
            $payment_plans = WC_Deposits_Plans_Manager::get_plan_ids();
        }

        if ( ! empty( $payment_plans ) ) {
            woocommerce_wp_checkbox(
                array(
                    'id'          => '_wc_deposit_hybrid_allow_plans',
                    'label'       => __( 'Allow Payment Plans', 'wc-deposits-hybrid' ),
                    'description' => __( 'Allow customers to choose a payment plan for the remaining balance', 'wc-deposits-hybrid' ),
                )
            );

            // Multi-select dropdown for payment plans
            $selected_plans = get_post_meta( $post->ID, '_wc_deposit_hybrid_plans', true );
            if ( ! is_array( $selected_plans ) ) {
                $selected_plans = array();
            }
            echo '<p class="form-field show_if_allow_plans">';
            echo '<label for="_wc_deposit_hybrid_plans">' . __( 'Available Payment Plans', 'wc-deposits-hybrid' ) . '</label>';
            echo '<select id="_wc_deposit_hybrid_plans" name="_wc_deposit_hybrid_plans[]" multiple="multiple" style="min-width:200px;">';
            foreach ( $payment_plans as $plan_id => $plan_name ) {
                $selected = in_array( $plan_id, $selected_plans ) ? 'selected' : '';
                echo '<option value="' . esc_attr( $plan_id ) . '" ' . $selected . '>' . esc_html( $plan_name ) . '</option>';
            }
            echo '</select>';
            echo '<span class="description">' . __( 'Select which payment plans are available for this product', 'wc-deposits-hybrid' ) . '</span>';
            echo '</p>';
        } else {
            echo '<p class="form-field">';
            echo '<label>' . __( 'Payment Plans', 'wc-deposits-hybrid' ) . '</label>';
            echo '<span class="description">' . __( 'No payment plans available. Please create payment plans in WooCommerce Deposits settings.', 'wc-deposits-hybrid' ) . '</span>';
            echo '</p>';
        }

        echo '</div>';
    }

    /**
     * Add JavaScript to handle payment plan display
     */
    public function add_payment_plan_script() {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Show/hide payment plan options based on checkbox
                function togglePaymentPlanOptions() {
                    var allowPlans = $('#_wc_deposit_hybrid_allow_plans').is(':checked');
                    $('.show_if_allow_plans').toggle(allowPlans);
                }

                // Initial state
                togglePaymentPlanOptions();

                // Handle checkbox change
                $('#_wc_deposit_hybrid_allow_plans').on('change', function() {
                    togglePaymentPlanOptions();
                });

                // Handle deposit type change
                $('#_wc_deposit_type').on('change', function() {
                    var isHybrid = $(this).val() === 'hybrid';
                    $('.show_if_hybrid').toggle(isHybrid);
                    if (isHybrid) {
                        togglePaymentPlanOptions();
                    }
                });
            });
        </script>
        <?php
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

        if ( ! class_exists( 'WC_Deposits_Plans_Manager' ) ) {
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

    /**
     * Add Hybrid Deposits tab to product data tabs
     */
    public function add_hybrid_tab( $tabs ) {
        $tabs['hybrid_deposits'] = array(
            'label'    => __( 'Hybrid Deposits', 'wc-deposits-hybrid' ),
            'target'   => 'hybrid_deposits_product_data',
            'class'    => array('show_if_simple', 'show_if_variable'),
            'priority' => 80,
        );
        return $tabs;
    }

    /**
     * Output fields for Hybrid Deposits tab
     */
    public function hybrid_panel_content() {
        global $post;
        $initial_percent = get_post_meta( $post->ID, '_wc_deposit_hybrid_initial_percent', true );
        $is_nrd = get_post_meta( $post->ID, '_wc_deposit_hybrid_nrd', true );
        $allow_plans = get_post_meta( $post->ID, '_wc_deposit_hybrid_allow_plans', true );
        $selected_plans = get_post_meta( $post->ID, '_wc_deposit_hybrid_plans', true );
        if ( ! is_array( $selected_plans ) ) {
            $selected_plans = array();
        }
        $payment_plans = class_exists( 'WC_Deposits_Plans_Manager' ) ? WC_Deposits_Plans_Manager::get_plan_ids() : array();
        ?>
        <div id="hybrid_deposits_product_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <?php
                // Initial deposit percentage
                woocommerce_wp_text_input( array(
                    'id'          => '_wc_deposit_hybrid_initial_percent',
                    'label'       => __( 'Initial Deposit (%)', 'wc-deposits-hybrid' ),
                    'description' => __( 'Percentage of total price to be paid as initial deposit', 'wc-deposits-hybrid' ),
                    'type'        => 'number',
                    'custom_attributes' => array(
                        'step' => 'any',
                        'min'  => '0',
                        'max'  => '100',
                    ),
                    'value'       => $initial_percent,
                ) );

                // Non-refundable deposit option
                woocommerce_wp_checkbox( array(
                    'id'          => '_wc_deposit_hybrid_nrd',
                    'label'       => __( 'Non-Refundable Deposit', 'wc-deposits-hybrid' ),
                    'description' => __( 'Make the initial deposit non-refundable', 'wc-deposits-hybrid' ),
                    'value'       => $is_nrd,
                ) );

                // Allow payment plans
                woocommerce_wp_checkbox( array(
                    'id'          => '_wc_deposit_hybrid_allow_plans',
                    'label'       => __( 'Allow Payment Plans', 'wc-deposits-hybrid' ),
                    'description' => __( 'Allow customers to choose a payment plan for the remaining balance', 'wc-deposits-hybrid' ),
                    'value'       => $allow_plans,
                ) );

                // Payment plan multi-select
                echo '<p class="form-field show_if_allow_plans">';
                echo '<label for="_wc_deposit_hybrid_plans">' . __( 'Available Payment Plans', 'wc-deposits-hybrid' ) . '</label>';
                echo '<select id="_wc_deposit_hybrid_plans" name="_wc_deposit_hybrid_plans[]" multiple="multiple" style="min-width:200px;">';
                foreach ( $payment_plans as $plan_id => $plan_name ) {
                    $selected = in_array( $plan_id, $selected_plans ) ? 'selected' : '';
                    echo '<option value="' . esc_attr( $plan_id ) . '" ' . $selected . '>' . esc_html( $plan_name ) . '</option>';
                }
                echo '</select>';
                echo '<span class="description">' . __( 'Select which payment plans are available for this product', 'wc-deposits-hybrid' ) . '</span>';
                echo '</p>';
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Save Hybrid Deposits fields
     */
    public function save_hybrid_tab_fields( $post_id ) {
        $initial_percent = isset( $_POST['_wc_deposit_hybrid_initial_percent'] ) ? wc_clean( wp_unslash( $_POST['_wc_deposit_hybrid_initial_percent'] ) ) : '';
        $is_nrd = isset( $_POST['_wc_deposit_hybrid_nrd'] ) ? 'yes' : 'no';
        $allow_plans = isset( $_POST['_wc_deposit_hybrid_allow_plans'] ) ? 'yes' : 'no';
        $plans = isset( $_POST['_wc_deposit_hybrid_plans'] ) ? array_map( 'absint', (array) $_POST['_wc_deposit_hybrid_plans'] ) : array();

        update_post_meta( $post_id, '_wc_deposit_hybrid_initial_percent', $initial_percent );
        update_post_meta( $post_id, '_wc_deposit_hybrid_nrd', $is_nrd );
        update_post_meta( $post_id, '_wc_deposit_hybrid_allow_plans', $allow_plans );
        update_post_meta( $post_id, '_wc_deposit_hybrid_plans', $plans );
    }

    // Add hooks for the new tab
    public function add_hooks() {
        add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_hybrid_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( $this, 'hybrid_panel_content' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_hybrid_tab_fields' ) );
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
        add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 10, 2 );
        add_filter( 'wc_deposits_deposit_type', array( $this, 'filter_cart_deposit_type' ), 10, 3 );
        add_filter( 'wc_deposits_deposit_amount', array( $this, 'filter_cart_deposit_amount' ), 10, 3 );
        add_filter( 'woocommerce_order_item_hidden_meta', array( $this, 'filter_order_item_hidden_meta' ), 10, 2 );
    }

    /**
     * Enqueue frontend template for hybrid deposit options
     */
    public function add_frontend_hooks() {
        add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'show_hybrid_deposit_options' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
    }

    public function enqueue_frontend_assets() {
        if ( is_product() ) {
            wp_enqueue_style( 'wc-deposits-hybrid-frontend', WC_DEPOSITS_HYBRID_PLUGIN_URL . 'assets/css/frontend-hybrid.css', array(), WC_DEPOSITS_HYBRID_VERSION );
            wp_enqueue_script( 'wc-deposits-hybrid-frontend', WC_DEPOSITS_HYBRID_PLUGIN_URL . 'assets/js/frontend-hybrid.js', array('jquery'), WC_DEPOSITS_HYBRID_VERSION, true );
        }
    }

    public function show_hybrid_deposit_options() {
        global $product;
        // Only show for simple/variable products with hybrid settings
        $initial_percent = get_post_meta( $product->get_id(), '_wc_deposit_hybrid_initial_percent', true );
        if ( ! $initial_percent ) return;
        $allow_plans = get_post_meta( $product->get_id(), '_wc_deposit_hybrid_allow_plans', true );
        $is_nrd = get_post_meta( $product->get_id(), '_wc_deposit_hybrid_nrd', true );
        $selected_plans = get_post_meta( $product->get_id(), '_wc_deposit_hybrid_plans', true );
        if ( ! is_array( $selected_plans ) ) $selected_plans = array();
        $payment_plans = class_exists( 'WC_Deposits_Plans_Manager' ) ? WC_Deposits_Plans_Manager::get_plan_ids() : array();
        include WC_DEPOSITS_HYBRID_PLUGIN_DIR . 'templates/single-product/hybrid-deposit-options.php';
    }

    /**
     * Capture hybrid deposit option when adding to cart
     */
    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
        if ( isset( $_POST['wc_deposits_hybrid_option'] ) ) {
            $cart_item_data['wc_deposits_hybrid_option'] = sanitize_text_field( $_POST['wc_deposits_hybrid_option'] );
            if ( 'plan' === $cart_item_data['wc_deposits_hybrid_option'] && isset( $_POST['wc_deposits_hybrid_plan_id'] ) ) {
                $cart_item_data['wc_deposits_hybrid_plan_id'] = absint( $_POST['wc_deposits_hybrid_plan_id'] );
            }
        }
        return $cart_item_data;
    }

    /**
     * Display hybrid deposit option in cart/checkout
     */
    public function get_item_data( $item_data, $cart_item ) {
        if ( isset( $cart_item['wc_deposits_hybrid_option'] ) ) {
            $label = __( 'Payment Option', 'wc-deposits-hybrid' );
            $value = '';
            switch ( $cart_item['wc_deposits_hybrid_option'] ) {
                case 'full':
                    $value = __( 'Full Payment', 'wc-deposits-hybrid' );
                    break;
                case 'nrd':
                    $value = __( '20% Non-Refundable Deposit', 'wc-deposits-hybrid' );
                    break;
                case 'plan':
                    $value = __( 'Payment Plan', 'wc-deposits-hybrid' );
                    if ( isset( $cart_item['wc_deposits_hybrid_plan_id'] ) ) {
                        $plans = class_exists( 'WC_Deposits_Plans_Manager' ) ? WC_Deposits_Plans_Manager::get_plan_ids() : array();
                        $plan_id = $cart_item['wc_deposits_hybrid_plan_id'];
                        if ( isset( $plans[ $plan_id ] ) ) {
                            $value .= ': ' . esc_html( $plans[ $plan_id ] );
                        }
                    }
                    break;
            }
            $item_data[] = array( 'name' => $label, 'value' => $value );
        }
        return $item_data;
    }

    /**
     * Force deposit type and amount for NRD in cart
     */
    public function filter_cart_deposit_type( $type, $product_id, $cart_item ) {
        if ( isset( $cart_item['wc_deposits_hybrid_option'] ) && $cart_item['wc_deposits_hybrid_option'] === 'nrd' ) {
            return 'deposit';
        }
        return $type;
    }

    public function filter_cart_deposit_amount( $amount, $product_id, $cart_item ) {
        if ( isset( $cart_item['wc_deposits_hybrid_option'] ) && $cart_item['wc_deposits_hybrid_option'] === 'nrd' ) {
            $initial_percent = get_post_meta( $product_id, '_wc_deposit_hybrid_initial_percent', true );
            $product = wc_get_product( $product_id );
            $price = $product ? $product->get_price() : 0;
            if ( $initial_percent && $price ) {
                return ( $price * $initial_percent ) / 100;
            }
        }
        return $amount;
    }

    /**
     * Hide internal hybrid meta from order details/emails
     */
    public function filter_order_item_hidden_meta( $hidden, $meta_key ) {
        $internal = array('wc_deposits_hybrid_option', 'wc_deposits_hybrid_plan_id');
        if ( in_array( $meta_key, $internal, true ) ) {
            return true;
        }
        return $hidden;
    }
}

// Initialize the product manager and add hooks
$hybrid_manager = new WC_Deposits_Hybrid_Product_Manager();
$hybrid_manager->add_hooks();
$hybrid_manager->add_frontend_hooks(); 