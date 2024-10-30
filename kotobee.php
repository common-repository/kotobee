<?php
/*
Plugin Name: Kotobee Integration
Plugin URI: https://www.kotobee.com
Description: This Wordpress plugin allows you to control access to your <a href="https://www.kotobee.com" target="_blank">Kotobee</a> cloud ebooks and libraries through various Wordpress stores and payment gateways, such as <a href="http://www.woocommerce.com" target="_blank">WooCommerce</a> and <a href="http://www.memberful.com" target="_blank">Memberful</a> (more coming in the pipeline). This is the only official Kotobee Wordpress plugin.
Version: 1.5.5
Author: Kotobee
Author URI: https://profiles.wordpress.org/kotobee/
Text Domain: kotobee-integration
Domain Path: /languages
Requires at least: 4.7
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 3.0.0
WC tested up to: 6.0.0

*/

/**
 * A quick logging function to easily log arrays and objects
 * @param $stuff array|object|string The object you want to log
 * @param string $prepend If you want to add a string before the object log
 */
function kotobee_log($stuff, $prepend = '') {
    if(is_object($stuff) || is_array($stuff))
        error_log($prepend." ".print_r($stuff, true));
    else
        error_log($prepend." ".$stuff);
}
require_once plugin_dir_path(__FILE__).'classes/loader.php';

/**
 * Versions History:
 * 1.0 The first launch of the two integrations: Woocommerce and Memberful.
 * 1.1 Chapter-specific access feature was added.
 * 1.2 Allowed appending chapters 
 * 1.3 Added support for WooCommerce Product Variations
 * 1.4 Added back compatibility with Woocommerce 3.0.0 in getting order billing email
 * 1.5 Added support for WooCommerce Subscriptions extension
 * 1.5.1 Fixed a fatal error in global settings page
 * 1.5.2 Fixed a bug when WooCommerce Subscription is not active
 * 1.5.3 Fixed a typo that raises a warning in PHP8 
 * 1.5.4 Security fixes
 * 1.5.5 Added advanced settings to support a custom api domain
 */
define("KOTOBEE_INTEGRATION_VERSION", "1.5.5");
define("KOTOBEE_INTEGRATION_TABLE", "kotobee_integration");
define("KOTOBEE_INTEGRATION_TEXTDOMAIN", "kotobee-integration");
define('KOTOBEE_INTEGRATION_SETTINGS_PAGE', 'admin.php?page=kotobee-integration');
define('KOTOBEE_INTEGRATION_PLUGIN_BASE_DIR_URL', plugin_dir_url(__FILE__));
define('KOTOBEE_INTEGRATION_PLUGIN_BASE_DIR_PATH', plugin_dir_path(__FILE__));

$kotobee_integrations = array(
    'woocommerce' => __('WooCommerce', KOTOBEE_INTEGRATION_TEXTDOMAIN),
    'memberful' => __('Memberful', KOTOBEE_INTEGRATION_TEXTDOMAIN)
);
$kotobeeClient = new KotobeeApiClient(get_option('kotobee_integration_serial'), get_option('kotobee_integration_apiDomain'));

/**
 * Creates the needed table structure for the plugin
 */
function kotobee_integration_install() {
    global $wpdb;
    error_log("Activating Kotobee WP Integration");

    $table_name = $wpdb->prefix . KOTOBEE_INTEGRATION_TABLE;

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
		id mediumint(20) NOT NULL AUTO_INCREMENT,
		itemType varchar(20) NOT NULL,
		itemID bigint(20) NOT NULL,
		kType varchar(10) NOT NULL,
		kID bigint(20) NOT NULL,
		options text,
		PRIMARY KEY  (id)
	) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    error_log("Database table should have been added/updated");

    add_option( 'kotobee_integration_db_version', KOTOBEE_INTEGRATION_VERSION );

    //Now We'll migrate the product meta that might be added from an older plugin
    $products = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'any',
        'meta_key' => 'kotobee_woocommerce_product_links'
    ));
    if(count($products)) {
        $wcIntegration = KotobeeWoocommerceIntegration::getInstance();
        $wcIntegration->migrateProductMeta($products);
    }

    //Set a default value for "Send activation emails " option
    add_option('kotobee_integration_sendEmail', 1);
}
register_activation_hook( __FILE__, 'kotobee_integration_install' );

/**
 * Adds the main settings tab and links it with the settings page
 */
function kotobee_integration_settings_tab() {
     add_menu_page(
        __('Kotobee Integration Settings',KOTOBEE_INTEGRATION_TEXTDOMAIN),
        __('Kotobee',KOTOBEE_INTEGRATION_TEXTDOMAIN),
        'manage_options', //TODO: Add a new capability and assign it to a role, this makes it easy to be changed by users
        'kotobee-integration',
        'kotobee_integration_settings_page_content',
        plugin_dir_url(__FILE__) . 'images/icon-kotobee.jpg',
        20
    );

    // The main settings page
    // This will help replacing the label of the first item in the submenu, from 'Kotobee' to 'Settings'
    add_submenu_page(
        'kotobee-integration',
        'kotobee Integration Settings',
        'Settings',
        'manage_options',
        'kotobee-integration',
        'kotobee_integration_settings_page_content'
    );
}

