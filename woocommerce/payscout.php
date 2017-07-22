<?php
/**
 * Plugin Name: Payscout Payment Gateway for Woocommerce
 * Plugin URI: Plugin URI: https://wordpress.org/plugins/payscout_2_0/
 * Description: This plugin adds a payment option in WooCommerce for customers to pay with their Credit Cards Via Payscout.
 * Version: 2.5.0
 * Author: Payscout Inc
 * Author URI: https://www.payscout.com/
 * License: GPLv2
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
function payscout_init()
{
	
	function add_payscout_gateway_class( $methods ) 
	{
		$methods[] = 'WC_Payscout_Gateway'; 
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_payscout_gateway_class' );
	
	if(class_exists('WC_Payment_Gateway'))
	{
		class WC_Payscout_Gateway extends WC_Payment_Gateway 
		{
		public function __construct()
		{

		$this->id               = 'payscout';
		$this->icon             = plugins_url( 'images/payscout.png' , __FILE__ ) ;
		$this->has_fields       = true;
		$this->method_title     = 'Payscout Payment Gateway Settings';		
		$this->init_form_fields();
		$this->init_settings();
		$this->supports  = array(  'default_credit_card_form');
		$this->title	= $this->get_option( 'payscout_title' );
		$this->transaction_server = $this->settings['transaction_server'];		
		$this->payscout_cardtypes       = $this->get_option( 'payscout_cardtypes'); 
		
		if($this->settings['transaction_server'] == 'live')  // FOR LIVE TRANSACTIONS
			{
				$this->trans_url 		= 'https://gateway.payscout.com/api/process';				
				$this->client_username  = $this->settings['client_username'];
				$this->client_password  = $this->settings['client_password'];
				$this->client_token 	 = $this->settings['client_token'];				
				
			}else{				 // FOR TEST TRANSACTIONS
			
				$this->trans_url 	= 'https://mystaging.paymentecommerce.com/api/process';				
				$this->client_username  = $this->settings['client_username'];
				$this->client_password  = $this->settings['client_password'];
				$this->client_token 	 = $this->settings['client_token'];	
			
			}      
        
			
		 if (is_admin()) 
		 {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) ); 		 }

		}
		
		
		
		public function admin_options()
		{
		?>
		<h3><?php _e( 'Payscout Payment Gateway', 'woocommerce' ); ?></h3>
		<p><?php  _e( 'payscout is a payment gateway service provider allowing merchants to accept credit card.', 'woocommerce' ); ?></p>
		<table class="form-table">
		  <?php $this->generate_settings_html(); ?>
		</table>       
		<?php
		}
		
		
		
		public function init_form_fields()
		{
		$this->form_fields = array
		(
			'enabled' => array(
			  'title' => __( 'Enable/Disable', 'woocommerce' ),
			  'type' => 'checkbox',
			  'label' => __( 'Enable Payscout', 'woocommerce' ),
			  'default' => 'yes'
			  ),
			  
			  'transaction_server' => array(
					'title' 		=> __('Transaction Server', 'woocommerce'),
					'type' 			=> 'select',					
					'options' => array(
					  'live'        => __( 'Live', 'woocommerce' ),				
					  'test'       => __( 'Sandbox/Test', 'woocommerce' )				
					),
					'desc_tip' 		=> true
                ),
				
			'payscout_title' => array(
			  'title' => __( 'Title', 'woocommerce' ),
			  'type' => 'text',
			  'description' => __( 'This controls the title which the buyer sees during checkout.', 'woocommerce' ),
			  'default' => __( 'Payscout', 'woocommerce' ),
			  'desc_tip'      => true,
			  ),			  
			'client_username' => array(
					'title' 		=> __('Client Username', 'woocommerce'),
					'type' 			=> 'text',
					'description' 	=> __('Payscout Client Username'),
					'desc_tip' 		=> true
				),
      			'client_password' => array(
					'title' 		=> __('Client Password', 'woocommerce'),
					'type' 			=> 'text',
					'description' 	=>  __('Payscout Client Password', 'woocommerce'),
					'desc_tip' 		=> true
                ),
			'client_token' => array(
					'title' 		=> __('Client Token', 'woocommerce'),
					'type' 			=> 'text',
					'description' 	=>  __('Payscout Client Token', 'woocommerce'),
					'desc_tip' 		=> true
                ),
			'payscout_cardtypes' => array(
			 'title'    => __( 'Accepted Cards', 'woocommerce' ),
			 'type'     => 'multiselect',
			 'class'    => 'chosen_select',
			 'css'      => 'width: 350px;',
			 'desc_tip' => __( 'Select the card types to accept.', 'woocommerce' ),
			 'options'  => array(
				'mastercard'       => 'MasterCard',
				'visa'             => 'Visa',
				'discover'         => 'Discover',
				'amex' 		    => 'American Express',
				'jcb'		    => 'JCB',
				'dinersclub'       => 'Dinners Club',
			 ),
			 'default' => array( 'mastercard', 'visa', 'discover', 'amex' ),
			),
	  	);
  		}
			


  		/*Get Icon*/
		public function get_icon() {
		$icon = '';
		if(is_array($this->payscout_cardtypes ))
		{
        foreach ( $this->payscout_cardtypes  as $card_type ) {

				if ( $url = $this->get_payment_method_image_url( $card_type ) ) {
					
					$icon .= '<img src="'.esc_url( $url ).'" alt="'.esc_attr( strtolower( $card_type ) ).'" />';
				}
			}
		}
		else
		{
			$icon .= '<img src="'.esc_url( plugins_url( 'images/payscout.png' , __FILE__ ) ).'" alt="Payscout Payment Gateway" />';	  
		}

         return apply_filters( 'woocommerce_payscout_icon', $icon, $this->id );
		}
 
		public function get_payment_method_image_url( $type ) {

		$image_type = strtolower( $type );
				return  WC_HTTPS::force_https_url( plugins_url( 'images/' . $image_type . '.png' , __FILE__ ) ); 
		}
		/*Get Icon*/


		/*Get Card Types*/
		function get_card_type($number)
		{
		    $number=preg_replace('/[^\d]/','',$number);
		    if (preg_match('/^3[47][0-9]{13}$/',$number))
		    {
		        return 'amex';
		    }
		    elseif (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/',$number))
		    {
		        return 'dinersclub';
		    }
		    elseif (preg_match('/^6(?:011|5[0-9][0-9])[0-9]{12}$/',$number))
		    {
		        return 'discover';
		    }
		    elseif (preg_match('/^(?:2131|1800|35\d{3})\d{11}$/',$number))
		    {
		        return 'jcb';
		    }
		    elseif (preg_match('/^5[1-5][0-9]{14}$/',$number))
		    {
		        return 'mastercard';
		    }
		    elseif (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/',$number))
		    {
		        return 'visa';
		    }
		    else
		    {
		        return 'unknown card';
		    }
		}// End of getcard type function
		
		
		//Function to check IP
		function get_client_ip() 
		{
			$ipaddress = '';
			if (getenv('HTTP_CLIENT_IP'))
				$ipaddress = getenv('HTTP_CLIENT_IP');
			else if(getenv('HTTP_X_FORWARDED_FOR'))
				$ipaddress = getenv('HTTP_X_FORWARDED_FOR');
			else if(getenv('HTTP_X_FORWARDED'))
				$ipaddress = getenv('HTTP_X_FORWARDED');
			else if(getenv('HTTP_FORWARDED_FOR'))
				$ipaddress = getenv('HTTP_FORWARDED_FOR');
			else if(getenv('HTTP_FORWARDED'))
				$ipaddress = getenv('HTTP_FORWARDED');
			else if(getenv('REMOTE_ADDR'))
				$ipaddress = getenv('REMOTE_ADDR');
			else
				$ipaddress = '0.0.0.0';
			return $ipaddress;
		}
		
		//End of function to check IP

		/*Initialize Payscout Parameters*/
		public function payscout_params($wc_order){
				
				$exp_date         = explode( "/", sanitize_text_field($_POST['payscout-card-expiry']));
				$exp_month        = str_replace( ' ', '', $exp_date[0]);
				$exp_year         = str_replace( ' ', '',$exp_date[1]);

				if (strlen($exp_year) == 2) {
					$exp_year += 2000;
				}
      	
				$payscout_params_args = array(				
				'client_username'     => $this->client_username,
				'client_password'     => $this->client_password,
				'client_token'        => $this->client_token,				
				'account_number'      => sanitize_text_field(str_replace(' ','',$_POST['payscout-card-number'])),
				'cvv2'                => sanitize_text_field($_POST['payscout-card-cvc']),
				'processing_type'     => 'DEBIT',
				'billing_date_of_birth'=> date('Ymd', strtotime(sanitize_text_field(str_replace(' ','',$_POST['payscout-billing-dob'])))),
				'expiration_month'    => $exp_month,
				'expiration_year'     => $exp_year,
				'currency'			=> get_woocommerce_currency(),
				'initial_amount'      => $wc_order->order_total,
				'billing_first_name'  => html_entity_decode($wc_order->billing_first_name, ENT_QUOTES, 'UTF-8'),
				'billing_last_name'   => html_entity_decode($wc_order->billing_last_name, ENT_QUOTES, 'UTF-8'),				
				'billing_email_address' => $wc_order->billing_email,				
				'billing_address_line_1'=> html_entity_decode($wc_order->billing_address_1, ENT_QUOTES, 'UTF-8'),
				'billing_phone_number'  => html_entity_decode(preg_replace('/[^0-9]/', '', $wc_order->billing_phone), ENT_QUOTES, 'UTF-8'),
				'billing_city'          => html_entity_decode($wc_order->billing_city, ENT_QUOTES, 'UTF-8'),
				'billing_state'         => html_entity_decode($wc_order->billing_state, ENT_QUOTES, 'UTF-8'),
				'billing_country'       => html_entity_decode($wc_order->billing_country, ENT_QUOTES, 'UTF-8'),				
				'billing_postal_code'   => html_entity_decode($wc_order->billing_postcode, ENT_QUOTES, 'UTF-8'),
				'pass_through'          => $wc_order->get_order_number(),				
				'shipping_first_name'   => html_entity_decode($wc_order->shipping_first_name, ENT_QUOTES, 'UTF-8'),
				'shipping_last_name'    => html_entity_decode($wc_order->shipping_last_name, ENT_QUOTES, 'UTF-8'),				
				'shipping_address_line_1' => html_entity_decode($wc_order->shipping_address_1, ENT_QUOTES, 'UTF-8'),				
				'shipping_city'         => html_entity_decode($wc_order->shipping_city, ENT_QUOTES, 'UTF-8'),
				'shipping_state'        => html_entity_decode($wc_order->shipping_state, ENT_QUOTES, 'UTF-8'),				
				'shipping_country'      => html_entity_decode($wc_order->shipping_country, ENT_QUOTES, 'UTF-8'),
				'shipping_postal_code'  => $wc_order->shipping_postcode								  
			);
			
			
        			 return $payscout_params_args;
     	 } // End of payscout_params
		
		
		
		
		
		/*Payment Processing Fields*/
		public function process_payment($order_id)
		{
		
			global $woocommerce;
         		$wc_order = new WC_Order($order_id);
         		
			$cardtype = $this->get_card_type(sanitize_text_field(str_replace(' ','',$_POST['payscout-card-number'])));
			
         		if(!in_array($cardtype ,$this->payscout_cardtypes ))
         		{
         			wc_add_notice('Merchant do not support accepting in '.$cardtype,  $notice_type = 'error' );
         			return array (
								'result'   => 'success',
								'redirect' => WC()->cart->get_checkout_url(),
							   );
				    die;
         		}
         
			
			 $gatewayurl = $this->trans_url; 
			
			
			$params = $this->payscout_params($wc_order);
         
			$post_string = '';
			foreach( $params as $key => $value )
			{ 
			  $post_string .= urlencode( $key )."=".urlencode($value )."&"; 
			}
			
			
			$post_string = rtrim($post_string,"&");

			/*HTTP POST API*/
				$response = wp_remote_post( $gatewayurl, array(
					'method'       => 'POST',
					'body'         => $post_string,
					'redirection'  => 0,
					'timeout'      => 70,
					'sslverify'    => false,
				) );
			
				if ( is_wp_error( $response ) ) throw new Exception( __( 'Problem connecting to the payment gateway.', 'woocommerce' ) );
			
				if ( empty( $response['body'] ) ) throw new Exception( __( 'Empty Payscout response.','woocommerce') );
			
				$content = json_decode($response['body']);
						
			$i = 1;
			
			

		if ( count($content) > 0 )
		{			
			if( isset($content->status) && $content->status == 'approved')
			{
				$wc_order->add_order_note( __( $content->status. ' on '.date("d-m-Y h:i:s e").' with Transaction ID = '.$content->transaction_id.' using payscout gateway, cardholder authentication verification response code: '.$content->message, 'woocommerce' ) );
			
			$wc_order->payment_complete("Thank You, Your payment has been processed successfully.");
			WC()->cart->empty_cart();
			return array (
						'result'   => 'success',
						'redirect' => $this->get_return_url( $wc_order ),
					   );
			}else if( isset($content->status) && $content->status != 'approved')
			{
				
				$wc_order->add_order_note( __( 'payment failed.'.str_replace("responsetext=", "", $content->status).'--'.'--', 'woocommerce' ) );
				
				$payment_error = $content->message;
				
				if(isset($content->raw_message) && $content->raw_message !="")
				{
					$payment_error = $content->raw_message;
				}
				
				wc_add_notice('Error Processing Payments', $payment_error );
			}
			else 
			{
				$wc_order->add_order_note( __( 'payment failed.'. $content->message.'--'.'--', 'woocommerce' ) );
				wc_add_notice('Error Processing Payments', $notice_type = 'error' );
			}
		}
		else 
		{
			$wc_order->add_order_note( __( 'payment failed.', 'woocommerce' ) );
			wc_add_notice('Error Processing Payments', $notice_type = 'error' );
		}
        
		}// End of process_payment
		
		
		}// End of class WC_Payscout_Gateway
	} // End if WC_Payment_Gateway
}// End of function payscout_init

