<?php
/**
 * صفحه پایگاه دانش و لاگ کرال
 *
 * @package Yuniq\Ai
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$type_labels = array(
	'product'           => 'محصولات',
	'post'              => 'مقالات',
	'page'              => 'برگه‌ها',
	'tax_category'      => 'دسته مقالات',
	'tax_post_tag'      => 'برچسب مقالات',
	'tax_product_cat'   => 'دسته محصولات',
	'tax_product_tag'   => 'برچسب محصولات',
);
?>
<div class="wrap yuniq-ai-admin-wrap" dir="rtl">
	<div class="yuniq-ai-header">
		<div class="yuniq-ai-header-brand">
			<span class="yuniq-ai-logo">✦</span>
			<div>
				<h1>پایگاه دانش و لاگ ایندکس</h1>
				<p class="yuniq-ai-subtitle">فقط محتوایی که در تنظیمات خزنده تیک زده‌اید ایندکس می‌شود</p>
			</div>
		</div>
	</div>

	<div class="yuniq-ai-stats-row">
		<div class="yuniq-ai-stat-card">
			<span class="stat-value" id="yuniq-ai-kb-count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
			<span class="stat-label">کل آیتم‌های ایندکس‌شده</span>
		</div>
		<?php
		foreach ( array( 'product' => 'محصولات', 'post' => 'مقالات', 'page' => 'برگه‌ها' ) as $ptype => $label ) :
			$c = isset( $counts_by_type[ $ptype ] ) ? $counts_by_type[ $ptype ] : 0;
			?>
			<div class="yuniq-ai-stat-card">
				<span class="stat-value"><?php echo esc_html( number_format_i18n( $c ) ); ?></span>
				<span class="stat-label"><?php echo esc_html( $label ); ?></span>
			</div>
		<?php endforeach; ?>
		<div class="yuniq-ai-stat-card">
			<span class="stat-value" id="yuniq-ai-crawl-status">
				<?php
				if ( $status && isset( $status['status'] ) ) {
					$map = array( 'running' => 'در حال اجرا', 'completed' => 'تکمیل‌شده', 'pending' => 'در انتظار', 'failed' => 'ناموفق' );
					echo esc_html( isset( $map[ $status['status'] ] ) ? $map[ $status['status'] ] : $status['status'] );
				} else {
					echo 'هرگز اجرا نشده';
				}
				?>
			</span>
			<span class="stat-label">وضعیت آخرین کرال</span>
		</div>
	</div>

	<?php if ( $status && ! empty( $status['finished_at'] ) ) : ?>
	<p style="color:#64748b;margin:-8px 0 20px;">آخرین کرال: <strong><?php echo esc_html( $status['finished_at'] ); ?></strong>
		<?php if ( ! empty( $status['processed_items'] ) ) : ?>
			— <?php echo esc_html( number_format_i18n( $status['processed_items'] ) ); ?> آیتم
		<?php endif; ?>
		<?php if ( ! empty( $status['message'] ) ) : ?>
			— <?php echo esc_html( $status['message'] ); ?>
		<?php endif; ?>
	</p>
	<?php endif; ?>

	<div class="yuniq-ai-card">
		<h2>اجرای کرال</h2>
		<p>بر اساس تیک‌های تب «خزنده محتوا» در تنظیمات، صفحات را ایندکس می‌کند و لیست زیر را به‌روز می‌کند.</p>
		<p class="description">ایندکس به صورت دسته‌ای و پشت سر هم اجرا می‌شود، بنابراین روی سایت‌های بزرگ هم قطع نمی‌شود. می‌توانید هر لحظه آن را متوقف کنید.</p>
		<p>
			<button type="button" class="button button-primary button-hero" id="yuniq-ai-start-crawl">شروع ایندکس‌گذاری</button>
			<button type="button" class="button button-secondary" id="yuniq-ai-stop-crawl" style="margin-right:8px;display:none;">توقف</button>
			<button type="button" class="button button-secondary" id="yuniq-ai-rebuild-kb" style="margin-right:8px;">پاک و بازسازی کامل</button>
			<button type="button" class="button" id="yuniq-ai-clear-kb" style="margin-right:8px;color:#b91c1c;">حذف همه داده‌ها</button>
		</p>
		<div id="yuniq-ai-crawl-progress" style="display:none;margin-top:16px;">
			<div class="yuniq-ai-progress-bar">
				<div class="yuniq-ai-progress-fill" id="yuniq-ai-progress-fill"></div>
			</div>
			<p id="yuniq-ai-crawl-message"></p>
		</div>
	</div>

	<div class="yuniq-ai-card">
		<h2>لاگ صفحات ایندکس‌شده</h2>
		<p class="description">هر ردیف یک صفحه/دسته است که کرال شده و در پایگاه دانش ذخیره شده است.</p>
		<?php if ( empty( $recent ) ) : ?>
			<p>هنوز چیزی ایندکس نشده. نوع محتوا را در تنظیمات انتخاب کنید و «شروع ایندکس‌گذاری» را بزنید.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:40px;">#</th>
						<th>عنوان</th>
						<th>نوع</th>
						<th>آدرس</th>
						<th>تاریخ ایندکس</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$i = 1;
					foreach ( $recent as $item ) :
						$ptype = $item['post_type'];
						$label = isset( $type_labels[ $ptype ] ) ? $type_labels[ $ptype ] : $ptype;
						?>
						<tr>
							<td><?php echo esc_html( $i++ ); ?></td>
							<td><strong><?php echo esc_html( $item['title'] ); ?></strong></td>
							<td><span class="yuniq-ai-type-badge"><?php echo esc_html( $label ); ?></span></td>
							<td>
								<?php if ( ! empty( $item['url'] ) ) : ?>
									<a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $item['url'], PHP_URL_PATH ) ); ?></a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $item['indexed_at'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $count > count( $recent ) ) : ?>
				<p class="description" style="margin-top:12px;">نمایش <?php echo esc_html( count( $recent ) ); ?> مورد از <?php echo esc_html( number_format_i18n( $count ) ); ?> — برای دیدن همه، کرال را دوباره اجرا کنید یا تعداد را در کد افزایش دهید.</p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
