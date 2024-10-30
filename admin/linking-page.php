<?php
if(!isset($this) || !is_a($this,'KotobeeIntegration'))
    exit;
global $kotobeeClient;

if(isset($_GET['type'])) {
    $type = sanitize_key($_GET['type']);
    //Validate
    if(!$this->isType($type))
        wp_die(esc_html__("The requested page was not found", KOTOBEE_INTEGRATION_TEXTDOMAIN));
} else {
    $type = $this->getDefaultType();
}

if(!$this->serialCheck()) {
    return;
}

$kotobeeItems = $this->getUserData();
$linkedObjects = $this->getLinkedItemsObject($type);
$items = $this->getAllItems($type);

?>
<style>
    select#sub-items {
        display: none;
    }
    select#linking-box {
        margin-top:7px;
    }
    #connectionBadge.connected {
        font-size: 11px;
        margin: 0 10px;
        border-radius: 4px;
        background: green;
        padding: 5px;
        color: white;
        display: inline-block;
        line-height: initial;
    }
    #connectionBadge.untested {
        font-size: 11px;
        margin: 0 10px;
        border-radius: 4px;
        background: grey;
        padding: 5px;
        color: white;
        display: inline-block;
        line-height: initial;
    }
</style>
<div class="wrap">
    <?php
    
    if($this->isRemote) {
        if($this->isConnected()) {
            $badge = __("Connected",KOTOBEE_INTEGRATION_TEXTDOMAIN);
            $badge_class = 'connected';
        }
        else {
            $badge = __("Untested",KOTOBEE_INTEGRATION_TEXTDOMAIN);
            $badge_class = 'untested';
        }
    }
    ?>
    <h1><?php echo esc_html($this->integrationTitle) . "<span id='connectionBadge' class='" . esc_attr($badge_class) . "'>" . esc_html($badge) . "</span>"; ?></h1>
    <?php
    if(count($this->allowedTypes) > 1):
    ?>
    <div id="type-navigation" class="nav-tab-wrapper" style="margin-bottom: 10px;">
        <?php
        foreach($this->allowedTypes as $key => $value) {
            $url = add_query_arg(array("page"=>$this->OPTIONS_PAGE_SLUG, "type"=>$key), admin_url('admin.php'));
            $active = $key == $type?'nav-tab-active':'';
            echo "<a id='" . esc_attr($key) . "'  href='" . esc_attr($url) . "' class='nav-tab " . esc_attr($active) . "'>" . esc_html($value) . "</a>";
        }
        ?>
    </div>
    <?php
    endif; //End allowed type > 1

    if(!count($items)) {
        echo "<div class='wrap'><div class='notice notice-warning'><p>".esc_html__("You do not have any {$this->allowedTypes[$type]} to be linked with Kotobee", KOTOBEE_INTEGRATION_TEXTDOMAIN)."</p></div></div>";
        return;
    }

    ?>
    <div id="integration-intro">
        <?php 
        //TODO: Find a better way to do this!
        $this->outputIntroHtml($type); 
        ?>
    </div>

    <div id="linking-box">
        <select id="items">
            <option value="0" selected="selected"><?php esc_html_e("Select one",KOTOBEE_INTEGRATION_TEXTDOMAIN)?></option>
            <?php
            foreach ($items as $ID => $name) :
                ?>
                <option value="<?php echo esc_attr($ID); ?>"><?php echo esc_html($name); ?></option>
            <?php
            endforeach;
            ?>
        </select>
        <select id="sub-items"></select>
        <div class="spinner"></div>
    </div>

    <?php include_once plugin_dir_path(__FILE__).'book-selection-template.php'; ?>
</div>
<script>
    var linkedObj = <?php echo wp_json_encode($linkedObjects); ?>;
    var selectedItemID = jQuery('#items').find(":selected").val();
    var currentItemType = "<?php echo esc_js($type); ?>";
    jQuery(function() {
        jQuery('#items').change(function() {
            selectedItemID = jQuery('#items').find(":selected").val();
            updateSubItems();
            updateLinkedState();
        });
        jQuery('#sub-items').change(function() {
            selectedItemID = jQuery('#sub-items').find(":selected").val();
            updateLinkedState();
        });
        updateLinkedState();
    });

</script>
