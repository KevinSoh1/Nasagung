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
from fastapi import FastAPI, HTTPException, Request, Form, Depends, UploadFile, File, Cookie
from fastapi.staticfiles import StaticFiles
from fastapi.responses import HTMLResponse, RedirectResponse
from fastapi.middleware.cors import CORSMiddleware
from fastapi.templating import Jinja2Templates
from pydantic import BaseModel
from openai import OpenAI

# 로깅 설정
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# FastAPI 생성
app = FastAPI()

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

# 1. templates 폴더 설정
templates_dir = os.path.join(BASE_DIR, "templates")
os.makedirs(templates_dir, exist_ok=True)

templates = Jinja2Templates(directory=templates_dir)
templates.env.policies.setdefault("json.dumps_kwargs", {})

# 2. static 및 하위 폴더 설정
static_dir = os.path.join(BASE_DIR, "static")
UPLOAD_DIR = os.path.join(static_dir, "uploads")
images_dir = os.path.join(static_dir, "images")

for folder in [static_dir, UPLOAD_DIR, images_dir]:
    os.makedirs(folder, exist_ok=True)

# static 디렉토리 매핑 설정
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
NAVER_CLIENT_SECRET = os.getenv('NAVER_CLIENT_SECRET', 'KMrCXfSWK0')
NAVER_REDIRECT_URI = 'https://nasagung.onrender.com/callback?type=naver'

KAKAO_CLIENT_ID = '3df972d36e9c33e81dc306fa0829de88'
KAKAO_CLIENT_SECRET = 'OSyIzzbnv0QK5OxoTDRUlNTt62Cu2Bab'
KAKAO_REDIRECT_URI = 'https://nasagung.onrender.com/callback?type=kakao'

# OpenAI 클라이언트 초기화 (Render 환경 변수 OPENAI_API_KEY 사용)
# client = OpenAI(api_key=os.getenv("OPENAI_API_KEY"))
# OpenAI API 키 로드
OPENAI_API_KEY = os.getenv("OPENAI_API_KEY")

# API 키가 제대로 설정되었는지 로그로 확인 (보안을 위해 일부만 출력)
if not OPENAI_API_KEY:
    logger.error("? OPENAI_API_KEY가 설정되지 않았습니다. Environment 변수를 확인하세요.")
else:
    logger.info(f"? OPENAI_API_KEY 로드 완료 (Prefix: {OPENAI_API_KEY[:7]}...)")

# OpenAI 클라이언트 생성
client = OpenAI(api_key=OPENAI_API_KEY)

# Render 환경 변수 로드
RAW_HOST = os.getenv("DB_HOST", "nasagung-nasagung.g.aivencloud.com")
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


# 로그인 유저 정보 조회 함수 (helper)
def get_current_user(user_email: str, db):
    if not user_email:
        return None
    with db.cursor() as cursor:
        sql = "SELECT email, name, gender, birthyear, birthday, birthtime, calendarType, current_point FROM nasagung_users WHERE email = %s"
        cursor.execute(sql, (user_email,))
        return cursor.fetchone()


# Pydantic 모델 정의
class SajuRequest(BaseModel):
    name: str
    gender: str
    birthdate: str
    birthtime: str
    calendarType: str
    productID: str = "mBan"
    fiveElements: str = ""
    
    partnerName: Optional[str] = ""
    partnerGender: Optional[str] = ""
    partnerBirthdate: Optional[str] = ""
    partnerBirthtime: Optional[str] = ""
    partnerCalendarType: Optional[str] = ""


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
            return templates.TemplateResponse(
                request, target_file, {"user": current_user}
            )

    return HTMLResponse(
        content="<h1>Error: templates 폴더 내에서 index.html 문서를 찾을 수 없습니다.</h1>",
        status_code=404,
    )


