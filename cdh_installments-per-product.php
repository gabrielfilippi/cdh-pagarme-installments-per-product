<?php
 /**
 * Plugin Name: CodeHive - Show Installments allowed per product using Pagar.me
 * Plugin URI: #
 * Description: Shows the installment plan available for each product as well as the PIX payment method using the Pagar.me payment method
 * Author: CodeHive
 * Author URI: https://codehive.com.br
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: cdh_installments_per_product_using_pagarme
 * Version: 2.0.1
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if (! defined ('ABSPATH')) exit; // Saia se acessado diretamente

add_action('wp_enqueue_scripts', 'cdh_installments_on_product_css', 1001);
function cdh_installments_on_product_css(){
	// custom styles
	wp_deregister_style('cdh_installments_product');
	wp_register_style('cdh_installments_product', plugins_url( "/layout/css/installments_on_product_loop.css", __FILE__ ));
	wp_enqueue_style('cdh_installments_product');
}

add_filter( 'woocommerce_get_price_html', 'cdh_add_installments_after_price_html' );
function cdh_add_installments_after_price_html($price){
    global $product;

    if ( ! is_a($product, 'WC_Product') ) {
        $product = wc_get_product( get_the_id() );
    }
    
    if ( is_a($product, 'WC_Product') && !$product->is_type(array('composite', 'subscription', 'subscription_variation', 'variable-subscription')) && $product->get_price() !== "" && $product->get_price() !== null && $product->get_price() !== 0 && $product->get_price() ) {
        $installments = cdh_get_installments_per_product($product);
        return $price . "<br>" . $installments;
    }else {
        return $price;
    }
}

function cdh_get_installments_per_product($product){
    $pix_configuration = get_option("woocommerce_woo-pagarme-payments-pix_settings");
    $credit_card_configuration = get_option("woocommerce_woo-pagarme-payments-credit_card_settings");
    $output = '';

    if(!$pix_configuration AND !$credit_card_configuration){
        return $output;
    }

    $output = '<div id="installments">';

    /**
     * PIX HTML
     */
    if($pix_configuration AND $pix_configuration['enabled']){
        $url_img_pix = plugins_url( "/layout/img/logo-pix-100-100.png", __FILE__ );

        $output .= '
            Pague com <img src="'.$url_img_pix.'" width="50px" height="20px"><br>
        ';
    }

    /**
     * Credit Card HTML
     */
    if($credit_card_configuration AND $credit_card_configuration['enabled'] == "yes"){ //show installments per Product

        $free_installments = $credit_card_configuration['cc_installments_without_interest'];
        $max_installment = $credit_card_configuration['cc_installments_maximum'];
        $interest_rate_increase = $credit_card_configuration['cc_installments_interest_increase'];
        $smallest_installment  = $credit_card_configuration['cc_installments_min_amount'];

        if ( $product->is_type( 'variable' ) ) {
            $prices = $product->get_variation_prices( true );
            $product_price = current( $prices['price'] ); //min_price
        }else{
            $product_price = $product->get_price();
        }

        $hasFreeInstallments = false;
        for($max_installments_without_fees=$free_installments; $max_installments_without_fees>0; $max_installments_without_fees--){
            $installments_value = round($product_price / $max_installments_without_fees, 2);
            if( $installments_value >= $smallest_installment){
                $output .= 'Parcele em até <span class="intallments-product">'.$max_installments_without_fees.'x R$'.number_format((float)$installments_value, 2, ",", "").' sem juros</span> <br>';
                $hasFreeInstallments = true;
                break;
            }
        }

        if(!$hasFreeInstallments){ // no have installments then is 1x without fees
            $output .= 'ou em <span class="intallments-product">1x R$'.number_format((float)$product_price, 2, ",", "").' sem juros</span>';
        }

        for($max_installments_with_fees = $max_installment; $max_installments_with_fees>0; $max_installments_with_fees--){
            $installments_value = round($product_price / $max_installments_with_fees, 2);
            //will only show the installment with interest if the installment with interest is greater than the installment without interest
            if( $installments_value >= $smallest_installment && $max_installments_with_fees > $max_installments_without_fees){
                $interest_rate = ($max_installments_with_fees - $free_installments) * $interest_rate_increase;
                $value_with_fees = (($product_price * ($interest_rate / 100)) + $product_price ) / $max_installments_with_fees;
                $installments_value_with_fees = ceil($value_with_fees * 100) / 100;

                $output .= 'ou em até <span class="intallments-product-fees">'.$max_installments_with_fees.'x R$'.number_format((float)$installments_value_with_fees, 2, ",", "").'</span> <br>';
                break;
            }
        }
    }

    $output .= '</div>';
    return $output;
}