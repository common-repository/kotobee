<?php
/**
 * Interface for Kotobee integrations to inherit from
 */

abstract class KotobeeIntegration
{
    /* Singleton Pattern logic */
    protected function __construct()
    {
    }

    final public static function getInstance()
    {
        static $instances = array();

        $calledClass = get_called_class();

        if (!isset($instances[$calledClass]))
        {
            $instances[$calledClass] = new $calledClass();
        }

        return $instances[$calledClass];
    }

    final private function __clone()
    {
    }
    /* End of Singleton Pattern logic */

    public $ADD_LINK_NONCE_ID = 'kotobee-integration-add-link-nonce';
    public $REMOVE_LINK_NONCE_ID = 'kotobee-integration-remove-link-nonce';
    public $OPTIONS_PAGE_SLUG;

    public $AJAX_ACTION_LINK_ITEM;
    public $AJAX_ACTION_REMOVE_LINKED_ITEM;

    /**
     * @var bool whether or not this integration connects to a remote service
     */
    public $isRemote = false;
    public $integrationTitle = '';
    public $integrationID = '';

    protected $allowedTypes = array();
    protected $kotobeeTypes = array(
        'cloud' =>'cid',
        'book' => 'bid',
        'library' => 'libid',
        'cat' => 'catid',
        'role' => 'rid'
    );

    protected $itemID;
    protected $itemType;
    protected $kotobeeItemID;
    protected $kotobeeItemType;
    protected $options;

    /**
     * Validates serial
     * @return bool true if serial works, false otherwise.
     */
    function serialCheck() {
        global $kotobeeClient;
        return $kotobeeClient->serialCheck();
    }
    protected function tableName() {
        global $wpdb;
        return $wpdb->prefix.KOTOBEE_INTEGRATION_TABLE;
    }
    protected function isAccessRemovalAllowed() {
        return get_option('kotobee_integration_removeAccess');
    }
    /**
     * Checks if the provided item ID exists
     * @param $itemID
     * @param $itemType
     * @return mixed
     */
    abstract protected function itemIDExists($itemID, $itemType);
    abstract protected function getAllItems($itemType);

    /**
     * Implementing class must implement its own capability check function
     * @param $itemID
     * @param $itemType
     * @return mixed
     */
    abstract protected function currentUserCanLink($itemID, $itemType);
    abstract public function mySubMenuPage();
    function isKType($kType) {
        return in_array($kType, $this->kotobeeTypes);
    }
    function isType($itemType) {
        return array_key_exists($itemType, $this->allowedTypes);
    }
    function myTypes() {
        return $this->allowedTypes;
    }
    function getDefaultType() {
        reset($this->allowedTypes);
        return key($this->allowedTypes);
    }
    /**
     * Stores a link in the database
     * @return bool
     */
    protected function saveLink() {
        global $wpdb;
        
        $itemType = $this->itemType;
        $itemID = $this->itemID;
        $kotobeeItemID = $this->kotobeeItemID;
        $kotobeeItemType = $this->kotobeeItemType;
        $options = $this->options;
        
        //Some checks first
        if(!$this->isType($itemType)){
            error_log("Unknown item type");
            return false;
        }
        if(!$this->isKType($kotobeeItemType)){
            error_log("Unknown kotobee item type");
            return false;
        }
        if(!$this->itemIDExists($itemID, $itemType)){
            error_log("Item does not exist");
            return false;
        }
        if(!$kotobeeItemID) {
            error_log("Invalid kotobeeItemID value");
            return false;
        }

        //Double check it's not already there
        $query = "
          SELECT `id` 
          FROM {$this->tableName()} 
          WHERE `itemID` = %d 
            AND `itemType` LIKE '%s' 
            AND `kID` = %d 
            AND `kType` LIKE '%s'";
        $query = $wpdb->prepare($query, $itemID, $itemType, $kotobeeItemID, $kotobeeItemType);
        $result = $wpdb->get_results($query);

        if($result && count($result)){
            if(!empty($options)) {
                return $wpdb->update($this->tableName(), array(
                    "options" => json_encode($options)
                ), array("id" => $result[0]->id), array("%s"));
            }
            else
                return true;
        }

        //Now insert data
        $args = array(
            "itemID" => $itemID,
            "itemType" => $itemType,
            "kID" => $kotobeeItemID,
            "kType" => $kotobeeItemType,
        );
        $format = array("%d", "%s", "%d", "%s");

        if(!empty($options)) {
            $args['options'] = json_encode($options);
            $format[] = "%s";
        }
        $result = $wpdb->insert($this->tableName(), $args, $format);

        if($result)
            return true;
        error_log("Database error: ".$wpdb->last_error);
        return false;
    }

