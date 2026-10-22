<?php
$file = 'c:\\xampp\\htdocs\\wordpress\\wp-content\\plugins\\traffic-quiz-pro\\assets\\tqp-script.js';
$content = file_get_contents($file);

$search = "        }\r\n\r\n                        questionText = q.question_text;";

// In case line endings are \n instead of \r\n
$search2 = "        }\n\n                        questionText = q.question_text;";

$replace = <<<EOT
        }

        // Time calculation
        const hrs = Math.floor(secondsElapsed / 3600);
        const mins = Math.floor((secondsElapsed % 3600) / 60);
        const secs = secondsElapsed % 60;
        let timeStr;
        if (hrs > 0) {
            timeStr = `\${hrs.toString().padStart(2, '0')}:\${mins.toString().padStart(2, '0')}:\${secs.toString().padStart(2, '0')}`;
        } else {
            timeStr = `\${mins.toString().padStart(2, '0')}:\${secs.toString().padStart(2, '0')}`;
        }

        // Setup Final HTML Result if any
        const finalHtmlEl = document.getElementById('ts-final-result-html');
        if (finalHtmlEl) {
            if (data.final_result_html) {
                finalHtmlEl.innerHTML = data.final_result_html;
                finalHtmlEl.style.display = 'block';
            } else if (data.scoring_method === 'score_range') {
                finalHtmlEl.innerHTML = `<p style="text-align:center; color:#64748b; font-size:15px; margin-top:10px; font-style:italic;">(Chưa có khoảng kết quả nào được thiết lập cho mức điểm này)</p>`;
                finalHtmlEl.style.display = 'block';
            } else if (data.scoring_method === 'personality' && data.score.keywords_tally) {
                let tallyHtml = '<h3>Kết quả nổi bật:</h3><ul>';
                for (const [kw, count] of Object.entries(data.score.keywords_tally)) {
                    tallyHtml += `<li><strong>\${kw}</strong>: \${count} điểm</li>`;
                }
                tallyHtml += '</ul>';
                finalHtmlEl.innerHTML = tallyHtml;
                finalHtmlEl.style.display = 'block';
            } else {
                finalHtmlEl.style.display = 'none';
            }
        }

        // Stats
        const incorrect = data.score.total - data.score.correct;
        resultCorrect().textContent = data.score.correct;
        resultIncorrect().textContent = incorrect;
        resultTime().textContent = timeStr;

        // Hide ring and some stats if not standard
        if (data.scoring_method === 'score_range' || data.scoring_method === 'personality') {
            document.querySelector('.ts-score-ring-wrap').style.display = 'none';
            document.querySelector('.ts-result-stats').style.display = 'none';
            
            const badgeTextEl = document.getElementById('ts-result-badge-text');
            if (badgeTextEl) badgeTextEl.innerHTML = '🎉 Hoàn thành bài test';
            
            const totalScoreTextEl = document.getElementById('ts-result-total-score-text');
            if (totalScoreTextEl) {
                totalScoreTextEl.innerHTML = `Tổng điểm: \${data.score.total_weight}`;
                totalScoreTextEl.style.display = 'inline-block';
            }
            
            const rlEl = document.getElementById('ts-review-list');
            const toggleBtn = document.getElementById('ts-toggle-review-btn');
            if (rlEl && toggleBtn) {
                rlEl.style.display = 'none';
                toggleBtn.style.display = 'inline-block';
                toggleBtn.onclick = function() {
                    if (rlEl.style.display === 'none') {
                        rlEl.style.display = 'block';
                        toggleBtn.innerHTML = 'Ẩn chi tiết ▴';
                    } else {
                        rlEl.style.display = 'none';
                        toggleBtn.innerHTML = 'Hiển thị ▾';
                    }
                };
            }
        } else {
            // Standard
            document.querySelector('.ts-score-ring-wrap').style.display = 'flex';
            if (parseInt(examData.exam.show_answers) !== 0) {
                document.querySelector('.ts-result-stats').style.display = 'grid';
            } else {
                document.querySelector('.ts-result-stats').style.setProperty('display', 'none', 'important');
            }

            const circumference = 2 * Math.PI * 60;
            let pct = data.score.percentage / 100;
            let displayScore = `\${data.score.percentage}%`;
            
            if (data.exam_max_score && data.exam_max_score > 0) {
                pct = Math.min(data.score.earned_score / data.exam_max_score, 1);
                displayScore = `\${data.score.earned_score}/\${data.exam_max_score}`;
                scoreRingNumber().style.fontSize = '24px';
            }
            
            const offset = circumference - (circumference * pct);
            scoreRingNumber().textContent = displayScore;
            
            setTimeout(() => {
                scoreRing().style.strokeDasharray = circumference;
                scoreRing().style.strokeDashoffset = offset;
            }, 100);
        }

        // Render review items (accordion)
        reviewList().innerHTML = '';

        Object.keys(data.results).forEach((qId) => {
            const res = data.results[qId];

            // Find original question data
            let questionText = 'Câu hỏi trắc nghiệm:';
            let optA = '', optB = '', optC = '', optD = '', optE = '', optF = '', optG = '', optH = '';

            examData.groups.forEach((g) => {
                g.questions.forEach((q) => {
                    if (parseInt(q.id, 10) === parseInt(qId, 10)) {
                        questionText = q.question_text;
EOT;

$content = str_replace($search, $replace, $content);
$content = str_replace($search2, $replace, $content);

file_put_contents($file, $content);
echo "Done";
