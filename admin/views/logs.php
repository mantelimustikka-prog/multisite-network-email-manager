<?php
?><div class="wrap">
    <h1>Logs</h1>
    <table class="widefat striped">
        <thead><tr><th>Time</th><th>Level</th><th>Message</th></tr></thead>
        <tbody>
        <?php if (empty($entries)) : ?>
            <tr><td colspan="3">No log entries yet.</td></tr>
        <?php else : ?>
            <?php foreach ($entries as $entry) : ?>
                <tr>
                    <td><?php echo esc_html($entry['time']); ?></td>
                    <td><?php echo esc_html($entry['level']); ?></td>
                    <td><?php echo esc_html($entry['message']); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
