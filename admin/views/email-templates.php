<?php

defined('ABSPATH') || exit;

$variables = \MNEM\EmailTemplates::get_available_variables();
?>
<div class="wrap mnem-dashboard">
    <h1>Email Templates</h1>
    <?php if ($notice_message !== '') : ?>
        <div class="<?php echo esc_attr($notice_class); ?>"><p><?php echo esc_html($notice_message); ?></p></div>
    <?php endif; ?>

    <p>Available variables:
        <?php foreach ($variables as $variable => $label) : ?>
            <code><?php echo esc_html($variable); ?></code>
        <?php endforeach; ?>
    </p>

    <div class="mnem-grid">
        <div class="mnem-panel mnem-panel-wide">
            <h2>Create / Update Template</h2>
            <form method="post">
                <?php wp_nonce_field('mnem_email_templates'); ?>
                <input type="hidden" name="mnem_action" value="template_save" />
                <table class="form-table">
                    <tr>
                        <th><label for="mnem_template_id">Template ID</label></th>
                        <td><input id="mnem_template_id" name="template_id" type="text" class="regular-text" placeholder="custom-template-id" /></td>
                    </tr>
                    <tr>
                        <th><label for="mnem_template_name">Template Name</label></th>
                        <td><input id="mnem_template_name" name="template_name" type="text" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th><label for="mnem_template_subject">Subject</label></th>
                        <td><input id="mnem_template_subject" name="template_subject" type="text" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th><label for="mnem_template_body">Body</label></th>
                        <td>
                            <?php if (function_exists('wp_editor')) : ?>
                                <?php wp_editor('', 'mnem_template_body_editor', array('textarea_name' => 'template_body', 'textarea_rows' => 10, 'media_buttons' => true)); ?>
                            <?php else : ?>
                                <textarea id="mnem_template_body" name="template_body" rows="8" class="large-text"></textarea>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Template'); ?>
            </form>
        </div>

        <div class="mnem-panel mnem-panel-wide">
            <h2>Templates</h2>
            <table class="widefat striped">
                <thead><tr><th>ID</th><th>Name</th><th>Subject</th><th>Type</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ((array) $templates as $template) : ?>
                    <tr>
                        <td><code><?php echo esc_html((string) $template['id']); ?></code></td>
                        <td><?php echo esc_html((string) $template['name']); ?></td>
                        <td><?php echo esc_html((string) $template['subject']); ?></td>
                        <td><?php echo !empty($template['is_custom']) ? 'Custom' : 'Built-in'; ?></td>
                        <td>
                            <?php if (!empty($template['is_custom'])) : ?>
                                <form method="post" class="mnem-inline-form" onsubmit="return confirm('Delete this template?');">
                                    <?php wp_nonce_field('mnem_email_templates'); ?>
                                    <input type="hidden" name="mnem_action" value="template_delete" />
                                    <input type="hidden" name="template_id" value="<?php echo esc_attr((string) $template['id']); ?>" />
                                    <?php submit_button('Delete', 'delete', 'submit', false); ?>
                                </form>
                            <?php else : ?>
                                <form method="post" class="mnem-inline-form" onsubmit="return confirm('Reset template to default?');">
                                    <?php wp_nonce_field('mnem_email_templates'); ?>
                                    <input type="hidden" name="mnem_action" value="template_reset" />
                                    <input type="hidden" name="template_id" value="<?php echo esc_attr((string) $template['id']); ?>" />
                                    <?php submit_button('Reset to Default', 'secondary', 'submit', false); ?>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
