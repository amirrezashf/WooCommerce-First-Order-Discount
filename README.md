# WooCommerce First Order Discount

A lightweight, production-oriented WooCommerce plugin for restricting selected coupons to genuine first-time customers and presenting an eligible first-order discount directly at checkout.

The plugin combines **server-side coupon enforcement** with an optional checkout offer interface. It does not rely on JavaScript alone to decide whether a customer is eligible.

## Features

- Mark individual WooCommerce coupons as **First order only**
- Enforce the restriction server-side
- Check previous paid orders for logged-in customers
- Check previous guest orders by billing email when relevant
- Prevent a logged-in customer from bypassing the restriction through older guest orders using the same billing email
- Show an eligible first-order coupon offer at checkout
- Apply the selected server-approved coupon through WooCommerce
- “Not now” action for the current checkout view
- “Never show again” preference for logged-in users
- Accessible checkout dialog
- Keyboard/focus management
- Escape-key support
- Reduced-motion support
- WooCommerce HPOS compatibility
- WooCommerce order queries through `wc_get_orders()`
- WooCommerce native coupon validation remains authoritative
- No custom database table
- No site-specific domain, theme, branding, or coupon code
- Extensible through WordPress filters

## Requirements

- WordPress 6.0+
- PHP 7.4+
- WooCommerce

## Installation

1. Download the plugin ZIP.
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Upload `WooCommerce-First-Order-Discount.zip`.
4. Install and activate the plugin.
5. Create or edit a WooCommerce coupon.
6. Open the coupon's **Usage restriction** section.
7. Enable **First order only** for coupons that should be limited to first-time customers.
8. Configure the coupon's normal WooCommerce restrictions as usual.

## How It Works

The plugin adds a first-order restriction flag to individual WooCommerce coupons.

When WooCommerce validates a marked coupon, the plugin determines whether the current customer already has a previous paid order.

The restriction is enforced on the server, so hiding or modifying the checkout JavaScript does not remove the coupon rule.

WooCommerce still evaluates its own native coupon rules such as:

- expiry;
- minimum/maximum spend;
- product/category restrictions;
- usage limits;
- individual-use rules;
- excluded products/categories;
- email restrictions;
- other WooCommerce coupon validation.

The plugin adds the first-order rule instead of replacing WooCommerce's coupon engine.

## First-Order Eligibility

A customer is considered to have a previous purchase when a matching order exists in one of WooCommerce's paid statuses.

The implementation uses WooCommerce's paid-status definition instead of hardcoding only `completed`.

This matters because stores may consider statuses such as `processing` paid as well.

## Logged-In Customers

For an authenticated customer, the plugin checks for previous paid orders using the WooCommerce customer ID.

It also checks the customer's billing email when available.

The email check is important because the same person may previously have purchased as a guest and later created/logged into an account.

Without this second check, a customer could appear to be a “first-time” buyer simply because the previous order had no WordPress user ID.

## Guest Customers

For guests, eligibility can be evaluated when a usable billing email is available to WooCommerce.

Guest detection is naturally dependent on checkout timing because the email must already exist in the WooCommerce customer/checkout state.

The server-side coupon restriction remains the authoritative layer.

## Efficient Previous-Order Lookup

The plugin does not load a customer's full order history.

It uses `wc_get_orders()` and requests only enough information to determine whether at least one matching paid order exists.

The query is limited to one result/ID where appropriate.

This keeps the eligibility check significantly lighter than retrieving and iterating through all previous `WC_Order` objects.

## Checkout Offer

For eligible logged-in customers, the plugin can surface a first-order coupon offer during checkout.

The server determines which coupon is eligible.

The browser is not trusted to choose an arbitrary coupon code and have it applied without server validation.

The offer interface provides:

- discount/coupon information;
- an Apply action;
- a “Not now” action;
- a “Never show again” action for logged-in users.

After a successful application, WooCommerce recalculates the cart/checkout totals.

## Selecting the Offered Coupon

The plugin searches for recent coupons marked as first-order-only and evaluates their basic availability.

The selected offer can be customized through:

`wcfod_offer_coupon`

This allows a site-specific integration to alter the offered coupon without editing plugin source.

## Previous-Purchase Status Filter

The statuses considered a previous paid purchase can be customized through:

`wcfod_previous_purchase_statuses`

By default, the plugin is based on WooCommerce paid statuses.

Example:

```php
add_filter( 'wcfod_previous_purchase_statuses', function ( $statuses ) {
    return $statuses;
} );
```

