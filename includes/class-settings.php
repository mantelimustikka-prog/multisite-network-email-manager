<?php

namespace MNEM;

defined('ABSPATH') || exit;

class Settings
{
    public const OPTION_KEY = 'mnem_settings';

    public static function get_all()
    {
        $settings = get_site_option(self::OPTION_KEY, array());

        return is_array($settings) ? $settings : array();
    }

    public static function get(string $key, $default = null)
    {
        $settings = self::get_all();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public static function set(string $key, $value)
    {
        $settings = self::get_all();
        $settings[$key] = $value;

        return update_site_option(self::OPTION_KEY, $settings);
    }

    public static function delete(string $key)
    {
        $settings = self::get_all();

        if (array_key_exists($key, $settings)) {
            unset($settings[$key]);
        }

        return update_site_option(self::OPTION_KEY, $settings);
    }
}
