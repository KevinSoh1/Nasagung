import hashlib
import os
import re
import time
import uuid
import logging
import requests

from typing import Optional
from fastapi import File, UploadFile
from fastapi import APIRouter, Request, Form, File, UploadFile, Depends
from fastapi.responses import HTMLResponse, RedirectResponse
from database import get_db

router = APIRouter()

# ==========================================
# 1. 로그인 페이지 화면 띄우기 (GET)
# ==========================================
@router.get("/login", response_class=HTMLResponse)
async def login_page(request: Request):
    return templates.TemplateResponse(request, "login.html")


# ==========================================
# 2. 로그인 폼 제출 처리 (POST)
# ==========================================
@router.post("/login", response_class=HTMLResponse)
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
@router.get("/callback")
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
@router.get("/logout")
async def logout():
    response = RedirectResponse(url="/", status_code=303)
    response.delete_cookie(key="user_email")
    return response


# ==========================================
# 4. 회원가입 페이지 화면 띄우기 (GET)
# ==========================================
@router.get("/register", response_class=HTMLResponse)
async def register_page(request: Request):
    return templates.TemplateResponse(request, "register.html")


# ==========================================
# 5. 회원가입 폼 제출 처리 (POST)
# ==========================================
@router.post("/register", response_class=HTMLResponse)
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
@router.get("/mypage", response_class=HTMLResponse)
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
@router.get("/edit-profile", response_class=HTMLResponse)
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


@router.app.post("/edit-profile", response_class=HTMLResponse)
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
