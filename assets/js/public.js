/**
 * Yuniq.ai — public widget.
 *
 * @author Yegane Norouzi <https://github.com/Yeganenorouzi>
 */
(function () {
	'use strict';

	if (typeof yuniqAI === 'undefined') return;

	var cfg = yuniqAI.settings || {};
	var restUrl = yuniqAI.restUrl;
	var nonce = yuniqAI.nonce;
	var strings = cfg.i18n || {};

	var root = document.getElementById('yuniq-ai-root');
	var launcher = document.getElementById('yuniq-ai-launcher');
	var panel = document.getElementById('yuniq-ai-panel');
	if (!root || !launcher || !panel) return;

	var messagesEl = document.getElementById('yuniq-ai-messages');
	var introEl = document.getElementById('yuniq-ai-intro');
	var form = document.getElementById('yuniq-ai-chat-form');
	var input = document.getElementById('yuniq-ai-input');
	var sendBtn = document.getElementById('yuniq-ai-send');
	var closeBtn = document.getElementById('yuniq-ai-panel-close');
	var themeBtn = document.getElementById('yuniq-ai-theme-toggle');
	var jumpBtn = document.getElementById('yuniq-ai-jump');
	var videoEl = document.getElementById('yuniq-ai-video');
	var tickerEl = document.getElementById('yuniq-ai-prompt-ticker');
	var trackEl = document.getElementById('yuniq-ai-ticker-track');
	var humanBtn = document.getElementById('yuniq-ai-talk-human');

	var TXT_GENERIC_ERROR = strings.genericError || 'مشکلی پیش آمد. لطفاً دوباره تلاش کنید.';
	var TXT_NETWORK_ERROR = strings.networkError || 'خطای شبکه. اتصال اینترنت خود را بررسی کنید.';

	var sessionId = store('get', 'yuniq_ai_session_id') || '';
	var history = [];
	var isOpen = false;
	var isSending = false;
	var chatStarted = false;
	var lastFocused = null;

	var liveSupport = cfg.liveSupport || {};
	var conversationStatus = 'bot'; // bot | pending | active | resolved
	var lastMessageId = 0;
	var pollTimer = null;

	/* =====================================================
	   Small helpers
	   ===================================================== */
	function store(op, key, value) {
		try {
			if (op === 'get') return localStorage.getItem(key);
			if (op === 'set') localStorage.setItem(key, value);
		} catch (e) {}
		return null;
	}

	function isMobile() {
		return window.innerWidth <= 640;
	}

	function prefersReducedMotion() {
		return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	function escHtml(text) {
		return String(text || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	function escAttr(text) {
		return escHtml(text).replace(/"/g, '&quot;');
	}

	/* =====================================================
	   Theme
	   ===================================================== */
	var THEME_KEY = 'yuniq_ai_theme';

	function systemPrefersDark() {
		return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
	}

	function currentTheme() {
		return root.getAttribute('data-theme') || 'auto';
	}

	function resolvedIsDark() {
		var theme = currentTheme();
		if (theme === 'dark') return true;
		if (theme === 'light') return false;
		return systemPrefersDark();
	}

	function applyTheme(theme) {
		root.setAttribute('data-theme', theme);
		if (themeBtn) {
			themeBtn.setAttribute(
				'aria-label',
				resolvedIsDark() ? (strings.lightMode || 'حالت روشن') : (strings.darkMode || 'حالت تاریک')
			);
		}
	}

	// A visitor's own choice outlives the admin default.
	applyTheme(store('get', THEME_KEY) || cfg.theme || 'auto');

	if (themeBtn) {
		themeBtn.addEventListener('click', function () {
			var next = resolvedIsDark() ? 'light' : 'dark';
			applyTheme(next);
			store('set', THEME_KEY, next);
		});
	}

	/* =====================================================
	   Optional GSAP motion layer
	   ===================================================== */
	var motion = { state: 'idle', api: null };

	function loadScript(src) {
		return new Promise(function (resolve, reject) {
			var el = document.createElement('script');
			el.src = src;
			el.async = true;
			el.onload = resolve;
			el.onerror = reject;
			document.head.appendChild(el);
		});
	}

	/**
	 * Fetch GSAP and the motion layer from this plugin's own folder.
	 *
	 * Never blocks anything: the panel opens on CSS transitions whether or
	 * not this resolves, and it is skipped entirely when the visitor has
	 * asked for reduced motion.
	 */
	function loadMotion() {
		if (motion.state !== 'idle') return;

		var conf = cfg.motion || {};
		if (!conf.enabled || !conf.gsap || !conf.layer || prefersReducedMotion() || typeof Promise === 'undefined') {
			motion.state = 'off';
			return;
		}

		motion.state = 'loading';

		loadScript(conf.gsap)
			.then(function () { return loadScript(conf.layer); })
			.then(function () {
				motion.api = window.yuniqAIMotion || null;
				motion.state = motion.api ? 'ready' : 'off';
				if (motion.api) root.classList.add('yuniq-ai-gsap');
			})
			.catch(function () {
				motion.state = 'off';
			});
	}

	// Start fetching as soon as the visitor shows intent, so the library is
	// usually in place by the time the panel actually opens.
	launcher.addEventListener('pointerenter', loadMotion, { once: true });
	launcher.addEventListener('focus', loadMotion, { once: true });

	/* =====================================================
	   Video
	   ===================================================== */
	function playVideo() {
		if (!videoEl) return;
		try {
			var wantsMuted = !!cfg.videoMute;
			videoEl.setAttribute('playsinline', '');
			videoEl.playsInline = true;
			videoEl.muted = wantsMuted;
			videoEl.defaultMuted = wantsMuted;
			videoEl.volume = wantsMuted ? 0 : 1;

			if (!cfg.videoAutoplay) return;

			var p = videoEl.play();
			if (p && typeof p.catch === 'function') {
				p.catch(function () {
					// Browsers refuse unmuted autoplay; retry silently rather
					// than leaving the visitor with a frozen frame.
					if (wantsMuted) return;
					videoEl.muted = true;
					videoEl.play().catch(function () {});
				});
			}
		} catch (e) {}
	}

	function pauseVideo() {
		if (!videoEl) return;
		try { videoEl.pause(); } catch (e) {}
	}

	/* =====================================================
	   Mobile keyboard
	   ===================================================== */
	function onViewportChange() {
		if (!isOpen || !isMobile() || !window.visualViewport) return;
		panel.style.height = window.visualViewport.height + 'px';
		panel.style.maxHeight = window.visualViewport.height + 'px';
		panel.style.top = window.visualViewport.offsetTop + 'px';
		panel.style.bottom = 'auto';
	}

	function resetViewport() {
		panel.style.height = '';
		panel.style.maxHeight = '';
		panel.style.top = '';
		panel.style.bottom = '';
	}

	if (window.visualViewport) {
		window.visualViewport.addEventListener('resize', onViewportChange);
		window.visualViewport.addEventListener('scroll', onViewportChange);
	}

	/* =====================================================
	   Focus management
	   ===================================================== */
	var FOCUSABLE = 'a[href],button:not([disabled]),textarea:not([disabled]),input:not([disabled]),select:not([disabled]),[tabindex]:not([tabindex="-1"])';

	function focusables() {
		return Array.prototype.slice.call(panel.querySelectorAll(FOCUSABLE)).filter(function (el) {
			return el.offsetWidth > 0 || el.offsetHeight > 0;
		});
	}

	/** Keeps Tab inside the dialog while it is open. */
	function onKeydown(e) {
		if (e.key === 'Escape' && isOpen) {
			e.preventDefault();
			closePanel();
			return;
		}

		if (e.key !== 'Tab' || !isOpen) return;

		var list = focusables();
		if (!list.length) return;

		var first = list[0];
		var last = list[list.length - 1];

		if (e.shiftKey && document.activeElement === first) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && document.activeElement === last) {
			e.preventDefault();
			first.focus();
		}
	}

	document.addEventListener('keydown', onKeydown);

	/* =====================================================
	   Panel
	   ===================================================== */
	function afterOpen() {
		if (input && !isMobile()) input.focus({ preventScroll: true });
		if (motion.api) motion.api.enterChips(trackEl ? trackEl.children : null);
	}

	function openPanel() {
		if (isOpen) return;

		isOpen = true;
		lastFocused = document.activeElement;

		root.classList.add('is-open');
		panel.setAttribute('aria-hidden', 'false');
		launcher.setAttribute('aria-expanded', 'true');
		document.body.classList.add('yuniq-ai-open');

		if (motion.api) {
			motion.api.open({ root: root, panel: panel, isMobile: isMobile(), onComplete: afterOpen });
		} else {
			afterOpen();
		}

		playVideo();
		onViewportChange();
	}

	function afterClose() {
		root.classList.remove('is-open');
		panel.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('yuniq-ai-open');

		// Hand the transform back to CSS for the next open.
		if (motion.api) motion.api.reset(panel);

		pauseVideo();
		resetViewport();

		if (lastFocused && typeof lastFocused.focus === 'function') {
			lastFocused.focus();
		} else {
			launcher.focus();
		}
	}

	function closePanel() {
		if (!isOpen) return;

		isOpen = false;
		launcher.setAttribute('aria-expanded', 'false');

		if (motion.api) {
			motion.api.close({ root: root, panel: panel, isMobile: isMobile(), onComplete: afterClose });
		} else {
			afterClose();
		}
	}

	launcher.addEventListener('click', function (e) {
		e.preventDefault();
		e.stopPropagation();
		loadMotion();
		if (isOpen) closePanel();
		else openPanel();
	});

	if (closeBtn) {
		closeBtn.addEventListener('click', function (e) {
			e.stopPropagation();
			closePanel();
		});
	}

	// The [yuniq_ai_page] shortcode hides the launcher via CSS and expects
	// the panel to already be "open" (and announced as such to assistive
	// tech) as soon as the page renders.
	if (root.classList.contains('yuniq-ai-page-mode')) {
		openPanel();
	}

	/* =====================================================
	   Suggestion chips
	   ===================================================== */
	function initChips() {
		if (!trackEl) return;

		var actions = Array.isArray(cfg.quickActions) ? cfg.quickActions : [];
		var built = 0;

		trackEl.innerHTML = '';

		actions.forEach(function (a) {
			if (!a) return;
			var label = String(a.label || '').trim();
			if (!label) return;

			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'yuniq-ai-ticker-chip';
			btn.textContent = label;
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				sendMessage(String(a.prompt || label).trim());
			});
			trackEl.appendChild(btn);
			built++;
		});

		if (!built && tickerEl) tickerEl.classList.add('is-hidden');
	}
	initChips();

	function startChatUI() {
		if (chatStarted) return;
		chatStarted = true;

		if (introEl) introEl.hidden = true;
		if (tickerEl) tickerEl.classList.add('is-hidden');
		if (messagesEl) messagesEl.classList.add('is-active');
	}

	/* =====================================================
	   Scroll behaviour
	   ===================================================== */
	function atBottom() {
		if (!messagesEl) return true;
		return messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight < 48;
	}

	function scrollToBottom() {
		if (!messagesEl) return;
		messagesEl.scrollTop = messagesEl.scrollHeight;
		updateJump();
	}

	function updateJump() {
		if (!jumpBtn) return;
		jumpBtn.classList.toggle('is-visible', !atBottom());
	}

	if (messagesEl) messagesEl.addEventListener('scroll', updateJump, { passive: true });
	if (jumpBtn) jumpBtn.addEventListener('click', scrollToBottom);

	/* =====================================================
	   Rendering
	   ===================================================== */
	function formatText(text) {
		var html = String(text || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
		html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
		html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
		html = html.replace(/\n/g, '<br>');
		html = html.replace(/(^|[^"'>])(https?:\/\/[^\s<]+)/g, '$1<a href="$2" target="_blank" rel="noopener noreferrer">$2</a>');
		return html;
	}

	function appendMessage(role, text) {
		startChatUI();

		var stick = atBottom();
		var el = document.createElement('div');
		el.className = 'yuniq-ai-msg yuniq-ai-msg-' + (role === 'user' ? 'user' : 'assistant');
		el.innerHTML = formatText(text);
		messagesEl.appendChild(el);

		if (motion.api) motion.api.enterMessage(el);
		if (stick || role === 'user') scrollToBottom();
		else updateJump();

		return el;
	}

	function showTyping() {
		startChatUI();
		hideTyping();

		var el = document.createElement('div');
		el.className = 'yuniq-ai-typing';
		el.id = 'yuniq-ai-typing';
		el.setAttribute('aria-label', strings.answering || 'در حال پاسخ‌دادن');
		el.innerHTML = '<span></span><span></span><span></span>';
		messagesEl.appendChild(el);
		scrollToBottom();
	}

	function hideTyping() {
		var el = document.getElementById('yuniq-ai-typing');
		if (el) el.remove();
	}

	/**
	 * Paints accumulated text at most once per animation frame.
	 *
	 * Tokens arrive far faster than the screen refreshes, so rendering on
	 * every delta would re-parse the whole message dozens of times a second.
	 */
	function createRenderer(el) {
		var pending = false;
		var buffer = '';

		function paint() {
			pending = false;
			var stick = atBottom();
			el.innerHTML = formatText(buffer) + '<span class="yuniq-ai-caret"></span>';
			if (stick) scrollToBottom();
			else updateJump();
		}

		return {
			push: function (delta) {
				buffer += delta;
				if (!pending) {
					pending = true;
					window.requestAnimationFrame(paint);
				}
			},
			finish: function () {
				pending = false;
				var stick = atBottom();
				el.innerHTML = formatText(buffer);
				if (stick) scrollToBottom();
				else updateJump();
				return buffer;
			}
		};
	}

	/* =====================================================
	   Human handoff
	   ===================================================== */
	function appendNotice(text) {
		startChatUI();
		var el = document.createElement('div');
		el.className = 'yuniq-ai-msg yuniq-ai-msg-system';
		el.textContent = text;
		messagesEl.appendChild(el);
		if (motion.api) motion.api.enterMessage(el);
		scrollToBottom();
		return el;
	}

	function escalate() {
		if (!liveSupport.enabled || conversationStatus === 'pending' || conversationStatus === 'active') return;

		appendNotice(strings.escalating || 'در حال اتصال به کارشناس...');

		fetch(restUrl + 'escalate', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
			body: JSON.stringify({ session_id: sessionId })
		})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (!data || !data.success) {
					appendNotice((data && data.error) ? data.error : TXT_GENERIC_ERROR);
					return;
				}
				conversationStatus = data.status || 'pending';
				startPolling();
			})
			.catch(function () { appendNotice(TXT_NETWORK_ERROR); });
	}

	function startPolling() {
		if (pollTimer) return;
		pollTimer = window.setInterval(pollMessages, 4000);
		pollMessages();
	}

	function stopPolling() {
		if (!pollTimer) return;
		window.clearInterval(pollTimer);
		pollTimer = null;
	}

	function pollMessages() {
		fetch(restUrl + 'conversation/' + encodeURIComponent(sessionId) + '/messages?after_id=' + lastMessageId, {
			headers: { 'X-WP-Nonce': nonce }
		})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (!data || !data.success) return;

				var wasPending = conversationStatus === 'pending';

				(data.messages || []).forEach(function (m) {
					lastMessageId = Math.max(lastMessageId, parseInt(m.id, 10) || 0);
					if (m.role === 'agent') {
						var el = appendMessage('assistant', m.content);
						el.classList.add('yuniq-ai-msg-agent');
					}
				});

				if (data.status === 'active' && wasPending) {
					appendNotice(strings.connectedToAgent || 'به کارشناس پشتیبانی متصل شدید');
				}

				conversationStatus = data.status || conversationStatus;

				if (conversationStatus === 'resolved') {
					stopPolling();
					appendNotice(strings.resolvedByAgent || 'گفتگو با کارشناس پایان یافت.');
					conversationStatus = 'bot';
				}
			})
			.catch(function () {});
	}

	function sendEscalatedMessage(message) {
		fetch(restUrl + 'conversation/' + encodeURIComponent(sessionId) + '/messages', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
			body: JSON.stringify({ message: message })
		})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (!data || !data.success) {
					appendNotice((data && data.error) ? data.error : TXT_GENERIC_ERROR);
					conversationStatus = 'bot';
					stopPolling();
				}
			})
			.catch(function () {})
			.then(finishTurn);
	}

	function renderNeedHumanCta() {
		if (!liveSupport.enabled || liveSupport.mode === 'manual') return;
		if (conversationStatus === 'pending' || conversationStatus === 'active') return;

		startChatUI();
		var wrap = document.createElement('div');
		wrap.className = 'yuniq-ai-msg yuniq-ai-msg-system yuniq-ai-need-human';
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.textContent = strings.needHumanCta || 'این پاسخ کافی نبود؟ صحبت با کارشناس';
		btn.addEventListener('click', function () {
			wrap.remove();
			escalate();
		});
		wrap.appendChild(btn);
		messagesEl.appendChild(wrap);
		if (motion.api) motion.api.enterMessage(wrap);
		scrollToBottom();
	}

	function renderProductCard(product) {
		if (!product || !cfg.productCards) return;

		startChatUI();
		var card = document.createElement('div');
		card.className = 'yuniq-ai-msg yuniq-ai-msg-assistant yuniq-ai-product-card';

		var html = '';
		if (product.image) {
			html += '<img class="yuniq-ai-product-image" src="' + escAttr(product.image) + '" alt="" loading="lazy">';
		}
		html += '<div class="yuniq-ai-product-body">';
		html += '<div class="yuniq-ai-product-title">' + escHtml(product.title || '') + '</div>';

		if (product.sale_price && product.regular_price && product.sale_price !== product.regular_price) {
			html += '<div class="yuniq-ai-product-price"><s>' + escHtml(product.regular_price) + '</s> ' + escHtml(product.sale_price) + '</div>';
		} else if (product.price) {
			html += '<div class="yuniq-ai-product-price">' + escHtml(product.price) + '</div>';
		}

		if (product.stock_status) {
			var inStock = product.stock_status === 'instock';
			html += '<span class="yuniq-ai-product-stock ' + (inStock ? 'is-in-stock' : 'is-out-of-stock') + '">' +
				escHtml(inStock ? (strings.inStock || 'موجود') : (strings.outOfStock || 'ناموجود')) + '</span>';
		}

		html += '<div class="yuniq-ai-product-actions">';
		if (product.url) {
			html += '<a class="yuniq-ai-product-btn" href="' + escAttr(product.url) + '" target="_blank" rel="noopener noreferrer">' + escHtml(strings.viewProduct || 'مشاهده محصول') + '</a>';
			html += '<a class="yuniq-ai-product-btn yuniq-ai-product-btn-primary" href="' + escAttr(product.url) + '?add-to-cart=' + encodeURIComponent(product.id) + '">' + escHtml(strings.addToCart || 'افزودن به سبد خرید') + '</a>';
		}
		html += '</div></div>';

		card.innerHTML = html;
		messagesEl.appendChild(card);
		if (motion.api) motion.api.enterMessage(card);
		scrollToBottom();
	}

	function renderForm(formKey) {
		var forms = Array.isArray(cfg.leadForms) ? cfg.leadForms : [];
		var formDef = null;
		for (var i = 0; i < forms.length; i++) {
			if (forms[i] && forms[i].key === formKey) { formDef = forms[i]; break; }
		}
		if (!formDef) return;

		startChatUI();
		var wrap = document.createElement('div');
		wrap.className = 'yuniq-ai-msg yuniq-ai-msg-assistant yuniq-ai-inline-form';

		var title = document.createElement('div');
		title.className = 'yuniq-ai-inline-form-title';
		title.textContent = formDef.title || '';
		wrap.appendChild(title);

		var formEl = document.createElement('form');
		var inputs = {};

		(formDef.fields || []).forEach(function (field) {
			var row = document.createElement('label');
			row.className = 'yuniq-ai-inline-form-row';
			var span = document.createElement('span');
			span.textContent = field.label + (field.required ? ' *' : '');
			row.appendChild(span);

			var inputEl = field.type === 'textarea' ? document.createElement('textarea') : document.createElement('input');
			if (field.type !== 'textarea') inputEl.type = field.type === 'email' ? 'email' : (field.type === 'tel' ? 'tel' : 'text');
			if (field.required) inputEl.required = true;
			row.appendChild(inputEl);
			formEl.appendChild(row);
			inputs[field.name] = inputEl;
		});

		var submitBtn = document.createElement('button');
		submitBtn.type = 'submit';
		submitBtn.className = 'yuniq-ai-product-btn yuniq-ai-product-btn-primary';
		submitBtn.textContent = formDef.trigger_label || formDef.title || '';
		formEl.appendChild(submitBtn);

		formEl.addEventListener('submit', function (e) {
			e.preventDefault();
			var fields = {};
			Object.keys(inputs).forEach(function (name) { fields[name] = inputs[name].value; });

			submitBtn.disabled = true;

			fetch(restUrl + 'lead', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
				body: JSON.stringify({ session_id: sessionId, form_key: formKey, fields: fields })
			})
				.then(function (res) { return res.json(); })
				.then(function (data) {
					if (data && data.success) {
						formEl.innerHTML = '';
						var done = document.createElement('div');
						done.textContent = strings.formSubmitted || 'با تشکر! به‌زودی با شما تماس گرفته می‌شود.';
						formEl.appendChild(done);
					} else {
						submitBtn.disabled = false;
						appendNotice((data && data.error) ? data.error : TXT_GENERIC_ERROR);
					}
				})
				.catch(function () { submitBtn.disabled = false; appendNotice(TXT_NETWORK_ERROR); });
		});

		wrap.appendChild(formEl);
		messagesEl.appendChild(wrap);
		if (motion.api) motion.api.enterMessage(wrap);
		scrollToBottom();
	}

	if (humanBtn) {
		humanBtn.addEventListener('click', function (e) {
			e.preventDefault();
			escalate();
		});
	}

	/**
	 * A reload loses the in-memory transcript (see `history` above) but not
	 * the server-side escalation, so a visitor who reloads mid-handoff would
	 * otherwise silently fall back to the AI. Replay whatever is already on
	 * the conversation and resume polling instead.
	 */
	function checkExistingEscalation() {
		if (!sessionId || !liveSupport.enabled) return;

		fetch(restUrl + 'conversation/' + encodeURIComponent(sessionId) + '/messages?after_id=0', {
			headers: { 'X-WP-Nonce': nonce }
		})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (!data || !data.success || (data.status !== 'pending' && data.status !== 'active')) return;

				conversationStatus = data.status;

				(data.messages || []).forEach(function (m) {
					lastMessageId = Math.max(lastMessageId, parseInt(m.id, 10) || 0);
					var el = appendMessage(m.role === 'user' ? 'user' : 'assistant', m.content);
					if (m.role === 'agent') el.classList.add('yuniq-ai-msg-agent');
				});

				appendNotice(conversationStatus === 'active'
					? (strings.connectedToAgent || 'به کارشناس پشتیبانی متصل شدید')
					: (strings.escalating || 'در حال اتصال به کارشناس...'));

				startPolling();
			})
			.catch(function () {});
	}
	checkExistingEscalation();

	/* =====================================================
	   Chat
	   ===================================================== */
	function finishTurn() {
		isSending = false;
		if (sendBtn) {
			sendBtn.disabled = false;
			if (motion.api) motion.api.pulse(sendBtn);
		}
	}

	function showError(message) {
		hideTyping();
		appendMessage('assistant', message).classList.add('yuniq-ai-msg-error');
	}

	function rememberSession(id) {
		if (!id) return;
		sessionId = id;
		store('set', 'yuniq_ai_session_id', sessionId);
	}

	function requestInit(message) {
		return {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': nonce
			},
			body: JSON.stringify({
				message: message,
				session_id: sessionId,
				history: history.slice(0, -1)
			})
		};
	}

	function streamMessage(message) {
		fetch(restUrl + 'chat/stream', requestInit(message))
			.then(function (res) {
				var type = res.headers.get('Content-Type') || '';

				// A refusal (disabled, rate limited, invalid) comes back as JSON.
				if (type.indexOf('text/event-stream') === -1) {
					return res.json().then(function (data) {
						showError((data && data.error) ? data.error : TXT_GENERIC_ERROR);
						rememberSession(data && data.session_id);
						finishTurn();
					});
				}

				if (!res.body || !res.body.getReader) return blockingMessage(message);

				return consumeStream(res.body.getReader());
			})
			.catch(function () {
				// Network trouble or a proxy that refuses SSE: try the plain route.
				blockingMessage(message);
			});
	}

	function consumeStream(reader) {
		var decoder = new TextDecoder('utf-8');
		var carry = '';
		var renderer = null;
		var el = null;
		var errored = false;
		var settled = false;

		function ensureElement() {
			if (renderer) return;
			hideTyping();
			el = appendMessage('assistant', '');
			renderer = createRenderer(el);
		}

		/** Paint whatever arrived and record the turn. Safe to call twice. */
		function settle() {
			if (settled || !renderer) return;
			settled = true;
			var text = renderer.finish();
			if (text && !errored) history.push({ role: 'assistant', content: text });
		}

		function handleEvent(payload) {
			if (payload.type === 'start') {
				rememberSession(payload.session_id);
			} else if (payload.type === 'delta') {
				ensureElement();
				renderer.push(payload.delta || '');
			} else if (payload.type === 'error') {
				errored = true;
				if (renderer) {
					settle();
					el.classList.add('yuniq-ai-msg-error');
				} else {
					showError(payload.error || TXT_GENERIC_ERROR);
				}
			} else if (payload.type === 'done') {
				settle();
			} else if (payload.type === 'product_card') {
				renderProductCard(payload.product);
			} else if (payload.type === 'form') {
				renderForm(payload.form_key);
			} else if (payload.type === 'needs_human') {
				settle();
				renderNeedHumanCta();
			}
		}

		function pump() {
			return reader.read().then(function (result) {
				if (result.done) {
					hideTyping();
					// The connection can close without a done frame if the
					// server or a proxy cuts it short — keep what arrived.
					settle();
					finishTurn();
					return;
				}

				carry += decoder.decode(result.value, { stream: true });

				var frames = carry.split('\n\n');
				carry = frames.pop();

				frames.forEach(function (frame) {
					var line = frame.trim();
					if (line.indexOf('data:') !== 0) return;
					try {
						handleEvent(JSON.parse(line.slice(5).trim()));
					} catch (e) {}
				});

				return pump();
			});
		}

		return pump().catch(function () {
			hideTyping();
			if (renderer) settle();
			else showError(TXT_NETWORK_ERROR);
			finishTurn();
		});
	}

	function blockingMessage(message) {
		return fetch(restUrl + 'chat', requestInit(message))
			.then(function (res) { return res.json(); })
			.then(function (data) {
				hideTyping();
				rememberSession(data && data.session_id);

				if (data && data.success && data.content) {
					appendMessage('assistant', data.content);
					history.push({ role: 'assistant', content: data.content });
					if (data.product) renderProductCard(data.product);
					if (data.form_key) renderForm(data.form_key);
					if (data.needs_human) renderNeedHumanCta();
				} else {
					showError((data && data.error) ? data.error : TXT_GENERIC_ERROR);
					if (data && data.needs_human) renderNeedHumanCta();
				}
			})
			.catch(function () { showError(TXT_NETWORK_ERROR); })
			.then(finishTurn);
	}

	function sendMessage(text) {
		if (!text || isSending) return;

		var message = String(text).trim();
		if (!message) return;

		isSending = true;
		if (sendBtn) sendBtn.disabled = true;
		if (input) {
			input.value = '';
			autoGrow();
		}

		appendMessage('user', message);

		if (conversationStatus === 'pending' || conversationStatus === 'active') {
			sendEscalatedMessage(message);
			return;
		}

		history.push({ role: 'user', content: message });
		showTyping();

		var canStream = cfg.streaming !== false &&
			typeof window.fetch === 'function' &&
			typeof window.TextDecoder === 'function' &&
			typeof window.ReadableStream === 'function';

		if (canStream) streamMessage(message);
		else blockingMessage(message);
	}

	/* =====================================================
	   Composer
	   ===================================================== */
	function autoGrow() {
		if (!input) return;
		input.style.height = 'auto';
		input.style.height = Math.min(input.scrollHeight, 108) + 'px';
	}

	if (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			sendMessage(input ? input.value : '');
		});
	}

	if (input) {
		input.addEventListener('input', autoGrow);
		input.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				sendMessage(input.value);
			}
		});
		input.addEventListener('focus', function () {
			if (isMobile()) onViewportChange();
		});
	}
})();
