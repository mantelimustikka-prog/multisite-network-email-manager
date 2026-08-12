<?php

return array(
    'settings merge defaults' => function () {
        $store = array('mnem_settings' => array('enabled' => true));
        $settings = new MNEM_Settings(
            'mnem_settings',
            array('enabled' => false, 'host' => 'localhost'),
            function ($name, $defaults) use (&$store) {
                return isset($store[$name]) ? $store[$name] : $defaults;
            },
            function ($name, $value) use (&$store) {
                $store[$name] = $value;
                return true;
            }
        );

        mnem_assert_same(array('enabled' => true, 'host' => 'localhost'), $settings->all());
        $settings->update(array('host' => 'smtp.example.com'));

        mnem_assert_same('smtp.example.com', $store['mnem_settings']['host']);
    },
);
