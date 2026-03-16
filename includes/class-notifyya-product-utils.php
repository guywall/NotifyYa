<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NotifyYa_Product_Utils {
    public static function get_target_data( $product ) {
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return array();
        }

        $product_id        = $product->get_id();
        $variation_id      = 0;
        $target_product_id = $product_id;
        $product_name      = $product->get_name();
        $variation_summary = '';
        $product_url       = get_permalink( $product_id );
        $product_sku       = $product->get_sku();

        if ( $product->is_type( 'variation' ) ) {
            $variation_id      = $product->get_id();
            $product_id        = $product->get_parent_id();
            $target_product_id = $variation_id;
            $parent_product    = wc_get_product( $product_id );
            $product_name      = $parent_product ? $parent_product->get_name() : $product->get_name();
            $product_url       = get_permalink( $product_id );
            $product_sku       = $product->get_sku() ? $product->get_sku() : ( $parent_product ? $parent_product->get_sku() : '' );
            $variation_summary = self::build_variation_summary( $product );
        }

        return array(
            'product_id'        => $product_id,
            'variation_id'      => $variation_id,
            'target_product_id' => $target_product_id,
            'product_name'      => $product_name,
            'product_sku'       => $product_sku,
            'variation_summary' => $variation_summary,
            'product_url'       => $product_url,
        );
    }

    public static function build_variation_summary( WC_Product_Variation $variation ) {
        $parts = array();

        foreach ( $variation->get_attributes() as $attribute => $value ) {
            $taxonomy = str_replace( 'attribute_', '', $attribute );
            $label    = wc_attribute_label( $taxonomy );

            if ( taxonomy_exists( $taxonomy ) ) {
                $term = get_term_by( 'slug', $value, $taxonomy );
                if ( $term && ! is_wp_error( $term ) ) {
                    $value = $term->name;
                }
            }

            $parts[] = sprintf( '%1$s: %2$s', $label, $value );
        }

        return implode( ', ', array_filter( $parts ) );
    }
}