add_action( 'plugins_loaded', 'payscout_init' );


/*Plugin Settings Link*/
function payscout_settings_link( $links ) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_payscout_gateway">' . __( 'Settings' ) . '</a>';
    array_push( $links, $settings_link );
  	return $links;
}
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'payscout_settings_link' );


add_filter( 'woocommerce_credit_card_form_fields' , 'add_dob_on_cc' , 10, 2 );
function add_dob_on_cc ($cc_fields , $payment_id){
$cc_fields = array(
 'card-dob-field' => '<p class="form-row">
 <label for="payscout-billing-dob">' . __( 'Billing DOB (mm/dd/yyyy)', 'woocommerce' ) . ' <span class="required">*</span></label>
 <input id="payscout-billing-dob" class="input-text wc-credit-card-form-card-dob" type="text" autocomplete="off" placeholder="' . __( 'Billing DOB (mm/dd/yyyy)', 'woocommerce' ) . '" name="payscout-billing-dob" />
 </p>',
 'card-number-field' => '<p class="form-row form-row-wide">
 <label for="payscout-card-number">' . __( 'Card Number', 'woocommerce' ) . ' <span class="required">*</span></label>
 <input id="payscout-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" autocomplete="off" placeholder="•••• •••• •••• ••••" name="payscout-card-number" />
 </p>',
 'card-expiry-field' => '<p class="form-row form-row-first">
 <label for="payscout-card-expiry">' . __( 'Expiry (MM/YY)', 'woocommerce' ) . ' <span class="required">*</span></label>
 <input id="payscout-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="' . __( 'MM / YY', 'woocommerce' ) . '" name="payscout-card-expiry" />
 </p>',
 'card-cvc-field' => '<p class="form-row form-row-last">
 <label for="payscout-card-cvc">' . __( 'Card Code', 'woocommerce' ) . ' <span class="required">*</span></label>
 <input id="payscout-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="text" autocomplete="off" placeholder="' . __( 'CVC', 'woocommerce' ) . '" name="payscout-card-cvc" />
 </p>'
);
return $cc_fields;
}