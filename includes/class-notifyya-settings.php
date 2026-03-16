<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NotifyYa_Settings {
    const OPTION_KEY       = 'notifyya_settings';
    const EMAIL_OPTION_KEY = 'notifyya_email_settings';

    public function get_settings() {
        return wp_parse_args( get_option( self::OPTION_KEY, array() ), $this->get_default_settings() );
    }

    public function get_email_settings() {
        return wp_parse_args( get_option( self::EMAIL_OPTION_KEY, array() ), $this->get_default_email_settings() );
    }

    public function get_default_settings() {
        return array(
            'built_in_spam'        => 1,
            'recaptcha_enabled'    => 0,
            'recaptcha_site_key'   => '',
            'recaptcha_secret_key' => '',
            'button_label'         => __( 'Notify me when back in stock', 'notifyya' ),
            'modal_title'          => __( 'Back-in-stock alert', 'notifyya' ),
            'form_intro'           => __( 'Enter your email address and we will send you a message as soon as this item is available again.', 'notifyya' ),
            'success_message'      => __( 'Thanks. If this item is restocked, we will email you once.', 'notifyya' ),
            'min_submit_seconds'   => 3,
            'throttle_window_mins' => 15,
            'throttle_limit'       => 5,
        );
    }

    public function get_default_email_settings() {
        return array(
            'subject' => __( '{product_name} is back in stock', 'notifyya' ),
            'body'    => wp_kses_post(
                '<p>' . esc_html__( 'Good news, {product_name}{variation_suffix} is available again.', 'notifyya' ) . '</p>' .
                '<p><a href="{product_url}">' . esc_html__( 'View the product', 'notifyya' ) . '</a></p>' .
                '<p>' . esc_html__( 'You are receiving this email because you asked to be notified when it returned to stock.', 'notifyya' ) . '</p>'
            ),
        );
    }

    public function sanitize_settings( $input ) {
        $defaults = $this->get_default_settings();

        return array(
            'built_in_spam'        => empty( $input['built_in_spam'] ) ? 0 : 1,
            'recaptcha_enabled'    => empty( $input['recaptcha_enabled'] ) ? 0 : 1,
            'recaptcha_site_key'   => sanitize_text_field( $input['recaptcha_site_key'] ?? '' ),
            'recaptcha_secret_key' => sanitize_text_field( $input['recaptcha_secret_key'] ?? '' ),
            'button_label'         => sanitize_text_field( $input['button_label'] ?? $defaults['button_label'] ),
            'modal_title'          => sanitize_text_field( $input['modal_title'] ?? $defaults['modal_title'] ),
            'form_intro'           => wp_kses_post( $input['form_intro'] ?? $defaults['form_intro'] ),
            'success_message'      => sanitize_text_field( $input['success_message'] ?? $defaults['success_message'] ),
            'min_submit_seconds'   => max( 1, absint( $input['min_submit_seconds'] ?? $defaults['min_submit_seconds'] ) ),
            'throttle_window_mins' => max( 1, absint( $input['throttle_window_mins'] ?? $defaults['throttle_window_mins'] ) ),
            'throttle_limit'       => max( 1, absint( $input['throttle_limit'] ?? $defaults['throttle_limit'] ) ),
        );
    }

    public function sanitize_email_settings( $input ) {
        $defaults = $this->get_default_email_settings();

        return array(
            'subject' => sanitize_text_field( $input['subject'] ?? $defaults['subject'] ),
            'body'    => wp_kses_post( $input['body'] ?? $defaults['body'] ),
        );
    }

    public function get_placeholders() {
        return array(
            '{email}'             => __( 'Subscriber email address', 'notifyya' ),
            '{product_name}'      => __( 'Product name', 'notifyya' ),
            '{variation_summary}' => __( 'Variation summary, if any', 'notifyya' ),
            '{variation_suffix}'  => __( 'Variation summary prefixed with a space and dash when present', 'notifyya' ),
            '{product_url}'       => __( 'Product URL', 'notifyya' ),
            '{site_name}'         => __( 'Website name', 'notifyya' ),
            '{sku}'               => __( 'Product SKU', 'notifyya' ),
        );
    }

    public function get_capability() {
        $default_capability = class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';
        return apply_filters( 'notifyya_manage_capability', $default_capability );
    }
}