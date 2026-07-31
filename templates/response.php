<?php
session_start();
// 로그인 여부를 판단하여 true / false를 변수에 담습니다.
$is_login = isset($_SESSION['user_email']) ? true : false;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>나사궁 - 당신의 분석 결과</title>
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

        .saju-section { flex: 0.9; background: var(--glass-bg); padding: 40px; border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); color: #333; }
        .saju-section h2 { font-family: 'Noto Serif KR', serif; font-size: 1.8rem; margin-bottom: 30px; color: var(--bg-dark); font-weight: 700; text-align: center; }

        .form-grid { display: grid; grid-template-columns: 1fr; gap: 18px; margin-bottom: 15px; }
        .form-group { display: flex; flex-direction: row; align-items: center; justify-content: space-between; border-bottom: 1px solid #eee; padding-bottom: 8px; }
        .form-group label { font-size: 0.95rem; font-weight: 600; color: #555; }
        .form-group strong { font-size: 1.05rem; color: var(--bg-dark); }

        .quick-menu { flex: 1.3; display: flex; flex-direction: column; gap: 20px; width: 100%; }
        
        .result-box {
            background: #111625;
            padding: 35px;
            border-radius: 20px;
            white-space: pre-wrap;
            border: 1px solid rgba(255, 255, 255, 0.08);
            line-height: 1.7;
            font-size: 1.05rem;
            color: #e0e6ed;
            box-shadow: inset 0 4px 20px rgba(0,0,0,0.5);
        }

        /* 로그인 유도 박스 디자인 */
        .login-blur-box {
            margin-top: 25px;
            padding: 20px;
            background: rgba(207, 181, 59, 0.08);
            border: 1px dashed var(--accent-gold);
            border-radius: 12px;
            text-align: center;
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
            <h1 id="mainTitle">당신의 운명은 이미 <span class="highlight">흐르고</span> 있습니다</h1>
            <p id="mainSubText">사주팔자로 미래의 방향을 확인해보세요</p>
        </section>

        <div class="main-content-layout mt-5">
            <section class="saju-section">
                <h2>입력하신 명식 정보</h2>
		    <div class="form-grid">
		        <div class="form-group">
		            <label>이름</label><strong id="printName">-</strong>
		        </div>
		
		        <div class="form-group">
		            <label>성별</label><strong id="printGender">-</strong>
		        </div>
		
		        <div class="form-group">
		            <label>생년월일</label><strong id="printBirthdate">-</strong> 
		        </div>
		
		        <div class="form-group">
		            <label>출생시간</label><strong id="printBirthtime">-</strong>
		        </div>
		
		        <div class="form-group">
		            <label>달력형태</label><strong id="printCalendarType">-</strong>
		        </div>
		    </div>
            </section>

            <aside class="quick-menu">
            	<div id="username"></div>
             	<div class="result-box" id="result"></div>   
            </aside>
        </div>
    </div>
</main>

<script>
    // 1. 저장된 스토리지 데이터 가져오기
    const result = localStorage.getItem("sajuResult");
    const userName = localStorage.getItem("userName");
    const gender = localStorage.getItem("userGender");
    const birthdate = localStorage.getItem("userBirthdate");
    const birthtime = localStorage.getItem("userBirthtime");
    const calendarType = localStorage.getItem("userCalendarType");
    const productID = localStorage.getItem("productID"); 

    // 2. 입력 데이터 화면 연동 (성별 보정 처리 포함)
    if (userName) document.getElementById("printName").innerText = userName;
    if (gender) {
        document.getElementById("printGender").innerText = (gender === "male" || gender === "남성") ? "남성" : "여성";
    }
    if (birthdate) document.getElementById("printBirthdate").innerText = birthdate;
    if (birthtime) {
        document.getElementById("printBirthtime").innerText = birthtime.includes("시") ? birthtime : birthtime + "시";
    }
    if (calendarType) document.getElementById("printCalendarType").innerText = calendarType;

    // 3. productID 구분에 따른 타이틀 및 헤더 텍스트 동적 세팅
    let titleHtml = "";
    if (productID === "toDay") {
        document.getElementById("mainTitle").innerHTML = "오늘 하루의 <span class='highlight'>흐름과 길흉</span>";
        document.getElementById("mainSubText").innerText = "오늘 하루를 가장 지혜롭게 보내는 나침반이 되어 드립니다.";
        titleHtml = "<h2>☀️ " + userName + "님의 오늘의 운세 분석</h2>";
    } else {
        document.getElementById("mainTitle").innerHTML = "당신의 명반이 그리는 <span class='highlight'>인생의 지도</span>";
        document.getElementById("mainSubText").innerText = "타고난 사주 원국과 명반 구조를 분석한 정밀 리포트입니다.";
        titleHtml = "<h2>🔮 " + userName + "님의 정밀 명반 분석</h2>";
    }
    document.getElementById("username").innerHTML = titleHtml;	

    // 4. PHP 세션값 기반 로그인 판별 및 500자 자르기 뷰 제어 적용
    const isLogin = <?php echo $is_login ? 'true' : 'false'; ?>; // 수정 완료

    let displayResult = "";

    if (isLogin) {
        // 로그인 완료 상태: 개행(\n)을 브라우저용 줄바꿈(<br>)으로 치환하여 전체 출력
        displayResult = result ? result.replace(/\n/g, "<br>") : "분석 데이터가 존재하지 않습니다.";
    } else {
        // 비로그인 상태: 500자만 추출 후 자르기
        if (result) {
            let slicedResult = result.substring(0, 500);
            displayResult = slicedResult.replace(/\n/g, "<br>") + "...";
            
            // 고급스러운 안내 상자 마크업 결합
            displayResult += `
                <div class="login-blur-box">
                    <span style='color: #ff6b6b; font-weight: bold; display:block; margin-bottom:10px;'>🔒 로그인 후 전체 결과를 확인할 수 있습니다.</span>
                    <a href='login.php' style='color:#cfb53b; font-weight:bold; text-decoration:underline; font-size:1.1rem;'>👉 로그인하고 전체 리포트 보기</a>
                </div>
            `;
        } else {
            displayResult = "분석 데이터가 존재하지 않습니다.";
        }
    }

    // 5. DOM 최종 렌더링
    document.getElementById("result").innerHTML = displayResult;
</script>

</body>
</html>