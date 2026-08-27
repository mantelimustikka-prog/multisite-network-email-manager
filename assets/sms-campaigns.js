/**
 * SMS Campaigns admin JavaScript.
 *
 * Handles:
 *  - Live character & segment counter
 *  - Form validation
 *  - Test SMS AJAX
 *  - Campaign stats AJAX
 *  - Recipient preview AJAX
 *  - SMS list info AJAX
 */
(function ($) {
    'use strict';

    var mnemSms = window.mnemSms || {};

    // ---------------------------------------------------------------------------
    // Character / segment counter
    // ---------------------------------------------------------------------------

    function updateCounter($textarea) {
        var text     = $textarea.val();
        var len      = text.length;
        var isUni    = /[^\u0000-\u007F]/.test(text);
        var singleMax = isUni ? 70 : 160;
        var multiMax  = isUni ? 67 : 153;
        var segs     = len === 0 ? 1 : (len <= singleMax ? 1 : Math.ceil(len / multiMax));

        var $counter = $textarea.siblings('#mnem-sms-char-counter').length
            ? $textarea.siblings('#mnem-sms-char-counter')
            : $textarea.closest('td').find('#mnem-sms-char-counter');

        $counter.find('#mnem-sms-chars').text(len);
        $counter.find('#mnem-sms-segments').text(segs);

        var remaining = segs === 1 ? (singleMax - len) : ((segs * multiMax) - len);
        if (remaining < 20) {
            $counter.addClass('mnem-counter-warning');
        } else {
            $counter.removeClass('mnem-counter-warning');
        }
    }

    // ---------------------------------------------------------------------------
    // Form validation
    // ---------------------------------------------------------------------------

    function validateCampaignForm($form) {
        var valid   = true;
        var $name   = $form.find('[name="name"]');
        var $body   = $form.find('[name="message_body"]');
        var $listId = $form.find('[name="sms_list_id"]');

        if ($name.val().trim() === '') {
            $name.addClass('mnem-field-error');
            valid = false;
        } else {
            $name.removeClass('mnem-field-error');
        }

        if ($body.val().trim() === '') {
            $body.addClass('mnem-field-error');
            valid = false;
        } else {
            $body.removeClass('mnem-field-error');
        }

        if ($listId.length && $listId.val() === '') {
            $listId.addClass('mnem-field-error');
            valid = false;
        } else {
            $listId.removeClass('mnem-field-error');
        }

        return valid;
    }

    // ---------------------------------------------------------------------------
    // AJAX helpers
    // ---------------------------------------------------------------------------

    function getNonce() {
        return $('#mnem_sms_ajax_nonce').val() || (typeof mnemSms.nonce !== 'undefined' ? mnemSms.nonce : '');
    }

    /**
     * Send test SMS via AJAX.
     */
    function sendSmsTest(campaignId, phone, $btn, $result) {
        $btn.prop('disabled', true);
        $result.text('Sending…').css('color', '#666');

        $.post(ajaxurl, {
            action:      'mnem_send_sms_test',
            nonce:       getNonce(),
            campaign_id: campaignId,
            phone:       phone
        })
        .done(function (response) {
            if (response.success) {
                $result.text(response.data.message).css('color', '#00a32a');
            } else {
                $result.text((response.data && response.data.message) || 'Failed.').css('color', '#d63638');
            }
        })
        .fail(function () {
            $result.text('Request failed.').css('color', '#d63638');
        })
        .always(function () {
            $btn.prop('disabled', false);
        });
    }

    /**
     * Load SMS list subscriber count.
     */
    function loadListInfo(listId, $target) {
        if (!listId) {
            $target.text('');
            return;
        }

        $target.text('Loading…');

        $.post(ajaxurl, {
            action:  'mnem_get_sms_list_info',
            nonce:   getNonce(),
            list_id: listId
        })
        .done(function (response) {
            if (response.success) {
                $target.text(response.data.subscriber_count + ' recipients');
            } else {
                $target.text('');
            }
        })
        .fail(function () {
            $target.text('');
        });
    }

    /**
     * Load campaign delivery stats.
     */
    function loadCampaignStats(campaignId, $container) {
        $.post(ajaxurl, {
            action:      'mnem_get_sms_campaign_stats',
            nonce:       getNonce(),
            campaign_id: campaignId
        })
        .done(function (response) {
            if (response.success) {
                var d = response.data;
                $container.html(
                    'Total: <strong>' + d.total_recipients + '</strong> &bull; ' +
                    'Sent: <strong>' + d.sent_count + '</strong> &bull; ' +
                    'Failed: <strong>' + d.failed_count + '</strong> &bull; ' +
                    'Pending: <strong>' + d.pending_count + '</strong>'
                );
            }
        });
    }

    /**
     * Load recipient preview.
     */
    function loadRecipientPreview(campaignId, limit, $container) {
        $container.html('<em>Loading…</em>');

        $.post(ajaxurl, {
            action:      'mnem_preview_sms_recipients',
            nonce:       getNonce(),
            campaign_id: campaignId,
            limit:       limit || 20
        })
        .done(function (response) {
            if (!response.success) {
                $container.html('<em>Could not load preview.</em>');
                return;
            }

            var data   = response.data;
            var html   = '<p>Showing ' + data.recipients.length + ' of ' + data.total + ' recipients.</p>';
            html += '<ul style="margin:0;padding-left:1.2em;">';
            $.each(data.recipients, function (i, r) {
                html += '<li>' + $('<span>').text(r.phone_number).html() + '</li>';
            });
            html += '</ul>';
            $container.html(html);
        })
        .fail(function () {
            $container.html('<em>Request failed.</em>');
        });
    }

    // ---------------------------------------------------------------------------
    // DOM ready
    // ---------------------------------------------------------------------------

    $(function () {
        // Character counter on SMS body textarea
        var $body = $('#mnem_sms_campaign_body');
        if ($body.length) {
            $body.on('input keyup', function () { updateCounter($body); });
            updateCounter($body);
        }

        // Form validation on submit
        var $form = $('#mnem-sms-campaign-form');
        if ($form.length) {
            $form.on('submit', function (e) {
                if (!validateCampaignForm($form)) {
                    e.preventDefault();
                    if (window.alert) {
                        alert('Please fill in all required fields.');
                    }
                }
            });
        }

        // Test SMS button
        $(document).on('click', '#mnem-send-sms-test', function () {
            var campaignId = $('#mnem_sms_test_campaign_id').val();
            var phone      = $('#mnem_sms_test_phone').val().trim();
            var $btn       = $(this);
            var $result    = $('#mnem-sms-test-result');

            if (!phone) {
                $result.text('Please enter a phone number.').css('color', '#d63638');
                return;
            }

            sendSmsTest(campaignId, phone, $btn, $result);
        });

        // Load list info when SMS list selector changes
        $(document).on('change', '#mnem_sms_campaign_list', function () {
            var listId  = $(this).val();
            var $target = $('#mnem-sms-recipient-count');
            loadListInfo(listId, $target);
        });

        // Trigger list info load on page load if a list is already selected
        var $listSel = $('#mnem_sms_campaign_list');
        if ($listSel.length && $listSel.val()) {
            loadListInfo($listSel.val(), $('#mnem-sms-recipient-count'));
        }

        // Refresh stats button (if present)
        $(document).on('click', '.mnem-refresh-sms-stats', function () {
            var campaignId = $(this).data('campaign-id');
            var $container = $(this).siblings('.mnem-sms-stats-container');
            loadCampaignStats(campaignId, $container);
        });

        // Recipient preview button (if present)
        $(document).on('click', '.mnem-preview-sms-recipients', function () {
            var campaignId = $(this).data('campaign-id');
            var $container = $(this).siblings('.mnem-recipient-preview-container');
            loadRecipientPreview(campaignId, 20, $container);
        });

        // Bulk action confirmation
        $(document).on('submit', '.mnem-bulk-sms-action-form', function () {
            var action = $(this).find('select[name="bulk_action"]').val();
            if (action === 'delete') {
                if (!window.confirm('Delete the selected SMS campaigns? This cannot be undone.')) {
                    return false;
                }
            }
        });
    });

}(jQuery));