# DB 연결 테스트 전용 엔드포인트
@app.get("/db-test")
async def test_db():
    conn = None
    try:
        conn = get_db()
        with conn.cursor() as cursor:
            cursor.execute("SELECT VERSION() AS ver;")
            result = cursor.fetchone()
            
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
        if conn:
            conn.close()


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
    email: str = Form(...),
    password: str = Form(...),
    db=Depends(get_db)
):
    try:
        clean_pw = password.strip()
        hashed_pw = hashlib.md5(clean_pw.encode('utf-8')).hexdigest()
        
        with db.cursor() as cursor:
            sql = "SELECT * FROM nasagung_users WHERE email = %s"
            cursor.execute(sql, (email.strip(),))
            user = cursor.fetchone()

        if not user:
            return templates.TemplateResponse(
                request, "login.html", {"error": "존재하지 않는 이메일 계정입니다."}
            )

        if not user.get("password"):
            provider_name = user.get("provider", "소셜").upper()
            return templates.TemplateResponse(
                request, "login.html",
                {"error": f"해당 계정은 {provider_name} 간편 로그인으로 가입된 계정입니다. {provider_name} 버튼을 이용해 주세요."}
            )

        if user["password"] != hashed_pw:
            return templates.TemplateResponse(
                request, "login.html", {"error": "비밀번호가 올바르지 않습니다."}
            )

        response = RedirectResponse(url="/mypage", status_code=303)
        response.set_cookie(
            key="user_email",
            value=user["email"],
            httponly=True,
            samesite="lax",
            max_age=86400
        )
        return response

    except Exception as e:
        return templates.TemplateResponse(
            request, "login.html", {"error": f"로그인 처리 중 오류 발생: {str(e)}"}
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
                
                g_raw = u.get("gender", "")
                if g_raw == "M": gender = "male"
                elif g_raw == "F": gender = "female"
                
                birthyear = int(u.get("birthyear")) if u.get("birthyear") else None
                birthday = u.get("birthday", "")
                phone = u.get("mobile", "")
            
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
            name = kakao_account.get("name") or profile_info.get("nickname", "카카오 사용자")
            gender = kakao_account.get("gender", "")

            by_raw = kakao_account.get("birthyear")
            birthyear = int(by_raw) if by_raw else None

            bd_raw = kakao_account.get("birthday", "")
            if len(bd_raw) == 4:
                birthday = f"{bd_raw[:2]}-{bd_raw[2:]}"
            else:
                birthday = bd_raw

            ph_raw = kakao_account.get("phone_number", "")
            if ph_raw:
                phone = ph_raw.replace("+82 ", "0").replace("+82-", "0")

        if not email:
            return templates.TemplateResponse(request, "login.html", {"error": "소셜 계정에서 이메일 정보를 받아올 수 없습니다."})

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
                    
                insert_sql = """
                    INSERT INTO nasagung_users 
                    (email, password, name, gender, birthyear, birthday, phone, provider, sns_id, profile_img) 
                    VALUES (%s, '', %s, %s, %s, %s, %s, %s, %s, %s)
                """
                cursor.execute(insert_sql, (
                    email, name, gender, birthyear, birthday, phone, type, social_id, profile_img
                ))
                db.commit()

        response = RedirectResponse(url="/", status_code=303)
        response.set_cookie(key="user_email", value=email, httponly=True)
        return response

    except Exception as e:
        logger.error(f"Social login callback error: {str(e)}")
        return templates.TemplateResponse(request, "login.html", {"error": f"소셜 로그인 오류 발생: {str(e)}"})


# ==========================================
# 3. 로그아웃 처리 (GET)
# ==========================================
@app.get("/logout")
async def logout():
    response = RedirectResponse(url="/", status_code=303)
    response.delete_cookie(key="user_email")
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
    profile_img: UploadFile = File(None),
    db=Depends(get_db)
):
    pw_pattern = r'^(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[!@#$%^&*()_+]).{5,}$'
    if not re.match(pw_pattern, password):
        return templates.TemplateResponse(
            request, "register.html", 
            {"error": "비밀번호는 영문, 숫자, 특수문자 포함 5자 이상이어야 합니다."}
        )

    saved_filename = ""
    if profile_img and profile_img.filename:
        file_ext = os.path.splitext(profile_img.filename)[1]
        saved_filename = f"{int(time.time())}_{uuid.uuid4().hex[:8]}{file_ext}"
        file_path = os.path.join(UPLOAD_DIR, saved_filename)
        
        contents = await profile_img.read()
        with open(file_path, "wb") as f:
            f.write(contents)
    else:
        zodiac_icons = {
            0: "monkey.png",  1: "rooster.png", 2: "dog.png",    3: "pig.png",
            4: "rat.png",      5: "ox.png",       6: "tiger.png",  7: "rabbit.png",
            8: "dragon.png",   9: "snake.png",   10: "horse.png", 11: "sheep.png"
        }
        remainder = birthyear % 12
        saved_filename = zodiac_icons[remainder]

    hashed_pw = hashlib.md5(password.encode('utf-8')).hexdigest()

    try:
        with db.cursor() as cursor:
            check_sql = "SELECT id FROM nasagung_users WHERE email=%s AND provider='local'"
            cursor.execute(check_sql, (email,))
            if cursor.fetchone():
                return templates.TemplateResponse(
                    request, "register.html", 
                    {"error": "이미 등록된 이메일입니다."}
                )

            insert_sql = """
                INSERT INTO nasagung_users 
                (email, password, name, gender, birthyear, birthday, birthtime, phone, profile_img, provider) 
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, 'local')
            """
            cursor.execute(insert_sql, (
                email, hashed_pw, name, gender, birthyear, birthday, birthtime, phone, saved_filename
            ))
            db.commit()

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
    user_email = request.cookies.get("user_email")
    if not user_email:
        return HTMLResponse(
            content="<script>alert('로그인이 필요한 서비스입니다.'); location.href='/login';</script>"
        )

    with db.cursor() as cursor:
        sql = "SELECT * FROM nasagung_users WHERE email = %s"
        cursor.execute(sql, (user_email,))
        user = cursor.fetchone()

    if not user:
        return HTMLResponse(
            content="<script>alert('사용자 정보를 찾을 수 없습니다.'); location.href='/login';</script>"
        )

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
            img_display_path = f"/static/images/{zodiac_icons[remainder]}"
        else:
            img_display_path = "/static/images/default_profile.png"
    else:
        if "_" in current_img:
            img_display_path = f"/static/uploads/{current_img}"
        else:
            img_display_path = f"/static/images/{current_img}"

    return templates.TemplateResponse(
        request=request,
        name="mypage.html",
        context={
            "user": user,
            "img_display_path": img_display_path
        }
    )


