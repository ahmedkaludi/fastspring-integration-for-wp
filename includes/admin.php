<?php

if (!defined('ABSPATH')) exit;

add_action( 'admin_enqueue_scripts', 'fsifwp_enqueue_scripts' );

function fsifwp_enqueue_scripts( $hook ) {
    
    if( $hook == 'download_page_edd-payment-history' ){

        wp_register_script( 'fsifwp-admin-script', FSIFWP_DIR_URI . 'assets/admin.js', array('jquery'), FSIFWP_VERSION , true );                    
        wp_localize_script( 'fsifwp-admin-script', 'fsifwp_localize_data', array() );    
        wp_enqueue_script( 'fsifwp-admin-script' );

    }
    
}

function fsifwp_sync_meta_box_callback($post){

    $product_path = get_post_meta($post->ID, 'product_path', true);

    if(empty($product_path)){
        echo '<strong> <span style="color: #f2af17;" class="dashicons dashicons-warning"></span>'.esc_html__('Download is not sync with FastSpring. Please click update button or something went wrong on updation. Please check product name. It can not contain any special charactore or space at the end of product name', 'fastSpring-integration-for-wp').'</strong>';
    }else{
        echo '<strong>'.esc_html__('Download is fully sync with FastSpring', 'fastSpring-integration-for-wp').' </strong>';
    }

}

function fsifwp_edd_sync_metabox() {

    add_meta_box( 
        'fsifwp_edd_sync_meta_box', 
        'FastSpring', 
        'fsifwp_sync_meta_box_callback', 
        'download', 
        'side', 'high' 
    );

}

add_action( 'add_meta_boxes_download', 'fsifwp_edd_sync_metabox' );

add_action('edd_save_download', 'fsifwp_save_edd_subscription', 10, 2);

function fsifwp_save_edd_subscription( $download_id , $post ){
            
    $parent_product = array();
    
    $download = new EDD_Download( $download_id  );  

    $period        = '';
    $period_length = 0;

    if(!edd_has_variable_prices( $download_id ) && function_exists('EDD_Recurring')){

        $period_data   = edd_recurring()->get_period_single( $download_id );

        if(!empty($period_data)){

            $period        = 'year';
            $period_length = 1;

            if($period_data == 'quarter'){
                $period        = 'year';
                $period_length = '3';
            }else if($period_data == 'semi-year'){
                $period        = 'year';
                $period_length = '2';
            }else{
                $period        = $period_data;
                $period_length = 1;
            }
    
        }

    }
                    
    $parent_product['product']                           = $download->post_name;
    $parent_product['display']['en']                     = $download->post_name;
    $parent_product['description']['summary']['en']      = $download->post_excerpt;    
    $parent_product['description']['full']['en']         = $download->post_content;      
    $parent_product['format']                            = 'digital';
    $parent_product['taxcode']                           = 'DC020502';
    if($period_data != 'never' && $period_length > 0 && $period){
        $parent_product['pricing']['interval']               = $period;
        $parent_product['pricing']['intervalLength']         = $period_length;
    }
    $parent_product['pricing']['quantityBehavior']       = 'hide';
    $parent_product['pricing']['quantityDefault']        = 1;
    $parent_product['pricing']['price']['USD']           = $download->get_price();  
       
    $products['products'][] = $parent_product;

    if(edd_has_variable_prices( $download_id )){

        $child_product = array();

        $prices = edd_get_variable_prices($download_id);
        
        foreach($prices as $value){
                        
            if(isset($value['period'])){

                $period        = 'year';
                $period_length = 1;

                if($value['period'] == 'quarter'){
                    $period        = 'year';
                    $period_length = '3';
                }else if($value['period'] == 'semi-year'){
                    $period        = 'year';
                    $period_length = '2';
                }else{
                    $period        = $value['period'];
                    $period_length = 1;
                }

            }
                        
            $p_array = [];

            $value['name'] = str_replace(' ', '-', trim($value['name']));  
			$vproduct_name = $download->post_name.'-'.$value['name'];
			$vproduct_name = str_replace('--', '-', $vproduct_name);
			$vproduct_name = strtolower(str_replace('--', '-', $vproduct_name));         

            $p_array['product']                           = $vproduct_name;
            $p_array['display']['en']                     = $vproduct_name;
            $p_array['description']['summary']['en']      = $download->post_excerpt;    
            $p_array['description']['full']['en']         = $download->post_content;      
            $p_array['format']                            = 'digital';
            $p_array['taxcode']                           = 'DC020502';            

            if($period_length > 0 && $period){
                $p_array['pricing']['interval']               = $period;
                $p_array['pricing']['intervalLength']         = $period_length;
            }
            
            $p_array['pricing']['quantityBehavior']       = 'hide';
            $p_array['pricing']['quantityDefault']        = 1;
            $p_array['pricing']['price']['USD']           = $value['amount'];  

            $child_product[] = $p_array;

        }
        
        $products['products'] = array_merge($products['products'], $child_product);
        
    }
        
    $url    = FSIFWP_API_ENDPOINT.'products';
    $result = fsifwp_edd_post_request($url, $products);
    
    if(isset($result['products']) && !empty($result['products'])) {

        $product_path = array();

        foreach( $result['products'] as $value ){

            if($value['result'] == 'success'){
                $product_path[] = sanitize_text_field($value['product']);
            }
            
        }

        update_post_meta($download_id, 'product_path', $product_path);
    }else{
        delete_post_meta($download_id, 'product_path');
    }
        
}

