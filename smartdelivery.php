<?php
if ( ! defined( 'ABSPATH' ) ) exit; 
/*
Plugin Name: Smart Delivery - Shippings and Returns
Description: This plugin will post woocommerce order with their detail on specific API. Upon updation and deletion of order, it will syncronize order data to API. Also this plugin will add shipping method on woocommerce checkout.
Version:     1.1
Author:      Smart Delivery <sajjad@smart-delivery.se>
*/
if(!function_exists('smd_sr_create_tbl')){
    function smd_sr_create_tbl() {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix.'smartdelivery_setting';
        $sql1="CREATE TABLE $table_name(
        s_id int(11) NOT NULL AUTO_INCREMENT,
        api_key varchar(255) NOT NULL,
        supplier_key varchar(255) NOT NULL,
        shipping_option enum('Yes','No') NOT NULL DEFAULT 'No',
        shipping_price DECIMAL(10,2) NULL,
        send_order enum('Yes','No') NOT NULL DEFAULT 'Yes',
        delivery_date_field varchar(255) NOT NULL,
        added_by int(11) NOT NULL,
        added_on datetime NOT NULL,
        updated_by int(11) NOT NULL,
        updated_on datetime NOT NULL,
        PRIMARY KEY (s_id)
        )$charset_collate;";
        dbDelta($sql1);
        if(in_array('order-delivery-date-for-woocommerce/order_delivery_date.php',apply_filters('active_plugins',get_option('active_plugins')))){
            $data=array("api_key"=>'Your API Key',"supplier_key"=>'Your Supplier Key',"shipping_option"=>'No',"shipping_price"=>'0.00',"send_order"=>'No',"delivery_date_field"=>'Delivery Date',"added_by"=>get_current_user_id(),"added_on"=>date("Y-m-d H:i:s"));
            $wpdb->insert($wpdb->prefix.'smartdelivery_setting',$data);
        }
    }
}
if(!function_exists('smd_sr_plugin_install')){
    function smd_sr_plugin_install() {
        smd_sr_create_tbl();
    }
}
register_activation_hook( __FILE__, 'smd_sr_plugin_install' );
if(!function_exists('smd_sr_plugin_deactivate')){
    function smd_sr_plugin_deactivate(){
    }
}
register_deactivation_hook( __FILE__, 'smd_sr_plugin_deactivate' );
if(!function_exists('smd_sr_prefix_enqueue')){
    function smd_sr_prefix_enqueue(){       
        // CSS
        wp_register_style('prefix_bootstrap', plugins_url('css/bootstrap.min.css',__FILE__ ));
        wp_enqueue_style('prefix_bootstrap');
        
    }
}
if(isset($_REQUEST['page'])){ 
    if($_REQUEST['page']=='smart-delivery-shippings-and-returns/admin/inc/setting.php'){
        add_action( 'admin_enqueue_scripts', 'smd_sr_prefix_enqueue' );
    }
}
if(!function_exists('smd_sr_plugin_menu')){
    function smd_sr_plugin_menu(){
        add_menu_page('Setting','Smart Delivery','manage_options',plugin_dir_path(__FILE__).'admin/inc/setting.php',null,'dashicons-art',20);
    }
}   
add_action('admin_menu', 'smd_sr_plugin_menu');