    /**
     * Validating options object
     *
     * @param $itemType
     * @param $itemID
     * @param $kotobeeItemType
     * @param $kotobeeItemID
     * @param $options
     * @return array
     */
    protected function cleanOptionsObject($itemType, $itemID, $kotobeeItemType, $kotobeeItemID, $options) {
        $clean = array();
        if($kotobeeItemType == $this->kotobeeTypes['book'] || $kotobeeItemType == $this->kotobeeTypes['cloud'] ) {
            if(isset($options['chapters'])) {
                $chapters = $options['chapters'];

                $chapterArray = explode(",", $chapters);
                $valid = array();
                if(count($chapterArray)) {
                    foreach ($chapterArray as $chapter) {
                        $chapter = trim($chapter);
                        if(is_numeric($chapter) && ($chapter == (int) $chapter) && $chapter >= 0)
                            if(!in_array($chapter, $valid))
                                $valid[] = $chapter;
                    }
                }
                $chapters = implode(",",$valid);
                // if($chapters !== '')
                    $clean['chapters'] = $chapters;
            }
        }
        return $clean;
    }
    /**
     * Deletes a link in the database
     *
     * @return bool
     */
    protected function deleteLink() {
        global $wpdb;

        $itemType = $this->itemType;
        $itemID = $this->itemID;
        $kotobeeItemID = $this->kotobeeItemID;
        $kotobeeItemType = $this->kotobeeItemType;

        //Some checks first
        if(!$this->isType($itemType)){
            error_log("Unknown item type");
            return false;
        }
        if(!$this->isKType($kotobeeItemType)){
            error_log("Unknown kotobee item type");
            return false;
        }
        if(!$this->itemIDExists($itemID, $itemType)){
            error_log("Item does not exist");
            return false;
        }
        if(!$kotobeeItemID) {
            error_log("Invalid kotobeeItemID value");
            return false;
        }

        //Double check it's not already there
        $query = "
          SELECT `id` 
          FROM {$this->tableName()} 
          WHERE `itemID` = %d 
            AND `itemType` LIKE '%s' 
            AND `kID` = %d 
            AND `kType` LIKE '%s'";
        $query = $wpdb->prepare($query, $itemID, $itemType, $kotobeeItemID, $kotobeeItemType);
        $result = $wpdb->get_results($query);

        if(!$result)
            return true;

        //Now insert data
        $result = $wpdb->delete($this->tableName(), array(
            "itemID" => $itemID,
            "itemType" => $itemType,
            "kID" => $kotobeeItemID,
            "kType" => $kotobeeItemType
        ), array("%d","%s","%d","%s"));

        if($result)
            return true;

        error_log("Database error: ".$wpdb->last_error);
        return false;
    }

