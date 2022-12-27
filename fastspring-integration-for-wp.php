<?php
/*
Plugin Name: FastSpring Integration For WP
Description: FastSpring gateway for any wordpress stores and forms
Version: 1.0
Text Domain: fastSpring-integration-for-wp
Domain Path: /languages
Author: Integrator
Author URI: https://integrator.dev
Donate link: https://www.paypal.me/Kaludi/25
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) exit;

define('FSIFWP_VERSION', '1.0');
define('FSIFWP_DIR_NAME_FILE', __FILE__ );
define('FSIFWP_DIR_NAME', dirname( __FILE__ ));
define('FSIFWP_DIR_URI', plugin_dir_url(__FILE__));
define('FSIFWP_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ));
define('FSIFWP_PLUGIN_BASENAME', plugin_basename(__FILE__));

define('FSIFWP_API_ENDPOINT', 'https://api.fastspring.com/');


require_once FSIFWP_PLUGIN_DIR_PATH. 'includes/common.php';
require_once FSIFWP_PLUGIN_DIR_PATH. 'includes/admin.php';
require_once FSIFWP_PLUGIN_DIR_PATH. 'includes/webhook.php';


// registers the gateway
function fsifwp_register_fs_edd_payment_gateway($gateways){

    $gateways['fastspring'] = array(
        'admin_label'    => esc_htmlesc_html__('FastSpring', 'fastSpring-integration-for-wp'),
        'checkout_label' => esc_htmlesc_html__('FastSpring', 'fastSpring-integration-for-wp')
    );

    return $gateways;
}

add_filter('edd_payment_gateways', 'fsifwp_register_fs_edd_payment_gateway');

add_action('edd_fastspring_cc_form', '__return_false');

add_action('admin_notices', 'fsifwp_fs_edd_admin_notices');

function fsifwp_fs_edd_admin_notices(){

    if (! is_plugin_active('easy-digital-downloads/easy-digital-downloads.php')) {
          echo '<div id="notice" class="error"><p><b>'.esc_htmlesc_html__('Easy Digital Downloads Payment Gateway by FastSpring', 'fastSpring-integration-for-wp').'</b> '.esc_htmlesc_html__('add-on requires ', 'fastSpring-integration-for-wp').'<a href="https://easydigitaldownloads.com" target="_new">'.esc_htmlesc_html__('Easy Digital Downloads', 'fastSpring-integration-for-wp').'</a>'.' '.esc_htmlesc_html__('plugin. Please install and activate it.', 'fastSpring-integration-for-wp').'</p></div>';
    }
    elseif (! function_exists('curl_init')) {        
          echo '<div id="notice" class="error"><p><b>'.esc_htmlesc_html__('Easy Digital Downloads Payment Gateway by FastSpring', 'fastSpring-integration-for-wp').'</b> '.esc_htmlesc_html__('requires ', 'fastSpring-integration-for-wp').' '.esc_htmlesc_html__('PHP CURL.', 'fastSpring-integration-for-wp'). ' '.esc_htmlesc_html__(' Please install/enable php_curl!', 'fastSpring-integration-for-wp').'</p></div>';
    }
    
}

function fsifwp_get_edd_customer_data($purchase_data){

    $language   = strtoupper(substr(get_bloginfo('language') , 0, 2));
    $firstname  = $purchase_data['user_info']['first_name'];
    $lastname   = $purchase_data['user_info']['last_name'];
    $email      = isset($purchase_data['user_email']) ? $purchase_data['user_email'] : $purchase_data['user_info']['email'];

    if (empty($firstname) || empty($lastname)){
    
        $name = $firstname.$lastname;
        list($firstname, $lastname) = preg_match('/\s/', $name) ? explode(' ', $name, 2) : array($name, $name);
    }

    $customer_data = array(
        'name'      => $firstname . ' '. $lastname,
        'firstname' => $firstname,
        'lastname'  => $lastname,
        'email'     => $email,
        'currency'  => edd_get_currency(),
        'use_utf8'  => 1,
        'lang'      => $language
    );

    return $customer_data;
}

/**
* Return Wordpress plugin settings
* @param  string $key setting key
* @return mixed setting value
*/
function fsifwp_edd_get_setting($key)
{
    global $edd_options;

    if(isset($edd_options[$key]))
    {
        return $edd_options[$key];
    }
    return;
}