# ==========================================
# MyPage에서 정보 수정
# ==========================================
@app.get("/edit-profile", response_class=HTMLResponse)
async def edit_profile_page(request: Request, db=Depends(get_db)):
    user_email = request.cookies.get("user_email")
    if not user_email:
        return HTMLResponse(
            content="<script>alert('로그인이 필요합니다.'); location.href='/login';</script>"
        )

    with db.cursor() as cursor:
        sql = "SELECT * FROM nasagung_users WHERE email = %s"
        cursor.execute(sql, (user_email,))
        user = cursor.fetchone()

    if not user:
        return HTMLResponse(
            content="<script>alert('사용자 정보를 찾을 수 없습니다.'); location.href='/login';</script>"
        )

    current_img = user.get("profile_img") or ""
    if not current_img or current_img == "default_profile.png":
        birthyear = int(user.get("birthyear", 0))
        if birthyear > 0:
            zodiac_icons = {
                0: "monkey.png",  1: "rooster.png", 2: "dog.png",    3: "pig.png",
                4: "rat.png",      5: "ox.png",       6: "tiger.png",  7: "rabbit.png",
                8: "dragon.png",   9: "snake.png",   10: "horse.png", 11: "sheep.png"
            }
            auto_icon = zodiac_icons[birthyear % 12]
            with db.cursor() as cursor:
                cursor.execute(
                    "UPDATE nasagung_users SET profile_img = %s WHERE email = %s",
                    (auto_icon, user_email)
                )
                db.commit()
            user["profile_img"] = auto_icon
            current_img = auto_icon

    if "_" in current_img:
        img_display_path = f"/static/uploads/{current_img}"
    else:
        img_display_path = f"/static/images/{current_img}"

    return templates.TemplateResponse(
        request=request,
        name="edit_profile.html",
        context={
            "user": user,
            "img_display_path": img_display_path
        }
    )


