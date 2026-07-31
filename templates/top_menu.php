 <div class="header-container">
        <a href="/nasagung/"><div class="logo">나사궁(나의 사주가 궁금해)</div></a>
        <nav>
            <ul class="main-menu">
                <li class="dropdown">
                    <a href="#">사주팔자</a>
                    <ul class="submenu">
                        <li><a href="#">평생 사주</a></li>
                        <li><a href="#">신년 운세</a></li>
                        <li><a href="/nasagung/lotto">로또 추천</a></li>
                    </ul>
                </li>
                <li><a href="#">운세</a></li>
                <li><a href="/nasagung/gunghap/">궁합</a></li>
                <li><a href="#">재물운</a></li>
                <li><a href="#">사업운</a></li>
                <li><a href="#">AI 상담</a></li>
                <li><a href="#">커뮤니티</a></li>
            </ul>
        </nav>
        
        <div class="auth-buttons">
            <?php if (isset($_SESSION['user_email'])): ?>
                <span class="user-welcome">
                    <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong> 님 <br>
                    <div class="main-menu">현재 포인트 :<?php echo number_format($_SESSION['currentPoint']); ?> </div>
                </span>
                <a href="/nasagung/mypage"><button class="btn-mypage">My Page</button></a>
                <a href="/nasagung/logout"><button class="btn-logout">로그아웃</button></a>
                <a href="/nasagung/pointAdd"><button class="btn-logout">포인트 충전하기</button></a>
            <?php else: ?>
                <a href="/nasagung/login"><button class="btn-login">로그인</button></a>
                <a href="/nasagung/register"><button class="btn-signup">회원가입</button></a>
            <?php endif; ?>
        </div>
    </div>