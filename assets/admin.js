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

    function initQuillEditors() {
        if (typeof window.Quill === 'undefined') {
            return;
        }

        $('[data-mnem-quill="1"]').each(function () {
            var $container = $(this);
            if ($container.data('mnemQuillInitialized')) {
                return;
            }

            var configuredHeight = String($container.data('mnemQuillHeight') || '450px');
            var isReadOnly = $container.data('mnemQuillReadonly') === 1 || $container.data('mnemQuillReadonly') === '1';
            var $source = $container.next('textarea.mnem-quill-source');
            var initialHtml = String($container.data('mnemQuillInitial') || $source.val() || '');

            var i18n = (typeof mnemAdmin !== 'undefined' && mnemAdmin.i18n) ? mnemAdmin.i18n : {};
            var labelSource = i18n.sourceView || 'HTML Source';
            var labelVisual = i18n.visualView || 'Visual Editor';

            // Build wrapper: source toggle button + editor container.
            var $wrapper = $('<div class="mnem-quill-wrapper"></div>');
            var $toggleBtn = $('<button type="button" class="button mnem-quill-source-toggle">' +
                '<span class="mnem-quill-source-toggle-visual">' + labelSource + '</span>' +
                '<span class="mnem-quill-source-toggle-source" style="display:none;">' + labelVisual + '</span>' +
                '</button>');
            var $sourceArea = $('<textarea class="mnem-quill-html-source large-text" style="display:none; width:100%; box-sizing:border-box;"></textarea>');
            $sourceArea.css('min-height', configuredHeight);

            $container.before($wrapper);
            $wrapper.append($toggleBtn).append($sourceArea).append($container);

            // Initialize Quill.
            var quill = new window.Quill($container.get(0), {
                theme: 'snow',
                readOnly: isReadOnly,
                modules: {
                    toolbar: isReadOnly ? false : [
                        [{ 'header': [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                        [{ 'indent': '-1' }, { 'indent': '+1' }],
                        ['link', 'image'],
                        ['blockquote', 'code-block'],
                        ['clean']
                    ]
                }
            });

            $container.css('min-height', configuredHeight);

            // Set initial HTML content.
            if (initialHtml) {
                quill.clipboard.dangerouslyPasteHTML(initialHtml);
            }
            $source.val(quill.root.innerHTML);

            // Sync on change.
            quill.on('text-change', function () {
                $source.val(quill.root.innerHTML);
            });

            // Source view toggle.
            $toggleBtn.on('click', function () {
                var isSource = $sourceArea.is(':visible');
                if (isSource) {
                    // Switch back to visual: paste HTML from source area into Quill.
                    quill.clipboard.dangerouslyPasteHTML($sourceArea.val());
                    $source.val(quill.root.innerHTML);
                    $sourceArea.hide();
                    $container.show();
                    $toggleBtn.find('.mnem-quill-source-toggle-visual').show();
                    $toggleBtn.find('.mnem-quill-source-toggle-source').hide();
                } else {
                    // Switch to source: populate source area with current HTML.
                    $sourceArea.val(quill.root.innerHTML);
                    $container.hide();
                    $sourceArea.show();
                    $toggleBtn.find('.mnem-quill-source-toggle-visual').hide();
                    $toggleBtn.find('.mnem-quill-source-toggle-source').show();
                }
            });

            // Before submit, ensure hidden textarea has latest content.
            $wrapper.closest('form').on('submit.mnemQuillSync', function () {
                if ($sourceArea.is(':visible')) {
                    $source.val($sourceArea.val());
                } else {
                    $source.val(quill.root.innerHTML);
                }
            });

            $container.data('mnemQuillInitialized', true);
        });
    }

    $(initQuillEditors);

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

    $(document).on('click', '.mnem-send-queue-item-now', function () {
        var $button = $(this);
        var queueId = parseInt($button.data('queue-id'), 10) || 0;
        var recipient = $button.data('recipient') || 'this recipient';
        var status = String($button.data('status') || 'pending');
        var originalText = $button.text();
        var confirmMessage = 'Send this email to ' + recipient + ' immediately?';

        if (status !== 'pending' && status !== 'failed') {
            confirmMessage = 'This email is currently marked as "' + status + '". Send it again to ' + recipient + ' now?';
        }

        if (!queueId || !window.confirm(confirmMessage)) {
            return;
        }

        $button.prop('disabled', true).text('Sending...');

        $.post(mnemAdmin.ajaxUrl, {
            action: 'mnem_send_queue_item_now',
            nonce: mnemAdmin.nonce,
            queue_id: queueId
        }).done(function (response) {
            var redirectUrl = new window.URL(window.location.href);
            redirectUrl.searchParams.set('mnem_notice', (response && response.data && response.data.notice) || 'queue_item_sent_now');
            window.location.assign(redirectUrl.toString());
        }).fail(function (jqXHR) {
            var data = jqXHR.responseJSON && jqXHR.responseJSON.data ? jqXHR.responseJSON.data : {};
            var message = data.message || 'Failed to send queue item immediately.';
            window.alert(message);
            $button.prop('disabled', false).text(originalText);
        });
    });

    $(document).on('click', '[data-mnem-smtp-action="test-connection"]', function(){
        runSmtpAction('mnem_test_connection');
    });

    $(document).on('click', '#mnem_test_sms_connection', function(){
        var $button = $(this);
        var $result = $('#mnem_sms_test_result');
        var originalText = $button.text();

        $button.prop('disabled', true).text('Testing...');
        $result.html('');

        $.post(mnemAdmin.ajaxUrl, {
            action: 'mnem_test_sms_connection',
            nonce: mnemAdmin.nonce
        }, function(response){
            if (response && response.success) {
                $result.html('<span style="color:green;">&#10003; ' + response.data.message + '</span>');
            } else {
                $result.html('<span style="color:red;">&#10007; ' + (response.data && response.data.message ? response.data.message : 'Test failed') + '</span>');
            }
        }).fail(function(jqXHR){
            var data = jqXHR.responseJSON && jqXHR.responseJSON.data ? jqXHR.responseJSON.data : {};
            var message = data.message || 'Connection test failed.';
            $result.html('<span style="color:red;">&#10007; ' + message + '</span>');
        }).always(function(){
            $button.prop('disabled', false).text(originalText);
        });
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

    $(document).on('click', '.mnem-queue-preview-button', function(){
        var $button = $(this);
        var queueId = parseInt($button.data('queue-id') || 0, 10);
        if (!queueId) {
            return;
        }
        var $modal = ensurePreviewModal();
        $modal.find('[data-field="subject"]').text('Loading...');
        $modal.find('[data-field="body"]').text('Loading...');
        $modal.show();

        $.post(mnemAdmin.ajaxUrl, {
            action: 'mnem_get_queue_preview',
            nonce: mnemAdmin.nonce,
            queue_id: queueId
        }).done(function(response){
            if (!response || !response.success || !response.data) {
                $modal.find('[data-field="subject"]').text('Failed to load preview');
                $modal.find('[data-field="body"]').text('');
                return;
            }

            var row = response.data;
            var status = row.status || 'pending';
            var headers = parseHeaders(row.headers || '[]');
            var fromValue = '';
            if (row.from_name && row.from_email) {
                fromValue = row.from_name + ' <' + row.from_email + '>';
            } else if (row.from_email) {
                fromValue = row.from_email;
            } else {
                fromValue = extractHeaderValue(headers, 'From') || '';
            }

            $modal.find('[data-field="from"]').text(fromValue);
            $modal.find('[data-field="recipient"]').text(row.recipient_email || '');
            $modal.find('[data-field="sentAt"]').text(row.sent_at || row.created_at || '');
            $modal.find('[data-field="status"]').text(status).attr('class', 'mnem-badge mnem-status-' + status);
            $modal.find('[data-field="messageId"]').text(row.provider_message_id || '');
            $modal.find('[data-field="openCount"]').text(row.opened || '\u2014');
            $modal.find('[data-field="clickCount"]').text(row.clicked || '\u2014');
            $modal.find('[data-field="subject"]').text(row.subject || '');
            $modal.find('[data-field="body"]').text(row.body || '');
            $modal.find('[data-field="headers"]').text(JSON.stringify(headers, null, 2));
            $modal.find('[data-field="openTimestamps"]').text(row.opened || '');
            $modal.find('[data-field="clickTimestamps"]').text(row.clicked || '');
            renderPreviewFrame($modal.find('[data-field="bodyFrame"]'), row.body || '');
        });
    });

    $(document).on('click', '.mnem-modal__close, .mnem-modal__backdrop', function(){
        $('#mnem-email-preview-modal').hide();
    });

    function renderTableDiagnosticsOutput(payload) {
        var $output = $('#mnem-table-diagnostics-output');
        if (!$output.length) {
            return;
        }
        $output.text(JSON.stringify(payload || {}, null, 2));
    }

    function runTableDiagnosticsAction(action) {
        var actionMap = {
            recreate: 'mnem_table_diagnostics_recreate',
            optimize: 'mnem_table_diagnostics_optimize',
            repair: 'mnem_table_diagnostics_repair'
        };
        var ajaxAction = actionMap[action];
        if (!ajaxAction) {
            return;
        }

        $.post(mnemAdmin.ajaxUrl, {
            action: ajaxAction,
            nonce: mnemAdmin.nonce
        }).done(function(response){
            if (!response || !response.success) {
                renderTableDiagnosticsOutput({ success: false, message: 'Request failed.' });
                return;
            }
            renderTableDiagnosticsOutput(response.data || {});
        }).fail(function(jqXHR){
            var data = jqXHR.responseJSON && jqXHR.responseJSON.data ? jqXHR.responseJSON.data : {};
            renderTableDiagnosticsOutput({ success: false, error: data });
        });
    }

    function downloadTableDiagnosticsReport(format, report) {
        var extension = format === 'text' ? 'txt' : 'json';
        var mimeType = format === 'text' ? 'text/plain;charset=utf-8' : 'application/json;charset=utf-8';
        var blob = new Blob([String(report || '')], { type: mimeType });
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = 'mnem-table-diagnostics-' + (new Date().toISOString().replace(/[:.]/g, '-')) + '.' + extension;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    $(document).on('click', '[data-mnem-table-action]', function(){
        runTableDiagnosticsAction($(this).data('mnem-table-action'));
    });

    $(document).on('click', '[data-mnem-table-export]', function(){
        var format = ($(this).data('mnem-table-export') || 'json').toString();
        $.post(mnemAdmin.ajaxUrl, {
            action: 'mnem_table_diagnostics_export',
            nonce: mnemAdmin.nonce,
            format: format
        }).done(function(response){
            if (!response || !response.success || !response.data) {
                renderTableDiagnosticsOutput({ success: false, message: 'Export failed.' });
                return;
            }
            renderTableDiagnosticsOutput(response.data);
            downloadTableDiagnosticsReport(response.data.format || 'json', response.data.report || '');
        }).fail(function(jqXHR){
            var data = jqXHR.responseJSON && jqXHR.responseJSON.data ? jqXHR.responseJSON.data : {};
            renderTableDiagnosticsOutput({ success: false, error: data });
        });
    });
})(jQuery);