if(in_array('woocommerce/woocommerce.php',apply_filters('active_plugins',get_option('active_plugins')))){
    if(!function_exists('smd_sr_update_order_delivery_meta')){
        function smd_sr_update_order_delivery_meta($order_id){
            global $wpdb;
            $api_setting=$wpdb->get_row("SELECT api_key,supplier_key FROM ".$wpdb->prefix."smartdelivery_setting WHERE send_order='Yes' AND api_key!='' AND supplier_key!=''",ARRAY_A);
            if($wpdb->num_rows > 0){
                $data_to_post = array(
                    'api_key' => $api_setting['api_key'],
                    'second_list' => 'No',
                    'file_name' => 'Wordpress',
                    'addresses' => array(array(
                        'customer_id' => $order_id,
                        'supplier_id' => $api_setting['supplier_key']
                    ))
                );
                $url = "https://smart-delivery.se/api/order/retrieve";
                $args = array(
                    'body'        => $data_to_post,
                    'timeout'     => '5',
                    'redirection' => '5',
                    'httpversion' => '1.0',
                    'blocking'    => true,
                    'headers'     => array(),
                    'cookies'     => array(),
                );
                $response = wp_remote_post($url, $args);
                $http_code = wp_remote_retrieve_body($response);
                $result_arr=json_decode($http_code,true);
                if(count($result_arr)>0){
                    update_post_meta( $order_id, 'delivery_date', $result_arr[0]['delivery_date']);
                    update_post_meta( $order_id, 'delivery_time', $result_arr[0]['delivery_time']);
                    update_post_meta( $order_id, 'delivered_time', $result_arr[0]['delivered_time']);
                    update_post_meta( $order_id, 'delivery_status', $result_arr[0]['delivery_status']);
                    //$wpdb->insert($wpdb->prefix.'debug',array('api_method'=>'update_post_meta','req_data'=>$order_id,'response'=>json_encode($result_arr,true),'dated'=>date('Y-m-d H:i:s')));
                }
                return $result;
            }
        }
    }
    if(!function_exists('smd_sr_insert_order_smart_delivery')){
        function smd_sr_insert_order_smart_delivery($order_id){
            global $wpdb;
            $api_setting=$wpdb->get_row("SELECT api_key,supplier_key,delivery_date_field FROM ".$wpdb->prefix."smartdelivery_setting WHERE send_order='Yes' AND api_key!='' AND supplier_key!=''",ARRAY_A);
            if($wpdb->num_rows > 0){
                global $woocommerce; 
                $order_arr = wc_get_order($order_id);
                $items = $order_arr->get_items();
                $order_data=json_decode($order_arr,true); 
                $prod_str='';
                foreach($items as $item){
                    $prod_str.= $item->get_name().',';
                }
                $order_shipping_data = $order_arr->get_shipping_method();
                if($order_shipping_data == "Smart Delivery Shipping"){
                    $delivery_date=date('Y-m-d');
                    if(!empty(trim($api_setting['delivery_date_field']))){
                        $delivery_date_str = smd_sr_helper_get_order_meta($order_arr,$api_setting['delivery_date_field']);
                        if($delivery_date_str!=''){
                            $delivery_date = date('Y-m-d',strtotime(smd_sr_convert_sv_en($delivery_date_str)));
                        }
                    }
                    $name=$order_data['billing']['first_name'].' '.$order_data['billing']['last_name'];
                    if($order_data['shipping']['first_name']!=''){
                        $name=$order_data['shipping']['first_name'].' '.$order_data['shipping']['last_name'];
                    }
                    $address=$order_data['billing']['address_1'].' '.$order_data['billing']['address_2'];
                    if($order_data['shipping']['address_1']!=''){
                        $address=$order_data['shipping']['address_1'].' '.$order_data['shipping']['address_2'];
                    }
                    $postal_code=$order_data['billing']['postcode'];
                    if($order_data['shipping']['postcode']!=''){
                        $postal_code=$order_data['shipping']['postcode'];
                    }
                    $area=$order_data['billing']['city'];
                    if($order_data['shipping']['city']!=''){
                        $area=$order_data['shipping']['city'];
                    }
                    $data_to_post = array(
                        'api_key' => $api_setting['api_key'],
                        'second_list' => 'No',
                        'file_name' => 'Wordpress',
                        'addresses' => array(array(
                            'customer_id' => $order_id,
                            'name' => $name,
                            'mobile' => $order_data['billing']['phone'],
                            'mobile2' => '',
                            'email' => $order_data['billing']['email'],
                            'address' => $address,
                            'postal_code' => $postal_code,
                            'area' => $area,
                            'floor' => '',
                            'door_code' => '',
                            'product' => $prod_str,
                            'extra_product' => '',
                            'extra_info' => $order_data['customer_note'],
                            'supplier_id' => $api_setting['supplier_key'],
                            'delivery_date' => $delivery_date,
                            'delivery_type' => 'Delivery',
                            'route_id' => 0,
                        ))
                    );
                    $url = "https://smart-delivery.se/api/order/create";
                    $args = array(
                        'body'        => $data_to_post,
                        'timeout'     => '5',
                        'redirection' => '5',
                        'httpversion' => '1.0',
                        'blocking'    => true,
                        'headers'     => array(),
                        'cookies'     => array(),
                    );
                    $response = wp_remote_post($url, $args);
                    $http_codee = wp_remote_retrieve_body($response);
                    //$wpdb->insert($wpdb->prefix.'debug',array('api_method'=>'create','req_data'=>json_encode($data_to_post),'response'=>$http_code,'dated'=>date('Y-m-d H:i:s')));



                    $order_retrieve[]=array(
                        'customer_id' => $order_id,
                        'supplier_id' => $api_setting['supplier_key']
                    );
                    $data_to_post = array(
                        'api_key' => $api_setting['api_key'],
                        'second_list' => 'No',
                        'file_name' => 'Wordpress',
                        'addresses' => $order_retrieve
                    );
                    $url = "https://smart-delivery.se/api/order/retrieve";
                    $args = array(
                        'body'        => $data_to_post,
                        'timeout'     => '5',
                        'redirection' => '5',
                        'httpversion' => '1.0',
                        'blocking'    => true,
                        'headers'     => array(),
                        'cookies'     => array(),
                    );
                    $response = wp_remote_post($url, $args);
                    $http_code = wp_remote_retrieve_body($response);
                    $result_arr=json_decode($http_code,true);
                    if(count($result_arr)>0){
                        foreach($result_arr as $result_ind=>$ord_del_data){
                            update_post_meta($ord_del_data['customer_id'], 'delivery_date', $ord_del_data['delivery_date']);
                            update_post_meta($ord_del_data['customer_id'], 'delivery_time', $ord_del_data['delivery_time']);
                            update_post_meta($ord_del_data['customer_id'], 'delivered_time', $ord_del_data['delivered_time']);
                            update_post_meta($ord_del_data['customer_id'], 'delivery_status', $ord_del_data['delivery_status']);   
                        }
                    }

                    return $http_codee;
                }
            }
        }
    }
    if(!function_exists('smd_sr_update_order_smart_delivery')){
        function smd_sr_update_order_smart_delivery($order_id){
            global $wpdb;
            $api_setting=$wpdb->get_row("SELECT api_key,supplier_key,delivery_date_field FROM ".$wpdb->prefix."smartdelivery_setting WHERE send_order='Yes' AND api_key!='' AND supplier_key!=''",ARRAY_A);
            if($wpdb->num_rows > 0){
                global $woocommerce; 
                $order_arr = wc_get_order($order_id);
                $items = $order_arr->get_items();
                $order_data=json_decode($order_arr,true); 
                $prod_str='';
                foreach($items as $item){
                    $prod_str.= $item->get_name().',';
                }
                $order_shipping_data = $order_arr->get_shipping_method();
                if($order_shipping_data == "Smart Delivery Shipping"){
                $delivery_date=date('Y-m-d');
                    if(!empty(trim($api_setting['delivery_date_field']))){
                        $delivery_date_str = smd_sr_helper_get_order_meta($order_arr,$api_setting['delivery_date_field']);
                        if($delivery_date_str!=''){
                            $delivery_date = date('Y-m-d',strtotime(smd_sr_convert_sv_en($delivery_date_str)));
                        }
                    }
                    $name=$order_data['billing']['first_name'].' '.$order_data['billing']['last_name'];
                    if($order_data['shipping']['first_name']!=''){
                        $name=$order_data['shipping']['first_name'].' '.$order_data['shipping']['last_name'];
                    }
                    $address=$order_data['billing']['address_1'].' '.$order_data['billing']['address_2'];
                    if($order_data['shipping']['address_1']!=''){
                        $address=$order_data['shipping']['address_1'].' '.$order_data['shipping']['address_2'];
                    }
                    $postal_code=$order_data['billing']['postcode'];
                    if($order_data['shipping']['postcode']!=''){
                        $postal_code=$order_data['shipping']['postcode'];
                    }
                    $area=$order_data['billing']['city'];
                    if($order_data['shipping']['city']!=''){
                        $area=$order_data['shipping']['city'];
                    }
                    $data_to_post = array(
                        'api_key' => $api_setting['api_key'],
                        'second_list' => 'No',
                        'file_name' => 'Wordpress',
                        'addresses' => array(array(
                            'customer_id' => $order_id,
                            'name' => $name,
                            'mobile' => $order_data['billing']['phone'],
                            'mobile2' => '',
                            'email' => $order_data['billing']['email'],
                            'address' => $address,
                            'postal_code' => $postal_code,
                            'area' => $area,
                            'floor' => '',
                            'door_code' => '',
                            'product' => $prod_str,
                            'extra_product' => '',
                            'extra_info' => $order_data['customer_note'],
                            'supplier_id' => $api_setting['supplier_key'],
                            'delivery_date' => $delivery_date,
                            'delivery_type' => 'Delivery',
                            'route_id' => 0,
                        ))
                    );
                    $url = "https://smart-delivery.se/api/order/update";
                    $args = array(
                        'body'        => $data_to_post,
                        'timeout'     => '5',
                        'redirection' => '5',
                        'httpversion' => '1.0',
                        'blocking'    => true,
                        'headers'     => array(),
                        'cookies'     => array(),
                    );
                    $response = wp_remote_post($url, $args);
                    $http_code = wp_remote_retrieve_body($response);
                    //$wpdb->insert($wpdb->prefix.'debug',array('api_method'=>'update','req_data'=>json_encode($data_to_post),'response'=>$http_code,'dated'=>date('Y-m-d H:i:s')));
                    smd_sr_update_order_delivery_meta($order_id);
                    return $http_code;
                }
            }
        }
    }
    if(!function_exists('smd_sr_get_order_smart_delivery')){
        function smd_sr_get_order_smart_delivery($order_id){
            global $wpdb;
            $api_setting=$wpdb->get_row("SELECT api_key,supplier_key FROM ".$wpdb->prefix."smartdelivery_setting WHERE send_order='Yes' AND api_key!='' AND supplier_key!=''",ARRAY_A);
            if($wpdb->num_rows > 0){
                $data_to_post = array(
                    'api_key' => $api_setting['api_key'],
                    'second_list' => 'No',
                    'file_name' => 'Wordpress',
                    'addresses' => array(array(
                        'customer_id' => $order_id,
                        'supplier_id' => $api_setting['supplier_key']
                    ))
                );
                $url = "https://smart-delivery.se/api/order/retrieve";
                $args = array(
                    'body'        => $data_to_post,
                    'timeout'     => '5',
                    'redirection' => '5',
                    'httpversion' => '1.0',
                    'blocking'    => true,
                    'headers'     => array(),
                    'cookies'     => array(),
                );
                $response = wp_remote_post($url, $args);
                $http_code = wp_remote_retrieve_body($response);
                //$wpdb->insert($wpdb->prefix.'debug',array('api_method'=>'retrieve','req_data'=>json_encode($data_to_post),'response'=>$http_code,'dated'=>date('Y-m-d H:i:s')));
                return $http_code;
            }
        }
    }
    if(!function_exists('smd_sr_delete_order_smart_delivery')){
        function smd_sr_delete_order_smart_delivery($order_id){
            global $wpdb;
            global $woocommerce; 
            $api_setting=$wpdb->get_row("SELECT api_key,supplier_key FROM ".$wpdb->prefix."smartdelivery_setting WHERE send_order='Yes' AND api_key!='' AND supplier_key!=''",ARRAY_A);
            if($wpdb->num_rows > 0){
                $data_to_post = array(
                    'api_key' => $api_setting['api_key'],
                    'second_list' => 'No',
                    'file_name' => 'Wordpress',
                    'addresses' => array(array(
                        'customer_id' => $order_id,
                        'supplier_id' => $api_setting['supplier_key']
                    ))
                );
                $url = "https://smart-delivery.se/api/order/delete";
                $args = array(
                    'body'        => $data_to_post,
                    'timeout'     => '5',
                    'redirection' => '5',
                    'httpversion' => '1.0',
                    'blocking'    => true,
                    'headers'     => array(),
                    'cookies'     => array(),
                );
                $response = wp_remote_post($url, $args);
                $http_code = wp_remote_retrieve_body($response);
                //$wpdb->insert($wpdb->prefix.'debug',array('api_method'=>'delete','req_data'=>json_encode($data_to_post),'response'=>$http_code,'dated'=>date('Y-m-d H:i:s')));
                return $http_code;
            }
        }
    }
    if(!function_exists('smd_sr_bulk_order_smart_delivery')){
        function smd_sr_bulk_order_smart_delivery($order_id_arr,$method){
            global $wpdb;
            $api_setting=$wpdb->get_row("SELECT api_key,supplier_key,delivery_date_field FROM ".$wpdb->prefix."smartdelivery_setting WHERE api_key!='' AND supplier_key!=''",ARRAY_A);
            if($wpdb->num_rows > 0){
                $order_list=array(); $order_retrieve=array();
                if(count($order_id_arr)>0){
                    foreach($order_id_arr as $ord_ind=>$order_id){
                        $order_arr = wc_get_order($order_id);
                        $items = $order_arr->get_items();
                        $order_data=json_decode($order_arr,true); 
                        $prod_str='';
                        foreach($items as $item){
                            $prod_str.= $item->get_name().',';
                        }
                        $delivery_date=date('Y-m-d');
                        if(!empty(trim($api_setting['delivery_date_field']))){
                            $delivery_date_str = smd_sr_helper_get_order_meta($order_arr,$api_setting['delivery_date_field']);
                            if($delivery_date_str!=''){
                                $delivery_date = date('Y-m-d',strtotime(smd_sr_convert_sv_en($delivery_date_str)));
                            }
                        }
                        $name=$order_data['billing']['first_name'].' '.$order_data['billing']['last_name'];
                        if($order_data['shipping']['first_name']!=''){
                            $name=$order_data['shipping']['first_name'].' '.$order_data['shipping']['last_name'];
                        }
                        $address=$order_data['billing']['address_1'].' '.$order_data['billing']['address_2'];
                        if($order_data['shipping']['address_1']!=''){
                            $address=$order_data['shipping']['address_1'].' '.$order_data['shipping']['address_2'];
                        }
                        $postal_code=$order_data['billing']['postcode'];
                        if($order_data['shipping']['postcode']!=''){
                            $postal_code=$order_data['shipping']['postcode'];
                        }
                        $area=$order_data['billing']['city'];
                        if($order_data['shipping']['city']!=''){
                            $area=$order_data['shipping']['city'];
                        }
                        $order_list[]=array(
                            'customer_id' => $order_id,
                            'name' => $name,
                            'mobile' => $order_data['billing']['phone'],
                            'mobile2' => '',
                            'email' => $order_data['billing']['email'],
                            'address' => $address,
                            'postal_code' => $postal_code,
                            'area' => $area,
                            'floor' => '',
                            'door_code' => '',
                            'product' => $prod_str,
                            'extra_product' => '',
                            'extra_info' => $order_data['customer_note'],
                            'supplier_id' => $api_setting['supplier_key'],
                            'delivery_date' => $delivery_date,
                            'delivery_type' => 'Delivery',
                            'route_id' => 0
                        );
                        $order_retrieve[]=array(
                            'customer_id' => $order_id,
                            'supplier_id' => $api_setting['supplier_key']
                        );
                    }
                    $data_to_post = array(
                        'api_key' => $api_setting['api_key'],
                        'second_list' => 'No',
                        'file_name' => 'Wordpress',
                        'addresses' => $order_list
                    );
                    $url = "https://smart-delivery.se/api/order/".$method;
                    $args = array(
                        'body'        => $data_to_post,
                        'timeout'     => '5',
                        'redirection' => '5',
                        'httpversion' => '1.0',
                        'blocking'    => true,
                        'headers'     => array(),
                        'cookies'     => array(),
                    );
                    $response = wp_remote_post($url, $args);
                    $http_code = wp_remote_retrieve_body($response);
                    if($method=='update' or $method=='create'){
                        $data_to_post = array(
                            'api_key' => $api_setting['api_key'],
                            'second_list' => 'No',
                            'file_name' => 'Wordpress',
                            'addresses' => $order_retrieve
                        );
                        $url = "https://smart-delivery.se/api/order/retrieve";
                        $args = array(
                            'body'        => $data_to_post,
                            'timeout'     => '5',
                            'redirection' => '5',
                            'httpversion' => '1.0',
                            'blocking'    => true,
                            'headers'     => array(),
                            'cookies'     => array(),
                        );
                        $response = wp_remote_post($url, $args);
                        $http_code = wp_remote_retrieve_body($response);
                        $result_arr=json_decode($http_code,true);
                        if(count($result_arr)>0){
                            foreach($result_arr as $result_ind=>$ord_del_data){
                                update_post_meta($ord_del_data['customer_id'], 'delivery_date', $ord_del_data['delivery_date']);
                                update_post_meta($ord_del_data['customer_id'], 'delivery_time', $ord_del_data['delivery_time']);
                                update_post_meta($ord_del_data['customer_id'], 'delivered_time', $ord_del_data['delivered_time']);
                                update_post_meta($ord_del_data['customer_id'], 'delivery_status', $ord_del_data['delivery_status']);   
                            }
                        }
                    }
                }
                return 'success';
            }
        }
    }
}