Only change this when the store's business rules intentionally differ from WooCommerce's normal paid-order semantics.

## Coupon Administration

The plugin adds a **First order only** option to the WooCommerce coupon editor.

The value is stored as coupon meta:

`_wcfod_first_order_only`

Administrative saving requires the WooCommerce management capability.

## Dismiss Preference

When a logged-in customer chooses **Never show again**, the preference is stored as user meta:

`_wcfod_offer_dismissed`

This affects the promotional checkout dialog. It does **not** make an otherwise ineligible coupon valid and does not disable server-side coupon enforcement.

The “Not now” action is temporary and is intended only to dismiss the currently rendered offer.

## AJAX Security

Checkout actions use a dedicated nonce.

Server-side actions validate the request before applying or persisting state.

The coupon to apply is selected/validated server-side rather than trusting arbitrary browser input.

## Coupon Validation

The plugin integrates with WooCommerce coupon validation.

A coupon marked first-order-only is rejected when a previous qualifying purchase is found.

The user receives a coupon validation error instead of receiving a discount that should not be available.

This protection remains relevant even if the promotional dialog is disabled, dismissed, or bypassed.

## HPOS Compatibility

The plugin declares WooCommerce `custom_order_tables` compatibility.

Previous-order checks use WooCommerce order query APIs instead of querying `wp_posts` or `wp_postmeta` directly.

This keeps the customer purchase check compatible with High-Performance Order Storage.

## Data Storage

The plugin does not create a custom database table.

It uses:

Coupon meta:

`_wcfod_first_order_only`

User meta:

`_wcfod_offer_dismissed`

No separate analytics database is required.

## Privacy

The plugin uses existing WooCommerce customer/order information to determine first-order eligibility.

It does not create a separate copy of complete order histories.

For guest-history matching, billing email may be used as the matching identifier through WooCommerce order queries.

The dismiss preference stores only a simple user-level flag.

## Performance

The plugin is designed to avoid expensive full-history scans.

Important characteristics include:

- `wc_get_orders()` instead of direct order-table SQL;
- paid-status filtering;
- customer ID/email matching;
- result limit of one for previous-purchase existence checks;
- no loading of unlimited order objects;
- no custom analytics table;
- no global order scan during normal checkout;
- only a limited set of recent marked coupons considered for the promotional offer.

For very large stores, coupon counts and checkout traffic should still be considered when customizing offer-selection logic.

## Security

The plugin follows a server-authoritative model:

- first-order eligibility is checked server-side;
- coupon validity is not controlled only by JavaScript;
- WooCommerce native coupon validation remains active;
- AJAX requests use nonce verification;
- server chooses/validates the offered coupon;
- administrative coupon settings require WooCommerce management capability;
- order history is queried through WooCommerce APIs;
- frontend output is handled as plugin-controlled UI rather than trusting arbitrary HTML from the client.

## Accessibility

The checkout offer is implemented as an accessible dialog-oriented interface.

The implementation includes:

- keyboard interaction;
- focus management/trapping;
- focus restoration;
- Escape-key closing;
- reduced-motion handling;
- semantic asynchronous feedback where applicable.

The plugin does not require a specific theme or font.

## What “First Order” Means

In this plugin, “first order” is not merely:

> the first order associated with the current WordPress account.

It attempts to determine whether the customer has an earlier **paid WooCommerce purchase**, including relevant guest history associated with the billing email.

This provides stronger enforcement for stores where customers commonly purchase as guests before creating accounts.

## Limitations

- Guest eligibility depends on a usable billing email being available at the time validation occurs.
- Email-based guest matching assumes the customer uses the same billing email.
- A customer using a genuinely different email may not match earlier guest orders.
- The plugin does not perform identity verification beyond WooCommerce customer ID/email history.
- The promotional offer is designed primarily for logged-in checkout customers.
- “Not now” is temporary and does not create a persistent preference.
- “Never show again” controls the offer UI, not coupon eligibility.
- The plugin does not replace WooCommerce coupon restrictions.
- It does not automatically create coupons.
- It does not automatically choose the most financially optimal coupon among every coupon in the database.

## Troubleshooting

### Coupon is rejected for a customer who says this is their first order

Check whether:

- the account has a previous paid order;
- the billing email matches an older guest order;
- the store has custom paid statuses;
- another plugin has changed WooCommerce coupon validation or order/customer data.

### Offer does not appear

Check:

