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
})(jQuery);
