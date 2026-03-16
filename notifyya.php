<?php
/**
 * Plugin Name: NotifyYa
 * Description: Back-in-stock notifications for WooCommerce products.
 * Version: 0.1.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Codex
 * Text Domain: notifyya
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NOTIFYYA_VERSION', '0.1.2' );
define( 'NOTIFYYA_FILE', __FILE__ );
define( 'NOTIFYYA_DIR', plugin_dir_path( __FILE__ ) );
define( 'NOTIFYYA_URL', plugin_dir_url( __FILE__ ) );

require_once NOTIFYYA_DIR . 'includes/class-notifyya-settings.php';
require_once NOTIFYYA_DIR . 'includes/class-notifyya-installer.php';
require_once NOTIFYYA_DIR . 'includes/class-notifyya-database.php';
require_once NOTIFYYA_DIR . 'includes/class-notifyya-logger.php';
require_once NOTIFYYA_DIR . 'includes/class-notifyya-product-utils.php';
require_once NOTIFYYA_DIR . 'includes/class-notifyya-emailer.php';
require_once NOTIFYYA_DIR . 'includes/class-notifyya-frontend.php';
require_once NOTIFYYA_DIR . 'includes/class-notifyya-ajax.php';
require_once NOTIFYYA_DIR . 'includes/class-notifyya-stock-watcher.php';
require_once NOTIFYYA_DIR . 'includes/class-notifyya-request-list-table.php';
require_once NOTIFYYA_DIR . 'includes/class-notifyya-log-list-table.php';
require_once NOTIFYYA_DIR . 'includes/class-notifyya-admin.php';
require_once NOTIFYYA_DIR . 'includes/class-notifyya-plugin.php';

register_activation_hook( __FILE__, array( 'NotifyYa_Installer', 'activate' ) );

add_action(
    'plugins_loaded',
    static function () {
        NotifyYa_Plugin::instance();
    },
    20
);
