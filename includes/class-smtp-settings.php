<?php

class MNEM_SMTP_Settings extends MNEM_Settings
{
    const PASSWORD_PREFIX = 'obfuscated:';

    public function __construct(callable $get_callback = null, callable $update_callback = null)
    {
        parent::__construct('mnem_smtp_settings', array(
            'enabled' => false,
            'host' => '',
            'port' => 587,
            'secure' => 'tls',
            'username' => '',
            'password' => '',
            'from_email' => '',
            'from_name' => '',
        ), $get_callback, $update_callback);
    }

    public function update(array $values)
    {
        if (array_key_exists('password', $values) && '' !== $values['password']) {
            $values['password'] = $this->obfuscate_password($values['password']);
        } elseif (array_key_exists('password', $values) && '' === $values['password']) {
            unset($values['password']);
        }

        if (array_key_exists('enabled', $values)) {
            $values['enabled'] = (bool) $values['enabled'];
        }

        if (array_key_exists('port', $values)) {
            $values['port'] = (int) $values['port'];
        }

        return parent::update($values);
    }

    public function get_password()
    {
        return $this->deobfuscate_password((string) $this->get('password', ''));
    }

    public function export()
    {
        $settings = $this->all();
        $settings['password'] = $this->get_password();

        return $settings;
    }

    public function obfuscate_password($password)
    {
        return self::PASSWORD_PREFIX . base64_encode((string) $password);
    }

    public function deobfuscate_password($stored)
    {
        if (0 !== strpos((string) $stored, self::PASSWORD_PREFIX)) {
            return (string) $stored;
        }

        $decoded = base64_decode(substr((string) $stored, strlen(self::PASSWORD_PREFIX)), true);

        return false === $decoded ? '' : $decoded;
    }
}
