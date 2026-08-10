import os
import re
import time
import uuid
import hashlib
import logging
import pymysql
import urllib.parse
import requests
from typing import Optional
from fastapi import FastAPI, HTTPException, Request, Form, Depends,UploadFile,File
from fastapi.responses import HTMLResponse, RedirectResponse
from fastapi.middleware.cors import CORSMiddleware
from fastapi.templating import Jinja2Templates
from pydantic import BaseModel
from openai import OpenAI
from fastapi.staticfiles import StaticFiles  # Static Files 임포트
from fastapi import Cookie  # Cookie 패키지 추가

# 로깅 설정
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# FastAPI 생성
app = FastAPI()

# nasagung.py와 같은 위치에 있는 templates 폴더 지정
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
templates_dir = os.path.join(BASE_DIR, "templates")
templates = Jinja2Templates(directory=templates_dir)
templates.env.charset = "utf-8" # 템플릿 인코딩 강제 고정

# /static 경로를 static 폴더와 매핑 (이미지, CSS, JS 파일 제공용)
static_dir = os.path.join(BASE_DIR, "static")
if not os.path.exists(static_dir):
    os.makedirs(static_dir) # static 폴더가 없으면 자동 생성

app.mount("/static", StaticFiles(directory=static_dir), name="static")

# CORS 에러 방지 설정
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# 소셜 로그인 API 상수 정의
NAVER_CLIENT_ID = 'UF79Ckgq9nduIA5W44JW'
NAVER_CLIENT_SECRET = os.getenv('NAVER_CLIENT_SECRET', 'KMrCXfSWK0')  # 네이버 개발자 센터 Secret 값 입력
NAVER_REDIRECT_URI = 'https://nasagung.onrender.com/callback?type=naver'

KAKAO_CLIENT_ID = '3df972d36e9c33e81dc306fa0829de88'
KAKAO_CLIENT_SECRET = 'OSyIzzbnv0QK5OxoTDRUlNTt62Cu2Bab'
KAKAO_REDIRECT_URI = 'https://nasagung.onrender.com/callback?type=kakao'


# Render 환경 변수 로드
RAW_HOST = os.getenv("DB_HOST", "nasagung-nasagung.g.aivencloud.com")
# 💡 만약 DB_HOST에 mysql:// URI 전체가 들어온 경우 순수 호스트만 추출하는 안전장치
if RAW_HOST.startswith("mysql://") or RAW_HOST.startswith("postgres://"):
    parsed = urllib.parse.urlparse(RAW_HOST)
    DB_HOST = parsed.hostname
else:
    DB_HOST = RAW_HOST

DB_PORT = int(os.getenv("DB_PORT", 24465))
DB_USER = os.getenv("DB_USER", "avnadmin")
DB_PASS = os.getenv("DB_PASS", "AVNS_dlJ3IOY5zdNXOk81fy6")
DB_NAME = os.getenv("DB_NAME", "defaultdb")


# DB 연결 생성 함수
def get_db():
    timeout = 10
    return pymysql.connect(
        host=DB_HOST,
        port=DB_PORT,
        user=DB_USER,
        password=DB_PASS,
        database=DB_NAME,
        charset="utf8mb4",
       cursorclass=pymysql.cursors.DictCursor,
        ssl={"ssl": {}}  # Aiven SSL 대응
  )


@app.get("/", response_class=HTMLResponse)
async def read_root(
    request: Request,
    user_email: Optional[str] = Cookie(None),
    db=Depends(get_db),
):
  current_user = get_current_user(user_email, db)

  for target_file in ["index.html"]:
    file_path = os.path.join(templates_dir, target_file)
    if os.path.exists(file_path):
      # user 객체를 템플릿 context로 함께 전달
      return templates.TemplateResponse(
          request, target_file, {"user": current_user}
      )

  return HTMLResponse(
      content=(
          "<h1>Error: templates 폴더 내에서 index.html 문서를 찾을 수"
          " 없습니다.</h1>"
      ),
      status_code=404,
  )

