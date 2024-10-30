<?php
$memberfulIntegration = KotobeeMemberfulIntegration::getInstance();
?>
<h2><?php esc_html_e("Memberful Settings", KOTOBEE_INTEGRATION_TEXTDOMAIN) ?></h2>
<table class="form-table">
    <tr>
        <td width="30%">
            <?php esc_html_e("Webhook Endpoint", KOTOBEE_INTEGRATION_TEXTDOMAIN); ?>
        </td>
        <td width="70%">
            <code><?php echo esc_html(esc_url($memberfulIntegration->getWebhookEndpoint())); ?></code>
        </td>
    </tr>
</table>