function fsifwp_edd_process_payment($purchase_data)
{       
    global $edd_options; 

    $price_id = $product_path = '';

    $download_id = $purchase_data['downloads'][0]['id'];    

    if(isset($purchase_data['downloads'][0]['options']['price_id'])){
      $price_id    = $purchase_data['downloads'][0]['options']['price_id'];
    }    

    $product_path_info = get_post_meta($download_id, 'product_path', true);        

    if(isset($product_path_info[$price_id])){
            $product_path = $product_path_info[$price_id];    
    }else{
            $product_path = $product_path_info[0];
    }
    
    $customer_data      = fsifwp_get_edd_customer_data($purchase_data);
    $mod_version        = FSIFWP_VERSION;
    $ajax_url           = admin_url( 'admin-ajax.php' ); 
    $fs_security_nonce  = wp_create_nonce('fs_ajax_check_nonce');

    // Config data
    $config_data = array(
        'return_url'            => get_permalink(fsifwp_edd_get_setting('success_page')),
        'return_method'         => 'POST',
        'error_return_url'      => edd_get_checkout_uri(),      
        'error_return_method'   => 'POST'
    );

    /**********************************
    * set up the payment details      *
    **********************************/
    
    $payment = array(
        'price'         => $purchase_data['price'],
        'date'          => $purchase_data['date'],
        'user_email'    => $purchase_data['user_email'],
        'purchase_key'  => $purchase_data['purchase_key'],
        'currency'      => fsifwp_edd_get_setting('currency'),
        'downloads'     => $purchase_data['downloads'],
        'cart_details'  => $purchase_data['cart_details'],
        'user_info'     => $purchase_data['user_info'],
        'status'        => 'pending'
    );

    $order_no = edd_insert_payment($payment);
    
    $purchase_data = array(       
        'amount'                      => $payment['price'] * 100,
        'merchant_order'              => $order_no,
        'currency'                    => $payment['currency'],
        'email'                       => $payment['user_info']['email'],
        'name'                        => $customer_data['name'],
        'firstname'                   => $customer_data['firstname'],
        'lastname'                    => $customer_data['lastname'],
        'config'                      => $config_data,      
        'callback_url'                => $config_data['return_url'] . "?gateway=fastspring_gateway&merchant_order_id=$order_no",
        'integration'                 => 'edd',
        'integration_version'         => $mod_version,
        'integration_parent_version'  => EDD_VERSION,
    );

   
    $errors = edd_get_errors();

    if (!$errors)
    {
        $html = '
        <!doctype html>
        <html>
          <head>
            <title>FastSpring</title>
            <meta name="viewport" content="user-scalable=no,width=device-width,initial-scale=1,maximum-scale=1">
            <meta http-equiv="pragma" content="no-cache">
            <meta http-equiv="cache-control" content="no-cache">
            <meta http-equiv="expires" content="0">
            <style>            
            #fs-lds-default {
                display: none;
                position: absolute;
                width: 80px;
                height: 80px;
                top: 50%;
                left: 50%;
              }
              #fs-lds-default div {
                position: absolute;
                width: 6px;
                height: 6px;
                background: #fff;
                border-radius: 50%;
                animation: lds-default 1.2s linear infinite;
              }
              #fs-lds-default div:nth-child(1) {
                animation-delay: 0s;
                top: 37px;
                left: 66px;
              }
              #fs-lds-default div:nth-child(2) {
                animation-delay: -0.1s;
                top: 22px;
                left: 62px;
              }
              #fs-lds-default div:nth-child(3) {
                animation-delay: -0.2s;
                top: 11px;
                left: 52px;
              }
              #fs-lds-default div:nth-child(4) {
                animation-delay: -0.3s;
                top: 7px;
                left: 37px;
              }
              #fs-lds-default div:nth-child(5) {
                animation-delay: -0.4s;
                top: 11px;
                left: 22px;
              }
              #fs-lds-default div:nth-child(6) {
                animation-delay: -0.5s;
                top: 22px;
                left: 11px;
              }
              #fs-lds-default div:nth-child(7) {
                animation-delay: -0.6s;
                top: 37px;
                left: 7px;
              }
              #fs-lds-default div:nth-child(8) {
                animation-delay: -0.7s;
                top: 52px;
                left: 11px;
              }
              #fs-lds-default div:nth-child(9) {
                animation-delay: -0.8s;
                top: 62px;
                left: 22px;
              }
              #fs-lds-default div:nth-child(10) {
                animation-delay: -0.9s;
                top: 66px;
                left: 37px;
              }
              #fs-lds-default div:nth-child(11) {
                animation-delay: -1s;
                top: 62px;
                left: 52px;
              }
              #fs-lds-default div:nth-child(12) {
                animation-delay: -1.1s;
                top: 52px;
                left: 62px;
              }
              @keyframes lds-default {
                0%, 20%, 80%, 100% {
                  transform: scale(1);
                }
                50% {
                  transform: scale(1.5);
                }
              }
                            
            </style>

            <script
                id="fsc-api"
                src="https://d1f8f9xcsvx3ha.cloudfront.net/sbl/0.8.7/fastspring-builder.min.js"
                type="text/javascript"
                data-storefront="'.esc_attr($edd_options['storefront']).'"	
                data-debug="false"	
                data-data-callback="dataCallback"
                data-popup-closed="onPopupClose"
                data-error-callback="errorCallback"		
                data-popup-webhook-received="popupWebhookReceived"
                >
            </script>
            <script>
        //Operation js functions starts here
       
        function popupWebhookReceived(orderReference){
          
          if(orderReference){

           var body = {"payment_id": '.esc_attr($order_no).', "fs_security_nonce": "'.esc_attr($fs_security_nonce).'", "order_reference": orderReference };

           var xhttp = new XMLHttpRequest();
           xhttp.open("POST", "'.esc_url($ajax_url).'?action=fsifwp_edd_save_order_data", true);                
           xhttp.setRequestHeader("Content-Type", "application/json");               
           xhttp.onreadystatechange = function() {

             if (this.readyState == 4 && this.status == 200) {              
                 console.log(JSON.parse(this.responseText));                                    
             }
             };

           xhttp.send(JSON.stringify(body));
           fastspring.builder.reset();

          }

       }

        function onPopupClose(orderReference){
                        
            if(orderReference){
                
            document.body.style.backgroundColor = "#7f7f7f";  
            document.getElementById("fs-lds-default").style.display = "inline-block";     

            window.location.replace("'.esc_url(edd_get_success_page_uri()).'");
                
            fastspring.builder.reset();
              
            }else{                
                window.location.href = "' . esc_url(edd_get_checkout_uri()) . '";
            }
          
        }

        function errorCallback (code, string) {
          console.log("Error: ", code, string);
          window.location.href = "' . esc_url(edd_get_checkout_uri()) . '";
      }

        //Operation js functions ends here


        //Fire once the page is loaded.
        document.addEventListener("DOMContentLoaded", function()
		{
                            
			s =
			{
				//Reset the cart session  to remove everything added to the cart previously.
				"reset":true,
				//Define the product path(s) and the quantity to add.
				"products" : [
					{
						"path":"'.esc_attr($product_path).'",
						"quantity":1
					}					
				],
				//Optionally apply a coupon
				//"coupon":"FREE",
				//Optionally apply customer information to the order
				"paymentContact":{  
					"email": "' . esc_attr($purchase_data['email']) . '",
					"firstName":"' . esc_attr($purchase_data['firstname']) . '", 
					"lastName":"' . esc_attr($purchase_data['lastname']) . '", 															
				},
				//Specify that when this JSON object is pushed to the Store Builder Library to begin checkout process.
				"checkout":true
			}
			//Push the above JSON object to the Store Builder Library.
			fastspring.builder.push(s);
		});
        </script>
          </head>
          <body>
          <div id="fs-lds-default"><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div>
          </body>
        </html>';

        //This is static html and whatever dynamic inside it have been escaped above.
        echo $html;
        exit;
    }
}

