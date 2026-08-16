<?php

defined('ABSPATH') || exit;
?>

<div class="wrap mnem-dashboard">
    <h1><?php esc_html_e('Add Subscribers from Network Users', 'multisite-network-email-manager'); ?></h1>

    <div class="mnem-grid" style="max-width: 1200px;">
        <!-- Step 1: Select Users -->
        <div class="mnem-panel mnem-panel-wide">
            <h2><?php esc_html_e('Step 1: Select Network Users', 'multisite-network-email-manager'); ?></h2>

            <div style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <!-- Filter by Site -->
                    <div>
                        <label for="mnem_filter_site" style="font-weight: bold;">
                            <?php esc_html_e('Filter by Site:', 'multisite-network-email-manager'); ?>
                        </label>
                        <select id="mnem_filter_site" class="mnem-user-filter" style="width: 100%; padding: 8px; margin-top: 5px;">
                            <option value=""><?php esc_html_e('All Sites', 'multisite-network-email-manager'); ?></option>
                            <?php foreach ($all_sites as $site) : ?>
                                <option value="<?php echo esc_attr((string) $site['id']); ?>">
                                    <?php echo esc_html($site['name']); ?> (<?php echo esc_html((string) $site['count']); ?> users)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filter by Role -->
                    <div>
                        <label for="mnem_filter_role" style="font-weight: bold;">
                            <?php esc_html_e('Filter by Role:', 'multisite-network-email-manager'); ?>
                        </label>
                        <select id="mnem_filter_role" class="mnem-user-filter" style="width: 100%; padding: 8px; margin-top: 5px;">
                            <option value=""><?php esc_html_e('All Roles', 'multisite-network-email-manager'); ?></option>
                            <?php foreach ($all_roles as $role_key => $role_name) : ?>
                                <option value="<?php echo esc_attr($role_key); ?>">
                                    <?php echo esc_html($role_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Search Users -->
                    <div style="grid-column: 1 / -1;">
                        <label for="mnem_search_users" style="font-weight: bold;">
                            <?php esc_html_e('Search by Username or Email:', 'multisite-network-email-manager'); ?>
                        </label>
                        <input type="text" id="mnem_search_users" class="mnem-user-filter regular-text" placeholder="Type username or email..." style="margin-top: 5px;">
                    </div>
                </div>

                <!-- User Selection Table -->
                <table class="widefat striped" id="mnem_users_table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="mnem_select_all_users" />
                            </th>
                            <th><?php esc_html_e('Username', 'multisite-network-email-manager'); ?></th>
                            <th><?php esc_html_e('Email', 'multisite-network-email-manager'); ?></th>
                            <th><?php esc_html_e('Site', 'multisite-network-email-manager'); ?></th>
                            <th><?php esc_html_e('Role', 'multisite-network-email-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="mnem_users_tbody">
                        <?php foreach ($all_network_users as $user) : ?>
                            <tr class="mnem-user-row"
                                data-user-id="<?php echo esc_attr((string) $user['user_id']); ?>"
                                data-site-id="<?php echo esc_attr((string) $user['site_id']); ?>"
                                data-role="<?php echo esc_attr($user['role']); ?>"
                                data-email="<?php echo esc_attr($user['email']); ?>"
                                data-login="<?php echo esc_attr($user['login']); ?>">
                                <td>
                                    <input type="checkbox" class="mnem-user-checkbox" value="<?php echo esc_attr((string) $user['user_id']); ?>" />
                                </td>
                                <td><?php echo esc_html($user['login']); ?></td>
                                <td><?php echo esc_html($user['email']); ?></td>
                                <td><?php echo esc_html($user['site_name']); ?></td>
                                <td><?php echo esc_html(ucfirst($user['role'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 15px; padding: 10px; background: white; border-radius: 4px;">
                    <strong><?php esc_html_e('Selected Users:', 'multisite-network-email-manager'); ?> <span id="mnem_selected_count">0</span></strong>
                </div>
            </div>
        </div>

        <!-- Step 2: Exclude Email Patterns -->
        <div class="mnem-panel mnem-panel-wide">
            <h2><?php esc_html_e('Step 2: Exclude Email Patterns (Optional)', 'multisite-network-email-manager'); ?></h2>

            <div style="background: #f9f9f9; padding: 15px; border-radius: 4px;">
                <div style="margin-bottom: 20px;">
                    <label style="font-weight: bold;">
                        <?php esc_html_e('Exclude Emails by Domain:', 'multisite-network-email-manager'); ?>
                    </label>
                    <p style="margin: 5px 0; color: #666; font-size: 12px;">
                        <?php esc_html_e('Enter one domain per line (e.g., @test.com, @staging.com)', 'multisite-network-email-manager'); ?>
                    </p>
                    <textarea id="mnem_exclude_domains" style="width: 100%; height: 100px;" placeholder="@test.com&#10;@staging.com&#10;@example.test"></textarea>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="font-weight: bold;">
                        <?php esc_html_e('Exclude Emails by Pattern (Regex):', 'multisite-network-email-manager'); ?>
                    </label>
                    <p style="margin: 5px 0; color: #666; font-size: 12px;">
                        <?php esc_html_e('Regular expression pattern (e.g., /.*\\+test@.*/)', 'multisite-network-email-manager'); ?>
                    </p>
                    <input type="text" id="mnem_exclude_regex" class="regular-text" placeholder="/.*\+test@.*/">
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="font-weight: bold;">
                        <?php esc_html_e('Exclude Specific Email Addresses:', 'multisite-network-email-manager'); ?>
                    </label>
                    <p style="margin: 5px 0; color: #666; font-size: 12px;">
                        <?php esc_html_e('Enter one email per line or upload CSV file', 'multisite-network-email-manager'); ?>
                    </p>
                    <textarea id="mnem_exclude_emails" style="width: 100%; height: 100px;" placeholder="test@example.com&#10;admin@test.com"></textarea>

                    <div style="margin-top: 10px;">
                        <label><?php esc_html_e('Or upload file:', 'multisite-network-email-manager'); ?></label><br>
                        <input type="file" id="mnem_exclude_file" accept=".csv,.txt" style="margin-top: 5px;">
                    </div>
                </div>

                <button type="button" id="mnem_apply_filters_btn" class="button button-primary" onclick="mnemApplyEmailFilters()">
                    <?php esc_html_e('Apply Filters & Preview', 'multisite-network-email-manager'); ?>
                </button>
            </div>
        </div>

        <!-- Step 3: Preview & Options -->
        <div class="mnem-panel mnem-panel-wide">
            <h2><?php esc_html_e('Step 3: Preview & Options', 'multisite-network-email-manager'); ?></h2>

            <div style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
                    <div style="background: white; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
                        <small><?php esc_html_e('Selected Users', 'multisite-network-email-manager'); ?></small>
                        <p style="font-size: 20px; margin: 5px 0 0 0; font-weight: bold;" id="mnem_stat_selected">0</p>
                    </div>
                    <div style="background: white; padding: 10px; border-radius: 4px; border-left: 4px solid #dc3545;">
                        <small><?php esc_html_e('Will Exclude', 'multisite-network-email-manager'); ?></small>
                        <p style="font-size: 20px; margin: 5px 0 0 0; font-weight: bold;" id="mnem_stat_excluded">0</p>
                    </div>
                    <div style="background: white; padding: 10px; border-radius: 4px; border-left: 4px solid #28a745;">
                        <small><?php esc_html_e('Will Add', 'multisite-network-email-manager'); ?></small>
                        <p style="font-size: 20px; margin: 5px 0 0 0; font-weight: bold;" id="mnem_stat_final">0</p>
                    </div>
                    <div style="background: white; padding: 10px; border-radius: 4px; border-left: 4px solid #ffc107;">
                        <small><?php esc_html_e('Already Subscribed', 'multisite-network-email-manager'); ?></small>
                        <p style="font-size: 20px; margin: 5px 0 0 0; font-weight: bold;" id="mnem_stat_duplicate">0</p>
                    </div>
                </div>

                <div style="margin-bottom: 20px; padding: 15px; background: white; border-radius: 4px;">
                    <h3><?php esc_html_e('Options:', 'multisite-network-email-manager'); ?></h3>
                    <label style="display: block; margin-bottom: 10px;">
                        <input type="checkbox" id="mnem_skip_existing" checked>
                        <?php esc_html_e('Skip users already subscribed to this list', 'multisite-network-email-manager'); ?>
                    </label>
                    <label style="display: block; margin-bottom: 10px;">
                        <input type="checkbox" id="mnem_skip_unsubscribed" checked>
                        <?php esc_html_e("Don't reactivate previously unsubscribed users", 'multisite-network-email-manager'); ?>
                    </label>
                </div>

                <div id="mnem_preview_users" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 10px; background: white; display: none;">
                    <h4><?php esc_html_e('Users to be added:', 'multisite-network-email-manager'); ?></h4>
                    <table class="widefat striped" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Username', 'multisite-network-email-manager'); ?></th>
                                <th><?php esc_html_e('Email', 'multisite-network-email-manager'); ?></th>
                                <th><?php esc_html_e('Site', 'multisite-network-email-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="mnem_preview_tbody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Step 4: Confirmation & Submit -->
        <div class="mnem-panel mnem-panel-wide">
            <div style="background: #f0f8ff; padding: 20px; border-radius: 4px; border-left: 4px solid #0073aa;">
                <form method="post" id="mnem_bulk_add_form">
                    <?php wp_nonce_field('mnem_subscriber_lists'); ?>
                    <input type="hidden" name="mnem_action" value="subscriber_bulk_add_from_users" />
                    <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
                    <input type="hidden" name="selected_user_ids" id="mnem_selected_user_ids" value="" />
                    <input type="hidden" name="exclude_emails" id="mnem_exclude_emails_value" value="" />

                    <p style="margin-bottom: 15px;">
                        <?php esc_html_e('Review the preview above, then click "Add to List" to proceed.', 'multisite-network-email-manager'); ?>
                    </p>

                    <?php submit_button(
                        __('Add Users to List', 'multisite-network-email-manager'),
                        'primary',
                        'submit',
                        true,
                        array('id' => 'mnem_submit_bulk_add', 'disabled' => true)
                    ); ?>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .mnem-user-row {
        cursor: pointer;
    }
    .mnem-user-row:hover {
        background: #f5f5f5;
    }
</style>

<script>
var mnemBulkAddNonce = <?php echo wp_json_encode(wp_create_nonce('mnem_bulk_add_users')); ?>;
var mnemBulkListId = <?php echo (int) $active_list['id']; ?>;

// Track selected users
var selectedUsers = new Set();
var allUsers = new Map();

// Initialize users map
<?php foreach ($all_network_users as $user) : ?>
    allUsers.set(<?php echo (int) $user['user_id']; ?>, {
        login: <?php echo wp_json_encode($user['login']); ?>,
        email: <?php echo wp_json_encode($user['email']); ?>,
        site_name: <?php echo wp_json_encode($user['site_name']); ?>,
        site_id: <?php echo (int) $user['site_id']; ?>
    });
<?php endforeach; ?>

// Update selected count
function updateSelectedCount() {
    document.getElementById('mnem_selected_count').textContent = selectedUsers.size;
    document.getElementById('mnem_submit_bulk_add').disabled = selectedUsers.size === 0;
}

// Select/Deselect all
document.getElementById('mnem_select_all_users').addEventListener('change', function() {
    var checked = this.checked;
    document.querySelectorAll('.mnem-user-row').forEach(function(row) {
        if (row.style.display === 'none') {
            return;
        }
        var checkbox = row.querySelector('.mnem-user-checkbox');
        checkbox.checked = checked;
        var userId = parseInt(checkbox.value, 10);
        if (checked) {
            selectedUsers.add(userId);
        } else {
            selectedUsers.delete(userId);
        }
    });
    updateSelectedCount();
});

// Individual checkbox selection
document.querySelectorAll('.mnem-user-checkbox').forEach(function(checkbox) {
    checkbox.addEventListener('change', function() {
        var userId = parseInt(this.value, 10);
        if (this.checked) {
            selectedUsers.add(userId);
        } else {
            selectedUsers.delete(userId);
        }
        updateSelectedCount();
    });
});

// Filter users
document.querySelectorAll('.mnem-user-filter').forEach(function(filter) {
    filter.addEventListener('change', filterUsers);
});

document.getElementById('mnem_search_users').addEventListener('keyup', filterUsers);

function filterUsers() {
    var siteFilter = document.getElementById('mnem_filter_site').value;
    var roleFilter = document.getElementById('mnem_filter_role').value;
    var searchText = document.getElementById('mnem_search_users').value.toLowerCase();

    document.querySelectorAll('.mnem-user-row').forEach(function(row) {
        var rowSiteId = row.dataset.siteId;
        var rowRole = row.dataset.role;
        var rowEmail = row.dataset.email.toLowerCase();
        var rowLogin = row.dataset.login.toLowerCase();

        var matchesSite = !siteFilter || rowSiteId === siteFilter;
        var matchesRole = !roleFilter || rowRole === roleFilter;
        var matchesSearch = !searchText || rowEmail.indexOf(searchText) !== -1 || rowLogin.indexOf(searchText) !== -1;

        row.style.display = (matchesSite && matchesRole && matchesSearch) ? '' : 'none';
    });
}

// Apply email filters and show preview
async function mnemApplyEmailFilters() {
    var selectedIds = Array.from(selectedUsers);
    var excludeDomains = document.getElementById('mnem_exclude_domains').value.split('\n').filter(function(d) { return d.trim(); });
    var excludeRegex = document.getElementById('mnem_exclude_regex').value.trim();
    var excludeEmails = document.getElementById('mnem_exclude_emails').value.split('\n').map(function(e) { return e.trim().toLowerCase(); }).filter(function(e) { return e; });

    // Handle file upload
    var fileInput = document.getElementById('mnem_exclude_file');
    if (fileInput.files.length > 0) {
        var text = await fileInput.files[0].text();
        var fileEmails = text.split('\n').map(function(e) { return e.trim().toLowerCase(); }).filter(function(e) { return e; });
        excludeEmails = excludeEmails.concat(fileEmails);
    }

    var excludedEmailsSet = new Set(excludeEmails);
    var excluded = 0;
    var finalUsers = [];
    var regexError = false;

    selectedIds.forEach(function(userId) {
        var user = allUsers.get(userId);
        if (!user) {
            return;
        }

        var shouldExclude = false;

        // Check domain exclusion
        for (var i = 0; i < excludeDomains.length; i++) {
            var cleanDomain = excludeDomains[i].trim().toLowerCase().replace(/^@/, '');
            if (user.email.toLowerCase().endsWith('@' + cleanDomain)) {
                shouldExclude = true;
                break;
            }
        }

        // Check regex exclusion
        if (!shouldExclude && excludeRegex) {
            try {
                var regex = new RegExp(excludeRegex);
                if (regex.test(user.email)) {
                    shouldExclude = true;
                }
            } catch (e) {
                if (!regexError) {
                    regexError = true;
                    alert('Invalid regex pattern: ' + e.message);
                }
                return;
            }
        }

        // Check specific email exclusion
        if (!shouldExclude && excludedEmailsSet.has(user.email.toLowerCase())) {
            shouldExclude = true;
        }

        if (shouldExclude) {
            excluded++;
        } else {
            finalUsers.push(user);
        }
    });

    if (regexError) {
        return;
    }

    // Update statistics
    document.getElementById('mnem_stat_selected').textContent = selectedIds.length;
    document.getElementById('mnem_stat_excluded').textContent = excluded;
    document.getElementById('mnem_stat_final').textContent = finalUsers.length;

    // Show preview
    var previewTbody = document.getElementById('mnem_preview_tbody');
    previewTbody.innerHTML = '';
    finalUsers.forEach(function(user) {
        var row = document.createElement('tr');
        var tdLogin = document.createElement('td');
        tdLogin.textContent = user.login;
        var tdEmail = document.createElement('td');
        tdEmail.textContent = user.email;
        var tdSite = document.createElement('td');
        tdSite.textContent = user.site_name;
        row.appendChild(tdLogin);
        row.appendChild(tdEmail);
        row.appendChild(tdSite);
        previewTbody.appendChild(row);
    });

    document.getElementById('mnem_preview_users').style.display = finalUsers.length > 0 ? 'block' : 'none';

    // Store values in form
    document.getElementById('mnem_selected_user_ids').value = selectedIds.join(',');
    document.getElementById('mnem_exclude_emails_value').value = Array.from(excludedEmailsSet).join(',');

    // Enable submit if there are users to add
    document.getElementById('mnem_submit_bulk_add').disabled = finalUsers.length === 0;
}

// Form submission with progress
document.getElementById('mnem_bulk_add_form').addEventListener('submit', async function(e) {
    e.preventDefault();

    var selectedIds = document.getElementById('mnem_selected_user_ids').value.split(',').filter(function(id) { return id; });

    if (selectedIds.length === 0) {
        alert(<?php echo wp_json_encode(__('No users selected', 'multisite-network-email-manager')); ?>);
        return;
    }

    var button = document.getElementById('mnem_submit_bulk_add');
    var originalText = button.textContent;
    button.disabled = true;
    button.textContent = <?php echo wp_json_encode(__('Processing...', 'multisite-network-email-manager')); ?>;

    try {
        var response = await fetch(ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'mnem_bulk_add_subscribers',
                nonce: mnemBulkAddNonce,
                list_id: mnemBulkListId,
                user_ids: selectedIds.join(','),
                skip_existing: document.getElementById('mnem_skip_existing').checked ? '1' : '0',
                skip_unsubscribed: document.getElementById('mnem_skip_unsubscribed').checked ? '1' : '0',
            })
        });

        var data = await response.json();

        if (data.success) {
            alert(data.data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.data.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    } finally {
        button.disabled = false;
        button.textContent = originalText;
    }
});
</script>
