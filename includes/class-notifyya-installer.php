<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NotifyYa_Installer {
    public static function activate() {
        $settings = new NotifyYa_Settings();
        $database = new NotifyYa_Database();

        $database->create_tables();

        if ( ! get_option( NotifyYa_Settings::OPTION_KEY ) ) {
            add_option( NotifyYa_Settings::OPTION_KEY, $settings->get_default_settings() );
        }

        if ( ! get_option( NotifyYa_Settings::EMAIL_OPTION_KEY ) ) {
            add_option( NotifyYa_Settings::EMAIL_OPTION_KEY, $settings->get_default_email_settings() );
        }
    }
}
