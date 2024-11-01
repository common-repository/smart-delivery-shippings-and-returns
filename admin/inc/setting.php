<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if(!session_id()) {
    session_start();
}
$errors = array();
if(isset($_REQUEST['save_setting'])){
    $api_key = sanitize_text_field($_REQUEST['api_key']);
    $supplier_key = sanitize_text_field($_REQUEST['supplier_key']); 
    $shipping_option = sanitize_text_field($_REQUEST['shipping_option']); 
    $shipping_price = sanitize_text_field($_REQUEST['shipping_price']); 
    $send_order = sanitize_text_field($_REQUEST['send_order']);
    $delivery_date_field  = sanitize_text_field($_REQUEST['delivery_date_field']);
    if(empty($api_key)){   
        $errors['api_key'] = "Please enter API key";  
    }
    if(empty($supplier_key)){   
        $errors['supplier_key'] = "Please enter supplier key";  
    }
    if($shipping_option=='Yes' && $shipping_price==''){
        $errors['supplier_key'] = "Please enter shipping price"; 
    }
    if(0 === count($errors)){
        $rec=$wpdb->get_row("SELECT s_id FROM ".$wpdb->prefix."smartdelivery_setting",ARRAY_A);
        if($wpdb->num_rows > 0){
            $s_id=$rec['s_id'];
            $data=array("api_key"=>$api_key,"supplier_key"=>$supplier_key,"shipping_option"=>$shipping_option,"shipping_price"=>$shipping_price,"send_order"=>$send_order,"delivery_date_field"=>$delivery_date_field,"updated_by"=>get_current_user_id(),"updated_on"=>date("Y-m-d H:i:s"));
            $wpdb->update($wpdb->prefix.'smartdelivery_setting',$data,array('s_id'=>$s_id));
        }else{
            $data=array("api_key"=>$api_key,"supplier_key"=>$supplier_key,"shipping_option"=>$shipping_option,"shipping_price"=>$shipping_price,"send_order"=>$send_order,"delivery_date_field"=>$delivery_date_field,"added_by"=>get_current_user_id(),"added_on"=>date("Y-m-d H:i:s"));
            $wpdb->insert($wpdb->prefix.'smartdelivery_setting',$data); 
        }
        $_SESSION['msg']['success'] = "Setting saved.";
        unset($_POST);
        header("Location: ".site_url()."/wp-admin/admin.php?page=smart-delivery-shippings-and-returns/admin/inc/setting.php"); exit;
    }
}
$setting_rec=$wpdb->get_row("SELECT * FROM ".$wpdb->prefix."smartdelivery_setting",ARRAY_A);
?>
<div style='padding:10px;'>
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-primary">
                <div class="panel-heading"><h4>Smart Delivery - Shippings and Returns</h4><hr></div>
                <div class="panel-body">
                    <?php 
                    if(count($errors)>0){
                        foreach($errors as $error_key=>$error_desc){
                            echo '<div class="alert alert-danger"><strong>Error! </strong>'.$error_desc.'</div>';
                        }
                    }
                    if(isset($_SESSION['msg']['success'])){
                        echo '<div class="alert alert-success">'.$_SESSION['msg']['success'].'</div>';
                        unset($_SESSION['msg']['success']);
                    } 
                    ?>
                    <form class="well well-lg" action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="POST">
                        <div class="row">
                            <?php 
                            if(!in_array('woocommerce/woocommerce.php',apply_filters('active_plugins',get_option('active_plugins')))){
                                echo '<div class="alert alert-danger">This plugin supported by woocommerce. Please install and activate woocommerce plugin. </div>';
                            }
                            ?>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>API Key <span class="asterik_field">*</span></label>
                                    <input type="text" class="form-control" id="api_key" name="api_key" value="<?php echo isset($_POST['api_key'])?esc_attr($_POST['api_key']):esc_attr($setting_rec['api_key']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Supplier Key <span class="asterik_field">*</span></label>
                                    <input type="text" class="form-control" id="supplier_key" name="supplier_key" value="<?php echo isset($_POST['supplier_key'])?esc_attr($_POST['supplier_key']):esc_attr($setting_rec['supplier_key']); ?>" required>
                                </div>
                                <div class="form-group" style="display:none;">
                                    <label>Shipping Option</label><br>
                                    <label class="radio-inline"><input type="radio" name="shipping_option" id="shipping_option_yes" value="Yes" <?php echo ($setting_rec['shipping_option']=='Yes'?'checked':''); ?>>Yes</label>
                                    <label class="radio-inline"><input type="radio" name="shipping_option" id="shipping_option_no" value="No" <?php echo ($setting_rec['shipping_option']=='No'?'checked':''); ?>>No</label>
                                </div>
                                <div class="form-group" id="shipping_sec" style="display:none;<?php //echo ($setting_rec['shipping_option']=='No'?'none':''); ?>">
                                    <label>Shipping Price <span class="asterik_field">*</span></label>
                                    <input type="number" step="any" class="form-control" id="shipping_price" name="shipping_price" value="<?php echo isset($_POST['shipping_price'])?esc_attr($_POST['shipping_price']):esc_attr($setting_rec['shipping_price']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Send Order Data Automatically</label><br>
                                    <label class="radio-inline"><input type="radio" name="send_order" id="send_order_yes" value="Yes" <?php echo ($setting_rec['send_order']=='Yes'?'checked':''); ?>>Yes</label>
                                    <label class="radio-inline"><input type="radio" name="send_order" id="send_order_no" value="No" <?php echo ($setting_rec['send_order']=='No'?'checked':''); ?>>No</label>
                                </div>
                                <div class="form-group">
                                    <label>Delivery Date For Sync</label><br>
                                    <input type="text" class="form-control" id="delivery_date_field" name="delivery_date_field" value="<?php echo isset($_POST['delivery_date_field'])?esc_attr($_POST['delivery_date_field']):esc_attr($setting_rec['delivery_date_field']); ?>" required>
                                </div>
                                <button type="submit" class="btn btn-primary" name="save_setting">Save</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<style type="text/css">
.asterik_field{color:red;}
</style>
<script type="text/javascript">
jQuery(document).ready(function(){
    jQuery('input[type=radio][name=shipping_option]').on('change', function() {
        if(jQuery(this).val()=='Yes'){
            jQuery('#shipping_sec').slideDown();
        }else{
            jQuery('#shipping_sec').slideUp();
        }      
    });
})
</script>