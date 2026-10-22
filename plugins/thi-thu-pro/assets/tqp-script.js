/**
 * TestSpace — SPA Engine
 * Vanilla JS (ES6) — Minimalism Exam Interface
 */
document.addEventListener('DOMContentLoaded', () => {
    // CAPTURE TRAFFIC SOURCE
    function captureTrafficSource() {
        if (!sessionStorage.getItem('tqp_traffic_source')) {
            const urlParams = new URLSearchParams(window.location.search);
            const sourceData = {
                utm_source: urlParams.get('utm_source') || '',
                utm_medium: urlParams.get('utm_medium') || '',
                utm_campaign: urlParams.get('utm_campaign') || '',
                utm_term: urlParams.get('utm_term') || '',
                utm_content: urlParams.get('utm_content') || '',
                click_id: urlParams.get('gclid') || urlParams.get('fbclid') || urlParams.get('ttclid') || '',
                referrer: document.referrer || ''
            };
            sessionStorage.setItem('tqp_traffic_source', JSON.stringify(sourceData));
        }
    }
    captureTrafficSource();

    const appRoot = document.getElementById('ts-app-root');
    if (!appRoot) return;

    let examId = parseInt(appRoot.dataset.examId, 10);

    // DOM Elements — resolved lazily inside the workspace container
    // (workspace is hidden until the lobby start button is clicked)
    const getEl = (id) => document.getElementById(id);

    // Shorthand getters for workspace elements (resolved when needed)
    const loader = () => getEl('ts-loader');
    const examWrap = () => getEl('ts-exam-wrap');
    const questionsArea = () => getEl('ts-questions-area');
    const paletteGrid = () => getEl('ts-palette-grid');
    const timerDisplay = () => getEl('ts-timer-display');
    const timerBarFill = () => getEl('ts-timer-bar-fill');
    const headerProgressFill = () => getEl('ts-header-progress-fill');
    const headerProgressText = () => getEl('ts-header-progress');
    const submitBtn = () => getEl('ts-submit-btn');
    const statAnswered = () => getEl('ts-stat-answered');
    const statUnanswered = () => getEl('ts-stat-unanswered');
    const statFlagged = () => getEl('ts-stat-flagged');
    const lockModal = () => getEl('ts-lock-modal');
    const unlockSubmitBtnTop = () => getEl('ts-unlock-submit-btn-top');
    const unlockCodeInputTop = () => getEl('ts-unlock-code-input-top');
    const unlockErrorTop = () => getEl('ts-unlock-error-top');
    const unlockSubmitBtnBottom = () => getEl('ts-unlock-submit-btn-bottom');
    const unlockCodeInputBottom = () => getEl('ts-unlock-code-input-bottom');
    const unlockErrorBottom = () => getEl('ts-unlock-error-bottom');
    const taskTagName = () => getEl('ts-task-tag-name');
    const resultContainer = () => getEl('ts-result-container');
    const resultCorrect = () => getEl('ts-result-correct');
    const resultIncorrect = () => getEl('ts-result-incorrect');
    const resultTime = () => getEl('ts-result-time');
    const scoreRing = () => getEl('ts-score-ring');
    const scoreRingNumber = () => getEl('ts-score-ring-number');
    const reviewList = () => getEl('ts-review-list');

    // State
    let examData = null;
    let userAnswers = {};
    let flaggedQuestions = {};
    let allQuestionIds = [];
    let timerInterval = null;
    let totalSeconds = 0;
    let remainingSeconds = 0;
    let secondsElapsed = 0;
    let totalQuestionsCount = 0;
    let quizSubmitted = false;

    // Helper function to safely navigate back (Programmatic Exit)
    function goBackToPortalOrHome() {
        const params = new URLSearchParams(window.location.search);
        if (params.has('exam_id')) {
            // URL chứa tham số exam_id -> Quay về trang chủ sạch (xóa bỏ query params)
            window.location.href = window.location.origin + window.location.pathname;
        } else {
            // Đang ở trang shortcode -> Tải lại trang để về màn hình Lobby mặc định
            window.location.reload();
        }
    }

    // Helper to navigate to the main WordPress home (Brand Logo Click)
    function goToMainHome() {
        if (typeof tqp_vars !== 'undefined' && tqp_vars.home_url) {
            window.location.href = tqp_vars.home_url;
        } else {
            window.location.href = window.location.origin + window.location.pathname;
        }
    }

    // ============================================================
    // LOAD EXAM
    // ============================================================
    async function loadExam() {
        try {
            const response = await fetch(`${tqp_vars.api_url}exam/${examId}?_t=${Date.now()}`, {
                headers: { 'X-WP-Nonce': tqp_vars.nonce }
            });
            const data = await response.json();

            if (data.success) {
                examData = data;
                initExamUI();
            } else {
                loader().innerHTML = `<p style="color:#ef4444;font-weight:600;font-size:14px;">Lỗi: ${data.message || 'Không tìm thấy dữ liệu đề thi'}</p>`;
            }
        } catch (error) {
            console.error('TestSpace API Error:', error);
            loader().innerHTML = `<p style="color:#ef4444;font-weight:600;font-size:14px;">Lỗi kết nối máy chủ! Vui lòng thử lại sau.</p>`;
        }
    }

    // ============================================================
    // INIT EXAM UI
    // ============================================================
    function initExamUI() {
        document.body.classList.add('ts-exam-active');
        loader().style.display = 'none';
        examWrap().style.display = 'block';

        // Set exam title & metadata in SPA Header
        const headerTitleEl = getEl('ts-header-exam-title');
        if (headerTitleEl) {
            headerTitleEl.textContent = examData.exam.title;
        }

        // Tạo sticky audio player nếu có audio_url
        if (examData.exam.audio_url) {
            let stickyAudio = document.getElementById('ts-sticky-global-audio');
            if (!stickyAudio) {
                stickyAudio = document.createElement('div');
                stickyAudio.id = 'ts-sticky-global-audio';
                stickyAudio.style.cssText = 'position: sticky; top: 70px; z-index: 1000; padding: 10px; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; text-align: center; border-radius: 8px; border: 1px solid #eee;';

                // Nếu người dùng nhập HTML (ví dụ <iframe>) thì in ra HTML, ngược lại nếu là URL thì tạo thẻ <audio>
                const audioContent = examData.exam.audio_url.trim();
                if (audioContent.startsWith('http')) {
                    stickyAudio.innerHTML = `<audio controls style="width: 100%; outline: none;"><source src="${audioContent}"></audio>`;
                } else {
                    stickyAudio.innerHTML = audioContent;
                }

                // Chèn vào phía trên khung nội dung bài thi
                const questionsWrap = getEl('ts-questions-area');
                if (questionsWrap && questionsWrap.parentElement) {
                    questionsWrap.parentElement.insertBefore(stickyAudio, questionsWrap);
                }
            }
        }

        // Count total questions
        totalQuestionsCount = 0;
        examData.groups.forEach(g => {
            totalQuestionsCount += g.questions.length;
        });

        if (headerProgressText()) {
            headerProgressText().textContent = `0 / ${totalQuestionsCount}`;
        }
        statUnanswered().textContent = totalQuestionsCount;

        // Setup timer
        totalSeconds = examData.exam.duration_minutes * 60;
        remainingSeconds = totalSeconds;
        updateTimerDisplay();
        startTimer();

        // Render questions
        renderQuestions();

        // Tự động duyệt ngược từ sidebar lên thẻ body để buộc overflow: visible cho position: sticky hoạt động 100%
        setTimeout(() => {
            let parent = document.querySelector('.ts-sticky-sidebar');
            while (parent && parent !== document.body) {
                parent.style.setProperty('overflow', 'visible', 'important');
                parent = parent.parentElement;
            }
        }, 100);

        // Submit button listener
        if (submitBtn()) submitBtn().addEventListener('click', promptSubmit);
        const mobileSubmitBtn = document.getElementById('ts-mobile-submit-btn');
        if (mobileSubmitBtn) mobileSubmitBtn.addEventListener('click', promptSubmit);

        // Scroll to palette from mobile sticky bar
        const mobileStatAnswered = document.getElementById('ts-mobile-stat-answered-block');
        if (mobileStatAnswered) {
            mobileStatAnswered.addEventListener('click', () => {
                const paletteSection = document.querySelector('.ts-palette-section');
                if (paletteSection) {
                    const offsetTop = paletteSection.getBoundingClientRect().top + window.scrollY - 100;
                    window.scrollTo({ top: offsetTop, behavior: 'smooth' });
                }
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', handleKeyboardShortcut);
    }

    // ============================================================
    // RENDER QUESTIONS
    // ============================================================
    function renderQuestions() {
        questionsArea().innerHTML = '';
        paletteGrid().innerHTML = '';
        allQuestionIds = [];

        let animDelay = 0;

        examData.groups.forEach((group, groupIndex) => {
            const groupCard = document.createElement('div');
            groupCard.className = 'ts-group-card';
            groupCard.style.animationDelay = `${animDelay * 0.05}s`;

            // Group instruction
            let groupHtml = `<div class="ts-group-instruction">

                ${group.instruction || 'Làm bài trắc nghiệm'}
            </div>`;

            // Audio
            if (group.media_url) {
                groupHtml += `<audio controls class="ts-group-audio">
                    <source src="${group.media_url}" type="audio/mpeg">
                    Trình duyệt của bạn không hỗ trợ phát Audio.
                </audio>`;
            }

            // Passage
            if (group.passage_text) {
                groupHtml += `<div class="ts-group-passage">${group.passage_text}</div>`;
            }

            groupCard.innerHTML = groupHtml;

            // Questions container
            const questionsContainer = document.createElement('div');

            group.questions.forEach((q) => {
                animDelay++;
                allQuestionIds.push(q.id);

                const qBlock = document.createElement('div');
                qBlock.className = 'ts-question-block';
                qBlock.id = `ts-q-${q.id}`;

                let optionsHtml = '';
                let finalQuestionText = q.question_text || 'Chọn đáp án đúng:';

                let selectHtml = `<select class="ts-aptis-select" data-q-id="${q.id}">
                    <option value="" disabled selected hidden></option>
                    ${q.opt_a ? `<option value="A">${q.opt_a}</option>` : ''}
                    ${q.opt_b ? `<option value="B">${q.opt_b}</option>` : ''}
                    ${q.opt_c ? `<option value="C">${q.opt_c}</option>` : ''}
                    ${q.opt_d ? `<option value="D">${q.opt_d}</option>` : ''}
                    ${q.opt_e ? `<option value="E">${q.opt_e}</option>` : ''}
                    ${q.opt_f ? `<option value="F">${q.opt_f}</option>` : ''}
                    ${q.opt_g ? `<option value="G">${q.opt_g}</option>` : ''}
                    ${q.opt_h ? `<option value="H">${q.opt_h}</option>` : ''}
                </select>`;

                if (group.group_type === 'text_input') {
                    optionsHtml = `
                        <div class="ts-text-input-wrap" style="margin-top: 15px;">
                            <input type="text" class="ts-text-input-answer" data-q-id="${q.id}" placeholder="Nhập đáp án...">
                        </div>
                    `;
                } else if (group.group_type === 'aptis_fill_blanks') {
                    let inlineSelect = selectHtml.replace('class="ts-aptis-select"', 'class="ts-aptis-select ts-inline-select" style="display: inline-block !important; vertical-align: middle; padding: 4px 28px 4px 8px !important; margin: 0 5px; border: 1px solid #cbd5e1; border-radius: 4px; font-family: inherit; font-size: inherit; color: #0f172a; cursor: pointer; outline: none; width: auto !important; height: auto !important;"');
                    finalQuestionText = finalQuestionText.replace(/_{2,}\s*\[?\d+\]?\s*_{2,}/g, inlineSelect);
                } else if (group.group_type === 'aptis_dropdown') {
                    optionsHtml = `
                    <div class="ts-aptis-dropdown-wrap" style="margin-top: 15px;">
                        ${selectHtml.replace('class="ts-aptis-select"', 'class="ts-aptis-select ts-block-select" style="display: block; box-sizing: border-box; width: auto; max-width: 100%; min-height: 40px; height: auto; line-height: 1.5; padding: 8px 36px 8px 12px !important; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 15px; color: #0f172a; cursor: pointer; outline: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"')}
                    </div>
                    `;
                } else if (group.group_type === 'writing') {
                    optionsHtml = `
                        <div class="ts-writing-wrap" style="margin-top: 15px;">
                            <textarea class="ts-writing-answer" data-q-id="${q.id}" placeholder="Nhập câu trả lời của bạn..." style="width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px; font-family: inherit; font-size: 15px; outline: none; resize: none; overflow: hidden; min-height: 150px; box-sizing: border-box;"></textarea>
                            <div class="ts-writing-wordcount" id="ts-wc-${q.id}" style="text-align: right; font-size: 13px; color: #64748b; margin-top: 5px; font-weight: 500;">Words 0</div>
                        </div>
                    `;
                } else if (group.group_type === 'speaking') {
                    let timeLimit = 120;
                    if (group.instruction) {
                        let inst = group.instruction.toLowerCase();
                        if (inst.includes('part 1')) timeLimit = 30;
                        else if (inst.includes('part 2')) timeLimit = 45;
                        else if (inst.includes('part 3')) timeLimit = 45;
                        else if (inst.includes('part 4')) timeLimit = 120;
                    }

                    optionsHtml = `
                        <div class="ts-speaking-wrap" style="margin-top: 15px; padding: 20px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; text-align: center; position: relative;" data-q-id="${q.id}" data-time-limit="${timeLimit}">
                            
                            <div style="text-align: right; margin-bottom: 8px;">
                                <button type="button" class="ts-btn-toggle-draft" style="background: transparent; border: none; color: #3b82f6; padding: 0; font-size: 13px; font-weight: 500; cursor: pointer; text-decoration: underline;">Giấy nháp</button>
                            </div>
                            
                            <div class="ts-speaking-draft-wrap" style="display: none; margin-bottom: 15px; text-align: left;">
                                <textarea placeholder="Nhập kịch bản hoặc dàn ý của bạn vào đây..." style="width: 100%; min-height: 120px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 14px; outline: none; resize: vertical; box-sizing: border-box; background: #fff;"></textarea>
                            </div>

                            <div class="ts-speaking-status" style="font-size: 16px; font-weight: 600; color: #475569; margin-bottom: 15px; min-height: 24px;"></div>
                            
                            <div class="ts-speaking-controls">
                                <button type="button" class="ts-btn-start-record" style="background: #3b82f6; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; display: inline-block;">Bắt đầu ghi âm</button>
                                <button type="button" class="ts-btn-stop-record" style="background: #ef4444; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; display: none;">Dừng ghi âm</button>
                                <button type="button" class="ts-btn-retry-record" style="background: #64748b; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; display: none; margin-left: 10px;">Ghi âm lại</button>
                            </div>
                            
                            <div class="ts-speaking-player-wrap" style="margin-top: 15px; display: none;">
                                <audio class="ts-speaking-player" controls style="width: 100%; max-width: 300px; outline: none;"></audio>
                            </div>
                        </div>
                    `;
                } else {
                    optionsHtml = `
                    <div class="ts-options-list">
                        <div class="ts-option-item" data-q-id="${q.id}" data-val="A">
                            <span class="ts-option-radio"></span>
                            <span class="ts-option-letter">A.</span>
                            <span class="ts-option-text">${q.opt_a}</span>
                            <span class="ts-option-shortcut">1</span>
                        </div>
                        <div class="ts-option-item" data-q-id="${q.id}" data-val="B">
                            <span class="ts-option-radio"></span>
                            <span class="ts-option-letter">B.</span>
                            <span class="ts-option-text">${q.opt_b}</span>
                            <span class="ts-option-shortcut">2</span>
                        </div>
                        ${q.opt_c && q.opt_c.trim() ? `
                        <div class="ts-option-item" data-q-id="${q.id}" data-val="C">
                            <span class="ts-option-radio"></span>
                            <span class="ts-option-letter">C.</span>
                            <span class="ts-option-text">${q.opt_c}</span>
                            <span class="ts-option-shortcut">3</span>
                        </div>
                        ` : ''}
                        ${q.opt_d && q.opt_d.trim() ? `
                        <div class="ts-option-item" data-q-id="${q.id}" data-val="D">
                            <span class="ts-option-radio"></span>
                            <span class="ts-option-letter">D.</span>
                            <span class="ts-option-text">${q.opt_d}</span>
                            <span class="ts-option-shortcut">4</span>
                        </div>
                        ` : ''}
                        ${q.opt_e && q.opt_e.trim() ? `
                        <div class="ts-option-item" data-q-id="${q.id}" data-val="E">
                            <span class="ts-option-radio"></span>
                            <span class="ts-option-letter">E.</span>
                            <span class="ts-option-text">${q.opt_e}</span>
                            <span class="ts-option-shortcut">5</span>
                        </div>
                        ` : ''}
                        ${q.opt_f && q.opt_f.trim() ? `
                        <div class="ts-option-item" data-q-id="${q.id}" data-val="F">
                            <span class="ts-option-radio"></span>
                            <span class="ts-option-letter">F.</span>
                            <span class="ts-option-text">${q.opt_f}</span>
                            <span class="ts-option-shortcut">6</span>
                        </div>
                        ` : ''}
                        ${q.opt_g && q.opt_g.trim() ? `
                        <div class="ts-option-item" data-q-id="${q.id}" data-val="G">
                            <span class="ts-option-radio"></span>
                            <span class="ts-option-letter">G.</span>
                            <span class="ts-option-text">${q.opt_g}</span>
                            <span class="ts-option-shortcut">7</span>
                        </div>
                        ` : ''}
                        ${q.opt_h && q.opt_h.trim() ? `
                        <div class="ts-option-item" data-q-id="${q.id}" data-val="H">
                            <span class="ts-option-radio"></span>
                            <span class="ts-option-letter">H.</span>
                            <span class="ts-option-text">${q.opt_h}</span>
                            <span class="ts-option-shortcut">8</span>
                        </div>
                        ` : ''}
                    </div>
                    `;
                }

                qBlock.innerHTML = `
                    <div class="ts-question-header">
                        <span class="ts-question-number">Câu ${q.question_number}</span>
                        <label class="ts-flag-label" id="ts-flag-${q.id}">
                            <input type="checkbox" class="ts-flag-cb" data-id="${q.id}">
                            <span>🚩 Xem lại</span>
                        </label>
                    </div>
                    <div class="ts-question-text" style="line-height: 1.8;">${finalQuestionText}</div>
                    ${optionsHtml}
                `;

                questionsContainer.appendChild(qBlock);

                // Palette button
                const paletteBtn = document.createElement('div');
                paletteBtn.className = 'ts-palette-btn';
                paletteBtn.id = `ts-pal-${q.id}`;
                paletteBtn.textContent = q.question_number;
                paletteBtn.title = `Câu ${q.question_number}`;
                paletteBtn.addEventListener('click', () => {
                    document.getElementById(`ts-q-${q.id}`).scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                });
                paletteGrid().appendChild(paletteBtn);
            });

            groupCard.appendChild(questionsContainer);
            questionsArea().appendChild(groupCard);
        });

        // Register option click events
        document.querySelectorAll('.ts-option-item').forEach((item) => {
            item.addEventListener('click', function () {
                selectOption(this.dataset.qId, this.dataset.val);

                // Auto scroll to next question smoothly
                const currentBlock = this.closest('.ts-question-block');
                if (currentBlock) {
                    const allBlocks = Array.from(document.querySelectorAll('.ts-question-block'));
                    const currentIndex = allBlocks.indexOf(currentBlock);
                    if (currentIndex !== -1 && currentIndex < allBlocks.length - 1) {
                        const nextBlock = allBlocks[currentIndex + 1];
                        setTimeout(() => {
                            // Calculate offset including fixed header
                            const stickyBar = document.getElementById('ts-mobile-sticky-bar');
                            const isMobile = window.innerWidth <= 768;
                            const headerOffset = (isMobile && stickyBar && stickyBar.offsetHeight > 0) ? stickyBar.offsetHeight + 20 : 100;
                            const elementPosition = nextBlock.getBoundingClientRect().top;
                            const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                            window.scrollTo({
                                top: offsetPosition,
                                behavior: "smooth"
                            });
                        }, 250); // Delay 250ms for smooth UX
                    }
                }
            });
        });

        // Register text input events
        document.querySelectorAll('.ts-text-input-answer').forEach(input => {
            input.addEventListener('input', function () {
                let val = this.value.toUpperCase();
                this.value = val;

                const qId = this.dataset.qId;
                const pBtn = document.getElementById(`ts-pal-${qId}`);

                this.style.borderColor = '#cbd5e1';
                if (val) {
                    userAnswers[qId] = val;
                    if (pBtn && !flaggedQuestions[qId]) {
                        pBtn.className = 'ts-palette-btn answered';
                    }
                } else {
                    delete userAnswers[qId];
                    if (pBtn && !flaggedQuestions[qId]) {
                        pBtn.className = 'ts-palette-btn';
                    }
                }
                updateStats();
                updateProgress();
            });
        });

        // Register aptis select events
        document.querySelectorAll('.ts-aptis-select').forEach(select => {
            select.addEventListener('change', function () {
                const qId = this.dataset.qId;
                const val = this.value;
                const pBtn = document.getElementById(`ts-pal-${qId}`);

                if (val) {
                    userAnswers[qId] = val;
                    if (pBtn && !flaggedQuestions[qId]) {
                        pBtn.className = 'ts-palette-btn answered';
                    }
                } else {
                    delete userAnswers[qId];
                    if (pBtn && !flaggedQuestions[qId]) {
                        pBtn.className = 'ts-palette-btn';
                    }
                }
                updateStats();
                updateProgress();
            });
        });

        // Register writing & speaking textarea events
        // Register writing textarea events
        document.querySelectorAll('.ts-writing-answer').forEach(textarea => {
            textarea.addEventListener('input', function () {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';

                const qId = this.dataset.qId;
                const val = this.value;
                const pBtn = document.getElementById(`ts-pal-${qId}`);
                const wcNode = document.getElementById(`ts-wc-${qId}`);

                // Count words
                const words = val.trim() ? val.trim().split(/\s+/).length : 0;
                if (wcNode) wcNode.textContent = `Words ${words}`;

                if (val.trim()) {
                    userAnswers[qId] = val;
                    if (pBtn && !flaggedQuestions[qId]) {
                        pBtn.className = 'ts-palette-btn answered';
                    }
                } else {
                    delete userAnswers[qId];
                    if (pBtn && !flaggedQuestions[qId]) {
                        pBtn.className = 'ts-palette-btn';
                    }
                }
                updateStats();
                updateProgress();
            });
        });

        // Register Speaking Recorder logic
        document.querySelectorAll('.ts-speaking-wrap').forEach(wrap => {
            const qId = wrap.dataset.qId;
            const timeLimit = parseInt(wrap.dataset.timeLimit) || 120;
            const btnStart = wrap.querySelector('.ts-btn-start-record');
            const btnStop = wrap.querySelector('.ts-btn-stop-record');
            const btnRetry = wrap.querySelector('.ts-btn-retry-record');
            const statusEl = wrap.querySelector('.ts-speaking-status');
            const playerWrap = wrap.querySelector('.ts-speaking-player-wrap');
            const audioPlayer = wrap.querySelector('.ts-speaking-player');
            const btnDraft = wrap.querySelector('.ts-btn-toggle-draft');
            const draftWrap = wrap.querySelector('.ts-speaking-draft-wrap');
            const pBtn = document.getElementById(`ts-pal-${qId}`);

            if (btnDraft) {
                btnDraft.addEventListener('click', () => {
                    if (draftWrap.style.display === 'none') {
                        draftWrap.style.display = 'block';
                        btnDraft.innerHTML = 'Đóng giấy nháp';
                        btnDraft.style.color = '#ef4444';
                    } else {
                        draftWrap.style.display = 'none';
                        btnDraft.innerHTML = 'Giấy nháp';
                        btnDraft.style.color = '#3b82f6';
                    }
                });
            }

            let mediaRecorder = null;
            let audioChunks = [];
            let timerInterval = null;
            let secondsRecorded = 0;

            const formatTime = (secs) => {
                const m = Math.floor(secs / 60).toString().padStart(2, '0');
                const s = (secs % 60).toString().padStart(2, '0');
                return `${m}:${s}`;
            };

            const stopRecording = () => {
                if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                    mediaRecorder.stop();
                }
                clearInterval(timerInterval);
                btnStop.style.display = 'none';
                btnRetry.style.display = 'inline-block';
                statusEl.textContent = '';
            };

            btnStart.addEventListener('click', async () => {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    mediaRecorder = new MediaRecorder(stream);
                    audioChunks = [];

                    mediaRecorder.ondataavailable = e => {
                        if (e.data.size > 0) audioChunks.push(e.data);
                    };

                    mediaRecorder.onstop = () => {
                        const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                        const audioUrl = URL.createObjectURL(audioBlob);
                        audioPlayer.src = audioUrl;
                        playerWrap.style.display = 'block';

                        // Save directly to userAnswers as Blob URL!
                        userAnswers[qId] = audioUrl;
                        if (pBtn && !flaggedQuestions[qId]) {
                            pBtn.className = 'ts-palette-btn answered';
                        }
                        updateStats();
                        updateProgress();

                        // Stop all tracks to release mic
                        stream.getTracks().forEach(track => track.stop());
                    };

                    mediaRecorder.start();
                    btnStart.style.display = 'none';
                    btnRetry.style.display = 'none';
                    btnStop.style.display = 'inline-block';
                    playerWrap.style.display = 'none';

                    secondsRecorded = 0;
                    statusEl.style.color = '#ef4444'; // Red
                    statusEl.innerHTML = `🔴 Đang ghi âm (${formatTime(secondsRecorded)} / ${formatTime(timeLimit)})`;

                    timerInterval = setInterval(() => {
                        secondsRecorded++;
                        statusEl.innerHTML = `🔴 Đang ghi âm (${formatTime(secondsRecorded)} / ${formatTime(timeLimit)})`;
                        if (secondsRecorded >= timeLimit) {
                            stopRecording();
                        }
                    }, 1000);

                } catch (err) {
                    alert('Lỗi: Trình duyệt không thể truy cập Microphone! Hãy cấp quyền trong cài đặt trình duyệt của bạn.');
                    console.error(err);
                }
            });

            btnStop.addEventListener('click', stopRecording);

            btnRetry.addEventListener('click', () => {
                if (confirm('Ghi âm lại sẽ xóa bản thu hiện tại. Bạn có chắc chắn?')) {
                    delete userAnswers[qId];
                    if (pBtn && !flaggedQuestions[qId]) {
                        pBtn.className = 'ts-palette-btn';
                    }
                    updateStats();
                    updateProgress();

                    btnStart.style.display = 'inline-block';
                    btnRetry.style.display = 'none';
                    playerWrap.style.display = 'none';
                    statusEl.textContent = '';
                }
            });
        });


        // Register flag checkbox events
        document.querySelectorAll('.ts-flag-cb').forEach((cb) => {
            cb.addEventListener('change', function () {
                const qId = this.dataset.id;
                const isChecked = this.checked;
                const flagLabel = document.getElementById(`ts-flag-${qId}`);
                const pBtn = document.getElementById(`ts-pal-${qId}`);

                flaggedQuestions[qId] = isChecked;

                if (isChecked) {
                    flagLabel.classList.add('is-flagged');
                    if (pBtn) pBtn.className = 'ts-palette-btn flagged';
                } else {
                    flagLabel.classList.remove('is-flagged');
                    if (pBtn) {
                        pBtn.className = userAnswers[qId] ? 'ts-palette-btn answered' : 'ts-palette-btn';
                    }
                }
                updateStats();
            });
        });

        // Vẽ các công thức toán học LaTeX bằng KaTeX
        if (typeof renderMathInElement === 'function') {
            renderMathInElement(questionsArea(), {
                delimiters: [
                    { left: '$$', right: '$$', display: true },
                    { left: '$', right: '$', display: false },
                    { left: '\\(', right: '\\)', display: false },
                    { left: '\\[', right: '\\]', display: true }
                ],
                throwOnError: false
            });
        }
    }

    // ============================================================
    // SELECT OPTION
    // ============================================================
    function selectOption(qId, optionVal) {
        const selectedEl = document.querySelector(`.ts-option-item[data-q-id="${qId}"][data-val="${optionVal}"]`);
        if (!selectedEl) return;

        document.querySelectorAll(`.ts-option-item[data-q-id="${qId}"]`).forEach((opt) => {
            opt.classList.remove('selected');
        });

        selectedEl.classList.add('selected');
        userAnswers[qId] = optionVal;

        const pBtn = document.getElementById(`ts-pal-${qId}`);
        if (pBtn && !flaggedQuestions[qId]) {
            pBtn.className = 'ts-palette-btn answered';
        }

        updateStats();
        updateProgress();
    }

    // ============================================================
    // KEYBOARD SHORTCUTS
    // ============================================================
    function handleKeyboardShortcut(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        if (lockModal() && lockModal().style.display === 'flex') return;
        if (resultContainer() && resultContainer().style.display === 'block') return;

        const keyMap = { '1': 'A', '2': 'B', '3': 'C', '4': 'D', '5': 'E', '6': 'F', '7': 'G', '8': 'H' };
        if (keyMap[e.key]) {
            const visibleQ = getVisibleQuestion();
            if (visibleQ) {
                selectOption(visibleQ, keyMap[e.key]);
            }
        }
    }

    function getVisibleQuestion() {
        const viewCenter = window.innerHeight / 2;
        let closestId = null;
        let closestDist = Infinity;

        allQuestionIds.forEach(qId => {
            const el = document.getElementById(`ts-q-${qId}`);
            if (el) {
                const rect = el.getBoundingClientRect();
                const elCenter = rect.top + rect.height / 2;
                const dist = Math.abs(elCenter - viewCenter);
                if (dist < closestDist) {
                    closestDist = dist;
                    closestId = qId;
                }
            }
        });

        return closestId;
    }

    // ============================================================
    // STATS & PROGRESS
    // ============================================================
    function updateStats() {
        const answered = Object.keys(userAnswers).length;
        const flagged = Object.values(flaggedQuestions).filter(v => v).length;
        const unanswered = totalQuestionsCount - answered;

        statAnswered().textContent = answered;
        statUnanswered().textContent = unanswered;
        statFlagged().textContent = flagged;
    }

    function updateProgress() {
        const answered = Object.keys(userAnswers).length;
        const pct = totalQuestionsCount > 0 ? Math.round((answered / totalQuestionsCount) * 100) : 0;

        if (headerProgressFill()) {
            headerProgressFill().style.width = `${pct}%`;
        }
        if (headerProgressText()) {
            headerProgressText().textContent = `${answered} / ${totalQuestionsCount}`;
        }
        const mobileAnswered = document.getElementById('ts-mobile-answered');
        if (mobileAnswered) {
            mobileAnswered.textContent = `${answered}/${totalQuestionsCount}`;
        }
    }

    // ============================================================
    // TIMER
    // ============================================================
    function startTimer() {
        timerInterval = setInterval(() => {
            remainingSeconds--;
            secondsElapsed++;
            updateTimerDisplay();

            if (remainingSeconds <= 0) {
                clearInterval(timerInterval);
                alert('Hết thời gian làm bài! Hệ thống tự động nộp bài thi.');
                submitExamDirectly();
            }
        }, 1000);
    }

    function updateTimerDisplay() {
        const hrs = Math.floor(remainingSeconds / 3600);
        const mins = Math.floor((remainingSeconds % 3600) / 60);
        const secs = remainingSeconds % 60;

        let timeStr;
        if (hrs > 0) {
            timeStr = `${hrs.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        } else {
            timeStr = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }
        timerDisplay().textContent = timeStr;

        const headerTimer = document.getElementById('ts-header-timer');
        if (headerTimer) {
            headerTimer.textContent = timeStr;
        }

        const mobileTimer = document.getElementById('ts-mobile-timer');
        if (mobileTimer) {
            mobileTimer.textContent = timeStr;
        }

        const pct = totalSeconds > 0 ? (remainingSeconds / totalSeconds) * 100 : 0;
        timerBarFill().style.width = `${pct}%`;

        const ratio = remainingSeconds / totalSeconds;
        const miniTimerWrap = document.getElementById('ts-mini-timer-wrap');

        if (ratio <= 0.05) {
            timerDisplay().className = 'ts-timer-display danger';
            timerBarFill().className = 'ts-timer-bar-fill danger';
            if (miniTimerWrap) miniTimerWrap.className = 'ts-spa-mini-stat danger';
        } else if (ratio <= 0.2) {
            timerDisplay().className = 'ts-timer-display warning';
            timerBarFill().className = 'ts-timer-bar-fill warning';
            if (miniTimerWrap) miniTimerWrap.className = 'ts-spa-mini-stat';
        } else {
            timerDisplay().className = 'ts-timer-display';
            timerBarFill().className = 'ts-timer-bar-fill';
            if (miniTimerWrap) miniTimerWrap.className = 'ts-spa-mini-stat';
        }
    }

    // ============================================================
    // SUBMIT EXAM
    // ============================================================
    function promptSubmit() {
        document.querySelectorAll('audio, video').forEach(media => {
            if (!media.paused) media.pause();
        });

        const totalQuestions = allQuestionIds.length;
        const answeredCount = Object.keys(userAnswers).length;
        const unansweredCount = totalQuestions - answeredCount;

        const submitConfirmModal = document.getElementById('ts-submit-confirm-modal');
        const submitConfirmDesc = document.getElementById('ts-submit-confirm-desc');
        const cancelBtn = document.getElementById('ts-submit-cancel-btn');
        const confirmBtn = document.getElementById('ts-submit-confirm-btn');

        if (submitConfirmModal) {
            let descMsg = 'Bạn có chắc chắn muốn nộp bài thi để chấm điểm? Hãy chắc chắn rằng bạn đã kiểm tra kỹ toàn bộ câu trả lời.';
            confirmBtn.style.display = '';
            if (unansweredCount > 0) {
                descMsg = `Bạn còn <strong style="color:var(--ts-danger);">${unansweredCount}</strong> câu hỏi chưa trả lời. Bạn vẫn muốn nộp bài thi chứ?`;
            }
            if (submitConfirmDesc) {
                submitConfirmDesc.innerHTML = descMsg;
            }

            submitConfirmModal.style.display = 'flex';

            cancelBtn.onclick = (e) => {
                if (e) e.preventDefault();
                submitConfirmModal.style.display = 'none';
            };

            confirmBtn.onclick = (e) => {
                if (e) e.preventDefault();
                submitConfirmModal.style.display = 'none';
                processFinalSubmit();
            };
        } else {
            let confirmMsg = 'Bạn có chắc chắn muốn nộp bài thi?';
            if (unansweredCount > 0) {
                confirmMsg = `Bạn còn ${unansweredCount} câu chưa làm. Bạn vẫn muốn nộp bài?`;
            }
            if (confirm(confirmMsg)) {
                processFinalSubmit();
            }
        }
    }

    function processFinalSubmit() {
        clearInterval(timerInterval);

        if (examData.exam.requires_unlock) {
            taskTagName().textContent = examData.exam.pass_tag;
            lockModal().style.display = 'flex';

            const tsTaskBtn = document.getElementById('ts-portal-task-btn'); if (tsTaskBtn) tsTaskBtn.click();

            const handleUnlock = (inputEl, btnEl, errorEl) => {
                const code = inputEl.value.trim();
                if (!code) {
                    showUnlockError('Vui lòng nhập mã xác nhận nhiệm vụ!', errorEl);
                    return;
                }
                submitExam(code, btnEl, errorEl);
            };

            if (unlockSubmitBtnTop()) unlockSubmitBtnTop().onclick = (e) => {
                if (e) e.preventDefault();
                handleUnlock(unlockCodeInputTop(), unlockSubmitBtnTop(), unlockErrorTop());
            };
            if (unlockSubmitBtnBottom()) unlockSubmitBtnBottom().onclick = (e) => {
                if (e) e.preventDefault();
                handleUnlock(unlockCodeInputBottom(), unlockSubmitBtnBottom(), unlockErrorBottom());
            };

            const setupEnter = (inputEl, btnEl) => {
                if (inputEl) {
                    inputEl.onkeydown = (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            btnEl.click();
                        }
                    };
                }
            };

            setupEnter(unlockCodeInputTop(), unlockSubmitBtnTop());
            setupEnter(unlockCodeInputBottom(), unlockSubmitBtnBottom());

            if (unlockCodeInputTop()) unlockCodeInputTop().focus();
        } else {
            submitExam('');
        }
    }

    function submitExamDirectly() {
        // Dừng tất cả audio và video
        document.querySelectorAll('audio, video').forEach(media => {
            if (!media.paused) media.pause();
        });
        clearInterval(timerInterval);
        if (examData.exam.requires_unlock) {
            taskTagName().textContent = examData.exam.pass_tag;
            lockModal().style.display = 'flex';
            const tsTaskBtn = document.getElementById('ts-portal-task-btn'); if (tsTaskBtn) tsTaskBtn.click();

            const handleUnlock = (inputEl, btnEl, errorEl) => {
                const code = inputEl.value.trim();
                if (!code) {
                    showUnlockError('Vui lòng nhập mã xác nhận nhiệm vụ!', errorEl);
                    return;
                }
                submitExam(code, btnEl, errorEl);
            };

            if (unlockSubmitBtnTop()) unlockSubmitBtnTop().onclick = (e) => {
                if (e) e.preventDefault();
                handleUnlock(unlockCodeInputTop(), unlockSubmitBtnTop(), unlockErrorTop());
            };
            if (unlockSubmitBtnBottom()) unlockSubmitBtnBottom().onclick = (e) => {
                if (e) e.preventDefault();
                handleUnlock(unlockCodeInputBottom(), unlockSubmitBtnBottom(), unlockErrorBottom());
            };

            const setupEnter = (inputEl, btnEl) => {
                if (inputEl) {
                    inputEl.onkeydown = (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            btnEl.click();
                        }
                    };
                }
            };

            setupEnter(unlockCodeInputTop(), unlockSubmitBtnTop());
            setupEnter(unlockCodeInputBottom(), unlockSubmitBtnBottom());

            if (unlockCodeInputTop()) unlockCodeInputTop().focus();
        } else {
            submitExam('');
        }
    }

    async function submitExam(unlockCode, btnEl, errorEl) {
        // Stop all audios and videos
        document.querySelectorAll('audio, video').forEach(media => {
            if (!media.paused) media.pause();
        });

        if (btnEl) {
            btnEl.disabled = true;
            btnEl.dataset.origText = btnEl.textContent;
            btnEl.textContent = 'ĐANG XỬ LÝ...';
        }
        if (errorEl) errorEl.style.display = 'none';

        try {
            const response = await fetch(`${tqp_vars.api_url}submit`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': tqp_vars.nonce
                },
                body: JSON.stringify({
                    exam_id: examId,
                    answers: userAnswers,
                    unlock_code: unlockCode
                })
            });
            const res = await response.json();

            if (res.success) {
                quizSubmitted = true;
                lockModal().style.display = 'none';
                examWrap().style.display = 'none';
                showResults(res);
            } else {
                showUnlockError(res.message || 'Mã xác nhận không đúng!', errorEl);
            }
        } catch (error) {
            console.error('TestSpace Submit Error:', error);
            showUnlockError('Lỗi kết nối máy chủ! Vui lòng thử lại.', errorEl);
        } finally {
            if (btnEl) {
                btnEl.disabled = false;
                btnEl.textContent = btnEl.dataset.origText || 'XÁC NHẬN';
            }
        }
    }

    function showUnlockError(msg, errorEl) {
        if (errorEl) {
            errorEl.textContent = msg;
            errorEl.style.display = 'block';
        }
    }

    // ============================================================
    // FETCH AND RENDER TASK
    // ============================================================
    const tsTaskBtn = document.getElementById('ts-portal-task-btn');
    if (tsTaskBtn) {
        tsTaskBtn.addEventListener('click', async function (e) {
            e.preventDefault();
            if (!examData || !examData.exam || !examData.exam.pass_tag) return;

            tsTaskBtn.disabled = true;
            tsTaskBtn.textContent = 'Đang tải nhiệm vụ...';

            // Xử lý tạo nút X (nếu chưa có do lỗi cache)
            const lockModalCard = lockModal().querySelector('.ts-modal-card');
            if (lockModalCard && !document.getElementById('ts-lock-modal-close')) {
                lockModalCard.style.position = 'relative';
                const closeBtn = document.createElement('span');
                closeBtn.id = 'ts-lock-modal-close';
                closeBtn.innerHTML = '&times;';
                closeBtn.style.cssText = 'position: absolute; top: 12px; right: 18px; font-size: 26px; cursor: pointer; color: #888; line-height: 1; font-weight: bold;';
                closeBtn.onclick = function() {
                    lockModal().style.display = 'none';
                    if (remainingSeconds > 0) {
                        clearInterval(timerInterval);
                        timerInterval = setInterval(updateTimer, 1000);
                    }
                };
                lockModalCard.appendChild(closeBtn);
            }

            // Xử lý tạo spinner (nếu chưa có do lỗi cache)
            let loadingSpinner = document.getElementById('ts-task-loading-spinner');
            if (!loadingSpinner) {
                const taskBox = document.querySelector('.ts-task-box');
                if (taskBox) {
                    loadingSpinner = document.createElement('div');
                    loadingSpinner.id = 'ts-task-loading-spinner';
                    loadingSpinner.style.cssText = 'display: none; margin: 30px 0;';
                    loadingSpinner.innerHTML = `
                        <svg viewBox="0 0 50 50" style="width: 40px; height: 40px; animation: ts-spin 1s linear infinite; margin: 0 auto; display: block;">
                            <circle cx="25" cy="25" r="20" fill="none" stroke="#222" stroke-width="4" stroke-dasharray="31.4 31.4" stroke-linecap="round"></circle>
                        </svg>
                        <p style="margin-top: 15px; color: #555; font-size: 14px; font-weight: bold;">Đang lấy hướng dẫn nhiệm vụ...</p>
                        <style>@keyframes ts-spin { 100% { transform: rotate(360deg); } }</style>
                    `;
                    taskBox.insertBefore(loadingSpinner, taskBox.querySelector('.ts-locker-form-group') || taskBox.lastChild);
                }
            }

            // Hiện spinner, ẩn form nhập mã lúc đang gọi API
            if (loadingSpinner) loadingSpinner.style.display = 'block';
            
            // Ẩn các form nhập mã hiện tại
            document.querySelectorAll('.ts-locker-form-group, .ts-code-input-wrap').forEach(el => el.style.display = 'none');

            try {
                const TRAFFIC_API_URL = "https://traffic.io.vn/wp-json/iats/v1";
                
                let trafficSourceStr = '';
                try {
                    const tsData = JSON.parse(sessionStorage.getItem('tqp_traffic_source'));
                    if (tsData) {
                        trafficSourceStr = `&ts_source=${encodeURIComponent(JSON.stringify(tsData))}`;
                    }
                } catch(e) {}
                
                const apiUrl = `${TRAFFIC_API_URL}/task?tag=${encodeURIComponent(examData.exam.pass_tag)}&url=${encodeURIComponent(window.location.href)}${trafficSourceStr}`;
                const res = await fetch(apiUrl);

                if (res.ok) {
                    let taskData = await res.json();
                    if (taskData && taskData.success && taskData.data) {
                        taskData = taskData.data;
                    }

                    // Ẩn spinner khi có kết quả
                    if (loadingSpinner) loadingSpinner.style.display = 'none';
                    document.querySelectorAll('.ts-locker-form-group, .ts-code-input-wrap').forEach(el => {
                        el.style.display = el.style.gap ? 'flex' : '';
                    });

                    if (taskData && taskData.id) {



                        let instructionsHtml = `
                            <div class="ts-steps" style="text-align: left; line-height: 1.5; font-size: 14px; color: #333;">
                                <div class="step-items" style="display: flex; align-items: center; padding-bottom: 4px; flex-wrap: wrap;">
                                    <p style="margin: 0;"><strong style="color: red; font-weight: bold;">Bước 1:</strong> Bấm nút COPY để sao chép từ khóa</p>
                                    <strong style="background: #333; color: white; padding: 4px 10px; border-radius: 6px; font-family: monospace; margin: 0 8px;">${taskData.detail}</strong>
                                    <button type="button" class="ts-btn-copy" data-text="${taskData.detail}" style="background: #007bff; color: white; border: none; border-radius: 8px; font-size: 0.9rem; padding: 5px 12px; cursor: pointer;">Copy</button>
                                </div>
                                
                                ${taskData.detail2 ? `
                                <div class="step-items" style="display: flex; align-items: center; padding-bottom: 4px; flex-wrap: wrap;">
                                    <p style="margin: 0;"><strong>HOẶC</strong> sao chép từ khóa: <strong style="background: #333; color: white; padding: 4px 10px; border-radius: 6px; font-family: monospace; margin: 0 8px;">${taskData.detail2}</strong></p>
                                    <button type="button" class="ts-btn-copy" data-text="${taskData.detail2}" style="background: #007bff; color: white; border: none; border-radius: 8px; font-size: 0.9rem; padding: 5px 12px; cursor: pointer;">Copy</button>
                                </div>
                                ` : ''}
                                
                                <p style="margin: 10px 0; font-size: 16px;"><strong style="color: red; font-weight: bold;">Bước 2:</strong> Mở tab mới, truy cập 
                                    <a href="https://google.com/" target="_blank" style="color: #1a0dab; text-decoration: none; font-weight: 500;">google.com 
                                    <svg style="width: 18px; height: 18px; vertical-align: middle; margin: 0 4px; color: #1a0dab;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                    <img src="https://upload.wikimedia.org/wikipedia/commons/2/2f/Google_2015_logo.svg" alt="Google" style="height: 20px; vertical-align: middle; margin-left: 2px;">
                                    </a>
                                </p>
                                <div style="border: 1px solid #dfe1e5; border-radius: 24px; padding: 10px 14px; display: flex; align-items: center; max-width: 450px; margin-top: 10px; margin-bottom: 20px; background: #fff; box-shadow: 0 1px 4px rgba(32,33,36,.15);">
                                    <svg focusable="false" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="fill: #9aa0a6; width: 20px; height: 20px; margin-right: 12px; flex-shrink: 0;"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"></path></svg>
                                    <span style="color: red; font-size: 15px; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 5px;">Nhập từ khóa và tìm kiếm</span>
                                    <svg viewBox="0 0 24 24" style="width: 18px; height: 18px; fill: #5f6368; margin-right: 10px; flex-shrink: 0;"><path d="M20 5H4c-1.1 0-1.99.9-1.99 2L2 17c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm-9 3h2v2h-2V8zm0 3h2v2h-2v-2zM8 8h2v2H8V8zm0 3h2v2H8v-2zm-1 2H5v-2h2v2zm0-3H5V8h2v2zm9 7H8v-2h8v2zm0-4h-2v-2h2v2zm0-3h-2V8h2v2zm3 3h-2v-2h2v2zm0-3h-2V8h2v2z"/></svg>
                                    <svg viewBox="0 0 24 24" style="width: 18px; height: 18px; fill: #5f6368; margin-right: 10px; flex-shrink: 0;"><path d="M12 14c1.66 0 2.99-1.34 2.99-3L15 5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-3c0 3-2.54 5.1-5.3 5.1S6.7 14 6.7 11H5c0 3.41 2.72 6.23 6 6.72V21h2v-3.28c3.28-.48 6-3.3 6-6.72h-1.7z"/></svg>
                                    <svg viewBox="0 0 24 24" style="width: 18px; height: 18px; fill: #5f6368; flex-shrink: 0;"><path d="M4 4h3l2-2h6l2 2h3c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2zm8 3c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zm0 8c-1.65 0-3-1.35-3-3s1.35-3 3-3 3 1.35 3 3-1.35 3-3 3z"/></svg>
                                </div>
                                
                                ${taskData.img3 ? `
                                <p style="margin: 10px 0;"><strong style="color: red; font-weight: bold;">Bước 3:</strong> Tìm website <strong style="color: red; font-weight: bold;">${taskData.website}</strong> giống hình rồi bấm vào.</p>
                                <div style="margin: 10px 0;"><img src="${taskData.img3}" alt="Hình minh họa bước 3" style="width: 100%; border-radius: 8px; border: 2px solid #ddd;"></div>
                                <p style="font-size:0.9em; font-style:italic; margin: 5px 0;">(Nếu <strong>trang 1</strong> không thấy hãy tìm ở <strong>trang 2, 3</strong> là có bạn nhé <img src="/wp-content/uploads/2026/07/trang-google.png" width="250px" style="display:inline-block; vertical-align: middle; margin-left: 5px;">)</p>
                                ` : ''}
                                
                                <p style="margin: 10px 0;"><strong style="color: red; font-weight: bold;">Bước 4:</strong> ${taskData.step4 || 'Cuộn xuống cuối trang, lấy mã (tìm đúng nút này )'}</p>
                                ${taskData.img4 ? `<div style="margin: 10px 0;"><img src="${taskData.img4}" alt="Hình minh họa bước 4" style="width: 100%; border-radius: 8px; border: 2px solid #ddd;"></div>` : ''}
                            </div>
                        `;
                        document.getElementById('ts-task-instructions').innerHTML = instructionsHtml;
                        document.getElementById('ts-task-instructions').style.display = 'block';
                        document.getElementById('ts-task-hint-text').style.display = 'none';
                        tsTaskBtn.style.display = 'none';

                        // Setup copy logic
                        const copyFallback = (textToCopy) => {
                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                return navigator.clipboard.writeText(textToCopy);
                            } else {
                                return new Promise((resolve, reject) => {
                                    const textArea = document.createElement("textarea");
                                    textArea.value = textToCopy;
                                    textArea.style.position = "fixed";
                                    textArea.style.left = "-9999px";
                                    document.body.appendChild(textArea);
                                    textArea.focus();
                                    textArea.select();
                                    try {
                                        document.execCommand('copy');
                                        resolve();
                                    } catch (err) {
                                        reject(err);
                                    }
                                    textArea.remove();
                                });
                            }
                        };

                        document.querySelectorAll('.ts-btn-copy').forEach(btn => {
                            btn.addEventListener('click', function () {
                                const text = this.getAttribute('data-text');
                                copyFallback(text).then(() => {
                                    const oldText = this.textContent;
                                    this.textContent = "Đã Copy!";
                                    this.style.background = "#dcfce7";
                                    this.style.borderColor = "#22c55e";
                                    this.style.color = "#166534";
                                    setTimeout(() => {
                                        this.textContent = oldText;
                                        this.style.background = "#f8fafc";
                                        this.style.borderColor = "#cbd5e1";
                                        this.style.color = "#475569";
                                    }, 2000);
                                });
                            });
                        });
                    } else {
                        alert("Không lấy được nhiệm vụ. Hãy thử lại!");
                        tsTaskBtn.textContent = 'Làm nhiệm vụ lấy mã →';
                        tsTaskBtn.disabled = false;
                        if (loadingSpinner) loadingSpinner.style.display = 'none';
                        document.querySelectorAll('.ts-locker-form-group, .ts-code-input-wrap').forEach(el => {
                            el.style.display = el.style.gap ? 'flex' : '';
                        });
                    }
                }
            } catch (err) {
                console.error(err);
                alert("Lỗi kết nối khi tải nhiệm vụ!");
                tsTaskBtn.textContent = 'Làm nhiệm vụ lấy mã →';
                tsTaskBtn.disabled = false;
                if (loadingSpinner) loadingSpinner.style.display = 'none';
                document.querySelectorAll('.ts-locker-form-group, .ts-code-input-wrap').forEach(el => {
                    el.style.display = el.style.gap ? 'flex' : '';
                });
            }
        });
    }

    // Đảm bảo nút đóng hoạt động (nếu đã được render từ PHP)
    const lockModalCloseBtn = document.getElementById('ts-lock-modal-close');
    if (lockModalCloseBtn && !lockModalCloseBtn.onclick) {
        lockModalCloseBtn.onclick = function() {
            lockModal().style.display = 'none';
            if (remainingSeconds > 0) {
                clearInterval(timerInterval);
                timerInterval = setInterval(updateTimer, 1000);
            }
        };
    }

    // ============================================================
    // SHOW RESULTS
    // ============================================================
    function showResults(data) {
        // Cập nhật đường dẫn URL kết quả để hỗ trợ router chia sẻ liên kết kết quả
        // history.pushState({state: 'results', examId: examId}, '', '?exam_id=' + examId + '&results=true');

        // Xóa class ts-exam-active để phục hồi lại toàn bộ Header/Footer/Menu của theme Flatsome (bao gồm cả responsive mobile)
        document.body.classList.remove('ts-exam-active');

        resultContainer().style.display = 'block';
        const reviewWrap = document.getElementById('ts-review-section-wrap');
        if (reviewWrap) {
            reviewWrap.style.display = (parseInt(examData.exam.show_answers) === 0) ? 'none' : 'block';
        }

        if (parseInt(examData.exam.show_answers) === 0) {
            resultContainer().classList.add('ts-standalone-mode');
        } else {
            resultContainer().classList.remove('ts-standalone-mode');
        }

        document.querySelectorAll('.ts-result-stats, .ts-spa-footer').forEach(el => {
            if (parseInt(examData.exam.show_answers) === 0) {
                el.style.setProperty('display', 'none', 'important');
            } else {
                el.style.display = '';
            }
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });

        // Hide the mini stats in header
        const headerStats = document.querySelector('.ts-spa-mini-stats');
        if (headerStats) {
            headerStats.style.display = 'none';
        }

        // Show a "Finished" badge instead in header
        const headerRight = document.querySelector('.ts-spa-header-right');
        if (headerRight) {
            if (!document.getElementById('ts-header-done-badge')) {
                const doneBadge = document.createElement('span');
                doneBadge.id = 'ts-header-done-badge';
                doneBadge.className = 'ts-spa-done-badge';
                doneBadge.textContent = '🎉 Đã hoàn thành';
                headerRight.appendChild(doneBadge);
            }
        }

        // Set the full-width header progress fill to 100% since exam is completed
        if (headerProgressFill()) {
            headerProgressFill().style.width = '100%';
        }

        // Confetti 🎉
        if (typeof confetti === 'function') {
            setTimeout(() => {
                confetti({ particleCount: 120, spread: 80, origin: { y: 0.4 } });
            }, 300);
            if (data.score.percentage >= 80) {
                setTimeout(() => {
                    confetti({ particleCount: 80, spread: 120, origin: { y: 0.5 } });
                }, 800);
            }
        }

        // Time calculation
        const hrs = Math.floor(secondsElapsed / 3600);
        const mins = Math.floor((secondsElapsed % 3600) / 60);
        const secs = secondsElapsed % 60;
        let timeStr;
        if (hrs > 0) {
            timeStr = `${hrs.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        } else {
            timeStr = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
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
                    tallyHtml += `<li><strong>${kw}</strong>: ${count} điểm</li>`;
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
            if (badgeTextEl) badgeTextEl.innerHTML = 'Hoàn thành bài test';

            const totalScoreTextEl = document.getElementById('ts-result-total-score-text');
            if (totalScoreTextEl) {
                totalScoreTextEl.innerHTML = `Tổng điểm: ${data.score.total_weight}`;
                totalScoreTextEl.style.display = 'inline-block';
            }

            const rlEl = document.getElementById('ts-review-list');
            const toggleBtn = document.getElementById('ts-toggle-review-btn');
            if (rlEl && toggleBtn) {
                rlEl.style.display = 'none';
                toggleBtn.style.display = 'inline-block';
                toggleBtn.onclick = function () {
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
            let displayScore = `${data.score.percentage}%`;

            if (data.exam_max_score && data.exam_max_score > 0) {
                pct = Math.min(data.score.earned_score / data.exam_max_score, 1);
                displayScore = `${data.score.earned_score}/${data.exam_max_score}`;
                scoreRingNumber().style.fontSize = '24px';
            }

            const offset = circumference - (circumference * pct);
            scoreRingNumber().textContent = displayScore;

            setTimeout(() => {
                scoreRing().style.strokeDasharray = circumference;
                scoreRing().style.strokeDashoffset = offset;
            }, 100);
        }

        // Tạo nút Xem Video Chữa Bài nếu có video_url
        let oldVideoContainer = document.getElementById('ts-video-review-container');
        if (oldVideoContainer) oldVideoContainer.remove();
        if (data.video_url) {
            const videoContainer = document.createElement('div');
            videoContainer.id = 'ts-video-review-container';
            videoContainer.style.cssText = 'text-align:center; margin: 0 0 20px 0;';

            const videoBtn = document.createElement('button');
            videoBtn.type = 'button';
            videoBtn.innerHTML = 'Xem Video Chữa Bài';
            videoBtn.style.cssText = 'display:inline-block; border:none; cursor:pointer; background:#e11d48; color:#fff; text-decoration:none; padding:12px 24px; border-radius:8px; font-weight:600; font-size:16px; box-shadow: 0 4px 12px rgba(225,29,72,0.3); transition:all 0.2s;';

            const playerWrap = document.createElement('div');
            playerWrap.style.cssText = 'display:none; margin-top:15px; border-radius:8px; overflow:hidden; background:#000; box-shadow: 0 4px 15px rgba(0,0,0,0.1);';

            videoBtn.onclick = function () {
                if (playerWrap.style.display === 'none') {
                    playerWrap.style.display = 'block';
                    videoBtn.innerHTML = 'Ẩn Video Chữa Bài';
                    videoBtn.style.background = '#475569';
                    videoBtn.style.boxShadow = 'none';

                    const url = data.video_url;
                    if (url.includes('youtube.com') || url.includes('youtu.be')) {
                        let ytId = '';
                        if (url.includes('/embed/')) {
                            ytId = url.split('/embed/')[1].split('?')[0];
                        } else if (url.includes('v=')) {
                            ytId = url.split('v=')[1].split('&')[0];
                        } else if (url.includes('youtu.be/')) {
                            ytId = url.split('youtu.be/')[1].split('?')[0];
                        }
                        playerWrap.innerHTML = `<iframe width="100%" height="450" src="https://www.youtube.com/embed/${ytId}?autoplay=1" frameborder="0" allow="autoplay; encrypted-media; fullscreen" allowfullscreen style="display:block;"></iframe>`;
                    } else {
                        playerWrap.innerHTML = `<video src="${url}" controls controlsList="nodownload" oncontextmenu="return false;" style="width:100%; max-height:500px; display:block;" autoplay></video>`;
                    }
                } else {
                    playerWrap.style.display = 'none';
                    playerWrap.innerHTML = ''; // Clear iframe/video to stop playing
                    videoBtn.innerHTML = 'Xem Video Chữa Bài';
                    videoBtn.style.background = '#e11d48';
                    videoBtn.style.boxShadow = '0 4px 12px rgba(225,29,72,0.3)';
                }
            };

            videoContainer.appendChild(videoBtn);
            videoContainer.appendChild(playerWrap);

            const reviewSection = document.getElementById('ts-review-section-wrap');
            if (reviewSection && reviewSection.parentNode) {
                reviewSection.parentNode.insertBefore(videoContainer, reviewSection);
            }
        }

        // Render review items (accordion)
        reviewList().innerHTML = '';

        Object.keys(data.results).forEach((qId) => {
            const res = data.results[qId];

            // Find original question data
            let questionText = 'Câu hỏi trắc nghiệm:';
            let optA = '', optB = '', optC = '', optD = '', optE = '', optF = '', optG = '', optH = '';
            let groupType = 'reading';

            examData.groups.forEach((g) => {
                g.questions.forEach((q) => {
                    if (parseInt(q.id, 10) === parseInt(qId, 10)) {
                        questionText = q.question_text;
                        optA = q.opt_a;
                        optB = q.opt_b;
                        optC = q.opt_c;
                        optD = q.opt_d;
                        optE = q.opt_e;
                        optF = q.opt_f;
                        optG = q.opt_g;
                        optH = q.opt_h;
                        groupType = g.group_type;
                    }
                });
            });

            const isScoreRange = data.scoring_method === 'score_range' || data.scoring_method === 'personality';
            const reviewItem = document.createElement('div');
            reviewItem.className = `ts-review-item ${isScoreRange ? 'neutral' : (res.is_correct ? 'correct' : 'incorrect')}`;

            let badgeHtml = '';
            if (isScoreRange) {
                const pts = res.points_earned !== undefined ? res.points_earned : 0;
                badgeHtml = `<span class="ts-review-badge ts-badge-points">+ ${pts} ĐIỂM</span>`;
            } else {
                badgeHtml = `<span class="ts-review-badge">${res.is_correct ? 'V ĐÚNG' : 'X SAI'}</span>`;
                if (res.is_correct && res.points_earned > 0) {
                    badgeHtml += `<span style="margin-left:10px; font-weight:600; font-size:13px; color:#2563eb;">(+ ${res.points_earned} ĐIỂM)</span>`;
                }
            }

            const getChoiceClass = (ans) => {
                let cls = '';
                if (res.user_ans === ans) cls += ' user-selected';
                if (!isScoreRange && res.correct_ans === ans) cls += ' correct-answer';
                return cls;
            };

            reviewItem.innerHTML = `
                <div class="ts-review-item-header">
                    <div class="ts-review-item-left">
                        <span class="ts-review-q-num">Câu ${res.question_number}</span>
                        ${badgeHtml}
                    </div>
                    <span class="ts-review-toggle">▾</span>
                </div>
                <div class="ts-review-item-body">
                    <div class="ts-review-question-text">${questionText}</div>
                    <div class="ts-review-choices">
                        ${groupType === 'text_input' ? `
                            <div style="padding: 10px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; margin-bottom: 10px;">
                                <div style="margin-bottom: 5px;"><strong>Đáp án của bạn:</strong> <span style="font-weight: bold; color: ${res.is_correct ? '#16a34a' : '#ef4444'};">${res.user_ans || '(Không trả lời)'}</span></div>
                                ${!isScoreRange ? `<div><strong>Đáp án đúng:</strong> <span style="font-weight: bold; color: #16a34a;">${res.correct_ans}</span></div>` : ''}
                            </div>
                        ` : (groupType === 'speaking') ? `
                            <div style="padding: 10px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; margin-bottom: 10px;">
                                <div style="margin-bottom: 5px;"><strong>Bài nói của bạn:</strong></div>
                                ${res.user_ans ? `<audio controls style="width: 100%; max-width: 300px; margin-top: 10px; outline: none;"><source src="${res.user_ans}" type="audio/webm"></audio>` : '<div style="color: #ef4444; margin-top: 5px; font-style: italic;">(Không có bản ghi âm)</div>'}
                            </div>
                        ` : (groupType === 'writing') ? `
                            <div style="padding: 10px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; margin-bottom: 10px;">
                                <div style="margin-bottom: 5px;"><strong>Bài viết của bạn:</strong><div style="margin-top: 10px; padding: 10px; background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; white-space: pre-wrap; font-family: inherit; line-height: 1.5; color: ${res.is_correct ? '#0f172a' : '#ef4444'};">${res.user_ans || '(Không trả lời)'}</div></div>
                            </div>
                        ` : `
                            <div class="ts-review-choice${getChoiceClass('A')}">
                                <strong>A.</strong> ${optA} ${res.user_ans === 'A' ? ' (Bạn chọn)' : ''}
                            </div>
                            <div class="ts-review-choice${getChoiceClass('B')}">
                                <strong>B.</strong> ${optB} ${res.user_ans === 'B' ? ' (Bạn chọn)' : ''}
                            </div>
                            ${optC && optC.trim() ? `
                            <div class="ts-review-choice${getChoiceClass('C')}">
                                <strong>C.</strong> ${optC} ${res.user_ans === 'C' ? ' (Bạn chọn)' : ''}
                            </div>
                            ` : ''}
                            ${optD && optD.trim() ? `
                            <div class="ts-review-choice${getChoiceClass('D')}">
                                <strong>D.</strong> ${optD} ${res.user_ans === 'D' ? ' (Bạn chọn)' : ''}
                            </div>
                            ` : ''}
                            ${optE && optE.trim() ? `
                            <div class="ts-review-choice${getChoiceClass('E')}">
                                <strong>E.</strong> ${optE} ${res.user_ans === 'E' ? ' (Bạn chọn)' : ''}
                            </div>
                            ` : ''}
                            ${optF && optF.trim() ? `
                            <div class="ts-review-choice${getChoiceClass('F')}">
                                <strong>F.</strong> ${optF} ${res.user_ans === 'F' ? ' (Bạn chọn)' : ''}
                            </div>
                            ` : ''}
                            ${optG && optG.trim() ? `
                            <div class="ts-review-choice${getChoiceClass('G')}">
                                <strong>G.</strong> ${optG} ${res.user_ans === 'G' ? ' (Bạn chọn)' : ''}
                            </div>
                            ` : ''}
                            ${optH && optH.trim() ? `
                            <div class="ts-review-choice${getChoiceClass('H')}">
                                <strong>H.</strong> ${optH} ${res.user_ans === 'H' ? ' (Bạn chọn)' : ''}
                            </div>
                            ` : ''}
                        `}
                    </div>
                    <div class="ts-explanation-box">
                        <strong>Giải thích:</strong> ${res.explanation || 'Không có giải thích chi tiết cho câu hỏi này.'}
                    </div>
                </div>
            `;

            // Accordion toggle
            const header = reviewItem.querySelector('.ts-review-item-header');
            header.addEventListener('click', () => {
                reviewItem.classList.toggle('open');
            });

            reviewList().appendChild(reviewItem);
        });
        // Auto-open first 3 incorrect items
        document.querySelectorAll('.ts-review-item.incorrect').forEach((el, index) => {
            if (index < 3) el.classList.add('open');
        });

        // Vẽ các công thức toán học LaTeX bằng KaTeX trong phần xem lại bài
        if (typeof renderMathInElement === 'function') {
            renderMathInElement(reviewList(), {
                delimiters: [
                    { left: '$$', right: '$$', display: true },
                    { left: '$', right: '$', display: false },
                    { left: '\\(', right: '\\)', display: false },
                    { left: '\\[', right: '\\]', display: true }
                ],
                throwOnError: false
            });
        }
    }

    function initPortalHomepage() {
        const searchInput = document.getElementById('ts-portal-search-input');
        const categoriesContainer = document.querySelector('.ts-portal-categories');
        const catButtons = document.querySelectorAll('.ts-portal-cat-btn:not(.ts-dropdown-toggle)');
        const dropdownToggles = document.querySelectorAll('.ts-dropdown-toggle');
        const dropdownItems = document.querySelectorAll('.ts-dropdown-item');
        const dropdowns = document.querySelectorAll('.ts-portal-dropdown');
        const examCards = document.querySelectorAll('.ts-portal-card');
        const startButtons = document.querySelectorAll('.ts-portal-start-btn');

        // Dynamic search banner elements
        const searchStatusBanner = document.getElementById('ts-portal-search-status');
        const searchStatusText = document.getElementById('ts-search-status-text');
        const searchClearBtn = document.getElementById('ts-search-clear-btn');

        // Active category filter state (starts at 'all')
        let currentCategoryFilter = 'all';

        // 1. Dropdown Toggle Click Interaction
        dropdownToggles.forEach(toggle => {
            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                const parent = this.parentElement;
                const wasShow = parent.classList.contains('show');

                // Close all other dropdowns
                dropdowns.forEach(d => d.classList.remove('show'));
                if (categoriesContainer) {
                    categoriesContainer.classList.remove('ts-dropdown-open');
                }

                if (!wasShow) {
                    parent.classList.add('show');
                    if (categoriesContainer) {
                        categoriesContainer.classList.add('ts-dropdown-open');
                    }
                }
            });
        });

        // 2. Dropdown Item Click Interaction
        dropdownItems.forEach(item => {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                const filterVal = this.dataset.filter;
                const displayName = this.dataset.displayName;
                const dropdownParent = this.closest('.ts-portal-dropdown');
                const toggleBtn = dropdownParent.querySelector('.ts-dropdown-toggle');

                // Set current active filter state
                currentCategoryFilter = filterVal;

                // Reset standard category buttons
                catButtons.forEach(btn => btn.classList.remove('active'));

                // Reset all other dropdown states and titles
                dropdowns.forEach(d => {
                    d.classList.remove('active');
                    const t = d.querySelector('.ts-dropdown-toggle');
                    if (t) {
                        t.textContent = t.dataset.originalText;
                        t.classList.remove('active');
                    }
                });

                // Set active states for this dropdown
                dropdownParent.classList.add('active');
                toggleBtn.classList.add('active');
                toggleBtn.innerHTML = `${displayName} <span class="ts-arrow">▾</span>`;

                // Set active class for items inside menu
                dropdownItems.forEach(di => di.classList.remove('active'));
                this.classList.add('active');

                // Close the dropdown menu
                dropdownParent.classList.remove('show');
                if (categoriesContainer) {
                    categoriesContainer.classList.remove('ts-dropdown-open');
                }

                // If searching, we clear the search to focus on this category
                if (searchInput && searchInput.value !== '') {
                    searchInput.value = '';
                    if (searchStatusBanner) searchStatusBanner.style.display = 'none';
                    if (categoriesContainer) categoriesContainer.classList.remove('ts-search-mode');
                }

                filterCards();
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function () {
            dropdowns.forEach(d => d.classList.remove('show'));
            if (categoriesContainer) {
                categoriesContainer.classList.remove('ts-dropdown-open');
            }
        });

        // 3. Regular Category Filter Click
        catButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                // Set current active filter state
                currentCategoryFilter = this.dataset.filter || 'all';

                // Manage active states
                catButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                // Reset all dropdowns titles and active states
                dropdowns.forEach(d => {
                    d.classList.remove('active');
                    d.classList.remove('show');
                    const t = d.querySelector('.ts-dropdown-toggle');
                    if (t) {
                        t.textContent = t.dataset.originalText;
                        t.classList.remove('active');
                    }
                });
                dropdownItems.forEach(di => di.classList.remove('active'));
                if (categoriesContainer) {
                    categoriesContainer.classList.remove('ts-dropdown-open');
                }

                // If searching, we clear the search to focus on this category
                if (searchInput && searchInput.value !== '') {
                    searchInput.value = '';
                    if (searchStatusBanner) searchStatusBanner.style.display = 'none';
                    if (categoriesContainer) categoriesContainer.classList.remove('ts-search-mode');
                }

                filterCards();
            });
        });

        // 4. Search Filter
        if (searchInput) {
            ['input', 'keyup', 'change'].forEach(evt => {
                searchInput.addEventListener(evt, function () {
                    const rawQuery = this.value;
                    const query = rawQuery.toLowerCase().normalize("NFC").trim();

                    if (query !== '') {
                        // Reset all categories selections and dropdown titles on search
                        currentCategoryFilter = 'all';

                        catButtons.forEach(b => {
                            if (b.dataset.filter === 'all') {
                                b.classList.add('active');
                            } else {
                                b.classList.remove('active');
                            }
                        });

                        dropdowns.forEach(d => {
                            d.classList.remove('active');
                            d.classList.remove('show');
                            const t = d.querySelector('.ts-dropdown-toggle');
                            if (t) {
                                t.textContent = t.dataset.originalText;
                                t.classList.remove('active');
                            }
                        });
                        dropdownItems.forEach(di => di.classList.remove('active'));
                        if (categoriesContainer) {
                            categoriesContainer.classList.remove('ts-dropdown-open');
                            // Dim categories bar to indicate search mode
                            categoriesContainer.classList.add('ts-search-mode');
                        }
                    } else {
                        if (categoriesContainer) {
                            categoriesContainer.classList.remove('ts-search-mode');
                        }
                    }

                    filterCards();
                });
            });
        }

        // 5. Search Clear Button Listener
        if (searchClearBtn) {
            searchClearBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.focus();
                }
                if (searchStatusBanner) {
                    searchStatusBanner.style.display = 'none';
                }
                if (categoriesContainer) {
                    categoriesContainer.classList.remove('ts-search-mode');
                }
                filterCards();
            });
        }

        // Core Card Filtering Logic
        function filterCards() {
            const rawQuery = searchInput ? searchInput.value : '';
            const query = rawQuery.toLowerCase().normalize("NFC").trim();

            let matchesCount = 0;

            examCards.forEach(card => {
                const category = card.dataset.category || '';
                const titleRaw = card.dataset.title || '';
                const title = titleRaw.toLowerCase().normalize("NFC");

                const matchesFilter = (currentCategoryFilter === 'all' || category === currentCategoryFilter);
                const matchesSearch = (query === '' || title.indexOf(query) !== -1);

                if (matchesFilter && matchesSearch) {
                    card.style.setProperty('display', 'flex', 'important');
                    matchesCount++;
                } else {
                    card.style.setProperty('display', 'none', 'important');
                }
            });

            // Update real-time search status banner
            if (searchStatusBanner && searchStatusText) {
                if (query !== '') {
                    searchStatusBanner.style.display = 'flex';
                    if (matchesCount > 0) {
                        searchStatusBanner.classList.remove('no-results');
                        searchStatusText.innerHTML = `Tìm thấy <strong>${matchesCount}</strong> đề thi phù hợp với từ khóa "<strong>${escapeHtml(rawQuery)}</strong>"`;
                    } else {
                        searchStatusBanner.classList.add('no-results');
                        searchStatusText.innerHTML = `Không tìm thấy đề thi nào phù hợp với từ khóa "<strong>${escapeHtml(rawQuery)}</strong>"`;
                    }
                } else {
                    searchStatusBanner.style.display = 'none';
                }
            }
        }

        // Simple HTML Escaping helper
        function escapeHtml(text) {
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // 3. Start Exam Trigger
        startButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                const id = parseInt(this.dataset.id, 10);
                if (isNaN(id) || id <= 0) return;

                // Set global URL state
                history.pushState({ state: 'exam', examId: id }, '', '?exam_id=' + id);

                // Set global examId
                examId = id;

                // Dynamic layout transition
                const portalFront = document.getElementById('ts-portal-front');
                const portalExamView = document.getElementById('ts-portal-exam-view');
                const homeControls = document.getElementById('ts-home-header-controls');
                const headerMiniStats = document.getElementById('ts-header-mini-stats');

                if (portalFront) portalFront.style.display = 'none';
                if (portalExamView) portalExamView.style.display = 'block';
                if (loader()) loader().style.display = 'flex';
                if (homeControls) homeControls.style.display = 'none';
                if (headerMiniStats) headerMiniStats.style.display = 'flex';

                // Build a custom Back button on header
                const headerLeft = document.querySelector('.ts-spa-header-left');
                if (headerLeft) {
                    // Check if exit button exists, if not, create one
                    if (!document.querySelector('.ts-spa-exit-btn')) {
                        const exitBtn = document.createElement('a');
                        exitBtn.href = '#';
                        exitBtn.className = 'ts-spa-exit-btn';
                        exitBtn.title = 'Thoát khỏi phòng thi';
                        exitBtn.innerHTML = '<span class="ts-spa-exit-icon">🚪</span><span class="ts-spa-exit-text">Thoát</span>';
                        exitBtn.onclick = function (e) {
                            promptExit(e);
                        };
                        headerLeft.insertBefore(exitBtn, headerLeft.firstChild);

                        // Add divider if not exists
                        if (!document.querySelector('.ts-spa-divider')) {
                            const divider = document.createElement('div');
                            divider.className = 'ts-spa-divider';
                            headerLeft.insertBefore(divider, exitBtn.nextSibling);
                        }
                    }
                }

                // Load the exam details and kickstart SPA!
                loadExam();
            });
        });
    }

    // ============================================================
    // CUSTOM PROMPT EXIT MODAL
    // ============================================================
    function promptExit(e) {
        if (e) e.preventDefault();

        // Nếu đã nộp bài, hiện popup hỏi thoát phù hợp thay vì cảnh báo bỏ bài giữa chừng
        if (typeof quizSubmitted !== 'undefined' && quizSubmitted) {
            const exitConfirmModal = document.getElementById('ts-exit-confirm-modal');
            if (exitConfirmModal) {
                const modalTitle = exitConfirmModal.querySelector('.ts-modal-title');
                const modalDesc = exitConfirmModal.querySelector('.ts-modal-desc');
                const modalIcon = exitConfirmModal.querySelector('.ts-modal-icon');
                const cancelBtn = document.getElementById('ts-exit-cancel-btn');
                const confirmBtn = document.getElementById('ts-exit-confirm-btn');

                if (modalTitle) modalTitle.textContent = 'Trở về trang chủ?';
                if (modalDesc) modalDesc.textContent = 'Bạn đã hoàn thành bài thi và xem kết quả. Bạn có chắc chắn muốn thoát để trở về trang chủ không?';
                if (modalIcon) {
                    modalIcon.textContent = '🏠';
                    modalIcon.className = 'ts-modal-icon'; // remove warning styling
                }
                if (cancelBtn) cancelBtn.textContent = 'Ở lại xem kết quả';
                if (confirmBtn) {
                    confirmBtn.textContent = 'Xác nhận thoát';
                    confirmBtn.className = 'ts-modal-btn primary'; // Nice blue instead of red danger
                }

                exitConfirmModal.style.display = 'flex';

                if (cancelBtn) {
                    cancelBtn.onclick = () => {
                        exitConfirmModal.style.display = 'none';
                    };
                }
                if (confirmBtn) {
                    confirmBtn.onclick = () => {
                        goBackToPortalOrHome();
                    };
                }
            } else {
                goBackToPortalOrHome();
            }
            return;
        }

        // Kiểm tra xem phòng thi có đang hoạt động hay không
        const isExamActive = (examWrap() && examWrap().style.display === 'block') || (document.getElementById('ts-portal-exam-view') && document.getElementById('ts-portal-exam-view').style.display === 'block' && loader() && loader().style.display === 'none');

        if (isExamActive) {
            const exitConfirmModal = document.getElementById('ts-exit-confirm-modal');
            if (exitConfirmModal) {
                exitConfirmModal.style.display = 'flex';

                // Nút Hủy (Ở lại làm tiếp)
                const cancelBtn = document.getElementById('ts-exit-cancel-btn');
                if (cancelBtn) {
                    cancelBtn.onclick = () => {
                        exitConfirmModal.style.display = 'none';
                    };
                }

                // Nút Xác nhận Thoát
                const confirmBtn = document.getElementById('ts-exit-confirm-btn');
                if (confirmBtn) {
                    confirmBtn.onclick = () => {
                        goBackToPortalOrHome();
                    };
                }
            } else {
                // Fallback
                if (confirm('Bạn có chắc chắn muốn thoát khỏi phòng thi? Tiến trình làm bài sẽ bị mất!')) {
                    goBackToPortalOrHome();
                }
            }
        } else {
            goBackToPortalOrHome();
        }
    }

    // Gắn sự kiện click cho Logo thương hiệu
    const brandLogo = document.querySelector('.ts-spa-brand');
    if (brandLogo) {
        brandLogo.addEventListener('click', function (e) {
            const isExamActive = (examWrap() && examWrap().style.display === 'block') || (document.getElementById('ts-portal-exam-view') && document.getElementById('ts-portal-exam-view').style.display === 'block' && loader() && loader().style.display === 'none');
            if (isExamActive) {
                promptExit(e);
            } else {
                goToMainHome();
            }
        });
    }

    // Gắn sự kiện click cho các nút thoát tĩnh (như trong shortcode.php)
    const existingExitBtn = document.querySelector('.ts-spa-exit-btn');
    if (existingExitBtn) {
        existingExitBtn.addEventListener('click', function (e) {
            promptExit(e);
        });
    }

    // ============================================================
    // PREMIUM SPA ROUTER & DEEP LINKING
    // ============================================================
    function loadExamDirectlyFromUrl(id) {
        examId = id;

        const portalFront = document.getElementById('ts-portal-front');
        const portalExamView = document.getElementById('ts-portal-exam-view');
        const homeControls = document.getElementById('ts-home-header-controls');
        const headerMiniStats = document.getElementById('ts-header-mini-stats');

        if (portalFront) portalFront.style.display = 'none';
        if (portalExamView) portalExamView.style.display = 'block';
        if (loader()) loader().style.display = 'flex';
        if (homeControls) homeControls.style.display = 'none';
        if (headerMiniStats) headerMiniStats.style.display = 'flex';

        // Build Exit Button in Header dynamically
        const headerLeft = document.querySelector('.ts-spa-header-left');
        if (headerLeft) {
            if (!document.querySelector('.ts-spa-exit-btn')) {
                const exitBtn = document.createElement('a');
                exitBtn.href = '#';
                exitBtn.className = 'ts-spa-exit-btn';
                exitBtn.title = 'Thoát khỏi phòng thi';
                exitBtn.innerHTML = '<span class="ts-spa-exit-icon">🚪</span><span class="ts-spa-exit-text">Thoát</span>';
                exitBtn.onclick = function (e) {
                    promptExit(e);
                };
                headerLeft.insertBefore(exitBtn, headerLeft.firstChild);

                if (!document.querySelector('.ts-spa-divider')) {
                    const divider = document.createElement('div');
                    divider.className = 'ts-spa-divider';
                    headerLeft.insertBefore(divider, exitBtn.nextSibling);
                }
            }
        }

        loadExam();
    }

    // Xử lý nút Back/Forward của trình duyệt (Browser History Popstate)
    window.addEventListener('popstate', (e) => {
        const params = new URLSearchParams(window.location.search);
        const urlExamId = parseInt(params.get('exam_id'), 10);

        const isExamActive = (examWrap() && examWrap().style.display === 'block') && !quizSubmitted;

        if (isExamActive) {
            // Ngăn chặn thoát đột ngột khi đang làm bài dở dang bằng cách đẩy lại state cũ
            history.pushState({ state: 'exam', examId: examId }, '', '?exam_id=' + examId);
            promptExit();
            return;
        }

        // Chuyển hướng hoặc tải lại trạng thái tương ứng
        window.location.reload();
    });

    // ============================================================
    // START ENTRYPOINT
    // ============================================================
    const urlParams = new URLSearchParams(window.location.search);
    const queryExamId = parseInt(urlParams.get('exam_id'), 10);
    const hasLobby = document.getElementById('ts-exam-lobby') !== null;

    if (!isNaN(queryExamId) && queryExamId > 0 && !hasLobby) {
        // Được gọi trực tiếp từ URL trên trang chủ portal (không có Lobby) — bỏ qua Lobby
        loadExamDirectlyFromUrl(queryExamId);
    } else if ((!isNaN(examId) && examId > 0) || hasLobby) {
        // Có shortcode [traffic_quiz] hoặc có Lobby — hiển thị Lobby trước
        if (isNaN(examId) || examId <= 0) {
            const rootEl = document.getElementById('ts-app-root');
            if (rootEl && rootEl.dataset.examId) {
                examId = parseInt(rootEl.dataset.examId, 10);
            } else if (!isNaN(queryExamId) && queryExamId > 0) {
                examId = queryExamId;
            }
        }

        const lobbyBtn = getEl('ts-lobby-start-btn');
        const lobbyWrap = getEl('ts-exam-lobby');
        const workspaceWrap = getEl('ts-exam-workspace');

        if (lobbyBtn && lobbyWrap && workspaceWrap) {
            // Chế độ Lobby: giữ nguyên chủ đề (theme) và cho phép user đọc thông tin
            lobbyBtn.addEventListener('click', () => {
                // 1. Animate lobby exit
                lobbyWrap.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                lobbyWrap.style.opacity = '0';
                lobbyWrap.style.transform = 'translateY(-16px)';

                setTimeout(() => {
                    // 2. Hide lobby, show workspace
                    lobbyWrap.style.display = 'none';
                    workspaceWrap.style.display = 'block';
                    workspaceWrap.style.opacity = '0';
                    workspaceWrap.style.transition = 'opacity 0.4s ease';

                    // 3. Activate distraction-free mode (hide theme header/footer)
                    document.body.classList.add('ts-exam-active');

                    // 4. Trigger workspace fade-in
                    requestAnimationFrame(() => {
                        workspaceWrap.style.opacity = '1';
                    });

                    // 5. Load exam & start timer
                    loadExam();

                    // 6. Smooth scroll to top
                    window.scrollTo({ top: 0, behavior: 'smooth' });

                    // 7. Fix sticky sidebar overflow for all parent containers
                    setTimeout(() => {
                        let parent = document.querySelector('.ts-sticky-sidebar');
                        while (parent && parent !== document.body) {
                            parent.style.setProperty('overflow', 'visible', 'important');
                            parent = parent.parentElement;
                        }
                    }, 300);
                }, 400);
            });
        } else {
            // Fallback: nếu không có Lobby (old template), load ngay
            loadExam();
        }
    } else {
        initPortalHomepage();
    }

    // ============================================================
    // UNIFIED HEADER CATEGORY ROUTING
    // ============================================================
    const unifiedNavLinks = document.querySelectorAll('.ts-unified-nav-link, .ts-unified-dropdown-link');
    if (unifiedNavLinks.length > 0) {
        unifiedNavLinks.forEach(link => {
            link.addEventListener('click', function (e) {
                const filterVal = this.dataset.filter;
                if (!filterVal) return;

                const portalFront = document.getElementById('ts-portal-front');
                if (portalFront) {
                    // We are on Homepage, just trigger the local filter logic smoothly
                    e.preventDefault();
                    const targetBtn = document.querySelector(`.ts-portal-cat-btn[data-filter="${filterVal}"]`) || document.querySelector(`.ts-dropdown-item[data-filter="${filterVal}"]`);
                    if (targetBtn) {
                        targetBtn.click();
                        const categoriesContainer = document.querySelector('.ts-portal-categories');
                        if (categoriesContainer) {
                            categoriesContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }

                        // Manage active states on the header itself
                        unifiedNavLinks.forEach(l => l.classList.remove('ts-nav-active'));
                        this.classList.add('ts-nav-active');
                    } else {
                        window.location.href = this.href;
                    }
                }
            });
        });

        // Auto-apply filter if URL param exists (when redirected from another page)
        const filterParam = new URLSearchParams(window.location.search).get('filter');
        if (filterParam && document.getElementById('ts-portal-front')) {
            setTimeout(() => {
                const targetBtn = document.querySelector(`.ts-portal-cat-btn[data-filter="${filterParam}"]`) || document.querySelector(`.ts-dropdown-item[data-filter="${filterParam}"]`);
                if (targetBtn) {
                    targetBtn.click();
                    const categoriesContainer = document.querySelector('.ts-portal-categories');
                    if (categoriesContainer) {
                        categoriesContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                    // Manage active states on the header
                    unifiedNavLinks.forEach(l => {
                        if (l.dataset.filter === filterParam) {
                            l.classList.add('ts-nav-active');
                        } else {
                            l.classList.remove('ts-nav-active');
                        }
                    });
                }
            }, 100);
        }
    }

    // Auto-apply search keyword if URL param exists
    const searchParam = new URLSearchParams(window.location.search).get('tqp_search');
    if (searchParam && document.getElementById('ts-portal-front')) {
        setTimeout(() => {
            const searchInput = document.getElementById('ts-portal-search-input');
            if (searchInput) {
                searchInput.value = searchParam;
                // Trigger input event so the listener inside initPortalHomepage fires
                searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                // Scroll to search area smoothly
                const searchContainer = document.querySelector('.ts-portal-search');
                if (searchContainer) {
                    searchContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        }, 150);
    }
});
