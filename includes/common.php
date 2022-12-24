<?php

if (!defined('ABSPATH')) exit;

function fsifwp_edd_post_request($url, $body) {

    $response  = array();

    $username    = fsifwp_edd_get_setting('username');
    $password    = fsifwp_edd_get_setting('password');

    $auth = base64_encode( $username . ':' . $password );
        
    $args = array(
        'method' => 'POST',
        'headers' => array(           
            'Authorization' => "Basic ".$auth
        ),
        'body'    => wp_json_encode($body)
    );     
    
      $result = wp_remote_post( $url, $args );
    
      if ( is_wp_error( $result ) || 200 !== wp_remote_retrieve_response_code( $result ) ) {
            
            $message =  ( is_wp_error( $result) && $result->get_error_message() ) ? $result->get_error_message() : __( 'An error occurred, Please try after some time.' );
            $response = array('status' => 'error', 'message' => $message);

      }else{
        
            $result   = wp_remote_retrieve_body($result);                 
            $response = json_decode($result, true); 

      }
     
      return $response;

}


function fsifwp_edd_delete_request($url) {

    $response  = array();

    $username    = fsifwp_edd_get_setting('username');
    $password    = fsifwp_edd_get_setting('password');

    $auth = base64_encode( $username . ':' . $password );
        
    $args = array(
        'method' => 'DELETE',
        'headers' => array(           
            'Authorization' => "Basic ".$auth
        ),      
    );     
       
      $result = wp_remote_request( $url, $args );
      
      if ( is_wp_error( $result ) || 200 !== wp_remote_retrieve_response_code( $result ) ) {
            
            $message =  ( is_wp_error( $result) && $result->get_error_message() ) ? $result->get_error_message() : __( 'An error occurred, Please try after some time.' );
            $response = array('status' => 'error', 'message' => $message);

      }else{
        
            $result   = wp_remote_retrieve_body($result);                 
            $response = json_decode($result, true); 

      }
     
      return $response;

}