import os
import logging
from typing import Optional
from fastapi import APIRouter, Request, Cookie, Depends
from fastapi.responses import HTMLResponse
from fastapi.templating import Jinja2Templates
from sqlalchemy import text

from database import get_db, get_current_user
from config import OPENAI_API_KEY
from openai import OpenAI

logger = logging.getLogger(__name__)
client = OpenAI(api_key=OPENAI_API_KEY)

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
BASE_DIR = os.path.dirname(CURRENT_DIR)
TEMPLATES_DIR = os.path.join(BASE_DIR, "templates")
templates = Jinja2Templates(directory=TEMPLATES_DIR)

router = APIRouter()

# ==========================================
# 헬퍼 함수: point_history 결제 여부 확인
# ==========================================
def check_lotto_payment(user_email: str, db) -> bool:
    """point_history 테이블에서 로또 번호 결제 내역(LOTTO_PAY) 유무 확인"""
    if not user_email:
        return False
    
    try:
        if hasattr(db, "cursor"): # Raw PyMySQL / MySQLdb Cursor
            with db.cursor() as cursor:
                sql = """
                    SELECT COUNT(*) as count 
                    FROM point_history 
                    WHERE email = %s AND type = 'LOTTO_PAY' AND target_id = 'lotto'
                """
                cursor.execute(sql, (user_email,))
                row = cursor.fetchone()
                if isinstance(row, dict):
                    return row.get("count", 0) > 0
                elif row:
                    return row[0] > 0
        else: # SQLAlchemy Engine / Session
            query = text("""
                SELECT COUNT(*) 
                FROM point_history 
                WHERE email = :email AND type = 'LOTTO_PAY' AND target_id = 'lotto'
            """)
            result = db.execute(query, {"email": user_email}).scalar()
            return bool(result and result > 0)
    except Exception as e:
        logger.error(f"결제 확인 조회 실패: {e}")
        return False
    return False


# ==========================================
# 1. 로또 페이지 (/lotto)
# ==========================================
@router.api_route("/lotto", methods=["GET", "POST"], response_class=HTMLResponse)
async def lotto_page(
    request: Request,
    user_email: Optional[str] = Cookie(None),
    db=Depends(get_db)
):
    current_user = None
    is_paid_user = False

    if user_email:
        try:
            current_user = get_current_user(user_email, db)
            # point_history 내역 검사하여 결제 여부 결정
            is_paid_user = check_lotto_payment(user_email, db)
        except Exception as e:
            logger.warning(f"유저 정보 조회 중 예외: {e}")

    if request.method == "GET":
        return templates.TemplateResponse(
            request=request,
            name="lotto.html",
            context={
                "user": current_user,
                "result": None,
                "is_paid": is_paid_user,
                "service_title": "로또 번호 예측"
            }
        )

    displayed_result = None

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
            "절대로 인사말, 설명글, 마크다운 기호를 포함해선 안 됩니다.\n\n"
            f"[사용자 정보]\n- 이름: {name}\n- 성별: {gender}\n- 생년월일: {birthdate} ({calendarType})\n- 출생시간: {birthTime}\n- 집중오행기운: {fiveElements}\n\n"
            "[요구사항]\n1. 1부터 45 사이의 무작위 로또 번호 6개를 한 줄에 출력하세요.\n"
            "2. 총 5줄(5게임)을 엔터(줄바꿈)로 구분하여 출력하세요.\n"
            "3. 각 줄의 숫자는 쉼표(,)로 구분하세요.\n"
            "4. ★중요: 번호 순서 정렬 없이 무작위 추출 순서 그대로 뒤섞어 출력하세요.\n\n"
            "[출력 예시]\n42,7,19,3,32,11\n14,28,5,44,22,1\n33,9,18,25,41,12\n2,21,39,17,30,8\n45,13,6,24,35,16"
        )

        response = client.chat.completions.create(
            model="gpt-4o",
            messages=[
                {"role": "system", "content": system_role},
                {"role": "user", "content": prompt_content}
            ],
            temperature=0.8
        )
        full_lotto_result = response.choices[0].message.content.strip()

        # 결제 성공 고객 -> 5게임 전체 반환 / 미결제 고객 -> 1게임만 반환
        if is_paid_user:
            displayed_result = full_lotto_result
        else:
            lines = [line.strip() for line in full_lotto_result.split("\n") if line.strip()]
            displayed_result = lines[0] if lines else full_lotto_result

    except Exception as e:
        logger.error(f"Lotto prediction error: {str(e)}")
        displayed_result = f"AI 번호 생성 중 오류가 발생했습니다: {str(e)}"

    return templates.TemplateResponse(
        request=request,
        name="lotto.html",
        context={
            "user": current_user,
            "result": displayed_result,
            "is_paid": is_paid_user,
            "service_title": service_title
        }
    )

# ==========================================
# 2. 결제 팝업창 (/pay_popup)
# ==========================================
@router.api_route("/pay_popup", methods=["GET", "POST"], response_class=HTMLResponse)
async def process_payment(
    request: Request,
    db=Depends(get_db),
    user_email: Optional[str] = Cookie(None)
):
    current_user = None
    if user_email:
        try:
            current_user = get_current_user(user_email, db)
        except Exception as e:
            logger.warning(f"유저 조회 실패: {e}")

    PRICE = 500
    msg = None
    pay_success = False

    if isinstance(current_user, dict):
        user_point = current_user.get("current_point", 0)
    else:
        user_point = getattr(current_user, "current_point", 0) if current_user else 0

    if request.method == "POST":
        if not user_email or not current_user:
            msg = "로그인이 필요한 서비스입니다."
        elif user_point < PRICE:
            msg = "포인트가 부족합니다."
        else:
            try:
                # Raw Cursor 처리
                if hasattr(db, "cursor"):
                    with db.cursor() as cursor:
                        # 1) nasagung_users 포인트 차감
                        sql_user = "UPDATE nasagung_users SET current_point = current_point - %s WHERE email = %s"
                        cursor.execute(sql_user, (PRICE, user_email))

                        # 2) point_history 내역 기록
                        sql_history = """
                            INSERT INTO point_history (email, type, amount, description, target_id)
                            VALUES (%s, 'LOTTO_PAY', %s, '로또 5게임 조합 열람', 'lotto')
                        """
                        cursor.execute(sql_history, (user_email, -PRICE))
                    db.commit()

                # SQLAlchemy 처리
                else:
                    sql_user = text("UPDATE nasagung_users SET current_point = current_point - :price WHERE email = :email")
                    db.execute(sql_user, {"price": PRICE, "email": user_email})

                    sql_history = text("""
                        INSERT INTO point_history (email, type, amount, description, target_id)
                        VALUES (:email, 'LOTTO_PAY', :amount, '로또 5게임 조합 열람', 'lotto')
                    """)
                    db.execute(sql_history, {"email": user_email, "amount": -PRICE})
                    db.commit()

                user_point -= PRICE
                pay_success = True

            except Exception as e:
                if hasattr(db, "rollback"):
                    db.rollback()
                logger.error(f"포인트 결제 처리 중 오류 발생: {e}", exc_info=True)
                msg = f"결제 처리 실패: {str(e)}"

    return templates.TemplateResponse(
        request=request,
        name="pay_popup.html",
        context={
            "price": PRICE,
            "user_point": user_point,
            "pay_success": pay_success,
            "msg": msg
        }
    )
