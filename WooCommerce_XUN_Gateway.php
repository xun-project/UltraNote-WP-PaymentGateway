<?php
/**
 * Plugin Name: WooCommerce xun Gateway
 * Plugin URI: https://github.com/xun-project/UltraNote-WP-PayemtGateway
 * Description: Receive Ultranote (XUN) payments with woocommerce.
 * Author: UltraNote Team.
 * Version: 1.0.3
 * Text Domain: wc-gateway-xun
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2015-2019 UltraNote XUN, Inc. (info@ultranote.org) and WooCommerce
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Gateway-xun
 * @author    UltraNote Team
 * @category  Admin
 * @copyright Copyright (c) 2015-2019, UltraNote XUN, Inc. and WooCommerce
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 * This xun gateway forks the WooCommerce core "Cheque" payment gateway to create another xun payment method.
 */
 
defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}


/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + xun gateway
 */
function wc_xun_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_xun';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_xun_add_to_gateways' );


/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_xun_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=xun_gateway' ) . '">' . __( 'Settings', 'wc-gateway-xun' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_xun_gateway_plugin_links' );


/**
 * xun Payment Gateway
 *
 * Provides an xun Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_xun
 * @extends		WC_Payment_Gateway
 * @version		1.0.2
 * @package		WooCommerce/Classes/Payment
 * @author 		UltraNote Team
 */



add_action( 'woocommerce_checkout_before_order_review', function() {
	echo '<p>Don\'t forget to include your unit number in the address!</p>';
});





add_action( 'plugins_loaded', 'wc_xun_gateway_init', 11 );