- the customer is logged in;
- the customer is actually eligible;
- a valid first-order-only coupon exists;
- the offer was not permanently dismissed;
- the coupon is not expired or globally exhausted;
- checkout JavaScript is not being broken by another theme/plugin.

### Coupon is valid but the dialog is hidden

The promotional dialog and coupon enforcement are separate concerns.

A marked coupon can remain valid/invalid according to server rules even when the offer UI is not shown.

## Extensibility

Available filters include:

`wcfod_previous_purchase_statuses`

Used to customize which order statuses count as previous purchases.

`wcfod_offer_coupon`

Used to customize the coupon selected for the checkout offer.

These filters allow store-specific behavior without editing the public plugin source.

## Suggested GitHub Description

`Restrict WooCommerce coupons to first-time customers and show eligible first-order discounts at checkout with server-side validation and HPOS support.`

## License

GPL-3.0

## Author

**Amirreza Shayesteh Far**

GitHub: `https://github.com/amirrezashf`

---

# تخفیف اولین سفارش ووکامرس

**WooCommerce First Order Discount** افزونه‌ای مستقل برای WooCommerce است که امکان محدودکردن Couponهای مشخص به **اولین خرید واقعی مشتری** را فراهم می‌کند و در صورت واجد شرایط بودن مشتری می‌تواند پیشنهاد تخفیف اولین سفارش را در Checkout نمایش دهد.

نکته مهم این است که محدودیت Coupon فقط با JavaScript یا نمایش/عدم نمایش Popup کنترل نمی‌شود. Eligibility در سمت Server بررسی می‌شود و WooCommerce همچنان Validation اصلی Coupon را انجام می‌دهد.

## قابلیت‌ها

- اضافه‌کردن گزینه **فقط اولین خرید** به Couponهای WooCommerce
- اعمال محدودیت اولین خرید در سمت Server
- بررسی سفارش‌های قبلی کاربران Loginشده
- بررسی سفارش‌های Guest قبلی با Billing Email
- جلوگیری از دورزدن محدودیت با خرید Guest و سپس ساخت Account
- استفاده از Paid Statusهای واقعی WooCommerce
- نمایش پیشنهاد تخفیف در Checkout برای مشتری واجد شرایط
- Apply کردن Coupon از طریق WooCommerce
- گزینه **فعلاً نه** برای بستن پیشنهاد فعلی
- گزینه **دیگر نمایش نده** برای کاربران Loginشده
- ذخیره Preference عدم نمایش
- رابط Checkout قابل استفاده با Keyboard
- Focus Management
- پشتیبانی از Escape
- رعایت `prefers-reduced-motion`
- سازگاری با HPOS
- استفاده از `wc_get_orders()`
- عدم Query مستقیم `wp_posts` / `wp_postmeta` برای سفارش‌ها
- عدم ایجاد Custom Database Table
- بدون Coupon Code هاردکدشده
- بدون وابستگی به Domain، Theme یا فروشگاه خاص
- قابلیت توسعه با Filter

## هدف افزونه

فرض کنید Coupon با کد:

```text
WELCOME10
```

برای تخفیف خرید اول ساخته شده است.

در WooCommerce به‌صورت عادی ممکن است مشتری بتواند Coupon را وارد کند، اما WooCommerce به‌تنهایی لزوماً مفهوم Business Rule خاص شما با عنوان «این مشتری قبلاً هیچ خرید پرداخت‌شده‌ای نداشته باشد» را برای آن Coupon اعمال نمی‌کند.

این افزونه یک Restriction جدید به Coupon اضافه می‌کند:

**First order only**

وقتی این گزینه فعال باشد، Coupon فقط زمانی معتبر خواهد بود که افزونه هیچ خرید پرداخت‌شده قبلی برای مشتری پیدا نکند.

## نصب

1. ZIP افزونه را دانلود کنید.
2. در WordPress وارد **افزونه‌ها → افزودن افزونه تازه → بارگذاری افزونه** شوید.
3. فایل ZIP را نصب کنید.
4. افزونه را فعال کنید.
5. وارد بخش Couponهای WooCommerce شوید.
6. Coupon مورد نظر را Edit کنید.
7. بخش **Usage restriction** را باز کنید.
8. گزینه **First order only / فقط اولین خرید** را فعال کنید.
9. سایر محدودیت‌های Coupon را طبق نیاز خود WooCommerce تنظیم کنید.
10. Coupon را ذخیره کنید.

از این مرحله به بعد Restriction اولین خرید در Validation آن Coupon اعمال می‌شود.