add_action('edd_gateway_fastspring', 'fsifwp_edd_process_payment');

function fsifwp_edd_add_settings($settings)
{
   
    $fastspring_settings = array(
        array(
            'id'   => 'fastspring_settings',
            'name' => '<strong>' . esc_html__('FastSpring', 'fastSpring-integration-for-wp') . '</strong>',
            'desc' => esc_html__('Configure the FastSpring settings', 'fastSpring-integration-for-wp'),
            'type' => 'header'
        ),
        array(
            'id'   => 'title',
            'name' => esc_html__('Title:', 'fastSpring-integration-for-wp'),
            'desc' => esc_html__('This controls the title which the user sees during checkout.', 'fastSpring-integration-for-wp'),
            'type' => 'text',
            'size' => 'regular'
        ),
        array(
            'id'   => 'description',
            'name' => esc_html__('Description', 'fastSpring-integration-for-wp'),
            'type' => 'textarea',
            'desc' => esc_html__('This controls the description which the user sees during checkout.', 'fastSpring-integration-for-wp'),
        ),
        array(
            'id'   => 'username',
            'name' => esc_html__('User Name', 'fastspring'),
            'desc' => esc_html__('The User name and password can be generated from "Integrations" section of FastSpring Dashboard. Use test or live for test or live mode.', 'fastSpring-integration-for-wp'),
            'type' => 'text',
            'size' => 'regular'
        ),
        array(
            'id'   => 'password',
            'name' => esc_html__('Password', 'fastSpring-integration-for-wp'),
            'desc' => esc_html__('The User name and password can be generated from "Integrations" section of FastSpring Dashboard. Use test or live for test or live mode.', 'fastSpring-integration-for-wp'),
            'type' => 'text',
            'size' => 'regular'
        ),
        array(
            'id'   => 'storefront',
            'name' => esc_html__('Popup Data Storefront ', 'fastSpring-integration-for-wp'),
            'desc' => esc_html__('The Popup Storefront URL can be got from "Storefronts" section of FastSpring Dashboard. Use test or live for test or live mode.', 'fastSpring-integration-for-wp'),
            'type' => 'text',
            'size' => 'regular'
        )        
    );

    return array_merge($settings, $fastspring_settings);
}

add_filter('edd_settings_gateways', 'fsifwp_edd_add_settings');

function fsifwp_edd_order_reference_db_table(){
    global $wpdb;

    $table_name = $wpdb->prefix . "fsifwp_edd_order_reference"; 
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      status tinyint(1) NOT NULL,
      transaction_id text NOT NULL,
      PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

}

//create a order transaction details in db.
register_activation_hook( __FILE__, 'fsifwp_edd_order_reference_db_table' );