function wc_xun_gateway_init() {

	class WC_Gateway_xun extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
	  					
			//$order_id  = apply_filters( 'woocommerce_thankyou_order_id', absint( $wp->query_vars['order-received'] ) );
			$this->id                 = 'xun_gateway';
			$this->icon               = apply_filters('woocommerce_xun_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'Ultranote (XUN)', 'wc-gateway-xun' );
			$this->method_description = __( 'Receive xun payments using Ultranote (XUN) coin.', 'wc-gateway-xun' );
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description);
			$this->market_xun_address = $this->get_option( 'market_xun_address');
			$this->json_rpc_link = $this->get_option( 'json_rpc_link');
			$this->json_rpc_user = $this->get_option( 'json_rpc_user');
			$this->json_rpc_password = $this->get_option( 'json_rpc_password');
			
    		// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		  
			// Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}
	
	
		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'wc_xun_form_fields', array(
		  
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'wc-gateway-xun' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable xun Payment', 'wc-gateway-xun' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => __( 'Title', 'wc-gateway-xun' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-xun' ),
					'default'     => __( 'Ultranote (XUN)', 'wc-gateway-xun' ),
					'desc_tip'    => true,
				),
				
				'description' => array(
					'title'       => __( 'Description', 'wc-gateway-xun' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-gateway-xun' ),
					'default'     => __( 'Pay using Ultranote (XUN) coin', 'wc-gateway-xun' ),
					'desc_tip'    => true,
				),
				
				'instructions' => array(
					'title'       => __( 'Instructions', 'wc-gateway-xun' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-gateway-xun' ),
					'default'     => __( 'To complete the payment please send EXACTLY the amount listed bellow to the address listed bellow :', 'wc-gateway-xun' ),
					'desc_tip'    => true,
				),

				'market_xun_address' => array(
					'title'       => __( 'Market XUN Address', 'wc-gateway-xun' ),
					'type'        => 'text',
					'description' => __( 'This Is The Ultranote address where the market will recieve payements to.', 'wc-gateway-xun' ),
					'default'     => __( 'Xun3ZtBPE7eYvz1Uokg9zg9m8UJsYdWFyEFT6Mmk4snXgMeaSfAQRGKhHPSR7X6nPG5DVpjrpNJ2Jg7Ej4DV3xgL5PEsCMBnGV', 'wc-gateway-xun' ),
					'desc_tip'    => true,
				),

				'json_rpc_link' => array(
					'title'       => __( 'Json RPC link and port', 'wc-gateway-xun' ),
					'type'        => 'text',
					'description' => __( 'Json RPC link and port, something like : http://domain.com:8060/ or http://localhost:8060/json_rpc, or http://192.168.1.1:8060/json_rpc', 'wc-gateway-xun' ),
					'default'     => __( 'http://localhost:8060/json_rpc', 'wc-gateway-xun' ),
					'desc_tip'    => true,
				),

				'json_rpc_user' => array(
					'title'       => __( 'Json RPC username (leave empty if none)', 'wc-gateway-xun' ),
					'type'        => 'text',
					'description' => __( 'Json RPC username (leave empty if none)', 'wc-gateway-xun' ),
					'default'     => __( '', 'wc-gateway-xun' ),
					'desc_tip'    => true,
				),

				'json_rpc_password' => array(
					'title'       => __( 'Json RPC password (leave empty if none)', 'wc-gateway-xun' ),
					'type'        => 'text',
					'description' => __( 'Json RPC password (leave empty if none)', 'wc-gateway-xun' ),
					'default'     => __( '', 'wc-gateway-xun' ),
					'desc_tip'    => true,
				),


			) );
		}
	
	
		/**
		 * Output for the order received page.
		 */
		public function thankyou_page($order_id ){
            $order = wc_get_order( $order_id );
            if ($order->has_status( 'on-hold' )) {
            	if ( $this->instructions ) {
					echo "<h2>".wpautop( wptexturize( $this->instructions ) )."</h2>";
				}
				if ($this->market_xun_address) {
					echo "</br><h3>Amount to pay EXACTLY (in Xun):<br><input type='text' style='font-size:36px;width:100%;background:transparent;border-width:0;' name='xun_to_pay' onClick='this.setSelectionRange(0, this.value.length)' readonly='readonly' value='".$order->get_meta('xun_to_pay')."'></h3></br>";
					echo "<h3>To This Address:<br><input type='text' style='font-size:16px;width:100%;background:transparent;border-width:0;' name='xun_market_address' onClick='this.setSelectionRange(0, this.value.length)' readonly='readonly' value='".$this->market_xun_address."'></h3></br>";
					echo "<div id='xun_market_address_qr'></div>";
					echo '<script type="text/javascript">
	new QRCode("xun_market_address_qr", "'.$this->market_xun_address.'");
	</script>';
					echo "<h3>The page will automatically refresh in <span id='timer'>07 minutes and 00 seconds</span></h3>";
				?>
					<script type="text/javascript">
					function startTimer(duration, display) {
					    var timer = duration, minutes, seconds;
					    setInterval(function () {
					        minutes = parseInt(timer / 60, 10)
					        seconds = parseInt(timer % 60, 10);
					        minutes = minutes < 10 ? "0" + minutes : minutes;
					        seconds = seconds < 10 ? "0" + seconds : seconds;
					        display.textContent = minutes + " minutes and " + seconds + " seconds";
					        if (--timer < 0) {
				        		 window.location.search += '&autoreload=1';
					        }
					    }, 1000);
					}
					window.onload = function () {
					    var fiveMinutes = 60 * 7,
				        display = document.querySelector('#timer');
					    startTimer(fiveMinutes, display);
					};
					</script>


				<?php
				}
            } elseif ($order->has_status( 'processing' )) {
            	echo "<h1>Payement recieved successfully</h1>";
            	if(isset($_GET['autoreload'])){
            		?>
            		<script type="text/javascript">
            			window.onload = function () {
            				alert("Payement recieved successfully, Thank you !");
            			}
            		</script>
            		<?php
            	}
            }
		}
	
	
		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		
			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}
	
	
		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
	
			$order = wc_get_order( $order_id );
			
			// Mark as on-hold (we're awaiting the payment)
			$order->update_status( 'on-hold', __( 'Awaiting xun payment', 'wc-gateway-xun' ) );
			
			// Reduce stock levels
			$order->reduce_order_stock();
			
			// Remove cart
			WC()->cart->empty_cart();
			
			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
		}
  } // end \WC_Gateway_xun class
}


