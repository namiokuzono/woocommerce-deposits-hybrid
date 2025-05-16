<?php
/**
 * Frontend template for deposit options
 *
 * @package WC_Deposits_Hybrid
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

$price = $product->get_price();
$deposit_amount = ( $price * $initial_percent ) / 100;
$remaining_amount = $price - $deposit_amount;
?>

<div class="wc-deposits-hybrid-options">
    <h4><?php esc_html_e( 'Payment Options', 'wc-deposits-hybrid' ); ?></h4>
    
    <div class="payment-option">
        <input type="radio" name="wc_deposits_hybrid_option" id="full_payment" value="full" checked>
        <label for="full_payment">
            <strong><?php esc_html_e( 'Full Payment', 'wc-deposits-hybrid' ); ?></strong>
            <span class="price"><?php echo wc_price( $price ); ?></span>
            <span class="description"><?php esc_html_e( 'Pay the full amount now', 'wc-deposits-hybrid' ); ?></span>
        </label>
    </div>

    <div class="payment-option">
        <input type="radio" name="wc_deposits_hybrid_option" id="nrd_payment" value="nrd">
        <label for="nrd_payment">
            <strong><?php esc_html_e( 'Non-Refundable Deposit', 'wc-deposits-hybrid' ); ?></strong>
            <span class="price"><?php echo wc_price( $deposit_amount ); ?></span>
            <span class="description">
                <?php 
                printf(
                    esc_html__( 'Pay %s now (%s%%) and the remaining %s later', 'wc-deposits-hybrid' ),
                    wc_price( $deposit_amount ),
                    $initial_percent,
                    wc_price( $remaining_amount )
                );
                ?>
            </span>
        </label>
    </div>

    <?php if ( 'yes' === $allow_plans && ! empty( $payment_plans ) ) : ?>
        <div class="payment-option">
            <input type="radio" name="wc_deposits_hybrid_option" id="plan_payment" value="plan">
            <label for="plan_payment">
                <strong><?php esc_html_e( 'Payment Plan', 'wc-deposits-hybrid' ); ?></strong>
                <span class="price"><?php echo wc_price( $deposit_amount ); ?></span>
                <span class="description">
                    <?php 
                    printf(
                        esc_html__( 'Pay %s now (%s%%) and the remaining %s in installments', 'wc-deposits-hybrid' ),
                        wc_price( $deposit_amount ),
                        $initial_percent,
                        wc_price( $remaining_amount )
                    );
                    ?>
                </span>
            </label>

            <div class="payment-plan-options" style="display: none;">
                <select name="wc_deposits_hybrid_plan_id" id="wc_deposits_hybrid_plan_id">
                    <?php
                    foreach ( $payment_plans as $plan_id => $plan_name ) {
                        if ( in_array( $plan_id, $selected_plans ) ) {
                            echo '<option value="' . esc_attr( $plan_id ) . '">' . esc_html( $plan_name ) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
        </div>
    <?php endif; ?>
</div> 