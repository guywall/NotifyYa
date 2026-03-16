<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NotifyYa_Ajax {
    private $database;
    private $settings;
    private $logger;

    public function __construct( NotifyYa_Database $database, NotifyYa_Settings $settings, NotifyYa_Logger $logger ) {
        $this->database = $database;
        $this->settings = $settings;
        $this->logger   = $logger;

        add_action( 'wp_ajax_notifyya_subscribe', array( $this, 'handle_subscribe' ) );
        add_action( 'wp_ajax_nopriv_notifyya_subscribe', array( $this, 'handle_subscribe' ) );
    }

    public function handle_subscribe() {
        check_ajax_referer( 'notifyya_subscribe', 'nonce' );

        $email = $this->database->normalize_email( wp_unslash( $_POST['email'] ?? '' ) );
        if ( empty( $email ) ) {
            wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'notifyya' ) ), 400 );
        }

        $settings   = $this->settings->get_settings();
        $spam_check = $this->apply_spam_protection( $settings );
        if ( is_wp_error( $spam_check ) ) {
            $this->logger->log(
                'warning',
                'Blocked spam-like back-in-stock request.',
                array(
                    'email' => $email,
                    'code'  => $spam_check->get_error_code(),
                )
            );

            wp_send_json_error( array( 'message' => $spam_check->get_error_message() ), 400 );
        }

        if ( ! empty( $settings['recaptcha_enabled'] ) ) {
            $recaptcha_check = $this->verify_recaptcha( $settings );
            if ( is_wp_error( $recaptcha_check ) ) {
                wp_send_json_error( array( 'message' => $recaptcha_check->get_error_message() ), 400 );
            }
        }

        $product = $this->resolve_product_target();
        if ( is_wp_error( $product ) ) {
            wp_send_json_error( array( 'message' => $product->get_error_message() ), 400 );
        }

        if ( $product->is_in_stock() ) {
            wp_send_json_error( array( 'message' => __( 'This product is already in stock.', 'notifyya' ) ), 400 );
        }

        $target_data = NotifyYa_Product_Utils::get_target_data( $product );
        $result      = $this->database->create_or_get_request(
            array(
                'email'             => $email,
                'product_id'        => $target_data['product_id'],
                'variation_id'      => $target_data['variation_id'],
                'target_product_id' => $target_data['target_product_id'],
                'product_name'      => $target_data['product_name'],
                'product_sku'       => $target_data['product_sku'],
                'variation_summary' => $target_data['variation_summary'],
                'source_url'        => esc_url_raw( wp_unslash( $_POST['source_url'] ?? '' ) ),
                'user_ip'           => $this->get_user_ip(),
                'user_agent'        => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
            )
        );

        if ( is_wp_error( $result ) ) {
            $this->logger->log(
                'error',
                'Could not save notification request.',
                array(
                    'email'   => $email,
                    'product' => $target_data['target_product_id'],
                    'error'   => $result->get_error_message(),
                )
            );

            wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
        }

        $this->logger->log(
            'info',
            'Saved notification request.',
            array(
                'request_id' => $result['request']->id ?? 0,
                'email'      => $email,
                'created'    => ! empty( $result['created'] ),
                'target'     => $target_data['target_product_id'],
            )
        );

        wp_send_json_success( array( 'message' => $settings['success_message'] ) );
    }

    private function resolve_product_target() {
        $product_id   = absint( wp_unslash( $_POST['product_id'] ?? 0 ) );
        $variation_id = absint( wp_unslash( $_POST['variation_id'] ?? 0 ) );

        if ( $variation_id ) {
            $product = wc_get_product( $variation_id );
            if ( ! $product || ! $product->is_type( 'variation' ) || $product->get_parent_id() !== $product_id ) {
                return new WP_Error( 'notifyya_invalid_variation', __( 'Please select a valid variation.', 'notifyya' ) );
            }

            return $product;
        }

        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->is_type( 'simple' ) ) {
            return new WP_Error( 'notifyya_invalid_product', __( 'Please choose a valid out-of-stock product.', 'notifyya' ) );
        }

        return $product;
    }

    private function apply_spam_protection( $settings ) {
        if ( empty( $settings['built_in_spam'] ) ) {
            return true;
        }

        if ( ! empty( $_POST['company'] ) ) {
            return new WP_Error( 'notifyya_honeypot', __( 'We could not validate your request.', 'notifyya' ) );
        }

        $rendered_at = absint( wp_unslash( $_POST['rendered_at'] ?? 0 ) );
        if ( ! $rendered_at || ( time() - $rendered_at ) < absint( $settings['min_submit_seconds'] ) ) {
            return new WP_Error( 'notifyya_too_fast', __( 'Please wait a moment and try again.', 'notifyya' ) );
        }

        $key     = 'notifyya_rate_' . md5( $this->get_user_ip() );
        $attempt = get_transient( $key );
        $attempt = is_array( $attempt ) ? $attempt : array( 'count' => 0 );

        if ( $attempt['count'] >= absint( $settings['throttle_limit'] ) ) {
            return new WP_Error( 'notifyya_throttled', __( 'Too many requests were sent from your address. Please try again later.', 'notifyya' ) );
        }

        $attempt['count']++;
        set_transient( $key, $attempt, MINUTE_IN_SECONDS * absint( $settings['throttle_window_mins'] ) );

        return true;
    }

    private function verify_recaptcha( $settings ) {
        if ( empty( $settings['recaptcha_site_key'] ) || empty( $settings['recaptcha_secret_key'] ) ) {
            return new WP_Error( 'notifyya_recaptcha_not_configured', __( 'reCAPTCHA is enabled but not fully configured.', 'notifyya' ) );
        }

        $response = sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ?? '' ) );
        if ( empty( $response ) ) {
            return new WP_Error( 'notifyya_recaptcha_missing', __( 'Please complete the reCAPTCHA challenge.', 'notifyya' ) );
        }

        $remote = wp_remote_post(
            'https://www.google.com/recaptcha/api/siteverify',
            array(
                'timeout' => 10,
                'body'    => array(
                    'secret'   => $settings['recaptcha_secret_key'],
                    'response' => $response,
                    'remoteip' => $this->get_user_ip(),
                ),
            )
        );

        if ( is_wp_error( $remote ) ) {
            return new WP_Error( 'notifyya_recaptcha_failed', __( 'We could not verify the reCAPTCHA challenge. Please try again.', 'notifyya' ) );
        }

        $body = json_decode( wp_remote_retrieve_body( $remote ), true );
        if ( empty( $body['success'] ) ) {
            return new WP_Error( 'notifyya_recaptcha_invalid', __( 'The reCAPTCHA challenge was not accepted.', 'notifyya' ) );
        }

        return true;
    }

    private function get_user_ip() {
        $ip = wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' );
        $ip = filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';

        return (string) $ip;
    }
}