@app.post("/edit-profile", response_class=HTMLResponse)
async def edit_profile_submit(
    request: Request,
    name: str = Form(...),
    gender: str = Form(...),
    birthyear: int = Form(...),
    birthday: str = Form(...),
    birthtime: str = Form(...),
    phone: str = Form(...),
    calendarType: str = Form(...),
    password: str = Form(None),
    profile_img: UploadFile = File(None),
    db=Depends(get_db)
):
    user_email = request.cookies.get("user_email")
    if not user_email:
        return HTMLResponse(
            content="<script>alert('로그인이 필요합니다.'); location.href='/login';</script>"
        )

    try:
        update_fields = [
            "name = %s", "gender = %s", "birthyear = %s",
            "birthday = %s", "birthtime = %s", "phone = %s", "calendarType = %s"
        ]
        params = [name, gender, birthyear, birthday, birthtime, phone, calendarType]

        if password and password.strip():
            hashed_pw = hashlib.md5(password.strip().encode('utf-8')).hexdigest()
            update_fields.append("password = %s")
            params.append(hashed_pw)

        if profile_img and profile_img.filename:
            file_ext = os.path.splitext(profile_img.filename)[1]
            new_file_name = f"{int(time.time())}_{uuid.uuid4().hex[:8]}{file_ext}"
            file_path = os.path.join(UPLOAD_DIR, new_file_name)

            contents = await profile_img.read()
            with open(file_path, "wb") as f:
                f.write(contents)

            update_fields.append("profile_img = %s")
            params.append(new_file_name)

        params.append(user_email)
        sql = f"UPDATE nasagung_users SET {', '.join(update_fields)} WHERE email = %s"

        with db.cursor() as cursor:
            cursor.execute(sql, tuple(params))
            db.commit()

        with db.cursor() as cursor:
            cursor.execute("SELECT * FROM nasagung_users WHERE email = %s", (user_email,))
            updated_user = cursor.fetchone()

        curr_img = updated_user.get("profile_img") or ""
        img_path = f"/static/uploads/{curr_img}" if "_" in curr_img else f"/static/images/{curr_img}"

        return templates.TemplateResponse(
            request=request,
            name="edit_profile.html",
            context={
                "user": updated_user,
                "img_display_path": img_path,
                "success": True
            }
        )

    except Exception as e:
        with db.cursor() as cursor:
            cursor.execute("SELECT * FROM nasagung_users WHERE email = %s", (user_email,))
            user = cursor.fetchone()

        curr_img = user.get("profile_img") or ""
        img_path = f"/static/uploads/{curr_img}" if "_" in curr_img else f"/static/images/{curr_img}"

        return templates.TemplateResponse(
            request=request,
            name="edit_profile.html",
            context={
                "user": user,
                "img_display_path": img_path,
                "error": f"수정 중 오류가 발생했습니다: {str(e)}"
            }
        )

