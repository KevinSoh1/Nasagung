import os

# 소셜 로그인 API 상수 정의
NAVER_CLIENT_ID = 'UF79Ckgq9nduIA5W44JW'
NAVER_CLIENT_SECRET = os.getenv('NAVER_CLIENT_SECRET', 'KMrCXfSWK0')
NAVER_REDIRECT_URI = 'https://nasagung.onrender.com/callback?type=naver'

KAKAO_CLIENT_ID = '3df972d36e9c33e81dc306fa0829de88'
KAKAO_CLIENT_SECRET = 'OSyIzzbnv0QK5OxoTDRUlNTt62Cu2Bab'
KAKAO_REDIRECT_URI = 'https://nasagung.onrender.com/callback?type=kakao'

# OpenAI 클라이언트 초기화 (Render 환경 변수 OPENAI_API_KEY 사용)
# OpenAI API 키 로드
OPENAI_API_KEY = os.getenv("OPENAI_API_KEY")

# Render 환경 변수 로드
RAW_HOST = os.getenv("DB_HOST", "nasagung-nasagung.g.aivencloud.com")
DB_PORT = int(os.getenv("DB_PORT", 24465))
DB_USER = os.getenv("DB_USER", "avnadmin")
DB_PASS = os.getenv("DB_PASS", "AVNS_dlJ3IOY5zdNXOk81fy6")
DB_NAME = os.getenv("DB_NAME", "defaultdb")