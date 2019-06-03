<?php
/*
Plugin Name: EDD - bhartipay Gateway
Plugin URL: http://easydigitaldownloads.com/extension/bhartipay-gateway
Description: A bhartipay gateway for Easy Digital Downloads
Version: 1.0
Author: Rohit Kr Singh
Author URI: https://www.bhartipay.com/
Contributors: mordauk
*/

 include('bppg_helper.php');

// registers the gateway
function pw_edd_register_gateway($gateways) {
	$gateways['bhartipay_gateway'] = array('admin_label' => 'bhartipay Gateway', 'checkout_label' => __('bhartipay Gateway', 'pw_edd'));
	return $gateways;	
}
add_filter('edd_payment_gateways', 'pw_edd_register_gateway');

function pw_edd_bhartipay_gateway_cc_form() {
	// register the action to remove default CC form
	return;
}
add_action('edd_bhartipay_gateway_cc_form', 'pw_edd_bhartipay_gateway_cc_form');

// processes the payment
function pw_edd_process_payment($purchase_data) {

	global $edd_options;

	/**********************************
	* check for errors here
	**********************************/
	

	
	// check for any stored errors
	$errors = edd_get_errors();
	if(!$errors) {

		$purchase_summary = edd_get_purchase_summary($purchase_data);

		/**********************************
		* setup the payment details
		**********************************/

		$payment = array( 
			'price'        => $purchase_data['price'], 
			'date'         => $purchase_data['date'], 
			'user_email'   => $purchase_data['user_email'],
			'purchase_key' => $purchase_data['purchase_key'],
			'currency'     => $edd_options['currency'],
			'downloads'    => $purchase_data['downloads'],
			'cart_details' => $purchase_data['cart_details'],
			'user_info'    => $purchase_data['user_info'],
			'status'       => 'pending',
			
		);
	
		// record the pending payment
		$payment = edd_insert_payment($payment);
        $order_id=$payment;

		
		$merchant_payment_confirmed = false;

			

			$salt=$edd_options['salt_id'];
			
		    if(isset($edd_options['live_api_key'])){
               $pay_id=$edd_options['live_api_key'];
		    }

		    else{
		    	 $pay_id=$edd_options['test_api_key'];
		    }


			$return_url=$edd_options['return_url'] ;
			$transaction_url=$edd_options['transaction_url'] ;

		    //purchase summary
		    $purchase_summary = edd_get_purchase_summary( $purchase_data );
		    $total_amount= $purchase_data['price'];
		    $user_data=$purchase_data['user_info'];
		    //print_r($user_data);
            $user_email= $user_data['email'];
		    $user_name= $user_data['first_name']." ".$user_data['last_name'];

			$pg_transaction = new BPPGModule;
			$pg_transaction->setPayId("$pay_id");
			$pg_transaction->setPgRequestUrl("$transaction_url");
			@$pg_transaction->setSalt("$salt");
		
			$pg_transaction->setReturnUrl("$return_url");
			$pg_transaction->setCurrencyCode(356);
			$pg_transaction->setTxnType('SALE');
			$pg_transaction->setOrderId("$order_id");
			@$pg_transaction->setCustEmail("$user_email");
			@$pg_transaction->setCustName("$user_name");
			@$pg_transaction->setCustPhone("Nan");
			@$pg_transaction->setAmount(("$total_amount")*100); // convert to Rupee from Paisa
			@$pg_transaction->setProductDesc("Demo");
			//@$pg_transaction->setCustStreetAddress1("1x xxx");
			//@$pg_transaction->setCustCity("xxxxx");
			//@$pg_transaction->setCustState("xxxxxxxx");
			//@$pg_transaction->setCustCountry("India");
			//@$pg_transaction->setCustZip(1100xx);
			//@$pg_transaction->setCustShipStreetAddress1("1x xxxx");
			//@$pg_transaction->setCustShipCity("xxxx");
			//@$pg_transaction->setCustShipState("xxxxx");
			//@$pg_transaction->setCustShipCountry("India");
			//@$pg_transaction->setCustShipZip(1100xx);
			//@$pg_transaction->setCustShipPhone(xxxxxxxxxx);
			//$trans_id = edd_get_payment_transaction_id( $payment_id );@$pg_transaction->setCustShipName("Rxxxx xxxxx");
			// if form is submitted

			    $postdata = $pg_transaction->createTransactionRequest();
			    $pg_transaction->redirectForm($postdata);
 
               $valid = $pg_transaction->validateResponse($_REQUEST);
               $response = array('POST' => $_POST, 'GET' => $_GET, 'IS_VALID' => $valid);

		// if the merchant payment is complete, set a flag
		$merchant_payment_confirmed = true;		
		
		if($merchant_payment_confirmed) { // this is used when processing credit cards on site
			// once a transaction is successful, set the purchase to complete
			edd_update_payment_status($payment, 'complete');
			
			// go to the success page			
			edd_send_to_success_page();
			
		} else {
			$fail = true; // payment wasn't recorded
			
			edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);

					}
		
	} else {
		$fail = true; // errors were detected
		edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
		
	}
	
	if( $fail !== false ) {
		// if errors are present, send the user back to the purchase page so they can be corrected
		edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
	}
}
add_action('edd_gateway_bhartipay_gateway', 'pw_edd_process_payment');






