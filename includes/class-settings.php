<?php

class MNEM_Settings
{
    protected $option_name;
    protected $defaults;
    protected $get_callback;
    protected $update_callback;

    public function __construct($option_name = 'mnem_settings', array $defaults = array(), callable $get_callback = null, callable $update_callback = null)
    {
        $this->option_name = $option_name;
        $this->defaults = $defaults;
        $this->get_callback = $get_callback;
        $this->update_callback = $update_callback;
    }

    public function all()
    {
        $value = $this->read_option();

        if (! is_array($value)) {
            $value = array();
        }

        return array_replace($this->defaults, $value);
    }

    public function get($key, $default = null)
    {
        $settings = $this->all();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public function update(array $values)
    {
        $settings = array_replace($this->all(), $values);

        return $this->write_option($settings);
    }

    public function replace(array $values)
    {
        return $this->write_option(array_replace($this->defaults, $values));
    }

    protected function read_option()
    {
        if ($this->get_callback) {
            return call_user_func($this->get_callback, $this->option_name, $this->defaults);
        }

        if (function_exists('get_site_option')) {
            return get_site_option($this->option_name, $this->defaults);
        }

        return $this->defaults;
    }

    protected function write_option(array $settings)
    {
        if ($this->update_callback) {
            return (bool) call_user_func($this->update_callback, $this->option_name, $settings);
        }

        if (function_exists('update_site_option')) {
            return (bool) update_site_option($this->option_name, $settings);
        }

        return false;
    }
}
