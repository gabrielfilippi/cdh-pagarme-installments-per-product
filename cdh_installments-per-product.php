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
 * Version: 2.1.1
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if (! defined ('ABSPATH')) exit; // Saia se acessado diretamente

add_action('wp_enqueue_scripts', 'cdh_installments_on_product_css', 1001);
function cdh_installments_on_product_css(){
	// custom styles
	wp_deregister_style('cdh_installments_product');
	wp_register_style('cdh_installments_product', plugins_url( "/layout/css/installments_on_product_loop.min.css", __FILE__ ));
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

        $output .= '<img src="'.$url_img_pix.'" width="50px" height="20px"><span class="pix-installments-divider">|</span>';
    }

    /**
     * Credit Card HTML
     */
    if($credit_card_configuration AND $credit_card_configuration['enabled'] == "yes"){ //show installments per Product

        //add credit card icon
        $output .= '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink" width="16.5" height="16.5" x="0" y="0" viewBox="0 0 512.002 512.002" xml:space="preserve" class=""><g><path d="M502.903 96.829c-6.634-7.842-15.924-12.632-26.161-13.487L116.185 53.236c-10.238-.855-20.192 2.328-28.035 8.961-7.811 6.607-12.594 15.85-13.476 26.037L67.42 156.29H38.455C17.251 156.29 0 173.541 0 194.745v225.702c0 21.204 17.251 38.455 38.455 38.455h361.813c21.205 0 38.456-17.251 38.456-38.455v-36.613l12.839 1.072c1.083.09 2.16.135 3.228.135 19.768 0 36.62-15.209 38.294-35.257l18.781-224.919c.854-10.237-2.329-20.193-8.963-28.036zM38.455 176.29h361.813c10.176 0 18.456 8.279 18.456 18.455v20.566H20v-20.566c0-10.176 8.279-18.455 18.455-18.455zM20 235.311h398.724V276.8H20zm380.268 203.591H38.455c-10.176 0-18.455-8.279-18.455-18.455V296.8h398.724v123.647c0 10.176-8.28 18.455-18.456 18.455zM491.935 123.2l-18.781 224.919c-.847 10.141-9.788 17.706-19.927 16.856l-14.503-1.211V194.745c0-21.204-17.251-38.455-38.456-38.455H87.534l7.039-66.04c.008-.076.015-.151.021-.228.847-10.141 9.783-17.705 19.927-16.855l360.558 30.106c4.913.41 9.372 2.709 12.555 6.473s4.711 8.541 4.301 13.454z" fill="#000000" opacity="1" data-original="#000000" class=""></path><path d="M376.873 326.532h-96.242c-5.523 0-10 4.477-10 10v62.789c0 5.523 4.477 10 10 10h96.242c5.523 0 10-4.477 10-10v-62.789c0-5.523-4.477-10-10-10zm-10 62.789h-76.242v-42.789h76.242z" fill="#000000" opacity="1" data-original="#000000" class=""></path></g></svg>';
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
                $output .= ' <span class="intallments-product">'.$max_installments_without_fees.'x R$'.number_format((float)$installments_value, 2, ",", "").' sem juros</span>';
                $hasFreeInstallments = true;
                break;
            }
        }

        if(!$hasFreeInstallments){ // no have installments then is 1x without fees
            $output .= ' <span class="intallments-product">1x R$'.number_format((float)$product_price, 2, ",", "").' sem juros</span>';
        }

        for($max_installments_with_fees = $max_installment; $max_installments_with_fees>0; $max_installments_with_fees--){
            $installments_value = round($product_price / $max_installments_with_fees, 2);
            //will only show the installment with interest if the installment with interest is greater than the installment without interest
            if( $installments_value >= $smallest_installment && $max_installments_with_fees > $max_installments_without_fees){
                $interest_rate = ($max_installments_with_fees - $free_installments) * $interest_rate_increase;
                $value_with_fees = (($product_price * ($interest_rate / 100)) + $product_price ) / $max_installments_with_fees;
                $installments_value_with_fees = ceil($value_with_fees * 100) / 100;

                $output .= '<span class="cc-installments-divider">– ou</span><span class="intallments-product-fees">'.$max_installments_with_fees.'x R$'.number_format((float)$installments_value_with_fees, 2, ",", "").'</span>';
                break;
            }
        }
    }

    $output .= '</div>';
    return $output;
}