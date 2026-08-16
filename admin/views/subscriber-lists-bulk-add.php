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

                <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: end; margin-bottom: 15px;">
                    <div>
                        <label for="mnem_batch_size" style="font-weight: bold; display: block; margin-bottom: 5px;">
                            <?php esc_html_e('Batch Size:', 'multisite-network-email-manager'); ?>
                        </label>
                        <select id="mnem_batch_size" style="min-width: 140px;">
                            <?php foreach ($batch_sizes as $batch_size) : ?>
                                <option value="<?php echo esc_attr((string) $batch_size); ?>"<?php selected($batch_size, 1000); ?>>
                                    <?php echo esc_html((string) $batch_size); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="button" id="mnem_load_more_btn" class="button button-secondary">
                        <?php esc_html_e('Load More...', 'multisite-network-email-manager'); ?>
                    </button>
                    <button type="button" id="mnem_load_all_btn" class="button button-primary">
                        <?php esc_html_e('Load All Users', 'multisite-network-email-manager'); ?>
                    </button>
                </div>

                <div style="margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; gap: 10px; margin-bottom: 8px; font-weight: bold;">
                        <span><?php esc_html_e('Loading Progress', 'multisite-network-email-manager'); ?></span>
                        <span id="mnem_load_stats"><?php esc_html_e('Loaded 0 / Total 0 users', 'multisite-network-email-manager'); ?></span>
                    </div>
                    <div style="height: 18px; background: #e5e5e5; border-radius: 999px; overflow: hidden;">
                        <div id="mnem_load_progress_bar" style="height: 100%; width: 0%; background: #0073aa; transition: width 0.2s ease;"></div>
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
                        <tr id="mnem_users_empty_state">
                            <td colspan="5"><?php esc_html_e('No users loaded yet. Choose a batch size and start loading users.', 'multisite-network-email-manager'); ?></td>
                        </tr>
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
var mnemBulkAddStrings = {
    initialEmpty: <?php echo wp_json_encode(__('No users loaded yet. Choose a batch size and start loading users.', 'multisite-network-email-manager')); ?>,
    noUsersFound: <?php echo wp_json_encode(__('No network users were returned for this request.', 'multisite-network-email-manager')); ?>,
    loadingUsers: <?php echo wp_json_encode(__('Loading users...', 'multisite-network-email-manager')); ?>,
    loadMore: <?php echo wp_json_encode(__('Load More...', 'multisite-network-email-manager')); ?>,
    loadAll: <?php echo wp_json_encode(__('Load All Users', 'multisite-network-email-manager')); ?>,
    loadingMore: <?php echo wp_json_encode(__('Loading...', 'multisite-network-email-manager')); ?>,
    loadingAll: <?php echo wp_json_encode(__('Loading All Users...', 'multisite-network-email-manager')); ?>,
    loadedLabel: <?php echo wp_json_encode(__('Loaded', 'multisite-network-email-manager')); ?>,
    totalLabel: <?php echo wp_json_encode(__('Total', 'multisite-network-email-manager')); ?>,
    usersLabel: <?php echo wp_json_encode(__('users', 'multisite-network-email-manager')); ?>,
    invalidRegex: <?php echo wp_json_encode(__('Invalid regex pattern: ', 'multisite-network-email-manager')); ?>,
    noUsersSelected: <?php echo wp_json_encode(__('No users selected', 'multisite-network-email-manager')); ?>,
    processing: <?php echo wp_json_encode(__('Processing...', 'multisite-network-email-manager')); ?>,
    errorPrefix: <?php echo wp_json_encode(__('Error: ', 'multisite-network-email-manager')); ?>
};

var selectedUsers = new Set();
var allUsers = new Map();
var loadedUsersCount = 0;
var totalUsersCount = 0;
var hasMoreUsers = true;
var currentLoadingMode = '';

function updateSelectedCount() {
    document.getElementById('mnem_selected_count').textContent = selectedUsers.size;
}

function invalidatePreview() {
    document.getElementById('mnem_selected_user_ids').value = '';
    document.getElementById('mnem_submit_bulk_add').disabled = true;
    document.getElementById('mnem_preview_users').style.display = 'none';
}

