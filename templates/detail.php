<?php
session_start();
include "db_conn.php";

// 1. 로그인 여부 확인
if (!isset($_SESSION['user_email'])) {
    echo "<script>alert('로그인이 필요합니다.'); location.href='login.php';</script>";
    exit;
}

// 2. DB에서 사용자 최신 정보 가져오기
$user_email = $_SESSION['user_email'];
$sql = "SELECT * FROM nasagung_users WHERE email = '$user_email'";
$result = mysqli_query($conn, $sql);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    echo "<script>alert('사용자 정보를 찾을 수 없습니다.'); location.href='login.php';</script>";
    exit;
}

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
    <title>내 정보 상세 - 나사궁</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; padding-top: 50px; }
        .detail-card { max-width: 800px; margin: auto; border-radius: 15px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.05); background: white; }
        .info-label { font-weight: 600; color: #6c757d; width: 120px; display: inline-block; }
        .info-value { color: #212529; font-weight: 500; }
        /* 이미지 스타일: 1/2 크기 적용 */
        .profile-img-container {
            width: 126px;
            height: 249px;
            background-color: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #dee2e6;
        }
        .profile-img-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card detail-card p-5">
        <h2 class="text-center fw-bold mb-5">내 정보 상세 보기</h2>

        <div class="d-flex flex-column flex-md-row align-items-center align-items-md-start">
            
            <div class="me-md-5 mb-4 mb-md-0">
                <div class="profile-img-container">
                    <img src="<?php echo $img_display_path; ?>" alt="프로필 사진">
                </div>
            </div>

            <div class="flex-grow-1 w-100">
                <div class="mb-4 pb-2 border-bottom">
                    <span class="info-label">이름</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['name']); ?></span>
                </div>

                <div class="mb-4 pb-2 border-bottom">
                    <span class="info-label">이메일</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                </div>

                <div class="mb-4 pb-2 border-bottom">
                    <span class="info-label">생년월일</span>
                    <span class="info-value"><?php echo $user['birthyear']; ?>년 <?php echo $user['birthday']; ?></span>
                </div>

                <div class="mb-4 pb-2 border-bottom">
                    <span class="info-label">태어난 시간</span>
                    <span class="info-value">
                        <?php 
                        if (!empty($user['birthtime'])) {
                            echo htmlspecialchars($user['birthtime']) . "시";
                        } else {
                            echo "<span class='text-muted'>미지정</span>";
                        }
                        ?>
                    </span>
                </div>

                <div class="mb-5 pb-2 border-bottom">
                    <span class="info-label">전화번호</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['phone']); ?></span>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="edit_profile.php" class="btn btn-primary btn-lg fw-bold px-4">정보 수정하기</a>
                    <a href="/nasagung/" class="btn btn-outline-secondary btn-lg px-4">홈으로</a>
                    <a href="logout.php" class="btn btn-danger btn-lg px-4" onclick="return confirm('로그아웃 하시겠습니까?')">로그아웃</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>