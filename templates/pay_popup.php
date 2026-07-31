<?php
session_start();
include "db_conn.php";

if (!isset($_SESSION['user_email'])) {
    echo "<script>alert('로그인이 필요한 서비스입니다.'); window.close();</script>";
    exit;
}

$user_email = $_SESSION['user_email'];
$user_email = mysqli_real_escape_string($conn, $user_email);

// 1. 현재 사용자의 보유 포인트 확인
$user_sql = "SELECT current_point FROM nasagung_users WHERE email = '$user_email'";
$user_res = mysqli_query($conn, $user_sql);
$user_data = mysqli_fetch_assoc($user_res);
$current_point = intval($user_data['current_point']);

$price = 500; // 서비스 차감액
$msg = "";
$pay_success = false;

// 2. 결제 Submit 요청 시 트랜잭션 연산
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_pay'])) {
    if ($current_point < $price) {
        $msg = "<div class='alert alert-danger'>보유 포인트가 부족합니다. 충전 후 이용해 주세요.</div>";
    } else {
        $new_point = $current_point - $price;
        $update_sql = "UPDATE nasagung_users SET current_point = $new_point WHERE email = '$user_email'";
        
        if (mysqli_query($conn, $update_sql)) {
            // point_history 명세 컬럼 구조 규격 매칭
            $type = "use";
            $amount = $price;
            $description = "AI 로또 예측 번호 전체 해금 구매";
            $target_id = "lotto_" . time();
            
            // PHP 구버전 컴파일 구조에 최적화된 표준 SQL 매칭 선언
            $history_sql = "INSERT INTO point_history (email, type, amount, description, target_id, created_at) 
                            VALUES ('$user_email', '$type', $amount, '$description', '$target_id', NOW())";
            mysqli_query($conn, $history_sql);
            
            $pay_success = true;
        } else {
            $msg = "<div class='alert alert-danger'>결제 처리 중 오류가 발생했습니다.</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>나사궁 - 번호 조합 구매하기</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #0a1128; color: #fff; font-family: 'Pretendard', sans-serif; padding: 25px; text-align: center; }
        .pay-box { background: rgba(255,255,255,0.1); border-radius: 12px; padding: 20px; border: 1px solid rgba(207,181,59,0.3); margin-top: 15px; }
        .gold-txt { color: #cfb53b; font-weight: 700; }
        .btn-pay { background: #cfb53b; color: #000; font-weight: bold; width: 100%; padding: 12px; border: none; border-radius: 6px; margin-top: 15px; }
        .btn-pay:hover { background: #b89e2b; }
    </style>
</head>
<body>

    <h4 class="fw-bold gold-txt mb-3">?? AI 행운의 번호 전체 열람</h4>
    <p class="small text-muted">천기의 기운을 담아 생성된 나머지 로또 조합을 모두 잠금 해제합니다.</p>

    <?php if ($pay_success): ?>
        <div class="pay-box mt-4">
            <h5 class="fw-bold text-success mb-3">?? 구매가 완료되었습니다!</h5>
            <p class="small">분석 페이지에서 번호 조합 출력을 순차적으로 진행합니다.</p>
        </div>
        <script>
            // 부모창 인터페이스 통신 활성화 후 스스로 닫기
            if (window.opener && !window.opener.closed) {
                window.opener.completePayment();
            }
            setTimeout(function() {
                window.close();
            }, 1200);
        </script>
    <?php else: ?>
        <div class="pay-box">
            <div class="d-flex justify-content-between mb-2">
                <span>상품 금액 :</span>
                <span class="fw-bold"><?php echo $price; ?> P</span>
            </div>
            <div class="d-flex justify-content-between border-top pt-2">
                <span>나의 보유 포인트 :</span>
                <span class="fw-bold gold-txt"><?php echo $current_point; ?> P</span>
            </div>
        </div>

        <?php echo $msg; ?>

        <form method="POST" action="pay_popup.php">
            <input type="hidden" name="action_pay" value="1">
            <?php if ($current_point >= $price): ?>
                <button type="submit" class="btn-pay">500 포인트 차감 및 결제하기</button>
            <?php else: ?>
                <button type="button" class="btn-pay bg-danger text-white" onclick="alert('포인트가 부족합니다. 마이페이지 등에서 먼저 충전해 주세요.');">포인트 부족 (충전 필요)</button>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-secondary w-100 mt-2 text-white" onclick="window.close();">취소</button>
        </form>
    <?php endif; ?>

</body>
</html>