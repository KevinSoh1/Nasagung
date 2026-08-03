<?php
// Render Environment Variables에서 접속 정보 불러오기
$db_host = getenv('DB_HOST') ?: mysql://avnadmin:AVNS_dlJ3IOY5zdNXOk81fy6@nasagung-nasagung.g.aivencloud.com:24465/defaultdb?ssl-mode=REQUIRED;
$db_port = getenv('DB_PORT') ?: 24465;
$db_user = getenv('DB_USER') ?: avnadmin;
$db_pass = getenv('DB_PASS') ?: AVNS_dlJ3IOY5zdNXOk81fy6;
$db_name = getenv('DB_NAME') ?: defaultdb;

// MySQLi 객체 생성
$conn = mysqli_init();

if (!$conn) {
    die("mysqli_init 실패");
}

// 💡 Aiven MySQL 8.0 SSL 및 Public Key 보안 설정 대응
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);

// DB 연결 시도 (포트 $db_port 필수 명시)
$success = mysqli_real_connect(
    $conn,
    $db_host,
    $db_user,
    $db_pass,
    $db_name,
    (int)$db_port,
    NULL,
    MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT
);

if (!$success) {
    // 연결 실패 시 메시지 박스 출력 후 중단
    $error_msg = addslashes(mysqli_connect_error());
    echo "<script>alert('Aiven DB 연결 실패: {$error_msg}');</script>";
    die("Aiven DB 연결 실패: " . mysqli_connect_error());
}
// 한글 및 사주 한자 깨짐 방지 인코딩 설정

mysqli_set_charset($conn, "utf8mb4");
echo "<script>alert('Aiven DB 연결에 성공하였습니다!');</script>";
?>
