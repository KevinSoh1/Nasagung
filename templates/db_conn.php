<?php
// db_conn.php
$db_host = "localhost";
$db_user = "highteck";
$db_pass = "high#@!%";
$db_name = "highteck";

// --- [1. DB 연결] ---
// 구형 PHP 환경과의 호환성을 위해 절차지향 방식을 권장합니다.
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// --- [2. 연결 체크 및 에러 출력] ---
if (!$conn) {
    // 연결 실패 시 구체적인 에러 번호와 메시지를 출력하여 원인 파악을 돕습니다.
    die("DB 연결 실패 (에러번호: " . mysqli_connect_errno() . "): " . mysqli_connect_error());
}

// --- [3. 한글 깨짐 방지 설정] ---
// 연결 성공 시 즉시 캐릭터셋을 utf8로 강제 지정합니다.
if (!mysqli_set_charset($conn, "utf8")) {
    // 캐릭터셋 설정 실패 시 (거의 없지만 보안 차원)
    die("캐릭터셋 설정 오류: " . mysqli_error($conn));
}
?>