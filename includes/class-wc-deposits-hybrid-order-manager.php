<?php
/**
 * Order Manager for Hybrid Deposits
 *
 * @package WC_Deposits_Hybrid
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * WC_Deposits_Hybrid_Order_Manager class
 */
class WC_Deposits_Hybrid_Order_Manager {
    /**
     * Constructor
     */
    public function __construct() {
        // Process hybrid payments
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_hybrid_payment' ), 10, 3 );
        
        // Modify order status
        add_filter( 'woocommerce_order_status_changed', array( $this, 'modify_order_status' ), 10, 4 );
        
        // Schedule remaining payments
        add_action( 'woocommerce_payment_complete', array( $this, 'schedule_remaining_payments' ) );

        // Handle NRD orders
        add_action( 'woocommerce_order_status_changed', array( $this, 'handle_nrd_order_status' ), 10, 4 );

        // Set up deposit/payment plan based on hybrid selection at order creation
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_hybrid_meta_to_order_item' ), 10, 4 );
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'set_hybrid_order_payment_type' ), 20, 3 );
    }

    /**
     * Process hybrid payment
     *
     * @param int    $order_id Order ID
     * @param array  $posted_data Posted data
     * @param object $order Order object
     */
    public function process_hybrid_payment( $order_id, $posted_data, $order ) {
        $has_hybrid = false;
        $selected_plan = null;

        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( ! $product ) {
                continue;
            }

            if ( 'hybrid' === WC_Deposits_Product_Manager::get_deposit_type( $product->get_id() ) ) {
                $has_hybrid = true;
                if ( ! empty( $posted_data['wc_deposit_payment_plan'] ) ) {
                    $selected_plan = $posted_data['wc_deposit_payment_plan'];
                }
                break;
            }
        }

        if ( $has_hybrid ) {
            update_post_meta( $order_id, '_wc_deposit_hybrid_order', 'yes' );
            if ( $selected_plan ) {
                update_post_meta( $order_id, '_wc_deposit_hybrid_plan', $selected_plan );
            }
        }
    }

    /**
     * Modify order status
     *
     * @param int    $order_id Order ID
     * @param string $old_status Old status
     * @param string $new_status New status
     * @param object $order Order object
     */
    public function modify_order_status( $order_id, $old_status, $new_status, $order ) {
        if ( 'yes' !== get_post_meta( $order_id, '_wc_deposit_hybrid_order', true ) ) {
            return;
        }

        // If this is a payment plan order, ensure it stays in processing
        if ( get_post_meta( $order_id, '_wc_deposit_hybrid_plan', true ) ) {
            if ( 'processing' !== $new_status ) {
                $order->update_status( 'processing' );
            }
        }
    }

    /**
     * Schedule remaining payments
     *
     * @param int $order_id Order ID
     */
    public function schedule_remaining_payments( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || 'yes' !== get_post_meta( $order_id, '_wc_deposit_hybrid_order', true ) ) {
            return;
        }

        $plan_id = get_post_meta( $order_id, '_wc_deposit_hybrid_plan', true );
        if ( ! $plan_id ) {
            return;
        }

        $plan = WC_Deposits_Plans_Manager::get_plan( $plan_id );
        if ( ! $plan ) {
            return;
        }

        $remaining_amount = $order->get_total() - $order->get_deposit_paid();
        $schedule = $plan->get_schedule();
        
        foreach ( $schedule as $payment ) {
            $this->create_scheduled_payment( $order, $payment['amount'], $payment['date'] );
        }
    }

    /**
     * Create scheduled payment
     *
     * @param object $order Parent order
     * @param float  $amount Payment amount
     * @param string $date Payment date
     */
    private function create_scheduled_payment( $order, $amount, $date ) {
        $payment = new WC_Order();
        $payment->set_parent_id( $order->get_id() );
        $payment->set_customer_id( $order->get_customer_id() );
        $payment->set_total( $amount );
        $payment->set_date_created( $date );
        $payment->set_status( 'pending' );
        $payment->save();
    }

    /**
     * Handle NRD order status
     *
     * @param int    $order_id Order ID
     * @param string $old_status Old status
     * @param string $new_status New status
     * @param object $order Order object
     */
    public function handle_nrd_order_status( $order_id, $old_status, $new_status, $order ) {
        if ( 'yes' !== get_post_meta( $order_id, '_wc_deposit_hybrid_order', true ) ) {
            return;
        }

        // Check if this is an NRD order (no payment plan selected)
        if ( ! get_post_meta( $order_id, '_wc_deposit_hybrid_plan', true ) ) {
            // If order is being cancelled, check if deposit is non-refundable
            if ( 'cancelled' === $new_status ) {
                $is_nrd = false;
                foreach ( $order->get_items() as $item ) {
                    $product = $item->get_product();
                    if ( $product && 'yes' === get_post_meta( $product->get_id(), '_wc_deposit_hybrid_nrd', true ) ) {
                        $is_nrd = true;
                        break;
                    }
                }

                if ( $is_nrd ) {
                    // Add a note about non-refundable deposit
                    $order->add_order_note( __( 'Note: The initial deposit is non-refundable.', 'wc-deposits-hybrid' ) );
                }
            }
        }
    }

    /**
     * Set up deposit/payment plan based on hybrid selection at order creation
     */
    public function set_hybrid_order_payment_type( $order_id, $posted_data, $order ) {
        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            if ( ! $product ) continue;
            $option = $item->get_meta( 'wc_deposits_hybrid_option', true );
            $plan_id = $item->get_meta( 'wc_deposits_hybrid_plan_id', true );
            if ( ! $option ) continue;

            // Store on order for later reference
            update_post_meta( $order_id, '_wc_deposits_hybrid_order', 'yes' );
            update_post_meta( $order_id, '_wc_deposits_hybrid_option', $option );
            if ( $plan_id ) {
                update_post_meta( $order_id, '_wc_deposits_hybrid_plan', $plan_id );
            }

            // Handle logic
            switch ( $option ) {
                case 'full':
                    // No deposit, pay in full
                    update_post_meta( $order_id, '_wc_deposit_full_payment', 'yes' );
                    break;
                case 'nrd':
                    // Set as deposit, no payment plan
                    update_post_meta( $order_id, '_wc_deposit_nrd', 'yes' );
                    break;
                case 'plan':
                    // Set as deposit with payment plan
                    if ( $plan_id ) {
                        update_post_meta( $order_id, '_wc_deposit_plan', $plan_id );
                    }
                    break;
            }
        }
    }

    /**
     * Add hybrid meta to order item
     */
    public function add_hybrid_meta_to_order_item( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['wc_deposits_hybrid_option'] ) ) {
            $item->add_meta_data( 'wc_deposits_hybrid_option', $values['wc_deposits_hybrid_option'], true );
        }
        if ( isset( $values['wc_deposits_hybrid_plan_id'] ) ) {
            $item->add_meta_data( 'wc_deposits_hybrid_plan_id', $values['wc_deposits_hybrid_plan_id'], true );
        }
    }
}

// Initialize the order manager
new WC_Deposits_Hybrid_Order_Manager(); 