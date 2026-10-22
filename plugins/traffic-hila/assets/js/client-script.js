(function() {
    // 1. Tìm thông tin từ thẻ script hiện tại
    var scripts = document.getElementsByTagName('script');
    var currentScript = null;
    for (var i = 0; i < scripts.length; i++) {
        if (scripts[i].src && scripts[i].src.indexOf('client-script.js') !== -1) {
            currentScript = scripts[i];
            break;
        }
    }
    
    if (!currentScript) return;
    
    var urlObj = new URL(currentScript.src);
    var reqDomain = urlObj.searchParams.get('domain');
    var projectIdRaw = urlObj.searchParams.get('id'); // backward compatibility
    
    if (!reqDomain && !projectIdRaw) return;
    var projectId = projectIdRaw ? projectIdRaw.replace('TS-', '') : null;
    
    // Parse baseUrl để biết phải gọi API về đâu tự động
    // Ví dụ src: http://domain.com/wp-content/plugins/iauto-traffic-system/assets/js/client-script.js
    var baseUrl = currentScript.src.split('/wp-content/')[0];
    var apiInitUrl = baseUrl + '/wp-json/iats/v1/client/init';
    var apiGenerateUrl = baseUrl + '/wp-json/iats/v1/code/generate';
    
    // 2. Tạo phần tử nút "Lấy mã"
    var container = document.createElement('div');
    container.style.textAlign = 'center';
    container.style.margin = '20px auto';
    container.style.fontFamily = 'Arial, sans-serif';
    
    var btn = document.createElement('button');
    btn.innerText = "Lấy Mã Xác Nhận";
    btn.style.padding = "12px 24px";
    btn.style.background = "#ed1c24";
    btn.style.color = "#fff";
    btn.style.border = "none";
    btn.style.cursor = "pointer";
    btn.style.fontWeight = "bold";
    btn.style.fontSize = "16px";
    btn.style.borderRadius = "5px";
    btn.style.transition = "all 0.3s";
    
    var msgBox = document.createElement('div');
    msgBox.style.marginTop = "15px";
    
    container.appendChild(btn);
    container.appendChild(msgBox);
    
    // Chèn container giao diện vào div được chỉ định nếu có
    var targetDiv = document.getElementById('iats-traffic-btn');
    if (targetDiv) {
        targetDiv.appendChild(container);
    } else {
        currentScript.parentNode.insertBefore(container, currentScript);
    }
    
    // 3. Logic xử lý đếm ngược và lấy mã
    var countdown = 60; // 60 giây theo yêu cầu
    var timer = null;
    var isCounting = false;
    
    var boxConfig = {
        title: 'Mã của bạn là:',
        bg: '#e0f7fa',
        border: '#b2dfdb',
        textColor: '#555555',
        codeColor: '#00796b'
    };
    
    btn.onclick = function() {
        if (!isFromGoogle) {
            if (document.referrer === '') {
                alert("Hệ thống không tìm thấy nguồn Google.\n\nBạn phải tìm kiếm bài viết từ Google theo hướng dẫn (LƯU Ý: Vui lòng tắt chế độ Ẩn danh hoặc không copy link dán trực tiếp)");
            } else {
                alert("Hệ thống phát hiện bạn vào từ nguồn khác (" + document.referrer + ").\n\nBạn phải tìm kiếm bài viết từ Google theo đúng hướng dẫn để có thể lấy mã!");
            }
            return;
        }
        if (isCounting) return;
        
        // Bắt đầu đếm
        isCounting = true;
        btn.style.background = "#ccc";
        btn.style.color = "#666";
        btn.style.cursor = "not-allowed";
        
        timer = setInterval(function() {
            if (document.hidden) return; // Nếu đổi tab thì dừng đếm ngược
            countdown--;
            btn.innerText = "Vui lòng đợi " + countdown + " giây...";
            
            if (countdown <= 0) {
                clearInterval(timer);
                btn.innerText = "Đang tạo mã...";
                
                // Hết giờ -> Gọi API sinh mã
                var bodyData = {};
                if (reqDomain) bodyData.domain = reqDomain;
                if (projectId) bodyData.project_id = projectId;
                bodyData.url = window.location.href;

                fetch(apiGenerateUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(bodyData)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        btn.style.display = 'none'; // Ẩn nút đi
                        msgBox.innerHTML = '<div id="iats-code-box" style="background: ' + boxConfig.bg + '; border: 1px solid ' + boxConfig.border + '; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding:15px 30px; display:inline-block; border-radius:8px; cursor: pointer; transition: all 0.2s;" title="Nhấn để copy mã">' +
                                           '<span style="color:' + boxConfig.textColor + '; font-size:14px; display:block; margin-bottom:5px;">' + boxConfig.title + '</span>' + 
                                           '<b id="iats-code-text" style="font-size:32px; color:' + boxConfig.codeColor + '; letter-spacing:3px; text-shadow: 1px 1px 0px rgba(255,255,255,0.7);">' + data.code + '</b>' +
                                           '</div>';
                        
                        setTimeout(function() {
                            var box = document.getElementById("iats-code-box");
                            if(box) {
                                box.onclick = function() {
                                    navigator.clipboard.writeText(data.code).then(function() {
                                        box.style.transform = "scale(0.95)";
                                        setTimeout(function(){ box.style.transform = "scale(1)"; }, 150);
                                    }).catch(function(err) {
                                        // Fallback if clipboard fails
                                        var textArea = document.createElement("textarea");
                                        textArea.value = data.code;
                                        document.body.appendChild(textArea);
                                        textArea.select();
                                        document.execCommand("Copy");
                                        textArea.remove();
                                    });
                                };
                            }
                        }, 100);
                    } else {
                        btn.innerText = "Lấy Mã Xác Nhận";
                        isCounting = false;
                        btn.style.background = boxConfig.bg || '#E91E63';
                        btn.style.color = boxConfig.color || '#ffffff';
                        btn.style.cursor = "pointer";
                        alert(data.message);
                    }
                })
                .catch(err => {
                    btn.innerText = "Lỗi kết nối";
                    msgBox.innerHTML = '<span style="color:red;">Không thể kết nối tới máy chủ. Vui lòng thử lại.</span>';
                });
            }
        }, 1000); // 1000ms = 1s
    };
    
    // 4. Gọi API init để cấu hình giao diện nút (tuỳ chọn màu sắc) và kiểm tra URL
    var ref = document.referrer ? document.referrer.toLowerCase() : '';
    var isFromGoogle = (ref.indexOf('google.') !== -1);

    var initBody = {};
    if (reqDomain) initBody.domain = reqDomain;
    initBody.url = window.location.href;

    fetch(apiInitUrl, { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(initBody)
    }).then(res => res.json()).then(data => {
        if(data.success) {
            if(data.has_task === false) {
                container.style.display = 'none';
                return;
            }
            if(data.data) {
                btn.style.background = data.data.bg;
                btn.style.color = data.data.color;
                if(data.data.border_radius !== undefined) btn.style.borderRadius = data.data.border_radius + 'px';
                if(data.data.padding) btn.style.padding = data.data.padding;
                
                var logoHtml = data.data.logo ? '<img src="' + data.data.logo + '" onerror="this.style.display=\'none\'" style="width:30px; height:30px; vertical-align:middle; margin-right:8px; display:inline-block; border-radius:50%; object-fit:cover;">' : '';
                btn.innerHTML = logoHtml + '<span style="vertical-align:middle;">' + data.data.text + '</span>';
                
                if(data.data.box_bg) boxConfig.bg = data.data.box_bg;
                if(data.data.box_border) boxConfig.border = data.data.box_border;
                if(data.data.box_text_color) boxConfig.textColor = data.data.box_text_color;
                if(data.data.box_code_color) boxConfig.codeColor = data.data.box_code_color;
                if(data.data.box_title) boxConfig.title = data.data.box_title;
            }
        }
    }).catch(e=>{});
})();
