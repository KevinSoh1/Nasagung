<?php
session_start(); // 로그인 세션 정보를 읽기 위해 최상단에 필수 (전 버전 공통)

$user_name = "";
$user_birth_year = "";
$user_birth_day = "";
$user_birth_time = "";
$user_gender = "남성";      // 기본값 설정
$user_calendar = "양력";    // 기본값 설정

// 로그인한 사용자는 DB에서 회원정보 조회
if (isset($_SESSION['user_email'])) {
    include "db_conn.php"; // DB 접속 파일
    
    $user_email = $_SESSION['user_email'];
    $user_email = mysqli_real_escape_string($conn, $user_email);
    
    $user_type = $_SESSION['user_type'];
    $user_type = mysqli_real_escape_string($conn, $user_type);
    
    $sql = "SELECT * FROM nasagung_users WHERE email = '$user_email' AND provider = '$user_type'";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        // PHP 5.x 낮은 버전 호환성을 위해 isset 삼항 연산자 처리
        $user_name = isset($user['name']) ? $user['name'] : '';
        $user_birth_year = isset($user['birthyear']) ? $user['birthyear'] : '';
        $user_birth_day = isset($user['birthday']) ? $user['birthday'] : ''; 
        $user_birth_time = isset($user['birthtime']) ? $user['birthtime'] : '';
        
        if (isset($user['gender']) && $user['gender'] != '') {
            $user_gender = $user['gender'];
        }
        
        if (isset($user['calendarType']) && $user['calendarType'] != '') {
            $user_calendar = $user['calendarType'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>나사궁 - 천기누설 로또 명당</title>
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
    	    color: gray;             /* 마우스 올렸을 때 색상 (선택사항) */
  	}

  	a:active {
    	    color: black;            /* 클릭 시에도 같은 색상 유지 */
  	}
        :root {
            --bg-dark: #0a1128;
            --accent-gold: #cfb53b;
            --white: #ffffff;
            --text-muted: #bdc3c7;
            --glass-bg: rgba(255, 255, 255, 0.95);
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

        /* 메인페이지 배경 지정 */
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
        
        .gender-group { display: flex; gap: 10px; }
        .gender-btn { flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 8px; background: #fff; cursor: pointer; font-weight: 600; transition: 0.3s; }
        .gender-btn.active { background: var(--bg-dark); color: var(--white); border-color: var(--bg-dark); }

        /* 로또 프레임 스타일 */
        .lotto-section { flex: 1.2; background: var(--glass-bg); padding: 40px; border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); color: #333; }
        .lotto-section h2 { font-family: 'Noto Serif KR', serif; font-size: 1.8rem; margin-bottom: 10px; color: var(--bg-dark); font-weight: 700; text-align: center; }
        .lotto-sub-title { text-align: center; font-size: 0.95rem; color: #555; margin-bottom: 30px; }

        /* 여러 게임 조합이 생성될 컨테이너 및 게임 행(Row) 디자인 */
        .lotto-container { display: flex; flex-direction: column; gap: 20px; margin: 35px 0; min-height: 15px; justify-content: center; }
        
        .lotto-game-row { 
            display: flex; justify-content: center; align-items: center; gap: 12px; 
            padding: 12px; background: rgba(10, 17, 40, 0.04); border-radius: 50px;
            animation: rowFadeIn 0.4s ease-out both;
        }
        .game-label { font-weight: 800; color: var(--bg-dark); font-size: 0.95rem; margin-right: 10px; min-width: 50px; }

        /* 순수 코드로 구현한 3D 입체 로또공 이미지 스타일 */
        .lotto-ball-real {
            width: 54px; height: 54px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 1.35rem; font-weight: 800; color: #fff; position: relative;
            box-shadow: 0 6px 12px rgba(0,0,0,0.3), inset -5px -5px 12px rgba(0,0,0,0.4), inset 5px 5px 10px rgba(255,255,255,0.4);
            animation: ballPopIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
        }
        .lotto-ball-real::before {
            content: ''; position: absolute; width: 30px; height: 30px; background: rgba(255,255,255,0.9);
            border-radius: 50%; z-index: 1; box-shadow: inset 1px 1px 3px rgba(0,0,0,0.25);
        }
        .lotto-ball-real span { position: relative; z-index: 2; color: #111; font-weight: 900; font-family: 'Pretendard', sans-serif; }

        /* 로또 규격 색상별 입체 그라데이션 */
        .ball-yellow { background: radial-gradient(circle at 35% 35%, #ffe838, #f59e00); }
        .ball-blue { background: radial-gradient(circle at 35% 35%, #5db2f2, #0b63b7); }
        .ball-red { background: radial-gradient(circle at 35% 35%, #ff6868, #cc0a0a); }
        .ball-purple { background: radial-gradient(circle at 35% 35%, #bf7bfa, #671091); }
        .ball-green { background: radial-gradient(circle at 35% 35%, #5fba63, #137019); }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full-width { grid-column: span 2; }
        .form-group label { font-size: 0.9rem; font-weight: 600; margin-bottom: 8px; color: #444; }
        .form-group select, .form-group input, .form-group textarea { padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; transition: 0.3s; font-weight: 500; }
        .form-group select:focus, .form-group input:focus, .form-group textarea:focus { border-color: var(--accent-gold); outline: none; box-shadow: 0 0 0 3px rgba(207, 181, 59, 0.2); }

        /* 사주 정보 입력을 위한 포맷 구성 그리드 */
        .saju-grid-custom { display: grid; grid-template-columns: 1.2fr 1.5fr 1.3fr; gap: 15px; grid-column: span 2; }
        .birth-date-group { display: flex; gap: 8px; }

        /* 안내 및 링크 박스 전용 스타일 추가 */
        .lotto-notice-box {
            margin-top: 20px; padding: 15px; border-radius: 8px; text-align: center;
            font-weight: 600; font-size: 0.95rem; animation: rowFadeIn 0.5s ease-out both;
        }
        .notice-guest { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .notice-guest a { color: #0056b3; text-decoration: underline !important; font-weight: 700; }
        .notice-member { background: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }
        .notice-member span { color: #d9534f; font-weight: 700; cursor: pointer; text-decoration: underline; }

        .btn-submit { background: var(--accent-gold); color: #000; border: none; padding: 15px; border-radius: 8px; font-size: 1.1rem; font-weight: 700; width: 100%; cursor: pointer; transition: 0.3s; margin-top: 10px; box-shadow: 0 4px 10px rgba(207,181,59,0.3); }
        .btn-submit:hover { background: #b89e2b; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(207,181,59,0.4); }

        /* 우측 퀵메뉴 */
        .quick-menu { flex: 0.8; display: flex; flex-direction: column; gap: 20px; }
        .quick-item { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); padding: 25px; border-radius: 15px; display: flex; align-items: center; gap: 20px; cursor: pointer; transition: 0.3s; text-decoration: none; }
        .quick-item:hover { background: rgba(255, 255, 255, 0.1); transform: translateX(5px); border-color: var(--accent-gold); }
        .q-icon { font-size: 2.5rem; }
        .q-text strong { display: block; font-size: 1.15rem; color: var(--white); margin-bottom: 5px; }
        .q-text span { font-size: 0.85rem; color: var(--text-muted); }

        .promo-card { background: linear-gradient(135deg, #1e295d, #0a1128); border: 1px solid rgba(207, 181, 59, 0.3); padding: 25px; border-radius: 15px; display: flex; justify-content: space-between; align-items: center; position: relative; overflow: hidden; }
        .promo-card::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(207,181,59,0.1) 0%, transparent 70%); }
        .promo-text strong { display: block; font-size: 1.2rem; color: var(--accent-gold); margin-bottom: 5px; }
        .promo-text p { font-size: 0.85rem; color: var(--text-muted); margin: 0; }
        .promo-gift { font-size: 3rem; }

        /* 애니메이션 효과 */
        @keyframes ballPopIn {
            0% { opacity: 0; transform: scale(0) rotate(-180deg); }
            70% { transform: scale(1.15) rotate(15deg); }
            100% { opacity: 1; transform: scale(1) rotate(0deg); }
        }
        @keyframes rowFadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 992px) {
            .main-content-layout { flex-direction: column; }
            .hero { height: auto; padding: 120px 0 60px; }
            .intro-text h1 { font-size: 2.2rem; }
            .lotto-game-row { flex-wrap: wrap; border-radius: 15px; padding: 15px; }
            .game-label { width: 100%; text-align: center; margin-bottom: 5px; }
            .lotto-ball-real { width: 45px; height: 45px; font-size: 1.15rem; }
            .lotto-ball-real::before { width: 24px; height: 24px; }
            .saju-grid-custom { grid-template-columns: 1fr; gap: 12px; }
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
            <h1>천기누설 <span class="highlight">AI 행운의 번호</span> 추출</h1>
            <p>타고난 사주 오행 정보를 파악하여 OpenAI가 천기의 흐름을 담은 행운 번호를 전해드립니다</p>
        </section>

        <div class="main-content-layout mt-5">
            <section class="lotto-section">
                <h2>사주 기반 로또 번호 예측</h2>
                <p class="lotto-sub-title">본인의 이름과 사주 오행 기운을 매칭하여 번호를 생성합니다</p>
               
                <div class="lotto-container" id="lottoContainer">
                    <span style="color: #666; font-weight: 600; font-size: 1.05rem; text-align: center;">하단의 추출 버튼을 클릭하면 AI 분석 행운의 공들이 나타납니다.</span>
                </div>

                <form id="lottoForm">
                    <div class="form-grid">
                        <div class="saju-grid-custom">
                            <div class="form-group">
                                <label>이름</label>
                                <input type="text" id="userName" placeholder="이름" value="<?php echo htmlspecialchars($user_name); ?>"style="max-width: 180px;" required>
                            </div>
                            <div class="form-group">
                                <label>생년월일 (년 / 월-일)</label>
                                <div class="birth-date-group">
                                    <input type="number" id="birthYear" placeholder="년(4자리)" min="1900" max="2030" value="<?php echo htmlspecialchars($user_birth_year); ?>" style="width: 85%;" required>
                                    <input type="text" id="birthDay" placeholder="MM-DD" pattern="\d{2}-\d{2}" title="05-18 형태로 입력하세요" value="<?php echo htmlspecialchars($user_birth_day); ?>" style="width: 75%;" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>출생 시간 (12지시)</label>
                                <select id="birthTime" required>
				    <option value="">시간 선택</option>
				    <?php
    					// 💡 1. 구형 PHP 엔진에서도 안전한 전통 array 구조로 Key => Value 쌍을 완벽히 맞춥니다.	
    					$branches = array(
        					"子" => "子시 (23:30 ~ 01:29)", 
        					"丑" => "丑시 (01:30 ~ 03:29)",
        					"寅" => "寅시 (03:30 ~ 05:29)", 
        					"卯" => "卯시 (05:30 ~ 07:29)",
        					"辰" => "辰시 (07:30 ~ 09:29)", 
        					"巳" => "巳시 (09:30 ~ 11:29)",
        					"午" => "午시 (11:30 ~ 13:29)", 
        					"未" => "未시 (13:30 ~ 15:29)",
        					"申" => "申시 (15:30 ~ 17:29)", 
        					"酉" => "酉시 (17:30 ~ 19:29)",
        					"戌" => "戌시 (19:30 ~ 21:29)", 
        					"亥" => "亥시 (21:30 ~ 23:29)", 
        					"모름" => "시간 모름"
    					);

    					// 💡 2. $key 에는 "子", "丑" 등이 담기고 $val 에는 "子시 (...)" 가 담깁니다.
    					foreach ($branches as $key => $val) {
        
				        // DB에 저장된 값($user_birth_time)에 한 글자 기호("子", "丑" 등)가 포함되어 있는지 안전하게 대조합니다.
        				$db_time = trim($user_birth_time);
        				$selected = "";
					        
        				if ($db_time !== "" && (strpos($db_time, $key) !== false || $db_time === $key)) {
            					$selected = "selected";
        				}
        
        				// 💡 3. 실제 파이썬 백엔드로 전송될 value값은 $key(한 글자 기호)로 깔끔하게 밀어 넣어 줍니다.
        				echo "<option value='{$key}' {$selected}>{$val}</option>";
    					}
    				?>	
			    </select>
                            </div>
                        </div>
                        <div class="form-group">
		            <label>성별</label>
		            <input type="hidden" name="gender" id="genderValue" value="<?php echo htmlspecialchars($user_gender); ?>">
		
		            <div class="gender-group">
		                <button type="button" class="gender-btn <?php echo ($user_gender === '남성' || $user_gender =='male') ? 'active' : ''; ?>">남성</button>
		                <button type="button" class="gender-btn <?php echo ($user_gender === '여성' || $user_gender =='female') ? 'active' : ''; ?>">여성</button>
		            </div>
		        </div>
                        <div class="form-group">
		            <label>캘린더 선택</label>
		            <select name="calendarType" id="calendarType" required>
		                <option value="양력" <?php echo ($user_calendar === '양력') ? 'selected' : ''; ?>>양력 (Solar)</option>
		                <option value="음력-평달" <?php echo ($user_calendar === '음력-평달') ? 'selected' : ''; ?>>음력 평달 (Lunar Regular)</option>
		                <option value="음력-윤달" <?php echo ($user_calendar === '음력-윤달') ? 'selected' : ''; ?>>음력 윤달 (Lunar Leap)</option>
		            </select>
		        </div>

                        <div class="form-group full-width">
                            <label>집중 기운 (오행 선택)</label>
                            <select id="fiveElements" required>
                                <option value="all">종합 대박 재물운 조합 (추천)</option>
                                <option value="metal">금(金)의 기운 - 결실과 막대한 금전</option>
                                <option value="wood">목(木)의 기운 - 발전과 새로운 기회</option>
                                <option value="fire">화(火)의 기운 - 강렬한 명예와 번창</option>
                                <option value="earth">土(土)의 기운 - 흔들림 없는 저축과 자산</option>
                                <option value="water">수(水)의 기운 - 유연함 및 막힘없는 횡재</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit" id="btnExtract">
                        천기운集 AI 번호 추출하기 ➔
                    </button>
                </form>
            </section>

            <aside class="quick-menu">
                <a href="#" class="quick-item">
                    <div class="q-icon">💰</div>
                    <div class="q-text">
                        <strong>평생 재물운 분석</strong>
                        <span>타고난 금전운의 그릇 크기를 확인하세요</span>
                    </div>
                </a>
                <a href="#" class="quick-item">
                    <div class="q-icon">🧭</div>
                    <div class="q-text">
                        <strong>AI 사주 상담</strong>
                        <span>AI가 분석하는 정교한 개인 사주</span>
                    </div>
                </a>
                <a href="#" class="quick-item">
                    <div class="q-icon">🃏</div>
                    <div class="q-text">
                        <strong>오늘의 타로 운세</strong>
                        <span>오늘 나에게 다가올 행운의 지수는?</span>
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
<script>
const genderBtns = document.querySelectorAll('.gender-btn');
genderBtns.forEach(function(btn) {
    btn.addEventListener('click', function() {
        genderBtns.forEach(function(b) { b.classList.remove('active'); });
        this.classList.add('active');
        document.getElementById('genderValue').value = this.innerText;
    });
});

const isMember = <?php echo isset($_SESSION['user_email']) ? 'true' : 'false'; ?>;
let globalGamesData = [];

function completePayment() {
    const noticeBox = document.querySelector('.lotto-notice-box');
    if (noticeBox) noticeBox.remove();
    renderRemainingGames(); 
}

document.getElementById('lottoForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const container = document.getElementById('lottoContainer');
    const btnSubmit = document.getElementById('btnExtract');
    
    const name = document.getElementById('userName').value;
    const birthYear = document.getElementById('birthYear').value;
    const birthDay = document.getElementById('birthDay').value;
    const birthTime = document.getElementById('birthTime').value;
    const gender = document.getElementById('genderValue').value;
    const calendarType = document.getElementById('calendarType').value;
    const fiveElements = document.getElementById('fiveElements').value;
    
    const formattedBirthDate = `${birthYear}-${birthDay}`;

    btnSubmit.disabled = true;
    btnSubmit.innerText = "천기 기운으로 AI 번호를 산출하는 중...";
    container.innerHTML = '';

    const requestData = {
        name: name,
        gender: gender, 
        birthdate: formattedBirthDate,
        birthtime: birthTime,
        calendarType: calendarType,
        productID: "lotto",
        fiveElements: fiveElements
    };

    fetch("http://219.248.39.171:8000/nasagung/analyze", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(requestData)
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.error) {
            alert(data.error);
            resetSubmitButton();
            return;
        }

        const aiText = data.result;
        globalGamesData = []; 

        // 💡 [정렬 해제 핵심 수정]: 줄바꿈 단위로 숫자를 스캔할 때 절대로 정렬 함수(.sort)를 태우지 않습니다.
        const lines = aiText.split('\n');
        for (let idx = 0; idx < lines.length; idx++) {
            const line = lines[idx];
            const lineNumbers = line.match(/\d+/g);
            if (lineNumbers) {
                const validNumbers = [];
                for (let k = 0; k < lineNumbers.length; k++) {
                    const num = Number(lineNumbers[k]);
                    if (num >= 1 && num <= 45) {
                        validNumbers.push(num);
                    }
                }
                if (validNumbers.length === 6) {
                    // ★ 기존의 sort() 정렬 구문을 완전히 삭제하여 파이썬 원시 배열 순서를 100% 보존합니다.
                    globalGamesData.push(validNumbers);
                }
            }
        }
        
        // 줄바꿈 매칭이 실패했을 때 작동하는 Fallback 단일 라인 문자열 파싱 구역
        if (globalGamesData.length === 0) {
            const rawNumbers = aiText.match(/\d+/g);
            if (rawNumbers) {
                const fallbackNums = [];
                for (let x = 0; x < rawNumbers.length; x++) {
                    const n = Number(rawNumbers[x]);
                    if (n >= 1 && n <= 45) fallbackNums.push(n);
                }
                for (let i = 0; i < fallbackNums.length; i += 6) {
                    let chunk = fallbackNums.slice(i, i + 6);
                    if (chunk.length === 6) {
                        // ★ 예외 처리 구역에서도 정렬 구문을 완전 폐기합니다.
                        globalGamesData.push(chunk);
                    }
                }
            }
        }

        if (globalGamesData.length > 5) {
            globalGamesData = globalGamesData.slice(0, 5);
        }

        if (globalGamesData.length === 0) {
            alert("유효한 로또 게임 번호가 생성되지 않았습니다.");
            resetSubmitButton();
            return;
        }

        // 제 1게임 출력 (파이썬 터미널에 가장 처음 찍힌 6개 조합 순서 그대로 전달)
        renderLottoGameRow(globalGamesData[0], 1, 0);

        setTimeout(function() {
            const noticeDiv = document.createElement('div');
            noticeDiv.className = 'lotto-notice-box';
            
            if (!isMember) {
                noticeDiv.className += ' notice-guest';
                noticeDiv.innerHTML = '⚠️ 비회원은 맛보기용 1게임만 제공됩니다.<br>전체 5게임 조합 및 천기 분석 결과를 보시려면<br><a href="register.php">회원가입</a>을 진행해 주세요.';
            } else {
                noticeDiv.className += ' notice-member';
                noticeDiv.innerHTML = '🔒 회원은 기본 1게임이 무료 제공됩니다.<br>나머지 대박 추천 조합을 모두 열람하시려면<br><span onclick="openPaymentPopup()">[구매하기]</span>를 완료해 주세요.';
            }
            container.appendChild(noticeDiv);
            resetSubmitButton();
        }, 80 * 6 + 200);
    })
    .catch(function(err) {
        console.error(err);
        alert("서버 통신 중 오류가 발생했습니다.");
        resetSubmitButton();
    });

    function resetSubmitButton() {
        btnSubmit.disabled = false;
        btnSubmit.innerText = "천기운集 AI 번호 다시 추출하기 ➔";
    }
});

// 화면에 볼을 그리는 함수 (넘어온 정방향 배열 인덱스 흐름 그대로 주입)
function renderLottoGameRow(numbers, gameIndex, startDelay) {
    const container = document.getElementById('lottoContainer');
    const gameRow = document.createElement('div');
    gameRow.className = 'lotto-game-row';
    gameRow.style.animationDelay = `${(gameIndex - 1) * 0.2}s`;
    
    const label = document.createElement('div');
    label.className = 'game-label';
    label.innerText = `제 ${gameIndex}게임`;
    gameRow.appendChild(label);

    let ballDelay = startDelay;
    numbers.forEach(function(num) {
        ballDelay += 80;
        setTimeout(function() {
            const ball = document.createElement('div');
            ball.className = 'lotto-ball-real';
            if (num <= 10) ball.classList.add('ball-yellow');
            else if (num <= 20) ball.classList.add('ball-blue');
            else if (num <= 30) ball.classList.add('ball-red');
            else if (num <= 40) ball.classList.add('ball-purple');
            else ball.classList.add('ball-green');
            
            ball.innerHTML = `<span>${num}</span>`;
            gameRow.appendChild(ball);
        }, ballDelay);
    });

    container.appendChild(gameRow);
}

function renderRemainingGames() {
    if (globalGamesData.length <= 1) {
        alert("추가 생성된 로또 조합이 존재하지 않습니다.");
        return;
    }
    
    let currentDelay = 0;
    for (let i = 1; i < globalGamesData.length; i++) {
        renderLottoGameRow(globalGamesData[i], i + 1, currentDelay);
        currentDelay += 480; 
    }
}

function openPaymentPopup() {
    const width = 450;
    const height = 500;
    const left = (window.screen.width / 2) - (width / 2);
    const top = (window.screen.height / 2) - (height / 2);
    window.open("pay_popup.php", "NasaGungPay", `width=${width},height=${height},left=${left},top=${top},scrollbars=no,resizable=no`);
}

</script>
</body>
</html>