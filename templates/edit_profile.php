<?php
session_start();
include "db_conn.php";

// 1. 로그인 여부 확인
if (!isset($_SESSION['user_email'])) {
    echo "<script>alert('로그인이 필요합니다.'); location.href='login.php';</script>";
    exit;
}

$user_email = $_SESSION['user_email'];
$user_email = mysqli_real_escape_string($conn, $user_email);
    
$user_type = $_SESSION['user_type'];
$user_type = mysqli_real_escape_string($conn, $user_type);

$msg = "";

// 2. 현재 사용자 정보 조회
$user_res = mysqli_query($conn, "SELECT * FROM nasagung_users WHERE email = '$user_email' AND provider = '$user_type'");
$user = mysqli_fetch_assoc($user_res);

// 3. [원본 백엔드 로직] 이미지가 없으면 출생연도에 맞는 12지신 이미지 자동 할당 및 DB 저장
if (empty($user['profile_img']) || $user['profile_img'] == 'default_profile.png') {
    $birthyear = intval($user['birthyear']);
    if ($birthyear > 0) {
        $zodiac_icons = array(
            0 => "monkey.png", 1 => "rooster.png", 2 => "dog.png", 3 => "pig.png",
            4 => "rat.png",    5 => "ox.png",      6 => "tiger.png", 7 => "rabbit.png",
            8 => "dragon.png", 9 => "snake.png",  10 => "horse.png", 11 => "sheep.png"
        );
        $remainder = $birthyear % 12;
        $auto_icon = $zodiac_icons[$remainder];
        
        mysqli_query($conn, "UPDATE nasagung_users SET profile_img = '$auto_icon' WHERE email = '$user_email'");
        $user['profile_img'] = $auto_icon; // 화면 표시용 데이터 갱신
    }
}

// 4. 정보 수정 처리 (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name         = mysqli_real_escape_string($conn, $_POST['name']);
    $gender       = mysqli_real_escape_string($conn, $_POST['gender']);
    $birthyear    = mysqli_real_escape_string($conn, $_POST['birthyear']);
    $birthday     = mysqli_real_escape_string($conn, $_POST['birthday']);
    $birthtime    = mysqli_real_escape_string($conn, $_POST['birthtime']);
    $phone        = mysqli_real_escape_string($conn, $_POST['phone']);
    $calendarType = mysqli_real_escape_string($conn, $_POST['calendarType']); // 달력 값 추가 및 이스케이프

    // [중요] 빈 값에 의한 콤마(,) 에러를 원천 차단하기 위해 배열로 쿼리 조립
    $update_fields = array(
        "name = '$name'",
        "gender = '$gender'",
        "birthyear = '$birthyear'",
        "birthday = '$birthday'",
        "birthtime = '$birthtime'",
        "phone = '$phone'",
        "calendarType = '$calendarType'" // DB 컬럼 반영
    );

    // 비밀번호 입력 여부에 따른 분기 처리
    if (!empty($_POST['password'])) {
        $pw = $_POST['password'];
        $hashed_pw = md5($pw); // 기존 시스템의 암호화 방식 일치
        $update_fields[] = "password = '$hashed_pw'";
    }

    // 프로필 이미지 파일 업로드 처리
    if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] == 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_ext = pathinfo($_FILES['profile_img']['name'], PATHINFO_EXTENSION);
        $new_file_name = time() . '_' . uniqid() . '.' . $file_ext;
        
        if (move_uploaded_file($_FILES['profile_img']['tmp_name'], $upload_dir . $new_file_name)) {
            $update_fields[] = "profile_img = '$new_file_name'";
        }
    }

    // 데이터베이스 업데이트 수행 (안전한 implmde 결합 방식)
    $sql = "UPDATE nasagung_users SET " . implode(', ', $update_fields) . " WHERE email = '$user_email'";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['user_name'] = $name; // 세션 동기화
        echo "<script>alert('회원 정보가 성공적으로 수정되었습니다.'); location.href='mypage.php';</script>";
        exit;
    } else {
        $msg = "<div class='alert alert-danger'>오류 발생: " . mysqli_error($conn) . "</div>";
    }
}

