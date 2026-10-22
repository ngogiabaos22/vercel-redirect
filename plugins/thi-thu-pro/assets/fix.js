const fs = require('fs');
const targetFile = 'c:\\xampp\\htdocs\\wordpress\\wp-content\\plugins\\traffic-quiz-pro\\assets\\tqp-script.js';
let content = fs.readFileSync(targetFile, 'utf8');

const regex = /\s*\}\s*<span class="ts-option-shortcut">4<\/span>/;
const match = content.match(regex);
if(match) {
    const targetStr = match[0];
    const correctStr = targetStr.replace('}', `}

            // Passage
            if (group.passage_text) {
                groupHtml += \`<div class="ts-group-passage">\${group.passage_text}</div>\`;
            }

            groupCard.innerHTML = groupHtml;

            // Questions container
            const questionsContainer = document.createElement('div');

            group.questions.forEach((q) => {
                animDelay++;
                allQuestionIds.push(q.id);

                const qBlock = document.createElement('div');
                qBlock.className = 'ts-question-block';
                qBlock.id = \`ts-q-\${q.id}\`;

                let optionsHtml = '';
                let finalQuestionText = q.question_text || 'Chọn đáp án đúng:';
                
                let selectHtml = \`<select class="ts-aptis-select" data-q-id="\${q.id}">
                    <option value="" disabled selected hidden></option>
                    \${q.opt_a ? \`<option value="A">\${q.opt_a}</option>\` : ''}
                    \${q.opt_b ? \`<option value="B">\${q.opt_b}</option>\` : ''}
                    \${q.opt_c ? \`<option value="C">\${q.opt_c}</option>\` : ''}
                    \${q.opt_d ? \`<option value="D">\${q.opt_d}</option>\` : ''}
                    \${q.opt_e ? \`<option value="E">\${q.opt_e}</option>\` : ''}
                    \${q.opt_f ? \`<option value="F">\${q.opt_f}</option>\` : ''}
                    \${q.opt_g ? \`<option value="G">\${q.opt_g}</option>\` : ''}
                    \${q.opt_h ? \`<option value="H">\${q.opt_h}</option>\` : ''}
                </select>\`;

                if (group.group_type === 'text_input') {
                    optionsHtml = \`
                        <div class="ts-text-input-wrap" style="margin-top: 15px;">
                            <input type="text" class="ts-text-input-answer" data-q-id="\${q.id}" placeholder="Nhập đáp án (ví dụ: A)..." maxlength="1" >
                            <div class="ts-text-input-error" id="ts-err-\${q.id}" style="color: #ef4444; font-size: 13px; font-weight: 500; margin-top: 8px; display: none;">Chỉ cho phép nhập một trong các đáp án: A, B, C, D, E, F, G, H</div>
                        </div>
                    \`;
                } else if (group.group_type === 'aptis_fill_blanks') {
                    let inlineSelect = selectHtml.replace('class="ts-aptis-select"', 'class="ts-aptis-select ts-inline-select" style="display: inline-block !important; vertical-align: middle; padding: 4px 28px 4px 8px !important; margin: 0 5px; border: 1px solid #cbd5e1; border-radius: 4px; font-family: inherit; font-size: inherit; color: #0f172a; cursor: pointer; outline: none; width: auto !important; height: auto !important;"');
                    finalQuestionText = finalQuestionText.replace(/_{2,}\\s*\\[?\\d+\\]?\\s*_{2,}/g, inlineSelect);
                } else if (group.group_type === 'aptis_dropdown') {
                    optionsHtml = \`
                    <div class="ts-aptis-dropdown-wrap" style="margin-top: 15px;">
                        \${selectHtml.replace('class="ts-aptis-select"', 'class="ts-aptis-select ts-block-select" style="display: block; box-sizing: border-box; width: auto; max-width: 100%; min-height: 40px; height: auto; line-height: 1.5; padding: 8px 36px 8px 12px !important; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 15px; color: #0f172a; cursor: pointer; outline: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"')}
                    </div>
                    \`;
                } else if (group.group_type === 'writing') {
                    optionsHtml = \`
                        <div class="ts-writing-wrap" style="margin-top: 15px;">
                            <textarea class="ts-writing-answer" data-q-id="\${q.id}" placeholder="Nhập câu trả lời của bạn..." rows="8" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px; font-family: inherit; font-size: 15px; outline: none; resize: vertical; box-sizing: border-box;"></textarea>
                            <div class="ts-writing-wordcount" id="ts-wc-\${q.id}" style="text-align: right; font-size: 13px; color: #64748b; margin-top: 5px; font-weight: 500;">Words 0</div>
                        </div>
                    \`;
                } else {
                    optionsHtml = \`
                    <div class="ts-options-list">
                        <div class="ts-option-item" data-q-id="\${q.id}" data-val="A">
                            <span class="ts-option-radio"></span>
                            <span class="ts-option-letter">A.</span>
                            <span class="ts-option-text">\${q.opt_a}</span>
                            <span class="ts-option-shortcut">1</span>
                        </div>
                        <div class="ts-option-item" data-q-id="\${q.id}" data-val="B">
                            <span class="ts-option-radio"></span>
                            <span class="ts-option-letter">B.</span>
                            <span class="ts-option-text">\${q.opt_b}</span>
                            <span class="ts-option-shortcut">2</span>
                        </div>
                        \${q.opt_c && q.opt_c.trim() ? \`
                        <div class="ts-option-item" data-q-id="\${q.id}" data-val="C">
                            <span class="ts-option-radio"></span>
                            <span class="ts-option-letter">C.</span>
                            <span class="ts-option-text">\${q.opt_c}</span>
                            <span class="ts-option-shortcut">3</span>
                        </div>
                        \` : ''}
                        \${q.opt_d && q.opt_d.trim() ? \`
                        <div class="ts-option-item" data-q-id="\${q.id}" data-val="D">
                            <span class="ts-option-radio"></span>
                            <span class="ts-option-letter">D.</span>
                            <span class="ts-option-text">\${q.opt_d}</span>`);

    content = content.replace(targetStr, correctStr);
    fs.writeFileSync(targetFile, content);
    console.log("Success");
} else {
    console.log("Failed to match regex");
}