## «اولین خرید» چگونه تشخیص داده می‌شود؟

تعریف اولین خرید در این افزونه فقط این نیست که:

> آیا User ID فعلی قبلاً Order داشته است یا خیر؟

این تعریف برای بسیاری از فروشگاه‌ها کافی نیست.

برای مثال مشتری ممکن است:

1. ابتدا بدون Account و به‌صورت Guest خرید کند.
2. بعداً Account ایجاد کند.
3. Login کند.
4. Coupon اولین خرید را استفاده کند.

اگر فقط `customer_id` بررسی شود، خرید Guest قبلی ممکن است دیده نشود و مشتری دوباره First-Time Customer محسوب شود.

به همین دلیل افزونه علاوه بر Customer ID، Billing Email مرتبط را نیز در سناریوهای لازم بررسی می‌کند.

## کاربران Loginشده

برای کاربر Loginشده، افزونه ابتدا بررسی می‌کند آیا Order پرداخت‌شده قبلی با Customer ID او وجود دارد یا خیر.

در صورت وجود Billing Email قابل استفاده، History مربوط به آن Email نیز بررسی می‌شود.

این موضوع باعث می‌شود سفارش Guest قبلی با همان Email تا حد ممکن در Eligibility لحاظ شود.

## کاربران Guest

برای Guestها، زمانی که Billing Email در State ووکامرس در دسترس باشد، افزونه می‌تواند از Email برای بررسی History استفاده کند.

این بخش به Timing Checkout وابسته است؛ یعنی WooCommerce باید Billing Email را در آن مرحله در اختیار داشته باشد.

به همین دلیل Server-Side Coupon Validation لایه اصلی Enforcement باقی می‌ماند.

## چه Statusهایی خرید قبلی محسوب می‌شوند؟

افزونه به‌جای Hardcode کردن صرفاً:

`completed`

از Paid Statusهای WooCommerce استفاده می‌کند.

این موضوع مهم است، چون در WooCommerce سفارشی مثل:

`processing`

نیز معمولاً می‌تواند یک Order پرداخت‌شده باشد.

بنابراین مشتری‌ای که یک سفارش پرداخت‌شده Processing دارد نباید صرفاً به این دلیل که Order هنوز Completed نشده، First-Time Customer محسوب شود.

## Performance بررسی سفارش قبلی

برای تشخیص خرید قبلی نیازی نیست تمام سفارش‌های مشتری Load شوند.

افزونه با `wc_get_orders()` فقط وجود حداقل یک Order واجد شرایط را بررسی می‌کند.

در Query مربوط به وجود خرید قبلی، تعداد Resultها تا حد ممکن محدود می‌شود.

بنابراین به‌جای:

- Load کردن ده‌ها یا صدها `WC_Order`
- Loop روی تمام History
- Query مستقیم Post Meta

از Query سبک‌تر WooCommerce استفاده می‌شود.

## Validation Coupon

وقتی Coupon دارای Restriction اولین خرید باشد، افزونه هنگام Validation بررسی می‌کند آیا مشتری قبلاً خرید پرداخت‌شده داشته است.

اگر پاسخ مثبت باشد، Coupon Reject می‌شود.

این Validation در Server انجام می‌شود.

بنابراین کاربر نمی‌تواند صرفاً با:

- حذف JavaScript
- تغییر DOM
- مخفی‌کردن Popup
- اجرای دستی کد Frontend

محدودیت اصلی Coupon را از بین ببرد.

## WooCommerce همچنان مسئول Validation اصلی است

این افزونه Coupon Engine ووکامرس را دوباره از صفر پیاده‌سازی نمی‌کند.

WooCommerce همچنان مواردی مانند این‌ها را بررسی می‌کند:

- Expiry Date
- Minimum Spend
- Maximum Spend
- Product Restrictions
- Category Restrictions
- Excluded Products
- Excluded Categories
- Usage Limit
- Usage Limit per User
- Individual Use
- Email Restrictions
- سایر Validationهای خود WooCommerce

افزونه فقط Rule مربوط به First Order را به این فرآیند اضافه می‌کند.

این طراحی از Duplicate کردن منطق WooCommerce جلوگیری می‌کند.

## پیشنهاد تخفیف در Checkout

برای مشتری Loginشده و واجد شرایط، افزونه می‌تواند یک Offer برای Coupon اولین خرید در Checkout نمایش دهد.

این UI برای این طراحی شده که مشتری واجد شرایط متوجه تخفیف شود و بتواند آن را Apply کند.

