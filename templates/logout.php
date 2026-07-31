<?php
// logout.php
session_start();

// 1. 모든 세션 변수 해제
$_SESSION = array();

// 2. 세션 쿠키 삭제 (브라우저에 남은 흔적 제거)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. 세션 파괴
session_destroy();

// 4. 알림 후 로그인 페이지로 이동
echo "<script>alert('로그아웃 되었습니다.'); location.href='/nasagung/';</script>";
exit;
?>