<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NotifyYa_Emailer {
    private $settings;
    private $logger;

    public function __construct( NotifyYa_Settings $settings, NotifyYa_Logger $logger ) {
        $this->settings = $settings;
        $this->logger   = $logger;
    }

    public function send_notification( $request, $product = null ) {
        $recipient = sanitize_email( $request->email ?? '' );
        if ( empty( $recipient ) ) {
            return new WP_Error( 'notifyya_invalid_recipient', __( 'The notification request does not contain a valid recipient email.', 'notifyya' ) );
        }

        $email_settings = $this->settings->get_email_settings();
        $context        = $this->build_context( $request, $product );

        $result = wp_mail(
            $recipient,
            $this->replace_placeholders( $email_settings['subject'], $context ),
            $this->replace_placeholders( $email_settings['body'], $context ),
            array( 'Content-Type: text/html; charset=UTF-8' )
        );

        if ( ! $result ) {
            return new WP_Error( 'notifyya_mail_failed', __( 'WordPress could not send the notification email.', 'notifyya' ) );
        }

        return true;
    }

    public function send_test_email( $email, $product = null ) {
        $recipient = sanitize_email( $email );
        if ( empty( $recipient ) ) {
            return new WP_Error( 'notifyya_invalid_test_email', __( 'Please enter a valid test email address.', 'notifyya' ) );
        }

        $target_data = array(
            'product_name'      => __( 'Sample product', 'notifyya' ),
            'variation_summary' => __( 'Size: Medium', 'notifyya' ),
            'product_url'       => home_url( '/' ),
            'product_sku'       => 'SAMPLE-SKU',
            'product_id'        => 0,
        );

        if ( $product && is_a( $product, 'WC_Product' ) ) {
            $target_data = NotifyYa_Product_Utils::get_target_data( $product );
        }

        $request = (object) array(
            'email'             => $recipient,
            'product_name'      => $target_data['product_name'],
            'variation_summary' => $target_data['variation_summary'],
            'product_sku'       => $target_data['product_sku'],
            'product_id'        => $target_data['product_id'],
        );

        $context        = $this->build_context( $request, $product, $target_data );
        $email_settings = $this->settings->get_email_settings();
        $result         = wp_mail(
            $recipient,
            $this->replace_placeholders( $email_settings['subject'], $context ),
            $this->replace_placeholders( $email_settings['body'], $context ),
            array( 'Content-Type: text/html; charset=UTF-8' )
        );

        if ( ! $result ) {
            return new WP_Error( 'notifyya_test_mail_failed', __( 'WordPress could not send the test email.', 'notifyya' ) );
        }

        return true;
    }

    public function build_context( $request, $product = null, $fallback_data = array() ) {
        $target_data = $fallback_data;

        if ( $product && is_a( $product, 'WC_Product' ) ) {
            $target_data = NotifyYa_Product_Utils::get_target_data( $product );
        }

        $variation_summary = $target_data['variation_summary'] ?? ( $request->variation_summary ?? '' );
        $product_name      = $target_data['product_name'] ?? ( $request->product_name ?? '' );

        return array(
            '{email}'             => sanitize_email( $request->email ?? '' ),
            '{product_name}'      => wp_strip_all_tags( $product_name ),
            '{variation_summary}' => wp_strip_all_tags( $variation_summary ),
            '{variation_suffix}'  => $variation_summary ? ' - ' . wp_strip_all_tags( $variation_summary ) : '',
            '{product_url}'       => esc_url_raw( $target_data['product_url'] ?? get_permalink( $request->product_id ?? 0 ) ),
            '{site_name}'         => wp_strip_all_tags( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
            '{sku}'               => wp_strip_all_tags( $target_data['product_sku'] ?? ( $request->product_sku ?? '' ) ),
        );
    }

    private function replace_placeholders( $content, $context ) {
        return str_replace( array_keys( $context ), array_values( $context ), $content );
    }
}