Offer شامل Actionهایی مانند:

- Apply
- فعلاً نه
- دیگر نمایش نده

است.

## امنیت Apply Coupon

Browser نباید بتواند هر Coupon دلخواهی را به Server بدهد و از افزونه بخواهد آن را Apply کند.

در این افزونه Coupon پیشنهادی توسط Server انتخاب و Validate می‌شود.

در نهایت WooCommerce نیز هنگام Apply کردن Coupon Validation خودش را اجرا می‌کند.

بنابراین Client تنها مرجع تصمیم‌گیری نیست.

## انتخاب Coupon پیشنهادی

افزونه مجموعه محدودی از Couponهای جدیدی را که دارای First-Order Restriction هستند بررسی می‌کند.

Coupon باید حداقل از نظر شرایط اولیه قابل استفاده باشد.

انتخاب نهایی را می‌توان با Filter زیر Customize کرد:

`wcfod_offer_coupon`

به این ترتیب اگر فروشگاه Logic خاصی برای انتخاب Coupon دارد، نیازی به تغییر فایل اصلی افزونه نیست.

## گزینه «فعلاً نه»

گزینه **Not now / فعلاً نه** فقط Offer فعلی را می‌بندد.

هدف آن ایجاد Preference دائمی نیست.

بنابراین ممکن است پس از Reload یا Session/Render دیگری Offer دوباره نمایش داده شود.

## گزینه «دیگر نمایش نده»

برای کاربران Loginشده، گزینه **Never show again / دیگر نمایش نده** Preference را در User Meta ذخیره می‌کند:

`_wcfod_offer_dismissed`

این Preference فقط مربوط به **نمایش Offer** است.

یعنی:

- Coupon را حذف نمی‌کند.
- First-Order Restriction را غیرفعال نمی‌کند.
- Coupon نامعتبر را معتبر نمی‌کند.
- Server-Side Validation را دور نمی‌زند.

## Storage

افزونه Custom Table ایجاد نمی‌کند.

### Coupon Meta

برای مشخص‌کردن Coupon اولین خرید:

`_wcfod_first_order_only`

### User Meta

برای Preference مربوط به عدم نمایش Offer:

`_wcfod_offer_dismissed`

Order History موجود WooCommerce برای تشخیص Eligibility استفاده می‌شود و نسخه جداگانه‌ای از تمام History ساخته نمی‌شود.

## HPOS

افزونه Compatibility با WooCommerce:

`custom_order_tables`

را اعلام می‌کند.

برای بررسی History سفارش‌ها از WooCommerce Order Query API استفاده می‌شود.

بنابراین Logic اصلی تشخیص First Order به Query مستقیم:

`wp_posts`

یا:

`wp_postmeta`

وابسته نیست.

این موضوع برای فروشگاه‌هایی که **High-Performance Order Storage** فعال دارند اهمیت دارد.

## AJAX و امنیت

Actionهای Checkout که نیازمند ارتباط با Server هستند با Nonce محافظت می‌شوند.

Server درخواست را Validate می‌کند و تصمیم مهم مربوط به Coupon را به Browser واگذار نمی‌کند.

در Admin نیز ذخیره Restriction Coupon به Capability مناسب WooCommerce محدود است.

## Accessibility رابط Checkout

Offer صرفاً یک Popup تزئینی نیست.

در پیاده‌سازی مواردی مانند این‌ها لحاظ شده‌اند:

- Keyboard Interaction
- Focus Management
- Focus Trap
- بازگرداندن Focus
- بستن با Escape
- Async Feedback مناسب
- Reduced Motion

افزونه به Font یا Theme خاصی وابسته نیست.

## حریم خصوصی

برای تشخیص First Order، افزونه از اطلاعاتی استفاده می‌کند که WooCommerce از قبل برای Customer/Order دارد.

به‌خصوص Billing Email می‌تواند برای پیدا کردن Guest Order قبلی استفاده شود.

افزونه یک کپی کامل و جداگانه از Order History مشتری ایجاد نمی‌کند.

Preference مربوط به **دیگر نمایش نده** نیز فقط یک Flag ساده در User Meta است.

## Performance

برای کاهش فشار Checkout:

- از `wc_get_orders()` استفاده می‌شود.
- Paid Statusها Filter می‌شوند.
- Query برای پیدا کردن وجود Purchase قبلی محدود می‌شود.
- تمام History به شکل `WC_Order` Load نمی‌شود.
- Query سراسری تمام Orders وجود ندارد.
- Custom Analytics Table ایجاد نمی‌شود.
- برای Offer فقط تعداد محدودی Coupon بررسی می‌شود.

