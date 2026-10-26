/* TNP IQ Test – giao diện làm bài */
(function () {
	'use strict';

	var STORE = 'tnp_iq_state_v1';

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function fmt(sec) {
		sec = Math.max(0, Math.round(sec));
		return Math.floor(sec / 60) + ':' + ('0' + (sec % 60)).slice(-2);
	}
	function load() {
		try { return JSON.parse(sessionStorage.getItem(STORE) || 'null'); } catch (e) { return null; }
	}
	function save(st) {
		try { sessionStorage.setItem(STORE, JSON.stringify(st)); } catch (e) { /* bỏ qua */ }
	}
	function clear() {
		try { sessionStorage.removeItem(STORE); } catch (e) { /* bỏ qua */ }
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
		var c = this.cfg, saved = load(), self = this;
		var resume = saved && saved.api === c.api && saved.stage !== 'done' && !this.timeUp(saved);
		this.html(
			'<div class="tnp-card tnp-intro">' +
				'<div class="tnp-intro-icon" aria-hidden="true">🧠</div>' +
				'<h2>' + esc(c.introTitle) + '</h2>' +
				'<div class="tnp-intro-meta">' +
					'<span><b>' + esc(c.questions) + '</b> câu hỏi</span>' +
					(c.minutes ? '<span><b>' + esc(c.minutes) + '</b> phút</span>' : '<span>Không giới hạn thời gian</span>') +
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
		if (c.count !== null && c.count !== undefined) { q.push('count=' + encodeURIComponent(c.count)); }
		if (c.time !== null && c.time !== undefined) { q.push('time=' + encodeURIComponent(c.time)); }
		btn.disabled = true;
		btn.textContent = 'Đang tải đề…';
		fetch(c.api + '/start' + (q.length ? '?' + q.join('&') : ''), { credentials: 'same-origin' })
			.then(function (r) { return r.json().then(function (d) { if (!r.ok) { throw d; } return d; }); })
			.then(function (d) {
				self.st = {
					api: c.api, token: d.token, questions: d.questions, limit: d.timeLimit,
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

	/* ---------- Đồng hồ ---------- */
	Quiz.prototype.remaining = function (st) {
		st = st || this.st;
		return st.limit ? st.limit - (Date.now() - st.startedAt) / 1000 : Infinity;
	};
	Quiz.prototype.timeUp = function (st) { return this.remaining(st) <= 0; };

	Quiz.prototype.tick = function () {
		var self = this;
		clearInterval(this.timer);
		if (!this.st.limit) { return; }
		var el = this.root.querySelector('.tnp-timer');
		var update = function () {
			var left = self.remaining();
			if (el) {
				el.textContent = '⏱ ' + fmt(left);
				el.classList.toggle('is-low', left <= 60);
			}
			if (left <= 0) { clearInterval(self.timer); self.finishQuestions(true); }
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
					(st.limit ? '<span class="tnp-timer"></span>' : '') +
				'</div>' +
				'<div class="tnp-bar"><span style="width:' + Math.round(answered / n * 100) + '%"></span></div>' +
				'<h3 class="tnp-qtitle"><span>' + (st.index + 1) + '.</span> ' + esc(q.prompt) + '</h3>' +
				body + opts +
				'<div class="tnp-nav">' +
					'<button type="button" class="tnp-btn tnp-btn-ghost" data-act="prev"' + (st.index === 0 ? ' disabled' : '') + '>← Câu trước</button>' +
					(last
						? '<button type="button" class="tnp-btn" data-act="finish">Nộp bài</button>'
						: '<button type="button" class="tnp-btn" data-act="next">' + (st.answers[q.id] ? 'Câu tiếp →' : 'Bỏ qua →') + '</button>') +
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
		if (!st.finishedAt) { st.finishedAt = st.startedAt + st.limit * 1000; st.stage = 'age'; save(st); }
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
				clear();
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
	Quiz.prototype.renderResult = function (r) {
		var self = this, qs = this.st.questions, byId = {};
		qs.forEach(function (q) { byId[q.id] = q; });

		var cats = '';
		r.categories.forEach(function (c) {
			var p = c.total ? Math.round(c.correct / c.total * 100) : 0;
			cats += '<div class="tnp-cat"><div class="tnp-cat-head"><span>' + esc(c.name) + '</span><b>' + c.correct + '/' + c.total + '</b></div>' +
				'<div class="tnp-bar"><span style="width:' + p + '%"></span></div></div>';
		});

		var review = '';
		if (r.review && r.review.length) {
			review = '<details class="tnp-review"><summary>Xem lại đáp án (' + r.correct + '/' + r.total + ' câu đúng)</summary>';
			r.review.forEach(function (it, i) {
				var q = byId[it.id];
				if (!q) { return; }
				var right = q.options.filter(function (o) { return o.key === it.correct; })[0] || {};
				review += '<div class="tnp-rv ' + (it.ok ? 'is-ok' : 'is-bad') + '">' +
					'<div class="tnp-rv-head"><span class="tnp-rv-num">' + (i + 1) + '</span><span>' + esc(q.prompt) + '</span><span class="tnp-rv-mark">' + (it.ok ? '✓' : '✗') + '</span></div>' +
					'<div class="tnp-rv-body">' +
						(right.img ? '<img src="' + esc(right.img) + '" alt="">' : '') +
						'<div><div>Bạn chọn: <b>' + (it.your || 'Bỏ trống') + '</b> · Đáp án đúng: <b>' + esc(it.correct) + '</b>' + (right.text ? ' (' + esc(right.text) + ')' : '') + '</div>' +
						(it.explain ? '<div class="tnp-rv-ex">' + esc(it.explain) + '</div>' : '') + '</div>' +
					'</div></div>';
			});
			review += '</details>';
		}

		var cta = this.cfg.ctaText && this.cfg.ctaUrl ? '<a class="tnp-btn tnp-btn-ghost" href="' + esc(this.cfg.ctaUrl) + '">' + esc(this.cfg.ctaText) + '</a>' : '';

		this.html(
			'<div class="tnp-card tnp-result">' +
				'<div class="tnp-result-top">' +
					'<div class="tnp-score"><span>Chỉ số IQ của bạn</span><b>' + r.iq + '</b><em>' + esc(r.label.name) + '</em></div>' +
					'<div class="tnp-result-text">' +
						'<p class="tnp-pct">Cao hơn khoảng <b>' + r.percentile + '%</b> dân số</p>' +
						'<p>' + esc(r.label.desc) + '</p>' +
						'<div class="tnp-scale"><span style="left:' + Math.max(2, Math.min(98, (r.iq - 55) / 90 * 100)) + '%"></span></div>' +
						'<div class="tnp-scale-lbl">' + [70, 85, 100, 115, 130].map(function (v) { return '<span style="left:' + ((v - 55) / 90 * 100) + '%">' + v + '</span>'; }).join('') + '</div>' +
					'</div>' +
				'</div>' +
				'<div class="tnp-facts">' +
					'<div><b>' + r.correct + '/' + r.total + '</b><span>Câu đúng</span></div>' +
					'<div><b>' + fmt(r.elapsed) + '</b><span>Thời gian</span></div>' +
					'<div><b>' + r.age + '</b><span>Tuổi (nhóm ' + esc(r.ageGroup) + ')</span></div>' +
					(r.bonus ? '<div><b>+' + r.bonus + '</b><span>Thưởng tốc độ</span></div>' : '') +
				'</div>' +
				(cats ? '<h4>Theo nhóm năng lực</h4><div class="tnp-cats">' + cats + '</div>' : '') +
				review +
				'<p class="tnp-disclaimer">Kết quả online mang tính tham khảo, không thay thế đánh giá lâm sàng bằng bộ công cụ chuẩn (WAIS, Stanford–Binet).</p>' +
				'<div class="tnp-actions"><button type="button" class="tnp-btn" data-act="again">Làm lại bài test</button>' + cta + '</div>' +
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