add_action( 'woocommerce_thankyou', 'smd_sr_insert_order_smart_delivery', 10, 1 );
add_action( 'woocommerce_update_order', 'smd_sr_update_order_smart_delivery', 10, 1 );
add_action( 'woocommerce_delete_order', 'smd_sr_delete_order_smart_delivery', 10, 1 ); 
add_action( 'woocommerce_order_status_cancelled', 'smd_sr_delete_order_smart_delivery', 10, 1 ); 
add_action( 'woocommerce_order_status_failed', 'smd_sr_delete_order_smart_delivery', 10, 1 ); 
add_action( 'woocommerce_order_status_refunded', 'smd_sr_delete_order_smart_delivery', 10, 1 );
if(!function_exists('smd_sr_add_column_order_list')){
    function smd_sr_add_column_order_list($columns){
        $new_columns = array();
        foreach($columns as $column_name => $column_info){
            $new_columns[ $column_name ] = $column_info;
            if('order_total' === $column_name){
                $new_columns['delivery_date'] = __( 'Delivery Date', 'delivery_date');
                $new_columns['delivery_time'] = __('Delivery Time','delivery_time');
                $new_columns['delivered_time'] = __('Delivered Time','delivered_time');
                $new_columns['delivery_status'] = __('Delivery Status','delivery_status');
            }
        }
       return $new_columns;
    }
}
add_filter('manage_edit-shop_order_columns', 'smd_sr_add_column_order_list',20);
if(!function_exists('smd_sr_helper_get_order_meta')):
    function smd_sr_helper_get_order_meta( $order, $key = '', $single = true, $context = 'edit' ) {
        // WooCommerce > 3.0
        if(defined( 'WC_VERSION' ) && WC_VERSION && version_compare( WC_VERSION, '3.0', '>=' ) ) {
            $value = $order->get_meta( $key, $single, $context );
        }else{
            $order_id = is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id;
            $value    = get_post_meta( $order_id, $key, $single );
        }
        return empty($value)?'-':$value;
    }