    /**
     * An action to be hooked to add link ajax call
     */
    function addLinkedItemAjaxCallback() {
        global $wpdb;
        if(!current_user_can('manage_options'))
            die();

        $response = array(
            'success'=>false,
            'data'  => ''
        );

        $this->ajaxCallChecks($this->ADD_LINK_NONCE_ID);

        if(!$this->saveLink()) {
            $error = $wpdb->last_error;
            $response['data'] = __('Could not link item, please try again later', KOTOBEE_INTEGRATION_TEXTDOMAIN). " $error";
            echo wp_json_encode( $response );
            die();
        }

        $response['success'] = true;
        echo wp_json_encode( $response );
        die();
    }

    /**
     * An action to be hooked to remove link ajax call
     */
    function removeLinkedItemAjaxCallback() {
        if(!current_user_can('manage_options'))
            die();

        $response = array(
            'success'=>false,
            'data'  => ''
        );

        $this->ajaxCallChecks($this->REMOVE_LINK_NONCE_ID);

        $itemID = isset($_POST['itemID']) ? (int) $_POST['itemID'] : 0;
        $itemType = isset($_POST['itemType']) ? sanitize_text_field($_POST['itemType']) : null;
        $kotobeeItemID = isset($_POST['kotobeeItemID']) ? (int) $_POST['kotobeeItemID'] : 0;
        $kotobeeType = isset($_POST['kotobeeType']) ? sanitize_text_field($_POST['kotobeeType']) : null;

        if(!$this->deleteLink()) {
            $response['data'] = __('Could not remove linked item, please try again later', KOTOBEE_INTEGRATION_TEXTDOMAIN);
            echo wp_json_encode( $response );
            die();
        }

        $response['success'] = true;
        echo wp_json_encode( $response );
        die();
    }
    protected function ajaxCallChecks($nonceType) {
        $response = array(
            'success'=>false,
            'data'  => ''
        );

        if( !isset($_POST['nonce']) ||
            !isset($_POST['itemID']) ||
            !isset($_POST['itemType']) ||
            !isset($_POST['kotobeeItemID']) ||
            !isset($_POST['kotobeeType'])
        ) {
            $response['data'] = __('Missing argument', KOTOBEE_INTEGRATION_TEXTDOMAIN);
            echo wp_json_encode( $response );
            die();
        }

        $verified = wp_verify_nonce( $_POST['nonce'], $nonceType);
        $itemID = isset($_POST['itemID']) ? (int) $_POST['itemID'] : 0;
        $itemType = isset($_POST['itemType']) ? sanitize_text_field($_POST['itemType']) : null;
        $kotobeeItemID = isset($_POST['kotobeeItemID']) ? (int) $_POST['kotobeeItemID'] : 0;
        $kotobeeType = isset($_POST['kotobeeType']) ? sanitize_text_field($_POST['kotobeeType']) : null;
        $options = isset($_POST['options']) ? $this->sanitize_options($_POST['options']) : array();
        
        if( !$this->currentUserCanLink($itemID, $itemType) || ! $verified) {
            $response['data'] = __('Access Denied', KOTOBEE_INTEGRATION_TEXTDOMAIN);
            echo wp_json_encode( $response );
            die();
        }

        if(!$this->isType($itemType)) {
            $response['data'] = __('Unknown Type', KOTOBEE_INTEGRATION_TEXTDOMAIN);
            echo wp_json_encode( $response );
            die();
        }
        if(!$this->isKType($kotobeeType)) {
            $response['data'] = __('Unknown Kotobee item type', KOTOBEE_INTEGRATION_TEXTDOMAIN);
            echo wp_json_encode( $response );
            die();
        }
        if(!$kotobeeItemID) {
            $response['data'] = __('Unknown Kotobee item ID', KOTOBEE_INTEGRATION_TEXTDOMAIN);
            echo wp_json_encode( $response );
            die();
        }
        if(isset($options['chapters'])) {
            $chapters = $options['chapters'];
            //Validating chapters field
            $chapters = preg_replace("/' *?, *?(['\"0-9])/i", '",$1', $chapters); //convert single quotes to double quotes
            $chapters = preg_replace("/(['\"0-9]) *?, *?'/i", '$1,"', $chapters); //convert single quotes to double quotes
            $chapters = preg_replace("/['] *$/i", '"', $chapters); //convert single quote at end to double quote
            $chapters = preg_replace("/^ *[']/i", '"', $chapters); //convert single quote at start to double quote
            $chapterArray = json_decode("[" . $chapters . "]");
            if ($chapterArray === NULL) {
                $response['data'] = __('Chapters format is invalid', KOTOBEE_INTEGRATION_TEXTDOMAIN);
                echo wp_json_encode( $response );
                die();
            }
        }

        $this->itemID = $itemID;
        $this->itemType = $itemType;
        $this->kotobeeItemID = $kotobeeItemID;
        $this->kotobeeItemType = $kotobeeType;
        $this->options = $options;
    }

