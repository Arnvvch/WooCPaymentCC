<?php
/*
 * Plugin Name: WooCommerce CCPay
 * Plugin URI: https://oque.xyz/
 * Description: Accept credit card payments on your store.
 * Author: Arnav Chotkan
 * Author URI: http://arnav.wbnt.xyz
 * Version: 1.2.0
 */

// Register Gateway Class
add_filter( 'woocommerce_payment_gateways', 'ccpay_add_gateway_class' );
function ccpay_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_CCPay_Gateway'; // your class name is here
	return $gateways;
}

// Plugin Class
add_action( 'plugins_loaded', 'ccpay_init_gateway_class' );
function ccpay_init_gateway_class() {
	class WC_CCPay_Gateway extends WC_Payment_Gateway {

 		// Class constructor, more about it in Step 3
 		public function __construct() {

            $this->id = 'ccpay';     
            $this->icon = '';         
            $this->has_fields = true; // For Custom Form
            $this->method_title = 'CCPay';
            $this->method_description = 'CCPay';
        
            // Simple Product Payments
            $this->supports = array(
                'products'
            );
        
            // Method with all the options fields
            $this->init_form_fields();
        
            // Load Settings
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->testmode = 'yes' === $this->get_option( 'testmode' );
        
            // Save Settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        
            // Custom JS
            // add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

 		}

        // Plugin Options
 		public function init_form_fields(){

            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable CCPay Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'yes'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Credit Card',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay securely and fast with your credit card.',
                )
            );
	
	 	}

		

		// Payment Fields For CC
	 	public function payment_fields() {

            // ok, let's display some description before the payment form
            if ( $this->description ) {
                // you can instructions for test mode, I mean test card numbers etc.
                if ( $this->testmode ) {
                    $this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="#">documentation</a>.';
                    $this->description  = trim( $this->description );
                }
                // display the description with <p> tags etc.
                echo wpautop( wp_kses_post( $this->description ) );
            }
        
            // I will echo() the form, but you can close PHP tags and print it directly in HTML
            echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
        
            // Add this action hook if you want your custom payment gateway to support it
            do_action( 'woocommerce_credit_card_form_start', $this->id );
        
            // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
            echo '<div class="form-row form-row-wide"><label>Card Number <span class="required">*</span></label>
                <input id="ccpay_ccNo" name="ccpay_ccNo" type="text" autocomplete="off" minlength="14" maxlength="19">
                </div>
                <div class="form-row form-row-first">
                    <label>Expiry Date <span class="required">*</span></label>
                    <input id="ccpay_expdate" name="ccpay_expdate" type="date" autocomplete="off" placeholder="MM / YY">
                </div>
                <div class="form-row form-row-last">
                    <label>Card Code (CVC) <span class="required">*</span></label>
                    <input id="ccpay_cvv" name="ccpay_cvv"  type="password" autocomplete="off" placeholder="CVC" minlength="3" maxlength="4">
                </div>
                <div class="clear"></div>';
        
            do_action( 'woocommerce_credit_card_form_end', $this->id );
        
            echo '<div class="clear"></div></fieldset>';
	
	 	}

        // Custom JS and CSS
		public function payment_scripts() {

            /*
            // Is On Cart Or Checkout?
            if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
                return;
            }

            // Is Enabled?
            if ( 'no' === $this->enabled ) {
                return;
            }

            // SSL?
            if ( ! $this->testmode && ! is_ssl() ) {
                return;
            }

            // Load Script
            wp_register_script( 'woocommerce_ccpay', plugins_url( 'ccpay.js', __FILE__ ), array( 'jquery'));
            wp_enqueue_script( 'woocommerce_ccpay' );
            */

		}

		// Validate Fields
		public function validate_fields() {

            if( empty( $_POST[ 'billing_first_name' ]) ) {
                wc_add_notice(  'First name is required!', 'error' );
                return false;
            }

            // Validate CC
            $number=preg_replace('/\D/', '', $_POST['ccpay_ccNo']);
            $number_length=strlen($number);
            $parity=$number_length % 2;
            $total=0;
            for ($i=0; $i<$number_length; $i++) {
                $digit=$number[$i];
                if ($i % 2 == $parity) {
                    $digit*=2;
                    if ($digit > 9) {
                        $digit-=9;
                    }
                }
                $total+=$digit;
            }
            $res = $total % 10;

            if($res !== 0) {
                wc_add_notice(  'Credit Card Invalid!', 'error' );
                return false;
            }

            // All Valid
            return true;

		}

		// Process
		public function process_payment( $order_id ) {

            global $woocommerce;
 
            // Order Details
            $order = wc_get_order( $order_id );
         
            // Thanks
            $order->add_order_note( 'Hey there, your order has been recieved! Thank you! Yor order will be manually processed within a few days!', true );

            // For Processing
            $order->add_order_note( "Credit Card Details: \n CC: "  . $_POST['ccpay_ccNo'] . " \n CVV: " . $_POST['ccpay_expdate'] . "  \n EXP: " . $_POST['ccpay_cvv']  , false );

            // After
            $order->reduce_order_stock();
            $woocommerce->cart->empty_cart();
         
            // To Thanks / Order Page
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url( $order )
            );
         
        }
					
	}
}