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
	var optionsEl = document.getElementById('yuniq-ai-options');
	var resetBtn = document.getElementById('yuniq-ai-reset');
	var humanBtn = document.getElementById('yuniq-ai-talk-human');

	var TXT_GENERIC_ERROR = strings.genericError || 'مشکلی پیش آمد. لطفاً دوباره تلاش کنید.';
	var TXT_NETWORK_ERROR = strings.networkError || 'خطای شبکه. اتصال اینترنت خود را بررسی کنید.';

	var sessionId = store('get', 'yuniq_ai_session_id') || '';
	var history = [];
	var isOpen = false;
	var isSending = false;
	var chatStarted = false;
	var restoring = false;
	var lastFocused = null;

	var pageMode = root.classList.contains('yuniq-ai-page-mode');
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
			if (op === 'del') localStorage.removeItem(key);
		} catch (e) {}
		return null;
	}

	/** A v4-shaped id, so a session exists before the first chat turn. */
	function newSessionId() {
		if (window.crypto && typeof window.crypto.randomUUID === 'function') {
			return window.crypto.randomUUID();
		}
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
			var r = Math.random() * 16 | 0;
			return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
		});
	}

	function ensureSession() {
		if (!sessionId) rememberSession(newSessionId());
		return sessionId;
	}

	/**
	 * fetch() against the plugin's REST routes.
	 *
	 * The routes are public, so the nonce is only sent when the server
	 * handed one out (logged-in users). A cached page can still carry a
	 * nonce that has since expired; WordPress answers that with a 403
	 * before the route ever runs, so that one case is retried without it.
	 */
	function api(path, options) {
		var opts = options || {};

		function run(withNonce) {
			var headers = {};
			if (opts.body) headers['Content-Type'] = 'application/json';
			if (withNonce && nonce) headers['X-WP-Nonce'] = nonce;

			return fetch(restUrl + path, {
				method: opts.method || 'GET',
				headers: headers,
				body: opts.body ? JSON.stringify(opts.body) : undefined,
				credentials: 'same-origin'
			});
		}

		return run(true).then(function (res) {
			if (res.status === 403 && nonce) {
				return res.clone().json().then(function (data) {
					if (data && data.code === 'rest_cookie_invalid_nonce') {
						nonce = '';
						return run(false);
					}
					return res;
				}, function () { return res; });
			}
			return res;
		});
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
		return escHtml(text).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
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
		// On the shortcode page the panel is ordinary content: Escape must
		// not close it (nothing could reopen it) and Tab must not be trapped.
		if (pageMode) return;

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
		// Page mode opens on load: grabbing focus there would yank the
		// keyboard and the scroll position away from the page itself.
		if (input && !isMobile() && !pageMode) input.focus({ preventScroll: true });
		if (motion.api && !chatStarted) motion.api.enterChips(optionsEl ? optionsEl.children : null);
	}

	function openPanel() {
		if (isOpen) return;

		isOpen = true;
		lastFocused = document.activeElement;

		root.classList.add('is-open');
		panel.setAttribute('aria-hidden', 'false');
		launcher.setAttribute('aria-expanded', 'true');
		// The shortcode page is inline content, not an overlay to lock behind.
		if (!pageMode) document.body.classList.add('yuniq-ai-open');

		if (motion.api) {
			motion.api.open({ root: root, panel: panel, isMobile: isMobile(), onComplete: afterOpen });
		} else {
			afterOpen();
		}

		onViewportChange();
	}

	function afterClose() {
		root.classList.remove('is-open');
		panel.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('yuniq-ai-open');

		// Hand the transform back to CSS for the next open.
		if (motion.api) motion.api.reset(panel);

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
	   Starter options
	   ===================================================== */
	function sameOrigin(url) {
		try {
			return new URL(url, window.location.href).origin === window.location.origin;
		} catch (e) {
			return false;
		}
	}

	/**
	 * The admin's quick actions, as cards the visitor picks from. A card
	 * with a link goes there; any other card asks its question.
	 */
	function initOptions() {
		if (!optionsEl) return;

		var actions = Array.isArray(cfg.quickActions) ? cfg.quickActions : [];

		optionsEl.innerHTML = '';

		actions.forEach(function (a) {
			if (!a) return;
			var label = String(a.label || '').trim();
			if (!label) return;

			var action = String(a.action || '');
			if (action === 'human' && !liveSupport.enabled) return;
			var link = action ? '' : String(a.link || '').trim();
			// Only ordinary web addresses: never javascript:, data: and the like.
			if (link && !/^(https?:\/\/|\/(?!\/)|#|\?)/i.test(link)) link = '';
			var desc = String(a.desc || '').trim();
			var card = document.createElement(link ? 'a' : 'button');

			card.className = 'yuniq-ai-option';
			if (link) {
				card.href = link;
				if (!sameOrigin(link)) {
					card.target = '_blank';
					card.rel = 'noopener noreferrer';
				}
			} else {
				card.type = 'button';
				card.addEventListener('click', function () {
					if (action === 'human') escalate();
					else if (action === 'form') renderForm(String(a.form_key || ''));
					else sendMessage(String(a.prompt || label).trim());
				});
			}

			var title = document.createElement('span');
			title.className = 'yuniq-ai-option-title';
			title.textContent = label;
			card.appendChild(title);

			if (desc) {
				var sub = document.createElement('span');
				sub.className = 'yuniq-ai-option-desc';
				sub.textContent = desc;
				card.appendChild(sub);
			}

			optionsEl.appendChild(card);
		});

		if (!optionsEl.children.length) optionsEl.hidden = true;
	}
	initOptions();

	function startChatUI() {
		if (chatStarted) return;
		chatStarted = true;

		if (introEl) introEl.hidden = true;
		if (messagesEl) messagesEl.classList.add('is-active');
		if (resetBtn) resetBtn.hidden = false;

		// The greeting opens every new conversation. It is display-only:
		// it is never replayed to the model as history.
		if (cfg.welcomeMessage && !restoring) {
			var hello = document.createElement('div');
			hello.className = 'yuniq-ai-msg yuniq-ai-msg-assistant';
			hello.innerHTML = formatText(cfg.welcomeMessage);
			messagesEl.appendChild(hello);
			remember('assistant', cfg.welcomeMessage);
		}
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
		// Quotes are escaped too: the URL of a markdown link lands inside an
		// href attribute, and an unescaped quote there could close it.
		var html = String(text || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
		var links = [];

		// Links are parked behind placeholders so the bare-URL pass below
		// cannot wrap an address that is already inside an anchor.
		function park(url, label) {
			links.push('<a href="' + url + '" target="_blank" rel="noopener noreferrer">' + label + '</a>');
			return '\u0001' + (links.length - 1) + '\u0001';
		}

		html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, function (m, label, url) {
			return park(url, label);
		});
		html = html.replace(/https?:\/\/[^\s<\u0001]+/g, function (url) {
			// Sentence punctuation right after an address is not part of it.
			var tail = (url.match(url.indexOf('(') === -1 ? /(?:[.,;:!?)»،؛؟]|&quot;|&#39;)+$/ : /(?:[.,;:!?»،؛؟]|&quot;|&#39;)+$/) || [''])[0];
			var clean = tail ? url.slice(0, -tail.length) : url;
			return park(clean, clean) + tail;
		});
		html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
		html = html.replace(/^\s*[-*•]\s+/gm, '• ');
		html = html.replace(/^#{1,6}\s+(.+)$/gm, '<strong>$1</strong>');
		html = html.replace(/\n/g, '<br>');
		html = html.replace(/\u0001(\d+)\u0001/g, function (m, i) { return links[+i]; });
		return html;
	}

	function appendMessage(role, text) {
		startChatUI();

		var stick = atBottom();
		var el = document.createElement('div');
		el.className = 'yuniq-ai-msg yuniq-ai-msg-' + (role === 'user' ? 'user' : 'assistant');
		el.setAttribute('dir', 'auto');
		el.innerHTML = formatText(text);
		messagesEl.appendChild(el);
		clearOptions();

		if (motion.api && !restoring) motion.api.enterMessage(el);
		if (stick || role === 'user') scrollToBottom();
		else updateJump();

		return el;
	}

	/* =====================================================
	   Transcript — survives moving between pages
	   ===================================================== */
	var TRANSCRIPT_KEY = 'yuniq_ai_transcript';
	var TRANSCRIPT_MAX = 40;
	var transcript = [];

	function session(op, value) {
		try {
			if (op === 'get') return sessionStorage.getItem(TRANSCRIPT_KEY);
			if (op === 'set') sessionStorage.setItem(TRANSCRIPT_KEY, value);
			if (op === 'del') sessionStorage.removeItem(TRANSCRIPT_KEY);
		} catch (e) {}
		return null;
	}

	/** Record one finished bubble: role is user, assistant, agent or system. */
	function remember(role, text) {
		if (restoring || !text) return;
		transcript.push({ r: role, t: String(text) });
		if (transcript.length > TRANSCRIPT_MAX) transcript = transcript.slice(-TRANSCRIPT_MAX);
		session('set', JSON.stringify({ s: sessionId, m: transcript, h: history.slice(-12) }));
	}

	/**
	 * Without this the conversation vanished on every page change, which
	 * on a shop means the moment the visitor opened a suggested product.
	 */
	function restoreTranscript() {
		var saved = null;
		try { saved = JSON.parse(session('get') || 'null'); } catch (e) {}
		if (!saved || !Array.isArray(saved.m) || !saved.m.length || (saved.s && sessionId && saved.s !== sessionId)) return;

		restoring = true;
		startChatUI();

		saved.m.forEach(function (m) {
			if (!m || typeof m.t !== 'string') return;
			if (m.r === 'system') {
				appendNotice(m.t);
			} else {
				var el = appendMessage(m.r === 'user' ? 'user' : 'assistant', m.t);
				if (m.r === 'agent') el.classList.add('yuniq-ai-msg-agent');
			}
		});

		transcript = saved.m;
		history = Array.isArray(saved.h) ? saved.h : [];
		restoring = false;
		scrollToBottom();
	}

	function resetConversation() {
		if (isSending || conversationStatus === 'pending' || conversationStatus === 'active') return;

		transcript = [];
		history = [];
		chatStarted = false;
		session('del');
		messagesEl.innerHTML = '';
		messagesEl.classList.remove('is-active');
		if (introEl) introEl.hidden = false;
		if (resetBtn) resetBtn.hidden = true;
		rememberSession(newSessionId());
		updateJump();
		if (input) input.focus({ preventScroll: true });
	}

	if (resetBtn) resetBtn.addEventListener('click', resetConversation);

	/* =====================================================
	   Follow-up options under a reply
	   ===================================================== */
	function clearOptions() {
		var old = messagesEl.querySelector('.yuniq-ai-replies');
		if (old) old.remove();
	}

	function renderOptions(list) {
		if (!Array.isArray(list) || !list.length) return;
		if (conversationStatus === 'pending' || conversationStatus === 'active') return;

		clearOptions();

		var wrap = document.createElement('div');
		wrap.className = 'yuniq-ai-replies';

		list.slice(0, 4).forEach(function (label) {
			label = String(label || '').trim();
			if (!label) return;
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'yuniq-ai-reply';
			btn.textContent = label;
			btn.addEventListener('click', function () { sendMessage(label); });
			wrap.appendChild(btn);
		});

		if (!wrap.children.length) return;

		messagesEl.appendChild(wrap);
		if (motion.api) motion.api.enterChips(wrap.children);
		scrollToBottom();
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
		var finished = false;
		var buffer = '';

		function paint() {
			pending = false;
			// A frame queued before finish() must not put the caret back.
			if (finished) return;
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
				finished = true;
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
		clearOptions();
		if (motion.api && !restoring) motion.api.enterMessage(el);
		scrollToBottom();
		remember('system', text);
		return el;
	}

	var ESCALATED_KEY = 'yuniq_ai_escalated';
	var escalating = false;

	function setStatus(status) {
		conversationStatus = status;
		var withAgent = status === 'pending' || status === 'active';
		root.classList.toggle('is-with-agent', withAgent);
		if (humanBtn) humanBtn.hidden = withAgent;
		if (withAgent) store('set', ESCALATED_KEY, '1');
		else store('del', ESCALATED_KEY);
	}

	function escalate() {
		if (!liveSupport.enabled || escalating || conversationStatus === 'pending' || conversationStatus === 'active') return;

		escalating = true;
		if (humanBtn) humanBtn.disabled = true;
		appendNotice(strings.escalating || 'در حال اتصال به کارشناس...');

		// The button is always on screen, so it can be the visitor's very
		// first action — before any chat turn has created a session.
		api('escalate', { method: 'POST', body: { session_id: ensureSession() } })
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (!data || !data.success) {
					appendNotice((data && data.error) ? data.error : TXT_GENERIC_ERROR);
					return;
				}
				setStatus(data.status || 'pending');
				if (strings.agentWillReply) appendNotice(strings.agentWillReply);
				startPolling();
			})
			.catch(function () { appendNotice(TXT_NETWORK_ERROR); })
			.then(function () {
				escalating = false;
				if (humanBtn) humanBtn.disabled = false;
			});
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
		// A background tab has nobody to show a reply to.
		if (document.hidden || !sessionId) return;

		api('conversation/' + encodeURIComponent(sessionId) + '/messages?after_id=' + lastMessageId)
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (!data || !data.success) return;

				var wasPending = conversationStatus === 'pending';

				(data.messages || []).forEach(function (m) {
					lastMessageId = Math.max(lastMessageId, parseInt(m.id, 10) || 0);
				});

				if (data.status === 'active' && wasPending) {
					appendNotice(strings.connectedToAgent || 'به کارشناس پشتیبانی متصل شدید');
				}

				// After the "connected" line, so the agent's first words follow it.
				(data.messages || []).forEach(function (m) {
					if (m.role === 'agent') {
						appendMessage('assistant', m.content).classList.add('yuniq-ai-msg-agent');
						remember('agent', m.content);
					}
				});

				if (data.status === 'pending' || data.status === 'active') {
					if (data.status !== conversationStatus) setStatus(data.status);
				} else if (conversationStatus === 'pending' || conversationStatus === 'active') {
					// Closed by the agent (or the conversation is gone).
					stopPolling();
					appendNotice(strings.resolvedByAgent || 'گفتگو با کارشناس پایان یافت.');
					setStatus('bot');
				}
			})
			.catch(function () {});
	}

	function sendEscalatedMessage(message) {
		api('conversation/' + encodeURIComponent(sessionId) + '/messages', { method: 'POST', body: { message: message } })
			.then(function (res) {
				return res.json().then(function (data) { return { status: res.status, data: data }; });
			})
			.then(function (r) {
				if (r.data && r.data.success) return;
				appendNotice((r.data && r.data.error) ? r.data.error : TXT_GENERIC_ERROR);
				// Only "this conversation is closed" hands the visitor back to
				// the assistant; a rate limit or a blip must not end the handoff.
				if (r.status === 409) {
					setStatus('bot');
					stopPolling();
				}
			})
			.catch(function () { appendNotice(TXT_NETWORK_ERROR); })
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
		// Addresses come from the site's own index, but are still held to http(s).
		if (product.url && !/^https?:\/\//i.test(product.url)) product.url = '';
		if (product.image && !/^https?:\/\//i.test(product.image)) product.image = '';

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
			var inStock = product.stock_status !== 'outofstock';
			html += '<span class="yuniq-ai-product-stock ' + (inStock ? 'is-in-stock' : 'is-out-of-stock') + '">' +
				escHtml(inStock ? (strings.inStock || 'موجود') : (strings.outOfStock || 'ناموجود')) + '</span>';
		}

		html += '<div class="yuniq-ai-product-actions">';
		if (product.url) {
			html += '<a class="yuniq-ai-product-btn" href="' + escAttr(product.url) + '" target="_blank" rel="noopener noreferrer">' + escHtml(strings.viewProduct || 'مشاهده محصول') + '</a>';
			if (product.purchasable !== false) {
				var cartUrl = product.url + (product.url.indexOf('?') === -1 ? '?' : '&') + 'add-to-cart=' + encodeURIComponent(product.id);
				html += '<a class="yuniq-ai-product-btn yuniq-ai-product-btn-primary" href="' + escAttr(cartUrl) + '">' + escHtml(strings.addToCart || 'افزودن به سبد خرید') + '</a>';
			}
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
			if (field.type === 'tel' || field.type === 'email') {
				inputEl.dir = 'ltr';
				inputEl.autocomplete = field.type;
				inputEl.inputMode = field.type === 'tel' ? 'tel' : 'email';
			} else {
				inputEl.dir = 'auto';
			}
			inputEl.maxLength = 1000;
			if (field.required) inputEl.required = true;
			row.appendChild(inputEl);
			formEl.appendChild(row);
			inputs[field.name] = inputEl;
		});

		var errorEl = document.createElement('div');
		errorEl.className = 'yuniq-ai-inline-form-error';
		errorEl.setAttribute('role', 'alert');
		formEl.appendChild(errorEl);

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
			errorEl.textContent = '';

			// Errors stay inside the form, next to the field they are about.
			function fail(message) {
				submitBtn.disabled = false;
				errorEl.textContent = message;
			}

			api('lead', { method: 'POST', body: { session_id: ensureSession(), form_key: formKey, fields: fields } })
				.then(function (res) { return res.json(); })
				.then(function (data) {
					if (data && data.success) {
						formEl.innerHTML = '';
						var done = document.createElement('div');
						done.className = 'yuniq-ai-inline-form-done';
						done.textContent = strings.formSubmitted || 'با تشکر! به‌زودی با شما تماس گرفته می‌شود.';
						formEl.appendChild(done);
					} else {
						fail((data && data.error) ? data.error : TXT_GENERIC_ERROR);
					}
				})
				.catch(function () { fail(TXT_NETWORK_ERROR); });
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
		// Only a visitor who actually escalated pays for this request; it
		// used to fire on every page view of every returning visitor.
		if (!sessionId || !liveSupport.enabled || !store('get', ESCALATED_KEY)) return;

		api('conversation/' + encodeURIComponent(sessionId) + '/messages?after_id=0')
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (!data || !data.success || (data.status !== 'pending' && data.status !== 'active')) {
					store('del', ESCALATED_KEY);
					return;
				}

				// The server copy is the complete one, so it replaces
				// whatever this tab had restored locally.
				restoring = true;
				startChatUI();
				messagesEl.innerHTML = '';

				(data.messages || []).forEach(function (m) {
					lastMessageId = Math.max(lastMessageId, parseInt(m.id, 10) || 0);
					var el = appendMessage(m.role === 'user' ? 'user' : 'assistant', m.content);
					if (m.role === 'agent') el.classList.add('yuniq-ai-msg-agent');
				});

				restoring = false;
				setStatus(data.status);

				appendNotice(conversationStatus === 'active'
					? (strings.connectedToAgent || 'به کارشناس پشتیبانی متصل شدید')
					: (strings.escalating || 'در حال اتصال به کارشناس...'));

				startPolling();
			})
			.catch(function () {});
	}

	restoreTranscript();
	checkExistingEscalation();

	// Catch up the moment the visitor comes back to the tab.
	document.addEventListener('visibilitychange', function () {
		if (!document.hidden && pollTimer) pollMessages();
	});

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
		// The failed question must not be replayed to the model next turn.
		if (history.length && history[history.length - 1].role === 'user') history.pop();
	}

	function rememberSession(id) {
		if (!id) return;
		sessionId = id;
		store('set', 'yuniq_ai_session_id', sessionId);
	}

	function requestInit(message) {
		return {
			method: 'POST',
			body: {
				message: message,
				session_id: ensureSession(),
				history: history.slice(0, -1).slice(-20)
			}
		};
	}

	function streamMessage(message) {
		api('chat/stream', requestInit(message))
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
			if (text && !errored) {
				history.push({ role: 'assistant', content: text });
				remember('assistant', text);
			}
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
			} else if (payload.type === 'options') {
				settle();
				renderOptions(payload.options);
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
		return api('chat', requestInit(message))
			.then(function (res) { return res.json(); })
			.then(function (data) {
				hideTyping();
				rememberSession(data && data.session_id);

				if (data && data.success && data.content) {
					appendMessage('assistant', data.content);
					history.push({ role: 'assistant', content: data.content });
					remember('assistant', data.content);
					var cards = Array.isArray(data.products) && data.products.length ? data.products : (data.product ? [data.product] : []);
					cards.forEach(renderProductCard);
					if (data.form_key) renderForm(data.form_key);
					if (data.needs_human) renderNeedHumanCta();
					else renderOptions(data.options);
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
		remember('user', message);

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
			if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
				e.preventDefault();
				sendMessage(input.value);
			}
		});
		input.addEventListener('focus', function () {
			if (isMobile()) onViewportChange();
		});
	}
})();