function renderEmptyState(message) {
    var tbody = document.getElementById('mnem_users_tbody');
    tbody.innerHTML = '';

    var row = document.createElement('tr');
    row.id = 'mnem_users_empty_state';

    var cell = document.createElement('td');
    cell.colSpan = 5;
    cell.textContent = message;

    row.appendChild(cell);
    tbody.appendChild(row);
}

function updateLoadProgress() {
    var percentage = totalUsersCount > 0 ? Math.min(100, Math.round((loadedUsersCount / totalUsersCount) * 100)) : 0;
    document.getElementById('mnem_load_progress_bar').style.width = percentage + '%';
    document.getElementById('mnem_load_stats').textContent = mnemBulkAddStrings.loadedLabel + ' ' + loadedUsersCount + ' / ' + mnemBulkAddStrings.totalLabel + ' ' + totalUsersCount + ' ' + mnemBulkAddStrings.usersLabel;
}

function updateLoadButtons() {
    var loadMoreButton = document.getElementById('mnem_load_more_btn');
    var loadAllButton = document.getElementById('mnem_load_all_btn');
    var finishedLoading = !hasMoreUsers && (loadedUsersCount > 0 || totalUsersCount === 0);
    var isLoading = currentLoadingMode !== '';

    loadMoreButton.disabled = isLoading || finishedLoading;
    loadAllButton.disabled = isLoading || finishedLoading;
    loadMoreButton.textContent = currentLoadingMode === 'more' ? mnemBulkAddStrings.loadingMore : mnemBulkAddStrings.loadMore;
    loadAllButton.textContent = currentLoadingMode === 'all' ? mnemBulkAddStrings.loadingAll : mnemBulkAddStrings.loadAll;
}

function syncSelectAllState() {
    var selectAll = document.getElementById('mnem_select_all_users');
    var visibleCheckboxes = [];

    document.querySelectorAll('.mnem-user-row').forEach(function(row) {
        if (row.style.display !== 'none') {
            visibleCheckboxes.push(row.querySelector('.mnem-user-checkbox'));
        }
    });

    if (visibleCheckboxes.length === 0) {
        selectAll.checked = false;
        selectAll.indeterminate = false;
        return;
    }

    var checkedCount = visibleCheckboxes.filter(function(checkbox) {
        return checkbox.checked;
    }).length;

    selectAll.checked = checkedCount > 0 && checkedCount === visibleCheckboxes.length;
    selectAll.indeterminate = checkedCount > 0 && checkedCount < visibleCheckboxes.length;
}

function attachCheckboxListener(checkbox) {
    checkbox.addEventListener('change', function() {
        var userId = parseInt(this.value, 10);
        if (this.checked) {
            selectedUsers.add(userId);
        } else {
            selectedUsers.delete(userId);
        }

        updateSelectedCount();
        invalidatePreview();
        syncSelectAllState();
    });
}

function appendUsers(users) {
    var tbody = document.getElementById('mnem_users_tbody');
    var emptyState = document.getElementById('mnem_users_empty_state');

    if (emptyState) {
        emptyState.remove();
    }

    users.forEach(function(user) {
        allUsers.set(user.user_id, user);

        var row = document.createElement('tr');
        row.className = 'mnem-user-row';
        row.dataset.userId = user.user_id;
        row.dataset.siteId = user.site_id;
        row.dataset.role = user.role;
        row.dataset.email = user.email;
        row.dataset.login = user.login;

        var checkboxCell = document.createElement('td');
        var checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'mnem-user-checkbox';
        checkbox.value = user.user_id;
        if (selectedUsers.has(user.user_id)) {
            checkbox.checked = true;
        }
        attachCheckboxListener(checkbox);
        checkboxCell.appendChild(checkbox);

        var loginCell = document.createElement('td');
        loginCell.textContent = user.login;

        var emailCell = document.createElement('td');
        emailCell.textContent = user.email;

        var siteCell = document.createElement('td');
        siteCell.textContent = user.site_name;

        var roleCell = document.createElement('td');
        roleCell.textContent = user.role.charAt(0).toUpperCase() + user.role.slice(1);

        row.appendChild(checkboxCell);
        row.appendChild(loginCell);
        row.appendChild(emailCell);
        row.appendChild(siteCell);
        row.appendChild(roleCell);
        tbody.appendChild(row);
    });

    filterUsers();

    if (document.getElementById('mnem_select_all_users').checked) {
        applySelectAllToVisibleRows(true);
    } else {
        syncSelectAllState();
    }
}

