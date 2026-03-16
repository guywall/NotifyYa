<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NotifyYa_Plugin {
    private static $instance = null;
    private $settings;
    private $database;
    private $logger;
    private $emailer;
    private $admin;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        load_plugin_textdomain( 'notifyya', false, dirname( plugin_basename( NOTIFYYA_FILE ) ) . '/languages' );

        $this->settings = new NotifyYa_Settings();
        $this->database = new NotifyYa_Database();
        $this->logger   = new NotifyYa_Logger( $this->database );
        $this->emailer  = new NotifyYa_Emailer( $this->settings, $this->logger );
        $this->admin    = new NotifyYa_Admin( $this->database, $this->settings, $this->emailer );

        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'render_missing_woocommerce_notice' ) );
            return;
        }

        new NotifyYa_Frontend( $this->settings );
        new NotifyYa_Ajax( $this->database, $this->settings, $this->logger );
        new NotifyYa_Stock_Watcher( $this->database, $this->emailer, $this->logger );
    }

    public function render_missing_woocommerce_notice() {
        if ( ! current_user_can( $this->settings->get_capability() ) ) {
            return;
        }
        ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e( 'NotifyYa requires WooCommerce to be installed and active.', 'notifyya' ); ?></p>
        </div>
        <?php
    }
}