<?php
/**
 * صفحه تنظیمات ادمین
 *
 * @package Yuniq\Ai
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$s       = $settings;
$has_woo = class_exists( 'WooCommerce' );

/**
 * Print an on/off switch bound to one boolean setting.
 *
 * @param string $key Setting name.
 */
$yq_switch = function ( $key ) use ( $s ) {
	?>
	<label class="yuniq-ai-switch">
		<input type="checkbox" name="yuniq_ai_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $s[ $key ] ) ); ?> />
		<span class="slider"></span>
	</label>
	<?php
};

/**
 * Print a slider bound to one numeric setting, with its live value.
 *
 * @param string     $key  Setting name.
 * @param int|float  $min  Lower bound.
 * @param int|float  $max  Upper bound.
 * @param int|float  $step Step.
 * @param string     $unit Unit shown after the value.
 */
$yq_range = function ( $key, $min, $max, $step, $unit ) use ( $s ) {
	$id = 'yuniq-ai-' . str_replace( '_', '-', $key );
	?>
	<div class="yuniq-ai-range">
		<input type="range" name="yuniq_ai_settings[<?php echo esc_attr( $key ); ?>]" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" step="<?php echo esc_attr( $step ); ?>" />
		<output for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $s[ $key ] ); ?></output> <span class="yq-unit"><?php echo esc_html( $unit ); ?></span>
	</div>
	<?php
};

