<?php

namespace MNEM\Providers;

defined('ABSPATH') || exit;

/**
 * Abstract base for SMS provider stubs.
 */
abstract class SmsBaseProvider implements \MNEM\Interfaces\SmsProviderInterface
{
    /** @var array<string,string> */
    protected array $config;

    /** @param array<string,string> $config */
    public function __construct(array $config = array())
    {
        $this->config = $config;
    }

    public function send(string $phone, string $message): array
    {
        return array('success' => false, 'message' => 'Not implemented.');
    }

    public function test_connection(): array
    {
        return array('success' => false, 'message' => 'Not implemented.');
    }
}
