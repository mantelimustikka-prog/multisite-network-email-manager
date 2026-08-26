<?php

namespace MNEM;

defined('ABSPATH') || exit;

/**
 * Maps provider-specific SMS delivery statuses to the queue's canonical statuses.
 *
 * Queue canonical statuses: pending, sent, delivered, failed, bounce
 */
class SmsProviderStatusMap
{
    /**
     * Per-provider status translation tables.
     *
     * @var array<string,array<string,string>>
     */
    private static array $maps = array(
        'twilio' => array(
            'queued'       => 'pending',
            'accepted'     => 'pending',
            'sending'      => 'sent',
            'sent'         => 'sent',
            'delivered'    => 'delivered',
            'undelivered'  => 'bounce',
            'failed'       => 'failed',
        ),
        'clicksend' => array(
            'submitted'    => 'sent',
            'delivered'    => 'delivered',
            'failed'       => 'failed',
            'bounce'       => 'bounce',
        ),
        'textmagic' => array(
            'q'            => 'pending',
            's'            => 'sent',
            'd'            => 'delivered',
            'e'            => 'failed',
            'r'            => 'failed',
            // Textedly also uses text values.
            'sent'         => 'sent',
            'delivered'    => 'delivered',
            'failed'       => 'failed',
        ),
        'simpletexting' => array(
            'SENT'         => 'sent',
            'DELIVERED'    => 'delivered',
            'FAILED'       => 'failed',
            'UNDELIVERED'  => 'bounce',
            'sent'         => 'sent',
            'delivered'    => 'delivered',
            'failed'       => 'failed',
        ),
        'messagedesk' => array(
            'sent'         => 'sent',
            'delivered'    => 'delivered',
            'failed'       => 'failed',
        ),
        'eztexting' => array(
            'sent'         => 'sent',
            'delivered'    => 'delivered',
            'failed'       => 'failed',
            'bounce'       => 'bounce',
        ),
        'salesmsg' => array(
            'sent'         => 'sent',
            'delivered'    => 'delivered',
            'failed'       => 'failed',
            'undelivered'  => 'bounce',
        ),
        'textline' => array(
            'sent'         => 'sent',
            'delivered'    => 'delivered',
            'failed'       => 'failed',
        ),
        'slicktext' => array(
            'SENT'         => 'sent',
            'DELIVERED'    => 'delivered',
            'FAILED'       => 'failed',
            'sent'         => 'sent',
            'delivered'    => 'delivered',
            'failed'       => 'failed',
        ),
        'textedly' => array(
            'sent'         => 'sent',
            'delivered'    => 'delivered',
            'failed'       => 'failed',
        ),
        'textus' => array(
            'sent'         => 'sent',
            'delivered'    => 'delivered',
            'failed'       => 'failed',
        ),
        'vonage' => array(
            'submitted'    => 'sent',
            'delivered'    => 'delivered',
            'expired'      => 'bounce',
            'failed'       => 'failed',
            'rejected'     => 'failed',
            'unknown'      => 'failed',
        ),
    );

    /**
     * Translate a provider-specific status string into a queue canonical status.
     * Returns an empty string when the provider or status is unknown.
     */
    public static function map(string $provider, string $provider_status): string
    {
        $map = self::get_status_map($provider);
        return isset($map[$provider_status]) ? $map[$provider_status] : '';
    }

    /**
     * Return the full status translation table for a provider.
     *
     * @return array<string,string>
     */
    public static function get_status_map(string $provider): array
    {
        return isset(self::$maps[$provider]) ? self::$maps[$provider] : array();
    }

    /**
     * Check whether a given provider supports delivery-status tracking via webhooks.
     */
    public static function supports_tracking(string $provider): bool
    {
        return isset(self::$maps[$provider]);
    }

    /**
     * Return all providers that support tracking.
     *
     * @return string[]
     */
    public static function get_tracking_providers(): array
    {
        return array_keys(self::$maps);
    }
}
