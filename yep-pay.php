<?php
/**
 * Plugin Name: Yep-Pay
 * Plugin URI: https://yeppay.io
 * Description: WooCommerce payment channel to Yep-Pay
 * Version: 1.0.0
 * Author: Solarin Olakunle
 * Author URI: https://shapply.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * WC requires at least: 3.0.0 
 * Text Domain: yep-pay
 * Domain Path: /languages
 */
 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
 //confirm if woocommerce is installed
 if(!in_array('woocommerce/woocommerce.php',apply_filters('active_plugins',get_option('active_plugins')))){
	 return;
 }
 
 add_action('plugins_loaded','yep_pay_init');
 //Avail functionalities only if woocomerce is activated
 function yep_pay_init(){
	 if(class_exists('WC_Payment_Gateway')){
		 //Main Class
		 class WC_Yep_Pay_Payment_Gateway extends WC_Payment_Gateway{

				/**
				 * public key.
				 *
				 * @var string
				 */
				public $public_key;

				/**
				 * secret key.
				 *
				 * @var string
				 */
				public $secret_key;
				/**
				 * Contructor
				 */

				public function __construct(){
					$this->id = 'yep_pay';
					$this->icon = apply_filters('woocommerce_yep_pay_icon' ,plugins_url('yep-pay/assets/images/yepmainlogosvg_jvlgbm.svg'));
					$this->has_fields = false;
					$this->method_title = __('Yep Pay','yep-pay');
					$this->method_description = __('WooCommerce payment channel to Yep-Pay.',
					'yep-pay');
					//Load Configuration Fields
					$this->init_form_fields();
					$this->init_settings();
					$this->title = $this->get_option('title');
					$this->description = $this->get_option('description');
					$this->instruction = $this->get_option('instruction');
					$this->instructions = $this->get_option('instructions');
					add_action('woocommerce_update_options_payment_gateways_' . $this->id , array($this, 'process_admin_options'));					
					add_action( 'woocommerce_api_wc_yep_pay_payment_gateway', array( $this, 'returning_transaction' ) );
					$this->public_key = ($this->get_option( 'test_mode' ) == 'yes') ? $this->get_option( 'test_public_key' ) :  $this->get_option( 'live_public_key' ); 
					$this->secret_key =($this->get_option( 'test_mode' ) == 'yes') ?  $this->get_option( 'test_secret_key' ) : $this->get_option( 'live_secret_key' ); 
				}
				
				/**
				* Return from Yep! Pay Payment Gateway
				*/
				public function returning_transaction(){
					$data = sanitize_text_field( $_REQUEST['data'] ); 
					WC()->cart->empty_cart();
					wp_redirect( wc_get_page_permalink( 'cart' ) );
					exit;
				}
				/**
				* Setup fields for accepting configuration parameters
				*/
				public function init_form_fields(){
					 $this->form_fields = apply_filters(
					'woo_yep_pay_fields', array(
						'enabled' => array(
							'title' => __('Enable','yep-pay'),
							'type' => 'checkbox'
							,
							'label'       => __( ' ', 'yep-pay' ),
							'default' => 'disabled'
						),
						'title' => array(
							'title' => __('Yep-Pay Gateway','yep-pay'),
							'type' => 'text',
							'description'       => __( 'Add text that customers will see when they want to make payment through Yep-Pay', 'yep-pay' ),
							'default' =>  __( 'Yep Pay', 'yep-pay'),
							'desc_tip' => true	
						),
						'description' => array(
							'title' => __('Yep-Pay Gateway Description','yep-pay'),
							'type' => 'textarea'
							, 
							'default' =>  __( 'You will now be routed to the Yep-Pay online payment platform to make payments', 'yep-pay'),
							'desc_tip' => true,	
							'description'       => __( 'Add descriptive text that customers will see when they want to make payment through Yep-Pay', 'yep-pay' )
						),
						'instruction' => array(
							'title' => __('Yep-Pay Gateway Instructions','yep-pay'),
							'type' => 'textarea'
							, 
							'default' =>  __( 'Instructions ', 'yep-pay'),
							'desc_tip' => true,	
							'description'       => __( 'Add instructions text that customers will see when they want to make payment through Yep-Pay', 'yep-pay' )
						),
						'test_mode' => array(
							'title' => __('Test Mode','yep-pay'),
							'type' => 'checkbox'
							,
							'label'       => __( ' ', 'yep-pay' ),
							'default' => 'disabled'
						), 
						'test_secret_key'                  => array(
							'title'       => __( 'Test Secret Key', 'yep-pay' ),
							'type'        => 'text',
							'description' => __( 'Enter your Test Secret Key here', 'yep-pay' ),
							'default'     => '',
						),
						'test_public_key'                  => array(
							'title'       => __( 'Test Public Key', 'yep-pay' ),
							'type'        => 'text',
							'description' => __( 'Enter your Test Public Key here.', 'yep-pay' ),
							'default'     => '',
						),
						'live_secret_key'                  => array(
							'title'       => __( 'Live Secret Key', 'yep-pay' ),
							'type'        => 'text',
							'description' => __( 'Enter your Live Secret Key here', 'yep-pay' ),
							'default'     => '',
						),
						'live_public_key'                  => array(
							'title'       => __( 'Live Public Key', 'yep-pay' ),
							'type'        => 'text',
							'description' => __( 'Enter your Live Public Key here.', 'yep-pay' ),
							'default'     => '',
						),
					)); 
				} 
				
				/**
				 * Process a  payment.
				 *
				 * @param int $order_id
				 * @return array|void
				 */
			
				public function process_payment($order_id){
					$order        = wc_get_order( $order_id );
					$email        = method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : $order->billing_email;
					$amount       = $order->get_total()  ;
					$txnref       = $order_id . '_' . time();
					$currency     = method_exists( $order, 'get_currency' ) ? $order->get_currency() : $order->order_currency;
					$callback_url = WC()->api_request_url( 'WC_Yep_Pay_Payment_Gateway' );
					$payment_channels = array();
					$params = array(
						'amount'       => $amount,
						'email'        => $email, 
						'reference'    => $txnref,
						'callback_url' => $callback_url,
					);
					if ( ! empty( $payment_channels ) ) {
						$params['channels'] = $payment_channels;
					}
					$params['metadata']['cancel_action'] = wc_get_cart_url();
					update_post_meta( $order_id, '_txn_ref', $txnref );
					$url = 'https://payment.yeppay.io/api/v1/payment';
					$headers = array(
						'Authorization' => 'Bearer ' . $this->secret_key,
						'Content-Type'  => 'application/json',
						'Accept'  => 'application/json',
					);
					$args = array(
						'headers' => $headers,
						'timeout' => 60,
						'body'    => json_encode( $params ),
					);
					$request = wp_remote_post( $url, $args );
					if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {
						$response = json_decode( wp_remote_retrieve_body( $request ) );
						return array(
							'result'   => 'success',
							'redirect' => $response->data->payment_url ,
						);
					} else {
						wc_add_notice( __( 'Unable to process payment try again', 'yep-pay' ), 'error' );
						return;
					}
				}
		 }
	 }
 }
 
 add_filter('woocommerce_payment_gateways', 'add_to_woo_yep_pay_gateway');
 
 function add_to_woo_yep_pay_gateway($gateways){
	 $gateways[] = 'WC_Yep_Pay_Payment_Gateway';
	 return $gateways; 
 }