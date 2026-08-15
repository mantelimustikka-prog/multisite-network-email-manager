<?php

defined('ABSPATH') || exit;
?>
<div id="mnem-error-details-modal" role="dialog" aria-modal="true" aria-labelledby="mnem-modal-title">
    <div class="mnem-modal-inner">
        <h2 id="mnem-modal-title"><?php esc_html_e('Error Details', 'multisite-network-email-manager'); ?></h2>
        <button type="button" id="mnem-modal-close" class="button" style="float:right;margin-top:-36px;"><?php esc_html_e('Close', 'multisite-network-email-manager'); ?></button>
        <div id="mnem-error-details-body">
            <!-- Populated via AJAX -->
        </div>
    </div>
</div>
