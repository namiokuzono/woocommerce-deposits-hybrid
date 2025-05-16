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
        // Handle hybrid payment processing
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_hybrid_payment' ), 10, 3 );
        
        // Modify order status
        add_filter( 'wc_deposits_order_status', array( $this, 'modify_order_status' ), 10, 2 );
        
        // Add payment plan scheduling
        add_action( 'wc_deposits_after_payment_complete', array( $this, 'schedule_remaining_payments' ), 10, 2 );
    }

    /**
     * Process hybrid payment
     *
     * @param int   $order_id Order ID
     * @param array $posted_data Posted data
     * @param WC_Order $order Order object
     */
    public function process_hybrid_payment( $order_id, $posted_data, $order ) {
        $has_hybrid = false;
        $payment_plan_id = 0;

        // Check if order contains hybrid deposit items
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            if ( 'hybrid' === WC_Deposits_Product_Manager::get_deposit_type( $product_id ) ) {
                $has_hybrid = true;
                if ( ! empty( $posted_data['wc_deposit_payment_plan'] ) ) {
                    $payment_plan_id = absint( $posted_data['wc_deposit_payment_plan'] );
                }
                break;
            }
        }

        if ( ! $has_hybrid ) {
            return;
        }

        // Store payment plan ID if selected
        if ( $payment_plan_id ) {
            update_post_meta( $order_id, '_wc_deposit_hybrid_payment_plan', $payment_plan_id );
        }
    }

    /**
     * Modify order status
     *
     * @param string $status Order status
     * @param WC_Order $order Order object
     * @return string
     */
    public function modify_order_status( $status, $order ) {
        $has_hybrid = false;
        $has_payment_plan = false;

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            if ( 'hybrid' === WC_Deposits_Product_Manager::get_deposit_type( $product_id ) ) {
                $has_hybrid = true;
                if ( get_post_meta( $order->get_id(), '_wc_deposit_hybrid_payment_plan', true ) ) {
                    $has_payment_plan = true;
                }
                break;
            }
        }

        if ( $has_hybrid ) {
            if ( $has_payment_plan ) {
                return 'partial-payment';
            } else {
                return 'pending-deposit';
            }
        }

        return $status;
    }

    /**
     * Schedule remaining payments
     *
     * @param int $order_id Order ID
     * @param WC_Order $order Order object
     */
    public function schedule_remaining_payments( $order_id, $order ) {
        $payment_plan_id = get_post_meta( $order_id, '_wc_deposit_hybrid_payment_plan', true );
        if ( ! $payment_plan_id ) {
            return;
        }

        $payment_plan = new WC_Deposits_Plan( $payment_plan_id );
        if ( ! $payment_plan ) {
            return;
        }

        // Get remaining balance
        $remaining_balance = $order->get_total() - $order->get_total_paid();
        if ( $remaining_balance <= 0 ) {
            return;
        }

        // Schedule payments based on plan
        $schedule = $payment_plan->get_schedule();
        $current_timestamp = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

        foreach ( $schedule as $schedule_row ) {
            // Skip first payment as it's already paid
            if ( $schedule_row === reset( $schedule ) ) {
                continue;
            }

            // Calculate payment amount
            $payment_amount = ( $remaining_balance / 100 ) * $schedule_row->amount;
            
            // Calculate payment date
            $payment_date = strtotime( "+{$schedule_row->interval_amount} {$schedule_row->interval_unit}", $current_timestamp );

            // Create scheduled payment
            $this->create_scheduled_payment( $order, $payment_amount, $payment_date );
        }
    }

    /**
     * Create scheduled payment
     *
     * @param WC_Order $parent_order Parent order
     * @param float $amount Payment amount
     * @param int $payment_date Payment date timestamp
     */
    private function create_scheduled_payment( $parent_order, $amount, $payment_date ) {
        $order = wc_create_order();
        
        // Copy customer data
        $order->set_customer_id( $parent_order->get_customer_id() );
        $order->set_billing_email( $parent_order->get_billing_email() );
        $order->set_billing_first_name( $parent_order->get_billing_first_name() );
        $order->set_billing_last_name( $parent_order->get_billing_last_name() );
        $order->set_billing_address_1( $parent_order->get_billing_address_1() );
        $order->set_billing_address_2( $parent_order->get_billing_address_2() );
        $order->set_billing_city( $parent_order->get_billing_city() );
        $order->set_billing_state( $parent_order->get_billing_state() );
        $order->set_billing_postcode( $parent_order->get_billing_postcode() );
        $order->set_billing_country( $parent_order->get_billing_country() );
        $order->set_billing_phone( $parent_order->get_billing_phone() );

        // Copy shipping data
        $order->set_shipping_first_name( $parent_order->get_shipping_first_name() );
        $order->set_shipping_last_name( $parent_order->get_shipping_last_name() );
        $order->set_shipping_address_1( $parent_order->get_shipping_address_1() );
        $order->set_shipping_address_2( $parent_order->get_shipping_address_2() );
        $order->set_shipping_city( $parent_order->get_shipping_city() );
        $order->set_shipping_state( $parent_order->get_shipping_state() );
        $order->set_shipping_postcode( $parent_order->get_shipping_postcode() );
        $order->set_shipping_country( $parent_order->get_shipping_country() );

        // Set order data
        $order->set_parent_id( $parent_order->get_id() );
        $order->set_total( $amount );
        $order->set_status( 'pending' );
        $order->set_date_created( $payment_date );
        $order->set_payment_method( $parent_order->get_payment_method() );
        $order->set_payment_method_title( $parent_order->get_payment_method_title() );

        // Add order note
        $order->add_order_note( sprintf(
            __( 'Scheduled payment for order #%s', 'wc-deposits-hybrid' ),
            $parent_order->get_order_number()
        ) );

        $order->save();
    }
}

// Initialize the order manager
new WC_Deposits_Hybrid_Order_Manager(); 