/**
 * SMS Logs admin JavaScript.
 *
 * Handles the subscriber management action buttons (Unsubscribe, Delete User,
 * Unsubscribe & Delete) shown for every SMS log row:
 *  - AJAX requests (no page reload)
 *  - Confirmation dialogs
 *  - Inline success/error notifications (auto-hide after 5s)
 *  - Dynamic button state updates after an action completes
 */
(function ($) {
    'use strict';

    var settings = window.mnemSmsLogs || {};
    var i18n = settings.i18n || {};

    var ACTIONS = {
        unsubscribe: {
            ajaxAction: 'mnem_sms_log_unsubscribe',
            confirm: i18n.confirmUnsubscribe || 'Unsubscribe this phone number from the SMS list?'
        },
        delete_user: {
            ajaxAction: 'mnem_sms_log_delete_user',
            confirm: i18n.confirmDeleteUser || 'Delete the WordPress user associated with this phone number?'
        },
        both: {
            ajaxAction: 'mnem_sms_log_unsubscribe_delete',
            confirm: i18n.confirmUnsubscribeDelete || 'Unsubscribe from the SMS list AND delete the WordPress user?'
        }
    };

    function getNotice($wrapper) {
        var $notice = $wrapper.find('.mnem-sms-log-notice');
        if (!$notice.length) {
            $notice = $('<div class="mnem-sms-log-notice" style="display:none;"></div>').appendTo($wrapper);
        }
        return $notice;
    }

    function showNotice($wrapper, message, isError) {
        var $notice = getNotice($wrapper);

        if ($notice.data('mnemTimer')) {
            window.clearTimeout($notice.data('mnemTimer'));
        }

        $notice
            .text(message)
            .css('color', isError ? '#d63638' : '#00a32a')
            .show();

        $notice.data('mnemTimer', window.setTimeout(function () {
            $notice.fadeOut(200);
        }, 5000));
    }

    function disableButton($button, text) {
        $button
            .prop('disabled', true)
            .attr('aria-disabled', 'true')
            .css({ opacity: '0.5', cursor: 'not-allowed' });

        if (text) {
            $button.text(text);
        }
    }

    function applyButtonStates($wrapper, data) {
        var toDisable = (data && data.buttons_to_disable) || [];
        var newText = (data && data.new_button_text) || {};

        $.each(toDisable, function (index, action) {
            var $target = $wrapper.find('.mnem-sms-log-action[data-mnem-action="' + action + '"]');
            if ($target.length) {
                disableButton($target, newText[action] || null);
            }
        });
    }

    $(document).on('click', '.mnem-sms-refresh-status', function (event) {
        event.preventDefault();

        var $button = $(this);
        var $wrapper = $button.closest('.mnem-sms-log-actions');
        var $row = $button.closest('tr');
        var originalText = $button.text();

        $button.prop('disabled', true).text(i18n.refreshing || 'Refreshing…');
        $.post(settings.ajaxUrl, {
            action: 'mnem_sms_log_refresh_status',
            nonce: settings.nonce,
            sms_queue_id: $wrapper.data('queue-id')
        }).done(function (response) {
            if (!response || !response.success) {
                var error = response && response.data && response.data.message
                    ? response.data.message
                    : (i18n.requestFailed || 'The request failed. Please try again.');
                $button.prop('disabled', false).text(originalText);
                showNotice($wrapper, error, true);
                return;
            }

            if (response.data.provider_status) {
                $row.find('.mnem-sms-provider-status').text(response.data.provider_status_label || response.data.provider_status);
                $row.find('.mnem-sms-provider-checked').text(response.data.checked_at || '—');
                $row.find('.mnem-sms-queue-status .mnem-badge')
                    .attr('class', 'mnem-badge mnem-status-' + response.data.status)
                    .text(response.data.status.charAt(0).toUpperCase() + response.data.status.slice(1));
                $button.remove();
            } else {
                $button.prop('disabled', false).text(originalText);
            }
            showNotice($wrapper, response.data.message, false);
        }).fail(function () {
            $button.prop('disabled', false).text(originalText);
            showNotice($wrapper, i18n.requestFailed || 'The request failed. Please try again.', true);
        });
    });

    $(document).on('click', '.mnem-sms-log-action', function (event) {
        event.preventDefault();

        var $button = $(this);
        if ($button.prop('disabled')) {
            return;
        }

        var action = $button.data('mnem-action');
        var config = ACTIONS[action];
        if (!config) {
            return;
        }

        if (!window.confirm(config.confirm)) {
            return;
        }

        var $wrapper = $button.closest('.mnem-sms-log-actions');
        var originalText = $button.text();

        $button.prop('disabled', true).text(i18n.working || 'Working…');

        $.post(settings.ajaxUrl, {
            action: config.ajaxAction,
            nonce: settings.nonce,
            sms_queue_id: $wrapper.data('queue-id'),
            phone_number: $wrapper.data('phone'),
            sms_list_id: $wrapper.data('list-id')
        }).done(function (response) {
            if (response && response.success) {
                showNotice($wrapper, response.data.message, false);
                applyButtonStates($wrapper, response.data);
                return;
            }

            var message = (response && response.data && response.data.message)
                ? response.data.message
                : (i18n.requestFailed || 'The request failed. Please try again.');

            $button.prop('disabled', false).text(originalText);
            showNotice($wrapper, message, true);
        }).fail(function (xhr) {
            var message = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                ? xhr.responseJSON.data.message
                : (i18n.requestFailed || 'The request failed. Please try again.');

            $button.prop('disabled', false).text(originalText);
            showNotice($wrapper, message, true);
        });
    });

    /**
     * Bulk selection + batch actions toolbar.
     */
    var BULK_LABELS = {
        unsubscribe: i18n.bulkLabelUnsubscribe || 'Unsubscribe Selected',
        delete_users: i18n.bulkLabelDeleteUsers || 'Delete Selected Users',
        both: i18n.bulkLabelBoth || 'Unsubscribe & Delete Selected',
        refresh_status: i18n.bulkLabelRefresh || 'Refresh Status for Selected'
    };

    var selectedIds = {};

    function getRowCheckboxes() {
        return $('.mnem-sms-row-checkbox');
    }

    function isSelected(id) {
        return !!selectedIds[id];
    }

    function setSelected(id, checked) {
        if (checked) {
            selectedIds[id] = true;
        } else {
            delete selectedIds[id];
        }
    }

    function selectedCount() {
        return Object.keys(selectedIds).length;
    }

    function countAvailable(filterFn) {
        var count = 0;
        getRowCheckboxes().each(function () {
            var $cb = $(this);
            if (isSelected($cb.val()) && filterFn($cb)) {
                count += 1;
            }
        });
        return count;
    }

    function refreshSelectAllState() {
        var $rows = getRowCheckboxes();
        var total = $rows.length;
        var selected = 0;
        $rows.each(function () {
            if (isSelected($(this).val())) {
                selected += 1;
            }
        });

        var allSelected = total > 0 && selected === total;
        var noneSelected = selected === 0;

        $('#mnem-sms-select-all-header, #mnem-sms-select-all-toolbar').each(function () {
            var $cb = $(this);
            $cb.prop('checked', allSelected);
            $cb.prop('indeterminate', !allSelected && !noneSelected);
        });
    }

    function updateBulkToolbar() {
        var count = selectedCount();

        $('#mnem-sms-selected-count').text(
            (i18n.itemsSelected || '%d items selected').replace('%d', count)
        );

        var unsubscribeCount = countAvailable(function ($cb) { return $cb.data('unsubscribed') !== 1 && $cb.data('unsubscribed') !== '1'; });
        var deleteUsersCount = countAvailable(function ($cb) { return $cb.data('has-user') === 1 || $cb.data('has-user') === '1'; });
        var bothCount = countAvailable(function ($cb) {
            var notUnsubscribed = $cb.data('unsubscribed') !== 1 && $cb.data('unsubscribed') !== '1';
            var hasUser = $cb.data('has-user') === 1 || $cb.data('has-user') === '1';
            return notUnsubscribed && hasUser;
        });
        var refreshCount = countAvailable(function () { return true; });

        var counts = {
            unsubscribe: unsubscribeCount,
            delete_users: deleteUsersCount,
            both: bothCount,
            refresh_status: refreshCount
        };

        var $select = $('#mnem-sms-bulk-action-select');
        $select.find('option[value]').each(function () {
            var $option = $(this);
            var action = $option.val();
            if (!action || typeof counts[action] === 'undefined') {
                return;
            }
            var template = $option.data('label-template') || (BULK_LABELS[action] + ' (%d available)');
            $option.text(String(template).replace('%d', counts[action]));
            $option.prop('disabled', counts[action] === 0);
        });

        $select.prop('disabled', count === 0);
        if (count === 0) {
            $select.val('');
        }

        var selectedAction = $select.val();
        var $affectsUsers = $select.find('option[value="' + selectedAction + '"]').data('affects-users');
        $('#mnem-sms-bulk-warning').toggle(!!selectedAction && !!$affectsUsers);

        $('#mnem-sms-bulk-apply').prop('disabled', count === 0 || !selectedAction);

        refreshSelectAllState();
    }

    $(document).on('change', '.mnem-sms-row-checkbox', function () {
        setSelected($(this).val(), this.checked);
        updateBulkToolbar();
    });

    $(document).on('change', '#mnem-sms-select-all-header, #mnem-sms-select-all-toolbar', function () {
        var checked = this.checked;
        getRowCheckboxes().each(function () {
            $(this).prop('checked', checked);
            setSelected($(this).val(), checked);
        });
        updateBulkToolbar();
    });

    $(document).on('click', '#mnem-sms-clear-selection', function (event) {
        event.preventDefault();
        selectedIds = {};
        getRowCheckboxes().prop('checked', false);
        updateBulkToolbar();
    });

    $(document).on('change', '#mnem-sms-bulk-action-select', function () {
        updateBulkToolbar();
    });

    function renderBulkResult(data) {
        var $result = $('#mnem-sms-bulk-result');
        var total = data.total || 0;
        var successful = data.successful || 0;
        var failed = data.failed || 0;

        var lines = [];
        lines.push(
            (i18n.bulkSuccess || '%1$d of %2$d SMS records processed successfully.')
                .replace('%1$d', successful)
                .replace('%2$d', total)
        );
        if (failed > 0) {
            lines.push((i18n.bulkFailed || '%d failed.').replace('%d', failed));
        }
        if (data.dry_run) {
            lines.push(i18n.bulkDryRunNotice || 'Dry run only — no changes were made.');
        }

        var $html = $('<div></div>').text(lines.join(' '));

        if (data.errors && data.errors.length) {
            var $list = $('<ul style="margin:6px 0 0 18px;"></ul>');
            $.each(data.errors, function (index, error) {
                $('<li></li>').text(error).appendTo($list);
            });
            $html.append($list);
        }

        $result
            .css({
                background: failed > 0 ? '#fcf0f1' : '#edfaef',
                border: '1px solid ' + (failed > 0 ? '#d63638' : '#00a32a')
            })
            .empty()
            .append($html)
            .show();
    }

    $(document).on('click', '#mnem-sms-bulk-apply', function () {
        var $button = $(this);
        var action = $('#mnem-sms-bulk-action-select').val();
        var ids = Object.keys(selectedIds);

        if (!action) {
            window.alert(i18n.noBulkAction || 'Please choose a bulk action.');
            return;
        }
        if (!ids.length) {
            window.alert(i18n.noItemsSelected || 'No SMS records were selected.');
            return;
        }

        var dryRun = $('#mnem-sms-bulk-dry-run').is(':checked');
        var actionLabel = BULK_LABELS[action] || action;
        var confirmTemplate = dryRun
            ? (i18n.confirmBulkDryRun || 'Preview "%1$s" for %2$d selected SMS record(s) without making changes?')
            : (i18n.confirmBulk || 'Apply "%1$s" to %2$d selected SMS record(s)?');
        var confirmMessage = confirmTemplate.replace('%1$s', actionLabel).replace('%2$d', ids.length);

        if (!window.confirm(confirmMessage)) {
            return;
        }

        var $progress = $('#mnem-sms-bulk-progress');
        $('#mnem-sms-bulk-result').hide();
        $button.prop('disabled', true);
        $progress.text((i18n.processing || 'Processing %d SMS records…').replace('%d', ids.length)).show();

        $.post(settings.ajaxUrl, {
            action: 'mnem_sms_bulk_action',
            nonce: settings.nonce,
            action_type: action,
            queue_ids: ids,
            dry_run: dryRun ? '1' : ''
        }).done(function (response) {
            if (!response || !response.success) {
                var error = response && response.data && response.data.message
                    ? response.data.message
                    : (i18n.requestFailed || 'The request failed. Please try again.');
                window.alert(error);
                return;
            }

            renderBulkResult(response.data);

            if (!dryRun) {
                window.setTimeout(function () {
                    window.location.reload();
                }, 1500);
            }
        }).fail(function () {
            window.alert(i18n.requestFailed || 'The request failed. Please try again.');
        }).always(function () {
            $progress.hide();
            $button.prop('disabled', selectedCount() === 0);
        });
    });

    $(function () {
        updateBulkToolbar();
    });
})(jQuery);
