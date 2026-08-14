(function($){
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
})(jQuery);
