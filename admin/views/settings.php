<?php
/**
 * Settings page — tabbed container.
 *
 * Variables available: $active_tab, $settings, $cron_status, $notice, $notice_message, $notice_class
 */

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1><?php esc_html_e('Email Manager Settings', 'multisite-network-email-manager'); ?></h1>

    <?php if (!empty($notice_message)) : ?>
        <div class="<?php echo esc_attr($notice_class); ?>"><p><?php echo esc_html($notice_message); ?></p></div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper mnem-settings-tabs" style="border-bottom: 1px solid #ccc; margin: 20px 0 0;">
        <a class="nav-tab<?php echo $active_tab === 'general' ? ' nav-tab-active' : ''; ?>"
           href="<?php echo esc_url(network_admin_url('admin.php?page=mnem-settings&tab=general')); ?>">
            <?php esc_html_e('General Settings', 'multisite-network-email-manager'); ?>
        </a>
        <a class="nav-tab<?php echo $active_tab === 'smtp' ? ' nav-tab-active' : ''; ?>"
           href="<?php echo esc_url(network_admin_url('admin.php?page=mnem-settings&tab=smtp')); ?>">
            <?php esc_html_e('SMTP Settings', 'multisite-network-email-manager'); ?>
        </a>
        <a class="nav-tab<?php echo $active_tab === 'sender' ? ' nav-tab-active' : ''; ?>"
           href="<?php echo esc_url(network_admin_url('admin.php?page=mnem-settings&tab=sender')); ?>">
            <?php esc_html_e('Sender Settings', 'multisite-network-email-manager'); ?>
        </a>
        <a class="nav-tab<?php echo $active_tab === 'header-footer' ? ' nav-tab-active' : ''; ?>"
           href="<?php echo esc_url(network_admin_url('admin.php?page=mnem-settings&tab=header-footer')); ?>">
            <?php esc_html_e('Global Header &amp; Footer', 'multisite-network-email-manager'); ?>
        </a>
        <a class="nav-tab<?php echo $active_tab === 'webhook-health' ? ' nav-tab-active' : ''; ?>"
           href="<?php echo esc_url(network_admin_url('admin.php?page=mnem-settings&tab=webhook-health')); ?>">
            <?php esc_html_e('Webhook Health', 'multisite-network-email-manager'); ?>
        </a>
        <a class="nav-tab<?php echo $active_tab === 'sms' ? ' nav-tab-active' : ''; ?>"
           href="<?php echo esc_url(network_admin_url('admin.php?page=mnem-settings&tab=sms')); ?>">
            <?php esc_html_e('SMS Settings', 'multisite-network-email-manager'); ?>
        </a>
    </nav>

    <div class="mnem-tab-content" style="margin-top: 20px;">
        <?php
        $tab_file = MNEM_PLUGIN_DIR . 'admin/views/settings-' . $active_tab . '.php';
        if (file_exists($tab_file)) {
            include $tab_file;
        }
        ?>
    </div>
</div>