# DB 연결 테스트 전용 엔드포인트
@app.get("/db-test")
async def test_db():
    conn = None
    try:
        conn = get_db()
        with conn.cursor() as cursor:
            # 1. AS ver 구문으로 결과 컬럼명을 'ver'로 명확히 지정
            cursor.execute("SELECT VERSION() AS ver;")
            result = cursor.fetchone()
            
        # 2. 안전하게 버전 정보 추출
        if result and "ver" in result:
            db_version = result["ver"]
        else:
            db_version = "알 수 없음"

        return {
            "status": "success", 
            "message": f"Aiven DB 연결 성공! (MySQL 버젼: {db_version})"
        }
        
    except Exception as e:
        return {
            "status": "error", 
            "message": f"DB 연결 실패: {str(e)}"
        }
        
    finally:
        # 3. DB 커넥션 자원 반납
        if conn:
            conn.close()

# 업로드 이미지 저장 폴더 지정 (static/uploads)
UPLOAD_DIR = os.path.join(BASE_DIR, "static", "uploads")
if not os.path.exists(UPLOAD_DIR):
    os.makedirs(UPLOAD_DIR)
    
# Pydantic 모델 정의
class SajuRequest(BaseModel):
    name: str
    gender: str
    birthdate: str
    birthtime: str
    calendarType: str
    productID: str = "mBan"
    fiveElements: str = ""
    
    # 궁합용 상대방 데이터 필드
    partnerName: Optional[str] = ""
    partnerGender: Optional[str] = ""
    partnerBirthdate: Optional[str] = ""
    partnerBirthtime: Optional[str] = ""
    partnerCalendarType: Optional[str] = ""

# 로그인 유저 정보 조회 함수 (helper)
def get_current_user(user_email: str, db):
  if not user_email:
    return None
  with db.cursor() as cursor:
    sql = "SELECT email, name, current_point FROM nasagung_users WHERE email = %s"
    cursor.execute(sql, (user_email,))
    return cursor.fetchone()

# ==========================================
# 1. 로그인 페이지 화면 띄우기 (GET)
# ==========================================
@app.get("/login", response_class=HTMLResponse)
async def login_page(request: Request):
    return templates.TemplateResponse(request, "login.html")
# ==========================================
# 2. 로그인 폼 제출 처리 (POST)
# ==========================================
@app.post("/login", response_class=HTMLResponse)
async def login_submit(
    request: Request,
    email: str = Form(...),       # login.html의 name="email" 값을 받아옴
    password: str = Form(...),    # login.html의 name="password" 값을 받아옴
    db=Depends(get_db)
):
    try:
        # 일반 로그인 비밀번호 해시 처리
        hashed_pw = hashlib.md5(password.encode('utf-8')).hexdigest()
        
        with db.cursor() as cursor:
            sql = "SELECT * FROM nasagung_users WHERE email = %s AND provider = 'local'"
            cursor.execute(sql, (email,))
            user = cursor.fetchone()

        # 💡 2. 유저가 없거나 비밀번호가 틀린 경우
        # (단순 비교 예시, 실제 서비스 운영 시 bcrypt 등 암호화 해시 비교 권장)
        if not user or user["password"] != hashed_pw:
            return templates.TemplateResponse(
                request,
                "login.html",
                {"error": "이메일 또는 비밀번호가 올바르지 않습니다."}
            )

        # 💡 3. 로그인 성공 시 메인 페이지로 이동 및 로그인 쿠키 설정
        response = RedirectResponse(url="/", status_code=303)
        # 브라우저 쿠키에 user_id 저장 (HTTP-Only 보안 설정)
        response.set_cookie(key="user_email", value=user["email"], httponly=True)
        return response

    except Exception as e:
        return templates.TemplateResponse(
            request,
            "login.html",
            {"error": f"로그인 처리 중 오류 발생: {str(e)}"}
        )

