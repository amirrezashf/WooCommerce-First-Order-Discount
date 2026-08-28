<?php
/**
 * Plugin Name:       WooCommerce First Order Discount
 * Plugin URI:        https://github.com/amirrezashf/WooCommerce-First-Order-Discount
 * Description:       Mark WooCommerce coupons as first-order-only and optionally present the latest eligible coupon to logged-in first-time customers at checkout.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Amirreza Shayesteh Far
 * Author URI:        https://github.com/amirrezashf
 * License:           GPL-3.0
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       wc-first-order-discount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WC_First_Order_Discount {

	const VERSION             = '1.0.0';
	const COUPON_META_KEY     = '_wcfod_first_order_only';
	const DISMISSED_META_KEY  = '_wcfod_offer_dismissed';
	const NONCE_ACTION        = 'wcfod_checkout_offer';
	const AJAX_BOOT           = 'wcfod_boot';
	const AJAX_APPLY          = 'wcfod_apply';
	const AJAX_DISMISS        = 'wcfod_dismiss';

	/**
	 * @var WC_First_Order_Discount|null
	 */
	private static $instance = null;

	/**
	 * @return WC_First_Order_Discount
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function declare_hpos_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}

	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'render_missing_woocommerce_notice' ) );
			return;
		}

		add_action(
			'woocommerce_coupon_options_usage_restriction',
			array( $this, 'render_coupon_field' ),
			10,
			2
		);

		add_action(
			'woocommerce_coupon_options_save',
			array( $this, 'save_coupon_field' ),
			10,
			2
		);

		add_filter(
			'woocommerce_coupon_is_valid',
			array( $this, 'validate_coupon' ),
			10,
			3
		);

		add_filter(
			'woocommerce_coupon_error',
			array( $this, 'filter_coupon_error_message' ),
			10,
			3
		);

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_assets' ) );

		add_action( 'wp_ajax_' . self::AJAX_BOOT, array( $this, 'ajax_boot' ) );
		add_action( 'wp_ajax_' . self::AJAX_APPLY, array( $this, 'ajax_apply' ) );
		add_action( 'wp_ajax_' . self::AJAX_DISMISS, array( $this, 'ajax_dismiss' ) );
	}

	public function render_missing_woocommerce_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__(
			'WooCommerce First Order Discount requires WooCommerce to be installed and active.',
			'wc-first-order-discount'
		);
		echo '</p></div>';
	}

	/**
	 * Coupon editor field.
	 *
	 * @param int       $coupon_id Coupon ID.
	 * @param WC_Coupon $coupon   Coupon object.
	 */
	public function render_coupon_field( $coupon_id, $coupon ) {
		if ( ! $coupon instanceof WC_Coupon ) {
			return;
		}

		woocommerce_wp_checkbox(
			array(
				'id'          => self::COUPON_META_KEY,
				'label'       => esc_html__( 'فقط اولین خرید', 'wc-first-order-discount' ),
				'description' => esc_html__(
					'این کوپن فقط برای مشتری‌ای معتبر است که سفارش قبلی در وضعیت پرداخت‌شده/فعال نداشته باشد.',
					'wc-first-order-discount'
				),
				'desc_tip'    => true,
				'value'       => 'yes' === $coupon->get_meta( self::COUPON_META_KEY, true ) ? 'yes' : 'no',
			)
		);
	}

	/**
	 * Save coupon editor field.
	 *
	 * WooCommerce performs its own coupon-edit nonce/capability validation
	 * before firing this save hook. We still require coupon-management access.
	 *
	 * @param int       $post_id Coupon ID.
	 * @param WC_Coupon $coupon Coupon object.
	 */
	public function save_coupon_field( $post_id, $coupon ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! $coupon instanceof WC_Coupon ) {
			return;
		}

		$value = isset( $_POST[ self::COUPON_META_KEY ] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$coupon->update_meta_data( self::COUPON_META_KEY, $value );
		$coupon->save_meta_data();
	}

	/**
	 * Statuses that count as an existing purchase.
	 *
	 * @return array
	 */
	private function get_previous_purchase_statuses() {
		$statuses = function_exists( 'wc_get_is_paid_statuses' )
			? wc_get_is_paid_statuses()
			: array( 'processing', 'completed' );

		$statuses = array_map(
			static function ( $status ) {
				$status = sanitize_key( (string) $status );
				return 0 === strpos( $status, 'wc-' ) ? $status : 'wc-' . $status;
			},
			(array) $statuses
		);

		/**
		 * Filter the order statuses that disqualify a customer from a
		 * first-order coupon.
		 *
		 * @param array $statuses WooCommerce order statuses, prefixed with wc-.
		 */
		$statuses = apply_filters( 'wcfod_previous_purchase_statuses', $statuses );

		return array_values( array_unique( array_filter( (array) $statuses ) ) );
	}

	/**
	 * Determine whether the customer has a previous qualifying purchase.
	 *
	 * Both customer ID and billing email are checked. This matters when a
	 * registered customer previously placed an order as a guest using the
	 * same billing email.
	 *
	 * @param int    $user_id Customer user ID.
	 * @param string $email   Billing email.
	 * @return bool
	 */
	private function has_previous_purchase( $user_id = 0, $email = '' ) {
		$statuses = $this->get_previous_purchase_statuses();

		if ( empty( $statuses ) ) {
			return false;
		}

		$base_args = array(
			'status'   => $statuses,
			'limit'    => 1,
			'return'   => 'ids',
			'paginate' => false,
		);

		$user_id = absint( $user_id );

		if ( $user_id > 0 ) {
			$user_orders = wc_get_orders(
				array_merge(
					$base_args,
					array(
						'customer_id' => $user_id,
					)
				)
			);

			if ( ! empty( $user_orders ) ) {
				return true;
			}
		}

		$email = sanitize_email( $email );

		if ( '' !== $email ) {
			$email_orders = wc_get_orders(
				array_merge(
					$base_args,
					array(
						'billing_email' => $email,
					)
				)
			);

			if ( ! empty( $email_orders ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param WC_Coupon $coupon Coupon.
	 * @return bool
	 */
	private function is_first_order_coupon( $coupon ) {
		return $coupon instanceof WC_Coupon
			&& 'yes' === $coupon->get_meta( self::COUPON_META_KEY, true );
	}

	/**
	 * Return the newest published coupon marked as first-order-only that
	 * passes inexpensive availability checks.
	 *
	 * @return WC_Coupon|null
	 */
	private function get_offer_coupon() {
		$coupon_ids = get_posts(
			array(
				'post_type'              => 'shop_coupon',
				'post_status'            => 'publish',
				'posts_per_page'         => 10,
				'fields'                 => 'ids',
				'meta_key'               => self::COUPON_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'             => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $coupon_ids as $coupon_id ) {
			$coupon = new WC_Coupon( $coupon_id );

			if ( ! $this->is_coupon_basically_available( $coupon ) ) {
				continue;
			}

			/**
			 * Filter the coupon chosen for the checkout offer.
			 *
			 * Return null/false to suppress the offer.
			 *
			 * @param WC_Coupon $coupon Selected coupon.
			 */
			$filtered = apply_filters( 'wcfod_offer_coupon', $coupon );

			return $filtered instanceof WC_Coupon ? $filtered : null;
		}

		return null;
	}

	/**
	 * Cheap checks before showing a coupon in the offer UI.
	 *
	 * Full coupon/cart restrictions are still validated by WooCommerce when
	 * the coupon is actually applied.
	 *
	 * @param WC_Coupon $coupon Coupon.
	 * @return bool
	 */
	private function is_coupon_basically_available( $coupon ) {
		if ( ! $coupon instanceof WC_Coupon || ! $coupon->get_id() || '' === $coupon->get_code() ) {
			return false;
		}

		$expires = $coupon->get_date_expires();

		if ( $expires && $expires->getTimestamp() < time() ) {
			return false;
		}

		$usage_limit = absint( $coupon->get_usage_limit() );

		if ( $usage_limit > 0 && absint( $coupon->get_usage_count() ) >= $usage_limit ) {
			return false;
		}

		return true;
	}

	/**
	 * Format coupon amount for the frontend.
	 *
	 * @param WC_Coupon $coupon Coupon.
	 * @return string
	 */
	private function amount_label( $coupon ) {
		$amount = (float) $coupon->get_amount();
		$type   = (string) $coupon->get_discount_type();

		if ( false !== strpos( $type, 'percent' ) ) {
			return number_format_i18n( $amount, 0 ) . '٪';
		}

		return wp_strip_all_tags(
			wc_price(
				$amount,
				array(
					'currency' => get_woocommerce_currency(),
				)
			)
		);
	}

	/**
	 * Resolve the customer's current billing email.
	 *
	 * @return string
	 */
	private function get_current_customer_email() {
		$user_id = get_current_user_id();

		if ( $user_id ) {
			$user = get_userdata( $user_id );

			if ( $user instanceof WP_User ) {
				$billing_email = sanitize_email( (string) get_user_meta( $user_id, 'billing_email', true ) );

				if ( '' !== $billing_email ) {
					return $billing_email;
				}

				return sanitize_email( (string) $user->user_email );
			}
		}

		if ( function_exists( 'WC' ) && WC()->customer ) {
			return sanitize_email( (string) WC()->customer->get_billing_email() );
		}

		return '';
	}

	/**
	 * Server-side coupon validation.
	 *
	 * @param bool       $valid     Current validity.
	 * @param WC_Coupon  $coupon    Coupon.
	 * @param WC_Discounts $discounts Discounts object.
	 * @return bool
	 * @throws Exception When the coupon is restricted to first orders and the
	 *                   customer already has a qualifying order.
	 */
	public function validate_coupon( $valid, $coupon, $discounts ) {
		if ( ! $valid || ! $this->is_first_order_coupon( $coupon ) ) {
			return $valid;
		}

		$user_id = get_current_user_id();
		$email   = $this->get_current_customer_email();

		if ( $this->has_previous_purchase( $user_id, $email ) ) {
			throw new Exception(
				esc_html__( 'این کد تخفیف فقط برای اولین خرید قابل استفاده است.', 'wc-first-order-discount' ),
				WC_Coupon::E_WC_COUPON_INVALID_FILTERED
			);
		}

		return $valid;
	}

	/**
	 * @param string     $message  Existing message.
	 * @param int        $err_code Coupon error code.
	 * @param WC_Coupon  $coupon   Coupon.
	 * @return string
	 */
	public function filter_coupon_error_message( $message, $err_code, $coupon ) {
		if (
			WC_Coupon::E_WC_COUPON_INVALID_FILTERED === (int) $err_code
			&& $this->is_first_order_coupon( $coupon )
		) {
			return esc_html__( 'این کد تخفیف فقط برای اولین خرید قابل استفاده است.', 'wc-first-order-discount' );
		}

		return $message;
	}

	/**
	 * Load the offer only on checkout for logged-in customers.
	 *
	 * The coupon itself remains protected server-side for guest/manual usage;
	 * only the automatic promotional UI is limited to logged-in customers.
	 */
	public function enqueue_checkout_assets() {
		if (
			! function_exists( 'is_checkout' )
			|| ! is_checkout()
			|| function_exists( 'is_order_received_page' ) && is_order_received_page()
			|| ! is_user_logged_in()
		) {
			return;
		}

		wp_register_style( 'wcfod-checkout', false, array(), self::VERSION );
		wp_enqueue_style( 'wcfod-checkout' );
		wp_add_inline_style( 'wcfod-checkout', $this->get_css() );

		wp_register_script( 'wcfod-checkout', '', array(), self::VERSION, true );
		wp_enqueue_script( 'wcfod-checkout' );

		wp_add_inline_script(
			'wcfod-checkout',
			'window.WCFOD=' . wp_json_encode(
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
					'actions' => array(
						'boot'    => self::AJAX_BOOT,
						'apply'   => self::AJAX_APPLY,
						'dismiss' => self::AJAX_DISMISS,
					),
					'strings' => array(
						'communicationError' => 'خطای ارتباط با سرور.',
						'applyError'         => 'اعمال کد تخفیف انجام نشد.',
						'applying'           => 'در حال اعمال…',
						'apply'              => 'اعمال کد تخفیف',
					),
				)
			) . ';',
			'before'
		);

		wp_add_inline_script( 'wcfod-checkout', $this->get_js() );
	}

	private function verify_ajax_request() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'برای استفاده از این پیشنهاد باید وارد حساب کاربری شوید.' ), 403 );
		}
	}

	public function ajax_boot() {
		$this->verify_ajax_request();

		$user_id = get_current_user_id();
		$email   = $this->get_current_customer_email();

		if ( $this->has_previous_purchase( $user_id, $email ) ) {
			wp_send_json_success( array( 'show' => false ) );
		}

		if ( '1' === get_user_meta( $user_id, self::DISMISSED_META_KEY, true ) ) {
			wp_send_json_success( array( 'show' => false ) );
		}

		$coupon = $this->get_offer_coupon();

		if ( ! $coupon ) {
			wp_send_json_success( array( 'show' => false ) );
		}

		$applied = WC()->cart ? WC()->cart->get_applied_coupons() : array();

		if ( in_array( wc_strtolower( $coupon->get_code() ), array_map( 'wc_strtolower', $applied ), true ) ) {
			wp_send_json_success( array( 'show' => false ) );
		}

		wp_send_json_success(
			array(
				'show'         => true,
				'code'         => $coupon->get_code(),
				'amount_label' => $this->amount_label( $coupon ),
			)
		);
	}

	public function ajax_apply() {
		$this->verify_ajax_request();

		if ( ! WC()->cart ) {
			wp_send_json_error( array( 'message' => 'سبد خرید در دسترس نیست.' ), 400 );
		}

		$user_id = get_current_user_id();
		$email   = $this->get_current_customer_email();

		if ( $this->has_previous_purchase( $user_id, $email ) ) {
			wp_send_json_error(
				array( 'message' => 'این کد تخفیف فقط برای اولین خرید قابل استفاده است.' ),
				400
			);
		}

		$coupon = $this->get_offer_coupon();

		if ( ! $coupon ) {
			wp_send_json_error( array( 'message' => 'کوپن قابل استفاده‌ای یافت نشد.' ), 404 );
		}

		$code    = $coupon->get_code();
		$applied = WC()->cart->get_applied_coupons();

		if ( in_array( wc_strtolower( $code ), array_map( 'wc_strtolower', $applied ), true ) ) {
			wp_send_json_success(
				array(
					'applied' => true,
					'already' => true,
				)
			);
		}

		wc_clear_notices();

		$result = WC()->cart->apply_coupon( $code );

		ob_start();
		wc_print_notices();
		$notices = (string) ob_get_clean();

		if ( ! $result ) {
			wp_send_json_error(
				array(
					'message' => $notices ? wp_strip_all_tags( $notices ) : 'اعمال کد تخفیف انجام نشد.',
				),
				400
			);
		}

		WC()->cart->calculate_totals();

		wp_send_json_success(
			array(
				'applied' => true,
				'notices' => $notices,
			)
		);
	}

	public function ajax_dismiss() {
		$this->verify_ajax_request();

		$action = isset( $_POST['dismiss_action'] )
			? sanitize_key( wp_unslash( $_POST['dismiss_action'] ) )
			: 'later';

		if ( 'never' === $action ) {
			update_user_meta( get_current_user_id(), self::DISMISSED_META_KEY, '1' );
		}

		wp_send_json_success( array( 'dismissed' => true ) );
	}

	private function get_js() {
		return <<<'JS'
(function(){
	'use strict';

	if (!window.WCFOD || !window.fetch || !window.URLSearchParams) {
		return;
	}

	const cfg = window.WCFOD;
	let sheet = null;
	let pending = false;
	let previousFocus = null;

	function post(action, extra) {
		const body = new URLSearchParams();
		body.set('action', action);
		body.set('nonce', cfg.nonce);

		if (extra) {
			Object.keys(extra).forEach(function(key){
				body.set(key, extra[key]);
			});
		}

		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		}).then(function(response){
			return response.json().catch(function(){
				throw new Error(cfg.strings.communicationError);
			});
		});
	}

	function escapeHtml(value) {
		const div = document.createElement('div');
		div.textContent = value == null ? '' : String(value);
		return div.innerHTML;
	}

	function openSheet(data) {
		if (sheet) {
			return;
		}

		previousFocus = document.activeElement;

		const root = document.createElement('div');
		root.className = 'wcfod';
		root.id = 'wcfod-sheet';
		root.setAttribute('role', 'dialog');
		root.setAttribute('aria-modal', 'true');
		root.setAttribute('aria-labelledby', 'wcfod-title');

		root.innerHTML =
			'<div class="wcfod__scrim" data-dismiss="later"></div>' +
			'<div class="wcfod__panel" role="document">' +
				'<div class="wcfod__handle" aria-hidden="true"></div>' +
				'<div class="wcfod__icon" aria-hidden="true">' +
					'<svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12v10H4V12"/><path d="M2 7h20v5H2z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>' +
				'</div>' +
				'<h2 class="wcfod__title" id="wcfod-title">تخفیف اولین خرید</h2>' +
				'<p class="wcfod__desc">برای اولین خرید می‌توانید از <strong>' + escapeHtml(data.amount_label) + '</strong> تخفیف این سفارش استفاده کنید.</p>' +
				'<div class="wcfod__code">' +
					'<span class="wcfod__code-text">' + escapeHtml(data.code) + '</span>' +
					'<button type="button" class="wcfod__copy" data-copy aria-label="کپی کد تخفیف">کپی</button>' +
				'</div>' +
				'<button type="button" class="wcfod__apply" data-apply>' + escapeHtml(cfg.strings.apply) + '</button>' +
				'<div class="wcfod__actions">' +
					'<button type="button" class="wcfod__never" data-dismiss="never">دیگر نمایش نده</button>' +
					'<button type="button" class="wcfod__later" data-dismiss="later">الان نه</button>' +
				'</div>' +
				'<div class="wcfod__message" role="status" aria-live="polite"></div>' +
			'</div>';

		document.body.appendChild(root);
		sheet = root;

		requestAnimationFrame(function(){
			sheet.classList.add('is-open');
			const focusTarget = sheet.querySelector('[data-apply]');
			if (focusTarget) {
				focusTarget.focus();
			}
		});

		sheet.addEventListener('click', onSheetClick);
		document.addEventListener('keydown', onKeydown);
	}

	function closeSheet() {
		if (!sheet) {
			return;
		}

		const current = sheet;
		current.classList.remove('is-open');
		current.classList.add('is-closing');

		document.removeEventListener('keydown', onKeydown);

		window.setTimeout(function(){
			current.remove();
			if (sheet === current) {
				sheet = null;
			}

			if (previousFocus && typeof previousFocus.focus === 'function') {
				previousFocus.focus();
			}
		}, 190);
	}

	function dismiss(action) {
		post(cfg.actions.dismiss, {
			dismiss_action: action || 'later'
		}).catch(function(){});

		closeSheet();
	}

	function copyCode(button) {
		if (!sheet) {
			return;
		}

		const code = sheet.querySelector('.wcfod__code-text');
		if (!code) {
			return;
		}

		const text = (code.textContent || '').trim();

		function done() {
			const old = button.textContent;
			button.textContent = 'کپی شد';
			button.classList.add('is-done');

			window.setTimeout(function(){
				button.textContent = old;
				button.classList.remove('is-done');
			}, 1500);
		}

		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(done).catch(function(){});
			return;
		}

		const textarea = document.createElement('textarea');
		textarea.value = text;
		textarea.setAttribute('readonly', '');
		textarea.style.position = 'fixed';
		textarea.style.top = '-9999px';
		document.body.appendChild(textarea);
		textarea.select();

		try {
			document.execCommand('copy');
			done();
		} catch (error) {
		}

		textarea.remove();
	}

	function applyCode(button) {
		if (pending) {
			return;
		}

		pending = true;
		const original = button.textContent;
		const message = sheet ? sheet.querySelector('.wcfod__message') : null;

		button.disabled = true;
		button.textContent = cfg.strings.applying;

		if (message) {
			message.textContent = '';
			message.className = 'wcfod__message';
		}

		post(cfg.actions.apply).then(function(response){
			if (!response || !response.success) {
				throw new Error(
					response && response.data && response.data.message
						? response.data.message
						: cfg.strings.applyError
				);
			}

			if (message) {
				message.textContent = 'کد تخفیف با موفقیت اعمال شد.';
				message.classList.add('is-success');
			}

			window.setTimeout(function(){
				window.location.reload();
			}, 500);
		}).catch(function(error){
			pending = false;
			button.disabled = false;
			button.textContent = original;

			if (message) {
				message.textContent = error && error.message ? error.message : cfg.strings.applyError;
				message.classList.add('is-error');
			}
		});
	}

	function onSheetClick(event) {
		const copyButton = event.target.closest('[data-copy]');
		if (copyButton) {
			event.preventDefault();
			copyCode(copyButton);
			return;
		}

		const applyButton = event.target.closest('[data-apply]');
		if (applyButton) {
			event.preventDefault();
			applyCode(applyButton);
			return;
		}

		const dismissButton = event.target.closest('[data-dismiss]');
		if (dismissButton) {
			event.preventDefault();
			dismiss(dismissButton.getAttribute('data-dismiss') || 'later');
		}
	}

	function onKeydown(event) {
		if (!sheet) {
			return;
		}

		if (event.key === 'Escape') {
			event.preventDefault();
			dismiss('later');
			return;
		}

		if (event.key !== 'Tab') {
			return;
		}

		const focusable = Array.from(
			sheet.querySelectorAll('button:not([disabled]),a[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')
		);

		if (!focusable.length) {
			return;
		}

		const first = focusable[0];
		const last = focusable[focusable.length - 1];

		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	}

	function boot() {
		post(cfg.actions.boot).then(function(response){
			if (response && response.success && response.data && response.data.show) {
				openSheet(response.data);
			}
		}).catch(function(){});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot, { once: true });
	} else {
		boot();
	}
})();
JS;
	}

	private function get_css() {
		return <<<'CSS'
.wcfod{
	--wcfod-accent:#3858e9;
	--wcfod-accent-hover:#2145d8;
	--wcfod-success:#16803c;
	--wcfod-danger:#b42318;
	--wcfod-text:#111827;
	--wcfod-muted:#6b7280;
	--wcfod-border:#e5e7eb;
	--wcfod-surface:#fff;
	--wcfod-scrim:rgba(17,24,39,.48);
	position:fixed;
	inset:0;
	z-index:100100;
	display:flex;
	align-items:flex-end;
	justify-content:center;
	direction:rtl;
	font-family:inherit;
	opacity:0;
	pointer-events:none;
	transition:opacity .2s ease;
}
.wcfod.is-open{
	opacity:1;
	pointer-events:auto;
}
.wcfod__scrim{
	position:absolute;
	inset:0;
	background:var(--wcfod-scrim);
}
.wcfod__panel{
	position:relative;
	width:100%;
	max-width:430px;
	box-sizing:border-box;
	padding:16px 20px calc(20px + env(safe-area-inset-bottom));
	border-radius:20px 20px 0 0;
	background:var(--wcfod-surface);
	box-shadow:0 -10px 36px rgba(17,24,39,.18);
	transform:translateY(100%);
	transition:transform .28s cubic-bezier(.22,1,.36,1);
}
.wcfod.is-open .wcfod__panel{transform:translateY(0)}
.wcfod.is-closing .wcfod__panel{transform:translateY(100%)}
.wcfod__handle{
	width:40px;
	height:4px;
	margin:0 auto 14px;
	border-radius:999px;
	background:var(--wcfod-border);
}
.wcfod__icon{
	display:flex;
	justify-content:center;
	margin-bottom:9px;
	color:var(--wcfod-accent);
}
.wcfod__title{
	margin:0 0 7px;
	color:var(--wcfod-text);
	font-size:18px;
	font-weight:700;
	line-height:1.6;
	text-align:center;
}
.wcfod__desc{
	margin:0 0 15px;
	color:var(--wcfod-muted);
	font-size:13px;
	line-height:1.9;
	text-align:center;
}
.wcfod__desc strong{color:var(--wcfod-accent)}
.wcfod__code{
	display:flex;
	align-items:center;
	justify-content:space-between;
	gap:10px;
	margin-top:14px;
	padding:10px 12px;
	border:1px dashed #aeb9ed;
	border-radius:10px;
	background:#f7f8ff;
}
.wcfod__code-text{
	color:var(--wcfod-text);
	font-size:15px;
	font-weight:700;
	letter-spacing:.02em;
	direction:ltr;
}
.wcfod__copy{
	flex:0 0 auto;
	min-height:34px;
	padding:5px 10px;
	border:1px solid #c7d0f5;
	border-radius:7px;
	background:#fff;
	color:var(--wcfod-accent);
	font:inherit;
	font-size:12px;
	font-weight:700;
	cursor:pointer;
}
.wcfod__copy.is-done{
	border-color:#b7ddc4;
	color:var(--wcfod-success);
}
.wcfod__apply{
	width:100%;
	min-height:44px;
	margin-top:12px;
	padding:9px 14px;
	border:1px solid var(--wcfod-accent);
	border-radius:9px;
	background:var(--wcfod-accent);
	color:#fff;
	font:inherit;
	font-size:13px;
	font-weight:700;
	cursor:pointer;
}
.wcfod__apply:hover{background:var(--wcfod-accent-hover)}
.wcfod__apply:disabled{opacity:.65;cursor:wait}
.wcfod__actions{
	display:flex;
	gap:8px;
	margin-top:9px;
}
.wcfod__later,
.wcfod__never{
	min-height:40px;
	padding:8px 11px;
	border:1px solid var(--wcfod-border);
	border-radius:8px;
	background:#fff;
	color:var(--wcfod-muted);
	font:inherit;
	font-size:12px;
	cursor:pointer;
}
.wcfod__later{flex:1}
.wcfod__never{flex:1;background:#f6f7f7}
.wcfod__message{
	min-height:18px;
	margin-top:9px;
	font-size:12px;
	line-height:1.8;
	text-align:center;
}
.wcfod__message.is-success{color:var(--wcfod-success)}
.wcfod__message.is-error{color:var(--wcfod-danger)}
.wcfod button:focus-visible{
	outline:2px solid var(--wcfod-accent);
	outline-offset:2px;
}
@media (min-width:768px){
	.wcfod{align-items:center}
	.wcfod__panel{
		border-radius:20px;
		transform:scale(.96);
	}
	.wcfod.is-open .wcfod__panel{transform:scale(1)}
	.wcfod.is-closing .wcfod__panel{transform:scale(.96)}
}
@media (max-width:767px){
	.wcfod__panel{max-width:100%}
}
@media (prefers-reduced-motion:reduce){
	.wcfod,
	.wcfod__panel{transition:none}
	.wcfod.is-open .wcfod__panel,
	.wcfod.is-closing .wcfod__panel{transform:none}
}
CSS;
	}
}

WC_First_Order_Discount::instance();
