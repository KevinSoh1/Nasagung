<?php
session_start(); // 로그인 세션 정보를 읽기 위해 최상단에 필수 (전 버전 공통)

// 초기화 (비회원은 빈칸 또는 기본값으로 출력되어 직접 데이터 입력 가능)
$user_name = "";
$user_gender = "남성"; 
$user_birthdate_html = ""; 
$user_birth_time = "";
$user_calendar = "양력";

// 로그인한 사용자이면 DB에서 회원정보 조회
if (isset($_SESSION['user_email'])) {

	$user_email = $_SESSION['user_email'];
    $user_email = mysqli_real_escape_string($conn, $user_email);
    
    $user_type = $_SESSION['user_type'];
    $user_type = mysqli_real_escape_string($conn, $user_type);
    
    $sql = "SELECT * FROM nasagung_users WHERE email = '$user_email' AND provider = '$user_type'";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        // 💡 [PHP 5.2/5.3 이하 컴파일러 에러 원천 차단]
        // 구형 파서 충돌을 막기 위해 연산식을 완전히 쪼개어 단순 값 할당으로 매핑합니다.
        if (isset($user)) {
            if (isset($user['name'])) {
                $user_name = $user['name'];
            }
            if (isset($user['gender']) && $user['gender'] != '') {
                $user_gender = $user['gender'];
            }
            
            $b_year = isset($user['birthyear']) ? $user['birthyear'] : '';
            $b_day  = isset($user['birthday']) ? $user['birthday'] : '';
            if ($b_year != '' && $b_day != '') {
                $user_birthdate_html = $b_year . "-" . $b_day;
            }
            
            if (isset($user['birthtime'])) {
                $user_birth_time = $user['birthtime'];
            }
            if (isset($user['calendarType']) && $user['calendarType'] != '') {
                $user_calendar = $user['calendarType'];
            }
        }
    }
}

// 💡 HTML 인라인 태그 내부 숏코드 컴파일 오작동 방지용 사전 가공
$html_name_val = htmlspecialchars($user_name);
$html_birth_val = htmlspecialchars($user_birthdate_html);

$gender_hidden_val = "남성";
$css_male_class = "gender-btn active";
$css_female_class = "gender-btn";

if ($user_gender === 'female' || $user_gender === '여성') {
    $gender_hidden_val = "여성";
    $css_male_class = "gender-btn";
    $css_female_class = "gender-btn active";
}