# ==========================================
# 로또 페이지 (GET: 화면 접속 / POST: AI 번호 생성)
# ==========================================
@app.api_route("/lotto", methods=["GET", "POST"], response_class=HTMLResponse)
async def lotto_page(
    request: Request,
    user_email: Optional[str] = Cookie(None),
    db=Depends(get_db)
):
    # DB에서 현재 로그인한 유저 정보 조회
    current_user = get_current_user(user_email, db)

    # 1. 주소창 직접 접속 (GET 요청)
    if request.method == "GET":
        return templates.TemplateResponse(
            request=request,
            name="lotto.html",
            context={
                "user": current_user,
                "result": None,
                "service_title": "로또 번호 예측"
            }
        )

    # 2. 폼 제출 (POST 요청)
    try:
        form_data = await request.form()
        name = form_data.get("name", "")
        birthYear = form_data.get("birthYear", "")
        birthDay = form_data.get("birthDay", "")
        birthTime = form_data.get("birthTime", "")
        gender = form_data.get("gender", "")
        calendarType = form_data.get("calendarType", "")
        fiveElements = form_data.get("fiveElements", "")

        birthdate = f"{birthYear}-{birthDay}"
        service_title = "로또 번호 예측"
        system_role = "당신은 타고난 사주 오행과 천기의 흐름을 바탕으로 행운의 숫자를 산출하는 전문 숫자 분석가입니다."
        
        prompt_content = (
            "아래 사용자 정보를 분석하여 오직 '숫자'와 '쉼표', '줄바꿈' 기호만 사용하여 답변을 작성하세요. "
            "절대로 인사말, 사주 풀이 설명, 마크다운(###, **, -) 등의 일반 텍스트를 포함해서는 안 됩니다.\n\n"
            f"[사용자 정보]\n- 이름: {name}\n- 성별: {gender}\n- 생년월일: {birthdate} ({calendarType})\n- 출생시간: {birthTime}\n- 집중오행기운: {fiveElements}\n\n"
            "[출력 형식 및 제한 요구사항]\n1. 사용자의 사주 음양오행과 집중 기운을 참고하여 1부터 45 사이의 무작위 로또 번호 6개를 한 줄에 출력하세요.\n"
            "2. 총 5줄(5게임, 총 30개 숫자)을 엔터(줄바꿈)로 구분하여 출력하세요.\n"
            "3. 각 줄의 숫자는 쉼표(,)로만 구분되어야 합니다.\n"
            "4. ★중요: 각 줄의 숫자 6개는 절대로 작은 수부터 정렬(1, 2, 3...)하지 말고, 무작위로 추출된 천기의 순서 그대로 뒤섞어 출력해야 합니다.\n\n"
            "[올바른 출력 예시]\n42,7,19,3,32,11\n14,28,5,44,22,1\n33,9,18,25,41,12\n2,21,39,17,30,8\n45,13,6,24,35,16"
        )

        response = client.chat.completions.create(
            model="gpt-4o",
            messages=[
                {"role": "system", "content": system_role},
                {"role": "user", "content": prompt_content}
            ],
            temperature=0.8
        )
        lotto_result = response.choices[0].message.content.strip()

    except Exception as e:
        logger.error(f"Lotto prediction error: {str(e)}")
        lotto_result = f"AI 번호 생성 중 오류가 발생했습니다: {str(e)}"

    return templates.TemplateResponse(
        request=request,
        name="lotto.html",
        context={
            "user": current_user,
            "result": lotto_result,
            "service_title": service_title
        }
    )
    
# ==========================================
# [결제하기] pay_popup.html 처리
# ==========================================
@app.api_route("/pay_popup", methods=["GET", "POST"], response_class=HTMLResponse)
async def pay_popup(
    request: Request,
    action_pay: Optional[str] = Form(None),
    user_email: Optional[str] = Cookie(None),
    db=Depends(get_db)
):
    # 1. 로그인 여부 확인
    current_user = get_current_user(user_email, db) if user_email else None
    
    if not current_user:
        return HTMLResponse(
            "<script>alert('로그인이 필요한 서비스입니다.'); window.close();</script>"
        )

    PRICE = 500
    msg = ""
    pay_success = False
    
    # DB에서 사용자의 보유 포인트 가져오기
    current_point = getattr(current_user, 'current_point', 0) if hasattr(current_user, 'current_point') else current_user.get('current_point', 0)
    current_point = int(current_point or 0)

    # 2. 결제 버튼 클릭 시 (POST)
    if request.method == "POST" and action_pay == "1":
        if current_point < PRICE:
            msg = "보유 포인트가 부족합니다. 충전 후 이용해 주세요."
        else:
            cursor = None
            try:
                new_point = current_point - PRICE
                target_id = int(time.time())  # target_id를 정수(타임스탬프)로 처리
                
                # DB 커서(Cursor) 생성
                cursor = db.cursor()

                # 1. nasagung_users 테이블 포인트 차감
                update_user_sql = "UPDATE nasagung_users SET current_point = %s WHERE email = %s"
                cursor.execute(update_user_sql, (new_point, user_email))

                # 2. point_history 테이블 내역 추가
                insert_history_sql = """
                    INSERT INTO point_history (email, type, amount, description, target_id, created_at)
                    VALUES (%s, 'use', %s, 'AI 로또 예측 번호 전체 해금 구매', %s, NOW())
                """
                cursor.execute(insert_history_sql, (user_email, PRICE, target_id))

                # 3. DB 변경사항 커밋
                db.commit()

                pay_success = True
                current_point = new_point  # 💥 차감된 포인트로 갱신하여 템플릿에 전달
                
            except Exception as e:
                db.rollback()
                logger.error(f"Payment error: {str(e)}")
                msg = "결제 처리 중 오류가 발생했습니다."
            finally:
                if cursor:
                    cursor.close()

    # 3. templates/pay_popup.html 렌더링
    return templates.TemplateResponse(
        request=request,
        name="pay_popup.html",
        context={
            "user_point": current_point,  # 차감 후 변경된 current_point가 들어갑니다.
            "price": PRICE,
            "pay_success": pay_success,
            "msg": msg
        }
    )

