# WooCommerce First Order Discount

A general WooCommerce plugin that turns any coupon into a **first-order-only** coupon and can show the latest eligible first-order coupon to logged-in customers during checkout.

## Features

- Adds a **First order only** checkbox to WooCommerce coupon restrictions
- Server-side enforcement even if the coupon is entered manually
- Checks previous purchases using WooCommerce order APIs
- Checks both:
  - registered customer ID
  - billing email
- Prevents a registered customer from bypassing the rule when an older purchase was placed as a guest using the same email
- Uses WooCommerce paid-order statuses by default
- Paid/qualifying statuses can be customized through a filter
- Shows the latest available first-order coupon at checkout
- Copy coupon button
- Apply coupon button
- Persistent **Never show again** preference per user
- **Not now** dismisses only the current dialog
- Coupon expiry and global usage-limit prechecks
- Full cart/product/coupon restrictions remain delegated to WooCommerce
- Responsive accessible modal/bottom sheet
- Keyboard Escape support and focus trapping
- Reduced-motion support
- HPOS compatibility
- No site-specific domain, branding, font, or theme CSS variable dependency
- No custom database tables

## Requirements

- WordPress 6.0+
- PHP 7.4+
- WooCommerce

## Installation

1. Upload `WooCommerce-First-Order-Discount` to `/wp-content/plugins/`.
2. Activate **WooCommerce First Order Discount**.
3. Open **Marketing → Coupons** (or the WooCommerce coupon editor used by your installation).
4. Edit a coupon.
5. Under **Usage restriction**, enable **First order only**.
6. Save the coupon.

If more than one published coupon is marked as first-order-only, the newest basically available coupon is used for the automatic checkout offer.

## What Counts as a Previous Purchase?

By default the plugin uses WooCommerce's paid order statuses (`wc_get_is_paid_statuses()`), typically including Processing and Completed.

The check is performed against both the current customer ID and billing email.

You can change the statuses:

```php
add_filter( 'wcfod_previous_purchase_statuses', function ( $statuses ) {
	return array( 'wc-processing', 'wc-completed' );
} );
```

## Choosing the Offer Coupon

By default, the newest published first-order coupon that is not expired and has not exhausted its global usage limit is used.

You can override or disable the selected coupon:

```php
add_filter( 'wcfod_offer_coupon', function ( $coupon ) {
	// Return a WC_Coupon instance or null.
	return $coupon;
} );
```

## Checkout Offer

The automatic promotional dialog is shown only to logged-in users on checkout.

This restriction applies only to the UI. Coupon eligibility itself is validated server-side, so a guest who manually enters a first-order-only coupon is still checked using the billing email available to WooCommerce.

The dialog includes:

- coupon discount amount
- coupon code
- copy button
- direct apply button
- Not now
- Never show again

After a successful AJAX application, checkout reloads so the updated totals work consistently across checkout implementations.

## Important Eligibility Notes

"First order" is defined by qualifying previous orders, not by account age.

A pending, failed, cancelled, or other non-paid order does not disqualify the customer unless its status is added through `wcfod_previous_purchase_statuses`.

Refund semantics depend on the order's current status and your customized status list.

## Coupon Restrictions

The promotional UI performs only inexpensive prechecks before showing a coupon:

- coupon exists
- coupon is published
- coupon is not expired
- global usage limit is not exhausted

WooCommerce remains responsible for final validation of:

- minimum/maximum spend
- products/categories
- individual use
- email restrictions
- usage per user
- excluded sale items
- other extension-defined restrictions

## HPOS

The plugin declares HPOS compatibility and uses `wc_get_orders()` for previous-order checks.

No direct SQL is used against `wp_posts` or HPOS order tables.

## Data Storage

No custom table is created.

The plugin stores:

- `_wcfod_first_order_only` on coupons
- `_wcfod_offer_dismissed` in user meta only when the customer chooses **Never show again**

## Security

- AJAX requests use WordPress nonces
- Automatic offer AJAX endpoints require an authenticated user
- Coupon validation is also enforced server-side
- Coupon IDs/codes are not accepted from the browser when applying the offer; the server selects the eligible coupon itself
- WooCommerce's coupon engine performs the final application

## Original Snippet Fixes

The packaged plugin also fixes issues present in the supplied snippet:

- removes all Beban-specific prefixes, text domain, theme variables, and fonts
- fixes the malformed CSS character after `transition`
- fixes the unclosed mobile media query
- adds the missing UI action that actually calls the existing coupon-apply logic
- checks previous guest purchases by billing email for logged-in users
- uses current WooCommerce paid-order statuses instead of hardcoding only two statuses
- avoids deleting the permanent-dismiss preference when the user chooses **Not now**
- removes the jQuery dependency from the frontend UI

## License

GPL-3.0

## Author

Amirreza Shayesteh Far  
https://github.com/amirrezashf

---

# تخفیف اولین خرید ووکامرس

این افزونه هر Coupon ووکامرس را می‌تواند به کوپن مخصوص اولین خرید تبدیل کند و برای کاربران واجد شرایط در Checkout پیشنهاد تخفیف نمایش دهد.

## قابلیت‌ها

- گزینه «فقط اولین خرید» در ویرایش Coupon
- اعتبارسنجی سمت سرور
- بررسی خریدهای قبلی با شناسه کاربر و ایمیل صورتحساب
- پشتیبانی از خرید قبلی مهمان با همان ایمیل
- استفاده از Statusهای پرداخت‌شده خود WooCommerce
- نمایش Coupon واجد شرایط در Checkout
- کپی کد
- اعمال مستقیم کد
- «الان نه»
- «دیگر نمایش نده»
- سازگاری HPOS
- بدون وابستگی به Domain، Theme، Font یا Branding خاص
- بدون Custom Table

## تعریف اولین خرید

به‌صورت پیش‌فرض Orderهایی که WooCommerce پرداخت‌شده در نظر می‌گیرد باعث عدم صلاحیت مشتری برای Coupon اولین خرید می‌شوند.

Statusها با Filter زیر قابل تغییرند:

```php
add_filter( 'wcfod_previous_purchase_statuses', function ( $statuses ) {
	return array( 'wc-processing', 'wc-completed' );
} );
```

## نکته

بررسی نهایی تمام محدودیت‌های خود Coupon همچنان توسط WooCommerce انجام می‌شود. این افزونه فقط محدودیت «اولین خرید» را به آن اضافه می‌کند.

## مجوز

GPL-3.0

## نویسنده

Amirreza Shayesteh Far  
https://github.com/amirrezashf
