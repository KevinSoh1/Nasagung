import os
import logging
from typing import Optional
from fastapi import APIRouter, Request, Cookie, Depends
from fastapi.responses import HTMLResponse
from fastapi.templating import Jinja2Templates

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
            if current_user:
                # dict 객체와 ORM 객체 모두 대응 가능한 처리
                if isinstance(current_user, dict):
                    is_paid_user = bool(current_user.get("is_paid", False))
                else:
                    is_paid_user = bool(getattr(current_user, "is_paid", False))
        except Exception as e:
            logger.warning(f"유저 정보 조회 중 오류: {e}")

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
        full_lotto_result = response.choices[0].message.content.strip()

        # 결제 여부에 따른 번호 제공 수 제어
        if is_paid_user:
            displayed_result = full_lotto_result
        else:
            lines = full_lotto_result.split("\n")
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

# 예시: /pay_popup 라우트 처리 함수 내부
@router.post("/pay_popup")
async def process_payment(request: Request, db=Depends(get_db), user_email: Optional[str] = Cookie(None)):
   # 1. 포인트 값 안전하게 꺼내기 (없으면 기본값 0)
user_point = user.get("point", 0) if isinstance(user, dict) else getattr(user, "point", 0)

# 2. 포인트 비교 및 처리
if user_point >= 500:
    if isinstance(user, dict):
        # user가 dict 형태인 경우
        user["point"] = user_point - 500
        user["is_paid"] = True
        
        # ※ 만약 DB 업데이트를 SQL Query로 따로 날려줘야 하는 구조라면 아래 예시처럼 실행
        # db.execute("UPDATE users SET point = :p, is_paid = :p_flag WHERE email = :email", 
        #            {"p": user["point"], "p_flag": True, "email": user.get("email")})
        # db.commit()
    else:
        # user가 ORM 객체인 경우
        user.point -= 500
        user.is_paid = True
        db.commit()