# ==========================================
# 2-1. 소셜 로그인 콜백 처리 (네이버 / 카카오)
# ==========================================
@app.get("/callback")
async def social_callback(
    request: Request,
    type: str = "",
    code: str = "",
    state: Optional[str] = "",
    db=Depends(get_db)
):
    if not code or not type:
        return templates.TemplateResponse(request, "login.html", {"error": "소셜 인증 정보가 유효하지 않습니다."})

    email = None
    name = "사용자"
    gender = ""
    birthyear = None
    birthday = ""
    phone = ""
    social_id = ""

    try:
        # ------------------------------------
        # 네이버 소셜 로그인
        # ------------------------------------
        if type == "naver":
            token_url = f"https://nid.naver.com/oauth2.0/token?grant_type=authorization_code&client_id={NAVER_CLIENT_ID}&client_secret={NAVER_CLIENT_SECRET}&code={code}&state={state}"
            token_res = requests.get(token_url).json()
            access_token = token_res.get("access_token")

            if not access_token:
                return templates.TemplateResponse(request, "login.html", {"error": "네이버 토큰 발급에 실패했습니다."})

            profile_res = requests.get(
                "https://openapi.naver.com/v1/nid/me",
                headers={"Authorization": f"Bearer {access_token}"}
            ).json()

            if profile_res.get("resultcode") == "00":
                u = profile_res.get("response", {})
                social_id = u.get("id", "")
                email = u.get("email")
                name = u.get("name") or u.get("nickname", "네이버 사용자")
                
                # 성별 (M -> male, F -> female)
                g_raw = u.get("gender", "")
                if g_raw == "M": gender = "male"
                elif g_raw == "F": gender = "female"
                
                # 출생연도 & 생일 (MM-DD)
                birthyear = int(u.get("birthyear")) if u.get("birthyear") else None
                birthday = u.get("birthday", "")
                
                # 전화번호
                phone = u.get("mobile", "")
            
        # ------------------------------------
        # 카카오 소셜 로그인
        # ------------------------------------
        elif type == "kakao":
            token_url = "https://kauth.kakao.com/oauth/token"
            headers = {"Content-Type": "application/x-www-form-urlencoded;charset=utf-8"}
            body = {
                "grant_type": "authorization_code",
                "client_id": KAKAO_CLIENT_ID,
                "client_secret": KAKAO_CLIENT_SECRET,
                "redirect_uri": KAKAO_REDIRECT_URI,
                "code": code
            }
            token_res = requests.post(token_url, headers=headers, data=body).json()
            access_token = token_res.get("access_token")

            if not access_token:
                return templates.TemplateResponse(request, "login.html", {"error": "카카오 토큰 발급에 실패했습니다."})

            profile_res = requests.get(
                "https://kapi.kakao.com/v2/user/me",
                headers={"Authorization": f"Bearer {access_token}"}
            ).json()

            social_id = str(profile_res.get("id", ""))
            kakao_account = profile_res.get("kakao_account", {})
            profile_info = kakao_account.get("profile", {})

            email = kakao_account.get("email")
            # 이름 우선 사용, 없으면 닉네임 사용
            name = kakao_account.get("name") or profile_info.get("nickname", "카카오 사용자")

            # 성별 (male / female)
            gender = kakao_account.get("gender", "")

            # 출생연도
            by_raw = kakao_account.get("birthyear")
            birthyear = int(by_raw) if by_raw else None

            # 생일 (MMDD -> MM-DD 포맷팅)
            bd_raw = kakao_account.get("birthday", "")
            if len(bd_raw) == 4:
                birthday = f"{bd_raw[:2]}-{bd_raw[2:]}"
            else:
                birthday = bd_raw

            # 전화번호 (+82 10-1234-5678 -> 010-1234-5678 포맷팅)
            ph_raw = kakao_account.get("phone_number", "")
            if ph_raw:
                phone = ph_raw.replace("+82 ", "0").replace("+82-", "0")
                
            kakao_account = profile_res.get("kakao_account", {})
            email = kakao_account.get("email")
            name = kakao_account.get("profile", {}).get("nickname", "카카오 사용자")

        if not email:
            return templates.TemplateResponse(request, "login.html", {"error": "소셜 계정에서 이메일 정보를 받아올 수 없습니다."})

        # ------------------------------------
        # DB 조회 및 미가입 시 자동 가입 (INSERT)
        # ------------------------------------
        with db.cursor() as cursor:
            cursor.execute("SELECT * FROM nasagung_users WHERE email = %s", (email,))
            user = cursor.fetchone()

            if not user:
                profile_img = "rat.png"
                if birthyear:
                    zodiac_icons = {
                        0: "monkey.png", 1: "rooster.png", 2: "dog.png", 3: "pig.png",
                        4: "rat.png", 5: "ox.png", 6: "tiger.png", 7: "rabbit.png",
                        8: "dragon.png", 9: "snake.png", 10: "horse.png", 11: "sheep.png"
                    }
                    profile_img = zodiac_icons[birthyear % 12]
                    
               # 신규 사용자 자동 가입 (SNS 정보 포함)
                insert_sql = """
                    INSERT INTO nasagung_users 
                    (email, password, name, gender, birthyear, birthday, phone, provider, sns_id, profile_img) 
                    VALUES (%s, '', %s, %s, %s, %s, %s, %s, %s, %s)
                """
                cursor.execute(insert_sql, (
                    email, name, gender, birthyear, birthday, phone, type, social_id, profile_img
                ))
                db.commit()

        # 로그인 처리 (쿠키 생성 후 메인 이동)
        response = RedirectResponse(url="/", status_code=303)
        response.set_cookie(key="user_email", value=email, httponly=True)
        return response

    except Exception as e:
        logger.error(f"Social login callback error: {str(e)}")
        return templates.TemplateResponse(request, "login.html", {"error": f"소셜 로그인 오류 발생: {str(e)}"})

