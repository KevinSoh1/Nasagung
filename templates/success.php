<?php
session_start(); // 로그인 세션 정보를 읽기 위해 최상단에 필수 추가
// 로그인하지 않은 사용자는 로그인 페이지로 리다이렉트
if (!isset($_SESSION['user_email'])) {
    echo "<script>alert('로그인이 필요한 서비스입니다.'); location.href='login.php';</script>";
    exit;
}
// success.php
include 'db_conn.php'; // 기존 DB 연결 설정 파일

// 1. URL 파라미터 안전하게 가져오기
$user_id    = isset($_GET['user_id']) ? mysqli_real_escape_string($conn, $_GET['user_id']) : '';
$paymentKey = isset($_GET['paymentKey']) ? mysqli_real_escape_string($conn, $_GET['paymentKey']) : '';
$orderId    = isset($_GET['orderId']) ? mysqli_real_escape_string($conn, $_GET['orderId']) : '';
$amount     = isset($_GET['amount']) ? intval($_GET['amount']) : 5000; 
$amountT = 1000;		//테스트를 위해 1000원만 결재하도록 수정.
// 2. 🔑 내 토스페이먼츠 시크릿 키 입력
$secretKey = "test_sk_AQ92ymxN34gMmRGOJa7A8ajRKXvd"; 
$credential = base64_encode($secretKey . ":");

// 3. 토스페이먼츠 최종 결제 승인 요청 (cURL)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.tosspayments.com/v1/payments/confirm");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);

// 💡 [수정 내용 1] json_encode 내부를 array() 구문으로 안전하게 매칭
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
    'paymentKey' => $paymentKey,
    'orderId' => $orderId,
//    'amount' => $amount
    'amount' => $amountT	//테스트를 위해 1000원만 결재하도록 수정.
)));

// 💡 [수정 내용 2] 헤더 설정 배열 또한 대괄호 [ ] 대신 array( )로 완벽하게 변경
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Basic ' . $credential,
    'Content-Type: application/json'
));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$resData = json_decode($response, true);

// 4. 토스페이먼츠 승인이 최종 성공(200 OK)했는지 확인
if ($httpCode === 200 && isset($resData['status']) && $resData['status'] === 'DONE') {
    
    // 토스 응답 데이터에서 실제 결제 수단 추출 (카드, 가상계좌, 휴대폰 등)
    $method = isset($resData['method']) ? mysqli_real_escape_string($conn, $resData['method']) : '카드';

    // 데이터 일관성을 위해 트랜잭션(Transaction) 시작
    //mysqli_begin_transaction($conn);
    mysqli_query($conn, "SET AUTOCOMMIT=0");
    mysqli_query($conn, "START TRANSACTION");

    try {
    	
    	$calculatedPoint = floor($amount * 1.1);
        // 5-1. 💰 [유저 포인트 업데이트] 기존 보유 포인트에 결제 금액만큼 합산
        $updateUserSql = "UPDATE nasagung_users 
                          SET current_point = current_point + $calculatedPoint 
                          WHERE email = '$user_id'";
        mysqli_query($conn, $updateUserSql);

        // 5-2. 💳 [payments 테이블 저장] 결제 원장 기록 누적
        //$insertPaymentSql = "INSERT INTO payments (email, order_merchant_id, order_id, amount, method, status, created_at) 
        //                     VALUES ('$user_id', '$paymentKey', '$orderId', $amount, '$method', 'SUCCESS', NOW())";
        $insertPaymentSql = "INSERT INTO payments (email, order_merchant_id, pay_amount, got_point, pay_method, pg_tid, status, paid_at) 
        		     VALUES ('$user_id', '$orderId', $amount, $calculatedPoint, '$method', '$paymentKey', 'SUCCESS', NOW())";
        mysqli_query($conn, $insertPaymentSql);

        // 5-3. 📈 [Point_history 테이블 저장] 포인트 변동 이력 로그 적립
        //$pointDesc = "포인트 충전소 (" . number_format($amount) . "원 결제)";
        //$insertHistorySql = "INSERT INTO Point_history (user_id, order_id, point_change, description, created_at) 
        //                     VALUES ('$user_id', '$orderId', $amount, '$pointDesc', NOW())";
        
        $pointDesc = "포인트 충전소 (" . number_format($amount) . "원 결제 + 10% 보너스)";
        $insertHistorySql = "INSERT INTO point_history (email, type, amount, description, target_id, created_at) 
        		     VALUES ('$user_id', '$method', $calculatedPoint, '$pointDesc', NULL, NOW())";
        //mysqli_query($conn, $insertHistorySql);
        mysqli_query($conn, $insertHistorySql);

        // 모든 쿼리가 에러 없이 성공했다면 DB에 최종 반영
        //mysqli_commit($conn);
        mysqli_commit($conn);
	mysqli_query($conn, "SET AUTOCOMMIT=1"); // 오토커밋 복구

        // 성공 알림 후 메인 페이지 이동
        echo "<script>
            alert('포인트 충전 및 결제 처리가 완료되었습니다.');
            window.location.href = 'http://www.highteck.kr/nasagung/index.php?user_id=' + encodeURIComponent('" . $user_id . "');
        </script>";
        exit;

    } catch (Exception $e) {
        // 하나라도 실패 시 데이터 롤백(취소) 처리
        //mysqli_rollback($conn);
        mysqli_rollback($conn);
	mysqli_query($conn, "SET AUTOCOMMIT=1"); // 오토커밋 복구
        
        echo "<script>
            alert('결제는 완료되었으나 시스템 DB 반영 중 오류가 발생했습니다. 관리자에게 문의하세요.');
            window.location.href = 'http://www.highteck.kr/nasagung/index.php?user_id=' + encodeURIComponent('" . $user_id . "');
        </script>";
        exit;
    }

} else {
    // 토스 측에서 승인이 거절된 경우
    $errorMsg = isset($resData['message']) ? $resData['message'] : '결제 승인에 실패했습니다.';
    echo "<script>
        alert('실패 원인: " . addslashes($errorMsg) . "');
        window.location.href = 'http://www.highteck.kr/nasagung/fail.html?user_id=' + encodeURIComponent('" . $user_id . "');
    </script>";
    exit;
}
?>