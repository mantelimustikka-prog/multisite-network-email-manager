<?php

namespace MNEM;

defined('ABSPATH') || exit;

/**
 * Abstract base class for email service providers.
 */
abstract class EmailProvider
{
    /** @var array<string,mixed> */
    protected $config = array();

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(array $config = array())
    {
        $this->config = $config;
    }

    /**
     * Send an email.
     *
     * @param string              $to
     * @param string              $subject
     * @param string              $body
     * @param array<string,mixed> $headers
     * @return array<string,mixed>
     */
    abstract public function send(string $to, string $subject, string $body, array $headers = array()): array;

    /**
     * Validate provider configuration.
     *
     * @return bool
     */
    abstract public function validate_config(): bool;

    /**
     * Test provider connectivity.
     *
     * @return array<string,mixed>
     */
    abstract public function test_connection(): array;

    /**
     * Human-readable provider name.
     *
     * @return string
     */
    abstract public function get_provider_name(): string;

    /**
     * Return field definitions needed to configure the provider.
     *
     * @return array<string,array<string,string>>
     */
    abstract public function get_config_fields(): array;

    /**
     * Build a successful result array.
     *
     * @param string              $message
     * @param string              $message_id
     * @param array<string,mixed> $metadata
     * @return array<string,mixed>
     */
    protected function success_result(string $message = 'Email sent.', string $message_id = '', array $metadata = array()): array
    {
        return array(
            'success'    => true,
            'message'    => $message,
            'provider'   => $this->get_provider_name(),
            'message_id' => $message_id,
            'metadata'   => $metadata,
        );
    }

    /**
     * Build an error result array.
     *
     * @param string              $message
     * @param array<string,mixed> $metadata
     * @return array<string,mixed>
     */
    protected function error_result(string $message = 'Send failed.', array $metadata = array()): array
    {
        return array(
            'success'    => false,
            'message'    => $message,
            'provider'   => $this->get_provider_name(),
            'message_id' => '',
            'metadata'   => $metadata,
        );
    }

    /**
     * Get a config value.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    protected function config(string $key, $default = '')
    {
        return array_key_exists($key, $this->config) ? $this->config[$key] : $default;
    }
}