# ==========================================
# 3. 로그아웃 처리 (POST/GET)
# ==========================================
@app.get("/logout")
async def logout():
    response = RedirectResponse(url="/", status_code=303)
    response.delete_cookie(key="user_email") # 쿠키 삭제
    return response

# ==========================================
# 4. 회원가입 페이지 화면 띄우기 (GET)
# ==========================================
@app.get("/register", response_class=HTMLResponse)
async def register_page(request: Request):
    return templates.TemplateResponse(request, "register.html")

# ==========================================
# 5. 회원가입 폼 제출 처리 (POST)
# ==========================================
@app.post("/register", response_class=HTMLResponse)
async def register_submit(
    request: Request,
    email: str = Form(...),
    password: str = Form(...),
    name: str = Form(...),
    gender: str = Form(...),
    birthyear: int = Form(...),
    birthday: str = Form(...),
    birthtime: str = Form(...),
    phone: str = Form(...),
    profile_img: UploadFile = File(None), # 업로드 파일 (선택)
    db=Depends(get_db)
):
    # --- 비밀번호 유효성 검사 (영문, 숫자, 특수문자 포함 5자 이상) ---
    pw_pattern = r'^(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[!@#$%^&*()_+]).{5,}$'
    if not re.match(pw_pattern, password):
        return templates.TemplateResponse(
            request, "register.html", 
            {"error": "비밀번호는 영문, 숫자, 특수문자 포함 5자 이상이어야 합니다."}
        )

    # --- 프로필 사진 처리 ---
    saved_filename = ""
    
    # 1. 사용자가 파일 업로드 시 파일 저장
    if profile_img and profile_img.filename:
        file_ext = os.path.splitext(profile_img.filename)[1]
        saved_filename = f"{int(time.time())}_{uuid.uuid4().hex[:8]}{file_ext}"
        file_path = os.path.join(UPLOAD_DIR, saved_filename)
        
        contents = await profile_img.read()
        with open(file_path, "wb") as f:
            f.write(contents)
            
    # 2. 미업로드 시 출생연도 기준 12지신 자동 할당
    else:
        zodiac_icons = {
            0: "monkey.png",  1: "rooster.png", 2: "dog.png",    3: "pig.png",
            4: "rat.png",      5: "ox.png",       6: "tiger.png",  7: "rabbit.png",
            8: "dragon.png",   9: "snake.png",   10: "horse.png", 11: "sheep.png"
        }
        remainder = birthyear % 12
        saved_filename = zodiac_icons[remainder]

    # --- 비밀번호 해싱 (MD5) ---
    hashed_pw = hashlib.md5(password.encode('utf-8')).hexdigest()

    try:
        with db.cursor() as cursor:
            # 1. 중복 이메일 체크
            check_sql = "SELECT id FROM nasagung_users WHERE email=%s AND provider='local'"
            cursor.execute(check_sql, (email,))
            if cursor.fetchone():
                return templates.TemplateResponse(
                    request, "register.html", 
                    {"error": "이미 등록된 이메일입니다."}
                )

            # 2. DB 저장
            insert_sql = """
                INSERT INTO nasagung_users 
                (email, password, name, gender, birthyear, birthday, birthtime, phone, profile_img, provider) 
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, 'local')
            """
            cursor.execute(insert_sql, (
                email, hashed_pw, name, gender, birthyear, birthday, birthtime, phone, saved_filename
            ))
            db.commit() # 변경사항 DB 반영

        return templates.TemplateResponse(
            request, "register.html", 
            {"success": True}
        )

    except Exception as e:
        return templates.TemplateResponse(
            request, "register.html", 
            {"error": f"가입 중 오류가 발생했습니다: {str(e)}"}
        )