add_filter( 'the_title', 'woo_personalize_order_received_title', 10, 2 );
function woo_personalize_order_received_title( $title, $id ) {
    if ( is_order_received_page() && get_the_ID() === $id ) {
	    global $wp;
        // Get the order. Line 9 to 17 are present in order_received() in includes/shortcodes/class-wc-shortcode-checkout.php file
        $order_id  = apply_filters( 'woocommerce_thankyou_order_id', absint( $wp->query_vars['order-received'] ) );
        $order_key = apply_filters( 'woocommerce_thankyou_order_key', empty( $_GET['key'] ) ? '' : wc_clean( $_GET['key'] ) );
        if ( $order_id > 0 ) {
            $order = wc_get_order( $order_id );
            if ( $order->get_order_key() != $order_key ) {
                $order = false;
            }
        }
        if ( isset ( $order ) ) {
            //$title = sprintf( "You are awesome, %s!", esc_html( $order->billing_first_name ) ); // use this for WooCommerce versions older then v2.7
	    $title = sprintf( "You are awesome, %s!", esc_html( $order->get_billing_first_name() ) ).$order_id;
        }
    }
    return $title;
}

// add_action( 'init', 'tesssts' );
// function tesssts(){
// 	echo get_xun_to_curency_rate();
// }


function get_xun_to_curency_rate(){
	// $xun_plugin_options=get_option('woocommerce_xun_gateway_settings');
	// $apiUrl=$xun_plugin_options['json_rpc_link'];
	// $apiuser=$xun_plugin_options['json_rpc_user'];
	// $apipw=$xun_plugin_options['json_rpc_password'];

	//get_woocommerce_currency();
	$url = "https://api.coingecko.com/api/v3/simple/price?ids=ultra-note&vs_currencies=".get_woocommerce_currency(); 
	$ch = curl_init();  
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt($ch, CURLOPT_URL, $url); 
	$result = curl_exec($ch); 
	curl_close($ch);
    $obj=json_decode($result, true);
	if (current(current($obj))!==null) {
		return current(current($obj));
	}else{
		return null;
	}    
}
add_action('woocommerce_checkout_update_order_meta',function( $order_id, $posted ) {
    $order = wc_get_order( $order_id );
    $total = $order->get_total();
    $xun_to_curency_rate=get_xun_to_curency_rate();
    if ($xun_to_curency_rate==null or $xun_to_curency_rate==false) {
    	    $order->update_meta_data( 'xun_to_pay', null );
    	    $order->save();
    	    return false;
    }
	$new_total = round($total/$xun_to_curency_rate, 0, PHP_ROUND_HALF_UP) + $order_id/100000;
    $order->update_meta_data( 'xun_to_pay', $new_total );
    if ( ! empty( $_POST['xun_to_message'] ) ) {
        update_post_meta( $order_id, 'xun_to_message', sanitize_text_field( $_POST['xun_to_message'] ) );
    }
    $order->save();
} , 10, 2);




add_action( 'woocommerce_checkout_before_customer_details', 'mahxun_add_xun_to_message_field' );
function mahxun_add_xun_to_message_field() {

    echo '<div id="xun_to_message_field"><h2>' . __('Your XUN Messaging Address') . '</h2>';

    woocommerce_form_field( 'xun_to_message', array(
        'type'          => 'text',
      	'class'         => array( 'xun_to_message' ),
      	'label'         => __( 'You will recieve confirmation messages to this XUN address' ),
      	'placeholder'   => __( 'Ultranote Address' ),
        'required'      => true,
    ), WC()->checkout->get_value( 'xun_to_message' ));

    echo '</div>';

}
/**
 * Process the checkout
 */
add_action('woocommerce_checkout_process', 'mahxun_custom_xunAddress_field_process');
function mahxun_custom_xunAddress_field_process() {
    // Check if set, if its not set add an error.
    if ( ! $_POST['xun_to_message'] )
        wc_add_notice( __( 'Please fill in your Ultranote Address.' ), 'error' );
}
// Update the order meta with field value
add_action( 'woocommerce_checkout_update_order_meta', 'my_custom_checkout_field_update_order_meta', 10, 1 );
function my_custom_checkout_field_update_order_meta( $order_id ) {
    if ( ! empty( $_POST['developer_name'] ) ) {
        update_post_meta( $order_id, 'Developer name', sanitize_text_field( $_POST['developer_name'] ) );
    }
}
















