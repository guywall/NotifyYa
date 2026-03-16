<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NotifyYa_Admin {
    private $database;
    private $settings;
    private $emailer;

    public function __construct( NotifyYa_Database $database, NotifyYa_Settings $settings, NotifyYa_Emailer $emailer ) {
        $this->database = $database;
        $this->settings = $settings;
        $this->emailer  = $emailer;

        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_post_notifyya_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_post_notifyya_save_email_settings', array( $this, 'save_email_settings' ) );
        add_action( 'admin_post_notifyya_export_csv', array( $this, 'export_csv' ) );
        add_action( 'admin_post_notifyya_send_test_email', array( $this, 'send_test_email' ) );
        add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
    }

    public function register_menu() {
        $capability = $this->settings->get_capability();

        add_menu_page(
            __( 'NotifyYa', 'notifyya' ),
            __( 'NotifyYa', 'notifyya' ),
            $capability,
            'notifyya-requests',
            array( $this, 'render_requests_page' ),
            'dashicons-email-alt'
        );

        add_submenu_page( 'notifyya-requests', __( 'Requests', 'notifyya' ), __( 'Requests', 'notifyya' ), $capability, 'notifyya-requests', array( $this, 'render_requests_page' ) );
        add_submenu_page( 'notifyya-requests', __( 'Settings', 'notifyya' ), __( 'Settings', 'notifyya' ), $capability, 'notifyya-settings', array( $this, 'render_settings_page' ) );
        add_submenu_page( 'notifyya-requests', __( 'Email', 'notifyya' ), __( 'Email', 'notifyya' ), $capability, 'notifyya-email', array( $this, 'render_email_page' ) );
        add_submenu_page( 'notifyya-requests', __( 'Logs', 'notifyya' ), __( 'Logs', 'notifyya' ), $capability, 'notifyya-logs', array( $this, 'render_logs_page' ) );
    }

    public function render_requests_page() {
        $this->authorize();

        $list_table = new NotifyYa_Request_List_Table( $this->database );
        $list_table->prepare_items();

        $export_url = wp_nonce_url(
            add_query_arg(
                array(
                    'action'  => 'notifyya_export_csv',
                    'status'  => sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) ),
                    's'       => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
                    'orderby' => sanitize_key( wp_unslash( $_GET['orderby'] ?? 'subscribed_at' ) ),
                    'order'   => sanitize_key( wp_unslash( $_GET['order'] ?? 'desc' ) ),
                ),
                admin_url( 'admin-post.php' )
            ),
            'notifyya_export_csv'
        );
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Back-in-stock requests', 'notifyya' ); ?></h1>
            <a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'notifyya' ); ?></a>
            <hr class="wp-header-end" />
            <form method="get">
                <input type="hidden" name="page" value="notifyya-requests" />
                <?php $list_table->search_box( __( 'Search requests', 'notifyya' ), 'notifyya-request' ); ?>
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
    }

    public function render_settings_page() {
        $this->authorize();
        $settings = $this->settings->get_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'NotifyYa Settings', 'notifyya' ); ?></h1>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'notifyya_save_settings' ); ?>
                <input type="hidden" name="action" value="notifyya_save_settings" />
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Button label', 'notifyya' ); ?></th>
                        <td><input type="text" class="regular-text" name="settings[button_label]" value="<?php echo esc_attr( $settings['button_label'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Modal title', 'notifyya' ); ?></th>
                        <td><input type="text" class="regular-text" name="settings[modal_title]" value="<?php echo esc_attr( $settings['modal_title'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Intro text', 'notifyya' ); ?></th>
                        <td><textarea class="large-text" rows="4" name="settings[form_intro]"><?php echo esc_textarea( $settings['form_intro'] ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Success message', 'notifyya' ); ?></th>
                        <td><input type="text" class="regular-text" name="settings[success_message]" value="<?php echo esc_attr( $settings['success_message'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Built-in anti-spam', 'notifyya' ); ?></th>
                        <td><label><input type="checkbox" name="settings[built_in_spam]" value="1" <?php checked( ! empty( $settings['built_in_spam'] ) ); ?> /> <?php esc_html_e( 'Enable honeypot, timing, and rate-limit checks', 'notifyya' ); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Minimum submit time', 'notifyya' ); ?></th>
                        <td><input type="number" min="1" name="settings[min_submit_seconds]" value="<?php echo esc_attr( $settings['min_submit_seconds'] ); ?>" /> <?php esc_html_e( 'seconds', 'notifyya' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Rate limit window', 'notifyya' ); ?></th>
                        <td><input type="number" min="1" name="settings[throttle_window_mins]" value="<?php echo esc_attr( $settings['throttle_window_mins'] ); ?>" /> <?php esc_html_e( 'minutes', 'notifyya' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Rate limit count', 'notifyya' ); ?></th>
                        <td><input type="number" min="1" name="settings[throttle_limit]" value="<?php echo esc_attr( $settings['throttle_limit'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable reCAPTCHA v2', 'notifyya' ); ?></th>
                        <td><label><input type="checkbox" name="settings[recaptcha_enabled]" value="1" <?php checked( ! empty( $settings['recaptcha_enabled'] ) ); ?> /> <?php esc_html_e( 'Require a Google reCAPTCHA checkbox challenge', 'notifyya' ); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'reCAPTCHA site key', 'notifyya' ); ?></th>
                        <td><input type="text" class="regular-text" name="settings[recaptcha_site_key]" value="<?php echo esc_attr( $settings['recaptcha_site_key'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'reCAPTCHA secret key', 'notifyya' ); ?></th>
                        <td><input type="text" class="regular-text" name="settings[recaptcha_secret_key]" value="<?php echo esc_attr( $settings['recaptcha_secret_key'] ); ?>" /></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Save settings', 'notifyya' ) ); ?>
            </form>
        </div>
        <?php
    }

    public function render_email_page() {
        $this->authorize();
        $email_settings = $this->settings->get_email_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Email template', 'notifyya' ); ?></h1>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'notifyya_save_email_settings' ); ?>
                <input type="hidden" name="action" value="notifyya_save_email_settings" />
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Subject', 'notifyya' ); ?></th>
                        <td><input type="text" class="large-text" name="email_settings[subject]" value="<?php echo esc_attr( $email_settings['subject'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'HTML body', 'notifyya' ); ?></th>
                        <td><textarea class="large-text code" rows="12" name="email_settings[body]"><?php echo esc_textarea( $email_settings['body'] ); ?></textarea></td>
                    </tr>
                </table>
                <p><strong><?php esc_html_e( 'Available placeholders:', 'notifyya' ); ?></strong></p>
                <ul>
                    <?php foreach ( $this->settings->get_placeholders() as $placeholder => $description ) : ?>
                        <li><code><?php echo esc_html( $placeholder ); ?></code> - <?php echo esc_html( $description ); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php submit_button( __( 'Save email template', 'notifyya' ) ); ?>
            </form>

            <hr />

            <h2><?php esc_html_e( 'Send a test email', 'notifyya' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'notifyya_send_test_email' ); ?>
                <input type="hidden" name="action" value="notifyya_send_test_email" />
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Recipient email', 'notifyya' ); ?></th>
                        <td><input type="email" class="regular-text" name="test_email" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Sample product ID', 'notifyya' ); ?></th>
                        <td><input type="number" class="small-text" min="0" name="test_product_id" /> <p class="description"><?php esc_html_e( 'Optional. Leave blank to use generic sample data.', 'notifyya' ); ?></p></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Send test email', 'notifyya' ), 'secondary' ); ?>
            </form>
        </div>
        <?php
    }

    public function render_logs_page() {
        $this->authorize();

        $list_table = new NotifyYa_Log_List_Table( $this->database );
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'NotifyYa logs', 'notifyya' ); ?></h1>
            <form method="get">
                <input type="hidden" name="page" value="notifyya-logs" />
                <?php $list_table->search_box( __( 'Search logs', 'notifyya' ), 'notifyya-logs' ); ?>
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
    }

    public function save_settings() {
        $this->authorize_request( 'notifyya_save_settings' );
        update_option( NotifyYa_Settings::OPTION_KEY, $this->settings->sanitize_settings( wp_unslash( $_POST['settings'] ?? array() ) ) );
        wp_safe_redirect( add_query_arg( array( 'page' => 'notifyya-settings', 'notifyya_notice' => 'settings_saved' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function save_email_settings() {
        $this->authorize_request( 'notifyya_save_email_settings' );
        update_option( NotifyYa_Settings::EMAIL_OPTION_KEY, $this->settings->sanitize_email_settings( wp_unslash( $_POST['email_settings'] ?? array() ) ) );
        wp_safe_redirect( add_query_arg( array( 'page' => 'notifyya-email', 'notifyya_notice' => 'email_saved' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function export_csv() {
        $this->authorize_request( 'notifyya_export_csv' );

        $items = $this->database->get_requests(
            array(
                'search'  => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
                'status'  => sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) ),
                'orderby' => sanitize_key( wp_unslash( $_GET['orderby'] ?? 'subscribed_at' ) ),
                'order'   => sanitize_key( wp_unslash( $_GET['order'] ?? 'desc' ) ),
                'export'  => true,
            )
        );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=notifyya-requests.csv' );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, array( 'ID', 'Email', 'Product', 'Variation', 'SKU', 'Status', 'Subscribed At', 'Sent At', 'Last Error' ) );

        foreach ( $items as $item ) {
            fputcsv(
                $output,
                array(
                    $item->id,
                    $item->email,
                    $item->product_name,
                    $item->variation_summary,
                    $item->product_sku,
                    $item->status,
                    $item->subscribed_at,
                    $item->sent_at,
                    $item->last_error,
                )
            );
        }

        fclose( $output );
        exit;
    }

    public function send_test_email() {
        $this->authorize_request( 'notifyya_send_test_email' );

        $email      = sanitize_email( wp_unslash( $_POST['test_email'] ?? '' ) );
        $product_id = absint( wp_unslash( $_POST['test_product_id'] ?? 0 ) );
        $product    = ( $product_id && function_exists( 'wc_get_product' ) ) ? wc_get_product( $product_id ) : null;
        $result     = $this->emailer->send_test_email( $email, $product );

        $notice = is_wp_error( $result ) ? 'test_failed' : 'test_sent';
        wp_safe_redirect( add_query_arg( array( 'page' => 'notifyya-email', 'notifyya_notice' => $notice ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function render_admin_notices() {
        if ( empty( $_GET['notifyya_notice'] ) ) {
            return;
        }

        $messages = array(
            'settings_saved' => __( 'NotifyYa settings saved.', 'notifyya' ),
            'email_saved'    => __( 'NotifyYa email template saved.', 'notifyya' ),
            'test_sent'      => __( 'Test email sent.', 'notifyya' ),
            'test_failed'    => __( 'The test email could not be sent.', 'notifyya' ),
        );

        $notice = sanitize_key( wp_unslash( $_GET['notifyya_notice'] ?? '' ) );
        if ( empty( $messages[ $notice ] ) ) {
            return;
        }

        $class = 'test_failed' === $notice ? 'notice notice-error' : 'notice notice-success';
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $messages[ $notice ] ) );
    }

    private function authorize() {
        if ( ! current_user_can( $this->settings->get_capability() ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'notifyya' ) );
        }
    }

    private function authorize_request( $nonce_action ) {
        $this->authorize();
        check_admin_referer( $nonce_action );
    }
}
