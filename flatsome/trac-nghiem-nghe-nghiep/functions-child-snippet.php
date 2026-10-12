<?php
/* ===== DÁN ĐOẠN DƯỚI ĐÂY VÀO CUỐI functions.php CỦA THEME CON (flatsome-child) =====
 * KHÔNG dán dòng "<?php" ở trên nếu file đã có sẵn "<?php" ở đầu.
 * Chỉ nạp JS trên trang Trắc nghiệm nghề nghiệp (page ID 609).
 */
add_action( 'wp_footer', function () {
	if ( ! is_page( 609 ) ) {
		return;
	}
	?>
<script id="lbn-js">
/* La Bàn Nghề Nghiệp – tracnghiemplus.com/trac-nghiem-nghe-nghiep/
 * Tab khám phá Holland/MBTI + FAQ. Chỉ chạy khi trang có #lbn-explore hoặc .lbn-faq-list.
 */
(function () {
  var HOLLAND_URL = 'https://tracnghiemplus.com/bai-thi/test-holland/';
  var MBTI_URL = 'https://tracnghiemplus.com/bai-thi/test-mbti-mien-phi/';

  function each(list, fn) { Array.prototype.forEach.call(list, fn); }

  function initExplore() {
    var root = document.getElementById('lbn-explore');
    if (!root) return;

    function activateTab(name) {
      each(root.querySelectorAll('[data-tab]'), function (tab) {
        var active = tab.getAttribute('data-tab') === name;
        tab.classList.toggle('active', active);
        tab.setAttribute('aria-selected', String(active));
      });
      document.getElementById('lbn-panel-holland').hidden = name !== 'holland';
      document.getElementById('lbn-panel-mbti').hidden = name !== 'mbti';
    }
    each(root.querySelectorAll('[data-tab]'), function (tab) {
      tab.addEventListener('click', function () { activateTab(tab.getAttribute('data-tab')); });
    });
    each(document.querySelectorAll('[data-open-tab]'), function (link) {
      link.addEventListener('click', function () { activateTab(link.getAttribute('data-open-tab')); });
    });

    var hollandNames = { R: 'Thực tế', I: 'Nghiên cứu', A: 'Nghệ thuật', S: 'Xã hội', E: 'Quản lý', C: 'Nghiệp vụ' };
    var interests = [];
    var hollandResult = document.getElementById('lbn-holland-result');
    var hollandEmpty = hollandResult.innerHTML;
    each(root.querySelectorAll('.lbn-interest'), function (option) {
      option.addEventListener('click', function () {
        var type = option.getAttribute('data-type');
        var index = interests.indexOf(type);
        if (index !== -1) {
          interests.splice(index, 1);
          option.classList.remove('selected');
          option.setAttribute('aria-pressed', 'false');
        } else if (interests.length < 3) {
          interests.push(type);
          option.classList.add('selected');
          option.setAttribute('aria-pressed', 'true');
        } else {
          hollandResult.textContent = 'Bạn đã chọn 3 nhóm. Bỏ chọn một nhóm để thử lựa chọn khác nhé.';
          return;
        }
        if (!interests.length) {
          hollandResult.innerHTML = hollandEmpty;
        } else {
          hollandResult.innerHTML = '✦ Nhóm bạn đang quan tâm: <b>' +
            interests.map(function (t) { return hollandNames[t] + ' (' + t + ')'; }).join(' · ') +
            '</b>. Làm <a href="' + HOLLAND_URL + '">test Holland đầy đủ</a> để biết chính xác mã Holland và nhóm ngành phù hợp.';
        }
      });
    });

    var mbtiChoices = [null, null, null, null];
    var mbtiResult = document.getElementById('lbn-mbti-result');
    each(root.querySelectorAll('.lbn-pair'), function (pair) {
      each(pair.querySelectorAll('button'), function (button) {
        button.addEventListener('click', function () {
          each(pair.querySelectorAll('button'), function (item) {
            item.classList.remove('selected');
            item.setAttribute('aria-pressed', 'false');
          });
          button.classList.add('selected');
          button.setAttribute('aria-pressed', 'true');
          mbtiChoices[Number(pair.getAttribute('data-dimension'))] = button.getAttribute('data-value');
          var remaining = mbtiChoices.filter(function (v) { return !v; }).length;
          mbtiResult.innerHTML = remaining
            ? '✦ Còn <b>' + remaining + ' cặp xu hướng</b> để bạn lựa chọn.'
            : '✦ Mã xu hướng minh họa của bạn: <b>' + mbtiChoices.join('') + '</b>. Đây chỉ là điểm khởi đầu — làm <a href="' + MBTI_URL + '">test MBTI đầy đủ</a> để có kết quả chính xác.';
        });
      });
    });
  }

  function initFaq() {
    each(document.querySelectorAll('.lbn-faq-list'), function (list) {
      each(list.querySelectorAll('.lbn-faq-item > button'), function (button) {
        button.addEventListener('click', function () {
          var item = button.parentNode;
          var willOpen = !item.classList.contains('open');
          each(list.querySelectorAll('.lbn-faq-item'), function (other) {
            other.classList.remove('open');
            other.querySelector('button').setAttribute('aria-expanded', 'false');
          });
          if (willOpen) {
            item.classList.add('open');
            button.setAttribute('aria-expanded', 'true');
          }
        });
      });
    });
  }

  function init() { initExplore(); initFaq(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
</script>
	<?php
}, 100 );
