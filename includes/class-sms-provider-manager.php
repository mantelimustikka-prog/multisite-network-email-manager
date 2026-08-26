<?php

namespace MNEM;

defined('ABSPATH') || exit;

class SmsProviderManager
{
    private const PROVIDERS = array(
        'textmagic'     => 'TextMagic',
        'simpletexting' => 'SimpleTexting',
        'messagedesk'   => 'MessageDesk',
        'eztexting'     => 'EZ Texting',
        'salesmsg'      => 'Salesmsg',
        'textline'      => 'Textline',
        'slicktext'     => 'SlickText',
        'textedly'      => 'Textedly',
        'textus'        => 'TextUS',
        'twilio'        => 'Twilio',
        'clicksend'     => 'ClickSend',
    );

    /**
     * Map of provider key → fully-qualified class name.
     *
     * @var array<string,string>
     */
    private const PROVIDER_CLASSES = array(
        'textmagic'     => \MNEM\Providers\SmsTextmagic::class,
        'simpletexting' => \MNEM\Providers\SmsSimpletexting::class,
        'messagedesk'   => \MNEM\Providers\SmsMessagedesk::class,
        'eztexting'     => \MNEM\Providers\SmsEztexting::class,
        'salesmsg'      => \MNEM\Providers\SmsSalesmsg::class,
        'textline'      => \MNEM\Providers\SmsTextline::class,
        'slicktext'     => \MNEM\Providers\SmsSlicktext::class,
        'textedly'      => \MNEM\Providers\SmsTextedly::class,
        'textus'        => \MNEM\Providers\SmsTextus::class,
        'twilio'        => \MNEM\Providers\SmsTwilio::class,
        'clicksend'     => \MNEM\Providers\SmsClicksend::class,
    );

    /**
     * Get a provider instance by key, configured with the stored credentials.
     *
     * @return \MNEM\Interfaces\SmsProviderInterface|null
     */
    public static function get_provider(string $name): ?\MNEM\Interfaces\SmsProviderInterface
    {
        $name = sanitize_key($name);
        if (!isset(self::PROVIDER_CLASSES[$name])) {
            return null;
        }

        $class  = self::PROVIDER_CLASSES[$name];
        $config = SmsSettings::get_provider_config($name);

        return new $class($config);
    }

    /**
     * Return array of provider_key => display_name pairs.
     *
     * @return array<string,string>
     */
    public static function get_available_providers(): array
    {
        return self::PROVIDERS;
    }

    /**
     * Return the config schema for a specific provider.
     *
     * @return array<int, array{key: string, label: string, type: string}>
     */
    public static function get_provider_schema(string $name): array
    {
        $provider = self::get_provider($name);
        if ($provider === null) {
            return array();
        }
        return $provider->get_config_schema();
    }
}
