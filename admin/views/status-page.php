<?php
/**
 * صفحه وضعیت سیستم و گزارش خطاها
 *
 * @package Yuniq\Ai
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$yq_totals = array(
	'ok'   => 0,
	'warn' => 0,
	'fail' => 0,
);
foreach ( $checks as $yq_check ) {
	++$yq_totals[ $yq_check['status'] ];
}

$yq_sources = array(
	'ai'       => 'هوش مصنوعی',
	'notify'   => 'اعلان‌ها',
	'crawl'    => 'ایندکس',
	'db'       => 'پایگاه‌داده',
	'security' => 'امنیت',
	'system'   => 'سیستم',
);
$yq_icons   = array(
	'ok'   => 'yes-alt',
	'warn' => 'warning',
	'fail' => 'dismiss',
);
?>
<div class="wrap yuniq-ai-admin-wrap" dir="rtl">
	<div class="yuniq-ai-header">
		<div class="yuniq-ai-header-brand">
			<span class="yuniq-ai-logo">✦</span>
			<div>
				<h1>وضعیت و خطاها</h1>
				<p class="yuniq-ai-subtitle">همه‌چیز درست کار می‌کند؟ اگر نه، دلیلش و راه رفعش همین‌جاست.</p>
			</div>
		</div>
		<div class="yq-hero-chips">
			<span class="yq-chip yq-chip-ok"><?php echo esc_html( number_format_i18n( $yq_totals['ok'] ) ); ?> سالم</span>
			<?php if ( $yq_totals['warn'] ) : ?>
				<span class="yq-chip yq-chip-warn"><?php echo esc_html( number_format_i18n( $yq_totals['warn'] ) ); ?> هشدار</span>
			<?php endif; ?>
			<?php if ( $yq_totals['fail'] ) : ?>
				<span class="yq-chip yq-chip-fail"><?php echo esc_html( number_format_i18n( $yq_totals['fail'] ) ); ?> مشکل</span>
			<?php endif; ?>
		</div>
	</div>

	<div class="yuniq-ai-card">
		<h2>بررسی سلامت</h2>
		<p class="description">مشکل‌ها اول نمایش داده می‌شوند. زیر هر مورد نوشته شده چه کاری باید انجام دهید.</p>
		<ul class="yq-checks" id="yuniq-ai-checks">
			<?php
			foreach ( array( 'fail', 'warn', 'ok' ) as $yq_status ) :
				foreach ( $checks as $yq_check ) :
					if ( $yq_check['status'] !== $yq_status ) {
						continue;
					}
					?>
					<li class="yq-check yq-check-<?php echo esc_attr( $yq_status ); ?>">
						<span class="dashicons dashicons-<?php echo esc_attr( $yq_icons[ $yq_status ] ); ?>" aria-hidden="true"></span>
						<div>
							<strong><?php echo esc_html( $yq_check['title'] ); ?></strong>
							<p><?php echo esc_html( $yq_check['text'] ); ?></p>
						</div>
					</li>
					<?php
				endforeach;
			endforeach;
			?>
		</ul>
	</div>

	<div class="yuniq-ai-card">
		<h2>ابزارها</h2>
		<div class="yq-tools">
			<div class="yq-tool">
				<strong>اعلان آزمایشی</strong>
				<p class="description">یک پیام آزمایشی به ایمیل و تلگرامِ تنظیم‌شده می‌فرستد تا مطمئن شوید درخواست‌های مشتریان به دستتان می‌رسد.</p>
				<button type="button" class="button" id="yuniq-ai-test-notify">ارسال اعلان آزمایشی</button>
				<div class="yuniq-ai-test-result" id="yuniq-ai-notify-result" aria-live="polite"></div>
			</div>
			<div class="yq-tool">
				<strong>گزارش برای پشتیبانی</strong>
				<p class="description">خلاصه وضعیت و آخرین خطاها را کپی کنید و برای پشتیبانی بفرستید. کلید API و توکن‌ها در این گزارش نیستند.</p>
				<button type="button" class="button" id="yuniq-ai-copy-report">کپی گزارش</button>
				<textarea id="yuniq-ai-report" class="yq-report" readonly dir="ltr" rows="4"><?php echo esc_textarea( $report ); ?></textarea>
			</div>
			<div class="yq-tool">
				<strong>راهنمای کامل</strong>
				<p class="description">دریافت کلید API، انتخاب سرویس، کاهش هزینه و رفع خطاهای رایج، قدم‌به‌قدم.</p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=yuniq-ai-guide' ) ); ?>">باز کردن راهنما</a>
			</div>
		</div>
	</div>

	<div class="yuniq-ai-card">
		<div class="yq-card-head">
			<div>
				<h2>گزارش خطاها</h2>
				<p class="description">هر خطایی که افزونه با آن روبه‌رو شود اینجا ثبت می‌شود (حداکثر ۱۰۰ مورد آخر). خطاهای تکراری یک ردیف با شمارنده می‌شوند.</p>
			</div>
			<?php if ( $entries ) : ?>
				<button type="button" class="button yq-danger-link" id="yuniq-ai-clear-log">پاک کردن گزارش</button>
			<?php endif; ?>
		</div>

		<?php if ( ! $entries ) : ?>
			<div class="yq-empty">
				<span class="dashicons dashicons-smiley" aria-hidden="true"></span>
				<strong>هیچ خطایی ثبت نشده است</strong>
				<p>اگر مشکلی پیش بیاید، جزئیاتش اینجا نمایش داده می‌شود.</p>
			</div>
		<?php else : ?>
			<div class="yq-log-filters" role="group" aria-label="فیلتر گزارش">
				<button type="button" class="yuniq-ai-ls-filter active" data-level="">همه</button>
				<button type="button" class="yuniq-ai-ls-filter" data-level="error">خطاها</button>
				<button type="button" class="yuniq-ai-ls-filter" data-level="warning">هشدارها</button>
			</div>
			<ul class="yq-log" id="yuniq-ai-log">
				<?php foreach ( $entries as $yq_entry ) : ?>
					<li class="yq-log-row yq-log-<?php echo esc_attr( $yq_entry['level'] ); ?>" data-level="<?php echo esc_attr( $yq_entry['level'] ); ?>">
						<div class="yq-log-meta">
							<span class="yq-chip <?php echo 'error' === $yq_entry['level'] ? 'yq-chip-fail' : 'yq-chip-warn'; ?>"><?php echo 'error' === $yq_entry['level'] ? 'خطا' : 'هشدار'; ?></span>
							<span class="yq-log-source"><?php echo esc_html( isset( $yq_sources[ $yq_entry['source'] ] ) ? $yq_sources[ $yq_entry['source'] ] : $yq_entry['source'] ); ?></span>
							<time><?php echo esc_html( \Yuniq\Ai\Support\Text::human_date( $yq_entry['time'] ) ); ?></time>
							<?php if ( (int) $yq_entry['count'] > 1 ) : ?>
								<span class="yq-log-count"><?php echo esc_html( number_format_i18n( (int) $yq_entry['count'] ) ); ?> بار</span>
							<?php endif; ?>
						</div>
						<p class="yq-log-message" dir="auto"><?php echo esc_html( $yq_entry['message'] ); ?></p>
						<?php if ( ! empty( $yq_entry['context'] ) ) : ?>
							<code class="yq-log-context" dir="ltr">
								<?php
								$yq_parts = array();
								foreach ( (array) $yq_entry['context'] as $yq_key => $yq_value ) {
									$yq_parts[] = $yq_key . ': ' . $yq_value;
								}
								echo esc_html( implode( '  ·  ', $yq_parts ) );
								?>
							</code>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</div>