function execute_xunrpc($method='getStatus',$params=array()){
	$xun_plugin_options=get_option('woocommerce_xun_gateway_settings');
	$apiUrl=$xun_plugin_options['json_rpc_link'];
	$apiuser=$xun_plugin_options['json_rpc_user'];
	$apipw=$xun_plugin_options['json_rpc_password'];

	//$apiUrl = 'http://localhost:8070/json_rpc';
	$message_array = array('jsonrpc' => '2.0', 'id' => 1, 'method' => $method);
	if (count($params)>0) {
		$message_array['params']=$params;
	}
    $message = json_encode($message_array);
    $requestHeaders = [
        'Content-type: application/json'
    ];
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_USERPWD, $apiuser.":".$apipw);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
   
    $response = curl_exec($ch);
    curl_close($ch);
    $obj=json_decode($response, false);
    if (isset($obj->result)){
    	return $obj->result;
    }else{
    	return null;
    }
    
}
function get_current_xunBlock(){
	$response=execute_xunrpc('getStatus',[]);
    return 	$response->blockCount;
}
function get_current_xunBallance(){
	$xun_plugin_options=get_option('woocommerce_xun_gateway_settings');
	$marketaddress=$xun_plugin_options['market_xun_address'];
	$response=execute_xunrpc('getBalance',['address'=>$marketaddress]);
	if (!is_null($response->availableBalance)) {
		return 	$response->availableBalance/1000000;
	}else{
		return null;
	}
    
}
function get_new_xunTransactions($Starting_block,$Current_block){
	$range=(int)$Current_block-(int)$Starting_block;
	$response=execute_xunrpc('getTransactions',['firstBlockIndex'=>(int)$Starting_block,"blockCount">$range]);
	$transactions = array();
	if(isset($response->items)){
	    foreach ($response->items as $key => $item) {
	        if(count($item->transactions)>0){
	            $transactions[]=$item->transactions[0]->amount/1000000;
	        }
	    }
	}
	return 	$transactions;	
}
/*
function Send_xun_message($message,$address){
	$xun_plugin_options=get_option('woocommerce_xun_gateway_settings');
	$marketaddress=$xun_plugin_options['market_xun_address'];
	if ($message==1) {
		$text= $_SERVER['HTTP_HOST'].': Your order is received, now processing';
	}
	$parr=['addresses'=>[$marketaddress],"anonymity"=>0,"fee"=>0,"ttl"=>time()+10,"extra"=>"01fffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff04fffffffffff","text"=>$message,"transfers"=>[["amount"=>100,"address"=>$address]]];
	$response=execute_xunrpc('sendTransaction',$parr);
	return $response;
}
*/

function sendTransaction($from,$to,$ammount){
			$params=[
			"addresses"=>[$from],
				"anonymity"=> 2,
				"fee"=> 10000,
				"transfers"=>[
					[
						"amount"=>$ammount,
						"address"=>$to
					]
			]];
			$response=execute_xunrpc('sendTransaction',$params);
			return $response->transactionHash;
		}


/**
 * Custom currency and currency symbol
 */
add_filter( 'woocommerce_currencies', 'add_XUN_currency' );

function add_XUN_currency( $currencies ) {
     $currencies['XUN'] = __( 'Ultranote', 'woocommerce' );
     return $currencies;
}

add_filter('woocommerce_currency_symbol', 'add_XUN_currency_symbol', 10, 2);

function add_XUN_currency_symbol( $currency_symbol, $currency ) {
     switch( $currency ) {
          case 'XUN': $currency_symbol = 'XUN'; break;
     }
     return $currency_symbol;
}


