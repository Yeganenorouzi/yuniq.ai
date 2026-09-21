<?php
/**
 * صفحه تنظیمات ادمین
 *
 * @package Yuniq\Ai
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$s = $settings;
$has_woo = class_exists( 'WooCommerce' );
?>
<div class="wrap yuniq-ai-admin-wrap" dir="rtl">
	<div class="yuniq-ai-header">
		<div class="yuniq-ai-header-brand">
			<span class="yuniq-ai-logo">✦</span>
			<div>
				<h1>تنظیمات دستیار هوشمند</h1>
				<p class="yuniq-ai-subtitle">پیکربندی کامل دستیار هوش مصنوعی · توسعه‌یافته توسط یگانه نوروزی</p>
			</div>
		</div>
	</div>

	<?php settings_errors(); ?>

	<?php // novalidate: every field is already validated/clamped server-side in Settings::sanitize(); native constraint validation only risks silently blocking submission when the invalid field sits inside a currently-hidden tab (the browser can't scroll to focus it, so it just logs a console warning instead of showing anything). ?>
	<form method="post" action="options.php" id="yuniq-ai-settings-form" novalidate>
		<?php settings_fields( 'yuniq_ai_settings_group' ); ?>

		<div class="yuniq-ai-tabs">
			<nav class="yuniq-ai-tab-nav">
				<button type="button" class="yuniq-ai-tab-btn active" data-tab="general">عمومی</button>
				<button type="button" class="yuniq-ai-tab-btn" data-tab="ai">API هوش مصنوعی</button>
				<button type="button" class="yuniq-ai-tab-btn" data-tab="crawler">خزنده محتوا</button>
				<button type="button" class="yuniq-ai-tab-btn" data-tab="appearance">طراحی</button>
				<button type="button" class="yuniq-ai-tab-btn" data-tab="live-support">پشتیبانی زنده</button>
			</nav>

			<!-- عمومی -->
			<div class="yuniq-ai-tab-panel active" id="tab-general">
				<div class="yuniq-ai-card">
					<h2>تنظیمات عمومی</h2>
					<table class="form-table">
						<tr>
							<th scope="row">فعال‌سازی دستیار</th>
							<td>
								<label class="yuniq-ai-switch">
									<input type="checkbox" name="yuniq_ai_settings[enabled]" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?> />
									<span class="slider"></span>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">نام دستیار</th>
							<td>
								<input type="text" name="yuniq_ai_settings[assistant_name]" value="<?php echo esc_attr( isset( $s['assistant_name'] ) ? $s['assistant_name'] : 'دستیار هوشمند' ); ?>" class="regular-text" />
							</td>
						</tr>
						<tr>
							<th scope="row">پیام خوش‌آمدگویی</th>
							<td>
								<textarea name="yuniq_ai_settings[welcome_message]" rows="2" class="large-text"><?php echo esc_textarea( isset( $s['welcome_message'] ) ? $s['welcome_message'] : '' ); ?></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row">زیرعنوان هدر</th>
							<td>
								<input type="text" name="yuniq_ai_settings[header_subtitle]" value="<?php echo esc_attr( isset( $s['header_subtitle'] ) ? $s['header_subtitle'] : 'پاسخ سریع، دقیق و حرفه‌ای' ); ?>" class="regular-text" />
							</td>
						</tr>
						<tr>
							<th scope="row">عنوان بخش راهنما</th>
							<td>
								<input type="text" name="yuniq_ai_settings[help_title]" value="<?php echo esc_attr( isset( $s['help_title'] ) ? $s['help_title'] : 'چطور میتوانم کمک کنم؟' ); ?>" class="regular-text" />
							</td>
						</tr>
						<tr>
							<th scope="row">توضیح راهنما</th>
							<td>
								<input type="text" name="yuniq_ai_settings[help_subtitle]" value="<?php echo esc_attr( isset( $s['help_subtitle'] ) ? $s['help_subtitle'] : '' ); ?>" class="large-text" />
							</td>
						</tr>
						<tr>
							<th scope="row">متن پیشنهاد (چیپ)</th>
							<td>
								<input type="text" name="yuniq_ai_settings[suggestion_text]" value="<?php echo esc_attr( isset( $s['suggestion_text'] ) ? $s['suggestion_text'] : '' ); ?>" class="large-text" />
							</td>
						</tr>
						<tr>
							<th scope="row">لوگوی هدر</th>
							<td>
								<input type="url" name="yuniq_ai_settings[logo_url]" id="yuniq-ai-logo-url" value="<?php echo esc_attr( isset( $s['logo_url'] ) ? $s['logo_url'] : '' ); ?>" class="regular-text" />
								<button type="button" class="button" id="yuniq-ai-upload-logo">آپلود لوگو</button>
							</td>
						</tr>
					</table>
				</div>
				<div class="yuniq-ai-card">
					<h2>کارت‌های اقدام سریع</h2>
					<p class="description">این کارت‌ها بالای کادر پیام در پنل دستیار نمایش داده می‌شوند.</p>
					<div id="yuniq-ai-quick-actions">
						<?php
						$actions = isset( $s['quick_actions'] ) && is_array( $s['quick_actions'] ) ? $s['quick_actions'] : array();
						if ( empty( $actions ) ) {
							$actions = array( array( 'label' => '', 'prompt' => '' ) );
						}
						foreach ( $actions as $i => $action ) :
							?>
							<div class="yuniq-ai-qa-row" style="flex-wrap:wrap;">
								<input type="text" name="yuniq_ai_settings[quick_actions][<?php echo esc_attr( $i ); ?>][label]" value="<?php echo esc_attr( $action['label'] ); ?>" placeholder="عنوان کارت" style="width:110px;" />
								<input type="text" name="yuniq_ai_settings[quick_actions][<?php echo esc_attr( $i ); ?>][desc]" value="<?php echo esc_attr( isset( $action['desc'] ) ? $action['desc'] : '' ); ?>" placeholder="توضیح کوتاه" style="width:110px;" />
								<input type="text" name="yuniq_ai_settings[quick_actions][<?php echo esc_attr( $i ); ?>][prompt]" value="<?php echo esc_attr( $action['prompt'] ); ?>" placeholder="پرامپت AI (اگر لینک خالی باشد)" style="width:180px;" />
								<input type="text" name="yuniq_ai_settings[quick_actions][<?php echo esc_attr( $i ); ?>][link]" value="<?php echo esc_attr( isset( $action['link'] ) ? $action['link'] : '' ); ?>" placeholder="لینک اختیاری (مثلاً /services/)" style="width:180px;" />
								<button type="button" class="button yuniq-ai-remove-qa">&times;</button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button" id="yuniq-ai-add-qa">+ افزودن اقدام</button>
				</div>
			</div>

			<!-- AI API -->
			<div class="yuniq-ai-tab-panel" id="tab-ai">
				<div class="yuniq-ai-card">
					<h2>تنظیمات API هوش مصنوعی</h2>
					<table class="form-table">
						<tr>
							<th scope="row">ارائه‌دهنده</th>
							<td>
								<select name="yuniq_ai_settings[ai_provider]">
									<option value="openai" <?php selected( isset( $s['ai_provider'] ) ? $s['ai_provider'] : '', 'openai' ); ?>>OpenAI</option>
									<option value="custom" <?php selected( isset( $s['ai_provider'] ) ? $s['ai_provider'] : '', 'custom' ); ?>>API سفارشی</option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">کلید API</th>
							<td>
								<input type="password" name="yuniq_ai_settings[api_key]" value="<?php echo esc_attr( empty( $s['api_key'] ) ? '' : \Yuniq\Ai\Settings::SECRET_MASK ); ?>" class="regular-text" autocomplete="off" />
								<p class="description"><?php esc_html_e( 'کلید ذخیره‌شده هرگز در این صفحه نمایش داده نمی‌شود. برای تغییر، کلید جدید را وارد کنید؛ برای حفظ کلید فعلی این فیلد را دست نزنید.', 'yuniq-ai' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">آدرس Endpoint</th>
							<td>
								<input type="url" name="yuniq_ai_settings[api_endpoint]" value="<?php echo esc_attr( isset( $s['api_endpoint'] ) ? $s['api_endpoint'] : 'https://api.openai.com/v1/chat/completions' ); ?>" class="large-text" />
							</td>
						</tr>
						<tr>
							<th scope="row">مدل</th>
							<td>
								<input type="text" name="yuniq_ai_settings[model]" value="<?php echo esc_attr( isset( $s['model'] ) ? $s['model'] : 'gpt-4o-mini' ); ?>" class="regular-text" />
							</td>
						</tr>
						<tr>
							<th scope="row">دما (Temperature)</th>
							<td>
								<input type="number" name="yuniq_ai_settings[temperature]" value="<?php echo esc_attr( isset( $s['temperature'] ) ? $s['temperature'] : 0.7 ); ?>" min="0" max="2" step="0.1" />
							</td>
						</tr>
						<tr>
							<th scope="row">حداکثر توکن</th>
							<td>
								<input type="number" name="yuniq_ai_settings[max_tokens]" value="<?php echo esc_attr( isset( $s['max_tokens'] ) ? $s['max_tokens'] : 1024 ); ?>" min="64" max="8192" step="64" />
							</td>
						</tr>
						<tr>
							<th scope="row">پرامپت سیستم</th>
							<td>
								<textarea name="yuniq_ai_settings[system_prompt]" rows="6" class="large-text"><?php echo esc_textarea( isset( $s['system_prompt'] ) ? $s['system_prompt'] : '' ); ?></textarea>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- خزنده هوشمند -->
			<div class="yuniq-ai-tab-panel" id="tab-crawler">
				<div class="yuniq-ai-card">
					<h2>انتخاب محتوای قابل ایندکس</h2>
					<p class="description">فقط مواردی که تیک می‌زنید کرال و وارد پایگاه دانش می‌شوند. بعد از ذخیره، به «پایگاه دانش» بروید و ایندکس را اجرا کنید.</p>

					<?php
					// Detect public post types dynamically
					$public_types = get_post_types( array( 'public' => true ), 'objects' );
					unset( $public_types['attachment'] );
					$has_woo = class_exists( 'WooCommerce' );
					?>

					<div class="yuniq-ai-crawl-checklist">
						<?php if ( $has_woo ) : ?>
						<div class="yuniq-ai-check-group">
							<h3>ووکامرس <span class="yuniq-ai-badge-ok">شناسایی شد</span></h3>
							<label><input type="checkbox" name="yuniq_ai_settings[wc_products]" value="1" <?php checked( ! empty( $s['wc_products'] ) || ( ! isset( $s['wc_products'] ) && in_array( 'product', (array) ( $s['content_types'] ?? array() ), true ) ) ); ?> /> <strong>محصولات</strong> <code>product</code></label>
							<label><input type="checkbox" name="yuniq_ai_settings[wc_product_categories]" value="1" <?php checked( ! empty( $s['wc_product_categories'] ) ); ?> /> دسته‌بندی محصولات <code>product_cat</code></label>
							<label><input type="checkbox" name="yuniq_ai_settings[wc_product_tags]" value="1" <?php checked( ! empty( $s['wc_product_tags'] ) ); ?> /> برچسب محصولات <code>product_tag</code></label>
							<label><input type="checkbox" name="yuniq_ai_settings[wc_attributes]" value="1" <?php checked( ! empty( $s['wc_attributes'] ) ); ?> /> ویژگی‌های محصول (همراه محصول)</label>
							<label><input type="checkbox" name="yuniq_ai_settings[wc_reviews]" value="1" <?php checked( ! empty( $s['wc_reviews'] ) ); ?> /> نظرات محصولات (همراه محصول)</label>
						</div>
						<?php endif; ?>

						<div class="yuniq-ai-check-group">
							<h3>محتوای وردپرس</h3>
							<label><input type="checkbox" name="yuniq_ai_settings[wp_pages]" value="1" <?php checked( ! empty( $s['wp_pages'] ) || ( ! isset( $s['wp_pages'] ) && in_array( 'page', (array) ( $s['content_types'] ?? array() ), true ) ) ); ?> /> <strong>برگه‌ها</strong> <code>page</code></label>
							<label><input type="checkbox" name="yuniq_ai_settings[wp_posts]" value="1" <?php checked( ! empty( $s['wp_posts'] ) || ( ! isset( $s['wp_posts'] ) && in_array( 'post', (array) ( $s['content_types'] ?? array() ), true ) ) ); ?> /> <strong>مقالات / نوشته‌ها</strong> <code>post</code></label>
							<label><input type="checkbox" name="yuniq_ai_settings[wp_categories]" value="1" <?php checked( ! empty( $s['wp_categories'] ) ); ?> /> دسته‌بندی مقالات <code>category</code></label>
							<label><input type="checkbox" name="yuniq_ai_settings[wp_tags]" value="1" <?php checked( ! empty( $s['wp_tags'] ) ); ?> /> برچسب مقالات <code>post_tag</code></label>
						</div>

						<?php
						$skip = array( 'post', 'page', 'product', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation', 'wp_font_family', 'wp_font_face' );
						$custom = array();
						foreach ( $public_types as $pt => $obj ) {
							if ( in_array( $pt, $skip, true ) ) continue;
							$custom[ $pt ] = $obj->labels->name;
						}
						if ( ! empty( $custom ) ) :
							$selected_cpt = isset( $s['custom_post_types'] ) && is_array( $s['custom_post_types'] ) ? $s['custom_post_types'] : array();
						?>
						<div class="yuniq-ai-check-group">
							<h3>انواع پست سفارشی</h3>
							<?php foreach ( $custom as $pt => $label ) : ?>
								<label><input type="checkbox" name="yuniq_ai_settings[custom_post_types][]" value="<?php echo esc_attr( $pt ); ?>" <?php checked( in_array( $pt, $selected_cpt, true ) || ! empty( $s['wp_custom_post_types'] ) ); ?> /> <?php echo esc_html( $label ); ?> <code><?php echo esc_html( $pt ); ?></code></label>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>
					</div>

					<table class="form-table" style="margin-top:20px;">
						<tr>
							<th scope="row">شامل کردن URLها (اختیاری)</th>
							<td>
								<textarea name="yuniq_ai_settings[include_slugs]" rows="3" class="large-text code" placeholder="/products/&#10;/services/&#10;/blog/"><?php echo esc_textarea( isset( $s['include_slugs'] ) ? $s['include_slugs'] : '' ); ?></textarea>
								<p class="description">اگر پر باشد، فقط URLهایی که شامل این مسیرها هستند ایندکس می‌شوند. هر خط یک مسیر.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">مستثنی کردن URLها</th>
							<td>
								<textarea name="yuniq_ai_settings[exclude_slugs]" rows="3" class="large-text code"><?php echo esc_textarea( isset( $s['exclude_slugs'] ) ? $s['exclude_slugs'] : "/cart/\n/checkout/\n/my-account/\n/wp-admin/" ); ?></textarea>
								<p class="description">این مسیرها هرگز کرال نمی‌شوند.</p>
							</td>
						</tr>
					</table>
					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=yuniq-ai-knowledge' ) ); ?>" class="button button-primary">رفتن به پایگاه دانش و شروع ایندکس ←</a>
					</p>
				</div>
			</div>

			<!-- طراحی -->
			<div class="yuniq-ai-tab-panel" id="tab-appearance">
				<div class="yuniq-ai-card">
					<h2>تنظیمات طراحی</h2>
					<table class="form-table">
						<tr>
							<th scope="row">لوگو / آواتار</th>
							<td>
								<input type="url" name="yuniq_ai_settings[avatar_url]" id="yuniq-ai-avatar-url" value="<?php echo esc_attr( isset( $s['avatar_url'] ) ? $s['avatar_url'] : '' ); ?>" class="regular-text" />
								<button type="button" class="button" id="yuniq-ai-upload-avatar">آپلود</button>
							</td>
						</tr>
						<tr>
							<th scope="row">رنگ اصلی</th>
							<td><input type="text" name="yuniq_ai_settings[primary_color]" value="<?php echo esc_attr( isset( $s['primary_color'] ) ? $s['primary_color'] : '#263DFF' ); ?>" class="yuniq-ai-color-picker" /></td>
						</tr>
						<tr>
							<th scope="row">رنگ ثانویه</th>
							<td><input type="text" name="yuniq_ai_settings[secondary_color]" value="<?php echo esc_attr( isset( $s['secondary_color'] ) ? $s['secondary_color'] : '#111B55' ); ?>" class="yuniq-ai-color-picker" /></td>
						</tr>
						<tr>
							<th scope="row">سبک هدر پنل</th>
							<td>
								<?php $header_style = isset( $s['header_style'] ) ? $s['header_style'] : 'gradient'; ?>
								<select name="yuniq_ai_settings[header_style]">
									<option value="gradient" <?php selected( $header_style, 'gradient' ); ?>>گرادیان (رنگ اصلی → رنگ ثانویه)</option>
									<option value="solid" <?php selected( $header_style, 'solid' ); ?>>ساده (تخت و مینیمال)</option>
								</select>
								<p class="description">گرادیان از دو رنگی که بالا انتخاب کردید ساخته می‌شود، پس با تغییر رنگ‌ها ظاهر هدر هم عوض می‌شود.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">حالت رنگی</th>
							<td>
								<?php $hy_theme = isset( $s['theme'] ) ? $s['theme'] : 'auto'; ?>
								<select name="yuniq_ai_settings[theme]">
									<option value="auto" <?php selected( $hy_theme, 'auto' ); ?>>خودکار (بر اساس تنظیم مرورگر کاربر)</option>
									<option value="light" <?php selected( $hy_theme, 'light' ); ?>>همیشه روشن</option>
									<option value="dark" <?php selected( $hy_theme, 'dark' ); ?>>همیشه تاریک</option>
								</select>
								<p class="description">کاربر می‌تواند با دکمه‌ی داخل پنل، حالت را برای خودش تغییر دهد؛ انتخاب او ذخیره می‌شود.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">آیکون دکمه شناور</th>
							<td>
								<select name="yuniq_ai_settings[button_icon]">
									<option value="chat" <?php selected( isset( $s['button_icon'] ) ? $s['button_icon'] : '', 'chat' ); ?>>حباب گفتگو</option>
									<option value="sparkle" <?php selected( isset( $s['button_icon'] ) ? $s['button_icon'] : '', 'sparkle' ); ?>>جرقه AI</option>
									<option value="bot" <?php selected( isset( $s['button_icon'] ) ? $s['button_icon'] : '', 'bot' ); ?>>ربات</option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">موقعیت ویجت</th>
							<td>
								<select name="yuniq_ai_settings[widget_position]" id="yuniq-ai-widget-position">
									<option value="bottom-right" <?php selected( isset( $s['widget_position'] ) ? $s['widget_position'] : '', 'bottom-right' ); ?>>پایین راست</option>
									<option value="bottom-left" <?php selected( isset( $s['widget_position'] ) ? $s['widget_position'] : '', 'bottom-left' ); ?>>پایین چپ</option>
									<option value="top-right" <?php selected( isset( $s['widget_position'] ) ? $s['widget_position'] : '', 'top-right' ); ?>>بالا راست</option>
									<option value="top-left" <?php selected( isset( $s['widget_position'] ) ? $s['widget_position'] : '', 'top-left' ); ?>>بالا چپ</option>
									<option value="custom" <?php selected( isset( $s['widget_position'] ) ? $s['widget_position'] : '', 'custom' ); ?>>سفارشی</option>
								</select>
							</td>
						</tr>
						<tr class="yuniq-ai-custom-pos" style="<?php echo ( isset( $s['widget_position'] ) && 'custom' === $s['widget_position'] ) ? '' : 'display:none;'; ?>">
							<th scope="row">موقعیت سفارشی</th>
							<td>
								<label>X: <input type="number" name="yuniq_ai_settings[custom_position_x]" value="<?php echo esc_attr( isset( $s['custom_position_x'] ) ? $s['custom_position_x'] : 20 ); ?>" min="0" style="width:80px;" /></label>
								<label style="margin-right:12px;">Y: <input type="number" name="yuniq_ai_settings[custom_position_y]" value="<?php echo esc_attr( isset( $s['custom_position_y'] ) ? $s['custom_position_y'] : 20 ); ?>" min="0" style="width:80px;" /></label>
							</td>
						</tr>
						<tr>
							<th scope="row">اندازه پنجره</th>
							<td>
								<label>عرض: <input type="number" name="yuniq_ai_settings[chat_width]" value="<?php echo esc_attr( isset( $s['chat_width'] ) ? $s['chat_width'] : 400 ); ?>" min="320" max="560" style="width:80px;" /> px</label>
								<label style="margin-right:12px;">ارتفاع: <input type="number" name="yuniq_ai_settings[chat_height]" value="<?php echo esc_attr( isset( $s['chat_height'] ) ? $s['chat_height'] : 860 ); ?>" min="400" max="900" style="width:80px;" /> px</label>
							</td>
						</tr>
						<tr>
							<th scope="row">شعاع گوشه‌ها</th>
							<td>
								<input type="number" name="yuniq_ai_settings[border_radius]" value="<?php echo esc_attr( isset( $s['border_radius'] ) ? $s['border_radius'] : 16 ); ?>" min="0" max="32" style="width:80px;" /> px
							</td>
						</tr>
						<tr>
							<th scope="row">انیمیشن</th>
							<td>
								<label class="yuniq-ai-switch">
									<input type="checkbox" name="yuniq_ai_settings[enable_animations]" value="1" <?php checked( ! empty( $s['enable_animations'] ) ); ?> />
									<span class="slider"></span>
								</label>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- پشتیبانی زنده -->
			<div class="yuniq-ai-tab-panel" id="tab-live-support">
				<div class="yuniq-ai-card">
					<h2>اتصال به کارشناس انسانی</h2>
					<p class="description">وقتی فعال باشد، بازدیدکننده همیشه یک دکمه «صحبت با کارشناس» می‌بیند و اگر دستیار هوشمند نتواند کمک کند، خودش هم همین گزینه را پیشنهاد می‌دهد.</p>
					<table class="form-table">
						<tr>
							<th scope="row">فعال‌سازی پشتیبانی زنده</th>
							<td>
								<label class="yuniq-ai-switch">
									<input type="checkbox" name="yuniq_ai_settings[live_support_enabled]" value="1" <?php checked( ! empty( $s['live_support_enabled'] ) ); ?> />
									<span class="slider"></span>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">نحوه پیشنهاد اتصال</th>
							<td>
								<?php $ls_mode = isset( $s['live_support_mode'] ) ? $s['live_support_mode'] : 'both'; ?>
								<select name="yuniq_ai_settings[live_support_mode]">
									<option value="both" <?php selected( $ls_mode, 'both' ); ?>>دکمه ثابت + پیشنهاد خودکار دستیار</option>
									<option value="manual" <?php selected( $ls_mode, 'manual' ); ?>>فقط دکمه ثابت</option>
									<option value="auto_suggest" <?php selected( $ls_mode, 'auto_suggest' ); ?>>فقط پیشنهاد خودکار دستیار</option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">نام نمایشی کارشناس</th>
							<td>
								<input type="text" name="yuniq_ai_settings[agent_display_name]" value="<?php echo esc_attr( isset( $s['agent_display_name'] ) ? $s['agent_display_name'] : 'کارشناس پشتیبانی' ); ?>" class="regular-text" />
							</td>
						</tr>
					</table>
				</div>

				<div class="yuniq-ai-card">
					<h2>اعلان درخواست‌های جدید</h2>
					<p class="description">هر بار که بازدیدکننده‌ای درخواست کارشناس بدهد یا فرمی ثبت کند، از این کانال‌ها مطلع می‌شوید.</p>
					<table class="form-table">
						<tr>
							<th scope="row">کانال‌های اعلان</th>
							<td>
								<?php $channels = isset( $s['notify_channels'] ) && is_array( $s['notify_channels'] ) ? $s['notify_channels'] : array(); ?>
								<label><input type="checkbox" name="yuniq_ai_settings[notify_channels][]" value="email" <?php checked( in_array( 'email', $channels, true ) ); ?> /> ایمیل</label>
								&nbsp;&nbsp;
								<label><input type="checkbox" name="yuniq_ai_settings[notify_channels][]" value="telegram" <?php checked( in_array( 'telegram', $channels, true ) ); ?> /> تلگرام</label>
							</td>
						</tr>
						<tr>
							<th scope="row">ایمیل دریافت اعلان</th>
							<td>
								<input type="email" name="yuniq_ai_settings[notify_email]" value="<?php echo esc_attr( isset( $s['notify_email'] ) ? $s['notify_email'] : get_option( 'admin_email' ) ); ?>" class="regular-text" />
							</td>
						</tr>
						<tr>
							<th scope="row">توکن ربات تلگرام</th>
							<td>
								<input type="password" name="yuniq_ai_settings[telegram_bot_token]" value="<?php echo esc_attr( empty( $s['telegram_bot_token'] ) ? '' : \Yuniq\Ai\Settings::SECRET_MASK ); ?>" class="regular-text" autocomplete="off" />
								<p class="description">توکن را از <a href="https://t.me/BotFather" target="_blank" rel="noopener noreferrer">@BotFather</a> در تلگرام بگیرید.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">شناسه چت تلگرام (Chat ID)</th>
							<td>
								<input type="text" name="yuniq_ai_settings[telegram_chat_id]" value="<?php echo esc_attr( isset( $s['telegram_chat_id'] ) ? $s['telegram_chat_id'] : '' ); ?>" class="regular-text" dir="ltr" />
								<p class="description">شناسه چت خود یا گروه پشتیبانی را از ربات‌هایی مثل <a href="https://t.me/userinfobot" target="_blank" rel="noopener noreferrer">@userinfobot</a> پیدا کنید.</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="yuniq-ai-card">
					<h2>فرم دریافت سرنخ (Lead)</h2>
					<p class="description">دستیار هوشمند وقتی تشخیص دهد کاربر آماده درخواست مشاوره/تماس است، همین فرم را داخل گفتگو نمایش می‌دهد.</p>
					<?php
					$forms    = isset( $s['lead_forms'] ) && is_array( $s['lead_forms'] ) && ! empty( $s['lead_forms'] ) ? $s['lead_forms'] : array( array() );
					$form0    = $forms[0];
					$f_fields = isset( $form0['fields'] ) && is_array( $form0['fields'] ) && ! empty( $form0['fields'] ) ? $form0['fields'] : array( array( 'label' => '', 'type' => 'text', 'required' => true ) );
					?>
					<table class="form-table">
						<tr>
							<th scope="row">عنوان فرم</th>
							<td>
								<input type="text" name="yuniq_ai_settings[lead_forms][0][title]" value="<?php echo esc_attr( isset( $form0['title'] ) ? $form0['title'] : 'درخواست مشاوره' ); ?>" class="regular-text" />
							</td>
						</tr>
						<tr>
							<th scope="row">متن دکمه نمایش فرم</th>
							<td>
								<input type="text" name="yuniq_ai_settings[lead_forms][0][trigger_label]" value="<?php echo esc_attr( isset( $form0['trigger_label'] ) ? $form0['trigger_label'] : 'درخواست مشاوره رایگان' ); ?>" class="regular-text" />
							</td>
						</tr>
					</table>
					<h3>فیلدهای فرم</h3>
					<div id="yuniq-ai-lead-fields">
						<?php foreach ( $f_fields as $i => $field ) : ?>
							<div class="yuniq-ai-qa-row">
								<input type="text" name="yuniq_ai_settings[lead_forms][0][fields][<?php echo esc_attr( $i ); ?>][label]" value="<?php echo esc_attr( isset( $field['label'] ) ? $field['label'] : '' ); ?>" placeholder="عنوان فیلد (مثلاً نام شما)" style="width:200px;" />
								<select name="yuniq_ai_settings[lead_forms][0][fields][<?php echo esc_attr( $i ); ?>][type]">
									<?php $f_type = isset( $field['type'] ) ? $field['type'] : 'text'; ?>
									<option value="text" <?php selected( $f_type, 'text' ); ?>>متن</option>
									<option value="tel" <?php selected( $f_type, 'tel' ); ?>>تلفن</option>
									<option value="email" <?php selected( $f_type, 'email' ); ?>>ایمیل</option>
									<option value="textarea" <?php selected( $f_type, 'textarea' ); ?>>متن بلند</option>
								</select>
								<label><input type="checkbox" name="yuniq_ai_settings[lead_forms][0][fields][<?php echo esc_attr( $i ); ?>][required]" value="1" <?php checked( ! empty( $field['required'] ) ); ?> /> الزامی</label>
								<button type="button" class="button yuniq-ai-remove-qa">&times;</button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button" id="yuniq-ai-add-lead-field">+ افزودن فیلد</button>
				</div>
			</div>
		</div>

		<p class="submit">
			<?php submit_button( 'ذخیره تنظیمات', 'primary', 'submit', false ); ?>
		</p>
	</form>
</div>
