/* TNP IQ Test – trang sửa câu hỏi */
jQuery(function ($) {
	var $box = $('.tnp-iq-edit');
	if (!$box.length) { return; }
	var cfg = window.tnpIqAdmin || { keys: 'ABCDEFGH'.split(''), min: 2, max: 8 };

	// Chọn / xoá ảnh cho một ô
	$box.on('click', '.tnp-slot-pick', function (e) {
		e.preventDefault();
		var $slot = $(this).closest('.tnp-slot');
		var frame = wp.media({ title: 'Chọn ảnh', button: { text: 'Dùng ảnh này' }, library: { type: 'image' }, multiple: false });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			var url = (a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url);
			$slot.find('input[type=hidden]').val(a.id);
			$slot.find('img').attr('src', url);
			$slot.addClass('has-img');
		});
		frame.open();
	});
	$box.on('click', '.tnp-slot-clear', function (e) {
		e.preventDefault();
		var $slot = $(this).closest('.tnp-slot');
		$slot.find('input[type=hidden]').val(0);
		$slot.find('img').attr('src', '');
		$slot.removeClass('has-img');
	});

	// Kiểu đề bài: đổi nhãn ô ảnh (tuỳ chọn / bắt buộc)
	function syncType() {
		var t = $box.find('input[name=tnp_type]:checked').val() || 'question';
		var $l = $box.find('.tnp-image-label');
		$l.text(t === 'image' ? $l.data('image') : $l.data('question'));
		$box.toggleClass('is-image-type', t === 'image');
	}
	$box.on('change', 'input[name=tnp_type]', syncType);
	syncType();

	// Đáp án: đánh lại chữ cái, tên trường và giá trị “đáp án đúng” theo thứ tự hiện tại
	var $list = $box.find('.tnp-options');
	function renumber() {
		var $rows = $list.children('.tnp-option');
		$rows.each(function (i) {
			var $r = $(this);
			$r.find('.tnp-option-key').text(cfg.keys[i]);
			$r.find('[name^="tnp_options["]').each(function () {
				this.name = this.name.replace(/^tnp_options\[\d+\]/, 'tnp_options[' + i + ']');
			});
			$r.find('input[name=tnp_correct]').val(i);
		});
		$box.find('.tnp-option-add').prop('disabled', $rows.length >= cfg.max);
		$rows.find('.tnp-option-del').prop('disabled', $rows.length <= cfg.min);
	}

	$box.on('click', '.tnp-option-add', function (e) {
		e.preventDefault();
		if ($list.children('.tnp-option').length >= cfg.max) { return; }
		$list.append($($.trim($('#tmpl-tnp-option').html())));
		renumber();
		$list.children('.tnp-option').last().find('input[type=text]').trigger('focus');
	});
	$box.on('click', '.tnp-option-del', function (e) {
		e.preventDefault();
		if ($list.children('.tnp-option').length <= cfg.min) { return; }
		$(this).closest('.tnp-option').remove();
		renumber();
	});
	renumber();

	// Tô sáng đáp án đúng
	$box.on('change', 'input[name=tnp_correct]', function () {
		$list.children('.tnp-option').removeClass('is-correct');
		$(this).closest('.tnp-option').addClass('is-correct');
	});
});

/* Loại bài test: ẩn/hiện "Đáp án đúng" (IQ) hoặc "Nhóm / Điểm" (test tính cách) và đổi danh sách nhóm. */
jQuery(function ($) {
	var $sel = $('#tnp-test');
	if (!$sel.length) { return; }
	var cfg = window.tnpIqAdmin || {};
	function sync() {
		var type = $sel.find(':selected').data('type') || 'iq';
		var dims = (cfg.dims && cfg.dims[type]) || {};
		$('body').toggleClass('tnp-tt-iq', type === 'iq').toggleClass('tnp-tt-trait', type !== 'iq');
		$('.tnp-iq-edit').attr('data-test-type', type);
		$('.tnp-type-name').text($sel.find(':selected').text().replace(/^.*\((.*)\)\s*$/, '$1').replace(/^— Chung.*$/, 'IQ'));
		$('.tnp-type-text').text((cfg.help && cfg.help[type]) || '');
		// Dựng lại danh sách nhóm cho mọi đáp án (kể cả mẫu dùng khi bấm "+ Thêm đáp án"), giữ lựa chọn cũ nếu còn hợp lệ.
		var opts = '<option value="">— Chọn nhóm —</option>';
		$.each(dims, function (k, label) { opts += '<option value="' + k + '">' + $('<span>').text(label).html() + '</option>'; });
		$('select.tnp-dim').each(function () {
			var v = $(this).val() || $(this).attr('data-value') || '';
			$(this).html(opts).val(dims[v] ? v : '');
		});
		var $tpl = $('#tmpl-tnp-option');
		if ($tpl.length) {
			var $t = $('<div>').html($tpl.html());
			$t.find('select.tnp-dim').html(opts);
			$tpl.html($t.html());
		}
	}
	$sel.on('change', sync);
	sync();
});