    /**
     * Gets kotobee items linked with the provided item
     * @param $itemID
     * @param $itemType
     * @param bool $all true to show all columns
     * @return array
     */
    protected function getLinkedKotobeeItems($itemIDs, $itemType, $all = false) {
        global $wpdb;
        $linkedItems = array();

        $itemIDs = implode(',', array_map( 'absint', $itemIDs ));
        
        //Check if supported item type
        if(!$this->isType($itemType)) return $linkedItems;
        $cols = $all?"*":"`kType`,`kID`";
        $query = "
          SELECT $cols
          FROM {$this->tableName()} 
          WHERE `itemID` IN ($itemIDs) 
            AND `itemType` LIKE '%s' 
            ";
        $query = $wpdb->prepare($query, $itemType);
        $results = $wpdb->get_results($query, ARRAY_A);

        if($results && count($results)) {
            $linkedItems = $results;
        }

        return $linkedItems;
    }
    protected function getTypeLinkedKotobeeItems($itemType) {
        global $wpdb;
        $linkedItems = array();

        //Check if supported item type
        if(!array_key_exists($itemType, $this->allowedTypes)) return $linkedItems;

        $query = "
          SELECT *
          FROM {$this->tableName()} 
          WHERE `itemType` LIKE '%s' 
            ";
        $query = $wpdb->prepare($query, $itemType);
        $results = $wpdb->get_results($query, ARRAY_A);

        if($results && count($results)) {
            $linkedItems = $results;
        }

        return $linkedItems;
    }
    public function getLinkedItemsObject($type, $itemIDs = array()) {
        $linked = array();

        if($itemIDs)
            $linkedItems = $this->getLinkedKotobeeItems($itemIDs, $type, true);
        else
            $linkedItems = $this->getTypeLinkedKotobeeItems($type);
        if(count($linkedItems)) {
            foreach ($linkedItems as $linkedItem) {
                $itemKey = $linkedItem['itemID'];
                $kType = $linkedItem['kType'];
                $options = isset($linkedItem['options'])&&$linkedItem['options']?$linkedItem['options']:"{}";
                $linked[$itemKey][$kType][$linkedItem['kID']] = json_decode($options, true);


                /*$itemArray = array(
                    $this->kotobeeTypes['library'] => array(),
                    $this->kotobeeTypes['book'] => array(),
                    $this->kotobeeTypes['cat'] => array(),
                    $this->kotobeeTypes['cloud'] => array(),
                    $this->kotobeeTypes['role'] => array(),
                );
                if($this->isKType($kType))
                    $itemArray[$kType][] = $linkedItem['kID'];

                $linked[$itemKey] = $itemArray;*/
            }
        }
        return $linked;
    }

    public function settingsPageOutput() {
        require_once plugin_dir_path(__FILE__).'../admin/linking-page.php';

    }
    /**
     * Outputs HTML of admin notices
     *
     * @param string $status
     * @param string $message
     */
    protected function messageOutputHTML($status = 'success', $message='') {
        echo "<div id=\"message\" class=\"notice notice-" . esc_attr($status) . "\"><p>" . esc_html($message) . "</p></div>";
    }

