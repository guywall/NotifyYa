<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NotifyYa_Frontend {
    private $settings;
    private $has_rendered = false;

    public function __construct( NotifyYa_Settings $settings ) {
        $this->settings = $settings;

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'woocommerce_single_product_summary', array( $this, 'render_widget' ), 31 );
        add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'render_widget' ), 5 );
        add_action( 'woocommerce_product_meta_end', array( $this, 'render_widget' ), 20 );
        add_shortcode( 'notifyya_back_in_stock', array( $this, 'render_shortcode' ) );
    }

    public function enqueue_assets() {
        if ( ! function_exists( 'is_product' ) || ! is_product() ) {
            return;
        }

        wp_enqueue_style(
            'notifyya-frontend',
            NOTIFYYA_URL . 'assets/css/frontend.css',
            array(),
            NOTIFYYA_VERSION
        );

        wp_enqueue_script(
            'notifyya-frontend',
            NOTIFYYA_URL . 'assets/js/frontend.js',
            array( 'jquery' ),
            NOTIFYYA_VERSION,
            true
        );

        $settings = $this->settings->get_settings();

        wp_localize_script(
            'notifyya-frontend',
            'notifyYaFront',
            array(
                'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
                'nonce'            => wp_create_nonce( 'notifyya_subscribe' ),
                'successMessage'   => $settings['success_message'],
                'recaptchaEnabled' => ! empty( $settings['recaptcha_enabled'] ) && ! empty( $settings['recaptcha_site_key'] ),
                'recaptchaSiteKey' => $settings['recaptcha_site_key'],
                'buttonLabel'      => $settings['button_label'],
                'selectVariation'  => __( 'Select an out-of-stock variation to subscribe.', 'notifyya' ),
                'inStockMessage'   => __( 'The selected variation is currently in stock, so notifications are not needed.', 'notifyya' ),
                'requestError'     => __( 'We could not save your request. Please try again.', 'notifyya' ),
            )
        );

        if ( ! empty( $settings['recaptcha_enabled'] ) && ! empty( $settings['recaptcha_site_key'] ) ) {
            wp_enqueue_script(
                'google-recaptcha',
                'https://www.google.com/recaptcha/api.js?render=explicit',
                array(),
                null,
                true
            );
        }
    }

    public function render_widget() {
        if ( $this->has_rendered ) {
            return;
        }

        $markup = $this->get_widget_markup();
        if ( '' === $markup ) {
            return;
        }

        $this->has_rendered = true;
        echo $markup;
    }

    public function render_shortcode() {
        if ( $this->has_rendered ) {
            return '';
        }

        $markup = $this->get_widget_markup();
        if ( '' === $markup ) {
            return '';
        }

        $this->has_rendered = true;
        return $markup;
    }

    private function get_widget_markup() {
        global $product;

        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return '';
        }

        $settings     = $this->settings->get_settings();
        $product_type = $product->get_type();
        $is_variable  = $product->is_type( 'variable' );
        $is_simple    = $product->is_type( 'simple' );

        if ( ! $is_variable && ! $is_simple ) {
            return '';
        }

        if ( $is_simple && $product->is_in_stock() ) {
            return '';
        }

        if ( $is_variable ) {
            $has_out_of_stock_variation = false;

            foreach ( $product->get_children() as $child_id ) {
                $child_product = wc_get_product( $child_id );
                if ( $child_product && ! $child_product->is_in_stock() ) {
                    $has_out_of_stock_variation = true;
                    break;
                }
            }

            if ( ! $has_out_of_stock_variation ) {
                return '';
            }
        }

        $button_classes = array( 'notifyya-open-button' );
        $helper_text    = '';

        if ( $is_variable ) {
            $button_classes[] = 'is-disabled';
            $helper_text      = __( 'Select an out-of-stock variation to subscribe.', 'notifyya' );
        }

        ob_start();
        ?>
        <div class="notifyya-widget" data-product-type="<?php echo esc_attr( $product_type ); ?>">
            <button type="button" class="<?php echo esc_attr( implode( ' ', $button_classes ) ); ?>" <?php disabled( $is_variable ); ?>>
                <?php echo esc_html( $settings['button_label'] ); ?>
            </button>
            <p class="notifyya-helper-text"><?php echo esc_html( $helper_text ); ?></p>

            <div class="notifyya-modal" hidden>
                <div class="notifyya-modal__backdrop"></div>
                <div class="notifyya-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="notifyya-modal-title-<?php echo esc_attr( $product->get_id() ); ?>">
                    <button type="button" class="notifyya-modal__close" aria-label="<?php esc_attr_e( 'Close', 'notifyya' ); ?>">&times;</button>
                    <h3 id="notifyya-modal-title-<?php echo esc_attr( $product->get_id() ); ?>"><?php echo esc_html( $settings['modal_title'] ); ?></h3>
                    <div class="notifyya-modal__intro"><?php echo wp_kses_post( wpautop( $settings['form_intro'] ) ); ?></div>
                    <form class="notifyya-form">
                        <label for="notifyya-email-<?php echo esc_attr( $product->get_id() ); ?>"><?php esc_html_e( 'Email address', 'notifyya' ); ?></label>
                        <input id="notifyya-email-<?php echo esc_attr( $product->get_id() ); ?>" type="email" name="email" required placeholder="<?php esc_attr_e( 'you@example.com', 'notifyya' ); ?>" />
                        <input type="hidden" name="product_id" value="<?php echo esc_attr( $product->get_id() ); ?>" />
                        <input type="hidden" name="variation_id" value="0" />
                        <input type="hidden" name="source_url" value="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>" />
                        <input type="hidden" name="rendered_at" value="<?php echo esc_attr( time() ); ?>" />
                        <div class="notifyya-hp" aria-hidden="true">
                            <label for="notifyya-company-<?php echo esc_attr( $product->get_id() ); ?>"><?php esc_html_e( 'Company', 'notifyya' ); ?></label>
                            <input id="notifyya-company-<?php echo esc_attr( $product->get_id() ); ?>" type="text" name="company" tabindex="-1" autocomplete="off" />
                        </div>
                        <?php if ( ! empty( $settings['recaptcha_enabled'] ) && ! empty( $settings['recaptcha_site_key'] ) ) : ?>
                            <div class="notifyya-recaptcha" data-site-key="<?php echo esc_attr( $settings['recaptcha_site_key'] ); ?>"></div>
                        <?php endif; ?>
                        <div class="notifyya-form__feedback" hidden></div>
                        <button type="submit" class="notifyya-submit"><?php echo esc_html( $settings['button_label'] ); ?></button>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}