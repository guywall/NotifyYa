<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NotifyYa_Logger {
    private $database;

    public function __construct( NotifyYa_Database $database ) {
        $this->database = $database;
    }

    public function log( $level, $message, $context = array() ) {
        $this->database->insert_log(
            $level,
            $message,
            empty( $context ) ? '' : wp_json_encode( $context )
        );
    }
}