# ==========================================
# Mypage 처리 부분
# ==========================================
@app.get("/mypage", response_class=HTMLResponse)
async def mypage(request: Request, db=Depends(get_db)):
    # 1. 로그인 여부 체크 (쿠키에서 user_email 확인)
    user_email = request.cookies.get("user_email")
    if not user_email:
        # 미로그인 시 알림 팝업 후 로그인 페이지로 이동
        return HTMLResponse(
            content="<script>alert('로그인이 필요한 서비스입니다.'); location.href='/login';</script>"
        )

    # 2. DB에서 사용자 정보 조회
    with db.cursor() as cursor:
        sql = "SELECT * FROM nasagung_users WHERE email = %s"
        cursor.execute(sql, (user_email,))
        user = cursor.fetchone()

    if not user:
        return HTMLResponse(
            content="<script>alert('사용자 정보를 찾을 수 없습니다.'); location.href='/login';</script>"
        )

    # 3. 이미지 경로 및 12지신 자동 할당 로직
    current_img = user.get("profile_img") or ""
    img_display_path = ""

    if not current_img or current_img == "default_profile.png":
        birthyear = int(user.get("birthyear", 0))
        if birthyear > 0:
            zodiac_icons = {
                0: "monkey.png",  1: "rooster.png", 2: "dog.png",    3: "pig.png",
                4: "rat.png",      5: "ox.png",       6: "tiger.png",  7: "rabbit.png",
                8: "dragon.png",   9: "snake.png",   10: "horse.png", 11: "sheep.png"
            }
            remainder = birthyear % 12
            img_display_path = f"../static/images/{zodiac_icons[remainder]}"
        else:
            img_display_path = "../static/images/default_profile.png"
    else:
        # 업로드된 파일(_)인지 static/images의 띠 파일인지 구별
        if "_" in current_img:
            img_display_path = f"../static/uploads/{current_img}"
        else:
            img_display_path = f"../static/images/{current_img}"

    # 💡 TemplateResponse 전달 (request 필수)
    return templates.TemplateResponse(
        request=request,
        name="mypage.html",
        context={
            "user": user,
            "img_display_path": img_display_path  # HTML의 src="{{ img_display_path }}" 로 전달됨
        }
    )

    # 4. Jinja2 템플릿 반환
    return templates.TemplateResponse(
        request=request,
        name="mypage.html",
        context={
            "user": user,
            "img_display_path": img_display_path
        }
    )

