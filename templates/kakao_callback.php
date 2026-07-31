<?php
// ... (이전 단계: 액세스 토큰 발급 로직 생략) ...

// 사용자 정보 요청
$profile_url = "https://kapi.kakao.com/v2/user/me";
$header = ["Authorization: Bearer " . $access_token];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $profile_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
$user_data = json_decode($result, true);
curl_close($ch);

// [중요] 이메일 정보 추출 시도
$kakao_id = $user_data['id'];
$nickname = $user_data['properties']['nickname'];
$email    = isset($user_data['kakao_account']['email']) ? $user_data['kakao_account']['email'] : null;

if (!$email) {
    // 사용자가 이메일 제공에 동의하지 않은 경우
    // 방법 A: 다시 동의 창으로 보내기 (추천)
    echo "<script>
        alert('이메일 정보는 필수입니다. 가입을 위해 이메일 제공에 동의해주세요.');
        location.href = 'https://kauth.kakao.com/oauth/authorize?client_id={$rest_api_key}&redirect_uri={$redirect_uri}&response_type=code&scope=account_email';
    </script>";
    exit;
}

// 이메일이 있는 경우 정상 가입/로그인 처리
// DB 조회 후 INSERT 또는 SESSION 생성
echo "환영합니다, {$nickname}({$email})님!";
?>