add_action( 'wp_ajax_fsifwp_edd_save_order_data', 'fsifwp_edd_save_order_data_func' );
add_action( 'wp_ajax_nopriv_fsifwp_edd_save_order_data', 'fsifwp_edd_save_order_data_func' ); 

function fsifwp_edd_save_order_data_func(){
                 
    $body     = file_get_contents("php://input");
    $body_arr = json_decode($body, true);
    
    $parent_payment_id    =    $body_arr['payment_id'];
    $fs_security_nonce    =    $body_arr['fs_security_nonce']; 
    $order_reference      =    $body_arr['order_reference']; 

    // if ( ! is_user_logged_in() ) {
    //     echo 'You must be logged in to purchase a subscription';die;
    // }

    if(!wp_verify_nonce($fs_security_nonce, 'fs_ajax_check_nonce')){
        return;
    }     
     if(!empty($order_reference['reference'])){
            $payment                 = new EDD_Payment( $parent_payment_id );
            $payment->transaction_id = $order_reference['reference'];
            $payment->save();							
     } 

      // fetch transaction_id from db and complete pending order.
    global $wpdb;
    $fs_table_name = $wpdb->prefix . "fsifwp_edd_order_reference"; 
    $results = $wpdb->get_results( "SELECT * FROM $fs_table_name"); 

    foreach ($results as $key => $result) {
        
       $transaction_id = $result->transaction_id;

        if($transaction_id){

            $payment_id = edd_get_purchase_id_by_transaction_id( $transaction_id );
            
            if($payment_id){
                            
                $payment = new EDD_Payment( $payment_id );
                if('publish' != $payment->status){
                    $payment->status = 'publish';
                    $payment->save();   
                    $payment->add_note( sprintf( __( 'Order Placed successfully via FastSpring. ( data fetched from database )', ' edd-recurring' ), $transaction_id ) ); 
                }   

                //delete the record from db if payment id status is completed as we don't need it.
                if('publish' == $payment->status){
                    $wpdb->delete( $fs_table_name, array( 'transaction_id' => $transaction_id ) );
                }                    
            }
        }
    }
        
     wp_send_json( array('status' => 'Updated') );
     wp_die();
    
}

add_action('edd_subscription_status_change', 'fsifwp_edd_recurring_update_subscription', 10, 3);

function fsifwp_edd_recurring_update_subscription( $old_status, $new_status, $instance ){
        
    if($instance->gateway == 'fastspring'){
                
        $subscription_id = $instance->transaction_id;

        if($subscription_id){

            if($new_status == 'cancelled' ){           

                $cancel_url    = FSIFWP_API_ENDPOINT.'subscriptions/'.$subscription_id;
                fsifwp_edd_delete_request($cancel_url);            
            }

            if( $new_status == 'active' && $old_status != 'active' ){

                $reverse_url    = FSIFWP_API_ENDPOINT.'subscriptions/';

                $body['subscriptions'][0]  = array(
                    'subscription' => $subscription_id,
                    'deactivation' => null,
                );

                fsifwp_edd_post_request($reverse_url, $body);            

            }

        }        

    }    

}

add_action('edd_recurring_cancel_fastspring_subscription', 'fsifwp_edd_recurring_cancel_subscription', 10, 2);

function fsifwp_edd_recurring_cancel_subscription($instance, $status){
    
    if($instance->gateway == 'fastspring'){
                        
        $subscription_id = $instance->transaction_id;
        
        if($subscription_id){

            $cancel_url    = FSIFWP_API_ENDPOINT.'subscriptions/'.$subscription_id;
            fsifwp_edd_delete_request($cancel_url);

        }    

    }
        
}

add_action('edd_recurring_after_delete_subscription', 'fsifwp_edd_recurring_delete_subscription', 10, 2);

function fsifwp_edd_recurring_delete_subscription($deleted, $instance){
 
    if($instance->gateway == 'fastspring'){
            
        $subscription_id = $instance->transaction_id;

        if($subscription_id){

            $cancel_url    = FSIFWP_API_ENDPOINT.'subscriptions/'.$subscription_id.'?billingPeriod=0';
            fsifwp_edd_delete_request($cancel_url);

        }


    }
        
}