    /**
     * @see KotobeeApiClient for possible user args
     *
     * @param $itemType
     * @param $itemID
     * @param $email
     * @param array
     * @return bool
     */
    protected function grantUserAccess($itemType, $itemID, $email, $args = array()) {
        global $kotobeeClient;
        $sendEmail = get_option('kotobee_integration_sendEmail');

        $linked = $this->getLinkedKotobeeItems(array($itemID), $itemType, true);

        if(!count($linked)){
            error_log("No kotobee items linked to $itemType-$itemID to grant user access over");
            return false;
        }
        $success = true;
        foreach ($linked as $item) {
           $user = array_merge($args, array(
               "email" => $email,
               "active" => 1,
               'noemail' => $sendEmail?0:1,
               $item['kType'] => $item['kID'],
           ));
           if($item['options']) {
               $options = json_decode($item['options']);
               foreach ($options as $key => $option)
                   $user[$key] = $option;
           }
           $response = $kotobeeClient->addUser($user);
           if(!$response['Success']){
               //In case of assigning a role, if the user is already added this will return an error. Must be edited instead
               if($response['Message'] == "s_alreadyRegistered") {
                   $response = $kotobeeClient->editUser($user);
                   if(!$response['Success'])
                       error_log("KotobeeAPI Error: ".$response['Message']);
               } else
                    error_log("KotobeeAPI Error: ".$response['Message']);
           }
           $success = $success && $response['Success'];
        }
        return $success;
    }
    /**
     * @see KotobeeApiClient for possible user args
     *
     * @param $itemType
     * @param $itemID
     * @param $email
     * @param array $otherUserKotobeeItems Use this to prevent removing user access from an item that's registered with another plan or product
     * @return bool
     */
    protected function removeUserAccess($itemType, $itemID, $email, $otherUserKotobeeItems = array()) {
        global $kotobeeClient;

        if(!$this->isAccessRemovalAllowed()) {
            error_log("Access removal is not allowed.");
            return false;
        }

        error_log("Removing access of user $email from local item $itemType of ID $itemID");

        $linked = $this->getLinkedKotobeeItems(array($itemID), $itemType);

        if(!count($linked)){
            error_log("No kotobee items linked to $itemType-$itemID to remove user access over");
            return false;
        }
        $success = true;
        foreach ($linked as $item) {
            if($this->shouldKeepKotobeeItem($item['kType'], $item['kID'], $otherUserKotobeeItems)){
                error_log("User $email is linked to kotobee {$item['kType']}:{$item['kID']} from another way!");
                continue;
            }

            $user = array(
                "email" => $email,
                $item['kType'] => $item['kID']
            );
           $response = $kotobeeClient->deleteUser($user);
           if(!$response['Success'])
               error_log("KotobeeAPI Error: ".$response['Message']);
           $success = $success && $response['Success'];
        }
        return $success;
    }

