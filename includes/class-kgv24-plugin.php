<?php

if (!defined('ABSPATH')) {
    exit;
}

final class KGV24_Plugin
{
    private static $instance = null;

    private $settings;

    private $shortcodes;

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function init(): void
    {
        $client = new KGV24_API_Client();
        $this->settings = new KGV24_Settings($client);
        $this->shortcodes = new KGV24_Shortcodes($client);

        $this->settings->init();
        $this->shortcodes->init();
    }
}
