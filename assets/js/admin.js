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

	/* ---------- Lead form fields repeater (Settings > Live Support) ---------- */
	var leadFieldIndex = $('#yuniq-ai-lead-fields .yuniq-ai-qa-row').length;

	$('#yuniq-ai-add-lead-field').on('click', function () {
		var name = 'yuniq_ai_settings[lead_forms][0][fields][' + leadFieldIndex + ']';
		var html =
			'<div class="yuniq-ai-qa-row">' +
			'<input type="text" name="' + name + '[label]" placeholder="عنوان فیلد" style="width:200px;" />' +
			'<select name="' + name + '[type]">' +
			'<option value="text">متن</option>' +
			'<option value="tel">تلفن</option>' +
			'<option value="email">ایمیل</option>' +
			'<option value="textarea">متن بلند</option>' +
			'</select>' +
			'<label><input type="checkbox" name="' + name + '[required]" value="1" /> الزامی</label>' +
			'<button type="button" class="button yuniq-ai-remove-qa">&times;</button>' +
			'</div>';
		$('#yuniq-ai-lead-fields').append(html);
		leadFieldIndex++;
	});

	/* ---------- Live Support inbox ---------- */
	var $lsList = $('#yuniq-ai-ls-list');

	if ($lsList.length) {
		var lsState = { sessionId: null, filter: '' };

		function escHtml(text) {
			return String(text === undefined || text === null ? '' : text)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;');
		}

		function lsStatusLabel(status) {
			return { pending: i18n.lsStatusPending, active: i18n.lsStatusActive, resolved: i18n.lsStatusResolved }[status] || status;
		}

		function lsRefreshList() {
			post('yuniq_ai_ls_list', { status: lsState.filter }).done(function (res) {
				if (!res || !res.success) return;

				var rows = res.data.conversations || [];

				if (!rows.length) {
					$lsList.html('<p class="description" style="padding:14px;">' + escHtml(i18n.lsNoConversations || '') + '</p>');
					return;
				}

				var html = '';
				rows.forEach(function (c) {
					html += '<button type="button" class="yuniq-ai-ls-row' + (c.session_id === lsState.sessionId ? ' is-active' : '') + '" data-session-id="' + escHtml(c.session_id) + '">' +
						'<span class="yuniq-ai-ls-row-top">' +
						'<span class="yuniq-ai-ls-row-name">' + escHtml(c.visitor_name || 'بازدیدکننده ناشناس') + '</span>' +
						'<span class="yuniq-ai-ls-badge yuniq-ai-ls-badge-' + escHtml(c.status) + '">' + escHtml(lsStatusLabel(c.status)) + '</span>' +
						'</span>' +
						'<span class="yuniq-ai-ls-row-meta">' + escHtml(c.last_message_at || c.created_at || '') +
						(parseInt(c.unread_for_admin, 10) ? '<span class="yuniq-ai-ls-unread-dot"></span>' : '') +
						'</span></button>';
				});
				$lsList.html(html);
			});
		}

		function lsRenderThread(conversation, messages) {
			$('#yuniq-ai-ls-thread-empty').hide();
			$('#yuniq-ai-ls-thread').show();
			$('#yuniq-ai-ls-thread-name').text((conversation && conversation.visitor_name) || 'بازدیدکننده ناشناس');
			$('#yuniq-ai-ls-thread-contact').text((conversation && conversation.visitor_contact) || '');

			var html = '';
			messages.forEach(function (m) {
				// Message content is sanitized server-side with wp_kses_post
				// before storage, so it is safe to render as HTML here.
				html += '<div class="yuniq-ai-ls-msg yuniq-ai-ls-msg-' + escHtml(m.role) + '">' + m.content + '</div>';
			});
			var $box = $('#yuniq-ai-ls-messages').html(html);
			$box.scrollTop($box.prop('scrollHeight'));
		}

		function lsOpenConversation(sessionId) {
			lsState.sessionId = sessionId;
			$('.yuniq-ai-ls-row').removeClass('is-active');
			$('.yuniq-ai-ls-row[data-session-id="' + sessionId + '"]').addClass('is-active').find('.yuniq-ai-ls-unread-dot').remove();

			post('yuniq_ai_ls_messages', { session_id: sessionId }).done(function (res) {
				if (!res || !res.success) return;
				lsRenderThread(res.data.conversation, res.data.messages || []);
			});
		}

		function lsRefreshLeads() {
			post('yuniq_ai_ls_leads').done(function (res) {
				if (!res || !res.success) return;

				var leads = res.data.leads || [];

				if (!leads.length) {
					$('#yuniq-ai-ls-leads-table').html('<p class="description">هنوز سرنخی ثبت نشده است.</p>');
					return;
				}

				var html = '<table class="widefat striped"><thead><tr><th>تاریخ</th><th>فرم</th><th>اطلاعات</th></tr></thead><tbody>';
				leads.forEach(function (l) {
					var fields = l.fields || {};
					var fieldsHtml = Object.keys(fields).map(function (k) {
						return '<strong>' + escHtml(k) + ':</strong> ' + escHtml(fields[k]);
					}).join('<br>');
					html += '<tr><td>' + escHtml(l.created_at) + '</td><td>' + escHtml(l.form_key) + '</td><td>' + fieldsHtml + '</td></tr>';
				});
				html += '</tbody></table>';
				$('#yuniq-ai-ls-leads-table').html(html);
			});
		}

		$(document).on('click', '.yuniq-ai-ls-row', function () {
			lsOpenConversation($(this).data('session-id'));
		});

		$(document).on('click', '.yuniq-ai-ls-filter', function () {
			$('.yuniq-ai-ls-filter').removeClass('active');
			$(this).addClass('active');
			lsState.filter = $(this).data('status') || '';
			lsRefreshList();
		});

		$('#yuniq-ai-ls-reply-form').on('submit', function (e) {
			e.preventDefault();
			var $input = $('#yuniq-ai-ls-reply-input');
			var message = $input.val().trim();
			if (!message || !lsState.sessionId) return;

			post('yuniq_ai_ls_reply', { session_id: lsState.sessionId, message: message }).done(function (res) {
				if (res && res.success) {
					$input.val('');
					lsOpenConversation(lsState.sessionId);
					lsRefreshList();
				}
			});
		});

		$('#yuniq-ai-ls-claim').on('click', function () {
			if (!lsState.sessionId) return;
			post('yuniq_ai_ls_claim', { session_id: lsState.sessionId }).done(function () {
				lsOpenConversation(lsState.sessionId);
				lsRefreshList();
			});
		});

		$('#yuniq-ai-ls-resolve').on('click', function () {
			if (!lsState.sessionId || !confirm(i18n.lsConfirmResolve || '')) return;
			post('yuniq_ai_ls_resolve', { session_id: lsState.sessionId }).done(function () {
				lsOpenConversation(lsState.sessionId);
				lsRefreshList();
			});
		});

		lsRefreshList();
		lsRefreshLeads();

		setInterval(function () {
			lsRefreshList();
			lsRefreshLeads();
			if (lsState.sessionId) lsOpenConversation(lsState.sessionId);
		}, 8000);
	}

})(jQuery);