// Values the "back to default" button restores on the Design tab.
$yq_design_defaults = array_intersect_key(
	\Yuniq\Ai\Settings::defaults(),
	array_flip(
		array(
			'primary_color', 'secondary_color', 'header_style', 'theme', 'launcher_icon', 'launcher_shape',
			'launcher_label', 'launcher_size', 'launcher_bg', 'show_online_badge', 'enable_animations',
			'greeting_enabled', 'greeting_title', 'greeting_text', 'greeting_delay', 'widget_position',
			'custom_position_x', 'custom_position_y', 'chat_width', 'chat_height', 'user_bubble_color',
			'bot_bubble_color', 'font_family', 'custom_font', 'font_size', 'border_radius',
		)
	)
);
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
				<button type="button" class="yuniq-ai-tab-btn" data-tab="display">نمایش و پیشرفته</button>
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
				<div class="yuniq-ai-card yuniq-ai-guide">
					<h2>راهنمای اتصال در ۳ قدم</h2>
					<ol class="yuniq-ai-steps">
						<li>
							<strong>سرویس را انتخاب کنید.</strong>
							<span>اگر هاست سایت در ایران است، یکی از درگاه‌های ایرانی (اول‌ای‌آی، گپ‌جی‌پی‌تی، متیس) را انتخاب کنید. OpenAI، Gemini و Claude معمولاً درخواست‌هایی را که از IP ایران ارسال شود رد می‌کنند.</span>
						</li>
						<li>
							<strong>کلید API بگیرید.</strong>
							<span>در سایت سرویس ثبت‌نام کنید، اعتبار بخرید و از بخش API Keys یک کلید جدید بسازید. لینک مستقیم پایین فیلد «سرویس» نمایش داده می‌شود.</span>
						</li>
						<li>
							<strong>کلید را وارد کنید و «تست اتصال» را بزنید.</strong>
							<span>اگر پیام سبز دیدید، تنظیمات را ذخیره کنید و بعد از بخش «پایگاه دانش» محتوای سایت را ایندکس کنید تا دستیار سایت شما را بشناسد.</span>
						</li>
					</ol>
				</div>

				<div class="yuniq-ai-card">
					<h2>اتصال به سرویس هوش مصنوعی</h2>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="yuniq-ai-provider">سرویس</label></th>
							<td>
								<select name="yuniq_ai_settings[ai_provider]" id="yuniq-ai-provider">
									<?php foreach ( \Yuniq\Ai\Ai\Presets::all() as $preset_id => $preset ) : ?>
										<option value="<?php echo esc_attr( $preset_id ); ?>" <?php selected( $s['ai_provider'], $preset_id ); ?>><?php echo esc_html( $preset['label'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<div class="yuniq-ai-provider-info" id="yuniq-ai-provider-info" aria-live="polite"></div>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="yuniq-ai-api-key">کلید API</label></th>
							<td>
								<input type="password" name="yuniq_ai_settings[api_key]" id="yuniq-ai-api-key" value="<?php echo esc_attr( empty( $s['api_key'] ) ? '' : \Yuniq\Ai\Settings::SECRET_MASK ); ?>" class="regular-text" autocomplete="off" dir="ltr" />
								<p class="description">کلید فقط روی سرور شما نگه‌داری می‌شود و هرگز به مرورگر بازدیدکننده ارسال نمی‌شود. کلید ذخیره‌شده در این صفحه نمایش داده نمی‌شود؛ برای تغییر، کلید جدید را وارد کنید و برای حفظ کلید فعلی به این فیلد دست نزنید.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="yuniq-ai-endpoint">آدرس API (Endpoint)</label></th>
							<td>
								<input type="url" name="yuniq_ai_settings[api_endpoint]" id="yuniq-ai-endpoint" value="<?php echo esc_attr( $s['api_endpoint'] ); ?>" class="large-text" dir="ltr" />
								<p class="description">با انتخاب سرویس خودکار پر می‌شود. آدرس پایه (مثلاً <code>https://example.com/v1</code>) هم کافی است؛ مسیر <code>/chat/completions</code> خودکار اضافه می‌شود.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="yuniq-ai-model">مدل</label></th>
							<td>
								<div class="yuniq-ai-inline">
									<input type="text" name="yuniq_ai_settings[model]" id="yuniq-ai-model" value="<?php echo esc_attr( $s['model'] ); ?>" class="regular-text" dir="ltr" list="yuniq-ai-model-options" />
									<datalist id="yuniq-ai-model-options"></datalist>
									<button type="button" class="button" id="yuniq-ai-load-models">دریافت فهرست مدل‌ها</button>
								</div>
								<div class="yuniq-ai-model-list" id="yuniq-ai-model-list"></div>
								<p class="description">برای پشتیبانی سایت، مدل‌های سبک (مثل gpt-4o-mini یا claude-haiku) سریع‌تر و ارزان‌ترند و کیفیت کافی دارند.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">بررسی اتصال</th>
							<td>
								<button type="button" class="button button-secondary" id="yuniq-ai-test-connection">تست اتصال</button>
								<div class="yuniq-ai-test-result" id="yuniq-ai-test-result" aria-live="polite"></div>
								<p class="description">با مقادیر همین فرم (حتی پیش از ذخیره) یک پیام کوتاه ارسال می‌شود. هزینه آن ناچیز است.</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="yuniq-ai-card">
					<h2>رفتار پاسخ‌ها</h2>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="yuniq-ai-temperature">خلاقیت (Temperature)</label></th>
							<td>
								<div class="yuniq-ai-range">
									<input type="range" name="yuniq_ai_settings[temperature]" id="yuniq-ai-temperature" value="<?php echo esc_attr( $s['temperature'] ); ?>" min="0" max="2" step="0.1" />
									<output for="yuniq-ai-temperature"><?php echo esc_html( $s['temperature'] ); ?></output>
								</div>
								<p class="description">عدد کمتر = پاسخ‌های دقیق‌تر و یکسان‌تر (برای پشتیبانی و فروش ۰.۳ تا ۰.۷ مناسب است). عدد بیشتر = پاسخ‌های متنوع‌تر و خلاقانه‌تر.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="yuniq-ai-max-tokens">حداکثر طول پاسخ (توکن)</label></th>
							<td>
								<input type="number" name="yuniq_ai_settings[max_tokens]" id="yuniq-ai-max-tokens" value="<?php echo esc_attr( $s['max_tokens'] ); ?>" min="64" max="8192" step="64" class="small-text" />
								<p class="description">هر توکن تقریباً نصف یک کلمه فارسی است. ۵۱۲ برای پاسخ‌های کوتاه و ۱۰۲۴ برای پاسخ‌های کامل کافی است.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="yuniq-ai-history">حافظه گفتگو</label></th>
							<td>
								<input type="number" name="yuniq_ai_settings[history_limit]" id="yuniq-ai-history" value="<?php echo esc_attr( $s['history_limit'] ); ?>" min="0" max="20" class="small-text" /> پیام آخر
								<p class="description">چند پیام قبلی همراه هر سوال ارسال شود تا دستیار موضوع گفتگو را به خاطر بسپارد. عدد بیشتر = هزینه بیشتر.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="yuniq-ai-context">منابع از پایگاه دانش</label></th>
							<td>
								<input type="number" name="yuniq_ai_settings[context_documents]" id="yuniq-ai-context" value="<?php echo esc_attr( $s['context_documents'] ); ?>" min="1" max="12" class="small-text" /> صفحه
								<p class="description">چند صفحه مرتبط از سایت برای پاسخ به هر سوال به مدل داده شود. عدد بیشتر = پاسخ دقیق‌تر ولی کندتر و گران‌تر.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="yuniq-ai-timeout">حداکثر زمان انتظار</label></th>
							<td>
								<input type="number" name="yuniq_ai_settings[request_timeout]" id="yuniq-ai-timeout" value="<?php echo esc_attr( $s['request_timeout'] ); ?>" min="10" max="300" class="small-text" /> ثانیه
							</td>
						</tr>
					</table>
				</div>

				<div class="yuniq-ai-card">
					<h2>شخصیت و دستورالعمل دستیار</h2>
					<p class="description">این متن به دستیار می‌گوید چه کسی است، با چه لحنی صحبت کند و چه کارهایی انجام ندهد. می‌توانید از یکی از قالب‌های آماده شروع کنید و آن را ویرایش کنید.</p>
					<div class="yuniq-ai-template-row">
						<button type="button" class="button yuniq-ai-prompt-template" data-template="shop">فروشگاه اینترنتی</button>
						<button type="button" class="button yuniq-ai-prompt-template" data-template="services">شرکت خدماتی</button>
						<button type="button" class="button yuniq-ai-prompt-template" data-template="education">آموزشی / دوره</button>
						<button type="button" class="button yuniq-ai-prompt-template" data-template="clinic">مطب / کلینیک</button>
					</div>
					<textarea name="yuniq_ai_settings[system_prompt]" id="yuniq-ai-system-prompt" rows="8" class="large-text"><?php echo esc_textarea( $s['system_prompt'] ); ?></textarea>
					<p class="description">قوانین فنی (استفاده از پایگاه دانش، ارجاع به کارشناس، کارت محصول و فرم) همیشه خودکار اضافه می‌شوند و لازم نیست اینجا بنویسید.</p>
				</div>

				<div class="yuniq-ai-card">
					<h2>رفع مشکل</h2>
					<div class="yuniq-ai-faq">
						<details>
							<summary>خطای ۴۰۱ یا ۴۰۳ (دسترسی رد شد)</summary>
							<p>کلید اشتباه، منقضی یا مربوط به سرویس دیگری است. مطمئن شوید کلید را از همان سرویسی گرفته‌اید که در فیلد «سرویس» انتخاب کرده‌اید، و حساب شما اعتبار کافی دارد.</p>
						</details>
						<details>
							<summary>خطای ۴۰۴ (آدرس یافت نشد)</summary>
							<p>آدرس API یا نام مدل اشتباه است. سرویس را دوباره انتخاب کنید تا آدرس درست پر شود، و با دکمه «دریافت فهرست مدل‌ها» نام دقیق مدل را انتخاب کنید.</p>
						</details>
						<details>
							<summary>خطای ۴۲۹ (سقف درخواست)</summary>
							<p>اعتبار حساب تمام شده یا در یک دقیقه درخواست زیادی ارسال شده است. حساب کاربری خود را در سایت سرویس بررسی کنید.</p>
						</details>
						<details>
							<summary>خطای اتصال، timeout یا «cURL error»</summary>
							<p>سرور سایت نمی‌تواند به سرویس وصل شود. اگر هاست در ایران است، معمولاً دلیلش تحریم است: یک درگاه ایرانی انتخاب کنید. اگر هاست خارج از ایران است، از پشتیبانی هاست بپرسید که اتصال خروجی (outbound) مسدود نباشد.</p>
						</details>
						<details>
							<summary>پاسخ‌ها یکجا ظاهر می‌شوند، نه کلمه به کلمه</summary>
							<p>برای نمایش تدریجی پاسخ، افزونه PHP به نام cURL روی هاست لازم است. همچنین برخی کش‌ها و فایروال‌ها (مثل Cloudflare) پاسخ تدریجی را بافر می‌کنند. بدون آن هم دستیار کار می‌کند.</p>
						</details>
						<details>
							<summary>دستیار اطلاعات سایت را نمی‌داند</summary>
							<p>به «پایگاه دانش» بروید و ایندکس را اجرا کنید. بعد از افزودن محصول یا صفحه جدید هم ایندکس را دوباره اجرا کنید.</p>
						</details>
						<details>
							<summary>برای توسعه‌دهندگان</summary>
							<p>هر سرویسی که از قالب <code>chat/completions</code> پشتیبانی کند با گزینه «سفارشی» کار می‌کند. برای سرویس‌های دیگر فیلتر <code>yuniq_ai_provider</code> را برای برگرداندن کلاس سفارشی، <code>yuniq_ai_api_request_args</code> را برای افزودن هدر، <code>yuniq_ai_system_prompt</code> را برای تغییر پرامپت نهایی و <code>yuniq_ai_provider_presets</code> را برای افزودن سرویس به همین فهرست استفاده کنید.</p>
						</details>
					</div>
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
				<div class="yuniq-ai-design-layout">
					<div class="yuniq-ai-design-fields">

						<div class="yuniq-ai-card yq-quickstart">
							<div class="yq-card-head">
								<div>
									<h2>شروع سریع</h2>
									<p class="description">یک قالب آماده انتخاب کنید و بعد هر جزئی را که خواستید تغییر دهید. تا «ذخیره تنظیمات» را نزنید، چیزی در سایت عوض نمی‌شود.</p>
								</div>
								<button type="button" class="button button-link yq-reset-design" data-defaults="<?php echo esc_attr( wp_json_encode( $yq_design_defaults ) ); ?>">بازگشت به پیش‌فرض</button>
							</div>
							<div class="yq-presets">
								<?php
								$yq_style_presets = array(
									'classic' => array( 'آبی کلاسیک', '#263DFF', '#111B55' ),
									'violet'  => array( 'بنفش مدرن', '#7C3AED', '#2E1065' ),
									'ocean'   => array( 'فیروزه‌ای', '#0891B2', '#164E63' ),
									'emerald' => array( 'سبز', '#059669', '#064E3B' ),
									'sunset'  => array( 'نارنجی گرم', '#F97316', '#9A3412' ),
									'rose'    => array( 'قرمز', '#E11D48', '#881337' ),
									'minimal' => array( 'مشکی مینیمال', '#111827', '#374151' ),
								);
								foreach ( $yq_style_presets as $preset_key => $preset ) :
									?>
									<button type="button" class="yq-preset" data-preset="<?php echo esc_attr( $preset_key ); ?>" style="--a:<?php echo esc_attr( $preset[1] ); ?>;--b:<?php echo esc_attr( $preset[2] ); ?>">
										<span class="yq-preset-swatch"></span>
										<?php echo esc_html( $preset[0] ); ?>
									</button>
								<?php endforeach; ?>
							</div>
						</div>

						<nav class="yq-jump" aria-label="بخش‌های طراحی">
							<a href="#yq-sec-colors">رنگ‌ها</a>
							<a href="#yq-sec-launcher">دکمه شناور</a>
							<a href="#yq-sec-greeting">حباب خوش‌آمد</a>
							<a href="#yq-sec-position">جایگاه و اندازه</a>
							<a href="#yq-sec-more">تنظیمات بیشتر</a>
						</nav>

						<section class="yuniq-ai-card" id="yq-sec-colors">
							<h2><span class="yq-step">۱</span> رنگ‌ها</h2>
							<div class="yq-grid-2">
								<div class="yq-field">
									<span class="yq-label">رنگ اصلی</span>
									<input type="text" name="yuniq_ai_settings[primary_color]" value="<?php echo esc_attr( $s['primary_color'] ); ?>" class="yuniq-ai-color-picker" data-default-color="#263DFF" />
								</div>
								<div class="yq-field">
									<span class="yq-label">رنگ دوم (برای گرادیان)</span>
									<input type="text" name="yuniq_ai_settings[secondary_color]" value="<?php echo esc_attr( $s['secondary_color'] ); ?>" class="yuniq-ai-color-picker" data-default-color="#111B55" />
								</div>
							</div>
							<div class="yq-field">
								<span class="yq-label">سبک بالای پنجره گفتگو</span>
								<div class="yuniq-ai-choices">
									<?php foreach ( array( 'gradient' => 'گرادیان', 'brand' => 'تک‌رنگ', 'solid' => 'ساده و روشن' ) as $hs_key => $hs_label ) : ?>
										<label class="yuniq-ai-choice">
											<input type="radio" name="yuniq_ai_settings[header_style]" value="<?php echo esc_attr( $hs_key ); ?>" <?php checked( $s['header_style'], $hs_key ); ?> />
											<span class="yuniq-ai-choice-box"><span class="yq-header-demo yq-header-demo-<?php echo esc_attr( $hs_key ); ?>"></span><?php echo esc_html( $hs_label ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
							<div class="yq-field">
								<span class="yq-label">روشن یا تاریک</span>
								<div class="yuniq-ai-choices">
									<?php foreach ( array( 'auto' => 'خودکار', 'light' => 'همیشه روشن', 'dark' => 'همیشه تاریک' ) as $th_key => $th_label ) : ?>
										<label class="yuniq-ai-choice">
											<input type="radio" name="yuniq_ai_settings[theme]" value="<?php echo esc_attr( $th_key ); ?>" <?php checked( $s['theme'], $th_key ); ?> />
											<span class="yuniq-ai-choice-box"><span class="yq-theme-demo yq-theme-demo-<?php echo esc_attr( $th_key ); ?>"></span><?php echo esc_html( $th_label ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
								<p class="yq-help">«خودکار» یعنی مطابق تنظیم گوشی یا کامپیوتر بازدیدکننده.</p>
							</div>
						</section>

						<section class="yuniq-ai-card" id="yq-sec-launcher">
							<h2><span class="yq-step">۲</span> دکمه شناور</h2>
							<div class="yq-field">
								<span class="yq-label">تصویر روی دکمه</span>
								<div class="yuniq-ai-choices">
									<?php
									$icon_labels = array(
										'bot'     => 'ربات',
										'chat'    => 'گفتگو',
										'sparkle' => 'جرقه',
										'headset' => 'پشتیبان',
										'avatar'  => 'عکس دلخواه',
									);
									foreach ( $icon_labels as $icon_key => $icon_label ) :
										?>
										<label class="yuniq-ai-choice yuniq-ai-choice-icon">
											<input type="radio" name="yuniq_ai_settings[launcher_icon]" value="<?php echo esc_attr( $icon_key ); ?>" <?php checked( $s['launcher_icon'], $icon_key ); ?> />
											<span class="yuniq-ai-choice-box">
												<span class="yuniq-ai-choice-visual">
													<?php
													if ( 'avatar' === $icon_key ) {
														echo '<span class="dashicons dashicons-format-image" aria-hidden="true"></span>';
													} else {
														echo \Yuniq\Ai\Frontend\Widget::icon_markup( $icon_key ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
													}
													?>
												</span>
												<?php echo esc_html( $icon_label ); ?>
											</span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
							<div class="yq-field yuniq-ai-when-avatar">
								<label class="yq-label" for="yuniq-ai-avatar-url">عکس دلخواه</label>
								<div class="yuniq-ai-inline">
									<input type="url" name="yuniq_ai_settings[avatar_url]" id="yuniq-ai-avatar-url" value="<?php echo esc_attr( $s['avatar_url'] ); ?>" class="regular-text" dir="ltr" placeholder="https://" />
									<button type="button" class="button" id="yuniq-ai-upload-avatar">انتخاب از رسانه</button>
								</div>
								<p class="yq-help">بهترین نتیجه: تصویر مربعی، حداقل ۱۲۸×۱۲۸ پیکسل.</p>
							</div>
							<div class="yq-field">
								<span class="yq-label">شکل دکمه</span>
								<div class="yuniq-ai-choices">
									<?php foreach ( array( 'squircle' => 'مربع گرد', 'circle' => 'دایره', 'pill' => 'همراه متن' ) as $shape_key => $shape_label ) : ?>
										<label class="yuniq-ai-choice">
											<input type="radio" name="yuniq_ai_settings[launcher_shape]" value="<?php echo esc_attr( $shape_key ); ?>" <?php checked( $s['launcher_shape'], $shape_key ); ?> />
											<span class="yuniq-ai-choice-box"><span class="yuniq-ai-shape-demo yuniq-ai-shape-demo-<?php echo esc_attr( $shape_key ); ?>"></span><?php echo esc_html( $shape_label ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
							<div class="yq-field yuniq-ai-when-pill">
								<label class="yq-label" for="yuniq-ai-launcher-label">متن روی دکمه</label>
								<input type="text" name="yuniq_ai_settings[launcher_label]" id="yuniq-ai-launcher-label" value="<?php echo esc_attr( $s['launcher_label'] ); ?>" class="regular-text" maxlength="30" />
							</div>
							<div class="yq-field">
								<label class="yq-label" for="yuniq-ai-launcher-size">اندازه دکمه</label>
								<?php $yq_range( 'launcher_size', 44, 88, 2, 'px' ); ?>
							</div>
							<div class="yq-grid-2">
								<div class="yq-field">
									<span class="yq-label">رنگ دکمه</span>
									<div class="yuniq-ai-choices">
										<?php foreach ( array( 'gradient' => 'گرادیان', 'solid' => 'تک‌رنگ' ) as $bg_key => $bg_label ) : ?>
											<label class="yuniq-ai-choice">
												<input type="radio" name="yuniq_ai_settings[launcher_bg]" value="<?php echo esc_attr( $bg_key ); ?>" <?php checked( $s['launcher_bg'], $bg_key ); ?> />
												<span class="yuniq-ai-choice-box yq-choice-text"><?php echo esc_html( $bg_label ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								</div>
								<div class="yq-field yq-toggles">
									<label class="yq-toggle-row"><?php $yq_switch( 'show_online_badge' ); ?> نقطه سبز «آنلاین»</label>
									<label class="yq-toggle-row"><?php $yq_switch( 'enable_animations' ); ?> حرکت و انیمیشن</label>
								</div>
							</div>
						</section>

						<section class="yuniq-ai-card" id="yq-sec-greeting">
							<div class="yq-card-head">
								<h2><span class="yq-step">۳</span> حباب خوش‌آمد</h2>
								<label class="yq-toggle-row"><?php $yq_switch( 'greeting_enabled' ); ?> نمایش</label>
							</div>
							<div class="yq-greeting-fields">
								<div class="yq-grid-2">
									<div class="yq-field">
										<label class="yq-label" for="yuniq-ai-greeting-title">خط اول</label>
										<input type="text" name="yuniq_ai_settings[greeting_title]" id="yuniq-ai-greeting-title" value="<?php echo esc_attr( $s['greeting_title'] ); ?>" class="widefat" maxlength="40" />
									</div>
									<div class="yq-field">
										<label class="yq-label" for="yuniq-ai-greeting-text">خط دوم</label>
										<input type="text" name="yuniq_ai_settings[greeting_text]" id="yuniq-ai-greeting-text" value="<?php echo esc_attr( $s['greeting_text'] ); ?>" class="widefat" maxlength="60" />
									</div>
								</div>
								<div class="yq-field">
									<label class="yq-label" for="yuniq-ai-greeting-delay">چند ثانیه بعد از باز شدن صفحه خودش ظاهر شود؟</label>
									<?php $yq_range( 'greeting_delay', 0, 30, 1, 'ثانیه' ); ?>
									<p class="yq-help">۰ = خودکار ظاهر نشود؛ فقط وقتی ماوس روی دکمه برود.</p>
								</div>
							</div>
						</section>

						<section class="yuniq-ai-card" id="yq-sec-position">
							<h2><span class="yq-step">۴</span> جایگاه و اندازه</h2>
							<div class="yq-field">
								<span class="yq-label">گوشه صفحه</span>
								<div class="yuniq-ai-choices yuniq-ai-corner-choices">
									<?php foreach ( array( 'bottom-right' => 'پایین راست', 'bottom-left' => 'پایین چپ', 'top-right' => 'بالا راست', 'top-left' => 'بالا چپ' ) as $pos_key => $pos_label ) : ?>
										<label class="yuniq-ai-choice">
											<input type="radio" name="yuniq_ai_settings[widget_position]" value="<?php echo esc_attr( $pos_key ); ?>" <?php checked( $s['widget_position'], $pos_key ); ?> />
											<span class="yuniq-ai-choice-box"><span class="yuniq-ai-corner-demo yuniq-ai-corner-<?php echo esc_attr( $pos_key ); ?>"></span><?php echo esc_html( $pos_label ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
							<div class="yq-grid-2">
								<div class="yq-field">
									<label class="yq-label" for="yuniq-ai-custom-position-x">فاصله از کناره</label>
									<?php $yq_range( 'custom_position_x', 0, 200, 2, 'px' ); ?>
								</div>
								<div class="yq-field">
									<label class="yq-label" for="yuniq-ai-custom-position-y">فاصله از بالا/پایین</label>
									<?php $yq_range( 'custom_position_y', 0, 200, 2, 'px' ); ?>
								</div>
							</div>
							<p class="yq-help">اگر دکمه روی دکمه دیگری از سایت (مثل واتس‌اپ یا «بازگشت به بالا») افتاده، فاصله را بیشتر کنید.</p>
							<div class="yq-grid-2">
								<div class="yq-field">
									<label class="yq-label" for="yuniq-ai-chat-width">عرض پنجره گفتگو</label>
									<?php $yq_range( 'chat_width', 320, 560, 10, 'px' ); ?>
								</div>
								<div class="yq-field">
									<label class="yq-label" for="yuniq-ai-chat-height">ارتفاع پنجره گفتگو</label>
									<?php $yq_range( 'chat_height', 400, 900, 10, 'px' ); ?>
								</div>
							</div>
							<p class="yq-help">در موبایل پنجره همیشه تمام‌صفحه باز می‌شود.</p>
						</section>

						<details class="yuniq-ai-card yq-more" id="yq-sec-more">
							<summary><h2><span class="yq-step">+</span> تنظیمات بیشتر <small>رنگ پیام‌ها، فونت، گردی گوشه‌ها</small></h2></summary>
							<div class="yq-grid-2">
								<div class="yq-field">
									<span class="yq-label">رنگ پیام بازدیدکننده</span>
									<input type="text" name="yuniq_ai_settings[user_bubble_color]" value="<?php echo esc_attr( $s['user_bubble_color'] ); ?>" class="yuniq-ai-color-picker" />
									<p class="yq-help">خالی = گرادیان رنگ‌های بالا.</p>
								</div>
								<div class="yq-field">
									<span class="yq-label">رنگ پیام دستیار</span>
									<input type="text" name="yuniq_ai_settings[bot_bubble_color]" value="<?php echo esc_attr( $s['bot_bubble_color'] ); ?>" class="yuniq-ai-color-picker" />
									<p class="yq-help">خالی = سفید (در حالت تاریک، خاکستری تیره).</p>
								</div>
							</div>
							<p class="yq-help">رنگ متن داخل پیام‌ها خودکار سفید یا تیره انتخاب می‌شود تا همیشه خوانا باشد.</p>
							<div class="yq-grid-2">
								<div class="yq-field">
									<label class="yq-label" for="yuniq-ai-font-family">فونت</label>
									<select name="yuniq_ai_settings[font_family]" id="yuniq-ai-font-family">
										<option value="vazirmatn" <?php selected( $s['font_family'], 'vazirmatn' ); ?>>وزیرمتن (همراه افزونه)</option>
										<option value="inherit" <?php selected( $s['font_family'], 'inherit' ); ?>>همان فونت قالب سایت</option>
										<option value="custom" <?php selected( $s['font_family'], 'custom' ); ?>>نام فونت دلخواه</option>
									</select>
									<input type="text" name="yuniq_ai_settings[custom_font]" id="yuniq-ai-custom-font" value="<?php echo esc_attr( $s['custom_font'] ); ?>" class="regular-text yuniq-ai-when-custom-font" placeholder="IRANSansX" dir="ltr" style="margin-top:6px;" />
									<p class="yq-help yuniq-ai-when-custom-font">فونت باید در قالب سایت بارگذاری شده باشد؛ اینجا فقط نامش را بنویسید.</p>
								</div>
								<div class="yq-field">
									<label class="yq-label" for="yuniq-ai-font-size">اندازه متن</label>
									<?php $yq_range( 'font_size', 12, 18, 1, 'px' ); ?>
									<label class="yq-label" for="yuniq-ai-border-radius" style="margin-top:12px;">گردی گوشه‌های پنجره</label>
									<?php $yq_range( 'border_radius', 0, 32, 1, 'px' ); ?>
								</div>
							</div>
						</details>
					</div>

					<aside class="yuniq-ai-preview-col">
						<div class="yuniq-ai-preview-sticky">
							<div class="yuniq-ai-preview-head">
								<strong>پیش‌نمایش زنده</strong>
								<span class="description">با هر تغییر به‌روز می‌شود</span>
							</div>
							<div class="yuniq-ai-preview" id="yuniq-ai-preview" data-pos="bottom-right" data-theme="light">
								<div class="yq-pv-panel">
									<div class="yq-pv-header">
										<span class="yq-pv-logo"></span>
										<span class="yq-pv-titles">
											<strong class="yq-pv-name"><?php echo esc_html( $s['assistant_name'] ); ?></strong>
											<small><?php echo esc_html( $s['header_subtitle'] ); ?></small>
										</span>
									</div>
									<div class="yq-pv-body">
										<div class="yq-pv-msg yq-pv-bot">سلام! چطور می‌تونم کمکتون کنم؟</div>
										<div class="yq-pv-msg yq-pv-user">قیمت‌ها رو می‌خواستم</div>
										<div class="yq-pv-msg yq-pv-bot">حتماً! کدوم محصول مدنظرتونه؟</div>
									</div>
									<div class="yq-pv-input"><span class="yq-pv-placeholder"></span><span class="yq-pv-send"></span></div>
								</div>
								<div class="yq-pv-launcher-row">
									<span class="yq-pv-greeting"><strong></strong><span></span></span>
									<span class="yq-pv-launcher">
										<span class="yq-pv-badge"></span>
										<?php foreach ( array( 'bot', 'chat', 'sparkle', 'headset' ) as $pv_icon ) : ?>
											<span class="yq-pv-icon" data-icon="<?php echo esc_attr( $pv_icon ); ?>"><?php echo \Yuniq\Ai\Frontend\Widget::icon_markup( $pv_icon ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
										<?php endforeach; ?>
										<img class="yq-pv-icon yq-pv-avatar" data-icon="avatar" alt="" />
										<span class="yq-pv-label"></span>
									</span>
								</div>
							</div>
							<div class="yuniq-ai-preview-theme">
								<button type="button" class="button button-small is-active" data-pv-theme="light">روشن</button>
								<button type="button" class="button button-small" data-pv-theme="dark">تاریک</button>
							</div>
						</div>
					</aside>
				</div>
			</div>

			<!-- نمایش -->
			<div class="yuniq-ai-tab-panel" id="tab-display">
				<div class="yuniq-ai-card">
					<h2>کجا نمایش داده شود؟</h2>
					<table class="form-table">
						<tr>
							<th scope="row">صفحات</th>
							<td>
								<fieldset class="yuniq-ai-radio-list">
									<label><input type="radio" name="yuniq_ai_settings[display_rule]" value="all" <?php checked( $s['display_rule'], 'all' ); ?> /> همه صفحات سایت</label>
									<label><input type="radio" name="yuniq_ai_settings[display_rule]" value="include" <?php checked( $s['display_rule'], 'include' ); ?> /> فقط در صفحات زیر</label>
									<label><input type="radio" name="yuniq_ai_settings[display_rule]" value="exclude" <?php checked( $s['display_rule'], 'exclude' ); ?> /> همه صفحات به‌جز صفحات زیر</label>
								</fieldset>
							</td>
						</tr>
						<tr class="yuniq-ai-when-paths">
							<th scope="row"><label for="yuniq-ai-display-paths">فهرست آدرس‌ها</label></th>
							<td>
								<textarea name="yuniq_ai_settings[display_paths]" id="yuniq-ai-display-paths" rows="5" class="large-text code" dir="ltr" placeholder="/&#10;/shop/&#10;/blog/*&#10;/contact-us/"><?php echo esc_textarea( $s['display_paths'] ); ?></textarea>
								<p class="description">هر خط یک آدرس. <code>/</code> = فقط صفحه اصلی · <code>/shop/</code> = هر آدرسی که این بخش را دارد · <code>/blog/*</code> = هر آدرسی که با این شروع شود.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">پنهان در موبایل</th>
							<td><?php $yq_switch( 'hide_on_mobile' ); ?></td>
						</tr>
						<tr>
							<th scope="row">پنهان در دسکتاپ</th>
							<td><?php $yq_switch( 'hide_on_desktop' ); ?></td>
						</tr>
						<tr>
							<th scope="row">صفحه اختصاصی دستیار</th>
							<td>
								<?php $yq_switch( 'full_page_enabled' ); ?>
								<p class="description">کد کوتاه <code>[yuniq_ai_page]</code> را در هر برگه‌ای قرار دهید تا دستیار به‌صورت تمام‌صفحه (بدون دکمه شناور) در آن نمایش داده شود. این صفحه تابع قوانین بالا نیست.</p>
							</td>
						</tr>
						<?php if ( $has_woo ) : ?>
						<tr>
							<th scope="row">کارت محصول در گفتگو</th>
							<td>
								<?php $yq_switch( 'product_cards_enabled' ); ?>
								<p class="description">وقتی دستیار محصولی را پیشنهاد می‌دهد، تصویر، قیمت، موجودی و دکمه خرید آن را نمایش می‌دهد.</p>
							</td>
						</tr>
						<?php endif; ?>
					</table>
				</div>

				<div class="yuniq-ai-card">
					<h2>متن‌ها و اجزای پنجره</h2>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="yuniq-ai-placeholder">متن راهنمای کادر پیام</label></th>
							<td><input type="text" name="yuniq_ai_settings[input_placeholder]" id="yuniq-ai-placeholder" value="<?php echo esc_attr( $s['input_placeholder'] ); ?>" class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row">دکمه روشن / تاریک</th>
							<td><?php $yq_switch( 'show_theme_toggle' ); ?> <span class="description">اجازه تغییر حالت رنگی به بازدیدکننده</span></td>
						</tr>
						<tr>
							<th scope="row">متن پایین پنجره</th>
							<td>
								<?php $yq_switch( 'show_powered_by' ); ?>
								<input type="text" name="yuniq_ai_settings[powered_by_text]" value="<?php echo esc_attr( $s['powered_by_text'] ); ?>" class="regular-text" style="margin-top:8px;display:block;" />
							</td>
						</tr>
					</table>
				</div>

				<div class="yuniq-ai-card">
					<h2>CSS سفارشی</h2>
					<?php if ( current_user_can( 'unfiltered_html' ) ) : ?>
						<p class="description">برای تغییرات فراتر از گزینه‌های بالا. همه اجزا زیر <code>#yuniq-ai-root</code> هستند؛ برای مثال:</p>
						<pre class="yuniq-ai-code" dir="ltr">#yuniq-ai-root .yuniq-ai-launcher { box-shadow: none; }
#yuniq-ai-root .yuniq-ai-msg-assistant { border-radius: 4px; }
#yuniq-ai-root { --yuniq-ai-online: #f59e0b; }</pre>
						<textarea name="yuniq_ai_settings[custom_css]" rows="10" class="large-text code" dir="ltr" spellcheck="false"><?php echo esc_textarea( $s['custom_css'] ); ?></textarea>
					<?php else : ?>
						<p class="description">فقط کاربرانی که مجوز «unfiltered_html» دارند (مدیر کل شبکه در وردپرس چندسایته) می‌توانند CSS سفارشی ویرایش کنند.</p>
					<?php endif; ?>
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
