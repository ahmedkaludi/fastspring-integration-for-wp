<?php

if (!defined('ABSPATH')) exit;

add_action( 'rest_api_init', 'fsifwp_register_webhook_route');

function fsifwp_register_webhook_route(){

    register_rest_route( 'fsifwp-webhook', 'endpoint', array(
        'methods'    => 'POST',
        'callback'   => 'fsifwp_webhook_callback',
        'permission_callback' => '__return_true'
    ));

}

function fsifwp_webhook_callback( \WP_REST_Request $req ) {

    $body = $req->get_Body();
    
    $event_json = json_decode( $body, true );
    
    if( !empty($event_json['events']) && is_array($event_json) ){

        foreach ($event_json['events'] as $value) {
            
            switch ($value['type']) {

                case 'subscription.canceled':
                        
                    if(!empty($value['data']['subscription'])){

                        $subs_db      = new EDD_Subscriptions_DB;
                        $subs         = $subs_db->get_subscriptions( array( 'number' => 1, 'transaction_id' => $value['data']['subscription'] ) );
                        $subscription = reset( $subs );
                        $subscription->cancel();            
                        
                    }

                    break;    

                case 'subscription.deactivated':

                    if(!empty($value['data']['subscription'])){

                        $subs_db      = new EDD_Subscriptions_DB;
                        $subs         = $subs_db->get_subscriptions( array( 'number' => 1, 'transaction_id' => $value['data']['subscription'] ) );
                        $subscription = reset( $subs );
                        $subscription->cancel();            
                        
                    }                                           

                    break;
                
                case 'return.created':                    

                    $transaction_id   = $value['data']['original']['reference'];
                    
                    if($transaction_id){
                     
                        $payment_id = edd_get_purchase_id_by_transaction_id( $transaction_id );
                      
                        if($payment_id){
                            
                            $payment         = new EDD_Payment( $payment_id );
                            $payment->status = 'refunded';
						    $payment->save();	
                            $payment->add_note( sprintf( __( 'Charge %s refunded in FastSpring.', 'fastSpring-integration-for-wp' ), $transaction_id ) );						
                        }                        
                    }

                    break; 
                case 'subscription.charge.completed':

                    $subscription   = $value['data']['subscription'];                                        
                    
                    $subs_db      = new EDD_Subscriptions_DB;
                    $subs         = $subs_db->get_subscriptions( array( 'number' => 1, 'transaction_id' => $subscription ) );
                    $subscription = reset( $subs );
                                
                    if( $subscription && $subscription->id > 0 ) {

                        $payment_id = $subscription->add_payment( array(
                            'amount'         => $value['data']['total'],
                            'transaction_id' => $value['data']['reference']
                        ) );

                        if ( ! empty( $payment_id ) ) {

                            edd_debug_log( 'Recurring FastSpring - IPN for subscription ' . $subscription->id . ': renewal payment was recorded successfully, preparing to renew subscription' );
                            $subscription->renew( $payment_id );                
                            $subscription->add_note( sprintf( __( 'Outstanding subscription balance of %s collected successfully.', 'fastSpring-integration-for-wp' ), $subscription->id ) );
    
                        } else {
                            edd_debug_log( 'Recurring FastSpring - IPN for subscription ' . $subscription->id . ': renewal payment creation appeared to fail.' );
                        }
                    }

                    break; 
                case 'subscription.charge.failed':
                   
                    break;      
                    
                case 'order.completed':

                    $fs_order_id   = $value['data']['reference'];
                    
                    if($fs_order_id){

                        $payment_id = edd_get_purchase_id_by_transaction_id( $fs_order_id );                                                    
                        
                        if($payment_id){
                            
                            $payment         = new EDD_Payment( $payment_id );
                            $payment->status = 'publish';
						    $payment->save();	
                            $payment->add_note( sprintf( __( 'Order Placed successfully via FastSpring.', 'fastSpring-integration-for-wp' ), $fs_order_id ) );						
                        }

                        // save transaction_id in db table.
                        if(  empty($payment_id ) && $value['data']['completed'] == true){

                            global $wpdb;

                            $table_name = $wpdb->prefix . "fsifwp_edd_order_reference"; 
                            $status     = $value['data']['completed'];  
                            $wpdb->insert($table_name, array('status' => sanitize_text_field($status), 'transaction_id' => sanitize_text_field($fs_order_id)) ); 

                        }
                    }

                    break;

                case 'order.canceled':

                    $fs_order_id   = $value['data']['reference'];
                    
                    if($fs_order_id){
                        
                        $payment_id = edd_get_purchase_id_by_transaction_id( $fs_order_id );   
                        
                        if($payment_id){
                            
                            $payment         = new EDD_Payment( $payment_id );
                            $payment->status = 'abandoned';
						    $payment->save();	
                            $payment->add_note( sprintf( __( 'Order declined via FastSpring.', 'fastSpring-integration-for-wp' ), $fs_order_id ) );						
                        }
                                                
                    }

                    break;
                case 'order.failed':

                    $fs_order_id   = $value['data']['reference'];
                    
                    if($fs_order_id){
                        $payment_id = edd_get_purchase_id_by_transaction_id( $fs_order_id );  
                        
                        if($payment_id){
                            
                            $payment         = new EDD_Payment( $payment_id );
                            $payment->status = 'failed';
						    $payment->save();	
                            $payment->add_note( sprintf( __( 'Order failed via FastSpring.', 'fastSpring-integration-for-wp' ), $fs_order_id ) );						
                        }                                                
                    }

                    break;    
                                                 
                case 'subscription.activated':

                        if(!empty($value['data']['subscription'])){

                            $subs_db      = new EDD_Subscriptions_DB;
                            $subs         = $subs_db->get_subscriptions( array( 'number' => 1, 'transaction_id' => $value['data']['subscription'] ) );
                            $subscription = reset( $subs );
                                        
                            if( $subscription && $subscription->id > 0 ) {				
                                $subscription->update( array( 'status' => 'active' ) );
                            }
                        }
                            
                    break;    
                case 'subscription.uncanceled':

                    if(!empty($value['data']['subscription'])){

                        $subs_db      = new EDD_Subscriptions_DB;
                        $subs         = $subs_db->get_subscriptions( array( 'number' => 1, 'transaction_id' => $value['data']['subscription'] ) );
                        $subscription = reset( $subs );
                                    
                        if( $subscription && $subscription->id > 0 ) {				
                            $subscription->update( array( 'status' => 'active' ) );
                        }
                    }

                    break; 
                case 'subscription.updated':

                    
                    if(!empty($value['data']['subscription'])){

                        $data = $value['data'];

                        $subs_db      = new EDD_Subscriptions_DB;
                        $subs         = $subs_db->get_subscriptions( array( 'number' => 1, 'transaction_id' => $value['data']['subscription'] ) );
                        $subscription = reset( $subs );
                        
                        $args = array();

                        if($data['state'] == 'active'){
                            $args['status'] = 'active';
                        }                        
                        if($data['state'] == 'canceled'){
                            $args['status'] = 'cancelled';
                        }
                        if($data['state'] == 'deactivated'){
                            $args['status'] = 'cancelled';
                        }
                        if($data['state'] == 'trial'){
                            $args['status'] = 'trialling';
                        }
                                                
                        if( $subscription && $subscription->id > 0 ) {				
                            $subscription->update( $args );
                        }
                    }

                    break;                              
                                    
                default:                    
                    
                    break;
            }

        }

    }    

}