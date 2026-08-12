<?php

class MNEM_Logger
{
    const OPTION_NAME = 'mnem_logs';
    const MAX_ENTRIES = 200;

    protected $settings;

    public function __construct(MNEM_Settings $settings = null)
    {
        $this->settings = $settings ?: new MNEM_Settings(self::OPTION_NAME, array());
    }

    public function log($level, $message, array $context = array())
    {
        $entries = $this->settings->all();
        $entries[] = array(
            'time' => gmdate('c'),
            'level' => (string) $level,
            'message' => $this->scrub($message),
            'context' => $this->scrub($context),
        );

        if (count($entries) > self::MAX_ENTRIES) {
            $entries = array_slice($entries, -1 * self::MAX_ENTRIES);
        }

        $this->settings->replace($entries);

        return end($entries);
    }

    public function all()
    {
        return array_reverse($this->settings->all());
    }

    public function scrub($value, $key = '')
    {
        if (is_array($value)) {
            $scrubbed = array();
            foreach ($value as $nested_key => $nested_value) {
                $scrubbed[$nested_key] = $this->scrub($nested_value, (string) $nested_key);
            }

            return $scrubbed;
        }

        if (is_string($key) && preg_match('/pass|secret|token|key|authorization/i', $key)) {
            return '[redacted]';
        }

        if (! is_string($value)) {
            return $value;
        }

        $patterns = array(
            '/(password\s*[=:]\s*)([^\s,;]+)/i',
            '/(token\s*[=:]\s*)([^\s,;]+)/i',
            '/(authorization\s*:\s*bearer\s+)([^\s,;]+)/i',
        );

        foreach ($patterns as $pattern) {
            $value = preg_replace($pattern, '$1[redacted]', $value);
        }

        return $value;
    }
}
