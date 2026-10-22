<?php
if (!defined('ABSPATH')) exit;
// Lấy danh sách category để filter
global $wpdb;
$table_categories = $wpdb->prefix . 'tq_question_categories';
$categories = $wpdb->get_results("SELECT * FROM $table_categories ORDER BY id DESC");
?>

<div id="tqp-bank-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999;">
    <div style="background:#fff; width:90%; max-width:900px; margin: 50px auto; border-radius: 5px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); display:flex; flex-direction:column; max-height:85vh;">
        
        <div style="padding:15px 20px; border-bottom: 1px solid #ddd; display:flex; justify-content:space-between; align-items:center; background:#f9f9f9;">
            <h2 style="margin:0; font-size:18px;">Ngân hàng câu hỏi</h2>
            <button type="button" class="tqp-close-modal" style="background:none; border:none; font-size:24px; cursor:pointer;">&times;</button>
        </div>
        
        <div style="padding:15px 20px; border-bottom: 1px solid #ddd; display:flex; gap: 10px; align-items:center;">
            <select id="tqp-bank-filter-cat">
                <option value="0">-- Tất cả danh mục --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo esc_attr($cat->id); ?>"><?php echo esc_html($cat->name); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" id="tqp-bank-search" placeholder="Tìm kiếm câu hỏi..." style="flex:1;">
            <button type="button" class="button" id="tqp-bank-btn-search">Tìm</button>
        </div>
        
        <div style="flex:1; overflow-y:auto; padding:0;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:40px; text-align:center;"><input type="checkbox" id="tqp-bank-check-all"></th>
                        <th>Nội dung câu hỏi</th>
                        <th style="width:120px;">Danh mục</th>
                    </tr>
                </thead>
                <tbody id="tqp-bank-results">
                    <tr><td colspan="3" style="text-align:center; padding:20px;">Đang tải...</td></tr>
                </tbody>
            </table>
        </div>
        
        <div style="padding:15px 20px; border-top: 1px solid #ddd; background:#f9f9f9; display:flex; justify-content:space-between; align-items:center;">
            <div>
                Đã chọn: <strong id="tqp-bank-selected-count">0</strong> câu
            </div>
            <div style="display:flex; gap: 10px;">
                <button type="button" class="button tqp-close-modal">Hủy</button>
                <div style="border-left: 1px solid #ccc; height: 30px; margin: 0 5px;"></div>
                <button type="button" class="button button-primary" id="tqp-bank-btn-insert-sync" title="Đồng bộ (Sửa ngân hàng sẽ sửa trong đề thi)">Chèn dạng Liên Kết (Sync)</button>
                <button type="button" class="button button-secondary" id="tqp-bank-btn-insert-copy" title="Copy thành câu mới (Không liên quan đến ngân hàng)">Chèn dạng Sao Chép (Copy)</button>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let currentGroupId = null;
    let bankQuestions = [];
    
    // Mở modal
    $(document).on('click', '.tqp-btn-select-bank', function() {
        currentGroupId = $(this).data('group-id');
        $('#tqp-bank-modal').fadeIn(200);
        loadBankQuestions();
    });
    
    // Đóng modal
    $('.tqp-close-modal').on('click', function() {
        $('#tqp-bank-modal').fadeOut(200);
        currentGroupId = null;
    });
    
    // Lấy câu hỏi qua AJAX
    function loadBankQuestions() {
        $('#tqp-bank-results').html('<tr><td colspan="3" style="text-align:center; padding:20px;">Đang tải...</td></tr>');
        
        let data = {
            action: 'tqp_fetch_bank_questions',
            cat_id: $('#tqp-bank-filter-cat').val(),
            s: $('#tqp-bank-search').val()
        };
        
        $.post(ajaxurl, data, function(response) {
            if(response.success) {
                bankQuestions = response.data;
                renderBankQuestions();
            } else {
                $('#tqp-bank-results').html('<tr><td colspan="3" style="text-align:center; color:red;">Lỗi: ' + response.message + '</td></tr>');
            }
        });
    }
    
    function renderBankQuestions() {
        let html = '';
        if(bankQuestions.length === 0) {
            html = '<tr><td colspan="3" style="text-align:center;">Không tìm thấy câu hỏi nào.</td></tr>';
        } else {
            bankQuestions.forEach(function(q) {
                let catName = q.category_name ? q.category_name : 'Chưa phân loại';
                let shortText = q.question_text.length > 80 ? q.question_text.substring(0,80) + '...' : q.question_text;
                // strip html tags for preview
                let div = document.createElement("div");
                div.innerHTML = shortText;
                let textPreview = div.textContent || div.innerText || "";
                
                html += '<tr>';
                html += '<td style="text-align:center;"><input type="checkbox" class="tqp-bank-item-check" value="' + q.id + '"></td>';
                html += '<td>' + textPreview + '</td>';
                html += '<td>' + catName + '</td>';
                html += '</tr>';
            });
        }
        $('#tqp-bank-results').html(html);
        updateSelectedCount();
    }
    
    // Lọc
    $('#tqp-bank-btn-search').on('click', loadBankQuestions);
    $('#tqp-bank-filter-cat').on('change', loadBankQuestions);
    $('#tqp-bank-search').on('keypress', function(e) {
        if(e.which == 13) { e.preventDefault(); loadBankQuestions(); }
    });
    
    // Check all
    $('#tqp-bank-check-all').on('change', function() {
        $('.tqp-bank-item-check').prop('checked', $(this).prop('checked'));
        updateSelectedCount();
    });
    
    // Check item
    $(document).on('change', '.tqp-bank-item-check', function() {
        updateSelectedCount();
    });
    
    function updateSelectedCount() {
        $('#tqp-bank-selected-count').text($('.tqp-bank-item-check:checked').length);
    }
    
    // Xử lý Chèn (Sync hoặc Copy)
    $('#tqp-bank-btn-insert-sync, #tqp-bank-btn-insert-copy').on('click', function() {
        let isSync = $(this).attr('id') === 'tqp-bank-btn-insert-sync';
        let selectedIds = [];
        $('.tqp-bank-item-check:checked').each(function() {
            selectedIds.push(parseInt($(this).val()));
        });
        
        if (selectedIds.length === 0) {
            alert('Vui lòng chọn ít nhất 1 câu hỏi.');
            return;
        }
        
        // Chèn vào group tương ứng
        if (currentGroupId !== null) {
            // Lấy container của group đó (cần DOM)
            let container = $('.tqp-add-question-btn[data-group-id="' + currentGroupId + '"]').parent().siblings('.tqp-questions-container');
            
            selectedIds.forEach(function(id) {
                let qData = bankQuestions.find(q => parseInt(q.id) === id);
                if (qData) {
                    // Lấy index mới (mô phỏng logic thêm câu hỏi)
                    let qIdx = window.tqpQuestionIndexes[currentGroupId];
                    let tmpl = $('#tmpl-tqp-question').html();
                    tmpl = tmpl.replace(/{{group_idx}}/g, currentGroupId).replace(/{{question_idx}}/g, qIdx);
                    
                    // Xử lý ID (nếu Sync thì giữ ID, nếu Copy thì xóa ID)
                    tmpl = tmpl.replace(/{{question_id}}/g, isSync ? qData.id : '');
                    tmpl = tmpl.replace(/{{badge_display}}/g, isSync ? 'inline' : 'none');
                    
                    let qEl = $(tmpl);
                    
                    // Bơm dữ liệu vào form
                    qEl.find('input[name$="[question_number]"]').val(qIdx + 1);
                    qEl.find('input[name$="[question_text]"]').val(qData.question_text);
                    qEl.find('input[name$="[opt_a]"]').val(qData.opt_a);
                    qEl.find('input[name$="[opt_b]"]').val(qData.opt_b);
                    qEl.find('input[name$="[opt_c]"]').val(qData.opt_c);
                    qEl.find('input[name$="[opt_d]"]').val(qData.opt_d);
                    qEl.find('input[name$="[opt_e]"]').val(qData.opt_e);
                    qEl.find('input[name$="[opt_f]"]').val(qData.opt_f);
                    qEl.find('input[name$="[opt_g]"]').val(qData.opt_g);
                    qEl.find('input[name$="[opt_h]"]').val(qData.opt_h);
                    qEl.find('select[name$="[correct_ans]"]').val(qData.correct_ans);
                    qEl.find('input[name$="[explanation]"]').val(qData.explanation);
                    
                    // Options meta
                    if (qData.options_meta) {
                        try {
                            let meta = JSON.parse(qData.options_meta);
                            ['a','b','c','d','e','f','g','h'].forEach(function(opt) {
                                if (meta['opt_' + opt]) {
                                    qEl.find('input[name$="[meta_' + opt + '_weight]"]').val(meta['opt_' + opt].weight);
                                    qEl.find('input[name$="[meta_' + opt + '_keyword]"]').val(meta['opt_' + opt].keyword);
                                }
                            });
                        } catch(e) {}
                    }
                    
                    // Hiện các tùy chọn nếu có data
                    ['e','f','g','h'].forEach(function(opt) {
                        if (qData['opt_' + opt] && qData['opt_' + opt].trim() !== '') {
                            qEl.find('.tqp-opt-row[data-opt="' + opt + '"]').css('display', 'flex');
                        }
                    });
                    
                    // Nếu là Sync, khóa một số input để tránh sửa nhầm nếu muốn, nhưng yêu cầu là cho sửa và đồng bộ luôn.
                    // Ta chỉ cần add class để nhận diện.

                    container.append(qEl);
                    window.tqpQuestionIndexes[currentGroupId]++;
                }
            });
            
            // Trigger cập nhật hiển thị theo scoring method
            $('#tqp_exam_scoring_method').trigger('change');
        }
        
        $('#tqp-bank-modal').fadeOut(200);
        currentGroupId = null;
    });
});
</script>
