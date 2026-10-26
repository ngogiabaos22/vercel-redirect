/* TNP IQ Test – giao diện làm bài */
(function () {
	'use strict';

	var STORE = 'tnp_iq_state_v2';

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function fmt(sec) {
		sec = Math.max(0, Math.round(sec));
		return Math.floor(sec / 60) + ':' + ('0' + (sec % 60)).slice(-2);
	}
	// Mỗi bộ đề lưu bài đang làm riêng, để nhiều bài test trên site không lẫn nhau.
	function key(test) { return STORE + ':' + (test || ''); }
	function load(test) {
		try { return JSON.parse(sessionStorage.getItem(key(test)) || 'null'); } catch (e) { return null; }
	}
	function save(st) {
		try { sessionStorage.setItem(key(st.test), JSON.stringify(st)); } catch (e) { /* bỏ qua */ }
	}
	function clear(test) {
		try { sessionStorage.removeItem(key(test)); } catch (e) { /* bỏ qua */ }
	}

	function Quiz(root) {
		this.root = root;
		this.cfg = JSON.parse(root.getAttribute('data-config') || '{}');
		this.st = null;
		this.timer = null;
		this.renderIntro();
	}

	Quiz.prototype.html = function (h) {
		this.root.innerHTML = h;
		var top = this.root.getBoundingClientRect().top;
		if (top < 0) { window.scrollBy(0, top - 90); }
	};

	/* ---------- Màn hình bắt đầu ---------- */
	Quiz.prototype.renderIntro = function () {
		var c = this.cfg, saved = load(c.test), self = this;
		var resume = saved && saved.api === c.api && saved.stage !== 'done' && !this.timeUp(saved);
		this.html(
			'<div class="tnp-card tnp-intro">' +
				'<div class="tnp-intro-icon" aria-hidden="true">🧠</div>' +
				'<h2>' + esc(c.introTitle) + '</h2>' +
				'<div class="tnp-intro-meta">' +
					'<span><b>' + esc(c.questions) + '</b> câu hỏi</span>' +
					(c.minutes ? '<span>Tối đa <b>' + esc(c.minutes) + '</b> phút</span>' : '<span>⏱ Có tính giờ</span>') +
				'</div>' +
				'<p>' + esc(c.introText) + '</p>' +
				'<div class="tnp-actions">' +
					(resume ? '<button type="button" class="tnp-btn" data-act="resume">Tiếp tục bài đang làm (câu ' + (saved.index + 1) + ')</button>' : '') +
					'<button type="button" class="tnp-btn' + (resume ? ' tnp-btn-ghost' : '') + '" data-act="start">' + (resume ? 'Làm bài mới' : 'Bắt đầu làm bài') + '</button>' +
				'</div>' +
			'</div>'
		);
		this.root.querySelector('[data-act="start"]').onclick = function () { self.start(this); };
		var r = this.root.querySelector('[data-act="resume"]');
		if (r) { r.onclick = function () { self.st = saved; self.continue(); }; }
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
					answers: {}, index: 0, startedAt: Date.now(), finishedAt: null, stage: 'quiz'
				};
				save(self.st);
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
		if (this.st.stage === 'age' || this.timeUp(this.st)) { this.renderAge(this.timeUp(this.st) && !this.st.finishedAt); }
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

		if (q.type === 'grid') {
			body += '<div class="tnp-grid" style="--cols:' + q.grid + '">';
			q.cells.forEach(function (u) { body += '<div class="tnp-cell"><img src="' + esc(u) + '" alt=""></div>'; });
			body += '<div class="tnp-cell tnp-missing">?</div></div>';
		} else if (q.type === 'image') {
			body += '<div class="tnp-qimg"><img src="' + esc(q.image) + '" alt=""></div>';
		}

		var opts = '<div class="tnp-opts">';
		q.options.forEach(function (o) {
			var sel = st.answers[q.id] === o.key;
			opts += '<button type="button" class="tnp-opt' + (sel ? ' is-selected' : '') + (o.img ? '' : ' is-text') + '" data-key="' + esc(o.key) + '" aria-pressed="' + sel + '">' +
				'<span class="tnp-opt-key">' + esc(o.key) + '</span>' +
				(o.img ? '<span class="tnp-opt-img"><img src="' + esc(o.img) + '" alt="Đáp án ' + esc(o.key) + '"></span>' : '') +
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
					'<span class="tnp-timer" title="Thời gian đã làm"></span>' +
				'</div>' +
				'<div class="tnp-bar"><span style="width:' + Math.round(answered / n * 100) + '%"></span></div>' +
				'<h3 class="tnp-qtitle"><span>' + (st.index + 1) + '.</span> ' + esc(q.prompt) + '</h3>' +
				body + opts +
				'<div class="tnp-nav">' +
					'<button type="button" class="tnp-btn tnp-btn-ghost" data-act="prev"' + (st.index === 0 ? ' disabled' : '') + '>← Câu trước</button>' +
					(last
						? '<button type="button" class="tnp-btn" data-act="finish">Nộp bài</button>'
						: '<button type="button" class="tnp-btn" data-act="next">Câu tiếp →</button>') +
				'</div>' +
				dots +
			'</div>'
		);

		this.root.querySelectorAll('.tnp-opt').forEach(function (b) {
			b.onclick = function () {
				st.answers[q.id] = b.getAttribute('data-key');
				save(st);
				self.root.querySelectorAll('.tnp-opt').forEach(function (x) {
					x.classList.toggle('is-selected', x === b);
					x.setAttribute('aria-pressed', x === b);
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
	};

	Quiz.prototype.go = function (i) {
		if (i < 0 || i >= this.st.questions.length) { return; }
		this.st.index = i;
		save(this.st);
		this.renderQuestion();
	};

	Quiz.prototype.finishQuestions = function (timeout) {
		clearInterval(this.timer);
		if (!this.st.finishedAt) { this.st.finishedAt = Date.now(); }
		this.st.stage = 'age';
		save(this.st);
		this.renderAge(timeout);
	};

	/* ---------- Bước cuối: nhập tuổi ---------- */
	Quiz.prototype.renderAge = function (timeout) {
		var self = this, st = this.st;
		clearInterval(this.timer);
		if (!st.finishedAt) { st.finishedAt = st.limit ? Math.min(Date.now(), st.startedAt + st.limit * 1000) : Date.now(); st.stage = 'age'; save(st); }
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
			save(st);
			self.submit(form.querySelector('button'));
		};
	};

	Quiz.prototype.submit = function (btn) {
		var self = this, st = this.st;
		btn.disabled = true;
		btn.textContent = 'Đang chấm điểm…';
		fetch(this.cfg.api + '/submit', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				token: st.token,
				answers: st.answers,
				age: st.age,
				elapsed: Math.round((st.finishedAt - st.startedAt) / 1000)
			})
		})
			.then(function (r) { return r.json().then(function (d) { if (!r.ok) { throw d; } return d; }); })
			.then(function (res) {
				st.stage = 'done';
				clear(st.test);
				self.renderResult(res);
			})
			.catch(function (e) {
				btn.disabled = false;
				btn.textContent = 'Thử lại';
				var err = self.root.querySelector('.tnp-err');
				err.textContent = (e && e.message) || 'Không gửi được bài. Kiểm tra kết nối mạng rồi thử lại.';
				err.hidden = false;
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

	function boot() {
		document.querySelectorAll('.tnp-iq[data-config]').forEach(function (el) {
			if (!el.tnpQuiz) { el.tnpQuiz = new Quiz(el); }
		});
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
