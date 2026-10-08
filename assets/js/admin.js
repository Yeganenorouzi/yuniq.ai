/**
 * Yuniq.ai - Admin Scripts
 *
 * @author Yegane Norouzi <https://github.com/Yeganenorouzi>
 */
(function ($) {
	'use strict';

	var i18n = (window.yuniqAdmin && yuniqAdmin.i18n) || {};

	/* ---------- Unsaved changes ---------- */
	var $settingsForm = $('#yuniq-ai-settings-form');
	var dirty = false;
	var submitting = false;

	function markDirty() {
		if (dirty || !$settingsForm.length) return;
		dirty = true;
		$('#yuniq-ai-savebar').addClass('is-dirty');
		$('#yuniq-ai-save-status').text(i18n.unsaved || 'تغییرات ذخیره‌نشده دارید');
	}

	// Real user edits only: scripted updates (presets, the preview) call
	// markDirty() themselves where they change a value.
	$settingsForm.on('input change', 'input, select, textarea', function (e) {
		if (e.originalEvent) markDirty();
	});

	$settingsForm.on('submit', function () {
		submitting = true;
		$('#yuniq-ai-save-status').text(i18n.saving || 'در حال ذخیره...');
	});

	$(window).on('beforeunload', function () {
		if (dirty && !submitting) return i18n.unsaved || 'تغییرات ذخیره‌نشده دارید';
	});

	// Ctrl/Cmd+S saves, as people expect from an editor.
	$(document).on('keydown', function (e) {
		if ($settingsForm.length && (e.ctrlKey || e.metaKey) && String(e.key).toLowerCase() === 's') {
			e.preventDefault();
			$settingsForm.find('#submit').trigger('click');
		}
	});

	/* ---------- Tabs ---------- */
	// The last tab is remembered, so saving the form doesn't bounce the
	// admin back to "General" every time.
	var TAB_KEY = 'yuniq_ai_settings_tab';

	function showTab(tab) {
		var $btn = $('.yuniq-ai-tab-btn[data-tab="' + tab + '"]');
		if (!$btn.length) return;
		$('.yuniq-ai-tab-btn').removeClass('active');
		$btn.addClass('active');
		$('.yuniq-ai-tab-panel').removeClass('active');
		$('#tab-' + tab).addClass('active');
		if ($('#yuniq-ai-settings-form').length) {
			try { sessionStorage.setItem(TAB_KEY, tab); } catch (e) {}
		}
	}

	$(document).on('click', '.yuniq-ai-tab-btn', function () {
		showTab($(this).data('tab'));
	});

	if ($('#yuniq-ai-settings-form').length) {
		var savedTab = null;
		try { savedTab = sessionStorage.getItem(TAB_KEY); } catch (e) {}
		// A link such as …page=yuniq-ai#ai opens that tab directly.
		var hashTab = String(window.location.hash || '').replace(/[^a-z\-]/g, '');
		if (hashTab && $('.yuniq-ai-tab-btn[data-tab="' + hashTab + '"]').length) savedTab = hashTab;
		if (savedTab) showTab(savedTab);
	}

	function field(key) {
		return $('[name="yuniq_ai_settings[' + key + ']"]');
	}

	function fieldVal(key) {
		var $f = field(key);
		if (!$f.length) return '';
		if ($f.is(':radio')) return $f.filter(':checked').val() || '';
		if ($f.is(':checkbox')) return $f.is(':checked');
		return $f.val();
	}

	var pickersReady = false;
	setTimeout(function () { pickersReady = true; }, 600);

	if ($.fn.wpColorPicker) {
		$('.yuniq-ai-color-picker').wpColorPicker({
			change: function (event, ui) {
				// Fires before the input's own value updates.
				$(event.target).val(ui.color.toString());
				updatePreview();
				// The picker also fires while it initialises; that is not an edit.
				if (pickersReady) markDirty();
			},
			clear: function () {
				setTimeout(updatePreview, 0);
			}
		});
	}

	/* ---------- Range sliders show their value ---------- */
	$(document).on('input', '.yuniq-ai-range input[type="range"]', function () {
		$(this).siblings('output').text(this.value);
	});

	/* ---------- Rows that only matter for some choices ---------- */
	function toggleConditionalRows() {
		$('.yuniq-ai-when-avatar').toggle(fieldVal('launcher_icon') === 'avatar');
		$('.yuniq-ai-when-pill').toggle(fieldVal('launcher_shape') === 'pill');
		$('.yuniq-ai-when-custom-font').toggle(fieldVal('font_family') === 'custom');
		$('.yuniq-ai-when-paths').toggle(fieldVal('display_rule') !== 'all');
	}

	/* ---------- AI provider presets ---------- */
	var presets = (window.yuniqAdmin && yuniqAdmin.presets) || {};
	var $provider = $('#yuniq-ai-provider');
	var $endpoint = $('#yuniq-ai-endpoint');
	var $model = $('#yuniq-ai-model');

	function escText(text) {
		return $('<div>').text(text === undefined || text === null ? '' : String(text)).html();
	}

	function renderModelChips(models, label) {
		var $list = $('#yuniq-ai-model-list').empty();
		var $options = $('#yuniq-ai-model-options').empty();
		if (!models || !models.length) return;

		if (label) $list.append($('<span class="yuniq-ai-model-list-label">').text(label));
		models.forEach(function (m) {
			$list.append(
				$('<button type="button" class="yuniq-ai-chip">').text(m).attr('data-model', m).toggleClass('is-selected', m === $model.val())
			);
			$options.append($('<option>').attr('value', m));
		});
	}

	function renderProviderInfo() {
		var p = presets[$provider.val()];
		var $info = $('#yuniq-ai-provider-info');
		if (!p) { $info.empty(); return; }

		$info.empty();
		if (p.note) $info.append($('<p>').text(p.note));
		if (p.key_url) {
			$info.append(
				$('<a class="button button-small" target="_blank" rel="noopener noreferrer">')
					.attr('href', p.key_url)
					.text((i18n.getKey || 'دریافت کلید API') + ' ↗')
			);
		}
		renderModelChips(p.models, i18n.suggestedModels);
	}

	function isPresetEndpoint(url) {
		return !url || Object.keys(presets).some(function (id) {
			return presets[id].endpoint && presets[id].endpoint === url;
		});
	}

	$provider.on('change', function () {
		var p = presets[$provider.val()];
		if (p && p.endpoint && $endpoint.val() !== p.endpoint) {
			// Only overwrite silently when the current URL is itself a preset.
			if (isPresetEndpoint($endpoint.val()) || confirm(i18n.replaceEndpoint)) {
				$endpoint.val(p.endpoint);
				warnInsecureEndpoint();
			}
		}
		if (p && p.models && p.models.length && !$model.data('touched')) {
			$model.val(p.models[0]);
		}
		renderProviderInfo();
		$('#yuniq-ai-test-result').empty().removeClass('is-ok is-error is-pending');
	});

	$model.on('input', function () { $model.data('touched', true); });

	// A plain-http address sends the key unencrypted; say so right away.
	function warnInsecureEndpoint() {
		var url = $.trim($endpoint.val() || '');
		var insecure = /^http:\/\//i.test(url) && !/^http:\/\/(localhost|127\.0\.0\.1|\[::1\])([:\/]|$)/i.test(url);
		$('#yuniq-ai-endpoint-warning').text(insecure ? (i18n.insecureEndpoint || '') : '');
	}
	$endpoint.on('input change', warnInsecureEndpoint);
	if ($endpoint.length) warnInsecureEndpoint();

	$(document).on('click', '.yuniq-ai-chip[data-model]', function () {
		$model.val($(this).data('model')).data('touched', true);
		$('.yuniq-ai-chip[data-model]').removeClass('is-selected');
		$(this).addClass('is-selected');
		markDirty();
	});

	function connectionPayload(action) {
		return {
			action: action,
			nonce: yuniqAdmin.nonce,
			api_key: $('#yuniq-ai-api-key').val(),
			endpoint: $endpoint.val(),
			model: $model.val()
		};
	}

	function showResult(ok, message, detail) {
		var $r = $('#yuniq-ai-test-result');
		$r.removeClass('is-ok is-error is-pending').addClass(ok === null ? 'is-pending' : (ok ? 'is-ok' : 'is-error'));
		$r.text(message);
		if (detail) $r.append($('<small dir="ltr">').text(detail));
	}

	$('#yuniq-ai-test-connection').on('click', function () {
		var $btn = $(this).prop('disabled', true);
		showResult(null, i18n.testing);
		$.post(yuniqAdmin.ajaxUrl, connectionPayload('yuniq_ai_test_connection'))
			.done(function (res) {
				var data = (res && res.data) || {};
				if (res && res.success) {
					showResult(true, '✓ ' + data.message);
				} else {
					showResult(false, '✕ ' + (data.message || i18n.error), data.url ? 'POST ' + data.url + '/chat/completions' : '');
				}
			})
			.fail(function () { showResult(false, i18n.requestFailed); })
			.always(function () { $btn.prop('disabled', false); });
	});

	$('#yuniq-ai-load-models').on('click', function () {
		var $btn = $(this).prop('disabled', true);
		showResult(null, i18n.loadingModels);
		$.post(yuniqAdmin.ajaxUrl, connectionPayload('yuniq_ai_list_models'))
			.done(function (res) {
				var data = (res && res.data) || {};
				if (res && res.success && data.models && data.models.length) {
					renderModelChips(data.models);
					showResult(true, data.models.length + ' ' + i18n.modelsLoaded);
				} else if (res && res.success) {
					showResult(false, i18n.noModels);
				} else {
					showResult(false, '✕ ' + (data.message || i18n.error));
				}
			})
			.fail(function () { showResult(false, i18n.requestFailed); })
			.always(function () { $btn.prop('disabled', false); });
	});

	if ($provider.length) renderProviderInfo();

	/* ---------- System prompt templates ---------- */
	var promptTemplates = {
		shop: 'تو دستیار فروش و پشتیبانی فروشگاه اینترنتی ما هستی. به مشتری کمک کن محصول مناسب نیازش را پیدا کند و درباره قیمت، موجودی، ارسال، ضمانت و مرجوعی دقیق و فقط بر اساس اطلاعات سایت پاسخ بده. اگر محصولی موجود نیست، جایگزین مشابه پیشنهاد کن. هرگز قیمت یا تخفیفی را که در اطلاعات سایت نیست حدس نزن. لحنت صمیمی، کوتاه و محترمانه باشد.',
		services: 'تو دستیار شرکت ما هستی. خدمات، روند همکاری و مزیت‌های ما را به زبان ساده توضیح بده و به سوالات بازدیدکننده دقیق و کوتاه پاسخ بده. وقتی کسی به همکاری یا دریافت قیمت علاقه نشان داد، او را به ثبت درخواست مشاوره تشویق کن. درباره قیمت‌هایی که در اطلاعات سایت نیست قول نده و بگو کارشناسان ما دقیق اعلام می‌کنند. لحنت حرفه‌ای و گرم باشد.',
		education: 'تو مشاور آموزشی مجموعه ما هستی. بر اساس سطح و هدف هر فرد، دوره مناسب را از میان دوره‌های سایت پیشنهاد بده و سرفصل، مدت، شیوه برگزاری، قیمت و مدرک را از روی اطلاعات سایت توضیح بده. اگر سطح کاربر مشخص نیست، اول یک سوال کوتاه بپرس. لحنت دلگرم‌کننده و ساده باشد.',
		clinic: 'تو دستیار پذیرش مطب/کلینیک ما هستی. درباره خدمات، پزشکان، ساعات کاری، آدرس و روش نوبت‌گیری بر اساس اطلاعات سایت پاسخ بده. هرگز تشخیص پزشکی نده و دارو تجویز نکن؛ برای هر سوال درمانی توصیه کن با پزشک مشورت یا نوبت رزرو کند. در موارد اورژانسی بگو فوراً با اورژانس ۱۱۵ تماس بگیرند. لحنت آرام، مهربان و محترمانه باشد.'
	};

	$('.yuniq-ai-prompt-template').on('click', function () {
		var text = promptTemplates[$(this).data('template')];
		var $ta = $('#yuniq-ai-system-prompt');
		if (!text) return;
		if ($.trim($ta.val()) && !confirm(i18n.replacePrompt)) return;
		$ta.val(text).trigger('focus');
		markDirty();
	});

	/* ---------- Live preview (Settings > Design) ---------- */
	var $preview = $('#yuniq-ai-preview');

	// Same threshold as Widget::is_light() in PHP.
	function isLight(hex) {
		hex = hex.replace('#', '');
		if (hex.length === 3) hex = hex.replace(/(.)/g, '$1$1');
		var r = parseInt(hex.substr(0, 2), 16);
		var g = parseInt(hex.substr(2, 2), 16);
		var b = parseInt(hex.substr(4, 2), 16);
		return (0.299 * r + 0.587 * g + 0.114 * b) > 160;
	}

	function setOptionalColor(el, name, color) {
		if (color && /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(color)) {
			el.style.setProperty('--pv-' + name + '-bg', color);
			el.style.setProperty('--pv-' + name + '-text', isLight(color) ? '#14161c' : '#ffffff');
		} else {
			el.style.removeProperty('--pv-' + name + '-bg');
			el.style.removeProperty('--pv-' + name + '-text');
		}
	}

	function updatePreview() {
		toggleConditionalRows();
		if (!$preview.length) return;

		var el = $preview[0];
		var icon = fieldVal('launcher_icon') || 'bot';
		var avatar = fieldVal('avatar_url');
		var shape = fieldVal('launcher_shape') || 'squircle';
		var label = fieldVal('launcher_label');

		if (icon === 'avatar' && !avatar) icon = 'bot';
		if (shape === 'pill' && !label) shape = 'squircle';

		el.style.setProperty('--pv-primary', fieldVal('primary_color') || '#263DFF');
		el.style.setProperty('--pv-secondary', fieldVal('secondary_color') || '#111B55');
		el.style.setProperty('--pv-radius', (parseInt(fieldVal('border_radius'), 10) || 0) + 'px');
		el.style.setProperty('--pv-size', (parseInt(fieldVal('launcher_size'), 10) || 62) + 'px');
		el.style.setProperty('--pv-fs', (parseInt(fieldVal('font_size'), 10) || 14) + 'px');
		setOptionalColor(el, 'user', fieldVal('user_bubble_color'));
		setOptionalColor(el, 'bot', fieldVal('bot_bubble_color'));

		$preview.attr({
			'data-pos': fieldVal('widget_position') || 'bottom-right',
			'data-header': fieldVal('header_style') || 'gradient',
			'data-shape': shape,
			'data-bg': fieldVal('launcher_bg') || 'gradient',
			'data-icon': icon,
			'data-font': fieldVal('font_family') || 'vazirmatn'
		});
		$preview.toggleClass('no-badge', !fieldVal('show_online_badge'));
		$preview.find('.yq-pv-avatar').attr('src', avatar || '');
		$preview.find('.yq-pv-label').text(label);
		$preview.find('.yq-pv-name').text(fieldVal('assistant_name'));
		$preview.find('.yq-pv-titles small').text(fieldVal('header_subtitle'));
		$preview.find('.yq-pv-placeholder').text(fieldVal('input_placeholder'));

		var gTitle = fieldVal('greeting_title');
		var gText = fieldVal('greeting_text');
		var $g = $preview.find('.yq-pv-greeting');
		$g.toggle(!!fieldVal('greeting_enabled') && !!(gTitle || gText));
		$g.find('strong').text(gTitle).toggle(!!gTitle);
		$g.find('span').text(gText).toggle(!!gText);
	}

	$('#yuniq-ai-settings-form').on('input change', 'input, select, textarea', updatePreview);

	$(document).on('click', '[data-pv-theme]', function () {
		$('[data-pv-theme]').removeClass('is-active');
		$(this).addClass('is-active');
		$preview.attr('data-theme', $(this).data('pv-theme'));
	});

	updatePreview();

	/* ---------- Design tab: presets, reset, jump links ---------- */
	var stylePresets = {
		classic: { primary_color: '#263DFF', secondary_color: '#111B55', header_style: 'gradient', launcher_bg: 'gradient' },
		violet:  { primary_color: '#7C3AED', secondary_color: '#2E1065', header_style: 'gradient', launcher_bg: 'gradient' },
		ocean:   { primary_color: '#0891B2', secondary_color: '#164E63', header_style: 'gradient', launcher_bg: 'gradient' },
		emerald: { primary_color: '#059669', secondary_color: '#064E3B', header_style: 'brand', launcher_bg: 'gradient' },
		sunset:  { primary_color: '#F97316', secondary_color: '#9A3412', header_style: 'gradient', launcher_bg: 'gradient' },
		rose:    { primary_color: '#E11D48', secondary_color: '#881337', header_style: 'brand', launcher_bg: 'solid' },
		minimal: { primary_color: '#111827', secondary_color: '#374151', header_style: 'solid', launcher_bg: 'solid' }
	};

	function setField(key, value) {
		var $f = field(key);
		if (!$f.length) return;

		if ($f.is(':radio')) {
			$f.filter('[value="' + value + '"]').prop('checked', true);
		} else if ($f.is(':checkbox')) {
			$f.prop('checked', !!value);
		} else if ($f.hasClass('yuniq-ai-color-picker') && $.fn.wpColorPicker) {
			if (value) {
				$f.wpColorPicker('color', value);
			} else {
				$f.val('');
				$f.closest('.wp-picker-container').find('.wp-color-result').css('background-color', '');
			}
		} else {
			$f.val(value).trigger('input');
		}
	}

	function applyValues(values) {
		Object.keys(values).forEach(function (key) { setField(key, values[key]); });
		updatePreview();
		markDirty();
	}

	$(document).on('click', '.yq-preset', function () {
		var preset = stylePresets[$(this).data('preset')];
		if (!preset) return;
		$('.yq-preset').removeClass('is-active');
		$(this).addClass('is-active');
		applyValues(preset);
	});

	$(document).on('click', '.yq-reset-design', function () {
		if (!confirm(i18n.confirmResetDesign || 'همه تنظیمات طراحی به حالت اولیه برگردد؟')) return;
		$('.yq-preset').removeClass('is-active');
		applyValues($(this).data('defaults') || {});
	});

	$(document).on('click', '.yq-jump a', function (e) {
		var target = document.querySelector($(this).attr('href'));
		if (!target) return;
		e.preventDefault();
		if (target.tagName === 'DETAILS') target.open = true;
		target.scrollIntoView({ behavior: 'smooth', block: 'start' });
	});

	// Greeting text fields are dimmed while the greeting is switched off.
	function toggleGreetingFields() {
		$('.yq-greeting-fields').toggleClass('is-disabled', !fieldVal('greeting_enabled'));
	}
	$('#yuniq-ai-settings-form').on('change', '[name="yuniq_ai_settings[greeting_enabled]"]', toggleGreetingFields);
	toggleGreetingFields();

	/* ---------- Media pickers ---------- */
	function openMediaPicker(title, targetSelector) {
		var frame = wp.media({
			title: title,
			button: { text: i18n.useThisImage || 'استفاده از این تصویر' },
			multiple: false
		});
		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			$(targetSelector).val(attachment.url).trigger('change');
			markDirty();
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

	/* ---------- Starter options (quick actions) ---------- */
	var $qaList = $('#yuniq-ai-quick-actions');
	var qaIndex = $qaList.find('.yuniq-ai-qa-row').length;

	function qaField(name, key, label, placeholder, extra) {
		return $('<label class="yq-row-field">')
			.addClass(extra && extra.wide ? 'yq-row-wide' : '')
			.append($('<span>').text(label))
			.append(
				$('<input type="text">')
					.attr({ name: name + '[' + key + ']', placeholder: placeholder })
					.attr(extra && extra.ltr ? { dir: 'ltr' } : {})
			);
	}

	function toggleQaEmpty() {
		$('.yq-rows-empty').toggle($qaList.length > 0 && !$qaList.children().length);
	}

	$('#yuniq-ai-add-qa').on('click', function () {
		var name = 'yuniq_ai_settings[quick_actions][' + qaIndex + ']';
		var $row = $('<div class="yuniq-ai-qa-row yq-row">')
			.append(qaField(name, 'label', 'عنوان', 'مثلاً قیمت‌ها'))
			.append(qaField(name, 'desc', 'توضیح کوتاه', 'اختیاری'))
			.append(qaField(name, 'prompt', 'سوالی که پرسیده می‌شود', 'خالی = همان عنوان', { wide: true }))
			.append(qaField(name, 'link', 'یا لینک صفحه', '/services/', { wide: true, ltr: true }))
			.append('<button type="button" class="button yuniq-ai-remove-qa" aria-label="حذف این گزینه">&times;</button>');

		$qaList.append($row);
		$row.find('input').first().trigger('focus');
		qaIndex++;
		toggleQaEmpty();
		markDirty();
	});

	$(document).on('click', '.yuniq-ai-remove-qa', function () {
		$(this).closest('.yuniq-ai-qa-row').remove();
		toggleQaEmpty();
		markDirty();
	});

	toggleQaEmpty();

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

	function faNum(n) {
		try { return Number(n).toLocaleString('fa-IR'); } catch (e) { return String(n); }
	}

	function setProgress(processed, total) {
		var percent = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
		$fill.css('width', percent + '%');
		$msg.text(
			(i18n.indexed || 'ایندکس‌شده:') + ' ' + faNum(processed) +
			(total ? ' از ' + faNum(total) : '') + ' (' + faNum(percent) + '٪)'
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
					$('#yuniq-ai-kb-count').text(faNum(data.processed || 0));
					$('#yuniq-ai-crawl-status').text(i18n.completedShort || 'تکمیل‌شده');
					crawlUiEnd(data.message || i18n.completed, true);
					return;
				}

				crawl.retries = 0;
				runBatch();
			})
			.fail(function () {
				// One slow or dropped request should not throw away a long
				// crawl: the cursor lives on the server, so the same batch
				// can simply be asked for again.
				crawl.retries = (crawl.retries || 0) + 1;
				if (crawl.retries <= 3) {
					setTimeout(runBatch, 1500 * crawl.retries);
					return;
				}
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
			'<input type="text" name="' + name + '[label]" placeholder="عنوان فیلد (مثلاً نام شما)" />' +
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
		markDirty();
	});

	/* ---------- Status and error log screen ---------- */
	var $checks = $('#yuniq-ai-checks');

	if ($checks.length) {
		// Run from the browser on purpose: this is the same request the
		// widget makes, so a firewall that blocks visitors shows up here.
		var restRow = function (ok) {
			var $row = $('<li class="yq-check">').addClass(ok ? 'yq-check-ok' : 'yq-check-fail')
				.append($('<span class="dashicons" aria-hidden="true">').addClass(ok ? 'dashicons-yes-alt' : 'dashicons-dismiss'))
				.append(
					$('<div>')
						.append($('<strong>').text(ok ? i18n.restOk : i18n.restFail))
						.append($('<p>').text(ok ? i18n.restOkText : i18n.restFailText))
				);
			if (ok) $checks.append($row);
			else $checks.prepend($row);
		};

		if (window.fetch && yuniqAdmin.restUrl) {
			fetch(yuniqAdmin.restUrl, { credentials: 'omit', cache: 'no-store' })
				.then(function (res) { return res.ok ? res.json() : Promise.reject(); })
				.then(function (data) { restRow(!!data && typeof data === 'object' && 'enabled' in data); })
				.catch(function () { restRow(false); });
		}

		$('#yuniq-ai-clear-log').on('click', function () {
			if (!confirm(i18n.confirmClearLog || '')) return;
			var $btn = $(this).prop('disabled', true);
			post('yuniq_ai_clear_log')
				.done(function () { location.reload(); })
				.fail(function () { $btn.prop('disabled', false); });
		});

		$('#yuniq-ai-test-notify').on('click', function () {
			var $btn = $(this).prop('disabled', true);
			var $out = $('#yuniq-ai-notify-result').removeClass('is-ok is-error').addClass('is-pending').text(i18n.sending || '');

			var show = function (ok, res) {
				var message = res && res.data && res.data.message;
				$out.removeClass('is-pending').addClass(ok ? 'is-ok' : 'is-error')
					.css('white-space', 'pre-line').text(message || i18n.requestFailed || '');
			};

			post('yuniq_ai_test_notify')
				.done(function (res) { show(!!(res && res.success), res); })
				.fail(function (xhr) { show(false, xhr && xhr.responseJSON); })
				.always(function () { $btn.prop('disabled', false); });
		});

		$('#yuniq-ai-copy-report').on('click', function () {
			var $btn = $(this);
			var field = document.getElementById('yuniq-ai-report');
			var label = $btn.text();
			var done = function () {
				$btn.text('✓ ' + (i18n.copied || ''));
				setTimeout(function () { $btn.text(label); }, 1800);
			};

			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(field.value).then(done, function () { field.select(); });
			} else {
				field.select();
				try { if (document.execCommand('copy')) done(); } catch (e) {}
			}
		});

		$(document).on('click', '.yq-log-filters .yuniq-ai-ls-filter', function () {
			var level = $(this).data('level') || '';
			$('.yq-log-filters .yuniq-ai-ls-filter').removeClass('active');
			$(this).addClass('active');
			$('#yuniq-ai-log .yq-log-row').each(function () {
				$(this).toggle(!level || $(this).data('level') === level);
			});
		});
	}

	/* ---------- Live Support inbox ---------- */
	var $lsList = $('#yuniq-ai-ls-list');

	if ($lsList.length) {
		var lsState = { sessionId: null, filter: '', threadSig: '', listSig: '', leadsSig: '' };
		var anonymous = i18n.lsAnonymous || 'بازدیدکننده ناشناس';

		function escHtml(text) {
			return String(text === undefined || text === null ? '' : text)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#39;');
		}

		function lsStatusLabel(status) {
			return { pending: i18n.lsStatusPending, active: i18n.lsStatusActive, resolved: i18n.lsStatusResolved }[status] || status;
		}

		function lsRoleLabel(role) {
			return { user: i18n.lsRoleUser, assistant: i18n.lsRoleBot, agent: i18n.lsRoleAgent }[role] || '';
		}

		function lsRefreshList() {
			post('yuniq_ai_ls_list', { status: lsState.filter }).done(function (res) {
				if (!res || !res.success) return;

				var rows = res.data.conversations || [];
				var sig = JSON.stringify(rows) + '|' + lsState.sessionId;

				// Nothing changed since the last poll: leave the DOM (and the
				// admin's hover, focus and scroll position) alone.
				if (sig === lsState.listSig) return;
				lsState.listSig = sig;

				if (!rows.length) {
					$lsList.html('<p class="description" style="padding:14px;">' + escHtml(i18n.lsNoConversations || '') + '</p>');
					return;
				}

				var html = '';
				rows.forEach(function (c) {
					html += '<button type="button" class="yuniq-ai-ls-row' + (c.session_id === lsState.sessionId ? ' is-active' : '') + '" data-session-id="' + escHtml(c.session_id) + '">' +
						'<span class="yuniq-ai-ls-row-top">' +
						'<span class="yuniq-ai-ls-row-name">' + escHtml(c.visitor_name || anonymous) + '</span>' +
						'<span class="yuniq-ai-ls-badge yuniq-ai-ls-badge-' + escHtml(c.status) + '">' + escHtml(lsStatusLabel(c.status)) + '</span>' +
						'</span>' +
						'<span class="yuniq-ai-ls-row-meta">' + escHtml(c.time_label || '') +
						(parseInt(c.unread_for_admin, 10) && c.session_id !== lsState.sessionId ? '<span class="yuniq-ai-ls-unread-dot"></span>' : '') +
						'</span></button>';
				});
				$lsList.html(html);
			});
		}

		function lsRenderThread(conversation, messages) {
			var status = (conversation && conversation.status) || '';
			var sig = status + '|' + messages.length + '|' + (messages.length ? messages[messages.length - 1].id : 0);

			$('#yuniq-ai-ls-thread-empty').hide();
			$('#yuniq-ai-ls-thread').show();
			$('#yuniq-ai-ls-thread-name').text((conversation && conversation.visitor_name) || anonymous);
			$('#yuniq-ai-ls-thread-contact').text((conversation && conversation.visitor_contact) || '');

			// What the agent may do depends on where the conversation is.
			$('#yuniq-ai-ls-claim').toggle(status === 'pending');
			$('#yuniq-ai-ls-resolve').toggle(status === 'pending' || status === 'active');
			$('#yuniq-ai-ls-reply-form').toggle(status !== 'resolved');
			$('#yuniq-ai-ls-closed-note').toggle(status === 'resolved');

			if (sig === lsState.threadSig) return;

			var $box = $('#yuniq-ai-ls-messages');
			var box = $box[0];
			// Follow new messages only if the agent was already at the bottom.
			var stick = !lsState.threadSig || (box.scrollHeight - box.scrollTop - box.clientHeight < 60);
			lsState.threadSig = sig;

			var html = '';
			messages.forEach(function (m) {
				// Rendered as text: the visitor wrote part of this transcript.
				html += '<div class="yuniq-ai-ls-msg yuniq-ai-ls-msg-' + escHtml(m.role) + '" dir="auto">' +
					'<span class="yuniq-ai-ls-msg-role">' + escHtml(lsRoleLabel(m.role)) + '</span>' +
					escHtml(m.content).replace(/\n/g, '<br>') + '</div>';
			});
			$box.html(html);
			if (stick) $box.scrollTop(box.scrollHeight);
		}

		function lsLoadThread() {
			if (!lsState.sessionId) return;
			var requested = lsState.sessionId;

			post('yuniq_ai_ls_messages', { session_id: requested }).done(function (res) {
				// The admin may have opened another conversation meanwhile.
				if (!res || !res.success || requested !== lsState.sessionId) return;
				lsRenderThread(res.data.conversation, res.data.messages || []);
			});
		}

		function lsOpenConversation(sessionId) {
			lsState.sessionId = sessionId;
			lsState.threadSig = '';
			$('.yuniq-ai-ls-row').removeClass('is-active');
			$('.yuniq-ai-ls-row').filter(function () { return $(this).data('session-id') === sessionId; })
				.addClass('is-active').find('.yuniq-ai-ls-unread-dot').remove();
			$('#yuniq-ai-ls-messages').empty();
			$('#yuniq-ai-ls-error').empty();
			lsLoadThread();
		}

		function lsRefreshLeads() {
			post('yuniq_ai_ls_leads').done(function (res) {
				if (!res || !res.success) return;

				var leads = res.data.leads || [];
				var sig = JSON.stringify(leads);
				if (sig === lsState.leadsSig) return;
				lsState.leadsSig = sig;

				if (!leads.length) {
					$('#yuniq-ai-ls-leads-table').html('<p class="description">' + escHtml(i18n.lsNoLeads || 'هنوز سرنخی ثبت نشده است.') + '</p>');
					return;
				}

				var html = '<div class="yuniq-ai-table-scroll"><table class="widefat striped"><thead><tr><th>تاریخ</th><th>اطلاعات</th></tr></thead><tbody>';
				leads.forEach(function (l) {
					var fields = l.fields || {};
					var fieldsHtml = Object.keys(fields).map(function (k) {
						return '<strong>' + escHtml(k) + ':</strong> <span dir="auto">' + escHtml(fields[k]) + '</span>';
					}).join('<br>');
					html += '<tr><td>' + escHtml(l.created_at) + '</td><td>' + fieldsHtml + '</td></tr>';
				});
				html += '</tbody></table></div>';
				$('#yuniq-ai-ls-leads-table').html(html);
			});
		}

		function lsAction(action, data) {
			$('#yuniq-ai-ls-error').empty();
			return post(action, $.extend({ session_id: lsState.sessionId }, data || {}))
				.fail(function (xhr) {
					var message = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
					$('#yuniq-ai-ls-error').text(message || i18n.requestFailed || '');
				})
				.always(function () {
					lsState.threadSig = '';
					lsLoadThread();
					lsRefreshList();
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
			var $send = $(this).find('button[type="submit"]');
			var message = $.trim($input.val());
			if (!message || !lsState.sessionId || $send.prop('disabled')) return;

			$send.prop('disabled', true);
			lsAction('yuniq_ai_ls_reply', { message: message })
				.done(function (res) { if (res && res.success) $input.val(''); })
				.always(function () { $send.prop('disabled', false); $input.trigger('focus'); });
		});

		// Enter sends, Shift+Enter makes a new line — same as the widget.
		$('#yuniq-ai-ls-reply-input').on('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey && !(e.originalEvent && e.originalEvent.isComposing)) {
				e.preventDefault();
				$('#yuniq-ai-ls-reply-form').trigger('submit');
			}
		});

		$('#yuniq-ai-ls-claim').on('click', function () {
			if (lsState.sessionId) lsAction('yuniq_ai_ls_claim');
		});

		$('#yuniq-ai-ls-resolve').on('click', function () {
			if (!lsState.sessionId || !confirm(i18n.lsConfirmResolve || '')) return;
			lsAction('yuniq_ai_ls_resolve');
		});

		lsRefreshList();
		lsRefreshLeads();

		// No polling while the tab is in the background.
		setInterval(function () {
			if (document.hidden) return;
			lsRefreshList();
			lsLoadThread();
			if ($('#tab-ls-leads').hasClass('active')) lsRefreshLeads();
		}, 6000);

		$(document).on('click', '.yuniq-ai-tab-btn[data-tab="ls-leads"]', lsRefreshLeads);
	}

})(jQuery);