// 5. 이미지 경로 및 12지신 자동 할당 로직
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
    <title>나사궁 - 내 정보 수정</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+KR:wght@400;700&family=Pretendard:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        a { text-decoration: none; color: black; }
        a:visited { color: black; }
        a:hover { color: gray; }
        a:active { color: black; }

        :root {
            --bg-dark: #0a1128;
            --accent-gold: #cfb53b;
            --white: #ffffff;
            --text-muted: #bdc3c7;
            --glass-bg: rgba(255, 255, 255, 0.92);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Pretendard', sans-serif; 
            background: var(--bg-dark); 
            color: var(--white); 
            overflow-x: hidden; 
        }

        /* index.html 일체형 고정 상단 메뉴 바 */
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

        .user-welcome { font-size: 0.9rem; color: var(--text-muted); }
        .user-welcome strong { color: var(--accent-gold); }
        .btn-logout { background: transparent; border: 1px solid rgba(255,255,255,0.4); color: var(--text-muted); padding: 6px 14px; border-radius: 5px; font-size: 0.85rem; cursor: pointer; }
        
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


        /* 배경 및 전체 레이아웃 너비 조절 */
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

        .hero-content { position: relative; z-index: 1; max-width: 800px; width: 100%; margin: 0 auto; padding: 20px; }

        .intro-text { text-align: center; margin-bottom: 30px; }
        .intro-text h1 { font-family: 'Noto Serif KR', serif; font-size: 2.5rem; text-shadow: 2px 2px 10px rgba(0,0,0,0.8); }
        .intro-text .highlight { color: var(--accent-gold); }

        /* 정보 수정 흰색 카드 본체 */
        .edit-section { 
            background: var(--glass-bg); 
            padding: 45px; 
            border-radius: 15px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.3); 
            color: #333; 
        }

        .edit-form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px 30px; 
            margin-bottom: 25px;
        }
        
        .form-group { display: flex; flex-direction: column; }
        .form-group.full-width { grid-column: span 2; }
        
        .form-group label { font-size: 0.95rem; font-weight: 600; margin-bottom: 8px; color: #444; }
        .form-group label span { color: #888; font-weight: normal; font-size: 0.8rem; margin-left: 5px; }
        .form-group input, .form-group select { padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; color: #333; transition: 0.3s; }
        .form-group input:focus, .form-group select:focus { border-color: var(--accent-gold); outline: none; box-shadow: 0 0 0 3px rgba(207, 181, 59, 0.2); }
        
        .form-group input[readonly] { background-color: #e9ecef; color: #6c757d; cursor: not-allowed; }

        .gender-group { display: flex; gap: 10px; }
        .gender-btn { flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 8px; background: #fff; cursor: pointer; font-weight: 600; transition: 0.3s; color: #333; }
        .gender-btn.active { background: var(--bg-dark); color: var(--white); border-color: var(--bg-dark); }

        .avatar-preview-container { display: flex; align-items: center; gap: 20px; }
        .avatar-preview { width: 80px; height: 80px; border-radius: 50%; border: 2px solid var(--accent-gold); object-fit: cover; background: #f7f3eb; padding: 3px; }

        .action-buttons { display: flex; gap: 15px; margin-top: 35px; }
        .btn-submit-edit { background: var(--accent-gold); color: #000; border: none; padding: 14px; border-radius: 8px; font-size: 1.1rem; font-weight: 700; flex: 1.5; cursor: pointer; transition: 0.3s; }
        .btn-submit-edit:hover { background: #b89e2b; transform: translateY(-2px); }
        .btn-cancel { background: transparent; border: 1px solid #aaa; color: #666; padding: 14px; border-radius: 8px; font-size: 1.1rem; font-weight: 600; flex: 1; text-align: center; display: inline-block; transition: 0.3s; }
        .btn-cancel:hover { background: #eee; color: #333; }

        @media (max-width: 768px) {
            .edit-form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
            .action-buttons { flex-direction: column; }
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
            <h1><span class="highlight">회원 정보</span> 수정</h1>
        </section>

        <section class="edit-section">
            <?php echo $msg; ?>
            <form action="edit_profile.php" method="POST" enctype="multipart/form-data">
                
                <div class="form-group full-width mb-4">
                    <label>프로필 사진 변경</label>
                    <div class="avatar-preview-container">
                        <img src="<?php echo $img_display_path; ?>" id="avatar_img_preview" class="avatar-preview" alt="프로필 미리보기">
                        <input type="file" name="profile_img" id="profile_file_input" class="form-control" accept="image/*">
                    </div>
                </div>

                <div class="edit-form-grid">
                    
                    <div class="form-group">
                        <label>이메일 계정 <span>(변경 불가)</span></label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>이름</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>비밀번호 변경 <span>(변경 시에만 입력)</span></label>
                        <input type="password" name="password" placeholder="변경하지 않으려면 공란으로 유지">
                    </div>

                    <div class="form-group">
                        <label>성별</label>
                        <div class="gender-group">
                            <button type="button" class="gender-btn <?php echo (trim($user['gender']) == 'male') ? 'active' : ''; ?>" onclick="setGender('male')">남성</button>
                            <button type="button" class="gender-btn <?php echo (trim($user['gender']) == 'female') ? 'active' : ''; ?>" onclick="setGender('female')">여성</button>
                        </div>
                        <input type="hidden" name="gender" id="gender_hidden" value="<?php echo htmlspecialchars($user['gender']); ?>">
                    </div>

                    <div class="form-group">
                        <label>출생연도</label>
                        <input type="number" name="birthyear" value="<?php echo htmlspecialchars($user['birthyear']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>생일 (MM-DD)</label>
                        <input type="text" name="birthday" value="<?php echo htmlspecialchars($user['birthday']); ?>" placeholder="예: 05-20" required>
                    </div>

                    <div class="form-group">
                        <label>태어난 시간 (시주)</label>
                        <select name="birthtime" class="form-select">
                            <?php 
                            $times = array(
                                '子' => '子시 (23:30 ~ 01:29)', '丑' => '丑시 (01:30 ~ 03:29)', 
                                '寅' => '寅시 (03:30 ~ 05:29)', '卯' => '卯시 (05:30 ~ 07:29)', 
                                '辰' => '辰시 (07:30 ~ 09:29)', '巳' => '巳시 (09:30 ~ 11:29)', 
                                '午' => '午시 (11:30 ~ 13:29)', '未' => '未시 (13:30 ~ 15:29)', 
                                '申' => '申시 (15:30 ~ 17:29)', '酉' => '酉시 (17:30 ~ 19:29)', 
                                '戌' => '戌시 (19:30 ~ 21:29)', '亥' => '亥시 (21:30 ~ 23:29)'
                            ); 
                            $current_time = isset($user['birthtime']) ? trim($user['birthtime']) : '';
                            foreach($times as $key => $val) {
                                $selected = ($current_time == $key) ? 'selected' : '';
                                echo "<option value='$key' $selected>$val</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>전화번호</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                    </div>

                    <!-- [추가된 영역] 달력 선택 메뉴 부트스트랩 클래스 및 동적 selected 적용 -->
                    <div class="form-group">
                        <label>달력 선택</label>
                        <select name="calendarType" class="form-select" required>
                            <?php
                            $cal_type = isset($user['calendarType']) ? trim($user['calendarType']) : '양력';
                            ?>
                            <option value="양력" <?php echo ($cal_type == '양력') ? 'selected' : ''; ?>>양력</option>
                            <option value="음력-평달" <?php echo ($cal_type == '음력-평달') ? 'selected' : ''; ?>>음력-평달</option>
                            <option value="음력-윤달" <?php echo ($cal_type == '음력-윤달') ? 'selected' : ''; ?>>음력-윤달</option>
                        </select>
                    </div>

                </div> 
                
                <div class="action-buttons">
                    <button type="submit" class="btn-submit-edit">수정 완료하기</button>
                    <a href="mypage.php" class="btn-cancel">취소</a>
                </div>
            </form>
        </section>
    </div>
</main>

<script>
    function setGender(gender) {
        document.getElementById('gender_hidden').value = gender;
        const btns = document.querySelectorAll('.gender-btn');
        btns.forEach(btn => btn.classList.remove('active'));
        
        if(gender === 'male') {
            btns[0].classList.add('active');
        } else {
            btns[1].classList.add('active');
        }
    }

    document.getElementById('profile_file_input').addEventListener('change', function(e) {
        if(this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(event) {
                document.getElementById('avatar_img_preview').src = event.target.result;
            }
            reader.readAsDataURL(this.files[0]);
        }
    });
</script>

</body>
</html>