<?php

namespace MNEM;

defined('ABSPATH') || exit;

/**
 * Maps provider-specific SMS delivery statuses to the queue's canonical statuses.
 *
 * Queue canonical statuses: pending, sent, delivered, bounce, failed, rejected
 *
 * `failed` and `rejected` are distinct terminal statuses:
 * - `failed`   the number is invalid or the handset could not be reached (technical failure).
 * - `rejected` the number is valid and reachable, but the message was blocked by the
 *              provider account owner or by the mobile user. Rejected messages must not be retried.
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
            // TextMagic separates technical failures from user/account blocks:
            // 'e'/'f' = invalid number or unreachable network, 'r' = blocked by account owner or mobile user.
            'e'            => 'failed',
            'f'            => 'failed',
            'r'            => 'rejected',
            'b'            => 'bounce',
            'submitted'    => 'sent',
            'sent'         => 'sent',
            'delivered'    => 'delivered',
            'failed'       => 'failed',
            'rejected'     => 'rejected',
            'bounced'      => 'bounce',
            'undelivered'  => 'bounce',
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
     * Human-readable labels for raw provider status codes, keyed by provider.
     *
     * Only providers whose raw codes are not already self-explanatory words
     * (e.g. TextMagic's single-letter codes) need explicit entries here;
     * {@see get_provider_display_name()} falls back to the canonical status
     * map for every other provider/code combination.
     *
     * @var array<string,array<string,string>>
     */
    private static array $display_names = array(
        'textmagic' => array(
            'q' => 'Queued',
            's' => 'Sent',
            'd' => 'Delivered',
            'e' => 'Failed',
            'f' => 'Failed',
            'r' => 'Rejected',
            'b' => 'Bounced',
        ),
    );

    /**
     * Translate a provider-specific raw status code into a human-readable
     * display name for admin UI purposes (e.g. TextMagic 'r' => 'Rejected').
     *
     * Falls back to the canonical status bucket (via map()) humanised as
     * title case when no explicit display entry exists, and finally to a
     * title-cased version of the raw status itself. Returns an empty string
     * when the provider or status is empty.
     */
    public static function get_provider_display_name(string $provider, string $provider_status): string
    {
        $provider = strtolower(trim($provider));
        $provider_status = trim($provider_status);

        if ($provider === '' || $provider_status === '') {
            return '';
        }

        $display_map = isset(self::$display_names[$provider]) ? self::$display_names[$provider] : array();

        if (isset($display_map[$provider_status])) {
            return $display_map[$provider_status];
        }

        $normalised = strtolower($provider_status);
        if (isset($display_map[$normalised])) {
            return $display_map[$normalised];
        }

        $canonical = self::map($provider, $provider_status);
        if ($canonical !== '') {
            return $canonical === 'bounce' ? 'Bounced' : ucwords(str_replace('_', ' ', $canonical));
        }

        return ucwords(str_replace(array('_', '-'), ' ', $provider_status));
    }

    /**
     * Translate a provider-specific status string into a queue canonical status.
     * Returns an empty string when the provider or status is unknown.
     */
    public static function map(string $provider, string $provider_status): string
    {
        $provider = strtolower(trim($provider));
        $provider_status = trim($provider_status);
        $map = self::get_status_map($provider);

        if (isset($map[$provider_status])) {
            return $map[$provider_status];
        }

        $normalised = strtolower($provider_status);
        return isset($map[$normalised]) ? $map[$normalised] : '';
    }

    public static function map_textmagic_status(string $provider_status): string
    {
        return self::map('textmagic', $provider_status);
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
