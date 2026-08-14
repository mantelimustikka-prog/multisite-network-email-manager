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
                    '<p><strong>Recipient:</strong> <span data-field="recipient"></span></p>' +
                    '<p><strong>Sent:</strong> <span data-field="sentAt"></span></p>' +
                    '<p><strong>Status:</strong> <span data-field="status" class="mnem-badge"></span></p>' +
                    '<p><strong>Provider Message ID:</strong> <code data-field="messageId"></code></p>' +
                    '<p><strong>Open Count:</strong> <strong data-field="openCount"></strong> | <strong>Click Count:</strong> <strong data-field="clickCount"></strong></p>' +
                    '<p><strong>Open Timestamps:</strong></p>' +
                    '<pre data-field="openTimestamps"></pre>' +
                    '<p><strong>Click Timestamps:</strong></p>' +
                    '<pre data-field="clickTimestamps"></pre>' +
                    '<p><strong>Subject:</strong></p>' +
                    '<pre data-field="subject"></pre>' +
                    '<p><strong>Headers (JSON):</strong></p>' +
                    '<pre data-field="headers"></pre>' +
                    '<p><strong>Body:</strong></p>' +
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

    $(document).on('click', '.mnem-email-preview-button', function(){
        var $button = $(this);
        var $modal = ensurePreviewModal();
        var status = $button.data('status') || 'pending';

        $modal.find('[data-field="recipient"]').text($button.data('recipient') || '');
        $modal.find('[data-field="sentAt"]').text($button.data('sent-at') || '');
        $modal.find('[data-field="status"]').text(status).attr('class', 'mnem-badge mnem-status-' + status);
        $modal.find('[data-field="messageId"]').text($button.data('message-id') || '');
        $modal.find('[data-field="openCount"]').text($button.data('open-count') || 0);
        $modal.find('[data-field="clickCount"]').text($button.data('click-count') || 0);
        $modal.find('[data-field="subject"]').text($button.data('subject') || '');
        $modal.find('[data-field="body"]').text($button.data('body') || '');

        var headersRaw = $button.attr('data-headers') || '[]';
        var openRaw = $button.attr('data-open-timestamps') || '[]';
        var clickRaw = $button.attr('data-click-timestamps') || '[]';

        $modal.find('[data-field="headers"]').text(headersRaw);
        $modal.find('[data-field="openTimestamps"]').text(parseJsonArray(openRaw).join('\n'));
        $modal.find('[data-field="clickTimestamps"]').text(parseJsonArray(clickRaw).join('\n'));
        $modal.show();
    });

    $(document).on('click', '.mnem-modal__close, .mnem-modal__backdrop', function(){
        $('#mnem-email-preview-modal').hide();
    });
})(jQuery);