    /**
     * Checked before removing user access to a kotobee item to make sure user hasn't got his item in another product/subscription
     * @param $testType
     * @param $testID
     * @param $userItems
     * @return bool
     */
    private function shouldKeepKotobeeItem($testType, $testID, $userItems) {
        error_log("Checking should keep kotobee item");
        kotobee_log($userItems);

        $itemArr = array(
            'kID' => $testID,
            'kType' => $testType
        );
        kotobee_log($itemArr);

        if(in_array($itemArr,$userItems)){
            error_log("Keep it!");
            return true;
        }
        error_log("Do not keep!");
        return false;
/*        if(isset($userItems[$testType]))
            if(is_array($userItems[$testType]))
                if(in_array($testID, $userItems{$testType}))
                    return true;*/

//        return false;

    }
    protected function loadCommonHooks() {
        if(is_admin()) {
            add_action("wp_ajax_".$this->AJAX_ACTION_LINK_ITEM, array($this,'addLinkedItemAjaxCallback'));
            add_action("wp_ajax_".$this->AJAX_ACTION_REMOVE_LINKED_ITEM, array($this,'removeLinkedItemAjaxCallback'));
        }

        add_action('admin_menu',array($this, 'mySubMenuPage'));

        add_action( 'admin_enqueue_scripts', array($this, 'enqueueLinkingScript'));
    }
    function enqueueLinkingScript($hook) {
        if(strpos($hook, $this->OPTIONS_PAGE_SLUG) === false)
            return;

        $this->registerLinkingScript();
    }
    protected function registerLinkingScript() {
        wp_register_script('kotobee-linking-script', plugin_dir_url(__FILE__).'../js/linking-functions.js','jquery','1.4',true);
        $linkingConfig = array(
            "ajax_link_item" => $this->AJAX_ACTION_LINK_ITEM,
            "add_link_nonce" => wp_create_nonce($this->ADD_LINK_NONCE_ID),
            "ajax_unlink_item" => $this->AJAX_ACTION_REMOVE_LINKED_ITEM,
            "remove_link_nonce" => wp_create_nonce($this->REMOVE_LINK_NONCE_ID),
            "translation" => array(
                "link" => esc_js(__('Link', KOTOBEE_INTEGRATION_TEXTDOMAIN ) ),
                "unlink" => esc_js(__('Unlink', KOTOBEE_INTEGRATION_TEXTDOMAIN ) ),
                "anyVariation" => esc_js(__('Any Variation', KOTOBEE_INTEGRATION_TEXTDOMAIN) )
            )
        );
        wp_localize_script('kotobee-linking-script', "linkingConfig", $linkingConfig);
        wp_enqueue_script('kotobee-linking-script');
    }
    public function getUserData() {
        global $kotobeeClient;
        return $kotobeeClient->getUserLibrariesAndCloudBooks();
    }

