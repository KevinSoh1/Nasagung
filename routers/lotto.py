import os
import logging
from typing import Optional
from fastapi import APIRouter, Request, Cookie, Depends, Form
from fastapi.responses import HTMLResponse, JSONResponse
from fastapi.templating import Jinja2Templates

from database import get_db, get_current_user
from config import OPENAI_API_KEY
from openai import OpenAI

logger = logging.getLogger(__name__)
client = OpenAI(api_key=OPENAI_API_KEY)

# 템플릿 경로 설정
CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
BASE_DIR = os.path.dirname(CURRENT_DIR)
TEMPLATES_DIR = os.path.join(BASE_DIR, "templates")
templates = Jinja2Templates(directory=TEMPLATES_DIR)

router = APIRouter()

LOTTO_PRICE = 500  # 로또 5게임 열람 결제 포인트

# ==========================================
# 로또 페이지 (GET / POST)
# ==========================================
# ==========================================
# 결제 팝업 페이지 (GET / POST)
# ==========================================
@router.api_route("/pay_popup", methods=["GET", "POST"], response_class=HTMLResponse)
async def pay_popup(
    request: Request,
    user_email: Optional[str] = Cookie(None),
    db=Depends(get_db)
):
    # 1. 쿠키 확인 및 디버깅 로그
    logger.info(f"[PAY_POPUP] 요청 Method: {request.method}, Cookie Email: {user_email}")

    if not user_email:
        return HTMLResponse("로그인이 필요합니다. (쿠키 정보 없음)", status_code=401)

    # 2. 유저 정보 조회
    current_user = get_current_user(user_email, db)
    if not current_user:
        logger.error(f"[PAY_POPUP] 유저 조회 실패: {user_email}")
        return HTMLResponse("유저 정보를 찾을 수 없습니다.", status_code=404)

    # 3. 객체 타입(Dict vs ORM Class)에 구애받지 않고 안전하게 포인트 및 결제상태 추출
    def get_user_attr(user_obj, attr_name, default_value):
        if isinstance(user_obj, dict):
            return user_obj.get(attr_name, default_value)
        return getattr(user_obj, attr_name, default_value)

    def set_user_attr(user_obj, attr_name, value):
        if isinstance(user_obj, dict):
            user_obj[attr_name] = value
        else:
            setattr(user_obj, attr_name, value)

    user_point = int(get_user_attr(current_user, "point", 0) or 0)
    logger.info(f"[PAY_POPUP] 현재 조회된 유저 포인트: {user_point} P (필요: {LOTTO_PRICE} P)")

    pay_success = False
    msg = None

    # 4. 결제(POST) 처리
    if request.method == "POST":
        if user_point < LOTTO_PRICE:
            msg = f"포인트가 부족합니다. (보유: {user_point}P / 필요: {LOTTO_PRICE}P)"
            logger.warning(f"[PAY_POPUP] 결제 실패 - {msg}")
        else:
            try:
                new_point = user_point - LOTTO_PRICE
                
                # DB / 객체에 차감된 포인트 및 결제 상태 반영
                set_user_attr(current_user, "point", new_point)
                set_user_attr(current_user, "is_paid", True)

                # SQLAlchemy ORM을 사용 중일 경우 DB 커밋 수행
                if hasattr(db, "commit"):
                    db.commit()
                    if hasattr(db, "refresh"):
                        db.refresh(current_user)

                user_point = new_point
                pay_success = True
                logger.info(f"[PAY_POPUP] 결제 성공! 차감 후 잔여 포인트: {user_point} P")

            except Exception as e:
                logger.error(f"[PAY_POPUP] DB 저장 중 오류 발생: {str(e)}")
                if hasattr(db, "rollback"):
                    db.rollback()
                msg = "결제 처리(DB 저장) 중 오류가 발생했습니다."

    return templates.TemplateResponse(
        request=request,
        name="pay_popup.html",
        context={
            "price": LOTTO_PRICE,
            "user_point": user_point,
            "pay_success": pay_success,
            "msg": msg
        }
    )
