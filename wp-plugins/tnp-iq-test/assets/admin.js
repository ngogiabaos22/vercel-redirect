/* TNP IQ Test – trang sửa câu hỏi */
jQuery(function ($) {
	var $box = $('.tnp-iq-edit');
	if (!$box.length) { return; }

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

	// Kiểu đề bài
	function syncType() {
		var t = $box.find('input[name=tnp_type]:checked').val() || 'grid';
		$box.find('.tnp-pane').each(function () { $(this).toggle($(this).data('pane') === t); });
	}
	$box.on('change', 'input[name=tnp_type]', syncType);
	syncType();

	// Kích thước lưới: hiện đúng N×N−1 ô
	function syncGrid() {
		var n = parseInt($box.find('.tnp-grid-size').val(), 10) || 3;
		var $g = $box.find('.tnp-grid').css('grid-template-columns', 'repeat(' + n + ', 92px)');
		$g.find('.tnp-slot').not('.tnp-q').each(function (i) { $(this).toggle(i < n * n - 1); });
	}
	$box.on('change', '.tnp-grid-size', syncGrid);
	syncGrid();

	// Tô sáng đáp án đúng
	$box.on('change', 'input[name=tnp_correct]', function () {
		$box.find('.tnp-option').removeClass('is-correct');
		$(this).closest('.tnp-option').addClass('is-correct');
	});
});