# ==========================================
# 1. 포인트 충전 팝업 페이지 오픈 (/charge)
# ==========================================
@app.get("/charge", response_class=HTMLResponse)
async def charge_popup(
    request: Request,
    user_email: Optional[str] = Cookie(None),
    db=Depends(get_db)
):
    # 로그인 체크
    current_user = get_current_user(user_email, db) if user_email else None
    if not current_user:
        return HTMLResponse(
            "<script>alert('로그인이 필요한 서비스입니다.'); window.close();</script>"
        )

    # 현재 보유 포인트 조회
    current_point = getattr(current_user, 'current_point', 0) if hasattr(current_user, 'current_point') else current_user.get('current_point', 0)

    return templates.TemplateResponse(
        request=request,
        name="charge.html",
        context={
            "user_email": user_email,
            "user_point": int(current_point or 0)
        }
    )


# ==========================================
# 2. 포인트 충전 성공 처리 (/charge/success)
# ==========================================
@app.get("/charge/success", response_class=HTMLResponse)
async def charge_success(
    request: Request,
    user_email: str,
    amount: int,
    orderId: str,
    paymentKey: str = "",
    db=Depends(get_db)
):
    cursor = None
    try:
        cursor = db.cursor()

        # 1. 기존 사용자의 현재 포인트 조회
        cursor.execute("SELECT current_point FROM nasagung_users WHERE email = %s", (user_email,))
        row = cursor.fetchone()
        
        current_p = 0
        if row:
            current_p = row[0] if isinstance(row, tuple) else row.get('current_point', 0)

        new_point = int(current_p or 0) + int(amount)

        # 2. nasagung_users 포인트 업데이트
        update_sql = "UPDATE nasagung_users SET current_point = %s WHERE email = %s"
        cursor.execute(update_sql, (new_point, user_email))

        # 3. point_history 내역 추가
        target_id = int(time.time())
        insert_history_sql = """
            INSERT INTO point_history (email, type, amount, description, target_id, created_at)
            VALUES (%s, 'charge', %s, '포인트 충전', %s, NOW())
        """
        cursor.execute(insert_history_sql, (user_email, amount, target_id))

        db.commit()

        # 4. 결제 성공 시 부모 창 포인트 업데이트 및 팝업 닫기 Script 반환
        return HTMLResponse(f"""
            <script>
                alert('{amount:,} 포인트 충전이 완료되었습니다.');
                if (window.opener && !window.opener.closed) {{
                    if (typeof window.opener.updateTopPoint === 'function') {{
                        window.opener.updateTopPoint({new_point});
                    }} else {{
                        window.opener.location.reload();
                    }}
                }}
                window.close();
            </script>
        """)

    except Exception as e:
        db.rollback()
        logger.error(f"Charge Success Processing Error: {str(e)}")
        return HTMLResponse("<script>alert('포인트 적립 처리 중 오류가 발생했습니다.'); window.close();</script>")
    finally:
        if cursor:
            cursor.close()