@app.post("/nasagung/analyze")
async def get_saju_analysis(request: SajuRequest):
    try:
        # OpenAI API Key 검증
        api_key = os.getenv("OPENAI_API_KEY")
        if not api_key:
            logger.error("OPENAI_API_KEY가 환경 변수에 설정되지 않았습니다.")
            raise HTTPException(status_code=500, detail="서버 환경 변수(OPENAI_API_KEY)가 설정되지 않았습니다.")

        # OpenAI 클라이언트 요청 시점 생성 (안전성 확보)
        client = OpenAI(api_key=api_key)

        # 데이터 추출
        name = request.name
        gender = request.gender
        birthdate = request.birthdate
        birthtime = request.birthtime
        calendar_type = request.calendarType
        product_id = request.productID
        five_elements = request.fiveElements
        
        partner_name = request.partnerName
        partner_gender = request.partnerGender
        partner_birthdate = request.partnerBirthdate
        partner_birthtime = request.partnerBirthtime
        partner_calendar_type = request.partnerCalendarType
        
        # productID별 프롬프트 분기
        if product_id == "toDay":
            service_title = "오늘의 운세"
            system_role = "당신은 전통 사주 명리학자입니다."
            specific_requirement = "오늘의 운세와 하루의 흐름에 대해 상세히 설명해 주세요."
            
            prompt_content = f"당신은 정교하고 신뢰감 있는 전문 명리학자입니다.\n아래의 사용자 정보를 바탕으로 깊이 있는 사주/운세 풀이를 제공해 주세요.\n\n[사용자 정보]\n- 이름: {name}\n- 성별: {gender}\n- 생년월일: {birthdate} ({calendar_type})\n- 출생시간: {birthtime}\n\n[답변 요구사항]\n1. 정중하고 부드러운 어조(한글)로 작성해 주세요.\n2. {specific_requirement}\n3. 가독성이 좋게 단락을 나누고 markdown 서식을 활용하여 친절하게 설명해 주세요."
            
        elif product_id == "lotto":
            service_title = "로또 번호 예측"
            system_role = "당신은 타고난 사주 오행과 천기의 흐름을 바탕으로 행운의 숫자를 산출하는 전문 숫자 분석가입니다."
            
            prompt_content = (
                "아래 사용자 정보를 분석하여 오직 '숫자'와 '쉼표', '줄바꿈' 기호만 사용하여 답변을 작성하세요. "
                "절대로 인사말, 사주 풀이 설명, 마크다운(###, **, -) 등의 일반 텍스트를 포함해서는 안 됩니다.\n\n"
                f"[사용자 정보]\n- 이름: {name}\n- 성별: {gender}\n- 생년월일: {birthdate} ({calendar_type})\n- 출생시간: {birthtime}\n- 집중오행기운: {five_elements}\n\n"
                "[출력 형식 및 제한 요구사항]\n1. 사용자의 사주 음양오행과 집중 기운을 참고하여 1부터 45 사이의 무작위 로또 번호 6개를 한 줄에 출력하세요.\n"
                "2. 총 5줄(5게임, 총 30개 숫자)을 엔터(줄바꿈)로 구분하여 출력하세요.\n"
                "3. 각 줄의 숫자는 쉼표(,)로만 구분되어야 합니다.\n"
                "4. ★중요: 각 줄의 숫자 6개는 절대로 작은 수부터 정렬(1, 2, 3...)하지 말고, 무작위로 추출된 천기의 순서 그대로 뒤섞어 출력해야 합니다.\n\n"
                "[올바른 출력 예시]\n42,7,19,3,32,11\n14,28,5,44,22,1\n33,9,18,25,41,12\n2,21,39,17,30,8\n45,13,6,24,35,16"
            )
       
        elif product_id == "gunghap":
            service_title = "전통 궁합 분석"
            system_role = "당신은 두 사람의 사주와 오행의 조화를 분석하여 인연의 깊이를 풀어주는 전통 명리 궁합 전문가입니다."
            
            prompt_content = (
                "당신은 정교하고 신뢰감 있는 전문 궁합 학자입니다.\n아래 두 사람의 사주 정보를 바탕으로 성격 조화, 애정운, 주의해야 할 점 등을 종합적으로 분석한 깊이 있는 궁합 풀이를 제공해 주세요.\n\n"
                f"[본인 정보]\n- 이름: {name}\n- 성별: {gender}\n- 생년월일: {birthdate} ({calendar_type})\n- 출생시간: {birthtime}\n\n"
                f"[상대방 정보]\n- 이름: {partner_name}\n- 성별: {partner_gender}\n- 생년월일: {partner_birthdate} ({partner_calendar_type})\n- 출생시간: {partner_birthtime}\n\n"
                "[답변 요구사항]\n1. 정중하고 부드러운 어조(한글)로 작성해 주세요.\n2. 두 사람의 오행 조화, 성향 차이, 그리고 함께하면 좋은 발전적인 방향을 상세히 설명해 주세요.\n3. 가독성이 좋게 단락을 나누고 markdown 서식을 활용하여 친절하게 설명해 주세요."
            )

        else:
            service_title = "명반 분석"
            system_role = "당신은 전통 자미두수 및 명반 분석 전문가입니다."
            specific_requirement = "전체적인 타고난 운명의 흐름과 성향을 분석해 주세요."
            
            prompt_content = f"당신은 정교하고 신뢰감 있는 전문 명리학자입니다.\n아래의 사용자 정보를 바탕으로 깊이 있는 사주/운세 풀이를 제공해 주세요.\n\n[사용자 정보]\n- 이름: {name}\n- 성별: {gender}\n- 생년월일: {birthdate} ({calendar_type})\n- 출생시간: {birthtime}\n\n[답변 요구사항]\n1. 정중하고 부드러운 어조(한글)로 작성해 주세요.\n2. {specific_requirement}\n3. 가독성이 좋게 단락을 나누고 markdown 서식을 활용하여 친절하게 설명해 주세요."

        logger.info(f"[요청] 이름: {name} / 상품: {product_id} -> {service_title} 진행")
        
        # OpenAI API 호출
        response = client.chat.completions.create(
            model="gpt-4o-mini",
            messages=[
                {"role": "system", "content": system_role},
                {"role": "user", "content": prompt_content}
            ]
        )
        
        analysis_result = response.choices[0].message.content
        
        return {"result": analysis_result}

    except Exception as e:
        logger.error(f"[오류 발생]: {str(e)}")
        raise HTTPException(status_code=500, detail=f"서버 내부 처리 중 오류 발생: {str(e)}")
