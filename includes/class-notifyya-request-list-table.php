<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class NotifyYa_Request_List_Table extends WP_List_Table {
    private $database;

    public function __construct( NotifyYa_Database $database ) {
        $this->database = $database;

        parent::__construct(
            array(
                'singular' => 'notifyya_request',
                'plural'   => 'notifyya_requests',
                'ajax'     => false,
            )
        );
    }

    public function get_columns() {
        return array(
            'id'            => __( 'ID', 'notifyya' ),
            'email'         => __( 'Email', 'notifyya' ),
            'product_name'  => __( 'Product', 'notifyya' ),
            'status'        => __( 'Status', 'notifyya' ),
            'subscribed_at' => __( 'Signed up', 'notifyya' ),
            'sent_at'       => __( 'Sent', 'notifyya' ),
            'last_error'    => __( 'Last error', 'notifyya' ),
        );
    }

    protected function get_sortable_columns() {
        return array(
            'id'            => array( 'id', true ),
            'email'         => array( 'email', false ),
            'product_name'  => array( 'product_name', false ),
            'status'        => array( 'status', false ),
            'subscribed_at' => array( 'subscribed_at', true ),
            'sent_at'       => array( 'sent_at', false ),
        );
    }

    public function no_items() {
        esc_html_e( 'No notification requests found.', 'notifyya' );
    }

    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $search = sanitize_text_field( wp_unslash( $_REQUEST['s'] ?? '' ) );
        $status = sanitize_text_field( wp_unslash( $_REQUEST['status'] ?? '' ) );
        $orderby = sanitize_key( wp_unslash( $_REQUEST['orderby'] ?? 'subscribed_at' ) );
        $order = sanitize_key( wp_unslash( $_REQUEST['order'] ?? 'desc' ) );

        $this->items = $this->database->get_requests(
            array(
                'search'   => $search,
                'status'   => $status,
                'orderby'  => $orderby,
                'order'    => $order,
                'page'     => $current_page,
                'per_page' => $per_page,
            )
        );

        $this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

        $this->set_pagination_args(
            array(
                'total_items' => $this->database->count_requests(
                    array(
                        'search' => $search,
                        'status' => $status,
                    )
                ),
                'per_page'    => $per_page,
            )
        );
    }

    protected function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }
        $current_status = sanitize_text_field( wp_unslash( $_REQUEST['status'] ?? '' ) );
        ?>
        <div class="alignleft actions">
            <label class="screen-reader-text" for="filter-by-status"><?php esc_html_e( 'Filter by status', 'notifyya' ); ?></label>
            <select name="status" id="filter-by-status">
                <option value=""><?php esc_html_e( 'All statuses', 'notifyya' ); ?></option>
                <option value="pending" <?php selected( $current_status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'notifyya' ); ?></option>
                <option value="sent" <?php selected( $current_status, 'sent' ); ?>><?php esc_html_e( 'Sent', 'notifyya' ); ?></option>
            </select>
            <?php submit_button( __( 'Filter', 'notifyya' ), '', 'filter_action', false ); ?>
        </div>
        <?php
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id':
                return absint( $item->id );
            case 'email':
                return esc_html( $item->email );
            case 'status':
                return esc_html( ucfirst( $item->status ) );
            case 'subscribed_at':
            case 'sent_at':
                return $item->$column_name ? esc_html( $item->$column_name ) : '&mdash;';
            case 'last_error':
                return $item->last_error ? esc_html( $item->last_error ) : '&mdash;';
            default:
                return '';
        }
    }

    public function column_product_name( $item ) {
        $product_label = esc_html( $item->product_name );

        if ( $item->variation_summary ) {
            $product_label .= '<br /><small>' . esc_html( $item->variation_summary ) . '</small>';
        }

        if ( $item->product_sku ) {
            $product_label .= '<br /><small>' . sprintf( esc_html__( 'SKU: %s', 'notifyya' ), esc_html( $item->product_sku ) ) . '</small>';
        }

        return $product_label;
    }
}