add_action( 'init', 'CheckXunPayements' );
function CheckXunPayements(){
	WC()->mailer();

	//require_once( 'includes/class-wc_email_admins_processing_order.php' );
	//$order = wc_get_order( "429" );
	//echo "i";
	//do_action('woocommerce_order_status_on-hold_to_processing_notification',$order->get_id(),$order);
	// update transactions from blockchain
	// 1 get starting block from database
	$Starting_block = get_option( 'mahxun_starting_block', 330000 );
	// 2 get current block
	$Current_block = get_current_xunBlock(); //<function to create
	// 3 : read blockchain from 1 to 2 and get array of transactions
	$New_transactions = get_new_xunTransactions($Starting_block,$Current_block);//<function to create
	// 4 get transactions from database 
	$Saved_Transactions = get_option( 'mahxun_transactions', [] );
	// 5 save 4+3 to database
	$All_transactions = array_merge($Saved_Transactions, $New_transactions); 
	update_option( 'mahxun_transactions', $All_transactions );
	// update block number
	// 1 update block number to current block number to database
	update_option( 'mahxun_starting_block', $Current_block );

	// compare pending orders to 1
	// 1 get transactions from database
	if(!isset($All_transactions)){
		$All_transactions=get_option( 'mahxun_transactions', [] );
	}
	// 2 get list of waiting orders from database
	$args = array(
	    'status' => 'on-hold',
	);

	$orders = wc_get_orders( $args );
	// 3 if transaction = order then : change order status ; send message to client ; remove transaction from transactions.
	$Transactions_to_del = array();
	$thaks=false;
	foreach ($orders as $key => $order) {
		//echo $order->get_meta('xun_to_pay')."<br>";
		if(in_array($order->get_meta('xun_to_pay'), $All_transactions)){
			$thaks=true;
			$Transactions_to_del[]=$order->get_meta('xun_to_pay');
			$order->update_status( 'processing' );
			$order->save();
			do_action('woocommerce_order_status_on-hold_to_processing_notification',$order->get_id(),$order);
			//Send_xun_message(1,$order->get_meta('xun_to_message')); //<function to create
		}
	}

	/*
	if(isset($_GET['mai'])){
		$oo=$orders[0];
		do_action('woocommerce_order_status_on-hold_to_processing_notification',$oo->get_id(),$oo);
	}*/
	$All_transactions=array_diff($All_transactions, $Transactions_to_del);
	// 4 save transactions to database
	update_option( 'mahxun_transactions', $All_transactions );
}




add_action('wp_enqueue_scripts','qrcode_gen_js_init');
function qrcode_gen_js_init() {
    wp_enqueue_script( 'qrcode_gen_js', plugins_url( '/js/qrcode.min.js', __FILE__ ));
}


// Dashboard Widget
add_action('wp_dashboard_setup', 'xun_dashboard_widgets');
function xun_dashboard_widgets() {
	global $wp_meta_boxes;
	wp_add_dashboard_widget('xun_help_widget', 'Current Ultranote (XUN) Wallet Balance', 'xun_dashboard_help');
}
 
