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
$user_email = mysqli_real_escape_string($conn, $user_email);
    
$user_type = $_SESSION['user_type'];
$user_type = mysqli_real_escape_string($conn, $user_type);
    
$sql = "SELECT * FROM nasagung_users WHERE email = '$user_email' AND provider = '$user_type'";
$result = mysqli_query($conn, $sql);
$user = mysqli_fetch_assoc($result);

// [추가] 이미지 경로 및 12지신 자동 할당 로직
$current_img = $user['profile_img'];
$img_display_path = "";

if (empty($current_img) || $current_img == 'default_profile.png') {
    // 이미지가 없는 경우 출생연도로 12지신 계산
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
    // 이미지가 있는 경우: 업로드된 파일(_)인지 띠 이미지인지 구분
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
   <?php include 'style.php' ?>
</head>
<body>

<header>
    <?php include 'top_menu.php' ?>
</header>

<main class="hero">
    <div class="hero-content">
        <section class="intro-text">
            <h1>당신의 하늘에 담긴 <span class="highlight">나의 사주</span></h1>
        </section>

        <div class="mypage-layout mt-5">
            <section class="profile-section">
                <div class="profile-avatar-wrapper">
               		<img src="<?php echo $img_display_path; ?>">
            	</div>
                <h2><?php echo htmlspecialchars($user['name']); ?></h2>
                <div class="zodiac-tag"><?php echo htmlspecialchars($user['birthyear']); ?>년생 사주 원국</div>
                
                <div class="info-list">
                    <div class="info-item">
                        <span class="info-label">이메일 계정</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">성별</span>
                        <span class="info-value"><?php echo ($user['gender'] == 'male') ? '남성' : '여성'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">출생 정보</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['birthyear']); ?>년 <?php echo htmlspecialchars($user['birthday']); ?> (<?php echo htmlspecialchars($user['birthtime']); ?>시)</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">달력</span>
                        <span class="info-value"><?php echo $user['calendarType']; ?></span>
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
                    <h3>🔮 나의 기본 사주 명조</h3>
                    <div class="saju-box-mini">
                        <div class="saju-pillar">
                            <div class="saju-pillar-title">시주(時)</div>
                            <div class="saju-char-stem">?</div>
                            <div class="saju-char-branch"><?php echo htmlspecialchars($user['birthtime']); ?></div>
                        </div>
                        <div class="saju-pillar">
                            <div class="saju-pillar-title">일주(日)</div>
                            <div class="saju-char-stem">?</div>
                            <div class="saju-char-branch">?</div>
                        </div>
                        <div class="saju-pillar">
                            <div class="saju-pillar-title">월주(月)</div>
                            <div class="saju-char-stem">?</div>
                            <div class="saju-char-branch">?</div>
                        </div>
                        <div class="saju-pillar">
                            <div class="saju-pillar-title">년주(年)</div>
                            <div class="saju-char-stem">?</div>
                            <div class="saju-char-branch">?</div>
                        </div>
                    </div>
                </div>

                <div class="history-card">
                    <h3>📜 최근 분석 이력</h3>
                    <div class="history-list">
                        <div class="history-item">
                            <div class="history-info">
                                <strong>2026년 신년운세 종합 분석 명반</strong>
                                <span>분석일시: 2026.05.10</span>
                            </div>
                            <button onclick="gotoResult()" class="btn-view-result" >결과 보기</button>
                        </div>
                        <div class="history-item">
                            <div class="history-info">
                                <strong>오늘의 운세</strong>
                                <span>분석일시: 2026.04.18</span>
                            </div>
                            <button onclick="gotoToday()" class="btn-view-result" >결과 보기</button>
                        </div>
                    </div>
                </div>

                <div class="history-card">
                    <h3>🧭 AI 실시간 역학 상담 내역</h3>
                    <div class="history-list">
                        <div class="history-item">
                            <div class="history-info">
                                <strong>"올해 하반기 이직운과 이동 방향에 대해..."</strong>
                                <span>마지막 대화: 3일 전</span>
                            </div>
                            <button onclick="gotoRealtime()" class="btn-view-result" >결과 보기</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</main>

</body>
</html>