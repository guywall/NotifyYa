# NotifyYa

NotifyYa is a WooCommerce back-in-stock notification plugin. When a product is out of stock, shoppers can request an email alert, and the plugin sends a single notification when that exact product or variation returns to stock.

## Features

- Out-of-stock signup button with modal email form
- Support for simple products and specific variations
- Duplicate-safe subscriptions, with one active pending request per email and stock item
- Automatic send on restock, with sent requests expired after delivery
- Admin request list with search, filtering, and CSV export
- Admin logs for saved requests and send failures
- Built-in anti-spam protection with honeypot, timing, and rate limiting
- Optional Google reCAPTCHA v2 checkbox support
- Editable email subject and HTML body, plus test-send support

## Frontend placement

NotifyYa now tries several WooCommerce single-product hooks automatically:

- `woocommerce_single_product_summary`
- `woocommerce_after_add_to_cart_form`
- `woocommerce_product_meta_end`

That improves compatibility with many themes and builders, including Elementor-driven product layouts.

If your Elementor single-product template still does not show the button automatically, place this shortcode inside an Elementor Shortcode widget where you want the trigger to appear:

```text
[notifyya_back_in_stock]
```

The shortcode uses the current product context and will not render a second copy if the automatic hook placement already succeeded on the page.

## Admin areas

After activation, NotifyYa adds these admin pages:

- `NotifyYa > Requests`
- `NotifyYa > Settings`
- `NotifyYa > Email`
- `NotifyYa > Logs`

## Notes

- The plugin stores request and log data in custom database tables created on activation.
- Email delivery uses the site's configured WordPress mail transport.
- reCAPTCHA support in this version is Google reCAPTCHA v2 checkbox.