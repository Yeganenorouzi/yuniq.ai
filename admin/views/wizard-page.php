<?php
/**
 * راه‌اندازی اجباری: تا این مراحل تمام نشود، هیچ بخش دیگری از افزونه
 * (پایگاه دانش، آمار، پشتیبانی زنده، وضعیت) در دسترس نیست.
 *
 * Each of the four steps is its own distinct screen — no two steps ever
 * share one page — so saving one always visibly moves to a different,
 * recognisably new screen rather than appearing to reload the same page.
 *
 * @package Yuniq\Ai
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$s          = $settings;
$yq_done    = 0;
foreach ( $setup_steps as $yq_step ) {
	$yq_done += $yq_step['done'] ? 1 : 0;
}
$yq_percent  = (int) round( 100 * $yq_done / max( 1, count( $setup_steps ) ) );
$yq_titles   = array( 'کلید API', 'تست اتصال', 'ایندکس محتوا', 'فعال‌سازی' );
$yq_guide    = admin_url( 'admin.php?page=' . \Yuniq\Ai\Admin\AdminPages::GUIDE_SLUG );
$yq_base     = admin_url( 'admin.php?page=' . \Yuniq\Ai\Admin\AdminPages::MENU_SLUG );

/**
 * The "راهنما را ببینید" link, repeated on every single step — on purpose:
 * an owner stuck on any step (not only the first) still needs a fast way
 * back to the guide, instead of hunting for it once at the top.
 *
 * A local closure, not a global function: this file is require()'d (not
 * require_once()) by Admin\AdminPages::render_wizard(), so a top-level
 * `function` declaration here would fatal with "cannot redeclare" the
 * moment anything ever includes it a second time in the same request.
 *
 * @param string $anchor Section anchor on the guide page, without `#`.
 * @param string $label  Link text.
 */
