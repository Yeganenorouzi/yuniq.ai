<?php
/**
 * صفحه راهنمای کامل راه‌اندازی
 *
 * @package Yuniq\Ai
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$yq_settings_url = admin_url( 'admin.php?page=yuniq-ai' );

// Which services to suggest for which situation.
$yq_groups = array(
	array(
		'title' => 'هاست سایت در ایران است',
		'text'  => 'سرویس‌های خارجی درخواست‌های IP ایران را رد می‌کنند. یک درگاه ایرانی انتخاب کنید: پرداخت ریالی دارد و همان مدل‌های معروف را می‌دهد.',
		'ids'   => array( 'avalai', 'gapgpt', 'metis' ),
		'tag'   => 'پیشنهاد برای بیشتر سایت‌های ایرانی',
	),
	array(
		'title' => 'هاست سایت خارج از ایران است',
		'text'  => 'می‌توانید مستقیم از خود شرکت سازنده کلید بگیرید. پرداخت ارزی (کارت بین‌المللی) لازم است.',
		'ids'   => array( 'openai', 'openrouter', 'deepseek', 'gemini', 'anthropic', 'groq' ),
		'tag'   => '',
	),
	array(
		'title' => 'می‌خواهم مدل روی سرور خودم اجرا شود',
		'text'  => 'بدون هزینه مصرف و بدون خروج داده از سرور؛ ولی به سرور قوی (ترجیحاً با کارت گرافیک) و دانش فنی نیاز دارد.',
		'ids'   => array( 'ollama' ),
		'tag'   => '',
	),
);
?>
<div class="wrap yuniq-ai-admin-wrap yq-guide" dir="rtl">
	<div class="yuniq-ai-header">
		<div class="yuniq-ai-header-brand">
			<span class="yuniq-ai-logo">✦</span>
			<div>
				<h1>راهنمای راه‌اندازی</h1>
				<p class="yuniq-ai-subtitle">از صفر تا دستیار آماده به کار، در حدود ده دقیقه.</p>
			</div>
		</div>
		<a class="button button-primary" href="<?php echo esc_url( $yq_settings_url ); ?>">رفتن به تنظیمات</a>
	</div>

	<nav class="yq-jump" aria-label="بخش‌های راهنما">
		<a href="#yq-g-what">API چیست؟</a>
		<a href="#yq-g-choose">انتخاب سرویس</a>
		<a href="#yq-g-steps">دریافت و وارد کردن کلید</a>
		<a href="#yq-g-cost">هزینه</a>
		<a href="#yq-g-safe">امنیت کلید</a>
		<a href="#yq-g-use">استفاده روزمره</a>
		<a href="#yq-g-errors">رفع خطا</a>
	</nav>

	<section class="yuniq-ai-card" id="yq-g-what">
		<h2>API چیست و چرا باید خودم تهیه کنم؟</h2>
		<p>این افزونه «مغز» هوش مصنوعی ندارد؛ به یک سرویس هوش مصنوعی وصل می‌شود و سوال بازدیدکننده را همراه با محتوای سایت شما برایش می‌فرستد. <strong>کلید API</strong> رمز عبور شما برای آن سرویس است.</p>
		<div class="yq-grid-3">
			<div class="yq-fact">
				<span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
				<strong>کلید مال خود شماست</strong>
				<p>حساب را خودتان می‌سازید و شارژ می‌کنید. هزینه مصرف مستقیم به همان سرویس پرداخت می‌شود، بدون واسطه و بدون کارمزد اضافه.</p>
			</div>
			<div class="yq-fact">
				<span class="dashicons dashicons-randomize" aria-hidden="true"></span>
				<strong>هر وقت خواستید عوض کنید</strong>
				<p>به هیچ سرویسی وابسته نیستید. اگر سرویس ارزان‌تر یا بهتری پیدا کردید، فقط کلید و آدرس را عوض می‌کنید.</p>
			</div>
			<div class="yq-fact">
				<span class="dashicons dashicons-chart-line" aria-hidden="true"></span>
				<strong>فقط به اندازه مصرف</strong>
				<p>اشتراک ماهانه ندارد. به ازای هر پیام مبلغ بسیار کمی از اعتبار حساب کم می‌شود.</p>
			</div>
		</div>
	</section>

	<section class="yuniq-ai-card" id="yq-g-choose">
		<h2>کدام سرویس را انتخاب کنم؟</h2>
		<p class="description">نمی‌دانید هاست شما کجاست؟ از شرکت هاستینگ بپرسید، یا یک سرویس را امتحان کنید: اگر «تست اتصال» خطای اتصال یا ۴۰۳ داد، هاست در ایران است.</p>

		<?php foreach ( $yq_groups as $yq_group ) : ?>
			<div class="yq-provider-group">
				<h3>
					<?php echo esc_html( $yq_group['title'] ); ?>
					<?php if ( $yq_group['tag'] ) : ?>
						<span class="yuniq-ai-badge-ok"><?php echo esc_html( $yq_group['tag'] ); ?></span>
					<?php endif; ?>
				</h3>
				<p class="description"><?php echo esc_html( $yq_group['text'] ); ?></p>
				<div class="yq-providers">
					<?php
					foreach ( $yq_group['ids'] as $yq_id ) :
						if ( empty( $presets[ $yq_id ] ) ) {
							continue;
						}
						$yq_preset = $presets[ $yq_id ];
						?>
						<div class="yq-provider">
							<strong><?php echo esc_html( $yq_preset['label'] ); ?></strong>
							<p><?php echo esc_html( $yq_preset['note'] ); ?></p>
							<?php if ( ! empty( $yq_preset['models'] ) ) : ?>
								<span class="yq-provider-line">مدل پیشنهادی: <code dir="ltr"><?php echo esc_html( $yq_preset['models'][0] ); ?></code></span>
							<?php endif; ?>
							<?php if ( ! empty( $yq_preset['key_url'] ) ) : ?>
								<a class="button button-small" href="<?php echo esc_url( $yq_preset['key_url'] ); ?>" target="_blank" rel="noopener noreferrer">سایت سرویس ↗</a>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>

		<p class="yq-note">سرویس شما در این فهرست نیست؟ هر سرویسی که «سازگار با OpenAI» باشد کار می‌کند: در تنظیمات گزینه «سفارشی» را انتخاب کنید و آدرس و نام مدل را از مستندات همان سرویس بردارید. آدرس و نام مدل سرویس‌ها گاهی تغییر می‌کند؛ اگر اتصال برقرار نشد، مقدار دقیق را در پنل کاربری همان سرویس ببینید.</p>
	</section>

	<section class="yuniq-ai-card" id="yq-g-steps">
		<h2>دریافت کلید و اتصال، قدم‌به‌قدم</h2>
		<ol class="yuniq-ai-steps">
			<li>
				<strong>در سایت سرویس ثبت‌نام کنید.</strong>
				<span>با ایمیل یا شماره موبایل یک حساب کاربری بسازید.</span>
			</li>
			<li>
				<strong>حساب را شارژ کنید.</strong>
				<span>در بخش «کیف پول»، «اعتبار» یا «Billing» مبلغ کمی شارژ کنید. برای شروع و آزمایش، کمترین مبلغ ممکن کافی است.</span>
			</li>
			<li>
				<strong>یک کلید API بسازید.</strong>
				<span>در پنل کاربری به بخش «کلیدهای API» یا «API Keys» بروید و «ساخت کلید جدید» را بزنید. کلید یک متن طولانی است (معمولاً با <code dir="ltr">sk-</code> یا <code dir="ltr">aa-</code> شروع می‌شود). <strong>همان لحظه کپی‌اش کنید</strong>؛ بیشتر سرویس‌ها کلید را فقط یک بار نشان می‌دهند.</span>
			</li>
			<li>
				<strong>کلید را در افزونه وارد کنید.</strong>
				<span>به <a href="<?php echo esc_url( $yq_settings_url ); ?>">تنظیمات ← هوش مصنوعی و پاسخ‌ها</a> بروید. از فهرست «سرویس» همان سرویسی را که کلید گرفته‌اید انتخاب کنید (آدرس و مدل خودکار پر می‌شود) و کلید را در فیلد «کلید API» بچسبانید.</span>
			</li>
			<li>
				<strong>«تست اتصال» را بزنید.</strong>
				<span>اگر پیام سبز دیدید، همه‌چیز درست است. اگر قرمز بود، متن خطا می‌گوید مشکل از کجاست (بخش «رفع خطا» را ببینید).</span>
			</li>
			<li>
				<strong>ذخیره و ایندکس کنید.</strong>
				<span>«ذخیره تنظیمات» را بزنید، سپس در صفحه <a href="<?php echo esc_url( admin_url( 'admin.php?page=yuniq-ai-knowledge' ) ); ?>">پایگاه دانش</a> «شروع ایندکس‌گذاری» را اجرا کنید تا دستیار سایت شما را بشناسد.</span>
			</li>
		</ol>
	</section>

	<section class="yuniq-ai-card" id="yq-g-cost">
		<h2>هزینه چقدر می‌شود؟</h2>
		<p>سرویس‌ها بر اساس <strong>توکن</strong> حساب می‌کنند (هر توکن تقریباً نصف یک کلمه فارسی). در هر پیام، سوال بازدیدکننده، بخشی از محتوای سایت و پاسخ دستیار شمرده می‌شود؛ یعنی هر پاسخ معمولاً بین ۱٬۰۰۰ تا ۳٬۰۰۰ توکن. قیمت هر مدل در صفحه تعرفه سرویس نوشته شده است.</p>
		<div class="yq-grid-3">
			<div class="yq-fact">
				<span class="dashicons dashicons-performance" aria-hidden="true"></span>
				<strong>مدل سبک انتخاب کنید</strong>
				<p>مدل‌هایی که در نامشان mini، flash یا haiku دارند چند ده برابر ارزان‌تر و سریع‌ترند و برای پشتیبانی سایت کاملاً کافی هستند.</p>
			</div>
			<div class="yq-fact">
				<span class="dashicons dashicons-editor-alignright" aria-hidden="true"></span>
				<strong>پاسخ کوتاه</strong>
				<p>در «سبک پاسخ‌گویی»، طول پاسخ را روی «کوتاه» بگذارید. «منابع از پایگاه دانش» و «حافظه گفتگو» را هم بی‌دلیل بالا نبرید.</p>
			</div>
			<div class="yq-fact">
				<span class="dashicons dashicons-shield" aria-hidden="true"></span>
				<strong>سقف بگذارید</strong>
				<p>در تب «امنیت» سقف روزانه پیام تعیین کنید، و در پنل خود سرویس هم سقف هزینه ماهانه بگذارید تا هیچ‌وقت غافلگیر نشوید.</p>
			</div>
		</div>
	</section>

	<section class="yuniq-ai-card" id="yq-g-safe">
		<h2>از کلید خود مراقبت کنید</h2>
		<ul class="yq-list">
			<li><strong>کلید را به هیچ‌کس ندهید</strong> و در پیام‌رسان، تیکت یا اسکرین‌شات نفرستید. پشتیبانی افزونه هرگز کلید شما را نمی‌خواهد.</li>
			<li><strong>برای هر سایت یک کلید جدا بسازید.</strong> اگر یکی لو رفت، فقط همان را باطل می‌کنید.</li>
			<li><strong>اگر شک کردید کلید لو رفته،</strong> همان لحظه در پنل سرویس آن را حذف (Revoke) کنید و کلید تازه بسازید.</li>
			<li><strong>سقف هزینه بگذارید.</strong> بیشتر سرویس‌ها اجازه می‌دهند برای هر کلید یا کل حساب سقف مصرف تعیین کنید.</li>
			<li>این افزونه کلید را <strong>رمزنگاری‌شده</strong> در پایگاه‌داده نگه می‌دارد، هرگز به مرورگر بازدیدکننده نمی‌فرستد و در گزارش خطاها هم نمی‌نویسد.</li>
		</ul>
	</section>

	<section class="yuniq-ai-card" id="yq-g-use">
		<h2>استفاده روزمره</h2>
		<div class="yuniq-ai-faq">
			<details>
				<summary>دستیار چطور از محتوای سایت من باخبر می‌شود؟</summary>
				<p>یک بار در «پایگاه دانش» ایندکس را اجرا می‌کنید. از آن به بعد، هر نوشته، برگه یا محصولی که ذخیره، ویرایش یا حذف کنید خودکار به‌روز می‌شود. اگر تنظیمات «خزنده محتوا» را عوض کردید، ایندکس را دوباره اجرا کنید.</p>
			</details>
			<details>
				<summary>چطور لحن و رفتار دستیار را تنظیم کنم؟</summary>
				<p>در تب «هوش مصنوعی و پاسخ‌ها»، لحن، طول و زبان پاسخ را انتخاب کنید. برای دستورهای خاص کسب‌وکارتان (مثلاً «ارسال رایگان بالای ۵۰۰ هزار تومان») متن «شخصیت و دستورالعمل دستیار» را ویرایش کنید.</p>
			</details>
			<details>
				<summary>گزینه‌هایی که بازدیدکننده می‌بیند از کجا می‌آید؟</summary>
				<p>«گزینه‌های شروع گفتگو» را خودتان در تب «عمومی» تعریف می‌کنید. گزینه‌های زیر هر پاسخ را دستیار خودش پیشنهاد می‌دهد و می‌توانید آن را در «سبک پاسخ‌گویی» خاموش کنید.</p>
			</details>
			<details>
				<summary>چطور به‌جای ربات، خودم به مشتری پاسخ بدهم؟</summary>
				<p>در تب «پشتیبانی زنده» آن را روشن و ایمیل یا تلگرام را برای اعلان تنظیم کنید. هر وقت بازدیدکننده‌ای «صحبت با کارشناس» را بزند باخبر می‌شوید و از صفحه «پشتیبانی زنده» پاسخ می‌دهید.</p>
			</details>
			<details>
				<summary>می‌خواهم دستیار یک صفحه کامل داشته باشد</summary>
				<p>در تب «نمایش و پیشرفته»، «صفحه اختصاصی دستیار» را روشن کنید و کد کوتاه <code dir="ltr">[yuniq_ai_page]</code> را در یک برگه بگذارید.</p>
			</details>
		</div>
	</section>

	<section class="yuniq-ai-card" id="yq-g-errors">
		<h2>رفع خطاهای رایج</h2>
		<p class="description">اول به صفحه <a href="<?php echo esc_url( admin_url( 'admin.php?page=yuniq-ai-status' ) ); ?>">وضعیت و خطاها</a> سر بزنید؛ دلیل دقیق هر خطا آنجا ثبت می‌شود.</p>
		<div class="yuniq-ai-faq">
			<details>
				<summary>خطای ۴۰۱ یا ۴۰۳ (دسترسی رد شد)</summary>
				<p>کلید اشتباه، منقضی یا مربوط به سرویس دیگری است؛ یا هاست در ایران است و سرویس خارجی IP را رد می‌کند. مطمئن شوید کلید را از همان سرویسی گرفته‌اید که انتخاب کرده‌اید و حساب اعتبار دارد.</p>
			</details>
			<details>
				<summary>خطای ۴۰۴ (یافت نشد)</summary>
				<p>آدرس API یا نام مدل اشتباه است. سرویس را دوباره از فهرست انتخاب کنید تا آدرس درست پر شود، و با «دریافت فهرست مدل‌ها» نام دقیق مدل را بردارید.</p>
			</details>
			<details>
				<summary>خطای ۴۲۹ یا «اعتبار کافی نیست»</summary>
				<p>اعتبار حساب تمام شده یا تعداد درخواست‌ها از سقف سرویس گذشته است. حساب را شارژ کنید.</p>
			</details>
			<details>
				<summary>خطای اتصال، timeout یا cURL error</summary>
				<p>سرور سایت نمی‌تواند به سرویس وصل شود. اگر هاست در ایران است یک درگاه ایرانی انتخاب کنید؛ اگر خارج است از پشتیبانی هاست بخواهید اتصال خروجی به آن آدرس را باز کند.</p>
			</details>
			<details>
				<summary>بازدیدکننده می‌نویسد ولی پاسخی نمی‌گیرد</summary>
				<p>در صفحه «وضعیت و خطاها» ردیف «ارتباط ویجت با سایت» را ببینید. اگر قرمز است، یک افزونه امنیتی یا فایروال مسیر <code dir="ltr">/wp-json/yuniq-ai/</code> را بسته است.</p>
			</details>
			<details>
				<summary>دستیار اطلاعات سایت را نمی‌داند یا قیمت اشتباه می‌گوید</summary>
				<p>در «پایگاه دانش» ایندکس را دوباره اجرا کنید و مطمئن شوید نوع محتوای موردنظر در تب «خزنده محتوا» تیک خورده است.</p>
			</details>
		</div>
	</section>
</div>