$select_cal_1 = "";
$select_cal_2 = "";
$select_cal_3 = "";
if ($user_calendar === '양력') { $select_cal_1 = "selected"; }
if ($user_calendar === '음력-평달') { $select_cal_2 = "selected"; }
if ($user_calendar === '음력-윤달') { $select_cal_3 = "selected"; }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>나사궁 - 당신의 운명은 이미 흐르고 있습니다</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    	a {
            text-decoration: none;   /* 밑줄 제거 */
    	    color: black;            /* 기본 색상 지정 */
  	}

  	a:visited {
    	    color: black;            /* 방문 후에도 같은 색상 유지 */
  	}

  	a:hover {
    	    color: gray;             /* 마우스 올렸을 때 색상 */
  	}

  	a:active {
    	    color: black;            /* 클릭 시에도 같은 색상 유지 */
  	}
        :root {
            --bg-dark: #0a1128;
            --accent-gold: #cfb53b;
            --white: #ffffff;
            --text-muted: #bdc3c7;
            --glass-bg: rgba(255, 255, 255, 0.92);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { font-family: 'Pretendard', sans-serif; background: var(--bg-dark); color: var(--white); overflow-x: hidden; }

        header {
            background: rgba(10, 17, 40, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: fixed; width: 100%; top: 0; z-index: 1000;
        }
        .header-container {
            max-width: 1200px; margin: 0 auto; padding: 15px 20px;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: nowrap; white-space: nowrap; width: 100%;
        }
        .logo { font-family: 'Noto Serif KR', serif; font-size: 1.5rem; font-weight: 700; color: var(--white); }
        .logo span { color: var(--accent-gold); font-size: 1.2rem; margin-left: 5px; }

        nav .main-menu { display: flex; list-style: none; }
        nav .main-menu li { margin: 0 15px; position: relative; }
        nav .main-menu li a { color: var(--white); text-decoration: none; font-size: 0.95rem; transition: 0.3s; }
        nav .main-menu li a:hover { color: var(--accent-gold); }

        .submenu {
            display: none; position: absolute; top: 100%; left: 0; background: #151e3d;
            min-width: 150px; border-radius: 5px; padding: 10px 0; list-style: none;
            box-shadow: 0 10px 20px rgba(0,0,0,0.5);
        }
        .submenu li a { padding: 8px 20px; display: block; font-size: 0.85rem; color: var(--white) !important; }
        .dropdown:hover .submenu { display: block; }

        /* 우측 상단 버튼 영역 스타일 세팅 */
        .auth-buttons { display: flex; align-items: center; gap: 12px; }
        .btn-login { background: transparent; border: 1px solid var(--white); color: var(--white); padding: 8px 18px; border-radius: 5px; cursor: pointer; transition: 0.3s; }
        .btn-login:hover { background: rgba(255,255,255,0.1); }
        .btn-signup { background: var(--accent-gold); border: none; color: #000; padding: 8px 18px; border-radius: 5px; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .btn-signup:hover { background: #b89e2b; }
        
        /* 마이페이지 버튼 스타일 */
        .btn-mypage { background: transparent; border: 1px solid var(--accent-gold); color: var(--accent-gold); padding: 6px 14px; border-radius: 5px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: 0.3s; }
        .btn-mypage:hover { background: var(--accent-gold); color: #000; }

        /* 사용자 인사 문구 및 로그아웃 버튼 */
        .user-welcome { font-size: 0.95rem; color: var(--text-muted); margin-right: 5px; }
        .user-welcome strong { color: var(--accent-gold); }
        .btn-logout { background: transparent; border: 1px solid rgba(255,255,255,0.4); color: var(--text-muted); padding: 6px 14px; border-radius: 5px; font-size: 0.85rem; cursor: pointer; transition: 0.3s; }
        .btn-logout:hover { border-color: var(--white); color: var(--white); }

        .hero {
            min-height: 100vh;
            background: linear-gradient(rgba(10, 17, 40, 0.5), rgba(10, 17, 40, 0.5)), 
                        url('images/background.png') center/cover no-repeat;
            background-attachment: fixed;
            position: relative; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            padding-top: 140px; 
            padding-bottom: 60px;
        }

        .hero-content { position: relative; z-index: 1; max-width: 1200px; width: 100%; margin: 0 auto; padding: 40px 20px; }

        .intro-text { text-align: center; margin-bottom: 40px; }
        .intro-text h1 { font-family: 'Noto Serif KR', serif; font-size: 3.2rem; line-height: 1.3; margin-bottom: 15px; text-shadow: 2px 2px 10px rgba(0,0,0,0.8); }
        .intro-text .highlight { color: var(--accent-gold); }
        .intro-text p { color: var(--text-muted); font-size: 1.2rem; text-shadow: 1px 1px 5px rgba(0,0,0,0.8); }

        .main-content-layout { display: flex; gap: 40px; align-items: flex-start; }

        .saju-section { flex: 1.2; background: var(--glass-bg); padding: 40px; border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); color: #333; }
        .saju-section h2 { font-family: 'Noto Serif KR', serif; font-size: 1.8rem; margin-bottom: 30px; color: var(--bg-dark); font-weight: 700; text-align: center; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full-width { grid-column: span 2; }
        .form-group label { font-size: 0.9rem; font-weight: 600; margin-bottom: 8px; color: #555; }
        .form-group input, .form-group select { padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; transition: 0.3s; }
        .form-group input:focus, .form-group select:focus { border-color: var(--accent-gold); outline: none; box-shadow: 0 0 0 3px rgba(207, 181, 59, 0.2); }

        .gender-group { display: flex; gap: 10px; }
        .gender-btn { flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 8px; background: #fff; cursor: pointer; font-weight: 600; transition: 0.3s; }
        .gender-btn.active { background: var(--bg-dark); color: var(--white); border-color: var(--bg-dark); }

        .btn-submit { background: var(--accent-gold); color: #000; border: none; padding: 15px; border-radius: 8px; font-size: 1.1rem; font-weight: 700; width: 100%; cursor: pointer; transition: 0.3s; margin-top: 10px; }
        .btn-submit:hover { background: #b89e2b; transform: translateY(-2px); }

        .quick-menu { flex: 0.8; display: flex; flex-direction: column; gap: 20px; }
        .quick-item { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); padding: 25px; border-radius: 15px; display: flex; align-items: center; gap: 20px; cursor: pointer; transition: 0.3s; text-decoration: none; width: 100%; border-radius: 15px; text-align: left; }
        .quick-item:hover { background: rgba(255, 255, 255, 0.1); transform: translateX(5px); border-color: var(--accent-gold); }
        .q-icon { font-size: 2.5rem; }
        .q-text strong { display: block; font-size: 1.15rem; color: var(--white); margin-bottom: 5px; }
        .q-text span { font-size: 0.85rem; color: var(--text-muted); }

        .promo-card { background: linear-gradient(135deg, #1e295d, #0a1128); border: 1px solid rgba(207, 181, 59, 0.3); padding: 25px; border-radius: 15px; display: flex; justify-content: space-between; align-items: center; position: relative; overflow: hidden; }
        .promo-card::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(207,181,59,0.1) 0%, transparent 70%); }
        .promo-text strong { display: block; font-size: 1.2rem; color: var(--accent-gold); margin-bottom: 5px; }
        .promo-text p { font-size: 0.85rem; color: var(--text-muted); margin: 0; }
        .promo-gift { font-size: 3rem; }

        @media (max-width: 992px) {
            .main-content-layout { flex-direction: column; }
            .hero { height: auto; padding: 120px 0 60px; }
            .intro-text h1 { font-size: 2.2rem; }
        }
    </style>
</head>
<body>

<header>
   <?php include 'top_menu.php' ?>
</header>

<main class="hero">
    <div class="hero-content">
        <section class="intro-text">
            <h1>당신의 운명은 이미 <span class="highlight">흐르고</span> 있습니다</h1>
            <p>사주팔자로 미래의 방향을 확인해보세요</p>
        </section>

        <div class="main-content-layout mt-5">
            <section class="saju-section">
                <h2>명반 분석 입력</h2>
                <form id="sajuForm">

		    <div class="form-grid">
		
		        <div class="form-group">
		            <label>이름</label>
		            <input type="text" name="name" id="mainUserName" placeholder="이름을 입력하세요" value="<?php echo $html_name_val; ?>" required>
		        </div>
		
		        <div class="form-group">
		            <label>성별</label>
		
		            <!-- 실제 전송용 hidden -->
		            <input type="hidden" name="gender" id="genderValue" value="<?php echo $gender_hidden_val; ?>">
		
		            <div class="gender-group">
		                <button type="button" class="<?php echo $css_male_class; ?>">남성</button>
		                <button type="button" class="<?php echo $css_female_class; ?>">여성</button>
		            </div>
		        </div>
		
		        <div class="form-group">
		            <label>생년월일</label>
		            <input type="date" name="birthdate" id="mainBirthDate" value="<?php echo $html_birth_val; ?>" required>
		        </div>
		
		        <div class="form-group">
		            <label>출생시간</label>
		            <select name="birthtime" id="mainBirthTime" required>
		                <option value="">시간 선택</option>
		                <?php
		                $time_options = array(
		                    "子" => "子시 (23:30 ~ 01:29)", "丑" => "丑시 (01:30 ~ 03:29)",
		                    "寅" => "寅시 (03:30 ~ 05:29)", "卯" => "卯시 (05:30 ~ 07:29)",
		                    "辰" => "辰시 (07:30 ~ 09:29)", "巳" => "巳시 (09:30 ~ 11:29)",
		                    "午" => "午시 (11:30 ~ 13:29)", "未" => "未시 (13:30 ~ 15:29)",
		                    "申" => "申시 (15:30 ~ 17:29)", "酉" => "酉시 (17:30 ~ 19:29)",
		                    "戌" => "戌시 (19:30 ~ 21:29)", "亥" => "亥시 (21:30 ~ 23:29)"
		                );
		                foreach ($time_options as $key => $val) {
		                    $is_sel = (trim($user_birth_time) === $key || strpos($user_birth_time, $key) !== false) ? "selected" : "";
		                    echo "<option value='{$key}' {$is_sel}>{$val}</option>";
		                }
		                ?>
		            </select>
		        </div>
		
		        <div class="form-group full-width">
		            <label>캘린더 선택</label>
		            <select name="calendarType" id="mainCalendarType">
		                <option value="양력" <?php echo $select_cal_1; ?>>양력 (Solar)</option>
		                <option value="음력-평달" <?php echo $select_cal_2; ?>>음력 평달 (Lunar Regular)</option>
		                <option value="음력-윤달" <?php echo $select_cal_3; ?>>음력 윤달 (Lunar Leap)</option>
		            </select>
		        </div>
		
		    </div>
		    <button type="submit" id="btnSajuSubmit" name="productID" value="mBan" class="btn-submit">
		        운명 분석하기 ➔
		    </button>
            </section>

            <aside class="quick-menu">
                <button type="submit" id="btnTodaySubmit" name="productID" value="toDay" class="quick-item">
        		<div class="q-icon">☀️</div>
        		<div class="q-text">
            			<strong>오늘의 운세</strong>
            			<span>오늘의 흐름을 확인해보세요</span>
        		</div>
    		</button>
    		
    		</form>
                <a href="#" class="quick-item">
                    <div class="q-icon">🧭</div>
                    <div class="q-text">
                        <strong>AI 사주 상담</strong>
                        <span>AI가 분석하는 정교한 개인 사주</span>
                    </div>
                </a>
                <a href="gunghap/" class="quick-item">
                    <div class="q-icon">❤️</div>
                    <div class="q-text">
                        <strong>궁합 보기</strong>
                        <span>그 사람과의 인연은?</span>
                    </div>
                </a>
                <div class="promo-card">
                    <div class="promo-text">
                        <strong>신규회원 50% 할인</strong>
                        <p>지금 가입하고 모든 서비스를 반값에 이용하세요!</p>
                    </div>
                    <div class="promo-gift">🎁</div>
                </div>
            </aside>
        </div>
    </div>
</main>

<div class="modal fade" id="loadingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary" style="border-radius: 15px;">
            <div class="modal-body text-center p-5">
                <div class="spinner-border text-warning mb-4" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h4 class="font-family-serif text-warning mb-2" style="font-family: 'Noto Serif KR', serif;">당신의 <span id="productName"></span>을(를) 분석하고 있습니다</h4>
                <p class="text-muted mb-0">명반 분석에는 약 5~10초의 시간이 소요될 수 있습니다. 잠시만 기다려주세요...</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// 자바스크립트 영역 내부 주석 내 특수문자 및 기호들을 전면 청소하여 구형 파서의 오독 요소를 차단했습니다.
var globalActiveProductID = "mBan";

document.getElementById("btnSajuSubmit").addEventListener("click", function() {
    globalActiveProductID = "mBan";
});
document.getElementById("btnTodaySubmit").addEventListener("click", function() {
    globalActiveProductID = "toDay";
});

document.getElementById("sajuForm").addEventListener("submit", function(e) {
    e.preventDefault();

    var loadingModalEl = document.getElementById("loadingModal");
    var loadingModal = new bootstrap.Modal(loadingModalEl, {
        backdrop: "static",
        keyboard: false
    });
    
    var currentProductID = "mBan";
    if (e.submitter && e.submitter.value) {
        currentProductID = e.submitter.value;
    } else if (document.activeElement && document.activeElement.value) {
        currentProductID = document.activeElement.value;
    }

    var data = new Object();
    data.name = document.getElementById("mainUserName").value;
    data.gender = document.getElementById("genderValue").value;
    data.birthdate = document.getElementById("mainBirthDate").value;
    data.birthtime = document.getElementById("mainBirthTime").value;
    data.calendarType = document.getElementById("mainCalendarType").value;
    data.productID = currentProductID;

    var productName = "운세";
    if (data.productID === "toDay") { productName = "오늘의 운세"; }
    else if (data.productID === "mBan") { productName = "명반"; }

    document.getElementById("productName").textContent = productName;
    var apiUrl = "http://219.248.39.171:8000/nasagung/analyze";

    // 버튼 클릭 즉시 화면을 차단하고 로딩 모달을 구동시킵니다.
    loadingModal.show();

    fetch(apiUrl, {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(data)
    })
    .then(function(response) {
        if (!response.ok) {
            console.error("서버 에러 상태:", response.status);
            throw new Error("서버 연동 상태가 올바르지 않습니다.");
        }
        return response.json();
    })
    .then(function(result) {
        localStorage.setItem("sajuResult", result.result);
        localStorage.setItem("userName", data.name);
        localStorage.setItem("userGender", data.gender);
        localStorage.setItem("userBirthdate", data.birthdate);
        localStorage.setItem("userBirthtime", data.birthtime);
        localStorage.setItem("userCalendarType", data.calendarType);
        localStorage.setItem("productID", data.productID);
        
        loadingModal.hide();
        window.location.href = "response.php";
    })
    .catch(function(error) {
        console.error("오류내역:", error);
        
        loadingModal.hide();
        loadingModalEl.classList.remove("show");
        loadingModalEl.style.display = "none";
        
        var backdrops = document.getElementsByClassName("modal-backdrop");
        while (backdrops.length > 0) {
            var item = backdrops.item(0);
            if(item && item.parentNode) {
                item.parentNode.removeChild(item);
            }
        }
        
        document.body.classList.remove("modal-open");
        document.body.style.overflow = "";
        document.body.style.paddingRight = "";

        alert("서버 전송 실패 또는 주소가 올바르지 않습니다.");
    });
});

var genderBtns = document.querySelectorAll(".gender-btn");
for (var i = 0; i < genderBtns.length; i++) {
    genderBtns[i].addEventListener("click", function() {
        for (var j = 0; j < genderBtns.length; j++) {
            genderBtns[j].classList.remove("active");
        }
        this.classList.add("active");
        document.getElementById("genderValue").value = this.innerText;
    });
}
</script>
</body>
</html>
