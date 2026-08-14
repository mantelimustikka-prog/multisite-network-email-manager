(function($){
    function bindQueueDeletionUi() {
        $(document).on('change', '#mnem-check-all', function(){
            var checked = $(this).is(':checked');
            $('.mnem-queue-checkbox:not(:disabled)').prop('checked', checked);
        });

        $(document).on('click', '.mnem-delete-queue-item', function(event){
            var $button = $(this);
            var recipient = $button.data('recipient') || 'this recipient';
            var status = $button.data('status') || 'pending';
            if (!window.confirm('Delete queued email for ' + recipient + ' (' + status + ')?')) {
                event.preventDefault();
                return;
            }

            var $form = $('#mnem-single-queue-delete-form');
            $form.find('input[name="queue_id"]').val($button.data('queue-id'));
            $button.prop('disabled', true).text('Deleting...');
            $form.trigger('submit');
        });

        $(document).on('submit', '.mnem-queue-bulk-form', function(event){
            var $form = $(this);
            var bulkAction = $('#mnem-bulk-action').val();
            var $actionField = $form.find('input[name="mnem_action"]');
            var $statusField = $form.find('input[name="status"]');
            var selectedCount = $form.find('.mnem-queue-checkbox:checked').length;
            var message = '';

            $actionField.val('');
            $statusField.val('');

            if (!bulkAction) {
                event.preventDefault();
                return;
            }

            if (bulkAction === 'delete_selected') {
                if (selectedCount < 1) {
                    window.alert('No items selected for deletion.');
                    event.preventDefault();
                    return;
                }

                $actionField.val('delete_queue_items');
                message = 'Delete ' + selectedCount + ' selected queued email' + (selectedCount === 1 ? '' : 's') + '?';
            } else if (bulkAction === 'delete_pending') {
                $actionField.val('delete_queue_by_status');
                $statusField.val('pending');
                message = 'Delete all pending queued emails?';
            } else if (bulkAction === 'delete_failed') {
                $actionField.val('delete_queue_by_status');
                $statusField.val('failed');
                message = 'Delete all failed queued emails?';
            }

            if (!message || !window.confirm(message)) {
                event.preventDefault();
                return;
            }

            $form.find('button[type="submit"]').prop('disabled', true).text('Deleting...');
        });
    }

    bindQueueDeletionUi();

    $(document).on('click', '.mnem-toggle-api-key', function () {
        var $button = $(this);
        var inputId = $button.data('input-id');
        var encoded = $button.data('encoded');
        var $input = $('#' + inputId);
        var $icon = $button.find('.dashicons');

        if (!$input.length) {
            return;
        }

        if ($input.attr('type') === 'password') {
            var decoded = '';
            if (encoded) {
                try {
                    decoded = atob(String(encoded));
                } catch (e) {
                    decoded = String(encoded);
                }
            }
            $input.attr('type', 'text').val(decoded);
            $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            $button.attr('title', 'Hide API key');
        } else {
            $input.attr('type', 'password').val('\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022');
            $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            $button.attr('title', 'Show/hide API key');
        }
    });

    $(document).on('click', '.mnem-test-provider-connection', function () {
        var $button   = $(this);
        var provider  = $button.data('provider');
        var $result   = $('#mnem-test-result-' + provider);

        if (!provider || typeof mnemAdmin === 'undefined' || !mnemAdmin.ajaxUrl) {
            return;
        }

        $button.prop('disabled', true).text('Testing\u2026');
        $result.text('').css('color', '');

        $.post(mnemAdmin.ajaxUrl, {
            action:   'mnem_test_provider_connection',
            nonce:    mnemAdmin.nonce,
            provider: provider
        }).done(function (response) {
            $button.prop('disabled', false).text('Test Connection');
            var msg = response && response.data && response.data.message ? response.data.message : 'Connection successful.';
            $result.text('\u2714 ' + msg).css('color', 'green');
        }).fail(function (jqXHR) {
            $button.prop('disabled', false).text('Test Connection');
            var data = jqXHR.responseJSON && jqXHR.responseJSON.data ? jqXHR.responseJSON.data : {};
            var msg  = data.message || 'Connection failed.';
            $result.text('\u2716 ' + msg).css('color', '#d63638');
        });
    });

    if (typeof mnemAdmin === 'undefined' || !mnemAdmin.ajaxUrl) {
        return;
    }

    function refreshDashboardStats() {
        var $stats = $('[data-mnem-queue-stats]');
        if (!$stats.length) {
            return;
        }

        $.post(mnemAdmin.ajaxUrl, {
            action: 'mnem_dashboard_stats',
            nonce: mnemAdmin.nonce
        }).done(function(response){
            if (!response || !response.success || !response.data || !response.data.queue) {
                return;
            }

            $.each(response.data.queue, function(key, value){
                var $node = $('[data-mnem-stat="' + key + '"]');
                if (!$node.length) {
                    return;
                }

                if (key === 'next_retry_at') {
                    var attempts = response.data.queue.next_retry_attempts || 0;
                    $node.text(value ? value + ' (attempt ' + attempts + ')' : 'No retries scheduled');
                    return;
                }

                $node.text(value);
            });
        });
    }

    function renderSmtpResult(result) {
        var $result = $('[data-mnem-smtp-result]');
        if (!$result.length) {
            return;
        }

        $result.text(JSON.stringify(result, null, 2));
    }

    function runSmtpAction(action, payload) {
        payload = payload || {};
        payload.action = action;
        payload.nonce = mnemAdmin.nonce;

        $.post(mnemAdmin.ajaxUrl, payload).done(function(response){
            if (!response || !response.success) {
                renderSmtpResult({ success: false, message: 'Request failed.' });
                return;
            }

            renderSmtpResult(response.data || {});
        });
    }

    $(refreshDashboardStats);
    setInterval(refreshDashboardStats, 30000);

    $(document).on('click', '[data-mnem-smtp-action="test-connection"]', function(){
        runSmtpAction('mnem_test_connection');
    });

    $(document).on('click', '[data-mnem-smtp-action="send-test-email"]', function(){
        runSmtpAction('mnem_send_test_email', {
            email: $('#mnem-smtp-test-email').val() || ''
        });
    });

    $(document).on('click', '[data-mnem-smtp-action="copy-result"]', function(){
        var text = $('[data-mnem-smtp-result]').text();
        if (navigator.clipboard && text) {
            navigator.clipboard.writeText(text);
        }
    });

    function ensurePreviewModal() {
        var $existing = $('#mnem-email-preview-modal');
        if ($existing.length) {
            return $existing;
        }

        var modalHtml = '' +
            '<div id="mnem-email-preview-modal" class="mnem-modal" style="display:none;">' +
                '<div class="mnem-modal__backdrop"></div>' +
                '<div class="mnem-modal__dialog" role="dialog" aria-modal="true" aria-label="Email preview">' +
                    '<button type="button" class="mnem-modal__close button-link" aria-label="Close">&times;</button>' +
                    '<h2>Email Preview</h2>' +
                    '<div class="mnem-email-preview__meta">' +
                        '<p><strong>From:</strong> <span data-field="from"></span></p>' +
                        '<p><strong>To:</strong> <span data-field="recipient"></span></p>' +
                        '<p><strong>Sent:</strong> <span data-field="sentAt"></span></p>' +
                        '<p><strong>Status:</strong> <span data-field="status" class="mnem-badge"></span></p>' +
                        '<p><strong>Provider Message ID:</strong> <code data-field="messageId"></code></p>' +
                        '<p><strong>Open Count:</strong> <strong data-field="openCount"></strong></p>' +
                        '<p><strong>Click Count:</strong> <strong data-field="clickCount"></strong></p>' +
                    '</div>' +
                    '<p><strong>Open Timestamps:</strong></p>' +
                    '<pre data-field="openTimestamps"></pre>' +
                    '<p><strong>Click Timestamps:</strong></p>' +
                    '<pre data-field="clickTimestamps"></pre>' +
                    '<p><strong>Subject:</strong></p>' +
                    '<pre data-field="subject"></pre>' +
                    '<p><strong>Headers (JSON):</strong></p>' +
                    '<pre data-field="headers"></pre>' +
                    '<p><strong>Rendered Preview:</strong></p>' +
                    '<iframe title="Rendered email preview" class="mnem-email-preview__frame" sandbox="" data-field="bodyFrame"></iframe>' +
                    '<p><strong>HTML Source:</strong></p>' +
                    '<pre data-field="body"></pre>' +
                '</div>' +
            '</div>';

        $('body').append(modalHtml);
        return $('#mnem-email-preview-modal');
    }

    function parseJsonArray(raw) {
        if (!raw) {
            return [];
        }
        try {
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function parseHeaders(raw) {
        if (!raw) {
            return [];
        }

        if (Array.isArray(raw)) {
            return raw;
        }

        try {
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function extractHeaderValue(headers, name) {
        var prefix = String(name || '').toLowerCase() + ':';
        for (var i = 0; i < headers.length; i++) {
            var header = String(headers[i] || '');
            if (header.toLowerCase().indexOf(prefix) === 0) {
                return $.trim(header.substring(prefix.length));
            }
        }

        return '';
    }

    function renderPreviewFrame($frame, html) {
        var frame = $frame.get(0);
        if (!frame) {
            return;
        }

        frame.srcdoc = html || '';
    }

    $(document).on('click', '.mnem-email-preview-button', function(){
        var $button = $(this);
        var emailId = parseInt($button.data('email-id') || 0, 10);
        if (!emailId) {
            return;
        }
        var $modal = ensurePreviewModal();
        $modal.find('[data-field="subject"]').text('Loading...');
        $modal.find('[data-field="body"]').text('Loading...');
        $modal.show();

        $.post(mnemAdmin.ajaxUrl, {
            action: 'mnem_get_email_preview',
            nonce: mnemAdmin.nonce,
            email_id: emailId
        }).done(function(response){
            if (!response || !response.success || !response.data) {
                $modal.find('[data-field="subject"]').text('Failed to load preview');
                $modal.find('[data-field="body"]').text('');
                return;
            }

            var row = response.data;
            var status = row.delivery_status || 'pending';
            var headers = parseHeaders(row.headers || '[]');

            $modal.find('[data-field="from"]').text(extractHeaderValue(headers, 'From') || '');
            $modal.find('[data-field="recipient"]').text(row.recipient_email || '');
            $modal.find('[data-field="sentAt"]').text(row.created_at || '');
            $modal.find('[data-field="status"]').text(status).attr('class', 'mnem-badge mnem-status-' + status);
            $modal.find('[data-field="messageId"]').text(row.provider_message_id || '');
            $modal.find('[data-field="openCount"]').text(row.open_count || 0);
            $modal.find('[data-field="clickCount"]').text(row.click_count || 0);
            $modal.find('[data-field="subject"]').text(row.subject || '');
            $modal.find('[data-field="body"]').text(row.body || '');
            $modal.find('[data-field="headers"]').text(JSON.stringify(headers, null, 2));
            $modal.find('[data-field="openTimestamps"]').text(parseJsonArray(row.open_timestamps || '[]').join('\n'));
            $modal.find('[data-field="clickTimestamps"]').text(parseJsonArray(row.click_timestamps || '[]').join('\n'));
            renderPreviewFrame($modal.find('[data-field="bodyFrame"]'), row.body || '');
        });
    });

    $(document).on('click', '.mnem-modal__close, .mnem-modal__backdrop', function(){
        $('#mnem-email-preview-modal').hide();
    });
})(jQuery);
