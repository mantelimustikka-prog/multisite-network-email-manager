<?php

namespace MNEM\Interfaces;

defined('ABSPATH') || exit;

interface SmsProviderInterface
{
    /**
     * Send an SMS message to the given phone number.
     *
     * @return array{success: bool, message: string}
     */
    public function send(string $phone, string $message): array;

    /**
     * Test the provider connection / API credentials.
     *
     * @return array{success: bool, message: string}
     */
    public function test_connection(): array;

    /**
     * Return the list of required configuration fields for this provider.
     * Each entry: ['key' => string, 'label' => string, 'type' => 'text'|'password']
     *
     * @return array<int, array{key: string, label: string, type: string}>
     */
    public function get_config_schema(): array;
}
