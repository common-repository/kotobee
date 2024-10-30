<?php

    if ( ! defined( 'ABSPATH' ) ){
        echo 'direct execution is not allowed';
        exit;
    } // Exit if accessed directly

    if(!isset($this) || !is_a($this,'KotobeeWoocommerceIntegration')) {
        echo 'this is does not belong to the meant class';
        return;
    } //Return if called from outside Woocommerce integration

    if(!isset($product) || !isset($type)){
        echo 'type or product is not defined';
        return;
    } //Return if these are not defined

    if(!$this->serialCheck()) {
        esc_html_e('Your serial number is not valid.',KOTOBEE_INTEGRATION_TEXTDOMAIN);
    }
    else {
        $kotobeeItems = $this->getUserData();
        $linkedObjects = $this->getProductLinkedObject($product->ID);

        $summary = $this->outputReadableSummary($linkedObjects, $kotobeeItems, $product->ID);
        if($summary)
            echo "<div class='productMsg'>" . esc_html__("This product is linked to:",KOTOBEE_INTEGRATION_TEXTDOMAIN) . "</div>" . wp_kses($summary, ["ul"=>[], "li"=>[], "code"=>[]]);
        else
            echo "<div class='productMsg'>" . esc_html__("This product is not yet linked to any Kotobee book or library.", KOTOBEE_INTEGRATION_TEXTDOMAIN)."</div><br/>";
        ?>
        <?php add_thickbox(); ?>
        <style>
			.productMsg{
				margin: 10px auto;
			}
            #TB_ajaxContent {
                width: 100% !important;
                height: 90% !important;
                box-sizing: border-box;
            }
            .rtl #TB_ajaxContent {
                text-align: right;
            }
            .nav-tab.nav-tab-active {
                background-color: #fff;
                border-bottom: 1px solid #fff;
            }
        </style>
        <a href="#TB_inline?height=550&inlineId=kotobee-book-selection" class='button button-primary thickbox link-to-kotobee' data-item-id='<?php echo esc_attr($product->ID);?>'><?php esc_html_e("Link to Kotobee", 'kotobee-woocommerce-integration'); ?></a>
        <?php include_once plugin_dir_path(__FILE__).'book-selection-template.php'; ?>
        <script>
            var linkedObj = <?php echo wp_json_encode($linkedObjects); ?>;
            var selectedItemID = "<?php echo esc_js($product->ID); ?>";
            var currentItemType = "<?php echo esc_js($type); ?>";
            jQuery(function() {
                jQuery(document).on('mousedown', ".link-to-kotobee", function() { //Click is disabled for thickbox triggers
                    selectedItemID = jQuery(this).attr('data-item-id');
                    updateLinkedState(false);
                });
                updateLinkedState(false);
            });

        </script>
        <?php
    }


