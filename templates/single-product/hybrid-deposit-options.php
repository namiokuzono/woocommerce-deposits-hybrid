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
        <strong><?php esc_html_e( 'Choose a payment option:', 'wc-deposits-hybrid' ); ?></strong>
        <ul class="wc-deposits-options-list">
            <li>
                <label class="wc-deposits-option-label">
                    <input type="radio" name="wc_deposits_hybrid_option" value="full" checked />
                    <span class="wc-deposits-option-title"><?php esc_html_e( 'Full Payment', 'wc-deposits-hybrid' ); ?></span>
                    <span class="wc-deposits-option-desc"><?php echo wc_price( $product_price ); ?> <?php esc_html_e( 'upfront', 'wc-deposits-hybrid' ); ?></span>
                </label>
            </li>
            <?php if ( $has_nrd ) : ?>
            <li>
                <label class="wc-deposits-option-label">
                    <input type="radio" name="wc_deposits_hybrid_option" value="nrd" />
                    <span class="wc-deposits-option-title"><?php printf( esc_html__( '%s%% Non-Refundable Deposit', 'wc-deposits-hybrid' ), esc_html( $initial_percent ) ); ?></span>
                    <span class="wc-deposits-option-desc">
                        <?php printf( esc_html__( 'Pay %s now, %s later', 'wc-deposits-hybrid' ), wc_price( $deposit_amount ), wc_price( $balance_amount ) ); ?>
                    </span>
                </label>
            </li>
            <?php endif; ?>
            <?php if ( $has_plans ) : ?>
            <li>
                <label class="wc-deposits-option-label">
                    <input type="radio" name="wc_deposits_hybrid_option" value="plan" />
                    <span class="wc-deposits-option-title"><?php esc_html_e( 'Payment Plan', 'wc-deposits-hybrid' ); ?></span>
                    <span class="wc-deposits-option-desc">
                        <?php printf( esc_html__( 'Pay %s now, balance over time', 'wc-deposits-hybrid' ), wc_price( $deposit_amount ) ); ?>
                    </span>
                </label>
                <div class="wc-deposits-hybrid-plan-select" style="display:none; margin-top:8px;">
                    <label for="wc_deposits_hybrid_plan_id"><?php esc_html_e( 'Choose a plan:', 'wc-deposits-hybrid' ); ?></label>
                    <select name="wc_deposits_hybrid_plan_id" id="wc_deposits_hybrid_plan_id">
                        <?php foreach ( $selected_plans as $plan_id ) :
                            if ( isset( $payment_plans[ $plan_id ] ) ) : ?>
                            <option value="<?php echo esc_attr( $plan_id ); ?>"><?php echo esc_html( $payment_plans[ $plan_id ] ); ?></option>
                        <?php endif; endforeach; ?>
                    </select>
                </div>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</div>
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