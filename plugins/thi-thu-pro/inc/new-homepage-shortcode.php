<?php
if (!defined('ABSPATH')) exit;

add_shortcode('tqp_new_homepage', 'tqp_render_new_homepage');

function tqp_render_new_homepage($atts) {
    // Attributes can be added if needed later
    ob_start();
    ?>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --nh-primary: #3b82f6;
            --nh-primary-dark: #2563eb;
            --nh-secondary: #f43f5e;
            --nh-bg: #f8fafc;
            --nh-surface: #ffffff;
            --nh-text-main: #0f172a;
            --nh-text-muted: #64748b;
            --nh-border: #e2e8f0;
            --nh-radius-lg: 24px;
            --nh-radius-md: 16px;
            --nh-radius-sm: 8px;
            --nh-shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
            --nh-shadow-md: 0 10px 30px rgba(0,0,0,0.05);
            --nh-shadow-lg: 0 20px 40px rgba(59,130,246,0.1);
            --nh-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .nh-wrapper {
            font-family: 'Inter', sans-serif;
            background-color: var(--nh-bg);
            color: var(--nh-text-main);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .nh-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* --- Protect Flatsome Header from Tailwind --- */
        #header .container,
        #footer .container,
        .header-wrapper .container,
        .footer-wrapper .container {
            max-width: 1080px !important;
            padding-left: 15px !important;
            padding-right: 15px !important;
            margin-left: auto !important;
            margin-right: auto !important;
        }
        
        @media (min-width: 1200px) {
            #header .container,
            #footer .container,
            .header-wrapper .container,
            .footer-wrapper .container {
                max-width: 1190px !important;
            }
        }

        /* --- Utilities --- */
        .nh-gradient-text {
            background: linear-gradient(135deg, var(--nh-primary), var(--nh-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            color: transparent;
        }

        .nh-section {
            padding: 80px 0;
        }

        .nh-section-header {
            text-align: center;
            margin-bottom: 48px;
        }

        .nh-section-title {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 16px;
            letter-spacing: -0.5px;
        }

        .nh-section-subtitle {
            font-size: 18px;
            color: var(--nh-text-muted);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Hide WP Admin Bar for a clean design */
        #wpadminbar { display: none !important; }
        html { margin-top: 0 !important; }

        /* --- Hero Section --- */
        .nh-hero {
            padding: 80px 0 60px;
            position: relative;
            background: #ffffff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .nh-hero-content {
            text-align: center;
            max-width: 1100px;
            margin: 0 auto;
            width: 100%;
        }

        .nh-hero-title {
            font-size: 56px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 16px;
            color: #111827;
            letter-spacing: -1px;
        }

        .nh-hero-desc {
            font-size: 18px;
            color: #6b7280;
            margin-bottom: 48px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        /* 4 Feature Cards */
        .nh-hero-features {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 60px;
            text-align: left;
        }
        .nh-hero-feature-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid #f3f4f6;
        }
        .nh-hero-feature-card:nth-child(1) { background: #eff6ff; border-color: #bfdbfe; }
        .nh-hero-feature-card:nth-child(2) { background: #f0fdf4; border-color: #bbf7d0; }
        .nh-hero-feature-card:nth-child(3) { background: #faf5ff; border-color: #e9d5ff; }
        .nh-hero-feature-card:nth-child(4) { background: #fff7ed; border-color: #fed7aa; }

        .nh-hero-feature-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 18px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .nh-hero-feature-card:nth-child(1) .nh-hero-feature-icon { color: #3b82f6; }
        .nh-hero-feature-card:nth-child(2) .nh-hero-feature-icon { color: #10b981; }
        .nh-hero-feature-card:nth-child(3) .nh-hero-feature-icon { color: #a855f7; }
        .nh-hero-feature-card:nth-child(4) .nh-hero-feature-icon { color: #f97316; }

        .nh-hero-feature-title {
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 12px;
            color: #1f2937;
            line-height: 1.4;
        }
        .nh-hero-feature-desc {
            font-size: 14px;
            color: #4b5563;
            line-height: 1.5;
        }

        /* Stats Banner */
        .nh-stats-banner {
            background: #fff7ed;
            border-radius: 999px;
            padding: 16px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 80px;
            box-shadow: 0 4px 6px -1px rgba(234, 88, 12, 0.05);
            border: 1px solid #ffedd5;
        }
        .nh-stats-title {
            font-weight: 800;
            font-size: 18px;
            color: #111827;
        }
        .nh-stats-items {
            display: flex;
            gap: 40px;
            align-items: center;
        }
        .nh-stat-item {
            text-align: center;
            display: flex;
            flex-direction: column;
        }
        .nh-stat-value {
            font-weight: 800;
            font-size: 24px;
            color: #ea580c;
            line-height: 1;
            margin-bottom: 4px;
        }
        .nh-stat-label {
            font-size: 13px;
            color: #6b7280;
        }
        .nh-stats-btn {
            background: #ea580c;
            color: white;
            padding: 12px 24px;
            border-radius: 99px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
        }
        .nh-stats-btn:hover {
            background: #c2410c;
            color: white;
        }

        /* Search Section */
        .nh-search-section {
            text-align: center;
        }
        .nh-search-title {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 12px;
            color: #111827;
        }
        .nh-search-subtitle {
            font-size: 16px;
            color: #6b7280;
            margin-bottom: 32px;
        }
        .nh-search-box {
            display: flex;
            align-items: center;
            background: #f8fafc !important;
            padding: 6px 6px 6px 24px;
            border-radius: 999px;
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ea580c;
            transition: all 0.3s;
        }
        .nh-search-box:focus-within {
            box-shadow: 0 4px 15px rgba(234,88,12,0.15);
        }
        .nh-search-icon {
            color: #9ca3af;
            font-size: 20px;
            margin-right: 12px;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
        }
        .nh-search-input {
            flex: 1;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            font-size: 16px !important;
            background: transparent !important;
            color: #111827 !important;
            margin: 0 !important;
            padding: 10px 0 !important;
            line-height: normal !important;
        }
        .nh-search-btn {
            background: #ea580c !important;
            color: white !important;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            padding: 10px 32px !important;
            border-radius: 999px !important;
            font-size: 15px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            margin: 0 !important;
            line-height: normal !important;
            transition: all 0.2s;
        }
        .nh-search-btn:hover {
            background: #c2410c;
        }
        
        @media (max-width: 1024px) {
            .nh-hero-features { grid-template-columns: repeat(2, 1fr); }
            .nh-stats-banner { flex-direction: column; gap: 20px; text-align: center; border-radius: 24px; padding: 24px; }
            .nh-stats-items { flex-wrap: wrap; justify-content: center; }
        }
        @media (max-width: 768px) {
            .nh-hero-features { grid-template-columns: 1fr; }
        }

        /* --- Featured Exams (Tabs) --- */
        .nh-tabs-header {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin-bottom: 40px;
        }

        .nh-tab-btn {
            background: transparent;
            color: #64748b;
            border: none;
            cursor: pointer;
            box-shadow: none;
        }

        .nh-tab-btn:hover {
            color: #0f172a;
        }

        .nh-tab-btn.active {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }

        .nh-tab-content {
            display: none;
            animation: nh-fade-in 0.4s ease forwards;
        }

        .nh-tab-content.active {
            display: block;
        }

        @keyframes nh-fade-in {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .nh-grid-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }

        .nh-card {
            background: var(--nh-surface);
            border-radius: var(--nh-radius-lg);
            padding: 24px;
            box-shadow: var(--nh-shadow-md);
            border: 1px solid rgba(0,0,0,0.02);
            transition: var(--nh-transition);
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
        }

        .nh-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--nh-shadow-lg);
            border-color: rgba(59,130,246,0.1);
        }

        .nh-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(59,130,246,0.1);
            color: var(--nh-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 20px;
        }

        .nh-card-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .nh-card-desc {
            font-size: 14px;
            color: var(--nh-text-muted);
            margin-bottom: 20px;
            flex-grow: 1;
        }

        .nh-card-arrow {
            align-self: flex-end;
            color: var(--nh-text-muted);
            font-size: 14px;
            transition: var(--nh-transition);
        }

        .nh-card:hover .nh-card-arrow {
            color: var(--nh-primary);
            transform: translateX(5px);
        }

        /* --- University Exams --- */
        .nh-uni-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
        }

        .nh-uni-card {
            background: var(--nh-surface);
            border-radius: var(--nh-radius-md);
            padding: 32px 16px;
            text-align: center;
            box-shadow: var(--nh-shadow-md);
            transition: var(--nh-transition);
            text-decoration: none;
            color: inherit;
        }

        .nh-uni-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--nh-shadow-lg);
        }

        .nh-uni-icon {
            font-size: 40px;
            margin-bottom: 16px;
        }

        .nh-uni-title {
            font-size: 16px;
            font-weight: 600;
        }

        /* --- Learning Path --- */
        .nh-path-container {
            display: flex;
            justify-content: space-between;
            position: relative;
            padding: 40px 0;
            overflow-x: auto;
        }

        .nh-path-line {
            position: absolute;
            top: 64px;
            left: 5%;
            right: 5%;
            height: 2px;
            background: var(--nh-border);
            z-index: 1;
        }

        .nh-path-step {
            position: relative;
            z-index: 2;
            text-align: center;
            width: 15%;
            min-width: 120px;
        }

        .nh-path-icon-wrapper {
            width: 48px;
            height: 48px;
            background: var(--nh-surface);
            border: 2px solid var(--nh-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            color: var(--nh-primary);
            font-size: 20px;
            box-shadow: 0 0 0 8px var(--nh-bg);
            transition: var(--nh-transition);
        }

        .nh-path-step:hover .nh-path-icon-wrapper {
            background: var(--nh-primary);
            color: white;
            transform: scale(1.1);
        }

        .nh-path-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--nh-text-main);
        }

        /* --- Value Props --- */
        .nh-value-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
        }

        .nh-value-item {
            display: flex;
            gap: 16px;
        }

        .nh-value-icon {
            width: 56px;
            height: 56px;
            flex-shrink: 0;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(59,130,246,0.1), rgba(244,63,94,0.1));
            color: var(--nh-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .nh-value-text h4 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .nh-value-text p {
            font-size: 14px;
            color: var(--nh-text-muted);
        }

        /* --- Testimonials --- */
        .nh-testi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }

        .nh-testi-card {
            background: var(--nh-surface);
            padding: 32px;
            border-radius: var(--nh-radius-lg);
            box-shadow: var(--nh-shadow-md);
            position: relative;
        }

        .nh-testi-quote {
            color: rgba(59,130,246,0.1);
            font-size: 40px;
            position: absolute;
            top: 24px;
            right: 24px;
        }

        .nh-testi-content {
            font-size: 15px;
            color: var(--nh-text-main);
            font-style: italic;
            margin-bottom: 24px;
            position: relative;
            z-index: 2;
        }

        .nh-testi-author {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .nh-testi-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .nh-testi-info h5 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .nh-testi-info span {
            font-size: 13px;
            color: var(--nh-text-muted);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .nh-uni-grid { grid-template-columns: repeat(3, 1fr); }
            .nh-value-grid { grid-template-columns: repeat(2, 1fr); }
            .nh-hero-title { font-size: 48px; }
        }

        @media (max-width: 768px) {
            .nh-uni-grid { grid-template-columns: repeat(2, 1fr); }
            .nh-value-grid { grid-template-columns: 1fr; }
            .nh-path-container { flex-wrap: wrap; gap: 24px; }
            .nh-path-step { width: 30%; }
            .nh-path-line { display: none; }
            .nh-hero-title { font-size: 36px; }
            .nh-hero { padding: 80px 0 60px; }
            .nh-search-box { flex-direction: column; padding: 8px; border-radius: var(--nh-radius-md); }
            .nh-search-btn { width: 100%; border-radius: var(--nh-radius-sm); margin-top: 8px; }
            .nh-search-input { width: 100%; padding: 12px; }
        }
    </style>
    <!-- Add Tailwind CSS support without overriding Flatsome's container -->
    <script>
      tailwind = {
        config: {
          corePlugins: {
            preflight: false,
            container: false,
          }
        }
      };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>

    <div class="nh-wrapper">
        <!-- 1. Hero Banner & Features & Search -->
        <section class="nh-hero">
            <div class="nh-container">
                <div class="nh-hero-content">
                    <h1 class="nh-hero-title">
                        Luyện thi thông minh,<br>
                        <span class="nh-gradient-text">Bứt phá điểm số</span>
                    </h1>
                    <p class="nh-hero-desc">
                        Trắc Nghiệm AZ là nền tảng luyện thi trực tuyến chuyên về các bài tập và đề thi trắc nghiệm, được hàng triệu học sinh, sinh viên và các thầy cô giáo tin dùng trên khắp Việt Nam. Chúng tôi cung cấp kho đề thi khổng lồ hoàn toàn miễn phí — từ phổ thông, đại học đến các chứng chỉ quốc tế uy tín.
                    </p>

                    <!-- 4 Feature Cards -->
                    <div class="nh-hero-features">
                        <div class="nh-hero-feature-card">
                            <div class="nh-hero-feature-icon"><i class="fa-solid fa-book-open"></i></div>
                            <h3 class="nh-hero-feature-title">Tổng hợp đầy đủ các cấp độ</h3>
                            <p class="nh-hero-feature-desc">Từ lớp 1 đến lớp 12, đại học, chứng chỉ quốc tế và thi công chức – tất cả đề thi được phân loại rõ ràng theo môn học, cấp độ và dạng bài.</p>
                        </div>
                        <div class="nh-hero-feature-card">
                            <div class="nh-hero-feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
                            <h3 class="nh-hero-feature-title">Hệ thống thi thử đạt chuẩn</h3>
                            <p class="nh-hero-feature-desc">Đề thi được biên soạn bởi đội ngũ chuyên gia giáo dục, cập nhật sát với chương trình và cấu trúc đề thi chính thức mỗi năm.</p>
                        </div>
                        <div class="nh-hero-feature-card">
                            <div class="nh-hero-feature-icon"><i class="fa-solid fa-globe"></i></div>
                            <h3 class="nh-hero-feature-title">Giao diện thân thiện, dễ dùng</h3>
                            <p class="nh-hero-feature-desc">Thiết kế trực quan, tối ưu trên mọi thiết bị. Học viên mọi lứa tuổi đều có thể sử dụng ngay mà không cần hướng dẫn.</p>
                        </div>
                        <div class="nh-hero-feature-card">
                            <div class="nh-hero-feature-icon"><i class="fa-solid fa-users-rays"></i></div>
                            <h3 class="nh-hero-feature-title">Cộng đồng học tập sôi động</h3>
                            <p class="nh-hero-feature-desc">Hơn 2.8 triệu người dùng mỗi tháng chia sẻ kinh nghiệm, tài liệu và động viên nhau trong hành trình chinh phục các kỳ thi.</p>
                        </div>
                    </div>

                    <!-- Stats Banner -->
                    <div class="nh-stats-banner">
                        <div class="nh-stats-title">Lựa chọn hàng đầu của học sinh & giáo viên Việt Nam</div>
                        <div class="nh-stats-items">
                            <div class="nh-stat-item">
                                <span class="nh-stat-value">50K+</span>
                                <span class="nh-stat-label">Đề thi</span>
                            </div>
                            <div class="nh-stat-item">
                                <span class="nh-stat-value">2.8M+</span>
                                <span class="nh-stat-label">Người dùng/tháng</span>
                            </div>
                            <div class="nh-stat-item">
                                <span class="nh-stat-value">100%</span>
                                <span class="nh-stat-label">Miễn phí</span>
                            </div>
                            <a href="#" class="nh-stats-btn">Tìm hiểu thêm &rarr;</a>
                        </div>
                    </div>

                    <!-- Search Section -->
                    <div class="nh-search-section">
                        <h2 class="nh-search-title">Tìm đề thi phù hợp với bạn</h2>
                        <p class="nh-search-subtitle">Lọc theo loại bài thi, cấp độ và thời lượng để tìm đúng đề thi bạn cần ngay lập tức.</p>
                        <!-- Note: Thay action bằng link tới trang danh sách đề thi của bạn nếu cần -->
                        <form action="<?php echo esc_url(home_url('/search/')); ?>" method="GET" class="nh-search-box">
                            <i class="fa-solid fa-magnifying-glass nh-search-icon"></i>
                            <input type="text" name="tqp_search" class="nh-search-input" placeholder="Tìm kiếm: IELTS, MBTI, JLPT, Thi công chức..." required>
                            <button type="submit" class="nh-search-btn">Tìm</button>
                        </form>
                    </div>

                </div>
            </div>
        </section>

        <!-- 2. Featured Exams -->
        <section class="nh-section">
            <div class="nh-container">
                <div class="nh-section-header">
                    <h2 class="nh-section-title">Khám phá bài thi nổi bật</h2>
                    <p class="nh-section-subtitle">Lựa chọn hàng ngàn đề thi thử từ các chứng chỉ quốc tế và các bài test khám phá bản thân.</p>
                </div>

                <div class="flex justify-center mb-10">
                    <div class="bg-slate-100 p-1 rounded-2xl flex gap-1">
                        <button class="px-6 py-2.5 rounded-xl text-sm font-bold transition-all hover:text-slate-900 nh-tab-btn active" data-target="tab-ngoai-ngu" style="border: none; outline: none;">🌏 Luyện thi ngoại ngữ</button>
                        <button class="px-6 py-2.5 rounded-xl text-sm font-bold transition-all text-slate-500 hover:text-slate-900 nh-tab-btn" data-target="tab-tinh-cach" style="border: none; outline: none;">🧠 Trắc nghiệm tính cách</button>
                    </div>
                </div>

                <!-- Tab: Luyện thi ngoại ngữ -->
                <div class="nh-tab-content active" id="tab-ngoai-ngu">
                    <div class="nh-grid-cards">
                        <a href="<?php echo esc_url(home_url('/thi-thu-ielts/')); ?>" class="nh-card">
                            <div class="nh-card-icon" style="color: #ef4444; background: rgba(239,68,68,0.1);"><i class="fa-brands fa-diaspora"></i></div>
                            <h3 class="nh-card-title">IELTS</h3>
                            <p class="nh-card-desc">Chuẩn bị cho kỳ thi IELTS Academic & General với các đề thi chuẩn cấu trúc.</p>
                            <span class="nh-card-arrow">Luyện đề ngay <i class="fa-solid fa-arrow-right"></i></span>
                        </a>
                        <a href="<?php echo esc_url(home_url('/thi-thu-toeic/')); ?>" class="nh-card">
                            <div class="nh-card-icon" style="color: #3b82f6; background: rgba(59,130,246,0.1);"><i class="fa-solid fa-briefcase"></i></div>
                            <h3 class="nh-card-title">TOEIC</h3>
                            <p class="nh-card-desc">Luyện thi chứng chỉ tiếng Anh giao tiếp quốc tế dành cho người đi làm.</p>
                            <span class="nh-card-arrow">Luyện đề ngay <i class="fa-solid fa-arrow-right"></i></span>
                        </a>
                        <a href="<?php echo esc_url(home_url('/thi-thu-topik/')); ?>" class="nh-card">
                            <div class="nh-card-icon" style="color: #ec4899; background: rgba(236,72,153,0.1);"><i class="fa-solid fa-globe"></i></div>
                            <h3 class="nh-card-title">TOPIK</h3>
                            <p class="nh-card-desc">Kỳ thi Năng lực Tiếng Hàn (TOPIK I & II) do NIIED tổ chức.</p>
                            <span class="nh-card-arrow">Luyện đề ngay <i class="fa-solid fa-arrow-right"></i></span>
                        </a>
                        <a href="<?php echo esc_url(home_url('/thi-thu-hsk/')); ?>" class="nh-card">
                            <div class="nh-card-icon" style="color: #eab308; background: rgba(234,179,8,0.1);"><i class="fa-solid fa-yin-yang"></i></div>
                            <h3 class="nh-card-title">HSK</h3>
                            <p class="nh-card-desc">Bài thi đánh giá Năng lực Tiếng Trung (HSK 1 đến HSK 6).</p>
                            <span class="nh-card-arrow">Luyện đề ngay <i class="fa-solid fa-arrow-right"></i></span>
                        </a>
                        <a href="<?php echo esc_url(home_url('/thi-thu-jlpt/')); ?>" class="nh-card">
                            <div class="nh-card-icon" style="color: #ef4444; background: rgba(239,68,68,0.1);"><i class="fa-brands fa-pagelines"></i></div>
                            <h3 class="nh-card-title">JLPT</h3>
                            <p class="nh-card-desc">Kỳ thi Đánh giá năng lực tiếng Nhật (N5 đến N1).</p>
                            <span class="nh-card-arrow">Luyện đề ngay <i class="fa-solid fa-arrow-right"></i></span>
                        </a>
                        <a href="<?php echo esc_url(home_url('/thi-thu-vstep/')); ?>" class="nh-card">
                            <div class="nh-card-icon" style="color: #06b6d4; background: rgba(6,182,212,0.1);"><i class="fa-solid fa-star"></i></div>
                            <h3 class="nh-card-title">VSTEP</h3>
                            <p class="nh-card-desc">Chứng chỉ tiếng Anh theo khung năng lực 6 bậc dùng cho Việt Nam.</p>
                            <span class="nh-card-arrow">Luyện đề ngay <i class="fa-solid fa-arrow-right"></i></span>
                        </a>
                    </div>
                </div>

                <!-- Tab: Trắc nghiệm tính cách -->
                <div class="nh-tab-content" id="tab-tinh-cach">
                    <div class="nh-grid-cards">
                        <a href="<?php echo esc_url(home_url('/test-mbti/')); ?>" class="nh-card">
                            <div class="nh-card-icon" style="color: #8b5cf6; background: rgba(139,92,246,0.1);"><i class="fa-solid fa-brain"></i></div>
                            <h3 class="nh-card-title">MBTI</h3>
                            <p class="nh-card-desc">Trắc nghiệm tính cách gồm 16 nhóm giúp định hướng nghề nghiệp chuẩn xác.</p>
                            <span class="nh-card-arrow">Test ngay <i class="fa-solid fa-arrow-right"></i></span>
                        </a>
                        <a href="<?php echo esc_url(home_url('/test-holland/')); ?>" class="nh-card">
                            <div class="nh-card-icon" style="color: #f97316; background: rgba(249,115,22,0.1);"><i class="fa-solid fa-compass"></i></div>
                            <h3 class="nh-card-title">Holland</h3>
                            <p class="nh-card-desc">Mô hình Holland Codes giúp bạn tìm ra môi trường làm việc lý tưởng.</p>
                            <span class="nh-card-arrow">Test ngay <i class="fa-solid fa-arrow-right"></i></span>
                        </a>
                        <a href="<?php echo esc_url(home_url('/test-iq/')); ?>" class="nh-card">
                            <div class="nh-card-icon" style="color: #10b981; background: rgba(16,185,129,0.1);"><i class="fa-solid fa-lightbulb"></i></div>
                            <h3 class="nh-card-title">Test IQ</h3>
                            <p class="nh-card-desc">Đo lường chỉ số thông minh logic và tư duy phân tích của não bộ.</p>
                            <span class="nh-card-arrow">Test ngay <i class="fa-solid fa-arrow-right"></i></span>
                        </a>
                        <a href="<?php echo esc_url(home_url('/test-eq/')); ?>" class="nh-card">
                            <div class="nh-card-icon" style="color: #f43f5e; background: rgba(244,63,94,0.1);"><i class="fa-solid fa-heart-pulse"></i></div>
                            <h3 class="nh-card-title">Test EQ</h3>
                            <p class="nh-card-desc">Kiểm tra chỉ số trí tuệ cảm xúc, khả năng thấu hiểu bản thân và người khác.</p>
                            <span class="nh-card-arrow">Test ngay <i class="fa-solid fa-arrow-right"></i></span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. University Exams -->
        <section class="py-20 bg-gradient-to-b from-white to-slate-50/60" style="background: white;">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 mb-12">
                    <div>
                        <h2 class="text-3xl lg:text-4xl font-black text-slate-900 mb-3">Đề trắc nghiệm các môn Đại học</h2>
                        <p class="text-slate-500 max-w-xl leading-relaxed">Tổng hợp hơn <strong class="text-slate-900">9.500+ đề thi</strong> chia theo nhóm môn học — từ Chính trị, Đại cương đến Chuyên ngành và Ngoại ngữ. Ôn đúng trọng tâm, chinh phục mọi kỳ thi đại học.</p>
                    </div>
                    <a href="<?php echo esc_url(home_url('/dai-hoc/')); ?>" class="self-start lg:self-auto flex items-center gap-2 px-5 py-2.5 border-2 border-blue-600 text-blue-600 font-bold rounded-xl hover:bg-blue-600 hover:text-white transition-all text-sm flex-shrink-0" style="text-decoration: none;">
                        Xem toàn bộ đề 
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="m9 18 6-6-6-6"></path></svg>
                    </a>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-10">
                    <!-- Card 1 -->
                    <a href="<?php echo esc_url(home_url('/dai-hoc/')); ?>" class="rounded-3xl border-2 border-red-200 bg-white p-5 cursor-pointer group transition-all hover:shadow-xl hover:-translate-y-1 flex flex-col" style="text-decoration: none;">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-red-500 to-rose-600 flex items-center justify-center text-2xl shadow-md">🏛️</div>
                        </div>
                        <h3 class="font-black text-slate-900 text-base mb-1.5 group-hover:text-red-600 transition-colors">Môn Chính trị</h3>
                        <p class="text-xs text-slate-500 leading-relaxed mb-4">Các môn lý luận chính trị bắt buộc trong chương trình đại học toàn quốc.</p>
                        <div class="w-full py-2.5 rounded-xl text-xs font-bold transition-all mt-auto bg-red-50 text-red-700 group-hover:opacity-80 flex items-center justify-center gap-1">
                            Xem đề thi
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform"><path d="m9 18 6-6-6-6"></path></svg>
                        </div>
                    </a>
                    
                    <!-- Card 2 -->
                    <a href="<?php echo esc_url(home_url('/dai-hoc/')); ?>" class="rounded-3xl border-2 border-blue-200 bg-white p-5 cursor-pointer group transition-all hover:shadow-xl hover:-translate-y-1 flex flex-col" style="text-decoration: none;">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center text-2xl shadow-md">📐</div>
                        </div>
                        <h3 class="font-black text-slate-900 text-base mb-1.5 group-hover:text-blue-600 transition-colors">Môn Đại cương</h3>
                        <p class="text-xs text-slate-500 leading-relaxed mb-4">Nền tảng khoa học cơ bản cho sinh viên năm nhất, năm hai mọi ngành.</p>
                        <div class="w-full py-2.5 rounded-xl text-xs font-bold transition-all mt-auto bg-blue-50 text-blue-700 group-hover:opacity-80 flex items-center justify-center gap-1">
                            Xem đề thi
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform"><path d="m9 18 6-6-6-6"></path></svg>
                        </div>
                    </a>

                    <!-- Card 3 -->
                    <a href="<?php echo esc_url(home_url('/dai-hoc/')); ?>" class="rounded-3xl border-2 border-emerald-200 bg-white p-5 cursor-pointer group transition-all hover:shadow-xl hover:-translate-y-1 flex flex-col" style="text-decoration: none;">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-2xl shadow-md">🔬</div>
                        </div>
                        <h3 class="font-black text-slate-900 text-base mb-1.5 group-hover:text-emerald-600 transition-colors">Môn Cơ sở ngành</h3>
                        <p class="text-xs text-slate-500 leading-relaxed mb-4">Kiến thức nền tảng theo ngành, chuẩn bị cho học phần chuyên sâu.</p>
                        <div class="w-full py-2.5 rounded-xl text-xs font-bold transition-all mt-auto bg-emerald-50 text-emerald-700 group-hover:opacity-80 flex items-center justify-center gap-1">
                            Xem đề thi
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform"><path d="m9 18 6-6-6-6"></path></svg>
                        </div>
                    </a>

                    <!-- Card 4 -->
                    <a href="<?php echo esc_url(home_url('/dai-hoc/')); ?>" class="rounded-3xl border-2 border-violet-200 bg-white p-5 cursor-pointer group transition-all hover:shadow-xl hover:-translate-y-1 flex flex-col" style="text-decoration: none;">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center text-2xl shadow-md">🎓</div>
                        </div>
                        <h3 class="font-black text-slate-900 text-base mb-1.5 group-hover:text-violet-600 transition-colors">Môn Chuyên ngành</h3>
                        <p class="text-xs text-slate-500 leading-relaxed mb-4">Đề thi chuyên sâu riêng từng ngành học và chương trình đào tạo.</p>
                        <div class="w-full py-2.5 rounded-xl text-xs font-bold transition-all mt-auto bg-violet-50 text-violet-700 group-hover:opacity-80 flex items-center justify-center gap-1">
                            Xem đề thi
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform"><path d="m9 18 6-6-6-6"></path></svg>
                        </div>
                    </a>

                    <!-- Card 5 -->
                    <a href="<?php echo esc_url(home_url('/dai-hoc/')); ?>" class="rounded-3xl border-2 border-amber-200 bg-white p-5 cursor-pointer group transition-all hover:shadow-xl hover:-translate-y-1 flex flex-col" style="text-decoration: none;">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-500 flex items-center justify-center text-2xl shadow-md">🌐</div>
                        </div>
                        <h3 class="font-black text-slate-900 text-base mb-1.5 group-hover:text-amber-600 transition-colors">Môn Ngoại ngữ</h3>
                        <p class="text-xs text-slate-500 leading-relaxed mb-4">Tiếng Anh và các ngoại ngữ chuyên ngành theo chuẩn đầu ra đại học.</p>
                        <div class="w-full py-2.5 rounded-xl text-xs font-bold transition-all mt-auto bg-amber-50 text-amber-700 group-hover:opacity-80 flex items-center justify-center gap-1">
                            Xem đề thi
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform"><path d="m9 18 6-6-6-6"></path></svg>
                        </div>
                    </a>
                </div>
            </div>
        </section>

        <!-- 4. Learning Path -->
        <section id="roadmap" class="py-20 bg-slate-900 text-white relative overflow-hidden mt-10">
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-blue-500/10 rounded-full blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-[400px] h-[400px] bg-violet-500/10 rounded-full blur-3xl"></div>
            </div>
            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl lg:text-4xl font-black text-white text-center mb-3">Hành trình bứt phá điểm số</h2>
                <p class="text-white/60 text-center mb-16 max-w-2xl mx-auto">6 bước được thiết kế khoa học giúp bạn đạt được mục tiêu một cách nhanh nhất và hiệu quả nhất.</p>
                
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Step 1 -->
                    <div class="relative bg-white/5 backdrop-blur-sm border border-white/10 rounded-3xl p-6 hover:bg-white/10 transition-all group" style="opacity: 1; transform: none;">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-orange-500 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-lg group-hover:scale-110 transition-transform">
                                <i class="fa-solid fa-chart-column text-white text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <div class="text-xs text-white/40 font-bold mb-1 tracking-widest">BƯỚC 01</div>
                                <h3 class="font-bold text-white text-base mb-2">Kiểm tra trình độ đầu vào</h3>
                                <p class="text-white/60 text-sm leading-relaxed">Làm bài test đánh giá năng lực hiện tại trong 15-20 phút để xác định điểm xuất phát chính xác.</p>
                            </div>
                        </div>
                    </div>
                    <!-- Step 2 -->
                    <div class="relative bg-white/5 backdrop-blur-sm border border-white/10 rounded-3xl p-6 hover:bg-white/10 transition-all group" style="opacity: 1; transform: none;">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-violet-500 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-lg group-hover:scale-110 transition-transform">
                                <i class="fa-solid fa-bullseye text-white text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <div class="text-xs text-white/40 font-bold mb-1 tracking-widest">BƯỚC 02</div>
                                <h3 class="font-bold text-white text-base mb-2">Nhận lộ trình cá nhân hóa</h3>
                                <p class="text-white/60 text-sm leading-relaxed">Hệ thống AI phân tích điểm yếu và đề xuất lộ trình học tập tối ưu cho mục tiêu của bạn.</p>
                            </div>
                        </div>
                    </div>
                    <!-- Step 3 -->
                    <div class="relative bg-white/5 backdrop-blur-sm border border-white/10 rounded-3xl p-6 hover:bg-white/10 transition-all group" style="opacity: 1; transform: none;">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-blue-500 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-lg group-hover:scale-110 transition-transform">
                                <i class="fa-solid fa-book-open text-white text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <div class="text-xs text-white/40 font-bold mb-1 tracking-widest">BƯỚC 03</div>
                                <h3 class="font-bold text-white text-base mb-2">Luyện tập theo chủ đề</h3>
                                <p class="text-white/60 text-sm leading-relaxed">Luyện tập từng kỹ năng với bộ đề được chọn lọc kỹ càng, có giải thích chi tiết cho mỗi câu.</p>
                            </div>
                        </div>
                    </div>
                    <!-- Step 4 -->
                    <div class="relative bg-white/5 backdrop-blur-sm border border-white/10 rounded-3xl p-6 hover:bg-white/10 transition-all group" style="opacity: 1; transform: none;">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-emerald-500 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-lg group-hover:scale-110 transition-transform">
                                <i class="fa-solid fa-file-lines text-white text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <div class="text-xs text-white/40 font-bold mb-1 tracking-widest">BƯỚC 04</div>
                                <h3 class="font-bold text-white text-base mb-2">Thi thử toàn bộ đề</h3>
                                <p class="text-white/60 text-sm leading-relaxed">Thi thử đề thật với thời gian giới hạn, mô phỏng sát nhất điều kiện thi chính thức.</p>
                            </div>
                        </div>
                    </div>
                    <!-- Step 5 -->
                    <div class="relative bg-white/5 backdrop-blur-sm border border-white/10 rounded-3xl p-6 hover:bg-white/10 transition-all group" style="opacity: 1; transform: none;">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-rose-500 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-lg group-hover:scale-110 transition-transform">
                                <i class="fa-solid fa-arrow-trend-up text-white text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <div class="text-xs text-white/40 font-bold mb-1 tracking-widest">BƯỚC 05</div>
                                <h3 class="font-bold text-white text-base mb-2">Phân tích &amp; Cải thiện</h3>
                                <p class="text-white/60 text-sm leading-relaxed">Xem báo cáo chi tiết về điểm mạnh, điểm yếu và nhận gợi ý ôn tập cho lần thi sau.</p>
                            </div>
                        </div>
                    </div>
                    <!-- Step 6 -->
                    <div class="relative bg-white/5 backdrop-blur-sm border border-white/10 rounded-3xl p-6 hover:bg-white/10 transition-all group" style="opacity: 1; transform: none;">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-amber-500 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-lg group-hover:scale-110 transition-transform">
                                <i class="fa-solid fa-trophy text-white text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <div class="text-xs text-white/40 font-bold mb-1 tracking-widest">BƯỚC 06</div>
                                <h3 class="font-bold text-white text-base mb-2">Chinh phục mục tiêu</h3>
                                <p class="text-white/60 text-sm leading-relaxed">Đạt chứng chỉ quốc tế hoặc điểm số mơ ước với sự chuẩn bị toàn diện từ nền tảng.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 5. Value Proposition -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-16 items-center">
                    <div>
                        <h2 class="text-3xl lg:text-4xl font-black text-slate-900 mb-4">Tại sao luyện thi cùng TRACNGHIEMAZ?</h2>
                        <p class="text-slate-500 mb-8 leading-relaxed">Khác với các nền tảng trả phí khác, chúng tôi tin rằng giáo dục chất lượng phải là quyền lợi của tất cả mọi người. Đây là những gì làm chúng tôi khác biệt.</p>
                        <div class="space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="w-5 h-5 bg-orange-500/10 rounded-full flex items-center justify-center flex-shrink-0">
                                    <i class="fa-solid fa-circle-check text-orange-500 text-xs"></i>
                                </div>
                                <span class="text-slate-900 font-medium text-sm">Miễn phí hoàn toàn — không có phần Premium ẩn</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-5 h-5 bg-orange-500/10 rounded-full flex items-center justify-center flex-shrink-0">
                                    <i class="fa-solid fa-circle-check text-orange-500 text-xs"></i>
                                </div>
                                <span class="text-slate-900 font-medium text-sm">Đề thi cập nhật theo cấu trúc thi mới nhất</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-5 h-5 bg-orange-500/10 rounded-full flex items-center justify-center flex-shrink-0">
                                    <i class="fa-solid fa-circle-check text-orange-500 text-xs"></i>
                                </div>
                                <span class="text-slate-900 font-medium text-sm">Giải thích chi tiết cho mỗi đáp án</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-5 h-5 bg-orange-500/10 rounded-full flex items-center justify-center flex-shrink-0">
                                    <i class="fa-solid fa-circle-check text-orange-500 text-xs"></i>
                                </div>
                                <span class="text-slate-900 font-medium text-sm">Không quảng cáo gây mất tập trung</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-5 h-5 bg-orange-500/10 rounded-full flex items-center justify-center flex-shrink-0">
                                    <i class="fa-solid fa-circle-check text-orange-500 text-xs"></i>
                                </div>
                                <span class="text-slate-900 font-medium text-sm">Hỗ trợ đa nền tảng: Web, Mobile</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-white border border-slate-200 rounded-2xl p-5 hover:shadow-lg hover:shadow-black/5 transition-all group" style="opacity: 1; transform: none;">
                            <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                <i class="fa-solid fa-shield text-emerald-600 text-lg"></i>
                            </div>
                            <h4 class="font-bold text-sm text-slate-900 mb-1">Hoàn toàn miễn phí</h4>
                            <p class="text-xs text-slate-500 leading-relaxed">100% nội dung miễn phí, không quảng cáo gây phiền nhiễu. Cam kết duy trì vĩnh viễn.</p>
                        </div>
                        
                        <div class="bg-white border border-slate-200 rounded-2xl p-5 hover:shadow-lg hover:shadow-black/5 transition-all group" style="opacity: 1; transform: none;">
                            <div class="w-10 h-10 bg-orange-50 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                <i class="fa-solid fa-award text-orange-600 text-lg"></i>
                            </div>
                            <h4 class="font-bold text-sm text-slate-900 mb-1">Đề thi chuẩn xác</h4>
                            <p class="text-xs text-slate-500 leading-relaxed">50.000+ câu hỏi được biên soạn bởi chuyên gia, cập nhật theo cấu trúc đề thi mới nhất.</p>
                        </div>
                        
                        <div class="bg-white border border-slate-200 rounded-2xl p-5 hover:shadow-lg hover:shadow-black/5 transition-all group" style="opacity: 1; transform: none;">
                            <div class="w-10 h-10 bg-violet-50 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                <i class="fa-solid fa-brain text-violet-600 text-lg"></i>
                            </div>
                            <h4 class="font-bold text-sm text-slate-900 mb-1">AI thông minh</h4>
                            <p class="text-xs text-slate-500 leading-relaxed">Hệ thống AI phân tích điểm mạnh/yếu và tự động điều chỉnh lộ trình học tập cá nhân hóa.</p>
                        </div>
                        
                        <div class="bg-white border border-slate-200 rounded-2xl p-5 hover:shadow-lg hover:shadow-black/5 transition-all group" style="opacity: 1; transform: none;">
                            <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                <i class="fa-solid fa-globe text-blue-600 text-lg"></i>
                            </div>
                            <h4 class="font-bold text-sm text-slate-900 mb-1">Đa nền tảng</h4>
                            <p class="text-xs text-slate-500 leading-relaxed">Học mọi lúc, mọi nơi trên máy tính, điện thoại, máy tính bảng. Không cần cài đặt.</p>
                        </div>
                        
                        <div class="bg-white border border-slate-200 rounded-2xl p-5 hover:shadow-lg hover:shadow-black/5 transition-all group" style="opacity: 1; transform: none;">
                            <div class="w-10 h-10 bg-rose-50 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                <i class="fa-solid fa-chart-column text-rose-600 text-lg"></i>
                            </div>
                            <h4 class="font-bold text-sm text-slate-900 mb-1">Báo cáo chi tiết</h4>
                            <p class="text-xs text-slate-500 leading-relaxed">Theo dõi tiến trình học tập với biểu đồ trực quan, phân tích từng kỹ năng cụ thể.</p>
                        </div>
                        
                        <div class="bg-white border border-slate-200 rounded-2xl p-5 hover:shadow-lg hover:shadow-black/5 transition-all group" style="opacity: 1; transform: none;">
                            <div class="w-10 h-10 bg-teal-50 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                <i class="fa-solid fa-users text-teal-600 text-lg"></i>
                            </div>
                            <h4 class="font-bold text-sm text-slate-900 mb-1">Cộng đồng lớn</h4>
                            <p class="text-xs text-slate-500 leading-relaxed">Hơn 2.8 triệu người dùng mỗi tháng. Học hỏi và chia sẻ kinh nghiệm cùng cộng đồng.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 6. Testimonials -->
        <section class="py-20 bg-gradient-to-b from-orange-50/50 to-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl lg:text-4xl font-black text-slate-900 text-center mb-3">Học viên nói gì về chúng tôi?</h2>
                <p class="text-slate-500 text-center mb-12 max-w-2xl mx-auto">Hơn 2.8 triệu học viên từ giáo viên, phụ huynh đến học sinh đã tin tưởng luyện thi cùng TRACNGHIEMAZ.</p>
                <div class="max-w-4xl mx-auto">
                    <div class="flex justify-center gap-3 mb-10">
                        <button class="testi-nav-btn transition-all scale-110 ring-2 ring-orange-500 ring-offset-2 rounded-full p-0 border-0 bg-transparent" data-index="0">
                            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=80&h=80&fit=crop&auto=format" alt="Trần Minh Khoa" class="w-10 h-10 lg:w-12 lg:h-12 rounded-full object-cover bg-slate-100">
                        </button>
                        <button class="testi-nav-btn transition-all opacity-50 hover:opacity-80 rounded-full p-0 border-0 bg-transparent" data-index="1">
                            <img src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=80&h=80&fit=crop&auto=format" alt="Lê Thị Bảo Châu" class="w-10 h-10 lg:w-12 lg:h-12 rounded-full object-cover bg-slate-100">
                        </button>
                        <button class="testi-nav-btn transition-all opacity-50 hover:opacity-80 rounded-full p-0 border-0 bg-transparent" data-index="2">
                            <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=80&h=80&fit=crop&auto=format" alt="Phạm Đức Anh" class="w-10 h-10 lg:w-12 lg:h-12 rounded-full object-cover bg-slate-100">
                        </button>
                        <button class="testi-nav-btn transition-all opacity-50 hover:opacity-80 rounded-full p-0 border-0 bg-transparent" data-index="3">
                            <img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?w=80&h=80&fit=crop&auto=format" alt="Nguyễn Văn Phúc" class="w-10 h-10 lg:w-12 lg:h-12 rounded-full object-cover bg-slate-100">
                        </button>
                    </div>
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-xl shadow-black/5 p-8 lg:p-10 text-center relative" id="testi-card-wrapper" style="opacity: 1; transform: none; transition: all 0.3s ease;">
                        <div class="text-5xl text-orange-500/20 font-black leading-none mb-4">“</div>
                        <p id="testi-text" class="text-slate-900 text-lg leading-relaxed font-medium mb-6 max-w-2xl mx-auto">Giao diện thân thiện, dễ sử dụng. Mình thích nhất phần phân tích điểm yếu sau mỗi bài test, giúp mình tập trung ôn những phần còn kém.</p>
                        <div class="flex justify-center mb-4">
                            <div class="flex gap-1 text-amber-400 text-sm">
                                <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                            </div>
                        </div>
                        <div class="flex items-center justify-center gap-3">
                            <img id="testi-avatar" src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=80&h=80&fit=crop&auto=format" alt="Trần Minh Khoa" class="w-12 h-12 rounded-full object-cover ring-2 ring-orange-500/20 bg-slate-100">
                            <div class="text-left">
                                <div id="testi-name" class="font-bold text-slate-900">Trần Minh Khoa</div>
                                <div id="testi-role" class="text-sm text-slate-500">Sinh viên năm 3, ĐH Bách Khoa</div>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-center gap-3 mt-8">
                        <div id="testi-prev" class="w-8 h-8 rounded-xl border border-slate-200 flex items-center justify-center hover:bg-slate-100 hover:border-orange-500 transition-all text-slate-600 cursor-pointer">
                            <i class="fa-solid fa-chevron-left text-xs"></i>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="testi-dot rounded-full transition-all w-6 h-2 bg-orange-500 cursor-pointer" data-index="0"></div>
                            <div class="testi-dot rounded-full transition-all w-2 h-2 bg-slate-200 cursor-pointer hover:bg-slate-300" data-index="1"></div>
                            <div class="testi-dot rounded-full transition-all w-2 h-2 bg-slate-200 cursor-pointer hover:bg-slate-300" data-index="2"></div>
                            <div class="testi-dot rounded-full transition-all w-2 h-2 bg-slate-200 cursor-pointer hover:bg-slate-300" data-index="3"></div>
                        </div>
                        <div id="testi-next" class="w-8 h-8 rounded-xl border border-slate-200 flex items-center justify-center hover:bg-slate-100 hover:border-orange-500 transition-all text-slate-600 cursor-pointer">
                            <i class="fa-solid fa-chevron-right text-xs"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <script>
        /* JS logic - compressed to avoid wpautop issues */
        (function() {
            var tabBtns = document.querySelectorAll('.nh-tab-btn');
            var tabContents = document.querySelectorAll('.nh-tab-content');
            tabBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    tabBtns.forEach(function(b) { b.classList.remove('active'); });
                    tabContents.forEach(function(c) { c.classList.remove('active'); });
                    btn.classList.add('active');
                    var targetId = btn.getAttribute('data-target');
                    if(document.getElementById(targetId)) document.getElementById(targetId).classList.add('active');
                });
            });
            var testimonials = [
                { name: "Trần Minh Khoa", role: "Sinh viên năm 3, ĐH Bách Khoa", text: "Giao diện thân thiện, dễ sử dụng. Mình thích nhất phần phân tích điểm yếu sau mỗi bài test, giúp mình tập trung ôn những phần còn kém.", image: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=80&h=80&fit=crop&auto=format" },
                { name: "Lê Thị Bảo Châu", role: "Nhân viên văn phòng, TP.HCM", text: "Mình đang học tiếng Nhật và bộ đề JLPT trên TRACNGHIEMAZ thực sự rất sát với đề thi thật. Nhờ luyện tập mỗi ngày mà mình đã đỗ N3.", image: "https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=80&h=80&fit=crop&auto=format" },
                { name: "Phạm Đức Anh", role: "Lập trình viên", text: "Bài test tính cách MBTI và Holland rất bổ ích. Dù đã đi làm nhưng test lại vẫn thấy đúng. Các đề thi tiếng Anh chuyên ngành cũng rất chất lượng.", image: "https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=80&h=80&fit=crop&auto=format" },
                { name: "Nguyễn Văn Phúc", role: "Học sinh lớp 11, Đà Nẵng", text: "Hệ thống hoàn toàn miễn phí mà chất lượng lại ngang ngửa các trung tâm đắt tiền. Mình đã giới thiệu cho cả lớp cùng dùng.", image: "https://images.unsplash.com/photo-1560250097-0b93528c311a?w=80&h=80&fit=crop&auto=format" }
            ];
            var currentTesti = 0;
            var testiNavBtns = document.querySelectorAll('.testi-nav-btn');
            var testiDots = document.querySelectorAll('.testi-dot');
            var testiText = document.getElementById('testi-text');
            var testiName = document.getElementById('testi-name');
            var testiRole = document.getElementById('testi-role');
            var testiAvatar = document.getElementById('testi-avatar');
            var testiWrapper = document.getElementById('testi-card-wrapper');
            if(!testiWrapper) return;
            function updateTestimonial(index) {
                currentTesti = index;
                var data = testimonials[index];
                testiWrapper.style.opacity = 0;
                testiWrapper.style.transform = 'translateY(10px)';
                setTimeout(function() {
                    testiText.textContent = data.text;
                    testiName.textContent = data.name;
                    testiRole.textContent = data.role;
                    testiAvatar.src = data.image;
                    testiAvatar.alt = data.name;
                    testiNavBtns.forEach(function(btn, i) {
                        if (i === index) btn.className = "testi-nav-btn transition-all scale-110 ring-2 ring-orange-500 ring-offset-2 rounded-full p-0 border-0 bg-transparent";
                        else btn.className = "testi-nav-btn transition-all opacity-50 hover:opacity-80 rounded-full p-0 border-0 bg-transparent";
                    });
                    testiDots.forEach(function(dot, i) {
                        if (i === index) dot.className = "testi-dot rounded-full transition-all w-6 h-2 bg-orange-500 cursor-pointer";
                        else dot.className = "testi-dot rounded-full transition-all w-2 h-2 bg-slate-200 cursor-pointer hover:bg-slate-300";
                    });
                    testiWrapper.style.opacity = 1;
                    testiWrapper.style.transform = 'translateY(0)';
                }, 200);
            }
            document.getElementById('testi-prev').addEventListener('click', function() {
                var prev = currentTesti - 1;
                if (prev < 0) prev = testimonials.length - 1;
                updateTestimonial(prev);
            });
            document.getElementById('testi-next').addEventListener('click', function() {
                var next = currentTesti + 1;
                if (next >= testimonials.length) next = 0;
                updateTestimonial(next);
            });
            testiNavBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    updateTestimonial(parseInt(btn.getAttribute('data-index')));
                });
            });
            testiDots.forEach(function(dot) {
                dot.addEventListener('click', function() {
                    updateTestimonial(parseInt(dot.getAttribute('data-index')));
                });
            });
        })();
    </script>
    <?php
    return ob_get_clean();
}