اگر فروشگاه تعداد بسیار زیادی Coupon یا Checkout Traffic بسیار بالا دارد، Logic سفارشی انتخاب Offer باید متناسب با همان Scale بررسی شود.

## Filterها

### `wcfod_previous_purchase_statuses`

برای تغییر Statusهایی که خرید قبلی محسوب می‌شوند.

نمونه:

```php
add_filter( 'wcfod_previous_purchase_statuses', function ( $statuses ) {
    return $statuses;
} );
```

در حالت عادی بهتر است Paid Statusهای WooCommerce مبنا باقی بمانند مگر اینکه Business Rule فروشگاه عمداً متفاوت باشد.

### `wcfod_offer_coupon`

برای Customize کردن Couponی که در Offer Checkout پیشنهاد می‌شود.

این Filter برای فروشگاه‌هایی مناسب است که چند Coupon اولین خرید دارند و می‌خواهند Selection Logic اختصاصی داشته باشند.

## عیب‌یابی

### مشتری می‌گوید خرید اول اوست اما Coupon Reject می‌شود

موارد زیر را بررسی کنید:

1. آیا Account دارای Order پرداخت‌شده قبلی است؟
2. آیا Billing Email با یک Guest Order قدیمی Match می‌شود؟
3. آیا Store Paid Status سفارشی دارد؟
4. آیا Plugin دیگری Coupon Validation را تغییر داده است؟
5. آیا Order History مشتری به Account/Email دیگری مرتبط است؟

### Offer نمایش داده نمی‌شود

بررسی کنید:

1. مشتری Login باشد.
2. واقعاً First-Time Eligible باشد.
3. Coupon فعال First-Order وجود داشته باشد.
4. Coupon Expire نشده باشد.
5. Usage Limit آن تمام نشده باشد.
6. کاربر قبلاً **Never show again** نزده باشد.
7. JavaScript Checkout توسط Theme/Plugin دیگری خراب نشده باشد.

### Coupon کار می‌کند اما Offer دیده نمی‌شود

Offer UI و Coupon Enforcement دو بخش جدا هستند.

ممکن است Coupon طبق Server Rules معتبر باشد ولی UI پیشنهاد نمایش داده نشود.

برعکس، نمایش UI نیز نباید باعث شود Coupon نامعتبر از Validation WooCommerce عبور کند.

## محدودیت‌ها

- تشخیص Guest به Billing Email موجود وابسته است.
- اگر مشتری در خرید جدید از Email کاملاً متفاوتی استفاده کند، Guest Order قدیمی ممکن است Match نشود.
- افزونه Identity Verification انجام نمی‌دهد.
- Offer خودکار عمدتاً برای مشتری Loginشده طراحی شده است.
- **Not now** Preference دائمی نیست.
- **Never show again** فقط UI را کنترل می‌کند.
- افزونه Coupon را خودکار ایجاد نمی‌کند.
- WooCommerce Coupon Restrictions را جایگزین نمی‌کند.
- بین تمام Couponهای دیتابیس الزاماً بهترین تخفیف مالی را محاسبه و انتخاب نمی‌کند.

## چه کارهایی انجام نمی‌دهد؟

این افزونه:

- سیستم Loyalty کامل نیست.
- Customer Identity Verification انجام نمی‌دهد.
- مانع استفاده از Email جدید توسط یک فرد در سطح هویتی نمی‌شود.
- Coupon جدید را خودکار Generate نمی‌کند.
- Coupon Engine WooCommerce را Replace نمی‌کند.
- Order History را در Custom Table کپی نمی‌کند.
- تخفیف را صرفاً با JavaScript Enforce نمی‌کند.

## غیرفعال‌سازی و داده‌ها

Deactivate کردن افزونه لزوماً به معنی حذف Coupon Meta یا User Preference نیست.

این رفتار از تغییر ناخواسته تنظیمات Couponها جلوگیری می‌کند.

داده‌های اصلی افزونه:

`_wcfod_first_order_only`

و:

`_wcfod_offer_dismissed`

هستند.

## GitHub Description پیشنهادی

`Restrict WooCommerce coupons to first-time customers and show eligible first-order discounts at checkout with server-side validation and HPOS support.`

## مجوز

GPL-3.0

## نویسنده

**Amirreza Shayesteh Far**

GitHub: `https://github.com/amirrezashf`
