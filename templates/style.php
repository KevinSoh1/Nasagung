 <style>
        a {
            text-decoration: none;   /* 밑줄 제거 */
            color: --accent-gold;            /* 기본 색상 지정 */
        }
        a:visited { color: --accent-gold; }
        a:hover { color: --accent-gold; }
        a:active { color: --accent-gold; }

        :root {
            --bg-dark: #0a1128;
            --accent-gold: #cfb53b;
            --white: #ffffff;
            --text-muted: #bdc3c7;
            --glass-bg: rgba(255, 255, 255, 0.92);
            --glass-card: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Pretendard', sans-serif; 
            background: var(--bg-dark); 
            color: var(--white); 
            overflow-x: hidden; 
        }

        /* index.html과 상단 메뉴 및 위치 완전 일치 */
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

        nav .main-menu { display: flex; list-style: none; flex-wrap: nowrap; white-space: nowrap; }
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

        /* 로그인 상태에 맞춘 우측 상단 버튼 레이아웃 */
        .auth-buttons { display: flex; align-items: center; gap: 15px; }
        .user-welcome { font-size: 0.9rem; color: var(--text-muted); }
        .user-welcome strong { color: var(--accent-gold); }
        .btn-logout { background: transparent; border: 1px solid rgba(255,255,255,0.4); color: var(--text-muted); padding: 6px 14px; border-radius: 5px; font-size: 0.85rem; cursor: pointer; transition: 0.3s; }
        .btn-logout:hover { border-color: var(--white); color: var(--white); }
        
        /* 마이페이지 버튼 스타일 */
        .btn-mypage { background: transparent; border: 1px solid var(--accent-gold); color: var(--accent-gold); padding: 6px 14px; border-radius: 5px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: 0.3s; }
        .btn-mypage:hover { background: var(--accent-gold); color: #000; }



        /* index.html과 100% 동기화된 배경 화면 시스템 */
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

        .hero-content { position: relative; z-index: 1; max-width: 1200px; width: 100%; margin: 0 auto; padding: 40px 20px; }

        /* 타이틀 컴포넌트 규격 동기화 */
        .intro-text { text-align: center; margin-bottom: 40px; }
        .intro-text h1 { font-family: 'Noto Serif KR', serif; font-size: 3.2rem; line-height: 1.3; margin-bottom: 15px; text-shadow: 2px 2px 10px rgba(0,0,0,0.8); }
        .intro-text .highlight { color: var(--accent-gold); }
        
        /* 마이페이지 메인 콘텐츠 레이아웃 구성 */
        .mypage-layout { display: flex; gap: 40px; align-items: flex-start; }
        
        /* 왼쪽: 내 프로필 상세 정보 카드 (반투명 백색 형태 일치) */
        .profile-section { flex: 1; background: var(--glass-bg); padding: 40px; border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); color: #333; text-align: center; }
        .profile-avatar-wrapper { width: 100%; height: auto; margin: 0 auto 20px; border-radius: 0; background: transparent; justify-content: center; }
        .profile-avatar { width: 100%; height: auto; object-fit: cover; }
        .profile-section h2 { font-family: 'Noto Serif KR', serif; font-size: 1.8rem; margin-bottom: 5px; color: var(--bg-dark); font-weight: 700; }
        .zodiac-tag { display: inline-block; background: var(--bg-dark); color: var(--accent-gold); padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; margin-bottom: 25px; }
        
        .info-list { text-align: left; background: rgba(0,0,0,0.03); border-radius: 10px; padding: 20px; margin-bottom: 25px; }
        .info-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 0.95rem; }
        .info-item:last-child { border-bottom: none; }
        .info-label { color: #666; font-weight: 500; }
        .info-value { color: #111; font-weight: 600; }
        
        .btn-edit-profile { background: var(--bg-dark); color: var(--white); border: none; padding: 12px; border-radius: 8px; font-size: 1rem; font-weight: 600; width: 100%; cursor: pointer; transition: 0.3s; }
        .btn-edit-profile:hover { background: #1a264e; transform: translateY(-2px); color: var(--accent-gold); }

        /* 오른쪽: 대시보드 및 서비스 이력 섹션 (반투명 다크 유리 형태 일치) */
        .dashboard-section { flex: 1.5; display: flex; flex-direction: column; gap: 25px; }
        
        .history-card { background: var(--glass-card); border: 1px solid var(--glass-border); padding: 30px; border-radius: 15px; backdrop-filter: blur(5px); }
        .history-card h3 { font-family: 'Noto Serif KR', serif; font-size: 1.3rem; color: var(--accent-gold); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        
        .history-list { display: flex; flex-direction: column; gap: 15px; }
        .history-item { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 18px; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; transition: 0.3s; }
        .history-item:hover { border-color: var(--accent-gold); background: rgba(255,255,255,0.05); }
        .history-info strong { display: block; font-size: 1rem; color: var(--white); margin-bottom: 3px; }
        .history-info span { font-size: 0.8rem; color: var(--text-muted); }
        .btn-view-result { background: transparent; border: 1px solid var(--accent-gold); color: var(--accent-gold); padding: 6px 14px; border-radius: 5px; font-size: 0.85rem; font-weight: 600; }
        .btn-view-result:hover { background: var(--accent-gold); color: #000; }

        /* 내 사주 원국을 보여주는 명조 테두리 상자 */
        .saju-box-mini { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 15px; }
        .saju-pillar { background: rgba(207, 181, 59, 0.1); border: 1px solid rgba(207, 181, 59, 0.3); text-align: center; padding: 10px; border-radius: 6px; }
        .saju-pillar-title { font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px; }
        .saju-char-stem { font-size: 1.2rem; font-weight: 700; color: var(--white); }
        .saju-char-branch { font-size: 1.2rem; font-weight: 700; color: var(--accent-gold); }

        @media (max-width: 992px) {
            .mypage-layout { flex-direction: column; }
            .hero { height: auto; padding: 120px 0 60px; }
            .intro-text h1 { font-size: 2.2rem; }
        }
</style>

<script>
    	function gotoResult() {
    		window.location.href = "result_year.php";	
    	}
    	
    	function gotoToday() {
    		window.location.href = "result_today.php";	
    	}
    	
    	function gotoRealtime() {
    		window.location.href = "result_realtime.php";	
    	}

</script>