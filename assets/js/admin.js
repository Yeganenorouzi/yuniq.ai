/**
 * Yuniq.ai - Admin Scripts
 *
 * @author Yegane Norouzi <https://github.com/Yeganenorouzi>
 */
(function ($) {
	'use strict';

	var i18n = (window.yuniqAdmin && yuniqAdmin.i18n) || {};

	/* ---------- Tabs ---------- */
	$(document).on('click', '.yuniq-ai-tab-btn', function () {
		var tab = $(this).data('tab');
		$('.yuniq-ai-tab-btn').removeClass('active');
		$(this).addClass('active');
		$('.yuniq-ai-tab-panel').removeClass('active');
		$('#tab-' + tab).addClass('active');
	});

	if ($.fn.wpColorPicker) {
		$('.yuniq-ai-color-picker').wpColorPicker();
	}

	$('#yuniq-ai-widget-position').on('change', function () {
		if ($(this).val() === 'custom') {
			$('.yuniq-ai-custom-pos').show();
		} else {
			$('.yuniq-ai-custom-pos').hide();
		}
	});

	/* ---------- Media pickers ---------- */
	function openMediaPicker(title, targetSelector) {
		var frame = wp.media({
			title: title,
			button: { text: i18n.useThisImage || 'استفاده از این تصویر' },
			multiple: false
		});
		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			$(targetSelector).val(attachment.url);
		});
		frame.open();
	}

	$('#yuniq-ai-upload-avatar').on('click', function (e) {
		e.preventDefault();
		openMediaPicker(i18n.chooseAvatar || 'انتخاب آواتار / لوگو', '#yuniq-ai-avatar-url');
	});

	$('#yuniq-ai-upload-logo').on('click', function (e) {
		e.preventDefault();
		openMediaPicker(i18n.chooseLogo || 'انتخاب لوگو', '#yuniq-ai-logo-url');
	});

	/* ---------- Quick actions ---------- */
	var qaIndex = $('#yuniq-ai-quick-actions .yuniq-ai-qa-row').length;

	$('#yuniq-ai-add-qa').on('click', function () {
		var name = 'yuniq_ai_settings[quick_actions][' + qaIndex + ']';
		var html =
			'<div class="yuniq-ai-qa-row" style="flex-wrap:wrap;">' +
			'<input type="text" name="' + name + '[label]" placeholder="عنوان کارت" style="width:110px;" />' +
			'<input type="text" name="' + name + '[desc]" placeholder="توضیح کوتاه" style="width:110px;" />' +
			'<input type="text" name="' + name + '[prompt]" placeholder="پرامپت AI" style="width:180px;" />' +
			'<input type="url" name="' + name + '[link]" placeholder="لینک اختیاری" style="width:180px;" />' +
			'<button type="button" class="button yuniq-ai-remove-qa">&times;</button>' +
			'</div>';
		$('#yuniq-ai-quick-actions').append(html);
		qaIndex++;
	});

	$(document).on('click', '.yuniq-ai-remove-qa', function () {
		$(this).closest('.yuniq-ai-qa-row').remove();
	});

	/* ---------- Batched crawl ---------- */
	var crawl = {
		running: false,
		aborted: false,
		logId: 0
	};

	var $startBtn = $('#yuniq-ai-start-crawl');
	var $stopBtn = $('#yuniq-ai-stop-crawl');
	var $rebuildBtn = $('#yuniq-ai-rebuild-kb');
	var $progress = $('#yuniq-ai-crawl-progress');
	var $fill = $('#yuniq-ai-progress-fill');
	var $msg = $('#yuniq-ai-crawl-message');

	function post(action, data) {
		return $.post(yuniqAdmin.ajaxUrl, $.extend({
			action: action,
			nonce: yuniqAdmin.nonce
		}, data || {}));
	}

	function setProgress(processed, total) {
		var percent = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
		$fill.css('width', percent + '%');
		$msg.text(
			(i18n.indexed || 'ایندکس‌شده:') + ' ' + processed +
			(total ? ' / ' + total : '') + ' (' + percent + '%)'
		);
	}

	function crawlUiStart() {
		crawl.running = true;
		crawl.aborted = false;
		$startBtn.prop('disabled', true).text(i18n.crawling || 'در حال ایندکس‌گذاری...');
		$rebuildBtn.prop('disabled', true);
		$stopBtn.show().prop('disabled', false);
		$progress.show();
		$fill.css('width', '0%');
		$msg.text(i18n.starting || 'در حال شروع...');
	}

	function crawlUiEnd(message, reload) {
		crawl.running = false;
		$startBtn.prop('disabled', false).text(i18n.startIndex || 'شروع ایندکس‌گذاری');
		$rebuildBtn.prop('disabled', false);
		$stopBtn.hide();
		if (message) $msg.text(message);
		if (reload) {
			setTimeout(function () { location.reload(); }, 1200);
		}
	}

	/**
	 * Walks one batch at a time, so no single request has to survive the
	 * whole crawl and the progress bar reflects real work.
	 */
	function runBatch() {
		if (crawl.aborted) {
			crawlUiEnd(i18n.stopped || 'ایندکس‌گذاری متوقف شد.', true);
			return;
		}

		post('yuniq_ai_crawl_batch', { log_id: crawl.logId })
			.done(function (res) {
				if (!res || !res.success) {
					crawlUiEnd(i18n.error || 'خطایی رخ داد.');
					return;
				}

				var data = res.data || {};
				setProgress(data.processed || 0, data.total || 0);

				if (data.done) {
					$fill.css('width', '100%');
					$('#yuniq-ai-kb-count').text(data.processed || 0);
					$('#yuniq-ai-crawl-status').text(i18n.completedShort || 'تکمیل‌شده');
					crawlUiEnd(data.message || i18n.completed, true);
					return;
				}

				runBatch();
			})
			.fail(function () {
				crawlUiEnd(i18n.error || 'خطایی رخ داد.');
			});
	}

	function startCrawl() {
		crawlUiStart();

		post('yuniq_ai_start_crawl')
			.done(function (res) {
				if (!res || !res.success) {
					crawlUiEnd(i18n.error || 'خطایی رخ داد.');
					return;
				}

				var data = res.data || {};
				crawl.logId = data.log_id || 0;
				setProgress(0, data.total || 0);

				if (data.done || !crawl.logId) {
					crawlUiEnd(i18n.nothingToIndex || 'محتوایی برای ایندکس پیدا نشد.');
					return;
				}

				runBatch();
			})
			.fail(function () {
				crawlUiEnd(i18n.error || 'خطایی رخ داد.');
			});
	}

	$startBtn.on('click', function () {
		if (crawl.running) return;
		startCrawl();
	});

	$stopBtn.on('click', function () {
		if (!crawl.running) return;
		crawl.aborted = true;
		$stopBtn.prop('disabled', true);
		$msg.text(i18n.stopping || 'در حال توقف...');
		post('yuniq_ai_stop_crawl', { log_id: crawl.logId });
	});

	/* ---------- Knowledge base maintenance ---------- */
	$('#yuniq-ai-clear-kb').on('click', function () {
		if (!confirm(i18n.confirmClear)) return;

		var $btn = $(this);
		$btn.prop('disabled', true);

		post('yuniq_ai_clear_knowledge_base')
			.done(function (res) {
				if (res && res.success) {
					$('#yuniq-ai-kb-count').text('0');
					location.reload();
				}
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
	});

	$rebuildBtn.on('click', function () {
		if (crawl.running || !confirm(i18n.confirmRebuild)) return;

		$rebuildBtn.prop('disabled', true);

		post('yuniq_ai_clear_knowledge_base')
			.done(function () {
				startCrawl();
			})
			.fail(function () {
				$rebuildBtn.prop('disabled', false);
			});
	});

})(jQuery);
