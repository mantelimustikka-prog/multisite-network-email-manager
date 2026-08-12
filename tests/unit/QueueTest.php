<?php

return array(
    'queue retry backoff' => function () {
        $queue = new MNEM_Queue(new MNEM_Suppression_List());

        mnem_assert_same(300, $queue->retry_delay_seconds(1));
        mnem_assert_same(600, $queue->retry_delay_seconds(2));
        mnem_assert_same(1200, $queue->retry_delay_seconds(3));
    },
    'queue dedupe stability' => function () {
        $queue = new MNEM_Queue(new MNEM_Suppression_List());

        mnem_assert_same(
            $queue->build_dedupe_key('USER@example.com', 'Subject', 'Body', 10),
            $queue->build_dedupe_key('user@example.com', 'Subject', 'Body', 10)
        );
    },
);