function xun_dashboard_help() {
	$xun_plugin_options=get_option('woocommerce_xun_gateway_settings');
	$marketaddress=$xun_plugin_options['market_xun_address'];
	$xunBal=get_current_xunBallance();
	if (!is_null($xunBal)) {
		$xunBallance=$xunBal." XUN";
	}else{
		$xunBallance="RPC connection Failed.";
	}
	echo '<style type="text/css">#xun_help_widget{text-align: center;background:url('.plugins_url( '/images/Ultranote_Bg.png', __FILE__ ).') #233fbb;background-size: cover;padding-bottom: 20px;}#xun_help_widget *{color:white;} #sendxun input[type="checkbox"],#sendxun input[type="checkbox"]+div{display:none;} #sendxun input:checked+div{display:block;} #sendxun input[type="text"],#sendxun textarea {width: 100%; color:black; text-align:center;}</style>';
	echo '<img width="100%" src="'.plugins_url( '/images/UltraNote_Logo.png', __FILE__ ).'"/>';
	echo '<h2>Current Market Address :</h2><div style="word-break: break-all;">';
	echo "<textarea rows='3' style='word-break: break-all; width:100%;background:transparent;border-width:0;resize: none;overflow: hidden;' name='xun_market_address' onClick='this.setSelectionRange(0, this.value.length)' readonly='readonly'>".$marketaddress."</textarea>";
	echo '</div><h2>Current UltraNote Balance :</h2>';
	echo '<span style="width: 100%;font-size: 30px;text-align: center;display: inline-block;padding: 5px 0;">'.$xunBallance.'</span>';
	echo '<div id="sendxun"><label for="showit"><h2 style="margin:25px;padding:5px 5px 10px; border:1px solid white; border-radius:5px;">Send XUN to Another Address</h2></label><input type="checkbox" id="showit"><div><form method="post"><h2>Address:</h2><br><textarea name="xun_address" rows="2"></textarea><br><h2>Amount (Xun):</h2><br><input name="ammount" type="text"><input style="color:black; margin: 25px 0 0; border-radius:2px;border: 0;box-shadow: none;padding: 10px 20px;" type="submit" value="Send"></form></div></div>';
}
add_action( 'init', 'dashboard_idget_actions' );
function dashboard_idget_actions(){
	if (isset($_POST['xun_address']) and isset($_POST['ammount'])){

		if (!is_numeric($_POST['ammount']) or $_POST['ammount']<0) {
			add_action( 'admin_notices', 'widget_xun_notnumeric' );
			return false;
		}

		$xun_plugin_options=get_option('woocommerce_xun_gateway_settings');
		$marketaddress=$xun_plugin_options['market_xun_address'];
		$xunBal=get_current_xunBallance();
		if ($_POST['ammount']>get_current_xunBallance()) {
			add_action( 'admin_notices', 'widget_xun_toobig' );
			return false;	
		}
		if(is_null(sendTransaction($marketaddress,$_POST['xun_address'],$_POST['ammount']*1000000) )){
			add_action( 'admin_notices', 'widget_xun_unknown_error' );
			return false;
		}
		add_action( 'admin_notices', 'widget_xun_sent' );
	}	
}
function widget_xun_sent(){
	?><div class="updated notice"><p>You successfully sent the Ultranote; The transfer will be confirmed within the next 10 minutes</p></div><?php
}
function widget_xun_notnumeric(){
	?><div class="error notice"><p>Please enter a valid ammount !</p></div><?php
}
function widget_xun_toobig(){
	?><div class="error notice"><p>The ammount you selected is too big !</p></div><?php
}
function widget_xun_unknown_error(){
	?><div class="error notice"><p>An error occurred, please try again !</p></div><?php
}
// End Dashboard Widget



// Add customer address to the order email.
add_action( 'woocommerce_email_after_order_table', 'action_woocommerce_email_after_order_tablex', 10, 4 ); 
function action_woocommerce_email_after_order_tablex( $order, $sent_to_admin, $plain_text, $email ) { 
	$xun_plugin_options=get_option('woocommerce_xun_gateway_settings');
	$marketaddress=$xun_plugin_options['market_xun_address'];
    ?>
    <p><h2 style='color:#410dec;display:block;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;font-size:18px;font-weight:bold;line-height:130%;margin:0 0 18px;text-align:left'>Customer Xun Address :</h2><?php echo $order->get_meta('xun_to_message'); ?></p><p>------------------------------</p></br> 
<?php
}; 


// Woocommerce email template customisation
function add_expedited_order_woocommerce_email( $email_classes ) {
	//WC()->mailer();
	require_once( 'includes/class-wc_email_admins_processing_order.php' );
	$email_classes['WC_Email_Admins_Processing_Order'] = new WC_Email_Admins_Processing_Order();
	return $email_classes;
}
add_filter( 'woocommerce_email_classes', 'add_expedited_order_woocommerce_email' );

add_filter( 'woocommerce_locate_template', 'override_woocommerce_template', 10, 3 );
function override_woocommerce_template( $template, $template_name, $template_path ) {
    $template_directory = untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/includes/woocommerce_templates/';
    $path = $template_directory . $template_name;
    return file_exists( $path ) ? $path : $template;
}
// End Woocommerce email template customisation
