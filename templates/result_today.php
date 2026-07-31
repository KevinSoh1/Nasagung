<?php
session_start();
include "db_conn.php"; // DB 접속 파일

// 로그인하지 않은 사용자는 로그인 페이지로 리다이렉트
if (!isset($_SESSION['user_email'])) {
    echo "<script>alert('로그인이 필요한 서비스입니다.'); location.href='login.php';</script>";
    exit;
}

// 로그인된 사용자의 상세 정보 DB에서 가져오기
$user_email = $_SESSION['user_email'];
$sql = "SELECT * FROM nasagung_users WHERE email = '$user_email'";
$result = mysqli_query($conn, $sql);
$user = mysqli_fetch_assoc($result);

// 이미지 경로 및 12지신 자동 할당 로직
$current_img = $user['profile_img'];
$img_display_path = "";

if (empty($current_img) || $current_img == 'default_profile.png') {
    $birthyear = intval($user['birthyear']);
    if ($birthyear > 0) {
        $zodiac_icons = array(
            0 => "monkey.png", 1 => "rooster.png", 2 => "dog.png", 3 => "pig.png",
            4 => "rat.png",    5 => "ox.png",      6 => "tiger.png", 7 => "rabbit.png",
            8 => "dragon.png", 9 => "snake.png",  10 => "horse.png", 11 => "sheep.png"
        );
        $remainder = $birthyear % 12;
        $img_display_path = "images/" . $zodiac_icons[$remainder];
    } else {
        $img_display_path = "images/default_profile.png";
    }
} else {
    if (strpos($current_img, '_') !== false) {
        $img_display_path = "uploads/" . $current_img;
    } else {
        $img_display_path = "images/" . $current_img;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>나사궁 - 마이페이지</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+KR:wght@400;700&family=Pretendard:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        a { text-decoration: none; color: var(--accent-gold); }
        a:visited { color: var(--accent-gold); }
        a:hover { color: var(--accent-gold); }
        a:active { color: var(--accent-gold); }

        :root {
            --bg-dark: #0a1128;
            --accent-gold: #cfb53b;
            --white: #ffffff;
            --text-muted: #bdc3c7;
            --glass-bg: rgba(255, 255, 255, 0.92);
            --glass-card: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Pretendard', sans-serif; 
            background: var(--bg-dark); 
            color: var(--white); 
            overflow-x: hidden; 
        }

        header {
            background: rgba(10, 17, 40, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: fixed; width: 100%; top: 0; z-index: 1000;
        }
        .header-container {
            max-width: 1200px; margin: 0 auto; padding: 15px 20px;
            display: flex; justify-content: space-between; align-items: center;
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

        .auth-buttons { display: flex; align-items: center; gap: 15px; }
        .user-welcome { font-size: 0.9rem; color: var(--text-muted); }
        .user-welcome strong { color: var(--accent-gold); }
        .btn-logout { background: transparent; border: 1px solid rgba(255,255,255,0.4); color: var(--text-muted); padding: 6px 14px; border-radius: 5px; font-size: 0.85rem; cursor: pointer; transition: 0.3s; }
        .btn-logout:hover { border-color: var(--white); color: var(--white); }
        
        /* 마이페이지 버튼 스타일 */
        .btn-mypage { background: transparent; border: 1px solid var(--accent-gold); color: var(--accent-gold); padding: 6px 14px; border-radius: 5px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: 0.3s; }
        .btn-mypage:hover { background: var(--accent-gold); color: #000; }


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
        
        .mypage-layout { display: flex; gap: 40px; align-items: flex-start; }
        
        .profile-section { flex: 1; background: var(--glass-bg); padding: 40px; border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); color: #333; text-align: center; }
        .profile-section h2 { font-family: 'Noto Serif KR', serif; font-size: 1.8rem; margin-bottom: 5px; color: var(--bg-dark); font-weight: 700; }
        .zodiac-tag { display: inline-block; background: var(--bg-dark); color: var(--accent-gold); padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; margin-bottom: 25px; }
        
        .info-list { text-align: left; background: rgba(0,0,0,0.03); border-radius: 10px; padding: 20px; margin-bottom: 25px; }
        .info-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 0.95rem; }
        .info-item:last-child { border-bottom: none; }
        .info-label { color: #666; font-weight: 500; }
        .info-value { color: #111; font-weight: 600; }
        
        .btn-edit-profile { background: var(--bg-dark); color: var(--white); border: none; padding: 12px; border-radius: 8px; font-size: 1rem; font-weight: 600; width: 100%; cursor: pointer; transition: 0.3s; }
        .btn-edit-profile:hover { background: #1a264e; transform: translateY(-2px); color: var(--accent-gold); }

        .dashboard-section { flex: 1.5; display: flex; flex-direction: column; gap: 25px; }
        
        .history-card { background: var(--glass-card); border: 1px solid var(--glass-border); padding: 30px; border-radius: 15px; backdrop-filter: blur(5px); }
        .history-card h3 { font-family: 'Noto Serif KR', serif; font-size: 1.3rem; color: var(--accent-gold); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        
        .history-list { display: flex; flex-direction: column; gap: 15px; }
        .history-item { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 18px; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; transition: 0.3s; }
        .history-item:hover { border-color: var(--accent-gold); background: rgba(255,255,255,0.05); }
        .history-info { width: 100%; font-size: 1rem; color: var(--white); line-height: 1.6; }
        
        .fortune-text { white-space: pre-wrap; word-break: break-all; color: #f1f2f6; }

        .btn-view-result { background: transparent; border: 1px solid var(--accent-gold); color: var(--accent-gold); padding: 6px 14px; border-radius: 5px; font-size: 0.85rem; font-weight: 600; float:right; }
        .btn-view-result:hover { background: var(--accent-gold); color: #000; }
        .btn-view-result:focus { background: transparent; border: 1px solid var(--accent-gold); color: var(--accent-gold); padding: 6px 14px; border-radius: 5px; font-size: 0.85rem; font-weight: 600; float: right; text-decoration: none; }

        @media (max-width: 992px) {
            .mypage-layout { flex-direction: column; }
            .hero { height: auto; padding: 120px 0 60px; }
            .intro-text h1 { font-size: 2.2rem; }
        }
    </style>
    
    <script src="https://js.tosspayments.com/v1"></script>
<script>
	//오늘 날짜 출력.
	let today = new Date();
	let isoDate = today.toISOString().split('T')[0];
 	
 	document.getElementById("current-date").innerText = isoDate;
    
    	function resultView(){
	        // 1. 함수 호출 시 무조건 가장 먼저 알림창을 띄웁니다.
	        const userElem = document.getElementById('user_name');
	        const userName = userElem.dataset.userName;
	
	        const emailElem = document.getElementById('email');
	        const user_id = emailElem.dataset.email; 
	
	        const genElem = document.getElementById('genDer');
	        const genDer = genElem.dataset.gender;
	
	        const birthYElem = document.getElementById('birthYear');
	        const birthYear = birthYElem.dataset.birthyear;
	
	        const birthDElem = document.getElementById('birthDay');
	        const birthDay = birthDElem.dataset.birthday;
	
	        const birthTElem = document.getElementById('birthTime');
	        const birthTime = birthTElem.dataset.birthtime;
	        
		//오늘 날짜 출력.
		let today = new Date();
		let isoDate = today.toISOString().split('T')[0];
	 	
	        const q = `${userName}(${genDer}, ${birthYear}년 ${birthDay}일 ${birthTime}시 생)에 대한 오늘(${isoDate})의 운세를 분석해주세요`;
	
	        document.getElementById("result").innerText = "오늘의 운세를 분석하고 있습니다. 잠시만 기다려주세요...";
	
	        const existingPayBtn = document.getElementById("dynamic-pay-btn");
	        if (existingPayBtn) {
	            existingPayBtn.remove();
	        }
	
	        fetch("http://219.248.39.171:8000/yearLockey", {
	            method: "POST",
	            headers: {
	                "Content-Type": "application/json"
	            },
	            body: JSON.stringify({
	                question: q,
	                user_id: user_id, 
	                user_info: {
	                    name: userName,
	                    gender: genDer,
	                    birth_year: birthYear,
	                    birth_day: birthDay,
	                    birth_time: birthTime
	                    to_day:isoDate
	                }        
	            })
	        })
	        .then(res => res.json())
	        .then(data => {
	            if (data.answer) {
	                document.getElementById("result").innerText = data.answer;
	
	                if (data.is_paid === false) {
	                    const payButton = document.createElement("button");
	                    payButton.id = "dynamic-pay-btn"; 
	                    payButton.innerText = "💳 유료 결제하고 전체 내용 확인하기";
	
	                    payButton.style.marginTop = "30px";
	                    payButton.style.padding = "10px 20px";
	                    payButton.style.fontSize = "16px";
	                    payButton.style.cursor = "pointer";
	                    payButton.style.backgroundColor = "#ff4d4f";
	                    payButton.style.color = "white";
	                    payButton.style.border = "none";
	                    payButton.style.borderRadius = "5px";
	
	                    payButton.onclick = payMent; 
	
	                    document.getElementById("result").after(payButton);
	                }
	            } else if (data.error) {
	                document.getElementById("result").innerText = "에러: " + data.error;
	            }
	        })
	        .catch(err => {
	            console.error(err);
	            document.getElementById("result").innerText = "운세 서버 연결 오류 (백엔드가 켜져있는지 확인해 주세요)";
	        });
    	}
    
    function payMent() {
        const emailElem = document.getElementById('email');
        const user_id = emailElem.dataset.email; 

        const clientKey = "test_ck_LkKEypNArWWQKLKPGbRjrlmeaxYG"; 
        const tossPayments = TossPayments(clientKey);
        const orderId = "order_" + new Date().getTime();
        const backendUrl = "http://219.248.39.171:8000";

        tossPayments.requestPayment("카드", {
            amount: 1000,
            orderId: orderId,
            orderName: "2026년 신년 운세 종합 분석",
            successUrl: `${backendUrl}/success.html?user_id=${user_id}&orderId=${orderId}`,
            failUrl: `${backendUrl}/fail.html`
        });
    }

    // 페이지가 로드되었을 때 자동 실행 조건 설정 구역
    window.onload = function() {
        const urlParams = new URLSearchParams(window.location.search);
        const loggedInUser = urlParams.get('user_id');
        
        if (loggedInUser) {
            resultView(); 
        } else {
            // 마이페이지 접속 시 자동으로 무료 버전 출력 활성화
            resultView();
        }
    };
</script>
</head>
<body>

<header>
    <div class="header-container">
        <a href="/nasagung/"><div class="logo">나사궁(나의 사주가 궁금해)</div></a>
        <nav>
            <ul class="main-menu">
                <li class="dropdown">
                    <a href="#">사주팔자</a>
                    <ul class="submenu">
                        <li><a href="#">평생 사주</a></li>
                        <li><a href="#">신년 운세</a></li>
                        <li><a href="#">월간 흐름</a></li>
                    </ul>
                </li>
                <li><a href="#">운세</a></li>
                <li><a href="#">궁합</a></li>
                <li><a href="#">재물운</a></li>
                <li><a href="#">사업운</a></li>
                <li><a href="#">AI 상담</a></li>
                <li><a href="#">커뮤니티</a></li>
            </ul>
        </nav>
        <div class="auth-buttons">
            <?php if (isset($_SESSION['user_email'])): ?>
                <span class="user-welcome">
                    <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong> 님
                </span>
                <a href="mypage.php"><button class="btn-mypage">My Page</button></a>
                <a href="logout.php"><button class="btn-logout">로그아웃</button></a>
            <?php else: ?>
                <a href="login.php"><button class="btn-login">로그인</button></a>
                <a href="register.php"><button class="btn-signup">회원가입</button></a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="hero">
    <div class="hero-content">
        <section class="intro-text">
            <h1>당신의 하늘에 담긴 <span class="highlight">나의 사주</span></h1>
        </section>

        <div class="mypage-layout mt-5">
            <section class="profile-section">
                 <div>
               		<img src="<?php echo $img_display_path; ?>" alt="프로필 사진">
            	</div>
                <h2 id="user_name" data-user-name="<?php echo htmlspecialchars($user['name']); ?>"><?php echo htmlspecialchars($user['name']); ?></h2>
                <div class="zodiac-tag"><?php echo htmlspecialchars($user['birthyear']); ?>년생 사주 원국</div>
                
                <div class="info-list">
                    <div class="info-item">
                        <span class="info-label">이메일 계정</span>
                        <span id="email" data-email="<?php echo $user['email']; ?>" class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">성별</span>
                        <span id="genDer" data-gender="<?php echo ($user['gender'] == 'male') ? '남성' : '여성'; ?>" class="info-value"><?php echo ($user['gender'] == 'male') ? '남성' : '여성'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">출생 정보</span>
                        <span id="birthYear" data-birthyear="<?php echo $user['birthyear']; ?>" class="info-value"><?php echo htmlspecialchars($user['birthyear']); ?>년 <?php echo htmlspecialchars($user['birthday']); ?> (<?php echo htmlspecialchars($user['birthtime']); ?>시)</span>
                        
                        <span id="birthDay" data-birthday="<?php echo $user['birthday']; ?>" style="display:none;"></span>
                        <span id="birthTime" data-birthtime="<?php echo $user['birthtime']; ?>" style="display:none;"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">연락처</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['phone']); ?></span>
                    </div>
                </div>

                <button class="btn-edit-profile" onclick="location.href='edit_profile.php'">내 정보 수정하기</button>
            </section>

            <div class="dashboard-section">
                <div class="history-card">
                    <h3>📜 <strong><?php echo htmlspecialchars($user['name']); ?>님의 오늘(<span id="current-date"></spa>)의 운세</strong></h3>
                    <div class="history-list">
                        <div class="history-item">
                            <div id="result" class="history-info fortune-text">
                                여기에 분석 결과가 출력됩니다.
                            </div>
                        </div>
                    </div>
                    <br>
                    <br>
                     <a href="mypage.php" class="btn-view-result">최근 분석 이력 리스트 보기</a>
                </div>
            </div>
        </div>
    </div>
</main>

</body>
</html>