$yq_guide_link = function ( $anchor, $label ) use ( $yq_guide ) {
	printf(
		'<p class="yq-note"><a href="%s">%s ↗</a></p>',
		esc_url( $yq_guide . '#' . $anchor ),
		esc_html( $label )
	);
};
?>
<div class="wrap yuniq-ai-admin-wrap yq-wizard" dir="rtl">
	<div class="yuniq-ai-header">
		<div class="yuniq-ai-header-brand">
			<span class="yuniq-ai-logo">✦</span>
			<div>
				<h1>راه‌اندازی دستیار هوشمند Yuniq.ai</h1>
				<p class="yuniq-ai-subtitle">چهار قدم کوتاه، به‌ترتیب — هر قدم صفحه خودش را دارد. تا اینجا تمام نشود، بقیه بخش‌های افزونه (پایگاه دانش، آمار، پشتیبانی زنده) باز نمی‌شوند.</p>
			</div>
		</div>
		<a class="yq-chip yq-chip-link" href="<?php echo esc_url( $yq_guide ); ?>">راهنمای کامل ↗</a>
	</div>

	<div class="yuniq-ai-card yq-setup">
		<div class="yq-card-head">
			<div>
				<p class="description">قدم <?php echo esc_html( number_format_i18n( $current_step ) ); ?> از <?php echo esc_html( number_format_i18n( count( $setup_steps ) ) ); ?></p>
			</div>
			<div class="yq-setup-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $yq_percent ); ?>"><span style="width:<?php echo esc_attr( $yq_percent ); ?>%"></span></div>
		</div>
		<ol class="yq-setup-steps">
			<?php foreach ( $setup_steps as $yq_i => $yq_step ) : ?>
				<li class="<?php echo esc_attr( $yq_step['done'] ? 'is-done' : ( ( $yq_i + 1 ) === $current_step ? 'is-next' : '' ) ); ?>">
					<span class="yq-setup-mark" aria-hidden="true"></span>
					<div><strong><?php echo esc_html( isset( $yq_titles[ $yq_i ] ) ? $yq_titles[ $yq_i ] : $yq_step['title'] ); ?></strong></div>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>

	<?php
	// Without this, a successful save of a wizard step (redirected back to
	// this same screen, or forward to the next one) shows no confirmation
	// banner at all. This is the exact same "Settings saved." notice the
	// normal settings screen already shows after a save.
	settings_errors();
	?>

	<?php if ( 1 === $current_step ) : ?>
	<div class="yuniq-ai-card">
		<h2>۱. کلید API</h2>
		<p class="description">این افزونه خودش «مغز» هوش مصنوعی ندارد؛ باید به یک سرویس (OpenAI یا مشابه) وصل شود. سرویس را انتخاب و کلیدتان را وارد کنید.</p>
		<?php $yq_guide_link( 'yq-g-choose', 'نمی‌دانید کدام سرویس را انتخاب کنید یا از کجا کلید بگیرید؟ راهنما را ببینید' ); ?>

		<form method="post" action="options.php" id="yuniq-ai-settings-form">
			<?php settings_fields( 'yuniq_ai_settings_group' ); ?>
			<input type="hidden" name="yuniq_ai_settings[<?php echo esc_attr( \Yuniq\Ai\Settings::PARTIAL_FLAG ); ?>]" value="1" />
			<table class="form-table">
				<tr>
					<th scope="row"><label for="yuniq-ai-provider">سرویس</label></th>
					<td>
						<select name="yuniq_ai_settings[ai_provider]" id="yuniq-ai-provider">
							<?php foreach ( $presets as $yq_preset_id => $yq_preset ) : ?>
								<option value="<?php echo esc_attr( $yq_preset_id ); ?>" <?php selected( $s['ai_provider'], $yq_preset_id ); ?>><?php echo esc_html( $yq_preset['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<div class="yuniq-ai-provider-info" id="yuniq-ai-provider-info" aria-live="polite"></div>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="yuniq-ai-api-key">کلید API</label></th>
					<td>
						<input type="password" name="yuniq_ai_settings[api_key]" id="yuniq-ai-api-key" value="<?php echo esc_attr( empty( $s['api_key'] ) ? '' : \Yuniq\Ai\Settings::SECRET_MASK ); ?>" class="regular-text" autocomplete="off" dir="ltr" />
						<?php if ( ! empty( $s['api_key'] ) ) : ?>
							<span class="yuniq-ai-badge-ok">✓ کلیدی روی سرور ذخیره شده</span>
						<?php else : ?>
							<span class="yuniq-ai-badge-warn">هنوز کلیدی ذخیره نشده</span>
						<?php endif; ?>
						<p class="description">کلید را وارد کنید و پایین همین صفحه «ذخیره و ادامه» را بزنید؛ بعد از ذخیره خودکار به مرحله «تست اتصال» می‌روید. کلید واقعی هرگز در این صفحه نوشته نمی‌شود؛ بعد از ذخیره به‌صورت نقطه‌چین دیده می‌شود. برای تغییر، کلید جدید را وارد کنید؛ برای حفظ کلید فعلی به این فیلد دست نزنید.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="yuniq-ai-endpoint">آدرس API (Endpoint)</label></th>
					<td>
						<input type="url" name="yuniq_ai_settings[api_endpoint]" id="yuniq-ai-endpoint" value="<?php echo esc_attr( $s['api_endpoint'] ); ?>" class="large-text" dir="ltr" />
						<p class="yq-inline-warning" id="yuniq-ai-endpoint-warning" role="alert"></p>
						<p class="description">با انتخاب سرویس خودکار پر می‌شود.</p>
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
					</td>
				</tr>
			</table>
			<p><?php submit_button( 'ذخیره و ادامه', 'primary', 'submit', false ); ?></p>
		</form>
	</div>

	<?php elseif ( 2 === $current_step ) : ?>
	<div class="yuniq-ai-card">
		<h2>۲. تست اتصال</h2>
		<p class="description">کلید شما ذخیره شد. حالا با دکمه زیر یک پیام آزمایشی به همان سرویس فرستاده می‌شود تا مطمئن شوید کلید و مدل درست کار می‌کنند.</p>
		<?php $yq_guide_link( 'yq-g-errors', 'اتصال برقرار نشد یا خطا گرفتید؟ رفع خطاهای رایج را ببینید' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row">سرویس و مدل</th>
				<td>
					<p>
						<strong><?php echo esc_html( isset( $presets[ $s['ai_provider'] ]['label'] ) ? $presets[ $s['ai_provider'] ]['label'] : $s['ai_provider'] ); ?></strong>
						— مدل: <code dir="ltr"><?php echo esc_html( $s['model'] ); ?></code>
						— <span class="yuniq-ai-badge-ok">✓ کلیدی ذخیره شده</span>
					</p>
					<p class="description"><a href="<?php echo esc_url( add_query_arg( 'yuniq_step', '1', $yq_base ) ); ?>">ویرایش سرویس یا کلید ←</a></p>
					<!-- Hidden on purpose: the test uses the key already saved on the
					     server, not a value re-typed here. These just give the same
					     JS that runs on the settings screen something to read, the
					     same way the mask placeholder already tells the server "use
					     the stored key" (see ConnectionController::provider_from_request()). -->
					<input type="hidden" id="yuniq-ai-api-key" value="<?php echo esc_attr( \Yuniq\Ai\Settings::SECRET_MASK ); ?>" />
					<input type="hidden" id="yuniq-ai-endpoint" value="<?php echo esc_attr( $s['api_endpoint'] ); ?>" />
					<input type="hidden" id="yuniq-ai-model" value="<?php echo esc_attr( $s['model'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row">تست اتصال</th>
				<td>
					<button type="button" class="button button-primary button-hero" id="yuniq-ai-test-connection">تست اتصال</button>
					<div class="yuniq-ai-test-result" id="yuniq-ai-test-result" aria-live="polite"></div>
				</td>
			</tr>
		</table>

		<p id="yuniq-ai-wizard-step2-next" style="display:none;">
			<a class="button button-primary" href="<?php echo esc_url( $yq_base ); ?>">✓ اتصال موفق بود — مرحله بعد ←</a>
		</p>
	</div>

	<?php elseif ( 3 === $current_step ) : ?>
	<div class="yuniq-ai-card">
		<h2>۳. ایندکس محتوای سایت</h2>
		<p class="description">نوشته‌ها، برگه‌ها و (در صورت نصب ووکامرس) محصولات سایت شما به‌صورت پیش‌فرض انتخاب شده‌اند؛ بعداً از تب «خزنده محتوا» می‌توانید دقیق‌تر انتخاب کنید. همین حالا بزنید تا دستیار محتوای سایت شما را بشناسد.</p>
		<?php $yq_guide_link( 'yq-g-use', 'دستیار چطور از محتوای سایت باخبر می‌شود؟' ); ?>
		<p>
			<button type="button" class="button button-primary button-hero" id="yuniq-ai-start-crawl">شروع ایندکس‌گذاری</button>
			<button type="button" class="button button-secondary" id="yuniq-ai-stop-crawl" style="margin-right:8px;display:none;">توقف</button>
		</p>
		<div id="yuniq-ai-crawl-progress" style="display:none;margin-top:16px;">
			<div class="yuniq-ai-progress-bar">
				<div class="yuniq-ai-progress-fill" id="yuniq-ai-progress-fill"></div>
			</div>
			<p id="yuniq-ai-crawl-message"></p>
			<p class="description">ایندکس‌شده: <span id="yuniq-ai-kb-count">۰</span> — وضعیت: <span id="yuniq-ai-crawl-status"></span></p>
		</div>
		<p class="yq-note" style="margin-top:18px;">
			هنوز محتوایی برای ایندکس ندارید؟
			<a href="<?php echo esc_url( $skip_index_url ); ?>" id="yuniq-ai-skip-index">رد شدن از این مرحله</a> — بعداً از «پایگاه دانش» ایندکس را اجرا کنید.
		</p>
	</div>

	<?php else : ?>
	<div class="yuniq-ai-card">
		<h2>۴. فعال‌سازی دستیار</h2>
		<p class="description">آخرین قدم: دستیار را روی سایت روشن کنید تا بازدیدکنندگان حباب گفتگو را ببینند.</p>
		<?php $yq_guide_link( 'yq-g-use', 'بعد از فعال‌سازی، لحن و رفتار دستیار را چطور تنظیم کنم؟' ); ?>
		<form method="post" action="options.php" id="yuniq-ai-settings-form">
			<?php settings_fields( 'yuniq_ai_settings_group' ); ?>
			<input type="hidden" name="yuniq_ai_settings[<?php echo esc_attr( \Yuniq\Ai\Settings::PARTIAL_FLAG ); ?>]" value="1" />
			<table class="form-table">
				<tr>
					<th scope="row">فعال‌سازی دستیار</th>
					<td>
						<label class="yuniq-ai-switch">
							<input type="checkbox" id="yuniq-ai-enabled" name="yuniq_ai_settings[enabled]" value="1" checked="checked" />
							<span class="slider"></span>
						</label>
					</td>
				</tr>
			</table>
			<p><?php submit_button( 'فعال‌سازی و ورود به پنل مدیریت', 'primary', 'submit', false ); ?></p>
		</form>
	</div>
	<?php endif; ?>
</div>
