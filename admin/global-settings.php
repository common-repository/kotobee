<?php
/**
 * Settings page of Kotobee Integration Plugin
 */
global $kotobee_integrations;

$currentSerial = get_option('kotobee_integration_serial');
$sendEmail = get_option('kotobee_integration_sendEmail');
$removeAccess = get_option('kotobee_integration_removeAccess');
$activeIntegrations = get_option('kotobee_integration_active');
$apiDomain = get_option('kotobee_integration_apiDomain');

$sendEmail = $sendEmail?'checked':'';
$removeAccess = $removeAccess?'checked':'';

?>
<style>
    .integration-item {
        display: inline-block;
        float:left;
        margin-right:20px;
    }
    .integration-item input.integration-checkbox {
        margin-top:-60px;
    }
    input#kotobee-serial, input#kotobee-domain {
        width:90%;
    }
    #kotobee-submit {
        margin-top: 10px;
    }
</style>
<!-- A Wrap as recommended by Wordpress -->
<div class="wrap">
    <h1><?php esc_html_e('Kotobee Integration Settings',KOTOBEE_INTEGRATION_TEXTDOMAIN);?></h1>
    <form method="post" action="">
        <table class="form-table">
            <tr>
                <td width="30%">
                    <label for="kotobee-serial"><?php esc_html_e('Kotobee Serial Number',KOTOBEE_INTEGRATION_TEXTDOMAIN); ?></label>
                </td>
                <td width="70%">
                    <input id="kotobee-serial" name="kotobee-serial" type="text" value="<?php echo esc_attr($currentSerial); ?>"/>
                </td>
            </tr>
            <tr id="integrations">
                <td width="30%">
                    <?php esc_html_e('Integrate with:',KOTOBEE_INTEGRATION_TEXTDOMAIN);?>
                </td>
                <td width="70%">
                    <?php
                    if(count($kotobee_integrations)) {
                        foreach ($kotobee_integrations as $value => $label) {
                            $checked = '';
                            if(is_array($activeIntegrations) && in_array($value, $activeIntegrations))
                                $checked = 'checked';
                            $img = plugin_dir_url(__FILE__).'../images/'.$value.'.png';
                            ?>
                            <div class="integration-item">
                                <label for="<?php echo esc_attr($value); ?>">
                                    <input class="integration-checkbox" id="<?php echo esc_attr($value); ?>" type="checkbox" name="activeIntegrations[]" value="<?php echo esc_attr($value); ?>" <?php echo esc_attr($checked);?>/>
                                </label>
                                <img src="<?php echo esc_attr(esc_url($img)); ?>" />
                            </div>
                    <?php
                        }
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <td width="30%">
                    <label for="kotobee-sendemail"><?php esc_html_e('Send Activation Email?',KOTOBEE_INTEGRATION_TEXTDOMAIN); ?></label>
                </td>
                <td width="70%">
                    <input id="kotobee-sendemail" name="kotobee-sendemail" type="checkbox" <?php echo esc_attr($sendEmail);?> />
                </td>
            </tr>
            <tr>
                <td width="30%">
                    <label for="kotobee-remove-access"><?php esc_html_e('Automatically Remove Access of Unsubscribed Users',KOTOBEE_INTEGRATION_TEXTDOMAIN); ?></label>
                </td>
                <td width="70%">
                    <input id="kotobee-remove-access" name="kotobee-remove-access" type="checkbox" <?php echo esc_attr($removeAccess);?> />
                </td>
            </tr>
        </table>
        <?php
        if($activeIntegrations && count($activeIntegrations)) {
            foreach($activeIntegrations as $integration) {
                $path = KOTOBEE_INTEGRATION_PLUGIN_BASE_DIR_PATH.'/admin/settings/'.$integration.".php";
                if(file_exists($path))
                    include $path;
            }
        }
        ?>
        <a id="kotobee-advanced-show" style="<?php echo esc_attr(!$apiDomain?"display:block":"display:none"); ?>" href="javascript:void();" onclick="showAdvanced()"><?php esc_html_e("Show advanced settings", KOTOBEE_INTEGRATION_TEXTDOMAIN); ?></a>

        <div id="kotobee-advanced-settings" style="<?php echo esc_attr($apiDomain?"display:block":"display:none"); ?>">
            <table class="form-table">
                <tr>
                    <td width="30%">
                        <label for="kotobee-domain"><?php esc_html_e('API Domain',KOTOBEE_INTEGRATION_TEXTDOMAIN); ?></label>
                    </td>
                    <td width="70%">
                        <input id="kotobee-domain" name="kotobee-domain" type="text" placeholder="Eg. https://www.kotobee.com/" value="<?php echo esc_url($apiDomain);?>"/>
                    </td>
                </tr>
            </table>
            <a href="javascript:void();" onclick="hideAdvanced()"><?php esc_html_e("Hide advanced settings", KOTOBEE_INTEGRATION_TEXTDOMAIN); ?></a>
        </div>
        <input type="submit" class="button button-primary" id="kotobee-submit" name="kotobee-submit" value="<?php esc_attr_e('Save Settings',KOTOBEE_INTEGRATION_TEXTDOMAIN)?>" />
    </form>

</div>
<script>
    function showAdvanced() {
        jQuery("#kotobee-advanced-settings").show();
        jQuery("#kotobee-advanced-show").hide();
    }
    function hideAdvanced() {
        jQuery("#kotobee-advanced-settings").hide();
        jQuery("#kotobee-advanced-show").show();
    }
</script>
<!-- /wrap -->
