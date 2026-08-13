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

    $(refreshDashboardStats);
    setInterval(refreshDashboardStats, 30000);
})(jQuery);
