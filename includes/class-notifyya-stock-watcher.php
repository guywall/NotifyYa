<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NotifyYa_Stock_Watcher {
    private $database;
    private $emailer;
    private $logger;

    public function __construct( NotifyYa_Database $database, NotifyYa_Emailer $emailer, NotifyYa_Logger $logger ) {
        $this->database = $database;
        $this->emailer  = $emailer;
        $this->logger   = $logger;

        add_action( 'woocommerce_product_set_stock_status', array( $this, 'handle_stock_status_change' ), 10, 3 );
        add_action( 'woocommerce_variation_set_stock_status', array( $this, 'handle_stock_status_change' ), 10, 3 );
    }

    public function handle_stock_status_change( $product_id, $stock_status, $product ) {
        if ( 'instock' !== $stock_status ) {
            return;
        }

        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            $product = wc_get_product( $product_id );
        }

        if ( ! $product || ! $product->is_in_stock() ) {
            return;
        }

        $target_data = NotifyYa_Product_Utils::get_target_data( $product );
        if ( empty( $target_data['target_product_id'] ) ) {
            return;
        }

        $requests = $this->database->get_pending_requests_for_target( $target_data['target_product_id'] );
        if ( empty( $requests ) ) {
            return;
        }

        foreach ( $requests as $request ) {
            $result = $this->emailer->send_notification( $request, $product );
            if ( is_wp_error( $result ) ) {
                $this->database->mark_error( $request->id, $result->get_error_message() );
                $this->logger->log(
                    'error',
                    'Failed to send restock notification.',
                    array(
                        'request_id' => $request->id,
                        'email'      => $request->email,
                        'product_id' => $target_data['target_product_id'],
                        'error'      => $result->get_error_message(),
                    )
                );
                continue;
            }

            $this->database->mark_sent( $request->id );
            $this->logger->log(
                'info',
                'Sent restock notification.',
                array(
                    'request_id' => $request->id,
                    'email'      => $request->email,
                    'product_id' => $target_data['target_product_id'],
                )
            );
        }
    }
}
