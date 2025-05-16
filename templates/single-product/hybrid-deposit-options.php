<?php
// Template for Hybrid Deposit Options on the product page
if ( ! defined( 'ABSPATH' ) ) exit;

$has_nrd = ( $initial_percent && $is_nrd === 'yes' );
$has_plans = ( $allow_plans === 'yes' && ! empty( $selected_plans ) );
if ( ! $has_nrd && ! $has_plans ) return;

$product_price = $product->get_price();
$deposit_amount = $initial_percent ? ( $product_price * $initial_percent ) / 100 : 0;
$balance_amount = $product_price - $deposit_amount;
?>
<div class="wc-deposits-wrapper wc-deposits-hybrid-wrapper">
    <div class="wc-deposits-options wc-deposits-hybrid-options">
        <h3><?php esc_html_e( 'Payment Options', 'wc-deposits-hybrid' ); ?></h3>
        <div class="wc-deposits-options-list">
            <div class="wc-deposits-option">
                <input type="radio" name="wc_deposits_hybrid_option" id="wc_deposits_hybrid_full" value="full" checked />
                <label for="wc_deposits_hybrid_full" class="wc-deposits-option-label">
                    <span class="wc-deposits-option-title"><?php esc_html_e( 'Full Payment', 'wc-deposits-hybrid' ); ?></span>
                    <span class="wc-deposits-option-desc"><?php echo wc_price( $product_price ); ?> <?php esc_html_e( 'upfront', 'wc-deposits-hybrid' ); ?></span>
                </label>
            </div>

            <?php if ( $has_nrd ) : ?>
            <div class="wc-deposits-option">
                <input type="radio" name="wc_deposits_hybrid_option" id="wc_deposits_hybrid_nrd" value="nrd" />
                <label for="wc_deposits_hybrid_nrd" class="wc-deposits-option-label">
                    <span class="wc-deposits-option-title"><?php printf( esc_html__( '%s%% Non-Refundable Deposit', 'wc-deposits-hybrid' ), esc_html( $initial_percent ) ); ?></span>
                    <span class="wc-deposits-option-desc">
                        <?php printf( esc_html__( 'Pay %s now, %s later', 'wc-deposits-hybrid' ), wc_price( $deposit_amount ), wc_price( $balance_amount ) ); ?>
                    </span>
                </label>
            </div>
            <?php endif; ?>

            <?php if ( $has_plans ) : ?>
            <div class="wc-deposits-option">
                <input type="radio" name="wc_deposits_hybrid_option" id="wc_deposits_hybrid_plan" value="plan" />
                <label for="wc_deposits_hybrid_plan" class="wc-deposits-option-label">
                    <span class="wc-deposits-option-title"><?php esc_html_e( 'Payment Plan', 'wc-deposits-hybrid' ); ?></span>
                    <span class="wc-deposits-option-desc">
                        <?php printf( esc_html__( 'Pay %s now, balance over time', 'wc-deposits-hybrid' ), wc_price( $deposit_amount ) ); ?>
                    </span>
                </label>
                <div class="wc-deposits-hybrid-plan-select" style="display:none; margin-top:8px; margin-left:25px;">
                    <label for="wc_deposits_hybrid_plan_id"><?php esc_html_e( 'Choose a plan:', 'wc-deposits-hybrid' ); ?></label>
                    <select name="wc_deposits_hybrid_plan_id" id="wc_deposits_hybrid_plan_id" class="wc-deposits-select">
                        <?php foreach ( $selected_plans as $plan_id ) :
                            if ( isset( $payment_plans[ $plan_id ] ) ) : ?>
                            <option value="<?php echo esc_attr( $plan_id ); ?>"><?php echo esc_html( $payment_plans[ $plan_id ] ); ?></option>
                        <?php endif; endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.wc-deposits-wrapper {
    margin: 1em 0;
    padding: 1em;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.wc-deposits-options h3 {
    margin: 0 0 1em;
    padding: 0;
    font-size: 1.1em;
}
.wc-deposits-option {
    margin: 0.5em 0;
    padding: 0.5em;
    border: 1px solid #eee;
    border-radius: 3px;
}
.wc-deposits-option:hover {
    background: #f8f8f8;
}
.wc-deposits-option-label {
    display: block;
    margin-left: 1.5em;
    cursor: pointer;
}
.wc-deposits-option-title {
    display: block;
    font-weight: bold;
}
.wc-deposits-option-desc {
    display: block;
    color: #666;
    font-size: 0.9em;
}
.wc-deposits-select {
    width: 100%;
    max-width: 300px;
}
</style>

<script>
jQuery(function($){
    var $options = $('.wc-deposits-hybrid-options');
    $options.on('change', 'input[type=radio][name=wc_deposits_hybrid_option]', function(){
        if($(this).val() === 'plan') {
            $options.find('.wc-deposits-hybrid-plan-select').show();
        } else {
            $options.find('.wc-deposits-hybrid-plan-select').hide();
        }
    });
});
</script> 