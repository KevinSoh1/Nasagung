<?php
    // pointAdd.php의 실시간 DB 조회 로직 반영
    // 부모 파일(todayLock.php)에서 세션이 켜져 있으므로 여기서는 DB 조회만 수행합니다.
    include 'db_conn.php'; 
        
   // 로그인하지 않은 사용자는 로그인 페이지로 리다이렉트
   if (!isset($_SESSION['user_email'])) {
   echo "<script>alert('로그인이 필요한 서비스입니다.'); location.href='login.php';</script>";
   exit;
   }

// 로그인된 사용자의 상세 정보 DB에서 가져오기
$user_email = $_SESSION['user_email'];
$sql = "SELECT * FROM nasagung_users WHERE email = '$user_email'";
$result = mysqli_query($conn, $sql);
$user = mysqli_fetch_assoc($result);
    
    $user_point = 0;
    if (isset($_SESSION['user_email'])) {
        $user_email = mysqli_real_escape_string($conn, $_SESSION['user_email']);
        $sql = "SELECT current_point FROM nasagung_user WHERE email = '$user_email'";
        $result = mysqli_query($conn, $sql);
        
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $user_point = $row['current_point'];
        }
    }
   
?>
<style>
    /* 우측 사이드 퀵메뉴 너비 및 내부 충전 레이아웃 보정 스타일 */
    .quick-menu {
        flex: 1;
        width: 100%;
        max-width: 450px;
        margin: 0 auto;
    }
    
    .quick-item-charge {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 25px;
        border-radius: 15px;
        display: flex;
        flex-direction: column;
        gap: 20px;
        color: var(--white);
        border: 1px solid rgba(207, 181, 59, 0.2);
        backdrop-filter: blur(10px);
    }
    .charge-title {
        font-size: 1.4rem;
        color: var(--accent-gold);
        font-weight: 700;
        text-align: center;
        margin-bottom: 5px;
    }
    .balance-box {
        background: rgba(207, 181, 59, 0.08);
        border: 1px solid rgba(207, 181, 59, 0.3);
        border-radius: 10px;
        padding: 12px 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .balance-box span { font-size: 0.85rem; color: var(--text-muted); }
    .balance-box .amount { font-size: 1.1rem; font-weight: 700; color: var(--accent-gold); }

    .step-label { font-size: 0.9rem; font-weight: 600; color: var(--white); margin-bottom: -5px; }

    /* 콤팩트 라디오 그리드 디자인 */
    .p-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    .p-option input[type="radio"] { display: none; }
    .p-label {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 12px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: center;
    }
    .p-label:hover { border-color: rgba(207, 181, 59, 0.4); }
    .p-option input[type="radio"]:checked + .p-label {
        border-color: var(--accent-gold);
        background: rgba(207, 181, 59, 0.12);
    }
    .p-title { font-size: 1rem; font-weight: 700; color: var(--white); }
    .p-option input[type="radio"]:checked + .p-label .p-title { color: var(--accent-gold); }
    .p-price { font-size: 0.8rem; color: var(--text-muted); }
    .b-badge {
        background: #e74c3c; color: white; font-size: 0.65rem; padding: 1px 5px; border-radius: 10px; margin-bottom: 4px; font-weight: 600;
    }

    /* 결제 수단 가로 배열 */
    .m-group { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .m-option input[type="radio"] { display: none; }
    .m-label {
        display: block; text-align: center; padding: 10px; background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 6px; cursor: pointer; font-size: 0.8rem; transition: 0.2s;
    }
    .m-label:hover { border-color: rgba(255,255,255,0.2); }
    .m-option input[type="radio"]:checked + .m-label { border-color: var(--white); background: rgba(255, 255, 255, 0.15); font-weight: 600; }

    /* 합계창 및 실행 버튼 */
    .total-box {
        border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 15px;
        display: flex; justify-content: space-between; align-items: center;
    }
    .total-box .total-lbl { font-size: 0.9rem; color: var(--text-muted); }
    .total-box .total-val { font-size: 1.3rem; font-weight: 700; color: var(--accent-gold); }

    .btn-charge-submit {
        background: var(--accent-gold); color: #000; border: none; padding: 12px;
        border-radius: 6px; font-size: 1rem; font-weight: 700; width: 100%; cursor: pointer; transition: 0.3s;
    }
    .btn-charge-submit:hover { background: #b89e2b; transform: translateY(-1px); }
</style>

<aside class="quick-menu">
    <form id="sidePointChargeForm" action="pointCharge_process.php" method="POST">
        <div class="quick-item-charge">
            <div class="charge-title">포인트 충전소</div>
            
            <div class="balance-box">
                <span>현재 보유 포인트</span>
                <span class="amount"><?php echo number_format($user_point); ?> P</span>
            </div>

            <div class="step-label">충전 금액 선택</div>
            <div class="p-grid">
                <div class="p-option">
                    <input type="radio" name="point_amount" id="side_p1" value="5000" data-price="5000" checked>
                    <label for="side_p1" class="p-label">
                        <div class="p-title">5,000 P</div>
                        <div class="p-price">5,000 원</div>
                    </label>
                </div>

                <div class="p-option">
                    <input type="radio" name="point_amount" id="side_p2" value="10000" data-price="10000">
                    <label for="side_p2" class="p-label">
                        <div class="p-title">10,000 P</div>
                        <div class="p-price">10,000 원</div>
                    </label>
                </div>

                <div class="p-option">
                    <input type="radio" name="point_amount" id="side_p3" value="33000" data-price="30000">
                    <label for="side_p3" class="p-label">
                        <span class="b-badge">+10% 보너스</span>
                        <div class="p-title">33,000 P</div>
                        <div class="p-price">30,000 원</div>
                    </label>
                </div>

                <div class="p-option">
                    <input type="radio" name="point_amount" id="side_p4" value="57500" data-price="50000">
                    <label for="side_p4" class="p-label">
                        <span class="b-badge">+15% 보너스</span>
                        <div class="p-title">57,500 P</div>
                        <div class="p-price">50,000 원</div>
                    </label>
                </div>
            </div>

            <div class="step-label">결제 수단 선택</div>
            <div class="m-group">
                <div class="m-option">
                    <input type="radio" name="pay_method" id="side_m1" value="card" checked>
                    <label for="side_m1" class="m-label">신용카드</label>
                </div>
                <div class="m-option">
                    <input type="radio" name="pay_method" id="side_m2" value="kakao">
                    <label for="m2" class="m-label">카카오페이</label>
                </div>
                <div class="m-option">
                    <input type="radio" name="pay_method" id="side_m3" value="phone">
                    <label for="side_m3" class="m-label">휴대폰</label>
                </div>
            </div>

            <div class="total-box">
                <div class="total-lbl">최종 결제 금액</div>
                <div class="total-val" id="sideDisplayTotalPrice">5,000 원</div>
            </div>

            <button type="submit" class="btn-charge-submit">안전하게 결제하기</button>
        </div>
    </form>
</aside>

<script>
    // 사이드바 전용 포인트 실시간 금액 포맷팅 스크립트
    const sidePointRadios = document.querySelectorAll('input[name="point_amount"]');
    const sideDisplayPrice = document.getElementById('sideDisplayTotalPrice');

    sidePointRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            const price = parseInt(this.getAttribute('data-price'));
            sideDisplayPrice.textContent = price.toLocaleString() + " 원";
        });
    });
</script>