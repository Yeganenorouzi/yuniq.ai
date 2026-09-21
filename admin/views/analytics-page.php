<?php
/**
 * صفحه آمار و تحلیل
 *
 * @package Yuniq\Ai
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap yuniq-ai-admin-wrap" dir="rtl">
	<div class="yuniq-ai-header">
		<div class="yuniq-ai-header-brand">
			<span class="yuniq-ai-logo">✦</span>
			<div>
				<h1>آمار و تحلیل</h1>
				<p class="yuniq-ai-subtitle">بینش گفتگوها و معیارهای استفاده</p>
			</div>
		</div>
	</div>

	<div class="yuniq-ai-stats-row">
		<div class="yuniq-ai-stat-card">
			<span class="stat-value"><?php echo esc_html( number_format_i18n( $summary['total_conversations'] ) ); ?></span>
			<span class="stat-label">گفتگوها</span>
		</div>
		<div class="yuniq-ai-stat-card">
			<span class="stat-value"><?php echo esc_html( number_format_i18n( $summary['total_questions'] ) ); ?></span>
			<span class="stat-label">سوالات کاربران</span>
		</div>
		<div class="yuniq-ai-stat-card">
			<span class="stat-value"><?php echo esc_html( number_format_i18n( $summary['total_responses'] ) ); ?></span>
			<span class="stat-label">پاسخ‌های هوش مصنوعی</span>
		</div>
		<div class="yuniq-ai-stat-card">
			<span class="stat-value"><?php echo esc_html( number_format_i18n( (float) $summary['average_response_time'], 2 ) ); ?>s</span>
			<span class="stat-label">میانگین زمان پاسخ</span>
		</div>
	</div>

	<div class="yuniq-ai-card">
		<h2>موضوعات پرتکرار</h2>
		<?php if ( empty( $summary['popular_topics'] ) ) : ?>
			<p>هنوز داده‌ای وجود ندارد. پس از شروع گفتگوی بازدیدکنندگان، موضوعات اینجا نمایش داده می‌شوند.</p>
		<?php else : ?>
			<ul class="yuniq-ai-topics-list">
				<?php foreach ( $summary['popular_topics'] as $topic => $count ) : ?>
					<li>
						<span class="topic-name"><?php echo esc_html( $topic ); ?></span>
						<span class="topic-count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>

	<div class="yuniq-ai-card">
		<h2>فعالیت‌های اخیر</h2>
		<?php if ( empty( $recent ) ) : ?>
			<p>هنوز گفتگویی ثبت نشده است.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>زمان</th>
						<th>سوال</th>
						<th>پیش‌نمایش پاسخ</th>
						<th>توکن</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['created_at'] ); ?></td>
							<td><?php echo esc_html( wp_trim_words( $row['user_question'], 12 ) ); ?></td>
							<td><?php echo esc_html( $row['response_preview'] ); ?><?php echo strlen( $row['response_preview'] ) >= 120 ? '…' : ''; ?></td>
							<td><?php echo esc_html( $row['tokens_used'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
