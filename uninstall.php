<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}
//$option_name = 'wporg_option';
//delete_option($option_name);
// drop a custom database table
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}smartdelivery_setting");
?>