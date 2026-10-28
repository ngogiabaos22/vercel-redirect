/* TNP Trắc nghiệm – mở bài test trong cửa sổ bật lên khi bấm nút.
 * Nút/liên kết có đường dẫn "#tnp-test=slug-bai-test" (hoặc "#tnp-test" cho bài IQ chung)
 * hoặc thuộc tính data-tnp-test="slug-bai-test". */
(function () {
	var D = window.tnpLauncher || {}, loading = null, open = null;

	function assets() {
		if (window.TNPQuiz) { return Promise.resolve(); }
		if (loading) { return loading; }
		loading = new Promise(function (ok, fail) {
			if (!document.getElementById('tnp-iq-quiz-css')) {
				var l = document.createElement('link');
				l.rel = 'stylesheet'; l.id = 'tnp-iq-quiz-css'; l.href = D.css;
				document.head.appendChild(l);
			}
			var s = document.createElement('script');
			s.src = D.js; s.onload = ok; s.onerror = fail;
			document.body.appendChild(s);
		});
		return loading;
	}

	function slugOf(el) {
		if (el.hasAttribute('data-tnp-test')) { return el.getAttribute('data-tnp-test'); }
		var m = (el.getAttribute('href') || '').match(/#tnp-test(?:=([\w-]*))?/);
		return m ? (m[1] || '') : null;
	}

	function close() {
		if (!open) { return; }
		var q = open.querySelector('.tnp-iq');
		if (q && q.tnpQuiz) {
			clearInterval(q.tnpQuiz.timer);
			if (q.tnpQuiz.taskPopup) { q.tnpQuiz.taskPopup.close(); }
		}
		open.remove();
		open = null;
		document.documentElement.classList.remove('tnp-modal-open');
		document.removeEventListener('keydown', onKey);
		if (/#tnp-test/.test(location.hash) && history.replaceState) {
			history.replaceState(null, '', location.pathname + location.search);
		}
	}
	function onKey(e) {
		var q = open && open.querySelector('.tnp-iq');
		if (q && q.tnpQuiz && q.tnpQuiz.taskPopup) { return; } // đang làm nhiệm vụ: Esc không đóng bài test
		if (e.key === 'Escape') { close(); }
	}

	function show(slug) {
		var t = (D.tests || {})[slug];
		if (!t) { t = (D.tests || {})[''] || { kind: 'iq', title: 'Bài test', intro: '' }; slug = ''; }
		close();
		var cfg = {
			api: D.api, test: slug, count: null, time: null, kind: t.kind,
			introTitle: t.title, introText: t.intro, ageNote: D.ageNote, autostart: true
		};
		open = document.createElement('div');
		open.className = 'tnp-modal';
		open.setAttribute('role', 'dialog');
		open.setAttribute('aria-modal', 'true');
		open.setAttribute('aria-label', t.title);
		open.innerHTML =
			'<div class="tnp-modal-box">' +
				'<div class="tnp-modal-head"><b></b><button type="button" class="tnp-modal-x" aria-label="Đóng">×</button></div>' +
				'<div class="tnp-modal-body"><div class="tnp-iq"><div class="tnp-modal-wait">Đang tải bài test…</div></div></div>' +
			'</div>';
		open.querySelector('.tnp-modal-head b').textContent = t.title;
		var root = open.querySelector('.tnp-iq');
		root.setAttribute('data-config', JSON.stringify(cfg));
		open.addEventListener('click', function (e) {
			if (e.target === open || e.target.closest('.tnp-modal-x')) { close(); }
		});
		document.body.appendChild(open);
		document.documentElement.classList.add('tnp-modal-open');
		document.addEventListener('keydown', onKey);
		assets().then(function () {
			if (open && open.contains(root)) { window.TNPQuiz.mount(root); }
		}).catch(function () {
			root.innerHTML = '<div class="tnp-modal-wait">Không tải được bài test. Vui lòng thử lại.</div>';
		});
	}

	// Bắt sự kiện ở pha capture để theme (vd Flatsome cuộn tới #neo) không xử lý trước.
	window.addEventListener('click', function (e) {
		if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey) { return; }
		var el = e.target.closest && e.target.closest('[data-tnp-test], a[href*="#tnp-test"]');
		if (!el) { return; }
		var slug = slugOf(el);
		if (slug === null) { return; }
		e.preventDefault();
		e.stopPropagation();
		show(slug);
	}, true);

	// Mở sẵn khi đường dẫn có #tnp-test=… (chia sẻ link thẳng tới bài test).
	function fromHash() {
		var m = location.hash.match(/^#tnp-test(?:=([\w-]*))?$/);
		if (m) { show(m[1] || ''); }
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', fromHash); } else { fromHash(); }

	window.TNPLauncher = { open: show, close: close };
})();
