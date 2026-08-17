<?php
/**
 * Settings tab — Global Header & Footer Settings.
 *
 * Variables available: (none required, reads from site options)
 */

defined('ABSPATH') || exit;

$force_global   = (int) get_site_option('mnem_force_global_header_footer', 0) === 1;
$global_header  = (string) get_site_option('mnem_global_header', '');
$global_footer  = (string) get_site_option('mnem_global_footer', '');
?>

<form method="post" action="">
    <?php wp_nonce_field('mnem_header_footer_settings'); ?>
    <input type="hidden" name="mnem_action" value="save_header_footer_settings" />

    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><?php esc_html_e('Enable Global Header &amp; Footer', 'multisite-network-email-manager'); ?></th>
                <td>
                    <label>
                        <input
                            type="checkbox"
                            name="force_global_header_footer"
                            id="mnem-force-global-header-footer"
                            value="1"
                            <?php checked($force_global); ?>
                        />
                        <?php esc_html_e('Force Global Header &amp; Footer to all outbound email', 'multisite-network-email-manager'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('When enabled, the header and footer below will be prepended/appended to every outbound email sent by this plugin.', 'multisite-network-email-manager'); ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>

    <h2><?php esc_html_e('Global Email Header', 'multisite-network-email-manager'); ?></h2>
    <p class="description">
        <?php esc_html_e('This content will be prepended to all outbound emails. Supports HTML, shortcodes, and images.', 'multisite-network-email-manager'); ?>
    </p>
    <div class="mnem-editor-container" style="max-width: 800px; margin-bottom: 20px;">
        <textarea
            name="global_header"
            id="mnem_global_header"
            rows="10"
            class="large-text mnem-ckeditor-source"
            data-mnem-ckeditor="1"
            data-mnem-ckeditor-height="300px"
        ><?php echo esc_textarea($global_header); ?></textarea>
    </div>

    <h2><?php esc_html_e('Global Email Footer', 'multisite-network-email-manager'); ?></h2>
    <p class="description">
        <?php esc_html_e('This content will be appended to all outbound emails. Supports HTML, shortcodes, and images.', 'multisite-network-email-manager'); ?>
    </p>
    <div class="mnem-editor-container" style="max-width: 800px; margin-bottom: 20px;">
        <textarea
            name="global_footer"
            id="mnem_global_footer"
            rows="10"
            class="large-text mnem-ckeditor-source"
            data-mnem-ckeditor="1"
            data-mnem-ckeditor-height="300px"
        ><?php echo esc_textarea($global_footer); ?></textarea>
    </div>

    <?php submit_button(esc_html__('Save Header &amp; Footer Settings', 'multisite-network-email-manager')); ?>
</form>
