<?php
	session_start(); // 로그인 세션 정보를 읽기 위해 최상단에 필수 추가
    	// pointAdd.php의 실시간 DB 조회 로직 반영
    	// 부모 파일(todayLock.php)에서 세션이 켜져 있으므로 여기서는 DB 조회만 수행합니다.
    	
    	// 로그인하지 않은 사용자는 로그인 페이지로 리다이렉트
	if (!isset($_SESSION['user_email'])) {
    		echo "<script>alert('로그인이 필요한 서비스입니다.'); location.href='login.php';</script>";
    		exit;
	}

    	include 'db_conn.php'; 
        
   	// 1. 먼저 세션에 로그인 정보가 있는지 '안전하게' 확인합니다.
	if (isset($_SESSION['user_email'])) {
    
    	// 2. 보안을 위해 SQL 인젝션 방지 처리를 합니다.
    	$user_email = mysqli_real_escape_string($conn, $_SESSION['user_email']);
    
    	// 3. 쿼리를 한 번만 날려서 전체 정보(포인트 포함)를 모두 가져옵니다.
    	// (테이블명은 실제 DB에 맞춰 nasagung_users 또는 nasagung_user 중 하나로 통일하세요)
    	$sql = "SELECT * FROM nasagung_users WHERE email = '$user_email'";
    	$result = mysqli_query($conn, $sql);
    
    	if ($result && $row = mysqli_fetch_assoc($result)) {
        	// 회원 정보 전체를 $user 배열에 담기
        	$user = $row;
        	// 포인트 정보만 따로 변수에 담기
        	$user_point = $row['current_point'];
    	}

	} else {
    		// 로그인이 안 된 사용자일 경우 처리 (예: 로그인 페이지로 리다이렉트 등)
    		echo "로그인이 필요합니다.";
    		exit;
	}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>나사궁 - 오늘의 운</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://js.tosspayments.com/v1/payment"></script>
    <style>
    	a {
            text-decoration: none;   /* 밑줄 제거 */
    	    color: black;            /* 기본 색상 지정 */
  	}

  	a:visited {
    	    color: black;            /* 방문 후에도 같은 색상 유지 */
  	}

  	a:hover {
    	    color: gray;             /* 마우스 올렸을 때 색상 */
  	}

  	a:active {
    	    color: black;            /* 클릭 시에도 같은 색상 유지 */
  	}
        :root {
            --bg-dark: #0a1128;
            --accent-gold: #cfb53b;
            --white: #ffffff;
            --text-muted: #bdc3c7;
            --glass-bg: rgba(255, 255, 255, 0.92);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { font-family: 'Pretendard', sans-serif; background: var(--bg-dark); color: var(--white); overflow-x: hidden; }

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
        .user-welcome { font-size: 0.9rem; color: var(--text-muted); }
        .user-welcome strong { color: var(--accent-gold); }
        
        .btn-logout { background: transparent; border: 1px solid rgba(255,255,255,0.4); color: var(--text-muted); padding: 6px 14px; border-radius: 5px; font-size: 0.85rem; cursor: pointer; transition: 0.3s; }
        .btn-logout:hover { border-color: var(--white); color: var(--white); }

        .hero {
            min-height: 100vh;
            background: linear-gradient(rgba(10, 17, 40, 0.5), rgba(10, 17, 40, 0.5)), 
                        url('images/background.png') center/cover no-repeat;
            background-attachment: fixed;
            position: relative; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            padding-top: 120px; 
            padding-bottom: 60px;
        }

        .hero-content { position: relative; z-index: 1; max-width: 1200px; width: 100%; margin: 0 auto; padding: 40px 20px;}

        .intro-text { text-align: center; margin-top: 0; margin-bottom: 50px; }
        .intro-text h1 { font-family: 'Noto Serif KR', serif; font-size: 3.2rem; line-height: 1.3; margin-bottom: 15px; text-shadow: 2px 2px 10px rgba(0,0,0,0.8); }
        .intro-text .highlight { color: var(--accent-gold); }
        .intro-text p { color: var(--text-muted); font-size: 1.2rem; text-shadow: 1px 1px 5px rgba(0,0,0,0.8); }

        .main-content-layout { display: flex; gap: 40px; align-items: flex-start; justify-content: center; }

        .saju-section { flex: 1.2; background: var(--glass-bg); padding: 40px; border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); color: #333; }
        .saju-section h2 { font-family: 'Noto Serif KR', serif; font-size: 1.8rem; margin-bottom: 30px; color: var(--bg-dark); font-weight: 700; text-align: center; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full-width { grid-column: span 2; }
        .form-group label { font-size: 0.9rem; font-weight: 600; margin-bottom: 8px; color: #555; }
        .form-group input, .form-group select { padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; transition: 0.3s; }
        .form-group input:focus, .form-group select:focus { border-color: var(--accent-gold); outline: none; box-shadow: 0 0 0 3px rgba(207, 181, 59, 0.2); }

        .gender-group { display: flex; gap: 10px; }
        .gender-btn { flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 8px; background: #fff; cursor: pointer; font-weight: 600; transition: 0.3s; text-align: center;}
        .gender-btn.active { background: var(--bg-dark); color: var(--white); border-color: var(--bg-dark); }

        .btn-submit { background: var(--accent-gold); color: #000; border: none; padding: 15px; border-radius: 8px; font-size: 1.1rem; font-weight: 700; width: 100%; cursor: pointer; transition: 0.3s; margin-top: 10px; }
        .btn-submit:hover { background: #b89e2b; transform: translateY(-2px); }

        .promo-card { background: linear-gradient(135deg, #1e295d, #0a1128); border: 1px solid rgba(207, 181, 59, 0.3); padding: 25px; border-radius: 15px; display: flex; justify-content: space-between; align-items: center; position: relative; overflow: hidden; margin-top: 20px;}
        .promo-card::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(207,181,59,0.1) 0%, transparent 70%); }
        .promo-text strong { display: block; font-size: 1.2rem; color: var(--accent-gold); margin-bottom: 5px; }
        .promo-text p { font-size: 0.85rem; color: var(--text-muted); margin: 0; }
        .promo-gift { font-size: 3rem; }

        @media (max-width: 992px) {
            .main-content-layout { flex-direction: column; }
            .hero { height: auto; padding: 120px 0 60px; }
            .intro-text h1 { font-size: 2.2rem; }
        }
        
    .quick-menu {
        flex: 1;
        width: 100%;
        max-width: 450px;
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

    .m-group { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .m-option input[type="radio"] { display: none; }
    .m-label {
        display: block; text-align: center; padding: 10px; background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 6px; cursor: pointer; font-size: 0.8rem; transition: 0.2s;
    }
    .m-label:hover { border-color: rgba(255,255,255,0.2); }
    .m-option input[type="radio"]:checked + .m-label { border-color: var(--white); background: rgba(255, 255, 255, 0.15); font-weight: 600; }

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
</head>
<body>

<header>
   <?php include 'top_menu.php' ?>
   <span id="email" data-email="<?php echo htmlspecialchars($user_email); ?>" style="display:none;"></span>
</header>

<main class="hero">
    <div class="hero-content">
        <section class="intro-text">
            <h1>당신의 운명은 이미 <span class="highlight">흐르고</span> 있습니다</h1>
            <p>사주팔자로 미래의 방향을 확인해보세요</p>
        </section>

        <div class="main-content-layout">
            <aside class="quick-menu">
    			<form id="sidePointChargeForm">
        			<div class="quick-item-charge">
            				<div class="charge-title">포인트 충전소</div>
            
            				<div class="balance-box">
                				<span>현재 보유 포인트 : </span>
                				<span class="amount"> <?php echo number_format($user_point); ?>P</span>
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
                    				<input type="radio" name="pay_method" id="side_m1" value="카드" checked>
                    				<label for="side_m1" class="m-label">신용카드</label>
                			</div>
                			<div class="m-option">
                    				<input type="radio" name="pay_method" id="side_m2" value="가상계좌">
                    				<label for="side_m2" class="m-label">가상계좌</label>
                			</div>
                			<div class="m-option">
                    				<input type="radio" name="pay_method" id="side_m3" value="휴대폰">
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
	    </div>
    </div>
</main>

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

    // 💡 포인트 충전소 "안전하게 결제하기" 이벤트 및 토스페이먼츠 연동 함수
    document.getElementById('sidePointChargeForm').addEventListener('submit', function(e) {
        e.preventDefault(); // 폼 본래의 서브밋 이동 차단
        payMent();          // 토스 결제 함수 호출
    });

    function payMent() {
        const emailElem = document.getElementById('email');
        const user_id = emailElem.dataset.email; 

        // 💡 라디오 버튼에서 사용자가 실시간 선택한 금액과 수단 동적 추출
        const selectedPrice = parseInt(document.querySelector('input[name="point_amount"]:checked').getAttribute('data-price'));
        const selectedPriceA = 1000;
        const selectedMethod = document.querySelector('input[name="pay_method"]:checked').value;

        const clientKey = "test_ck_LkKEypNArWWQKLKPGbRjrlmeaxYG";
        const tossPayments = TossPayments(clientKey);
        const orderId = "order_" + new Date().getTime();
        const backendUrl = "http://www.highteck.kr/nasagung";

        // 사용자가 체크한 조건에 맞추어 토스페이먼츠창 오픈
        tossPayments.requestPayment(selectedMethod, {
            amount: selectedPriceA,
            orderId: orderId,
            orderName: "포인트 충전",
            successUrl: `${backendUrl}/success.php?user_id=${user_id}&orderId=${orderId}`,
            failUrl: `${backendUrl}/fail.html`
        });
    }
</script>

</body>
</html>