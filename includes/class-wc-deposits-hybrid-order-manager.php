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

        // After order is processed, enforce NRD and Payment Plan logic
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'enforce_hybrid_payment_logic' ), 30, 1 );

        // Add manual invoice action for NRD orders
        add_action( 'woocommerce_order_actions', array( $this, 'add_manual_invoice_action' ) );
        add_action( 'woocommerce_order_action_invoice_remaining_balance', array( $this, 'process_manual_invoice' ) );
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

    /**
     * After order is processed, enforce NRD and Payment Plan logic
     */
    public function enforce_hybrid_payment_logic( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $option = get_post_meta( $order_id, '_wc_deposits_hybrid_option', true );
        $plan_id = get_post_meta( $order_id, '_wc_deposits_hybrid_plan', true );

        // Calculate deposit amount for each line item
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( ! $product ) continue;

            $price = $item->get_total();
            $initial_percent = get_post_meta( $product->get_id(), '_wc_deposit_hybrid_initial_percent', true );
            if ( ! $initial_percent ) continue;

            $deposit_amount = ( $price * $initial_percent ) / 100;
            $remaining_amount = $price - $deposit_amount;

            switch ( $option ) {
                case 'full':
                    // Full Payment: No deposit, pay in full
                    delete_post_meta( $order_id, '_wc_deposit_plan' );
                    delete_post_meta( $order_id, '_wc_deposit_nrd' );
                    delete_post_meta( $order_id, '_wc_deposit_full_payment' );
                    
                    // Set order status to processing
                    if ( $order->get_status() !== 'processing' ) {
                        $order->update_status( 'processing' );
                    }
                    break;

                case 'nrd':
                    // Non-Refundable Deposit: Only deposit, no payment plan
                    delete_post_meta( $order_id, '_wc_deposit_plan' );
                    delete_post_meta( $order_id, '_wc_deposit_full_payment' );
                    update_post_meta( $order_id, '_wc_deposit_nrd', 'yes' );
                    update_post_meta( $order_id, '_wc_deposit_amount', $deposit_amount );
                    update_post_meta( $order_id, '_wc_deposit_remaining_payable', $remaining_amount );
                    
                    // Set order status to partially-paid
                    if ( $order->get_status() !== 'partially-paid' ) {
                        $order->update_status( 'partially-paid' );
                    }

                    // Add a note about the non-refundable deposit
                    $order->add_order_note( sprintf(
                        __( 'Order placed with non-refundable deposit of %s. Remaining balance of %s will be invoiced when the item is ready to ship.', 'wc-deposits-hybrid' ),
                        wc_price( $deposit_amount ),
                        wc_price( $remaining_amount )
                    ) );
                    break;

                case 'plan':
                    // Payment Plan: Deposit with scheduled payments
                    if ( $plan_id ) {
                        delete_post_meta( $order_id, '_wc_deposit_nrd' );
                        delete_post_meta( $order_id, '_wc_deposit_full_payment' );
                        update_post_meta( $order_id, '_wc_deposit_plan', $plan_id );
                        update_post_meta( $order_id, '_wc_deposit_amount', $deposit_amount );
                        update_post_meta( $order_id, '_wc_deposit_remaining_payable', $remaining_amount );

                        // Get the plan details
                        $plan = WC_Deposits_Plans_Manager::get_plan( $plan_id );
                        if ( $plan ) {
                            // Schedule the remaining payments
                            $schedule = $plan->get_schedule();
                            $total_scheduled = 0;
                            
                            foreach ( $schedule as $payment ) {
                                $payment_amount = ( $remaining_amount * $payment['amount'] ) / 100;
                                $total_scheduled += $payment_amount;
                                $this->create_scheduled_payment( $order, $payment_amount, $payment['date'] );
                            }

                            // Add a note about the payment plan
                            $order->add_order_note( sprintf(
                                __( 'Order placed with payment plan: %s. Initial deposit of %s paid, remaining balance of %s scheduled for automatic payments.', 'wc-deposits-hybrid' ),
                                $plan->get_name(),
                                wc_price( $deposit_amount ),
                                wc_price( $remaining_amount )
                            ) );
                        }
                    }
                    break;
            }
        }
    }

    /**
     * Add manual invoice action for NRD orders
     *
     * @param array $actions Order actions
     * @return array
     */
    public function add_manual_invoice_action( $actions ) {
        global $theorder;

        if ( ! $theorder ) {
            return $actions;
        }

        // Check if this is an NRD order
        if ( 'yes' === get_post_meta( $theorder->get_id(), '_wc_deposit_nrd', true ) ) {
            $actions['invoice_remaining_balance'] = __( 'Invoice Remaining Balance', 'wc-deposits-hybrid' );
        }

        return $actions;
    }

    /**
     * Process manual invoice for NRD orders
     *
     * @param WC_Order $order Order object
     */
    public function process_manual_invoice( $order ) {
        if ( ! $order || 'yes' !== get_post_meta( $order->get_id(), '_wc_deposit_nrd', true ) ) {
            return;
        }

        // Get the remaining balance from meta
        $remaining_amount = get_post_meta( $order->get_id(), '_wc_deposit_remaining_payable', true );
        if ( ! $remaining_amount || $remaining_amount <= 0 ) {
            // Fallback to calculating remaining amount
            $remaining_amount = $order->get_total() - $order->get_deposit_paid();
            if ( $remaining_amount <= 0 ) {
                return;
            }
        }

        // Create a new order for the remaining balance
        $invoice = new WC_Order();
        $invoice->set_parent_id( $order->get_id() );
        $invoice->set_customer_id( $order->get_customer_id() );
        $invoice->set_total( $remaining_amount );
        $invoice->set_status( 'pending' );
        $invoice->set_payment_method( $order->get_payment_method() );
        $invoice->set_payment_method_title( $order->get_payment_method_title() );
        $invoice->set_billing_email( $order->get_billing_email() );
        $invoice->set_billing_first_name( $order->get_billing_first_name() );
        $invoice->set_billing_last_name( $order->get_billing_last_name() );
        $invoice->set_billing_address_1( $order->get_billing_address_1() );
        $invoice->set_billing_address_2( $order->get_billing_address_2() );
        $invoice->set_billing_city( $order->get_billing_city() );
        $invoice->set_billing_state( $order->get_billing_state() );
        $invoice->set_billing_postcode( $order->get_billing_postcode() );
        $invoice->set_billing_country( $order->get_billing_country() );
        $invoice->set_billing_phone( $order->get_billing_phone() );
        $invoice->set_shipping_first_name( $order->get_shipping_first_name() );
        $invoice->set_shipping_last_name( $order->get_shipping_last_name() );
        $invoice->set_shipping_address_1( $order->get_shipping_address_1() );
        $invoice->set_shipping_address_2( $order->get_shipping_address_2() );
        $invoice->set_shipping_city( $order->get_shipping_city() );
        $invoice->set_shipping_state( $order->get_shipping_state() );
        $invoice->set_shipping_postcode( $order->get_shipping_postcode() );
        $invoice->set_shipping_country( $order->get_shipping_country() );

        // Copy line items from original order
        foreach ( $order->get_items() as $item ) {
            $new_item = new WC_Order_Item_Product();
            $new_item->set_props( array(
                'name'         => $item->get_name(),
                'product_id'   => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'quantity'     => $item->get_quantity(),
                'tax_class'    => $item->get_tax_class(),
                'subtotal'     => $item->get_subtotal(),
                'total'        => $item->get_total(),
            ) );
            $invoice->add_item( $new_item );
        }

        // Add a note to the invoice
        $invoice->add_order_note( sprintf(
            __( 'Invoice created for remaining balance of %s from order #%s', 'wc-deposits-hybrid' ),
            wc_price( $remaining_amount ),
            $order->get_order_number()
        ) );

        // Save the invoice
        $invoice->save();

        // Add a note to the original order
        $order->add_order_note( sprintf(
            __( 'Invoice #%s created for remaining balance of %s', 'wc-deposits-hybrid' ),
            $invoice->get_order_number(),
            wc_price( $remaining_amount )
        ) );

        // Send the invoice email
        do_action( 'woocommerce_order_status_pending_to_processing_notification', $invoice->get_id() );
    }
}

// Initialize the order manager
new WC_Deposits_Hybrid_Order_Manager(); 