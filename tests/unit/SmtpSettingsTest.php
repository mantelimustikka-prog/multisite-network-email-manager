<?php

return array(
    'smtp password roundtrip' => function () {
        $store = array();
        $settings = new MNEM_SMTP_Settings(
            function ($name, $defaults) use (&$store) {
                return isset($store[$name]) ? $store[$name] : $defaults;
            },
            function ($name, $value) use (&$store) {
                $store[$name] = $value;
                return true;
            }
        );

        $settings->update(array('password' => 'super-secret'));

        mnem_assert_string_starts_with(MNEM_SMTP_Settings::PASSWORD_PREFIX, $store['mnem_smtp_settings']['password']);
        mnem_assert_same('super-secret', $settings->get_password());
    },
);
