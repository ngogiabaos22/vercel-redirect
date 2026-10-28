/* TNP IQ Test – giao diện làm bài */
(function () {
	'use strict';

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function fmt(sec) {
		sec = Math.max(0, Math.round(sec));
		return Math.floor(sec / 60) + ':' + ('0' + (sec % 60)).slice(-2);
	}
	// Bài đang làm chỉ nằm trong bộ nhớ trang: tải lại (F5) là bắt đầu lại từ đầu.
	// Xoá dữ liệu "bài đang làm" mà các bản trước (1.1–1.2) từng lưu trong trình duyệt.
	try {
		Object.keys(sessionStorage).forEach(function (k) { if (k.indexOf('tnp_iq_state') === 0) { sessionStorage.removeItem(k); } });
	} catch (e) { /* bỏ qua */ }

	function Quiz(root) {
		this.root = root;
		this.cfg = JSON.parse(root.getAttribute('data-config') || '{}');
		this.st = null;
		this.timer = null;
		this.renderIntro();
		// Mở từ nút (cửa sổ bật lên): vào thẳng bài làm, không dừng ở màn hình giới thiệu.
		if (this.cfg.autostart) { this.start(this.root.querySelector('[data-act="start"]')); }
	}

	// Khung cuộn chứa bài test: cửa sổ bật lên (nếu mở từ nút) hoặc cả trang.
	Quiz.prototype.scroller = function () {
		return this.root.closest ? this.root.closest('.tnp-modal-body') : null;
	};

	Quiz.prototype.html = function (h) {
		this.root.innerHTML = h;
		var sc = this.scroller();
		if (sc) { sc.scrollTop = 0; return; }
		var top = this.root.getBoundingClientRect().top;
		if (top < 0) { window.scrollBy(0, top - 90); }
	};

	/* ---------- Màn hình bắt đầu ---------- */
	Quiz.prototype.renderIntro = function () {
		var c = this.cfg, self = this;
		this.html(
			'<div class="tnp-card tnp-intro">' +
				'<div class="tnp-intro-icon" aria-hidden="true">🧠</div>' +
				'<h2>' + esc(c.introTitle) + '</h2>' +
				'<p>' + esc(c.introText) + '</p>' +
				'<div class="tnp-actions">' +
					'<button type="button" class="tnp-btn" data-act="start">Bắt đầu làm bài</button>' +
				'</div>' +
			'</div>'
		);
		this.root.querySelector('[data-act="start"]').onclick = function () { self.start(this); };
	};

	Quiz.prototype.start = function (btn) {
		var self = this, c = this.cfg, q = [];
		if (c.test) { q.push('test=' + encodeURIComponent(c.test)); }
		if (c.count !== null && c.count !== undefined) { q.push('count=' + encodeURIComponent(c.count)); }
		if (c.time !== null && c.time !== undefined) { q.push('time=' + encodeURIComponent(c.time)); }
		btn.disabled = true;
		btn.textContent = 'Đang tải đề…';
		fetch(c.api + '/start' + (q.length ? '?' + q.join('&') : ''), { credentials: 'same-origin' })
			.then(function (r) { return r.json().then(function (d) { if (!r.ok) { throw d; } return d; }); })
			.then(function (d) {
				self.st = {
					api: c.api, test: c.test || '', token: d.token, questions: d.questions, limit: d.timeLimit,
					kind: d.kind || 'iq', needsAge: d.needsAge !== false, showTimer: d.showTimer !== false,
					task: d.task || null, unlocked: false,
					answers: {}, index: 0, startedAt: Date.now(), finishedAt: null, stage: 'quiz'
				};
				self.preload();
				self.continue();
			})
			.catch(function (e) { self.error(e && e.message ? e.message : 'Không tải được đề. Vui lòng thử lại.'); });
	};

	Quiz.prototype.preload = function () {
		this.st.questions.forEach(function (q) {
			q.cells.concat([q.image]).concat(q.options.map(function (o) { return o.img; })).forEach(function (u) {
				if (u) { var i = new Image(); i.src = u; }
			});
		});
	};

	Quiz.prototype.continue = function () {
		if (this.st.stage === 'age' || this.timeUp(this.st)) { this.finishQuestions(this.timeUp(this.st) && !this.st.finishedAt); }
		else { this.renderQuestion(); }
	};

	/* ---------- Đồng hồ (đếm xuôi) ---------- */
	Quiz.prototype.elapsed = function (st) {
		st = st || this.st;
		return (Date.now() - st.startedAt) / 1000;
	};
	// Chỉ hết giờ khi bài có đặt thời gian tối đa.
	Quiz.prototype.timeUp = function (st) {
		st = st || this.st;
		return !!st.limit && this.elapsed(st) >= st.limit;
	};

	Quiz.prototype.tick = function () {
		var self = this;
		clearInterval(this.timer);
		var el = this.root.querySelector('.tnp-timer');
		var update = function () {
			var used = self.elapsed();
			if (el) {
				el.textContent = '⏱ ' + fmt(used);
				el.classList.toggle('is-low', !!self.st.limit && self.st.limit - used <= 60);
			}
			if (self.timeUp()) { clearInterval(self.timer); self.finishQuestions(true); }
		};
		update();
		this.timer = setInterval(update, 1000);
	};

	/* ---------- Câu hỏi ---------- */
	Quiz.prototype.renderQuestion = function () {
		var self = this, st = this.st, q = st.questions[st.index], n = st.questions.length;
		var answered = Object.keys(st.answers).length;
		var body = '';

		if (q.body) {
			body += '<div class="tnp-qbody">' + esc(q.body).replace(/\n/g, '<br>') + '</div>';
		}
		if (q.type === 'grid') { // câu dạng lưới từ bản cũ
			body += '<div class="tnp-grid" style="--cols:' + q.grid + '">';
			q.cells.forEach(function (u) { body += '<div class="tnp-cell"><img src="' + esc(u) + '" alt=""></div>'; });
			body += '<div class="tnp-cell tnp-missing">?</div></div>';
		}
		if (q.image) {
			body += '<div class="tnp-qimg' + (q.type === 'image' ? ' is-main' : '') + '"><img src="' + esc(q.image) + '" alt=""></div>';
		}

		// Cách xếp đáp án: có ảnh → lưới thẻ (2–4 cột tuỳ số đáp án); chỉ có chữ → danh sách.
		var nOpt = q.options.length;
		var hasImg = q.options.some(function (o) { return o.img; });
		var shortText = q.options.every(function (o) { return (o.text || '').length <= 28; });
		var cols = nOpt <= 4 ? nOpt : (nOpt <= 6 ? 3 : 4);
		var layout = hasImg ? 'is-cards' : (shortText && nOpt > 2 ? 'is-list is-2col' : 'is-list');
		var opts = '<div class="tnp-opts ' + layout + '" style="--cols:' + cols + '" role="radiogroup" aria-label="Chọn đáp án">';
		q.options.forEach(function (o, i) {
			var sel = st.answers[q.id] === o.key;
			var label = 'ABCDEFGH'.charAt(i); // nhãn theo vị trí hiển thị (đáp án có thể đã được xáo trộn)
			opts += '<button type="button" class="tnp-opt' + (sel ? ' is-selected' : '') + '" data-key="' + esc(o.key) + '" role="radio" aria-checked="' + sel + '">' +
				'<span class="tnp-opt-key">' + label + '</span>' +
				(o.img ? '<span class="tnp-opt-img"><img src="' + esc(o.img) + '" alt="Đáp án ' + label + '"></span>' : '') +
				(o.text ? '<span class="tnp-opt-text">' + esc(o.text) + '</span>' : '') +
			'</button>';
		});
		opts += '</div>';

		var dots = '<div class="tnp-dots" aria-label="Chuyển câu">';
		st.questions.forEach(function (qq, i) {
			dots += '<button type="button" class="tnp-dot' + (i === st.index ? ' is-current' : '') + (st.answers[qq.id] ? ' is-done' : '') + '" data-go="' + i + '" aria-label="Câu ' + (i + 1) + '">' + (i + 1) + '</button>';
		});
		dots += '</div>';

		var last = st.index === n - 1;
		this.html(
			'<div class="tnp-card tnp-quiz">' +
				'<div class="tnp-top">' +
					'<span class="tnp-count">Câu <b>' + (st.index + 1) + '</b>/' + n + ' · Đã trả lời ' + answered + '</span>' +
					(st.showTimer ? '<span class="tnp-timer" title="Thời gian đã làm"></span>' : '') +
				'</div>' +
				'<div class="tnp-bar"><span style="width:' + Math.round(answered / n * 100) + '%"></span></div>' +
				'<h3 class="tnp-qtitle"><span>' + (st.index + 1) + '.</span> ' + esc(q.prompt) + '</h3>' +
				body + opts + dots +
				'<div class="tnp-nav">' +
					'<button type="button" class="tnp-btn tnp-btn-ghost" data-act="prev"' + (st.index === 0 ? ' disabled' : '') + '>← Câu trước</button>' +
					(last
						? '<button type="button" class="tnp-btn" data-act="finish">Nộp bài</button>'
						: '<button type="button" class="tnp-btn" data-act="next">Câu tiếp →</button>') +
				'</div>' +
			'</div>'
		);
		// Trên điện thoại dãy số câu cuộn ngang: đưa câu hiện tại vào giữa.
		var dotsEl = this.root.querySelector('.tnp-dots'), cur = this.root.querySelector('.tnp-dot.is-current');
		if (dotsEl && cur && dotsEl.scrollWidth > dotsEl.clientWidth) {
			dotsEl.scrollLeft = cur.offsetLeft - dotsEl.offsetLeft - dotsEl.clientWidth / 2 + cur.offsetWidth / 2;
		}

		this.root.querySelectorAll('.tnp-opt').forEach(function (b) {
			b.onclick = function () {
				st.answers[q.id] = b.getAttribute('data-key');
				self.root.querySelectorAll('.tnp-opt').forEach(function (x) {
					x.classList.toggle('is-selected', x === b);
					x.setAttribute('aria-checked', x === b);
				});
				if (!last) { setTimeout(function () { if (self.st.index === st.questions.indexOf(q)) { self.go(st.index + 1); } }, 350); }
				else { self.renderQuestion(); }
			};
		});
		this.root.querySelectorAll('[data-go]').forEach(function (b) {
			b.onclick = function () { self.go(+b.getAttribute('data-go')); };
		});
		var on = function (act, fn) { var b = self.root.querySelector('[data-act="' + act + '"]'); if (b) { b.onclick = fn; } };
		on('prev', function () { self.go(st.index - 1); });
		on('next', function () { self.go(st.index + 1); });
		on('finish', function () {
			var miss = n - Object.keys(st.answers).length;
			if (miss > 0 && !window.confirm('Bạn còn ' + miss + ' câu chưa trả lời. Vẫn nộp bài?')) { return; }
			self.finishQuestions(false);
		});
		this.tick();
		this.fit(true);
	};

	/* ---------- Cả câu (đề + ảnh + đáp án + nút) nằm gọn trong một màn hình / cửa sổ bật lên ---------- */
	var mobile = window.matchMedia ? window.matchMedia('(max-width:600px)') : { matches: false };

	// Chiều cao phần đầu trang đang dính trên cùng (vd header Flatsome khi cuộn), để không che đề bài.
	function stuckTop() {
		var el = document.elementFromPoint(window.innerWidth / 2, 2), h = 0;
		for (; el && el !== document.body && el !== document.documentElement; el = el.parentElement) {
			var pos = window.getComputedStyle(el).position;
			if (pos === 'fixed' || pos === 'sticky') {
				var b = el.getBoundingClientRect().bottom;
				if (b > 0 && b < window.innerHeight / 3) { h = b; }
			}
		}
		return h;
	}

	Quiz.prototype.fit = function (align) {
		var self = this, card = this.root.querySelector('.tnp-quiz');
		if (!card) { return; }
		var qimg = card.querySelector('.tnp-qimg img'), oimg = card.querySelector('.tnp-opts.is-cards .tnp-opt-img');
		card.style.removeProperty('--qimg-h');
		card.style.removeProperty('--opt-h');
		card.classList.toggle('is-fit', !!(qimg || oimg));
		if (!card.classList.contains('is-fit')) { return; }

		if (!this.fitBound) { // ảnh tải xong thì tính lại; lắng nghe một lần cho mỗi khung
			this.fitBound = true;
			this.root.addEventListener('load', function (e) { if (e.target.tagName === 'IMG') { self.fit(false); } }, true);
			window.addEventListener('resize', function () { clearTimeout(self.fitT); self.fitT = setTimeout(function () { self.fit(false); }, 150); });
		}

		var sc = this.scroller(), box = sc ? sc.getBoundingClientRect() : null;
		var top = sc ? box.top : stuckTop(), bottom = sc ? box.bottom : window.innerHeight;
		if (align && !sc && mobile.matches) {
			var y = card.getBoundingClientRect().top - top - 6;
			if (Math.abs(y) > 2) { window.scrollBy(0, y); top = stuckTop(); }
		}
		var opts = card.querySelector('.tnp-opts'), nav = card.querySelector('.tnp-nav');
		var avail = bottom - top - 6;
		var ctop = card.getBoundingClientRect().top;
		var lead = sc ? ctop + sc.scrollTop - top : 0; // khoảng đệm phía trên thẻ trong cửa sổ bật lên
		// Nút điều hướng dính đáy (điện thoại): cần chỗ cho đáp án + nút; nếu không dính: tới hết nút (gồm dãy số câu).
		var sticky = window.getComputedStyle(nav).position === 'sticky';
		var used = lead + (sticky ? opts.getBoundingClientRect().bottom - ctop + nav.offsetHeight + 12 : nav.getBoundingClientRect().bottom - ctop + 8);
		var over = used - avail;
		if (over <= 0) { return; }

		// Chia lại chiều cao còn lại: ảnh đề ~1/2 (tối thiểu 140px), phần còn lại cho các hàng ảnh đáp án (tối thiểu 46px).
		var cols = parseInt(opts.style.getPropertyValue('--cols'), 10) || 3;
		var rows = oimg ? Math.ceil(opts.children.length / cols) : 0;
		var qh = qimg ? qimg.getBoundingClientRect().height : 0, oh = oimg ? oimg.getBoundingClientRect().height : 0;
		var room = qh + rows * oh - over;
		if (room <= 0 && !qh) { return; }
		var nq = qh ? Math.min(qh, Math.max(140, Math.floor(rows ? room * 0.55 : room))) : 0;
		var no = rows ? Math.min(oh, Math.max(46, Math.floor((room - nq) / rows))) : 0;
		if (qh) { card.style.setProperty('--qimg-h', nq + 'px'); }
		if (oh && no < oh) { card.style.setProperty('--opt-h', no + 'px'); }
	};

	Quiz.prototype.go = function (i) {
		if (i < 0 || i >= this.st.questions.length) { return; }
		this.st.index = i;
		this.renderQuestion();
	};

	Quiz.prototype.finishQuestions = function (timeout) {
		clearInterval(this.timer);
		if (!this.st.finishedAt) { this.st.finishedAt = Date.now(); }
		this.st.stage = 'age';
		if (this.st.needsAge) { this.renderAge(timeout); } else { this.renderSubmitting(timeout); }
	};

	/* Test tính cách: không hỏi tuổi, chấm điểm ngay. */
	Quiz.prototype.renderSubmitting = function (timeout) {
		var self = this;
		this.html(
			'<div class="tnp-card tnp-age">' +
				(timeout ? '<div class="tnp-note">⏰ Đã hết giờ làm bài. Các câu bạn đã trả lời vẫn được tính.</div>' : '') +
				'<div class="tnp-intro-icon" aria-hidden="true">✨</div>' +
				'<p>Đang phân tích câu trả lời của bạn…</p>' +
				'<div class="tnp-err" hidden></div>' +
				'<div class="tnp-actions"><button type="button" class="tnp-btn" disabled>Đang chấm điểm…</button></div>' +
			'</div>'
		);
		var btn = this.root.querySelector('.tnp-btn');
		btn.onclick = function () { self.submit(btn); };
		this.submit(btn);
	};

	/* ---------- Bước cuối: nhập tuổi ---------- */
	Quiz.prototype.renderAge = function (timeout) {
		var self = this, st = this.st;
		clearInterval(this.timer);
		if (!st.finishedAt) { st.finishedAt = st.limit ? Math.min(Date.now(), st.startedAt + st.limit * 1000) : Date.now(); st.stage = 'age'; }
		this.html(
			'<form class="tnp-card tnp-age">' +
				(timeout ? '<div class="tnp-note">⏰ Đã hết giờ làm bài. Các câu bạn đã trả lời vẫn được tính điểm.</div>' : '') +
				'<div class="tnp-intro-icon" aria-hidden="true">🎂</div>' +
				'<h3 class="tnp-qtitle"><span>' + (st.questions.length + 1) + '.</span> Bạn bao nhiêu tuổi?</h3>' +
				'<p>' + esc(this.cfg.ageNote) + '</p>' +
				'<label class="tnp-age-field"><input type="number" name="age" min="5" max="100" inputmode="numeric" required placeholder="Ví dụ: 22" value="' + esc(st.age || '') + '"><span>tuổi</span></label>' +
				'<div class="tnp-err" hidden></div>' +
				'<div class="tnp-actions"><button type="submit" class="tnp-btn">Xem kết quả IQ</button></div>' +
			'</form>'
		);
		var form = this.root.querySelector('form'), input = form.querySelector('input');
		input.focus({ preventScroll: true });
		form.onsubmit = function (e) {
			e.preventDefault();
			var age = parseInt(input.value, 10);
			var err = form.querySelector('.tnp-err');
			if (!(age >= 5 && age <= 100)) {
				err.textContent = 'Vui lòng nhập tuổi từ 5 đến 100.';
				err.hidden = false;
				return;
			}
			st.age = age;
			self.submit(form.querySelector('button'));
		};
	};

	Quiz.prototype.send = function (code) {
		var st = this.st;
		return fetch(this.cfg.api + '/submit', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				token: st.token,
				answers: st.answers,
				age: st.needsAge ? st.age : 0,
				elapsed: Math.round((st.finishedAt - st.startedAt) / 1000),
				task_code: code || '',
				page: window.location.href
			})
		}).then(function (r) { return r.json().then(function (d) { if (!r.ok) { throw d; } return d; }); });
	};

	Quiz.prototype.submit = function (btn) {
		var self = this, st = this.st;
		if (st.task && !st.unlocked) { this.taskGate(btn); return; }
		btn.disabled = true;
		btn.textContent = 'Đang chấm điểm…';
		this.send('')
			.then(function (res) {
				st.stage = 'done';
				self.renderResult(res);
			})
			.catch(function (e) {
				btn.disabled = false;
				btn.textContent = 'Thử lại';
				self.showErr((e && e.message) || 'Không gửi được bài. Kiểm tra kết nối mạng rồi thử lại.');
			});
	};

	Quiz.prototype.showErr = function (msg) {
		var err = this.root.querySelector('.tnp-err');
		if (err) { err.textContent = msg; err.hidden = !msg; }
	};

	/* ---------- Nhiệm vụ (Traffic Hila): làm nhiệm vụ, nhập mã đúng mới xem được kết quả ---------- */
	var scripts = {};
	function loadScript(src) {
		if (window.IATSPopup) { return Promise.resolve(); }
		if (!scripts[src]) {
			scripts[src] = new Promise(function (ok, fail) {
				var s = document.createElement('script');
				s.src = src;
				s.onload = function () { if (window.IATSPopup) { ok(); } else { fail(); } };
				s.onerror = function () { delete scripts[src]; s.remove(); fail(); };
				document.body.appendChild(s);
			});
		}
		return scripts[src];
	}

	Quiz.prototype.taskGate = function (btn) {
		var self = this, st = this.st, label = /^Đang/.test(btn.textContent) ? 'Xem kết quả' : btn.textContent;
		btn.disabled = true;
		btn.textContent = 'Đang tải nhiệm vụ…';
		this.showErr('');
		loadScript(st.task.script).then(function () {
			btn.disabled = false;
			btn.textContent = label;
			self.taskPopup = window.IATSPopup.open({
				tag: st.task.tag,
				mode: 'result',
				onSubmit: function (code) {
					return self.send(code).then(function (res) {
						st.unlocked = true;
						self.taskPopup = null;
						setTimeout(function () { st.stage = 'done'; self.renderResult(res); }, 0);
						return { success: true };
					}, function (e) {
						return { success: false, message: (e && e.message) || 'Không kiểm tra được mã. Vui lòng thử lại.' };
					});
				},
				onClose: function () { self.taskPopup = null; }
			});
		}, function () {
			btn.disabled = false;
			btn.textContent = label;
			self.showErr('Không tải được nhiệm vụ. Kiểm tra kết nối mạng rồi bấm lại.');
		});
	};

	/* ---------- Kết quả ---------- */
	// Đường cong phân phối chuẩn, tô phần dân số thấp hơn và đánh dấu vị trí IQ của người làm.
	function bell(iq) {
		var W = 600, H = 190, base = 160, lo = 40, hi = 160;
		var x = function (v) { return 20 + (v - lo) / (hi - lo) * (W - 40); };
		var y = function (v) { return base - 130 * Math.exp(-Math.pow((v - 100) / 15, 2) / 2); };
		var line = '', area = '', v;
		for (v = lo; v <= hi; v += 1) { line += (v === lo ? 'M' : 'L') + x(v).toFixed(1) + ',' + y(v).toFixed(1); }
		var cap = Math.max(lo, Math.min(hi, iq));
		area = 'M' + x(lo) + ',' + base;
		for (v = lo; v <= cap; v += 1) { area += 'L' + x(v).toFixed(1) + ',' + y(v).toFixed(1); }
		area += 'L' + x(cap).toFixed(1) + ',' + y(cap).toFixed(1) + 'L' + x(cap).toFixed(1) + ',' + base + 'Z';
		var ticks = '';
		[55, 70, 85, 100, 115, 130, 145].forEach(function (t) {
			ticks += '<line x1="' + x(t) + '" y1="' + base + '" x2="' + x(t) + '" y2="' + (base + 5) + '" stroke="#cbd5e1"/>' +
				'<text x="' + x(t) + '" y="' + (base + 20) + '" text-anchor="middle">' + t + '</text>';
		});
		var mx = x(cap), my = y(cap);
		return '<svg class="tnp-bell" viewBox="0 0 ' + W + ' ' + (H + 10) + '" role="img" aria-label="Vị trí điểm IQ ' + iq + ' trên phân phối chuẩn">' +
			'<defs><linearGradient id="tnpBellFill" x1="0" x2="1"><stop offset="0" stop-color="#6366f1" stop-opacity=".15"/><stop offset="1" stop-color="#06b6d4" stop-opacity=".45"/></linearGradient></defs>' +
			'<path d="' + area + '" fill="url(#tnpBellFill)"/>' +
			'<path d="' + line + '" fill="none" stroke="#6366f1" stroke-width="3"/>' +
			'<line x1="20" y1="' + base + '" x2="' + (W - 20) + '" y2="' + base + '" stroke="#cbd5e1"/>' + ticks +
			'<line x1="' + mx + '" y1="' + (my - 26) + '" x2="' + mx + '" y2="' + base + '" stroke="#0f172a" stroke-width="2" stroke-dasharray="4 4"/>' +
			'<circle cx="' + mx + '" cy="' + my + '" r="7" fill="#fff" stroke="#4f46e5" stroke-width="4"/>' +
			'<rect x="' + (mx - 34) + '" y="' + (my - 54) + '" width="68" height="26" rx="13" fill="#0f172a"/>' +
			'<text x="' + mx + '" y="' + (my - 36) + '" text-anchor="middle" fill="#fff" font-weight="700">IQ ' + iq + '</text>' +
		'</svg>';
	}

	Quiz.prototype.renderResult = function (r) {
		var fn = { eq: 'renderEq', disc: 'renderDisc', mbti: 'renderMbti', holland: 'renderHolland' }[r.kind];
		if (fn) { this[fn](r); } else { this.renderIq(r); }
		var self = this, again = this.root.querySelector('[data-act="again"]');
		if (again) { again.onclick = function () { self.st = null; self.renderIntro(); }; }
	};

	/* Khung chung cho trang kết quả các test tính cách. */
	function list(arr) { return '<ul>' + (arr || []).map(function (t) { return '<li>' + esc(t) + '</li>'; }).join('') + '</ul>'; }
	function chips(arr) { return '<div class="tnp-chips">' + (arr || []).map(function (c) { return '<span>' + esc(c) + '</span>'; }).join('') + '</div>'; }
	function bars(items, cls) {
		return '<div class="tnp-traits ' + (cls || '') + '">' + items.map(function (b) {
			return '<div class="tnp-trait tnp-trait-' + esc(b.key) + '"><div class="tnp-cat-head"><span>' + esc(b.name) + '</span><b>' + b.pct + '%</b></div>' +
				'<div class="tnp-bar"><span style="width:' + Math.max(3, b.pct) + '%"></span></div></div>';
		}).join('') + '</div>';
	}
	function shell(top, body, note) {
		return '<div class="tnp-card tnp-result">' + top + body +
			'<p class="tnp-disclaimer">' + esc(note) + '</p>' +
			'<div class="tnp-actions"><button type="button" class="tnp-btn" data-act="again">Làm lại bài test</button></div>' +
		'</div>';
	}
	var NOTE = 'Kết quả mang tính tham khảo, giúp bạn hiểu thêm về bản thân; không phải chẩn đoán tâm lý hay đánh giá chuyên môn.';

	Quiz.prototype.renderEq = function (r) {
		var focus = '';
		if (r.strong || r.weak) {
			focus = '<div class="tnp-focus">' +
				(r.strong ? '<div><span>💪 Điểm mạnh nhất</span><b>' + esc(r.strong.name) + '</b></div>' : '') +
				(r.weak ? '<div><span>🎯 Nên rèn luyện thêm</span><b>' + esc(r.weak.name) + '</b><p>' + esc(r.weak.tip) + '</p></div>' : '') +
			'</div>';
		}
		this.html(shell(
			'<div class="tnp-result-top"><div class="tnp-score tnp-score-eq"><span>Điểm EQ của bạn</span><b>' + r.score + '</b><em>' + esc(r.level.name) + '</em></div>' +
			'<div class="tnp-result-text"><p class="tnp-pct">Trí tuệ cảm xúc: <b>' + esc(r.level.name) + '</b></p><p>' + esc(r.level.desc) + '</p>' +
			'<div class="tnp-meter"><span style="width:' + r.score + '%"></span></div><div class="tnp-meter-lbl"><span>0</span><span>50</span><span>100</span></div></div></div>',
			(r.bars.length ? '<div class="tnp-section"><h4>5 thành phần của trí tuệ cảm xúc</h4>' + focus + bars(r.bars) + '</div>' : ''),
			NOTE
		));
	};

	Quiz.prototype.renderDisc = function (r) {
		var P = r.primary, S = r.secondary;
		this.html(shell(
			'<div class="tnp-result-top"><div class="tnp-score tnp-score-disc tnp-disc-' + esc(P.key) + '"><span>Nhóm DISC của bạn</span><b>' + esc(r.code) + '</b><em>' + esc(P.name) + '</em></div>' +
			'<div class="tnp-result-text"><p class="tnp-pct">' + esc(P.summary) + '</p><p>' + esc(P.desc) + '</p>' +
			(S ? '<p>Nhóm phụ: <b>' + esc(S.name) + '</b> — ' + esc(S.summary) + '</p>' : '') + '</div></div>',
			'<div class="tnp-section"><h4>Biểu đồ DISC của bạn</h4>' + bars(r.bars, 'tnp-traits-disc') + '</div>' +
			'<div class="tnp-section"><div class="tnp-two">' +
				'<div class="tnp-box"><h5>Điểm mạnh</h5>' + list(P.strengths) + '</div>' +
				'<div class="tnp-box"><h5>Điều cần lưu ý</h5>' + list(P.watch) + '</div>' +
			'</div></div>' +
			'<div class="tnp-section"><h4>Trong công việc</h4><p>' + esc(P.work) + '</p><p class="tnp-tip">💬 ' + esc(P.talk) + '</p></div>',
			NOTE
		));
	};

	Quiz.prototype.renderMbti = function (r) {
		var T = r.type;
		var axes = r.axes.map(function (a) {
			var leftWin = a.pick === a.left.key;
			return '<div class="tnp-axis"><div class="tnp-axis-title">' + esc(a.title) + '</div>' +
				'<div class="tnp-axis-row"><span class="' + (leftWin ? 'is-pick' : '') + '"><b>' + esc(a.left.key) + '</b> ' + esc(a.left.name) + ' ' + a.left.pct + '%</span>' +
				'<span class="' + (leftWin ? '' : 'is-pick') + '">' + a.right.pct + '% ' + esc(a.right.name) + ' <b>' + esc(a.right.key) + '</b></span></div>' +
				'<div class="tnp-axis-bar"><span style="width:' + a.left.pct + '%"></span></div></div>';
		}).join('');
		this.html(shell(
			'<div class="tnp-result-top"><div class="tnp-score tnp-score-mbti"><span>Nhóm tính cách MBTI</span><b>' + esc(T.code) + '</b><em>' + esc(T.name) + '</em></div>' +
			'<div class="tnp-result-text"><p class="tnp-pct">' + esc(T.code) + ' – ' + esc(T.name) + ' <small>(' + esc(T.group) + ')</small></p><p>' + esc(T.desc) + '</p></div></div>',
			'<div class="tnp-section"><h4>Xu hướng của bạn trên 4 cặp tính cách</h4>' + axes + '</div>' +
			'<div class="tnp-section"><div class="tnp-two">' +
				'<div class="tnp-box"><h5>Điểm mạnh</h5>' + list(T.strengths) + '</div>' +
				'<div class="tnp-box"><h5>Điều cần lưu ý</h5>' + list(T.watch) + '</div>' +
			'</div></div>' +
			'<div class="tnp-section"><h4>Nghề nghiệp gợi ý</h4>' + chips(T.careers) + '</div>',
			NOTE
		));
	};

	Quiz.prototype.renderHolland = function (r) {
		var tops = r.top.map(function (t, i) {
			return '<div class="tnp-hcard"><div class="tnp-hcard-head"><span class="tnp-hletter">' + esc(t.key) + '</span><div><b>' + (i + 1) + '. ' + esc(t.name) + '</b><small>' + t.pct + '% mức độ yêu thích</small></div></div>' +
				'<p>' + esc(t.desc) + '</p>' + chips(t.careers) + '</div>';
		}).join('');
		this.html(shell(
			'<div class="tnp-result-top"><div class="tnp-score tnp-score-holland"><span>Mã Holland của bạn</span><b>' + esc(r.code) + '</b><em>' + esc(r.top[0].name) + '</em></div>' +
			'<div class="tnp-result-text"><p class="tnp-pct">Nhóm sở thích nổi bật: <b>' + r.top.map(function (t) { return esc(t.name); }).join(', ') + '</b></p>' +
			'<p>Mã Holland gồm 3 nhóm bạn yêu thích nhất, theo thứ tự. Nghề nghiệp phù hợp thường nằm ở chỗ giao nhau của các nhóm này.</p></div></div>',
			'<div class="tnp-section"><h4>3 nhóm sở thích nổi bật</h4><div class="tnp-hcards">' + tops + '</div></div>' +
			'<div class="tnp-section"><h4>Mức độ yêu thích 6 nhóm nghề</h4>' + bars(r.bars) + '</div>',
			'Kết quả mang tính tham khảo cho định hướng nghề nghiệp; hãy kết hợp với năng lực, điều kiện và tư vấn của thầy cô, chuyên gia.'
		));
	};

	Quiz.prototype.renderIq = function (r) {
		var self = this, L = r.label;
		var rank = Math.max(1, Math.round(100 - r.percentile));

		var cats = r.categories.slice().sort(function (a, b) { return b.pct - a.pct; });
		var catHtml = '';
		cats.forEach(function (c) {
			catHtml += '<div class="tnp-cat">' +
				'<div class="tnp-cat-head"><span>' + esc(c.name) + '</span><b class="tnp-lvl tnp-lvl-' + (c.pct >= 80 ? 4 : c.pct >= 60 ? 3 : c.pct >= 40 ? 2 : 1) + '">' + esc(c.level) + '</b></div>' +
				'<div class="tnp-bar"><span style="width:' + Math.max(4, c.pct) + '%"></span></div>' +
				'<p>' + esc(c.about) + '</p>' +
			'</div>';
		});
		var strong = cats[0], weak = cats[cats.length - 1];
		var focus = '';
		if (cats.length > 1 && strong.pct !== weak.pct) {
			focus = '<div class="tnp-focus">' +
				'<div><span>💪 Điểm mạnh nhất</span><b>' + esc(strong.name) + '</b><p>' + esc(strong.about) + '</p></div>' +
				'<div><span>🎯 Nên luyện thêm</span><b>' + esc(weak.name) + '</b><p>' + esc(weak.tip) + '</p></div>' +
			'</div>';
		}

		var list = function (arr) { return '<ul>' + arr.map(function (t) { return '<li>' + esc(t) + '</li>'; }).join('') + '</ul>'; };

		var lv = '';
		r.levels.forEach(function (l, i) {
			lv += '<tr' + (i === L.index ? ' class="is-you"' : '') + '><td>' + esc(l.range) + '</td><td>' + esc(l.name) + (i === L.index ? ' <em>Bạn ở đây</em>' : '') + '</td><td>' + esc(l.share) + '</td></tr>';
		});

		this.html(
			'<div class="tnp-card tnp-result">' +
				'<div class="tnp-result-top">' +
					'<div class="tnp-score"><span>Chỉ số IQ của bạn</span><b>' + r.iq + '</b><em>' + esc(L.name) + '</em></div>' +
					'<div class="tnp-result-text">' +
						'<p class="tnp-pct">Cao hơn khoảng <b>' + r.percentile + '%</b> người cùng độ tuổi</p>' +
						'<p>Nếu xếp 100 người cùng độ tuổi theo điểm IQ, bạn đứng ở khoảng <b>vị trí thứ ' + rank + '</b>.</p>' +
					'</div>' +
				'</div>' +
				'<div class="tnp-section"><h4>Bạn đang ở đâu trên thang IQ?</h4>' + bell(r.iq) + '</div>' +
				'<div class="tnp-section"><h4>Ý nghĩa kết quả: ' + esc(L.name) + '</h4><p>' + esc(L.desc) + '</p>' +
					'<div class="tnp-two">' +
						'<div class="tnp-box"><h5>Đặc điểm thường gặp</h5>' + list(L.traits) + '</div>' +
						'<div class="tnp-box"><h5>Gợi ý phát triển</h5>' + list(L.tips) + '</div>' +
					'</div>' +
				'</div>' +
				(catHtml ? '<div class="tnp-section"><h4>Phân tích theo nhóm năng lực</h4>' + focus + '<div class="tnp-cats">' + catHtml + '</div></div>' : '') +
				'<div class="tnp-section"><h4>Thang xếp loại IQ</h4><table class="tnp-levels"><thead><tr><th>Điểm IQ</th><th>Xếp loại</th><th>Tỷ lệ dân số</th></tr></thead><tbody>' + lv + '</tbody></table></div>' +
				'<p class="tnp-disclaimer">Kết quả online mang tính tham khảo, không thay thế đánh giá lâm sàng bằng bộ công cụ chuẩn (WAIS, Stanford–Binet).</p>' +
				'<div class="tnp-actions"><button type="button" class="tnp-btn" data-act="again">Làm lại bài test</button></div>' +
			'</div>'
		);
		this.root.querySelector('[data-act="again"]').onclick = function () { self.st = null; self.renderIntro(); };
	};

	Quiz.prototype.error = function (msg) {
		var self = this;
		this.html('<div class="tnp-card tnp-intro"><div class="tnp-intro-icon" aria-hidden="true">⚠️</div><p>' + esc(msg) + '</p><div class="tnp-actions"><button type="button" class="tnp-btn" data-act="back">Quay lại</button></div></div>');
		this.root.querySelector('[data-act="back"]').onclick = function () { self.renderIntro(); };
	};

	function mount(el) {
		if (!el.tnpQuiz) { el.tnpQuiz = new Quiz(el); }
		return el.tnpQuiz;
	}
	function boot() {
		document.querySelectorAll('.tnp-iq[data-config]').forEach(mount);
	}
	window.TNPQuiz = { mount: mount };
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