/**
 * Calls an external code for the settings page
 */
function kotobee_integration_settings_page_content() {
    require_once plugin_dir_path(__FILE__).'admin/global-settings.php';
}
add_action( 'admin_menu', 'kotobee_integration_settings_tab' );

/**
 * Some logic to do when init fires
 */
function kotobee_integration_initialize_plugin() {
    global $kotobeeClient;

    //This code response to the update of settings. It should be here to update integration pages before detecting them when initiating integration classes bellow
    if(isset($_POST["kotobee-submit"]) && current_user_can('manage_options')) {

        $activeIntegrations = array();
        if(isset($_POST['activeIntegrations']) && is_array($_POST['activeIntegrations'])) {
            //Validate activeIntegrations
            if ( in_array('woocommerce', $_POST['activeIntegrations']) )
                $activeIntegrations[] = 'woocommerce';
                
            if ( in_array('memberful', $_POST['activeIntegrations']) )
                $activeIntegrations[] = 'memberful';
                
        }

        $serial = isset($_POST['kotobee-serial']) ? sanitize_text_field($_POST['kotobee-serial']) : null;
        $domain = isset($_POST['kotobee-domain']) ? esc_url_raw($_POST['kotobee-domain'], array("http", "https")) : null;
        $sendEmail = isset($_POST['kotobee-sendemail']) ? 1 : null;
        $removeAccess = isset($_POST['kotobee-remove-access']) ? 1 : null;

        update_option('kotobee_integration_sendEmail',$sendEmail);
        update_option('kotobee_integration_removeAccess',$removeAccess);
        update_option('kotobee_integration_active',$activeIntegrations);
        update_option('kotobee_integration_apiDomain',$domain);
        update_option('kotobee_integration_serial',$serial);

        if (!$domain) 
            $domain = "https://www.kotobee.com"; //If domain is empty, use the default to validate the serial
        if($kotobeeClient->serialCheck($serial, $domain)) {
            add_action('admin_notices',function(){
                echo "<div class='notice notice-success'><p>".esc_html__("Settings updated successfully.",KOTOBEE_INTEGRATION_TEXTDOMAIN)."</p></div>";
            });
        } else {
            delete_option("kotobee_integration_serial");
            add_action('admin_notices',function(){
                echo "<div class='notice notice-error'><p>".esc_html__("The entered serial is invalid",KOTOBEE_INTEGRATION_TEXTDOMAIN)."</p></div>";
            });
        }
    }

    /**
     * Loads integration classes
     */
    $activeIntegrations = get_option('kotobee_integration_active');
    if(is_array($activeIntegrations)) {
        if(in_array('woocommerce', $activeIntegrations))
            KotobeeWoocommerceIntegration::getInstance();
        if(in_array('memberful', $activeIntegrations))
            KotobeeMemberfulIntegration::getInstance();
    }
}
add_action('init', 'kotobee_integration_initialize_plugin');

/**
 * Loads plugin text domain
 */
function kotobee_integration_load_text_domain() {
    load_plugin_textdomain(KOTOBEE_INTEGRATION_TEXTDOMAIN, false, basename( dirname( __FILE__ ) ) . '/languages');
}
add_action('init', 'kotobee_integration_load_text_domain');

/**
 * Adds link to settings page below plugin name in plugin.php
 *
 * @param $links
 * @return mixed
 */
function kotobee_integration_settings_link($links) {
    $settings_link = '<a href="'.KOTOBEE_INTEGRATION_SETTINGS_PAGE.'">'.esc_html__('Settings',KOTOBEE_INTEGRATION_TEXTDOMAIN).'</a>';
    array_unshift($links, $settings_link);
    return $links;
}

add_filter("plugin_action_links_".plugin_basename(__FILE__), 'kotobee_integration_settings_link' );

/**
 * Fixes the top margin of kotobee icon in admin menu
 */
function kotobee_integration_fix_kotobee_icon_css() {
    echo '<style>
    #adminmenu #toplevel_page_kotobee-integration .wp-menu-image img {
        padding: 6px 0 0 0;
    }
  </style>';
}
add_action('admin_head', 'kotobee_integration_fix_kotobee_icon_css');

/**
 * Shows an error admin notice if the serial is not yet set, reminding user to add it in settings page
 */
function kotobee_integration_no_serial_notice() {
    if(get_option('kotobee_integration_serial'))
        return;

    $screen = get_current_screen();
    if($screen->base == 'toplevel_page_kotobee-integration')
        return;
    $message = __('You must enter kotobee serial number to start using Kotobee integration.', KOTOBEE_INTEGRATION_TEXTDOMAIN);
    $message .= ' ';
    /* translators: This is a link to Kotobee settings page */
    $message .= sprintf( __( 'Click <a href="%s">here</a> to enter your serial.', KOTOBEE_INTEGRATION_TEXTDOMAIN ), esc_url( KOTOBEE_INTEGRATION_SETTINGS_PAGE ) );
    echo "<div class='notice notice-error'><p>" . wp_kses($message , array(  'a' => array( 'href' => array() ) ) ) . "</p></div>";
}
add_action('admin_notices', 'kotobee_integration_no_serial_notice');