    /**
     * This function returns a written summary of what is linked with this item.
     * @param $linkedObject
     * @param $kotobeeData
     * @param $currentItemID
     * @return string
     */
    public function outputReadableSummary($linkedObject, $kotobeeData, $currentItemID) {
        if(!isset($linkedObject[$currentItemID])) return '';
        if(!count($kotobeeData['Object'])) return '';

        $links = $linkedObject[$currentItemID];
        kotobee_log($links, "Item links: ");
        $libraries = $kotobeeData['Object']['libraries'];
        $books = $kotobeeData['Object']['books'];

        $output = '';

        if(count($libraries)) {
            foreach ($libraries as $library) {
                $globalStr = $roleStr = $catStr = $bookStr = '';

                $libStr = "<ul>";
                $libName = $library['lib']['name'];

                //Global Access
                if(isset($links['libid']) && array_key_exists($library['lib']['id'], $links['libid'])) {
                    /* translators: An item is linked with global access in %s library */
                    $globalStr = "<li>".sprintf("<code>".esc_html__("Global access", KOTOBEE_INTEGRATION_TEXTDOMAIN)."</code> ".esc_html__("in %s.", KOTOBEE_INTEGRATION_TEXTDOMAIN), $libName)."</li>";
                }
                //Roles
                if(isset($links['rid'])) {
                    $linked = false;
                    $roleStr = "";
                    if(count($library['lib']['cloud']['roles'])) {
                        foreach ($library['lib']['cloud']['roles'] as $role) {
                            if(array_key_exists($role['id'], $links['rid'])) {
                                $linked = true;
                                /* translators: An item is linked with role access in %s library */
                                $roleStr .= "<li><code>{$role['title']}</code> ".sprintf(esc_html__("role in %s:", KOTOBEE_INTEGRATION_TEXTDOMAIN), $libName)."</li>";
                            }
                        }
                    }
                    if(!$linked) $roleStr = '';
                }
                //Categories
                if(isset($links['catid'])) {
                    $linked = false;
                    $catStr = "";
                    if(count($library['lib']['categories'])) {
                        foreach ($library['lib']['categories'] as $category) {
                            if(array_key_exists($category['id'], $links['catid'])) {
                                $linked = true;
                                /* translators: An item is linked with category access in %s library */
                                $catStr .= "<li><code>{$category['name']}</code> ".sprintf(esc_html__("category in %s:", KOTOBEE_INTEGRATION_TEXTDOMAIN), $libName)."</li>";
                            }
                        }
                    }
                    if(!$linked) $catStr = '';
                }
                //Books
                if(isset($links['bid'])) {
                    $linked = false;
                    $bookStr = "";
                    if(count($library['lib']['books'])) {
                        foreach ($library['lib']['books'] as $book) {
                            if(array_key_exists($book['id'], $links['bid'])) {
                                $linked = true;
                                $bookStr .= "<li>";
                                $bookOptions = $links['bid'][$book['id']];
                                if(isset($bookOptions['chapters']))
                                    $bookStr .= sprintf(esc_html__("Chapters %s of ", KOTOBEE_INTEGRATION_TEXTDOMAIN), $bookOptions['chapters']);
                                /* translators: An item is linked with book access in %s library */
                                $bookStr .= "<code>{$book['name']}</code> ".sprintf(esc_html__("book in %s.", KOTOBEE_INTEGRATION_TEXTDOMAIN), $libName);
                                $bookStr .= "</li>";
                            }
                        }
                    }
                    if(!$linked) $bookStr = '';
                }



                $result = $globalStr.$roleStr.$catStr.$bookStr;
                if($result == ''){
                    $libStr = '';
                } else {
                    $libStr .= $result . "</ul>";
                }

                $output .= $libStr;
            }
        }

        if(count($books) && isset($links['cid'])) {
            $linked = false;
            $bookStr = '<ul>';
            foreach($books as $book) {
                if(array_key_exists($book['id'], $links['cid'])) {
                    $linked = true;
                    $bookStr .= "<li><code>{$book['name']}</code> ". esc_html__("Cloud Ebook", KOTOBEE_INTEGRATION_TEXTDOMAIN)."</li>";
                }
            }
            $bookStr .= '</ul>';

            if(!$linked) $bookStr = '';
            $output .= $bookStr;
        }
        return $output;
    }
    public function outputIntroHtml($type) {
        $path = KOTOBEE_INTEGRATION_PLUGIN_BASE_DIR_PATH.'admin/content/';
        $path .= $this->integrationID.'-'.$type.'-intro.php';
        if(file_exists($path))
            include($path);
    }
    protected function updateConnectionStatus($status = true) {
        $option = "kotobee_integration_".$this->integrationID."_connected";
        update_option($option, $status, true);
    }
    public function isConnected() {
        $option = "kotobee_integration_".$this->integrationID."_connected";
        return get_option($option);
    }

    /**
     * Cleans options object coming from external source
     */
    public function sanitize_options($options = array()) {
        $sanitized = array();

        //Currently, we only support "chapters" option
        if (isset($options['chapters'])) {
            $chapters = stripslashes($options['chapters']);
            $chapters = sanitize_text_field($chapters);
            $sanitized['chapters'] = $chapters;
        }

        return $sanitized;
    }

}
//Compatibility with PHP < 5.3
// get_called_class() is only in PHP >= 5.3.
if (!function_exists('get_called_class'))
{
    function get_called_class()
    {
        $bt = debug_backtrace();
        $l = 0;
        do
        {
            $l++;
            $lines = file($bt[$l]['file']);
            $callerLine = $lines[$bt[$l]['line']-1];
            preg_match('/([a-zA-Z0-9\_]+)::'.$bt[$l]['function'].'/', $callerLine, $matches);
        } while ($matches[1] === 'parent' && $matches[1]);

        return $matches[1];
    }
}