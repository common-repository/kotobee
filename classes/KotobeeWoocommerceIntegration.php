<?php

class KotobeeWoocommerceIntegration extends KotobeeIntegration
{
    public $AJAX_ACTION_LINK_ITEM = 'kotobee_woocommerce_integration_add_linked_product';
    public $AJAX_ACTION_REMOVE_LINKED_ITEM = 'kotobee_woocommerce_integration_remove_linked_product';
    public $OPTIONS_PAGE_SLUG = 'woocommerce-integration-settings';

    public $integrationID = 'woocommerce';

    protected $allowedTypes = array('wooProduct'=>"Products");

    private $isWcActive;

    protected function __construct()
    {
        $this->integrationTitle = __('Kotobee WooCommerce Integration', KOTOBEE_INTEGRATION_TEXTDOMAIN);

        if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
            $this->isWcActive = false;
            add_action( 'admin_notices', array($this, 'showNoWooCommerceNotice') );
            return;
        }
        $required_woo = '3.0.0';
        if ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, $required_woo, '<' )  ) {
            add_action( 'admin_notices', array($this, 'showIncompatibleWooCommerceNotice') );
            return;
        }        
        add_action( 'add_meta_boxes_product', array($this, 'addProductMetaBox'), 11 ); //The 11 helps overriding older plugin metabox
        
        // Link to Kotobee button in variation header
        add_action( 'woocommerce_variation_header', array($this, 'addVariationHtml'));

        $this->loadCommonHooks();
        
        //Get product variations in an ajax call
        add_action( 'wp_ajax_kotobee_get_product_variations', array($this, 'getProductVariationsCallback') );

        //Add the linking script in product's page
        add_action( 'admin_enqueue_scripts', array($this, 'enqueueLinkingScriptInProductPage'));

        //Register action with order status change
        add_action( 'woocommerce_order_status_changed', array($this, 'handleOrderStatusChange'), 10, 4 );
        
        //Register actions with subscription status changes
        add_action( 'woocommerce_subscription_status_updated', array($this, 'handleSubscriptionStatusChange'), 10, 3 );
    }

    /**
     * Creates a meta box in a specific post type, in our case: product
     *
     * @param $product WP_Post the current product
     */
    public function addProductMetaBox($product) {
        add_meta_box(
            'link-product-to-kotobee',
            __( 'Link Product to Kotobee', KOTOBEE_INTEGRATION_TEXTDOMAIN ),
            array($this, 'linkProductToKotobeeMetaBoxHTML'),
            'product',
            'side',
            'default'
        );
    }
    public function addVariationHtml(WP_Post $variation) {
        echo "<a href='#TB_inline?height=550&inlineId=kotobee-book-selection' class='button button-primary thickbox link-to-kotobee' data-item-id='".esc_attr($variation->ID)."'  style='margin-top:3px'>".esc_attr__("Link to Kotobee", KOTOBEE_INTEGRATION_TEXTDOMAIN)."</a>";
    }
    function linkProductToKotobeeMetaBoxHTML(WP_Post $product) {
        $type = 'wooProduct';

        require_once plugin_dir_path(__FILE__).'../admin/metabox-html.php';
    }
    function enqueueLinkingScriptInProductPage($hook) {
        $screen = get_current_screen();
        if( $screen->id != 'product')
            return;

        $this->registerLinkingScript();
    }

    function showNoWooCommerceNotice() {
        $this->messageOutputHTML('error', __('Kotobee WooCommerce Integration requires WooCommerce plugin to be installed and activated in your website. Please install it to be able to use our plugin', KOTOBEE_INTEGRATION_TEXTDOMAIN));
    }
    function showIncompatibleWooCommerceNotice() {
        $this->messageOutputHTML('error', __('Kotobee Integration plugin is not compatible with your current version of Woocommerce, please upgrade to at least Woocommerce version 3.0.0.', KOTOBEE_INTEGRATION_TEXTDOMAIN));
    }
    protected function itemIDExists($itemID, $itemType)
    {
        if(!$this->isType($itemType))
            return false;
        $product = WooCommerce::instance()->product_factory->get_product($itemID);
        if($product)
            return true;
        return false;
    }
    protected function getAllItems($itemType)
    {
        if(!function_exists('wc_get_products'))
            return array();

        $products = array();

        $wcProducts = wc_get_products(array());
        if(count($wcProducts)) {
            foreach ($wcProducts as $wcProduct) {
                $products[$wcProduct->get_id()] = $wcProduct->get_name();
            }
        }
        return $products;
    }
    protected function currentUserCanLink($itemID, $itemType)
    {
        return current_user_can('edit_product',$itemID);
    }

    function mySubMenuPage() {
        //The main settings page
        add_submenu_page(
            'kotobee-integration',
            __('WooCommerce Integration Settings', KOTOBEE_INTEGRATION_TEXTDOMAIN),
            __('WooCommerce', KOTOBEE_INTEGRATION_TEXTDOMAIN),
            'manage_options',
            $this->OPTIONS_PAGE_SLUG,
            array($this,'settingsPageOutput')
        );
    }
    public function getProductLinkedObject($productID) {
        $product = wc_get_product($productID);
        $IDs = $product->get_children();
        $IDs[] = $productID;
        error_log("Kotobee Integration: IDs array: " . json_encode($product->get_children()));
        return $this->getLinkedItemsObject('wooProduct', $IDs);
    }
    /**
     * Checks whether a woocommerce order is linked with a subscription
     */
    private function isSubscription(WC_Order $order) {
        if (!function_exists('wcs_order_contains_subscription'))
            return false;
            
        if (wcs_order_contains_subscription($order))
            return 'parent';
        if (wcs_order_contains_subscription($order, 'renewal'))
            return 'renewal';
        if (wcs_order_contains_subscription($order, 'resubscribe'))
            return 'resubscribe';
        if (wcs_order_contains_subscription($order, 'switch'))
            return 'switch';
        
        return false;
    }
     /**
     * Called from 'woocommerce_order_status_changed' action. 
     * If the order was completed and changed, then the user access should be removed.
     * If the order was something else and changed into completed, then we need to grant user access
     */
    public function handleOrderStatusChange($orderID, $fromStatus, $toStatus, WC_Order $order) {
        //Exclude subsciption orders as they handled in a separate action
        if ($this->isSubscription($order))
            return;

        if($fromStatus == 'completed') //Changing from completed
        {
            if($toStatus != 'completed') { //Just to make sure, but this should be always true
                error_log("Kotobee Integration: Order $orderID changed status from $fromStatus to $toStatus. Should remove the user access.");
                return $this->deactivateOrderUser($order);
            }
        } else {
            if ($toStatus == 'completed') {
                error_log("Kotobee Integration: Order $orderID changed status from $fromStatus to $toStatus. Should add user access");
                return $this->addKotobeeUserFromOrder($order);
            }
        }
    }
    /**
     * Called from 'woocommerce_subscription_status_updated' action. 
     * If the subscription was active or pending-cancel and changed, then the user access should be removed.
     * If the subscription was something else and changed into active, then we need to grant user access
     */
    public function handleSubscriptionStatusChange($order, $toStatus, $fromStatus) {
        //If it was active or pending-cancel
        if (in_array($fromStatus, array("active", "pending-cancel"))) {
            //Don't deactivate if status is going to be active or pending-cancel
            if (!in_array($toStatus, array("active", "pending-cancel"))) {
                error_log("Kotobee Integration: Handling subscription status change from $fromStatus to $toStatus");
                return $this->deactivateOrderUser($order);
            }
        } else {
            //Subscription wasn't active and became active
            if ($toStatus == 'active') {
                error_log("Handling subscription {$order->get_id()} activated.");
                return $this->addKotobeeUserFromOrder($order);
            }
        }
    }
    /**
     * Used to add a user (the one who made the order) to Kotobee with permissions to items linked to order products
     * @param $order WC_Order Completed order object
     */
    private function addKotobeeUserFromOrder(WC_Order $order) {
        $orderID = $order->get_id();
        error_log("Kotobee Integration: addKotobeeUserFromOrder with $orderID of class: " . get_class($order)); 

        $info = "Registered automatically by Kotobee Woocommerce Integration for Wordpress";
        $userEmail = $this->getOrderBillingEmail($order);
        if(!$userEmail) {
            $userID = $order->get_customer_id();
            $user = get_user_by('ID', $userID);
            if($user) {
                $userEmail = $user->user_email;
            }
            else {
                error_log("Kotobee Integration: Missing user email for order $orderID");
                return;
            }
        } 
        $name = $order->get_formatted_billing_full_name();
        error_log("Kotobee Integration: Billing email: " . $userEmail);
        error_log("Kotobee Integration: Billing name: " . $name);
        $items = $order->get_items();
        foreach($items as $item) {
            $this->grantUserAccess('wooProduct', $item['product_id'], $userEmail, array("name"=>$name, "info"=>$info));

            if($item['variation_id'] > 0) { //This is a variation
                error_log("Kotobee Integration: This is a variation");
                $this->grantUserAccess('wooProduct', $item['variation_id'], $userEmail, array("name"=>$name, "info"=>$info));
            }
        }
    }
    private function getOrderBillingEmail($order) {
        if (method_exists($order, 'get_billing_email')) {
            return $order->get_billing_email();
        }

        $order_data = $order->get_data();

        if (isset($order_data['billing'])) {
            if (isset($order_data['billing']['email'])) {
                return $order_data['billing']['email'];
            }
        }

        return null;
    }
    /**
     * Called locally to deactivate a user after a cancelled order or subscription
     */
    private function deactivateOrderUser(WC_Order $order) {
        error_log("Kotobee Integration: deactivateOrderUser called for order id: " . $order->get_id());
        $userEmail = $order->get_billing_email();
        $products = $order->get_items();
        foreach($products as $product) {
            $productID = $product['product_id'];
            $this->removeUserAccess('wooProduct', $productID, $userEmail);
        }
    }
    public function migrateProductMeta($products) {
        if(count($products)) {
            foreach($products as $product) {
                $links = get_post_meta($product->ID, 'kotobee_woocommerce_product_links', false);
                $kotobeeType = '';
                if(count($links)) {
                    foreach ($links as $link) {
                        switch ($link['type']) {
                            case 'global':
                                $kotobeeType = $this->kotobeeTypes['library'];
                                break;
                            case 'library':
                                $kotobeeType = $this->kotobeeTypes['library'];
                                break;
                            case 'category':
                                $kotobeeType = $this->kotobeeTypes['cat'];
                                break;
                            case 'library_book':
                                $kotobeeType = $this->kotobeeTypes['book'];
                                break;
                            case 'cloud_book':
                                $kotobeeType = $this->kotobeeTypes['cloud'];
                                break;
                        }
                        if($kotobeeType == '') continue;

                        $this->itemID = $product->ID;
                        $this->itemType = 'wooProduct';
                        $this->kotobeeItemID = $link['id'];
                        $this->kotobeeItemType = $kotobeeType;
                        $this->options = array();
                        if($this->saveLink())
                            error_log("Kotobee Integration: An old link added: Product: {$product->ID} $kotobeeType {$link['id']}");

                    }
                }
            }
        }
    }
    public function getProductVariationsCallback() {
        $response = array(
            'success'=>false,
            'data'  => array()
        );
        $productID = isset($_POST['product'])?(int) $_POST['product']:0;
        $product = wc_get_product($productID);
        if($product) {
            $response['success'] = true;
            $children = $product->get_children();
            if(!empty($children)) {
                foreach($children as $child) {
                    $variation = new WC_Product_Variation($child);
                    $response['data'][] = array(
                        "label" => $variation->get_name(),
                        "id" => $variation->get_id()
                    );
                }
            }
        }
        echo wp_json_encode($response);
        die;
    }

}