endif;
if(!function_exists('smd_sr_add_order_profit_column_content')){
    function smd_sr_add_order_profit_column_content($column){
        global $post;
        $order    = wc_get_order( $post->ID );
        if('delivery_date' === $column){
            echo smd_sr_helper_get_order_meta($order,'delivery_date');
        }
        if('delivery_time' === $column){
            echo smd_sr_helper_get_order_meta($order,'delivery_time');
        }
        if('delivered_time' === $column){
            echo smd_sr_helper_get_order_meta($order,'delivered_time');
        }
        if('delivery_status' === $column){
            echo smd_sr_helper_get_order_meta($order,'delivery_status');
        }
    }
}
add_action('manage_shop_order_posts_custom_column', 'smd_sr_add_order_profit_column_content' );

function smd_sr_register_smart_delivery_bulk_actions($bulk_actions) {
  $bulk_actions['post_smart_delivery'] = __( 'Post To Smart Delivery', 'post_smart_delivery');
  return $bulk_actions;
}
add_filter('bulk_actions-edit-shop_order', 'smd_sr_register_smart_delivery_bulk_actions' );

add_filter('handle_bulk_actions-edit-shop_order', function($redirect_url, $action, $post_ids) {
    if($action == 'post_smart_delivery'){
        global $wpdb;
        global $woocommerce; 
        global $post;
        if(count($post_ids)>0){
            $insert_ord=$update_ord=array();
            foreach($post_ids as $order_id){
                $order = wc_get_order($order_id);
                if(smd_sr_helper_get_order_meta($order,'delivery_status')=='-' || trim(smd_sr_helper_get_order_meta($order,'delivery_status'))==''){
                    $insert_ord[]=$order_id;
                }else{
                    $update_ord[]=$order_id;
                }
            } 
            smd_sr_bulk_order_smart_delivery($insert_ord,'create');
            smd_sr_bulk_order_smart_delivery($update_ord,'update');
            add_action( 'admin_notices', 'smd_sr_smartdelivery_bulk_action_msg' );
        }
        $redirect_url = add_query_arg('changed-to-published', count($post_ids), $redirect_url);
    }
    return $redirect_url;
}, 10, 3);
// add_action( 'admin_notices', 'smd_sr_smartdelivery_bulk_action_msg' );
function smd_sr_smartdelivery_bulk_action_msg() {
    printf('<div id="message" class="updated notice is-dismissible"><p>' .
            _n( 'Selected order successfully posted on smart delivery.','Selected order successfully posted on smart delivery.',$count,'write_downloads') . '</p>
            <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button><button type="button" class="notice-dismiss"><span class="screen-reader-text"></span></button></div>');
}
if(!function_exists('smd_sr_smartdelivery_shipping_fee')){
    function smd_sr_smartdelivery_shipping_fee(){
        global $wpdb;
        global $woocommerce;
        $api_setting=$wpdb->get_row("SELECT api_key,supplier_key,shipping_price FROM ".$wpdb->prefix."smartdelivery_setting WHERE api_key!='' AND supplier_key!='' AND shipping_option='Yes'",ARRAY_A);
        if($wpdb->num_rows > 0){
            $fee = $api_setting['shipping_price'];
            $title = 'Smart Delivery Shipping Fee';
            $woocommerce->cart->add_fee($title, $fee, TRUE, 'standard');
        }
    }
}
//add_action( 'woocommerce_cart_calculate_fees', 'smd_sr_smartdelivery_shipping_fee');
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        function smd_sr_shipping_method_init(){

            add_filter( 'woocommerce_shipping_methods', 'register_smart_delivery_shipping_method' );

            function register_smart_delivery_shipping_method( $methods ) {

                // $method contains available shipping methods
                $methods[ 'smart_delivery_shipping_method' ] = 'WC_Your_Shipping_Method';

                return $methods;
            }
            class WC_Your_Shipping_Method extends WC_Shipping_Method {

                /**
                 * Constructor. The instance ID is passed to this.
                 */
                public function __construct( $instance_id = 0 ) {
                    $this->id                    = 'smart_delivery_shipping_method';
                    $this->instance_id           = absint( $instance_id );
                    $this->method_title          = __( 'Smart Delivery Shipping' );
                    $this->method_description    = __( 'Shipping And Return' );
                    $this->supports              = array(
                        'shipping-zones',
                        'instance-settings',
                        'instance-settings-modal'
                    );
                    $this->instance_form_fields = array(
                        'title' => array(
                            'title' => __( 'Smart Delivery Shipping', 'woocommerce' ),
                            'type' => 'text',
                            'default' => __("Smart Delivery Shipping", 'woocommerce')
                        ),
                        'shipping_price' => array(
                            'title' => __( 'Shipping Price', 'woocommerce' ),
                            'type' => 'text',
                            'default' => __("0.00", 'woocommerce')
                        )
                    );
                    $this->title = $this->get_option( 'title' );
                    add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
                }
                public function calculate_shipping($package = array()){
                    $this->add_rate( array(
                        'id'    => $this->id . $this->instance_id,
                        'label' => $this->title,
                        'cost'  => $this->get_option( 'shipping_price' ),
                        'calc_tax' => 'per_item'
                    ) );
                }
            }
        }
        add_action( 'woocommerce_shipping_init', 'smd_sr_shipping_method_init' );
        function smd_sr_shipping_method($methods){
            $methods['smart_delivery_shipping_method'] = 'WC_Your_Shipping_Method';
            return $methods;    
        }
        add_filter( 'woocommerce_shipping_methods','smd_sr_shipping_method');

        function add_fexdex_logo(){
            ?>
            <script>
                var sd_logo = jQuery('label[for=shipping_method_0_smart_delivery_shipping_method]');
                sd_logo.before('<image src="<?php echo plugin_dir_url( __FILE__ )."img/checkout-logo.jpeg";?>" alt="Smart Delivery" style="width: 25%; display: inline;"></image>');
            </script>
            <?php
        }
        add_action( 'woocommerce_cart_totals_after_order_total', 'add_fexdex_logo' );
        add_action( 'woocommerce_review_order_after_order_total', 'add_fexdex_logo' );
}
if(!function_exists('smd_sr_convert_sv_en')):
    function smd_sr_convert_sv_en($date){
        $lower_cased_date_str = strtolower($date);
        $find = ['january', 'februari', 'mars', '   april', 'maj', 'juni', 'juli', 'augusti', 'september', 'oktober','november', 'december',  'måndag', 'tisdag', 'onsdag', 'torsdag', 'fredag', 'lördag', 'söndag'];
        $replace = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october','november', 'december', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        return str_replace($find, $replace, $lower_cased_date_str);
    }
endif;
?>