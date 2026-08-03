<?php
// Render Environment Variables에서 접속 정보 불러오기
$db_host = getenv('DB_HOST');
$db_port = getenv('DB_PORT');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');

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
    die("Aiven DB 연결 실패: " . mysqli_connect_error());
}

// 한글 및 사주 한자 깨짐 방지 인코딩 설정
mysqli_set_charset($conn, "utf8mb4");

echo "Aiven DB 연결 성공!";
?>
