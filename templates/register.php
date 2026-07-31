<?php
session_start();
include "db_conn.php"; 

$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $required_fields = array('email', 'password', 'name', 'gender', 'birthyear', 'birthday', 'birthtime', 'phone', 'calendarType');
    $is_valid = true;

    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $is_valid = false;
            break;
        }
    }

    if (!$is_valid) {
        $msg = "<div class='alert alert-danger'>모든 필수 항목(*)을 입력해주세요.</div>";
    } else {
        $email        = mysqli_real_escape_string($conn, $_POST['email']);
        $pw           = $_POST['password'];
        $name         = mysqli_real_escape_string($conn, $_POST['name']);
        $gender       = mysqli_real_escape_string($conn, $_POST['gender']);
        $birthyear    = mysqli_real_escape_string($conn, $_POST['birthyear']);
        $birthday     = mysqli_real_escape_string($conn, $_POST['birthday']);
        $birthtime    = mysqli_real_escape_string($conn, $_POST['birthtime']);
        $phone        = mysqli_real_escape_string($conn, $_POST['phone']);
        $calendarType = mysqli_real_escape_string($conn, $_POST['calendarType']);

        // --- 사진 처리 로직 ---
        $profile_img = ""; 
        if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] == 0) {
            $upload_dir = 'uploads/';
            $file_ext = pathinfo($_FILES['profile_img']['name'], PATHINFO_EXTENSION);
            $new_file_name = time() . '_' . uniqid() . '.' . $file_ext;
            if (move_uploaded_file($_FILES['profile_img']['tmp_name'], $upload_dir . $new_file_name)) {
                $profile_img = $new_file_name;
            }
        }
        if (empty($profile_img)) {
            $zodiac_icons = array(
                0 => "monkey.png", 1 => "rooster.png", 2 => "dog.png", 3 => "pig.png",
                4 => "rat.png",    5 => "ox.png",      6 => "tiger.png", 7 => "rabbit.png",
                8 => "dragon.png", 9 => "snake.png",  10 => "horse.png", 11 => "sheep.png"
            );
            $remainder = intval($birthyear) % 12;
            $profile_img = $zodiac_icons[$remainder];
        }

        // 비밀번호 검사
        $pattern = '/^(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[!@#$%^&*()_+]).{5,}$/';
        if (!preg_match($pattern, $pw)) {
            $msg = "<div class='alert alert-danger'>비밀번호는 영문, 숫자, 특수문자 포함 5자 이상이어야 합니다.</div>";
        } else {
            $hashed_pw = md5($pw); 
            
            $check_res = mysqli_query($conn, "SELECT id FROM nasagung_users WHERE email='$email' AND provider='local'");
            if (mysqli_num_rows($check_res) > 0) {
                $msg = "<div class='alert alert-danger'>이미 등록된 이메일입니다.</div>";
            } else {
            	$cur_point = 1000;
                
                // 💡 컬럼명을 calendar_type 명칭에서 calendarType 으로 원복 및 수정 완료
                $sql = "INSERT INTO nasagung_users (email, password, name, gender, birthyear, birthday, birthtime, phone, calendarType, profile_img, provider, current_point) 
                        VALUES ('$email', '$hashed_pw', '$name', '$gender', '$birthyear', '$birthday', '$birthtime', '$phone', '$calendarType', '$profile_img', 'local', '$cur_point')";
                
                if (mysqli_query($conn, $sql)) {
                    $msg = "<div class='alert alert-success'>가입되었습니다! <a href='login.php' class='alert-link'>로그인하기</a></div>";
                } else {
                    $msg = "<div class='alert alert-danger'>오류 발생: " . mysqli_error($conn) . "</div>";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>나사궁 - 회원가입</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
        body { font-family: 'Pretendard', sans-serif; background: var(--bg-dark); color: var(--white); overflow-x: hidden; }
	.reg-card { max-width: 550px; margin: auto; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); color: #333; }
        header {
            background: rgba(10, 17, 40, 0.8); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: fixed; width: 100%; top: 0; z-index: 1000;
        }
        .header-container { max-width: 1200px; margin: 0 auto; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-family: 'Noto Serif KR', serif; font-size: 1.5rem; font-weight: 700; color: var(--white); }
        .logo span { color: var(--accent-gold); font-size: 1.2rem; margin-left: 5px; }
        nav .main-menu { display: flex; list-style: none; }
        nav .main-menu li { margin: 0 15px; position: relative; }
        nav .main-menu li a { color: var(--white); text-decoration: none; font-size: 0.95rem; transition: 0.3s; }
        nav .main-menu li a:hover { color: var(--accent-gold); }
        .submenu {
            display: none; position: absolute; top: 100%; left: 0; background: #151e3d;
            min-width: 150px; border-radius: 5px; padding: 10px 0; list-style: none; box-shadow: 0 10px 20px rgba(0,0,0,0.5);
        }
        .submenu li a { padding: 8px 20px; display: block; font-size: 0.85rem; color: var(--white) !important; }
        .dropdown:hover .submenu { display: block; }
        .auth-buttons { display: flex; }
        .btn-login { background: transparent; border: 1px solid var(--white); color: var(--white); padding: 8px 18px; border-radius: 5px; margin-right: 10px; cursor: pointer; }
        .btn-signup { background: var(--accent-gold); border: none; color: #000; padding: 8px 18px; border-radius: 5px; font-weight: bold; cursor: pointer; }
        .hero {
            min-height: 100vh; background: linear-gradient(rgba(10, 17, 40, 0.5), rgba(10, 17, 40, 0.5)), url('images/background.png') center/cover no-repeat;
            background-attachment: fixed; position: relative; display: flex; align-items: center; justify-content: center; padding-top: 140px; padding-bottom: 60px;
        }
        .hero-content { position: relative; z-index: 1; max-width: 1100px; width: 100%; margin: 0 auto; padding: 40px 20px; }
        .intro-text { text-align: center; margin-bottom: 40px; }
        .intro-text h1 { font-family: 'Noto Serif KR', serif; font-size: 3.2rem; line-height: 1.3; margin-bottom: 15px; text-shadow: 2px 2px 10px rgba(0,0,0,0.8); }
        .intro-text .highlight { color: var(--accent-gold); }
        .intro-text p { color: var(--text-muted); font-size: 1.2rem; text-shadow: 1px 1px 5px rgba(0,0,0,0.8); }
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
        
        <div class="card reg-card p-4 mt-5">
            <h3 class="text-center fw-bold mb-4">회원가입</h3>
            <?php echo $msg; ?>
           
            <form action="register.php" method="POST" enctype="multipart/form-data">
                <div class="mb-3 text-center">
                    <label class="form-label d-block fw-bold text-primary">프로필 사진 (선택)</label>
                    <input type="file" name="profile_img" class="form-control" accept="image/*">
                    <small class="text-muted d-block mt-1">사진 미등록 시 띠별 캐릭터가 자동 등록됩니다.</small>
                </div>
            
                <div class="mb-3">
                    <label class="form-label">이메일*</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
            
                <div class="mb-3">
                    <label class="form-label">비밀번호*</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
            
                <div class="mb-3">
                    <label class="form-label">이름*</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
            
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">성별*</label>
                        <select name="gender" class="form-select" required>
                            <option value="남성">남성</option>
                            <option value="여성">여성</option>
                        </select>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">출생연도*</label>
                        <input type="number" name="birthyear" class="form-control" placeholder="예: 1990" required>
                    </div>
                </div>
            
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">생일* (MM-DD)</label>
                        <input type="text" name="birthday" class="form-control" placeholder="05-20" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">태어난 시간*</label>
                        <select name="birthtime" class="form-select" required>
                            <option value="子">子시 (23:30 ~ 01:29)</option>
                            <option value="丑">丑시 (01:30 ~ 03:29)</option>
                            <option value="寅">寅시 (03:30 ~ 05:29)</option>
                            <option value="卯">卯시 (05:30 ~ 07:29)</option>
                            <option value="辰">辰시 (07:30 ~ 09:29)</option>
                            <option value="巳">巳시 (09:30 ~ 11:29)</option>
                            <option value="午">午시 (11:30 ~ 13:29)</option>
                            <option value="未">未시 (13:30 ~ 15:29)</option>
                            <option value="申">申시 (15:30 ~ 17:29)</option>
                            <option value="酉">酉시 (17:30 ~ 19:29)</option>
                            <option value="戌">戌시 (19:30 ~ 21:29)</option>
                            <option value="亥">亥시 (21:30 ~ 23:29)</option>
                            <option value="모름">모름</option>
                        </select>
                    </div>
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label">달력 선택*</label>
                    <select name="calendarType" class="form-select" required>
                        <option value="양력">양력 (Solar)</option>
                        <option value="음력-평달">음력 평달 (Lunar Regular)</option>
                        <option value="음력-윤달">음력 윤달 (Lunar Leap)</option>
                    </select>
                </div>
            
                <div class="mb-4">
                    <label class="form-label">전화번호*</label>
                    <input type="tel" name="phone" class="form-control" required>
                </div>
            
                <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">가입하기</button>
            </form>
        </div>
    </div>
</main>
</body>
</html>