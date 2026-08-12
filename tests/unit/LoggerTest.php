<?php

return array(
    'logger scrubs secrets' => function () {
        $store = array();
        $settings = new MNEM_Settings(
            'mnem_logs',
            array(),
            function ($name, $defaults) use (&$store) {
                return isset($store[$name]) ? $store[$name] : $defaults;
            },
            function ($name, $value) use (&$store) {
                $store[$name] = $value;
                return true;
            }
        );
        $logger = new MNEM_Logger($settings);

        $entry = $logger->log('info', '******', array('token' => 'abc123', 'safe' => 'ok'));

        mnem_assert_same('******', $entry['message']);
        mnem_assert_same('[redacted]', $entry['context']['token']);
        mnem_assert_same('ok', $entry['context']['safe']);
    },
);