function applySelectAllToVisibleRows(checked) {
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
    invalidatePreview();
    syncSelectAllState();
}

async function requestUsersBatch() {
    var response = await fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'mnem_load_batch_users',
            nonce: mnemBulkAddNonce,
            batch_size: document.getElementById('mnem_batch_size').value,
            offset: loadedUsersCount
        })
    });

    var data = await response.json();
    if (!data.success) {
        throw new Error(data.data && data.data.message ? data.data.message : 'Failed to load users');
    }

    return data.data;
}

function handleBatchResponse(data) {
    totalUsersCount = typeof data.total === 'number' ? data.total : totalUsersCount;
    loadedUsersCount = typeof data.next_offset === 'number' ? data.next_offset : loadedUsersCount;
    hasMoreUsers = !!data.has_more;

    if (Array.isArray(data.users) && data.users.length > 0) {
        appendUsers(data.users);
    } else if (loadedUsersCount === 0) {
        renderEmptyState(mnemBulkAddStrings.noUsersFound);
    }

    updateLoadProgress();
    updateLoadButtons();
    invalidatePreview();
}

async function loadMoreUsers() {
    if (currentLoadingMode !== '' || (!hasMoreUsers && (loadedUsersCount > 0 || totalUsersCount === 0))) {
        return;
    }

    currentLoadingMode = 'more';
    if (loadedUsersCount === 0) {
        renderEmptyState(mnemBulkAddStrings.loadingUsers);
    }
    updateLoadButtons();

    try {
        handleBatchResponse(await requestUsersBatch());
    } catch (error) {
        alert(mnemBulkAddStrings.errorPrefix + error.message);
        if (loadedUsersCount === 0) {
            renderEmptyState(mnemBulkAddStrings.initialEmpty);
        }
    } finally {
        currentLoadingMode = '';
        updateLoadButtons();
    }
}

async function loadAllUsers() {
    if (currentLoadingMode !== '' || (!hasMoreUsers && (loadedUsersCount > 0 || totalUsersCount === 0))) {
        return;
    }

    currentLoadingMode = 'all';
    if (loadedUsersCount === 0) {
        renderEmptyState(mnemBulkAddStrings.loadingUsers);
    }
    updateLoadButtons();

    try {
        do {
            handleBatchResponse(await requestUsersBatch());
        } while (hasMoreUsers);
    } catch (error) {
        alert(mnemBulkAddStrings.errorPrefix + error.message);
        if (loadedUsersCount === 0) {
            renderEmptyState(mnemBulkAddStrings.initialEmpty);
        }
    } finally {
        currentLoadingMode = '';
        updateLoadButtons();
    }
}

document.getElementById('mnem_select_all_users').addEventListener('change', function() {
    applySelectAllToVisibleRows(this.checked);
});

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

    syncSelectAllState();
}

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
    var finalUserIds = [];
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
                    alert(mnemBulkAddStrings.invalidRegex + e.message);
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
            finalUserIds.push(userId);
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
    document.getElementById('mnem_selected_user_ids').value = finalUserIds.join(',');
    document.getElementById('mnem_exclude_emails_value').value = Array.from(excludedEmailsSet).join(',');

    // Enable submit if there are users to add
    document.getElementById('mnem_submit_bulk_add').disabled = finalUsers.length === 0;
}

// Form submission with progress
document.getElementById('mnem_bulk_add_form').addEventListener('submit', async function(e) {
    e.preventDefault();

    var selectedIds = document.getElementById('mnem_selected_user_ids').value.split(',').filter(function(id) { return id; });

    if (selectedIds.length === 0) {
        alert(mnemBulkAddStrings.noUsersSelected);
        return;
    }

    var button = document.getElementById('mnem_submit_bulk_add');
    var originalText = button.textContent;
    button.disabled = true;
    button.textContent = mnemBulkAddStrings.processing;

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
            alert(mnemBulkAddStrings.errorPrefix + data.data.message);
        }
    } catch (error) {
        alert(mnemBulkAddStrings.errorPrefix + error.message);
    } finally {
        button.disabled = false;
        button.textContent = originalText;
    }
});

document.getElementById('mnem_load_more_btn').addEventListener('click', loadMoreUsers);
document.getElementById('mnem_load_all_btn').addEventListener('click', loadAllUsers);
updateLoadProgress();
updateLoadButtons();
invalidatePreview();
</script>
