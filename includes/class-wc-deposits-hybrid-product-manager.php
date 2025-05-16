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
     * Debug mode
     *
     * @var bool
     */
    private $debug_mode = false;

    /**
     * Debug log file
     *
     * @var string
     */
    private $debug_log_file;

    /**
     * Constructor
     */
    public function __construct() {
        wc_deposits_hybrid_log( 'Product Manager constructor called' );

        // Initialize debug settings
        $this->init_debug_settings();

        // Add hybrid deposit type
        add_filter( 'wc_deposits_deposit_types', array( $this, 'add_hybrid_deposit_type' ) );
        
        // Add product options
        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_hybrid_options' ) );
        
        // Show hybrid options panel
        add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_hybrid_product_data_tab' ) );
        
        // Save product options
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_hybrid_options' ) );
        
        // Modify deposit amount calculation
        add_filter( 'wc_deposits_deposit_amount', array( $this, 'calculate_hybrid_deposit_amount' ), 10, 3 );
        
        // Add payment plan options
        add_filter( 'wc_deposits_payment_plan_options', array( $this, 'add_hybrid_payment_plan_options' ), 10, 2 );

        // Handle deposit selection
        add_filter( 'wc_deposits_deposit_selected_type', array( $this, 'handle_deposit_selection' ), 10, 2 );

        // Add JavaScript to handle payment plan display
        add_action( 'admin_footer', array( $this, 'add_payment_plan_script' ) );

        // Enqueue frontend template for hybrid deposit options
        add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'show_hybrid_deposit_options' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

        // Hide default WooCommerce Deposits UI
        add_action( 'init', array( $this, 'hide_default_deposits_ui' ) );

        // Handle cart item data
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
        add_filter( 'wc_deposits_enabled_for_cart_item', array( $this, 'deposits_enabled_for_cart_item' ), 10, 3 );
        add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 10, 2 );

        // Add AJAX handlers
        add_action( 'wp_ajax_wc_deposits_hybrid_update_cart_item', array( $this, 'ajax_update_cart_item' ) );
        add_action( 'wp_ajax_nopriv_wc_deposits_hybrid_update_cart_item', array( $this, 'ajax_update_cart_item' ) );

        wc_deposits_hybrid_log( 'Product Manager hooks registered' );
    }

    /**
     * Initialize debug settings
     */
    private function init_debug_settings() {
        $this->debug_mode = get_option( 'wc_deposits_hybrid_debug_mode', 'no' ) === 'yes';
        $this->debug_log_file = WC_DEPOSITS_HYBRID_PLUGIN_DIR . 'debug.log';
        
        if ( $this->debug_mode ) {
            $this->log_debug( 'Product Manager initialized', 'info' );
        }
    }

    /**
     * Log debug message
     *
     * @param string $message
     * @param string $level
     */
    private function log_debug( $message, $level = 'info' ) {
        if ( ! $this->debug_mode ) {
            return;
        }

        $timestamp = current_time( 'mysql' );
        $log_message = sprintf( "[%s] [%s] %s\n", $timestamp, strtoupper( $level ), $message );

        if ( is_writable( dirname( $this->debug_log_file ) ) ) {
            error_log( $log_message, 3, $this->debug_log_file );
        }
    }

    /**
     * Add hybrid deposit type
     *
     * @param array $types Existing deposit types
     * @return array
     */
    public function add_hybrid_deposit_type( $types ) {
        wc_deposits_hybrid_log( 'Adding hybrid deposit type' );
        $types['hybrid'] = __( 'Hybrid Deposit & Plan', 'wc-deposits-hybrid' );
        return $types;
    }

    /**
     * Add hybrid product data tab
     *
     * @param array $tabs Product data tabs
     * @return array
     */
    public function add_hybrid_product_data_tab( $tabs ) {
        wc_deposits_hybrid_log( 'Adding hybrid product data tab' );
        $tabs['hybrid_deposit'] = array(
            'label'    => __( 'Hybrid Deposit', 'wc-deposits-hybrid' ),
            'target'   => 'hybrid_deposit_product_data',
            'class'    => array( 'show_if_hybrid' ),
            'priority' => 21,
        );
        return $tabs;
    }

    /**
     * Add hybrid options to product data panel
     */
    public function add_hybrid_options() {
        global $post;

        echo '<div class="options_group show_if_hybrid">';
        
        // Debug mode toggle
        woocommerce_wp_checkbox(
            array(
                'id'          => '_wc_deposit_hybrid_debug',
                'label'       => __( 'Debug Mode', 'wc-deposits-hybrid' ),
                'description' => __( 'Enable debug logging for this product', 'wc-deposits-hybrid' ),
            )
        );

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

        $this->log_debug( 'Added hybrid options to product panel', 'info' );
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

                // Show/hide hybrid options based on deposit type
                function toggleHybridOptions() {
                    var depositType = $('#_wc_deposit_type').val();
                    var isHybrid = depositType === 'hybrid';
                    $('.show_if_hybrid').toggle(isHybrid);
                    if (isHybrid) {
                        togglePaymentPlanOptions();
                    }
                }

                // Initial state
                toggleHybridOptions();
                togglePaymentPlanOptions();

                // Handle checkbox change
                $('#_wc_deposit_hybrid_allow_plans').on('change', function() {
                    togglePaymentPlanOptions();
                });

                // Handle deposit type change
                $('#_wc_deposit_type').on('change', function() {
                    toggleHybridOptions();
                });

                // Show hybrid tab when hybrid type is selected
                $('a[href="#hybrid_deposit_product_data"]').on('click', function(e) {
                    e.preventDefault();
                    $('.product_data_tabs a[href="#hybrid_deposit_product_data"]').trigger('click');
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
        $debug = isset( $_POST['_wc_deposit_hybrid_debug'] ) ? 'yes' : 'no';

        update_post_meta( $post_id, '_wc_deposit_hybrid_initial_percent', $initial_percent );
        update_post_meta( $post_id, '_wc_deposit_hybrid_nrd', $is_nrd );
        update_post_meta( $post_id, '_wc_deposit_hybrid_allow_plans', $allow_plans );
        update_post_meta( $post_id, '_wc_deposit_hybrid_plans', $plans );
        update_post_meta( $post_id, '_wc_deposit_hybrid_debug', $debug );

        $this->log_debug( sprintf(
            'Product %d settings saved: initial_percent=%s, is_nrd=%s, allow_plans=%s, plans=%s, debug=%s',
            $post_id,
            $initial_percent,
            $is_nrd,
            $allow_plans,
            implode( ',', $plans ),
            $debug
        ) );
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
        $this->log_debug( 'Calculating hybrid deposit amount for product ' . $product_id );

        // Check if hybrid deposits are enabled for this product
        $deposit_type = get_post_meta( $product_id, '_wc_deposit_type', true );
        if ( $deposit_type !== 'hybrid' ) {
            return $amount;
        }

        // Get the cart item
        $cart_item = isset( $args['cart_item'] ) ? $args['cart_item'] : null;
        if ( ! $cart_item || ! isset( $cart_item['hybrid_deposit_type'] ) ) {
            return $amount;
        }

        // Get the product
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return $amount;
        }

        // Get the product price
        $price = $product->get_price();
        if ( ! $price ) {
            return $amount;
        }

        // Get the initial deposit percentage
        $initial_percent = isset( $cart_item['hybrid_initial_percent'] ) ? $cart_item['hybrid_initial_percent'] : get_post_meta( $product_id, '_wc_deposit_hybrid_initial_percent', true );
        if ( ! $initial_percent ) {
            return $amount;
        }

        // Calculate the deposit amount based on the selected option
        switch ( $cart_item['hybrid_deposit_type'] ) {
            case 'nrd':
            case 'plan':
                $deposit_amount = ( $price * $initial_percent ) / 100;
                $this->log_debug( sprintf( 'Calculated deposit amount: %s (%.2f%% of %s)', $deposit_amount, $initial_percent, $price ) );
                return $deposit_amount;

            case 'full':
            default:
                return $amount;
        }
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
     * Hide default WooCommerce Deposits UI
     */
    public function hide_default_deposits_ui() {
        if ( function_exists( 'WC_Deposits_Product_Manager' ) ) {
            remove_action( 'woocommerce_before_add_to_cart_button', array( WC_Deposits_Product_Manager::class, 'deposit_form' ) );
        }
    }

    /**
     * Handle deposit selection
     *
     * @param string $type Selected deposit type
     * @param int    $product_id Product ID
     * @return string
     */
    public function handle_deposit_selection( $type, $product_id ) {
        $this->log_debug( 'Handling deposit selection for product ' . $product_id );

        // Check if hybrid deposits are enabled for this product
        $deposit_type = get_post_meta( $product_id, '_wc_deposit_type', true );
        if ( $deposit_type !== 'hybrid' ) {
            return $type;
        }

        // Get the cart item
        $cart_item = WC()->cart ? WC()->cart->get_cart_item( $product_id ) : null;
        if ( ! $cart_item || ! isset( $cart_item['hybrid_deposit_type'] ) ) {
            return $type;
        }

        // Map hybrid deposit types to WooCommerce Deposits types
        switch ( $cart_item['hybrid_deposit_type'] ) {
            case 'nrd':
                return 'deposit';
            case 'plan':
                return 'payment_plan';
            case 'full':
            default:
                return 'full';
        }
    }

    /**
     * Add cart item data
     *
     * @param array $cart_item_data Cart item data
     * @param int   $product_id Product ID
     * @param int   $variation_id Variation ID
     * @return array
     */
    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
        $this->log_debug( 'Adding cart item data for product ' . $product_id );

        // Check if hybrid deposits are enabled for this product
        $deposit_type = get_post_meta( $product_id, '_wc_deposit_type', true );
        if ( $deposit_type !== 'hybrid' ) {
            return $cart_item_data;
        }

        // Get the selected deposit option
        $selected_option = isset( $_POST['wc_deposits_hybrid_option'] ) ? sanitize_text_field( $_POST['wc_deposits_hybrid_option'] ) : 'full';
        
        // Only proceed if a deposit option was selected
        if ( $selected_option !== 'full' ) {
            $cart_item_data['hybrid_deposit_type'] = $selected_option;
            
            // If payment plan was selected, store the plan ID
            if ( $selected_option === 'plan' && isset( $_POST['wc_deposits_hybrid_plan_id'] ) ) {
                $plan_id = absint( $_POST['wc_deposits_hybrid_plan_id'] );
                if ( $plan_id > 0 ) {
                    $cart_item_data['hybrid_plan_id'] = $plan_id;
                }
            }

            // Store the initial deposit percentage
            $initial_percent = get_post_meta( $product_id, '_wc_deposit_hybrid_initial_percent', true );
            if ( $initial_percent ) {
                $cart_item_data['hybrid_initial_percent'] = $initial_percent;
            }

            // Store whether this is a non-refundable deposit
            $is_nrd = get_post_meta( $product_id, '_wc_deposit_hybrid_nrd', true );
            if ( $is_nrd === 'yes' ) {
                $cart_item_data['hybrid_is_nrd'] = true;
            }

            $this->log_debug( 'Added hybrid deposit data to cart item: ' . print_r( $cart_item_data, true ) );
        }

        return $cart_item_data;
    }

    /**
     * Enable deposits for cart item
     *
     * @param bool  $enabled Whether deposits are enabled
     * @param int   $product_id Product ID
     * @param array $cart_item Cart item data
     * @return bool
     */
    public function deposits_enabled_for_cart_item( $enabled, $product_id, $cart_item ) {
        if ( isset( $cart_item['_wc_deposit_enabled'] ) && $cart_item['_wc_deposit_enabled'] === 'yes' ) {
            error_log('[HYBRID DEBUG] deposits_enabled_for_cart_item: Forcing enabled for product ' . $product_id);
            return true;
        }
        error_log('[HYBRID DEBUG] deposits_enabled_for_cart_item: Not forcing for product ' . $product_id);
        return $enabled;
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
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if ( is_product() ) {
            wp_enqueue_style(
                'wc-deposits-hybrid',
                WC_DEPOSITS_HYBRID_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                WC_DEPOSITS_HYBRID_VERSION
            );

            wp_enqueue_script(
                'wc-deposits-hybrid',
                WC_DEPOSITS_HYBRID_PLUGIN_URL . 'assets/js/frontend.js',
                array( 'jquery' ),
                WC_DEPOSITS_HYBRID_VERSION,
                true
            );

            wp_localize_script(
                'wc-deposits-hybrid',
                'wc_deposits_hybrid_params',
                array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'wc-deposits-hybrid' ),
                )
            );
        }
    }

    /**
     * Show hybrid deposit options
     */
    public function show_hybrid_deposit_options() {
        global $product;

        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return;
        }

        // Check if hybrid deposits are enabled for this product
        $deposit_type = get_post_meta( $product->get_id(), '_wc_deposit_type', true );
        if ( $deposit_type !== 'hybrid' ) {
            return;
        }

        // Get product settings
        $initial_percent = get_post_meta( $product->get_id(), '_wc_deposit_hybrid_initial_percent', true );
        $is_nrd = get_post_meta( $product->get_id(), '_wc_deposit_hybrid_nrd', true );
        $allow_plans = get_post_meta( $product->get_id(), '_wc_deposit_hybrid_allow_plans', true );
        $selected_plans = get_post_meta( $product->get_id(), '_wc_deposit_hybrid_plans', true );

        // Get available payment plans
        $payment_plans = array();
        if ( class_exists( 'WC_Deposits_Plans_Manager' ) ) {
            $payment_plans = WC_Deposits_Plans_Manager::get_plan_ids();
        }

        // Load the template
        wc_get_template(
            'single-product/hybrid-deposit-options.php',
            array(
                'product' => $product,
                'initial_percent' => $initial_percent,
                'is_nrd' => $is_nrd,
                'allow_plans' => $allow_plans,
                'selected_plans' => $selected_plans,
                'payment_plans' => $payment_plans,
            ),
            '',
            WC_DEPOSITS_HYBRID_PLUGIN_DIR . 'templates/'
        );
    }

    /**
     * AJAX handler for cart item updates
     */
    public function ajax_update_cart_item() {
        check_ajax_referer( 'wc-deposits-hybrid', 'nonce' );

        $option = isset( $_POST['option'] ) ? sanitize_text_field( wp_unslash( $_POST['option'] ) ) : '';
        $plan_id = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;

        if ( ! $option ) {
            wp_send_json_error( 'Invalid option' );
            return;
        }

        // Get the cart item key
        $cart_item_key = WC()->cart->get_cart_item_key( get_the_ID(), 0 );
        if ( ! $cart_item_key ) {
            wp_send_json_error( 'Cart item not found' );
            return;
        }

        // Update the cart item data
        $cart_item = WC()->cart->get_cart_item( $cart_item_key );
        if ( $cart_item ) {
            $cart_item['hybrid_deposit_type'] = $option;
            if ( $option === 'plan' && $plan_id > 0 ) {
                $cart_item['hybrid_plan_id'] = $plan_id;
            }
            WC()->cart->cart_contents[ $cart_item_key ] = $cart_item;
            WC()->cart->set_session();
        }

        wp_send_json_success();
    }
}

// Initialize the product manager
new WC_Deposits_Hybrid_Product_Manager(); 