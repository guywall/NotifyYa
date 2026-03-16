<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NotifyYa_Database {
    private $wpdb;
    private $request_table;
    private $log_table;

    public function __construct() {
        global $wpdb;

        $this->wpdb          = $wpdb;
        $this->request_table = $wpdb->prefix . 'notifyya_requests';
        $this->log_table     = $wpdb->prefix . 'notifyya_logs';
    }

    public function create_tables() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $this->wpdb->get_charset_collate();

        $request_sql = "CREATE TABLE {$this->request_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email varchar(190) NOT NULL,
            email_hash char(32) NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
            target_product_id bigint(20) unsigned NOT NULL,
            product_name varchar(255) NOT NULL DEFAULT '',
            product_sku varchar(100) NOT NULL DEFAULT '',
            variation_summary varchar(255) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'pending',
            source_url varchar(255) NOT NULL DEFAULT '',
            user_ip varchar(45) NOT NULL DEFAULT '',
            user_agent varchar(255) NOT NULL DEFAULT '',
            last_error text NULL,
            subscribed_at datetime NOT NULL,
            sent_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY pending_subscription (email_hash,target_product_id,status),
            KEY status (status),
            KEY product_id (product_id),
            KEY target_product_id (target_product_id),
            KEY email (email),
            KEY subscribed_at (subscribed_at)
        ) {$charset_collate};";

        $log_sql = "CREATE TABLE {$this->log_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL DEFAULT 'info',
            message varchar(255) NOT NULL,
            context longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta( $request_sql );
        dbDelta( $log_sql );
    }

    public function normalize_email( $email ) {
        return sanitize_email( strtolower( trim( (string) $email ) ) );
    }

    public function find_pending_request( $email, $target_product_id ) {
        $email = $this->normalize_email( $email );

        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->request_table} WHERE email_hash = %s AND target_product_id = %d AND status = 'pending' LIMIT 1",
                md5( $email ),
                $target_product_id
            )
        );
    }

    public function create_or_get_request( $data ) {
        $email = $this->normalize_email( $data['email'] ?? '' );
        if ( empty( $email ) ) {
            return new WP_Error( 'notifyya_invalid_email', __( 'Please enter a valid email address.', 'notifyya' ) );
        }

        $target_product_id = absint( $data['target_product_id'] ?? 0 );
        if ( ! $target_product_id ) {
            return new WP_Error( 'notifyya_invalid_target', __( 'Please choose a valid product.', 'notifyya' ) );
        }

        $existing = $this->find_pending_request( $email, $target_product_id );
        if ( $existing ) {
            return array(
                'created' => false,
                'request' => $existing,
            );
        }

        $inserted = $this->wpdb->insert(
            $this->request_table,
            array(
                'email'             => $email,
                'email_hash'        => md5( $email ),
                'product_id'        => absint( $data['product_id'] ?? 0 ),
                'variation_id'      => absint( $data['variation_id'] ?? 0 ),
                'target_product_id' => $target_product_id,
                'product_name'      => sanitize_text_field( $data['product_name'] ?? '' ),
                'product_sku'       => sanitize_text_field( $data['product_sku'] ?? '' ),
                'variation_summary' => sanitize_text_field( $data['variation_summary'] ?? '' ),
                'status'            => 'pending',
                'source_url'        => esc_url_raw( $data['source_url'] ?? '' ),
                'user_ip'           => sanitize_text_field( $data['user_ip'] ?? '' ),
                'user_agent'        => sanitize_text_field( $data['user_agent'] ?? '' ),
                'last_error'        => '',
                'subscribed_at'     => current_time( 'mysql' ),
            ),
            array(
                '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            )
        );

        if ( false === $inserted ) {
            $existing = $this->find_pending_request( $email, $target_product_id );
            if ( $existing ) {
                return array(
                    'created' => false,
                    'request' => $existing,
                );
            }

            return new WP_Error( 'notifyya_insert_failed', __( 'We could not save your notification request. Please try again.', 'notifyya' ) );
        }

        return array(
            'created' => true,
            'request' => $this->get_request( $this->wpdb->insert_id ),
        );
    }

    public function get_request( $request_id ) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->request_table} WHERE id = %d LIMIT 1",
                $request_id
            )
        );
    }

    public function get_pending_requests_for_target( $target_product_id ) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->request_table} WHERE target_product_id = %d AND status = 'pending' ORDER BY subscribed_at ASC",
                $target_product_id
            )
        );
    }

    public function mark_sent( $request_id ) {
        return $this->wpdb->update(
            $this->request_table,
            array(
                'status'     => 'sent',
                'last_error' => '',
                'sent_at'    => current_time( 'mysql' ),
            ),
            array( 'id' => absint( $request_id ) ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    public function mark_error( $request_id, $message ) {
        return $this->wpdb->update(
            $this->request_table,
            array(
                'last_error' => sanitize_textarea_field( $message ),
            ),
            array( 'id' => absint( $request_id ) ),
            array( '%s' ),
            array( '%d' )
        );
    }

    public function get_requests( $args = array() ) {
        $args = wp_parse_args(
            $args,
            array(
                'search'   => '',
                'status'   => '',
                'orderby'  => 'subscribed_at',
                'order'    => 'DESC',
                'page'     => 1,
                'per_page' => 20,
                'export'   => false,
            )
        );

        $where  = array( '1=1' );
        $params = array();

        if ( in_array( $args['status'], array( 'pending', 'sent' ), true ) ) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }

        if ( '' !== $args['search'] ) {
            $search_like = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
            $search_sql  = '(email LIKE %s OR product_name LIKE %s OR variation_summary LIKE %s OR product_sku LIKE %s';
            $params      = array_merge( $params, array( $search_like, $search_like, $search_like, $search_like ) );

            if ( is_numeric( $args['search'] ) ) {
                $search_sql .= ' OR product_id = %d OR variation_id = %d';
                $params[]    = absint( $args['search'] );
                $params[]    = absint( $args['search'] );
            }

            $search_sql .= ')';
            $where[]     = $search_sql;
        }

        $orderby_map = array(
            'id'            => 'id',
            'email'         => 'email',
            'product_name'  => 'product_name',
            'status'        => 'status',
            'subscribed_at' => 'subscribed_at',
            'sent_at'       => 'sent_at',
        );
        $orderby     = $orderby_map[ $args['orderby'] ] ?? 'subscribed_at';
        $order       = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
        $sql         = "SELECT * FROM {$this->request_table} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$orderby} {$order}";

        if ( ! $args['export'] ) {
            $offset = max( 0, ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] ) );
            $sql   .= $this->wpdb->prepare( ' LIMIT %d OFFSET %d', absint( $args['per_page'] ), $offset );
        }

        if ( ! empty( $params ) ) {
            $sql = $this->wpdb->prepare( $sql, $params );
        }

        return $this->wpdb->get_results( $sql );
    }

    public function count_requests( $args = array() ) {
        $args = wp_parse_args(
            $args,
            array(
                'search' => '',
                'status' => '',
            )
        );

        $where  = array( '1=1' );
        $params = array();

        if ( in_array( $args['status'], array( 'pending', 'sent' ), true ) ) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }

        if ( '' !== $args['search'] ) {
            $search_like = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
            $search_sql  = '(email LIKE %s OR product_name LIKE %s OR variation_summary LIKE %s OR product_sku LIKE %s';
            $params      = array_merge( $params, array( $search_like, $search_like, $search_like, $search_like ) );

            if ( is_numeric( $args['search'] ) ) {
                $search_sql .= ' OR product_id = %d OR variation_id = %d';
                $params[]    = absint( $args['search'] );
                $params[]    = absint( $args['search'] );
            }

            $search_sql .= ')';
            $where[]     = $search_sql;
        }

        $sql = "SELECT COUNT(*) FROM {$this->request_table} WHERE " . implode( ' AND ', $where );
        if ( ! empty( $params ) ) {
            $sql = $this->wpdb->prepare( $sql, $params );
        }

        return (int) $this->wpdb->get_var( $sql );
    }

    public function get_logs( $args = array() ) {
        $args = wp_parse_args(
            $args,
            array(
                'search'   => '',
                'orderby'  => 'created_at',
                'order'    => 'DESC',
                'page'     => 1,
                'per_page' => 20,
            )
        );

        $where  = array( '1=1' );
        $params = array();

        if ( '' !== $args['search'] ) {
            $search_like = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
            $where[]     = '(level LIKE %s OR message LIKE %s OR context LIKE %s)';
            $params      = array_merge( $params, array( $search_like, $search_like, $search_like ) );
        }

        $orderby_map = array(
            'level'      => 'level',
            'message'    => 'message',
            'created_at' => 'created_at',
        );
        $orderby     = $orderby_map[ $args['orderby'] ] ?? 'created_at';
        $order       = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
        $offset      = max( 0, ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] ) );
        $sql         = "SELECT * FROM {$this->log_table} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$orderby} {$order}";
        $sql        .= $this->wpdb->prepare( ' LIMIT %d OFFSET %d', absint( $args['per_page'] ), $offset );

        if ( ! empty( $params ) ) {
            $sql = $this->wpdb->prepare( $sql, $params );
        }

        return $this->wpdb->get_results( $sql );
    }

    public function count_logs( $args = array() ) {
        $args = wp_parse_args(
            $args,
            array(
                'search' => '',
            )
        );

        $where  = array( '1=1' );
        $params = array();

        if ( '' !== $args['search'] ) {
            $search_like = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
            $where[]     = '(level LIKE %s OR message LIKE %s OR context LIKE %s)';
            $params      = array_merge( $params, array( $search_like, $search_like, $search_like ) );
        }

        $sql = "SELECT COUNT(*) FROM {$this->log_table} WHERE " . implode( ' AND ', $where );
        if ( ! empty( $params ) ) {
            $sql = $this->wpdb->prepare( $sql, $params );
        }

        return (int) $this->wpdb->get_var( $sql );
    }

    public function insert_log( $level, $message, $context = '' ) {
        return $this->wpdb->insert(
            $this->log_table,
            array(
                'level'      => sanitize_key( $level ),
                'message'    => sanitize_text_field( $message ),
                'context'    => $context,
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s' )
        );
    }
}