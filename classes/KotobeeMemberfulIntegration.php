<?php

class KotobeeMemberfulIntegration extends KotobeeIntegration
{
    public $AJAX_ACTION_LINK_ITEM = 'kotobee_memberful_integration_add_linked_plan';
    public $AJAX_ACTION_REMOVE_LINKED_ITEM = 'kotobee_memberful_integration_remove_linked_plan';
    public $OPTIONS_PAGE_SLUG = 'memberful-integration-settings';

    public $isRemote = true;

    public $integrationID = 'memberful';

    protected $allowedTypes = array('memberfulPlan'=>"Plan", 'memberfulProduct'=>"Download");

    private $isMemberfulActive;
    private $ENDPOINT = 'kotobee_memberful_endpoint';

    protected function __construct()
    {
        $this->integrationTitle = __('Kotobee Memberful Integration', KOTOBEE_INTEGRATION_TEXTDOMAIN);

        if ( !in_array( 'memberful-wp/memberful-wp.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
            $this->isMemberfulActive = false;
            add_action( 'admin_notices', array($this, 'showMemberfulNotice') );
            return;
        }
        $this->loadCommonHooks();

        add_action( 'wp_loaded', array($this, 'webHookCallback') );
    }
    function showMemberfulNotice() {
        $this->messageOutputHTML('error', __('Kotobee Memberful Integration requires Memberful WP plugin to be installed and activated in your website. Please install it to be able to use our plugin', KOTOBEE_INTEGRATION_TEXTDOMAIN));
    }

    protected function itemIDExists($itemID, $itemType)
    {
        $items = $this->getAllItems($itemType);
        if(count($items)) {
            foreach ($items as $key => $value) {
                if($key == $itemID)
                    return true;
            }
        }
        return false;
    }

    /**
     * Gets all items of the supported type
     * @param $itemType
     * @return array|mixed
     */
    protected function getAllItems($itemType)
    {
        $items = $result = array();
        if($itemType == 'memberfulPlan') {
            $plans = get_option('memberful_subscriptions', array());
            if($plans)
                $items = $plans;
        }
        if($itemType == 'memberfulProduct') {
            $products = get_option('memberful_products', array());
            if($products)
                $items = $products;
        }
        if(count($items))
            foreach($items as $item)
                $result[$item['id']] = $item['name'];
        return $result;
    }
    protected function currentUserCanLink($itemID, $itemType)
    {
        return current_user_can('manage_options');
    }

    function mySubMenuPage() {
        //The main settings page
        add_submenu_page(
            'kotobee-integration',
            __('Memberful Integration Settings', KOTOBEE_INTEGRATION_TEXTDOMAIN),
            __('Memberful', KOTOBEE_INTEGRATION_TEXTDOMAIN),
            'manage_options',
            $this->OPTIONS_PAGE_SLUG,
            array($this,'settingsPageOutput')
        );
    }

    /**
     * An action fired at woocommerce completed order. Used to add a user (the one how made the order) to Kotobee with permissions to items linked before to order products
     * @param $orderID int Completed order ID
     */
    public function extractOrderDetails($orderID) {
        $order = new WC_Order($orderID);
        $userEmail = $order->get_billing_email();
        $products = $order->get_items();
        foreach($products as $product) {
            $productID = $product['product_id'];
            $this->grantUserAccess('wooProduct', $productID, $userEmail);
        }
    }
    public function webHookCallback() {
        if(isset($_GET[$this->ENDPOINT]) && $_GET[$this->ENDPOINT] == 'webhook') {
            error_log("Webhook endpoint triggered");
            if($_SERVER['REQUEST_METHOD'] != "POST")
                die( "Invalid request" );
            header('Cache-Control: private');
            try {
                $payload = file_get_contents( 'php://input' );
                error_log($payload);
                if($payload)
                    $this->verifyMemberfulAction(json_decode($payload));
                else
                    error_log("Kotobee Memberful Error: Empty payload.");
            } catch (Exception $exception) {
                error_log("Kotobee Memberful Error: ".$exception->getMessage());
            }
            http_response_code(200);
            exit;
        }
    }
    //Working here
    private function verifyMemberfulAction($payload) {
        error_log("Verifying payload!");
        if ( strpos( $payload->event, 'order.purchased' ) !== FALSE ) { //When a new user registers
            $this->updateConnectionStatus();

            $memberID = (int) $payload->order->member->id;
            if($payload->order->status != 'completed')
                return; //Nothing to do then! Wait for another event in order to take action
            error_log("order completed!");

            $member = memberful_api_member($memberID);
            if(is_wp_error($member)) {
                error_log("Kotobee Memberful Error: Unknown user in a webhook!");
                error_log($member->get_error_message());
                return;
            }
            error_log('Kotobee Memberful: Processing order purchase webhook for member '.$memberID);

            $subscriptions = $payload->order->subscriptions;
            if(count($subscriptions))
                foreach ($subscriptions as $subscription){
                    if($planID = $this->isMemberSubscribedAndActive($member, $subscription))
                        $this->addMemberToMatchingKotobeeItem($member, $planID, "memberfulPlan");
                }

            $products = $payload->order->products;
            if(count($products))
                foreach ($products as $product)
                    if($productID = $this->didMemberPurchase($member, $product))
                        $this->addMemberToMatchingKotobeeItem($member, $productID, "memberfulProduct");

        }/*elseif ( strpos( $payload->event, 'member_signup' ) !== FALSE ) {
            $memberID = (int) $payload->subscription->member->id;
            $subscriptionID = (int) $payload->subscription->id;
            error_log('Kotobee Memberful: Processing subscription webhook for member '.$memberID);
        }*/elseif ( strpos( $payload->event, 'subscription.activated' ) !== FALSE ) {
            $memberID = (int) $payload->subscription->member->id;
            $subscription = $payload->subscription;

            $member = memberful_api_member($memberID);
            if(is_wp_error($member)) {
                error_log("Kotobee Memberful Error: Unknown user in a webhook!");
                return;
            }
            error_log('Kotobee Memberful: Processing subscription activated webhook for member '.$memberID);

            if($planID = $this->isMemberSubscribedAndActive($member, $subscription))
                $this->addMemberToMatchingKotobeeItem($member, $planID, "memberfulPlan");

        } elseif ( strpos( $payload->event, 'subscription.renewed' ) !== FALSE ) {
            $memberID = (int) $payload->subscription->member->id;
            $subscription = $payload->subscription;

            $member = memberful_api_member($memberID);
            if(is_wp_error($member)) {
                error_log("Kotobee Memberful Error: Unknown user in a webhook!");
                return;
            }
            error_log('Kotobee Memberful: Processing subscription renewed webhook for member '.$memberID);

            if($planID = $this->isMemberSubscribedAndActive($member, $subscription))
                $this->addMemberToMatchingKotobeeItem($member, $planID, "memberfulPlan");

        } elseif ( strpos( $payload->event, 'subscription.deactivated' ) !== FALSE ) {
            $memberID = (int) $payload->subscription->member->id;
            $subscription = $payload->subscription;

            $member = memberful_api_member($memberID);
            if(is_wp_error($member)) {
                error_log("Kotobee Memberful Error: Unknown user in a webhook!");
                return;
            }
            error_log('Kotobee Memberful: Processing subscription deactivated webhook for member '.$memberID);
            $plan = $subscription->subscription_plan;
            if(!$this->isMemberSubscribedAndActive($member, $plan, true))
                $this->deleteMemberFromMatchingKotobeeItem($member, $plan->id, "memberfulPlan");

        } elseif ( strpos( $payload->event, 'subscription.deleted' ) !== FALSE ) {
            $memberID = (int) $payload->subscription->member->id;
            $subscription = $payload->subscription;
            $member = memberful_api_member($memberID);
            if(is_wp_error($member)) {
                error_log("Kotobee Memberful Error: Unknown user in a webhook!");
                return;
            }
            error_log('Kotobee Memberful: Processing subscription deleted webhook for member '.$memberID);
            $plan = $subscription->subscription_plan;
            if(!$this->isMemberSubscribedAndActive($member, $plan, true))
                $this->deleteMemberFromMatchingKotobeeItem($member, $plan->id, "memberfulPlan");

        } elseif ( strpos( $payload->event, 'subscription.updated' ) !== FALSE ) {
            $memberID = (int) $payload->subscription->member->id;
            $subscription = $payload->subscription;

            $member = memberful_api_member($memberID);
            if(is_wp_error($member)) {
                error_log("Kotobee Memberful Error: Unknown user in a webhook!");
                return;
            }
            error_log('Kotobee Memberful: Processing subscription updated webhook for member '.$memberID);

            if($planID = $this->isMemberSubscribedAndActive($member, $subscription))
                $this->addMemberToMatchingKotobeeItem($member, $planID, "memberfulPlan");
            else
                $this->deleteMemberFromMatchingKotobeeItem($member, $planID, "memberfulPlan");

        } else {
            error_log("Kotobee Memberful: Ignoring webhook " . $payload->event);
        }
    }

    /**
     * @param $memberObject
     * @param $subscription object Subscription or Plan object
     * @param bool $byPlan true to query by plan, false to query by member subscription
     * @return null
     */
    private function isMemberSubscribedAndActive($memberObject, $subscription, $byPlan = false) {
        $planID = null;
        $memberSubs = $memberObject->subscriptions;
        kotobee_log(print_r($memberSubs, true));
        if(count($memberSubs)) {
            foreach ($memberSubs as $subsc) {
                if($byPlan) {
                    if ($subsc->subscription->id == $subscription->id) {
                        if ($subsc->active)
                            $planID = $subsc->subscription->id;
                        break;
                    }
                } else {
                    if ($subsc->id == $subscription->id) {
                        if ($subsc->active)
                            $planID = $subsc->subscription->id;
                        break;
                    }
                }

            }
        }
        return $planID;
    }
    private function didMemberPurchase($memberObject, $product) {
        $memberProducts = $memberObject->products;
        if(count($memberProducts)) {
            foreach ($memberProducts as $prod) {
                if ($prod->product->id == $product->id)
                    return $prod->product->id;
            }
        }
        return null;
    }
    private function addMemberToMatchingKotobeeItem($memberObject, $memberfulItemID, $itemType) {
        error_log("Granting user access");

        $args = array(
            "name" => $memberObject->member->full_name,
            "info" => "Registered automatically by Kotobee Memberful Integration for Wordpress"
        );
        $this->grantUserAccess($itemType, $memberfulItemID, $memberObject->member->email, $args);
    }
    private function deleteMemberFromMatchingKotobeeItem($memberObject, $memberfulItemID, $itemType) {
        error_log("Deleting user access!");

        $this->removeUserAccess($itemType, $memberfulItemID, $memberObject->member->email, $this->getMemberKotobeeItems($memberObject));
    }
    private function getMemberKotobeeItems($memberObject) {
        error_log("Get member kotobee items");
        $items = array();
        $memberSubs = $memberObject->subscriptions;

        if(count($memberSubs)) {
            $planIDs = array();
            foreach ($memberSubs as $subsc) {
                $planIDs[] = $subsc->subscription->id;
            }
            $items = array_merge($items, $this->getLinkedKotobeeItems($planIDs, 'memberfulPlan'));
        }
        $prods = $memberObject->products;
        if(count($prods)) {
            $prodIDs = array();
            foreach ($prods as $prod) {
                $prodIDs[] = $prod->id;
            }
            $items = array_merge($items, $this->getLinkedKotobeeItems($prodIDs, 'memberfulProduct'));
        }
        return $items;
    }
    public function getWebhookEndpoint() {
        return add_query_arg(array($this->ENDPOINT => 'webhook'), home_url());
    }

}