<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class NotifyYa_Log_List_Table extends WP_List_Table {
    private $database;

    public function __construct( NotifyYa_Database $database ) {
        $this->database = $database;

        parent::__construct(
            array(
                'singular' => 'notifyya_log',
                'plural'   => 'notifyya_logs',
                'ajax'     => false,
            )
        );
    }

    public function get_columns() {
        return array(
            'created_at' => __( 'Time', 'notifyya' ),
            'level'      => __( 'Level', 'notifyya' ),
            'message'    => __( 'Message', 'notifyya' ),
            'context'    => __( 'Context', 'notifyya' ),
        );
    }

    protected function get_sortable_columns() {
        return array(
            'created_at' => array( 'created_at', true ),
            'level'      => array( 'level', false ),
            'message'    => array( 'message', false ),
        );
    }

    public function no_items() {
        esc_html_e( 'No logs found.', 'notifyya' );
    }

    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $search = sanitize_text_field( wp_unslash( $_REQUEST['s'] ?? '' ) );
        $orderby = sanitize_key( wp_unslash( $_REQUEST['orderby'] ?? 'created_at' ) );
        $order = sanitize_key( wp_unslash( $_REQUEST['order'] ?? 'desc' ) );

        $this->items = $this->database->get_logs(
            array(
                'search'   => $search,
                'orderby'  => $orderby,
                'order'    => $order,
                'page'     => $current_page,
                'per_page' => $per_page,
            )
        );

        $this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

        $this->set_pagination_args(
            array(
                'total_items' => $this->database->count_logs( array( 'search' => $search ) ),
                'per_page'    => $per_page,
            )
        );
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'created_at':
            case 'level':
            case 'message':
                return esc_html( $item->$column_name );
            case 'context':
                return $item->context ? '<code>' . esc_html( $item->context ) . '</code>' : '&mdash;';
            default:
                return '';
        }
    }
}
