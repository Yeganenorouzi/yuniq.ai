<?php
/**
 * صفحه پشتیبانی زنده (اتصال به کارشناس) و سرنخ‌ها
 *
 * @package Yuniq\Ai
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status_labels = array(
	'pending'  => 'در انتظار',
	'active'   => 'در حال گفتگو',
	'resolved' => 'بسته‌شده',
);
?>
<div class="wrap yuniq-ai-admin-wrap" dir="rtl">
	<div class="yuniq-ai-header">
		<div class="yuniq-ai-header-brand">
			<span class="yuniq-ai-logo">✦</span>
			<div>
				<h1>پشتیبانی زنده</h1>
				<p class="yuniq-ai-subtitle">گفتگوهایی که بازدیدکننده درخواست صحبت با کارشناس داده یا دستیار هوشمند نتوانسته کمک کند</p>
			</div>
		</div>
	</div>

	<div class="yuniq-ai-tabs">
		<nav class="yuniq-ai-tab-nav">
			<button type="button" class="yuniq-ai-tab-btn active" data-tab="ls-conversations">گفتگوها</button>
			<button type="button" class="yuniq-ai-tab-btn" data-tab="ls-leads">سرنخ‌ها</button>
		</nav>

		<div class="yuniq-ai-tab-panel active" id="tab-ls-conversations">
			<div class="yuniq-ai-ls-layout">
				<div class="yuniq-ai-ls-list-col">
					<div class="yuniq-ai-ls-filters">
						<button type="button" class="yuniq-ai-ls-filter active" data-status="">همه</button>
						<button type="button" class="yuniq-ai-ls-filter" data-status="pending">در انتظار</button>
						<button type="button" class="yuniq-ai-ls-filter" data-status="active">در حال گفتگو</button>
						<button type="button" class="yuniq-ai-ls-filter" data-status="resolved">بسته‌شده</button>
					</div>
					<div id="yuniq-ai-ls-list" class="yuniq-ai-ls-list">
						<?php if ( empty( $conversations ) ) : ?>
							<p class="description" style="padding:14px;">هنوز گفتگویی به کارشناس ارجاع نشده است.</p>
						<?php else : ?>
							<?php foreach ( $conversations as $c ) : ?>
								<button type="button"
									class="yuniq-ai-ls-row"
									data-session-id="<?php echo esc_attr( $c['session_id'] ); ?>"
									data-status="<?php echo esc_attr( $c['status'] ); ?>">
									<span class="yuniq-ai-ls-row-top">
										<span class="yuniq-ai-ls-row-name"><?php echo esc_html( $c['visitor_name'] ? $c['visitor_name'] : 'بازدیدکننده ناشناس' ); ?></span>
										<span class="yuniq-ai-ls-badge yuniq-ai-ls-badge-<?php echo esc_attr( $c['status'] ); ?>">
											<?php echo esc_html( isset( $status_labels[ $c['status'] ] ) ? $status_labels[ $c['status'] ] : $c['status'] ); ?>
										</span>
									</span>
									<span class="yuniq-ai-ls-row-meta">
										<?php echo esc_html( $c['last_message_at'] ? $c['last_message_at'] : $c['created_at'] ); ?>
										<?php if ( ! empty( $c['unread_for_admin'] ) ) : ?>
											<span class="yuniq-ai-ls-unread-dot"></span>
										<?php endif; ?>
									</span>
								</button>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>

				<div class="yuniq-ai-ls-thread-col">
					<div id="yuniq-ai-ls-thread-empty" class="yuniq-ai-ls-thread-empty">
						یک گفتگو را از فهرست انتخاب کنید.
					</div>
					<div id="yuniq-ai-ls-thread" class="yuniq-ai-ls-thread" style="display:none;">
						<div class="yuniq-ai-ls-thread-header">
							<div>
								<strong id="yuniq-ai-ls-thread-name">—</strong>
								<span id="yuniq-ai-ls-thread-contact" class="description"></span>
							</div>
							<div class="yuniq-ai-ls-thread-actions">
								<button type="button" class="button" id="yuniq-ai-ls-claim">اختصاص به من</button>
								<button type="button" class="button" id="yuniq-ai-ls-resolve">بستن گفتگو</button>
							</div>
						</div>
						<div id="yuniq-ai-ls-messages" class="yuniq-ai-ls-messages"></div>
						<form id="yuniq-ai-ls-reply-form" class="yuniq-ai-ls-reply-form">
							<textarea id="yuniq-ai-ls-reply-input" rows="2" placeholder="پاسخ خود را بنویسید..."></textarea>
							<button type="submit" class="button button-primary">ارسال</button>
						</form>
					</div>
				</div>
			</div>
		</div>

		<div class="yuniq-ai-tab-panel" id="tab-ls-leads">
			<div class="yuniq-ai-card">
				<h2>سرنخ‌های ثبت‌شده</h2>
				<p class="description">فرم‌هایی که بازدیدکنندگان داخل گفتگو با دستیار هوشمند پر کرده‌اند.</p>
				<div id="yuniq-ai-ls-leads-table">
					<p class="description">در حال بارگذاری...</p>
				</div>
			</div>
		</div>
	</div>
</div>