//Return Response
function edd_listen_for_bhartipay_gateway() {

	if ( isset( $_POST['RESPONSE_CODE'] ) && $_POST['CUST_EMAIL'] != "" ) {
            do_action( 'edd_verify_bhartipay_gateway' );    
                   
	}
}
add_action( 'init', 'edd_listen_for_bhartipay_gateway' );



function edd_process_bhartipay_gateway() {
    global $edd_options;
    $payment = edd_insert_payment($payment);
    $order_id=$payment;
          
		  if($_POST['STATUS']!='Captured'){
		            edd_send_back_to_checkout( '?payment-mode=bhartipay_gateway' );            
		        }
           
           if($_POST['STATUS']=='Captured')
					{
						$payment_meta   = edd_get_payment_meta( $order_id );
						edd_insert_payment_note( $order_id, sprintf( __( 'Thank you for your order . Your transaction has been successful. bhartipay Transaction ID: %s', 'edd' ) , $_POST['STATUS'] ) );
						edd_set_payment_transaction_id( $order_id, $_POST['STATUS'] );
						edd_update_payment_status( $order_id, 'complete' );
						edd_update_payment_status('complete' );
						edd_empty_cart();
						edd_send_to_success_page();
					}
					else{
						edd_record_gateway_error( __( 'bhartipay Error', 'edd' ), sprintf( __( 'It seems some issue in server to server communication. Kindly connect with administrator.', 'edd' ), '' ), $payment_id );
						edd_update_payment_status( $payment_id, 'failed' );
						edd_insert_payment_note( $payment_id, sprintf( __( 'It seems some issue in server to server communication. Kindly connect with administrator.', 'edd' ), '' ) );
						wp_redirect( '?page_id=6&payment-mode=bhartipay_gateway' );
					}


 
	
}
add_action( 'edd_verify_bhartipay_gateway', 'edd_process_bhartipay_gateway' );

//Return Response handle








// adds the settings to the Payment Gateways section
function pw_edd_add_settings($settings) {

	$bhartipay_gateway_settings = array(
		array(
			'id' => 'bhartipay_gateway_settings',
			'name' => '<strong>' . __('bhartipay Gateway Settings', 'pw_edd') . '</strong>',
			'desc' => __('Configure the gateway settings', 'pw_edd'),
			'type' => 'header'
		),
		array(
			'id' => 'live_api_key',
			'name' => __('Live API Key', 'pw_edd'),
			'desc' => __('Enter your live API key, found in your gateway Account Settings', 'pw_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'test_api_key',
			'name' => __('Test API Key', 'pw_edd'),
			'desc' => __('Enter your test API key, found in your gateway Account Settings', 'pw_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'salt_id',
			'name' => __('Salt Key', 'pw_edd'),
			'desc' => __('Enter your Salt key, found in your gateway Account Settings', 'pw_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'transaction_url',
			'name' => __('Transaction URL', 'pw_edd'),
			'desc' => __('Transaction URL Provided by Bhartipay', 'pw_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'return_url',
			'name' => __('Return URL', 'pw_edd'),
			'desc' => __('Return URL Provided by merchant', 'pw_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'merchant_name',
			'name' => __('Merchant Name', 'pw_edd'),
			'desc' => __('Merchant Name Provided by merchant', 'pw_edd'),
			'type' => 'text',
			'size' => 'regular'
		)

	);
	

	return array_merge($settings, $bhartipay_gateway_settings);	
}
add_filter('edd_settings_gateways', 'pw_edd_add_settings');

