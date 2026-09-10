# 1. Standard Library
import os
import logging
from typing import Optional

# 2. Third-Party Packages
from openai import OpenAI
from fastapi import APIRouter, Request, Cookie, Depends
from fastapi.responses import HTMLResponse, JSONResponse
from fastapi.templating import Jinja2Templates
from sqlalchemy.orm import Session

# 3. Local / Project Imports
from database import get_db, get_current_user
from config import OPENAI_API_KEY

# 로거 및 OpenAI 클라이언트 설정
logger = logging.getLogger(__name__)
client = OpenAI(api_key=OPENAI_API_KEY)

# --------------------------------------------------------------------------
# [경로 설정] routers/ 폴더에서 상위 루트의 templates/ 디렉토리 바라보기
# --------------------------------------------------------------------------
CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
BASE_DIR = os.path.dirname(CURRENT_DIR)
TEMPLATES_DIR = os.path.join(BASE_DIR, "templates")

templates = Jinja2Templates(directory=TEMPLATES_DIR)

router = APIRouter()

# ==========================================
# 궁합 페이지 & 결과 페이지 GET 라우트
# ==========================================

@router.get("/gunghap.html", response_class=HTMLResponse)
@router.get("/gunghap", response_class=HTMLResponse)
async def get_gunghap_page(
    request: Request, 
    user_email: Optional[str] = Cookie(None), 
    db: Session = Depends(get_db)
):
    current_user = None
    if user_email:
        try:
            current_user = get_current_user(user_email, db)
        except Exception as e:
            logger.warning(f"유저 조회 실패: {e}")

    return templates.TemplateResponse(
        request=request, 
        name="gunghap.html",
        context={"user": current_user}
    )

@router.get("/gunghapResult.html", response_class=HTMLResponse)
@router.get("/gunghapResult", response_class=HTMLResponse)
async def get_gunghap_result_page(
    request: Request, 
    user_email: Optional[str] = Cookie(None),
    db: Session = Depends(get_db)  # 💡 DB 세션 주입 추가
):
    current_user = None
    if user_email:
        try:
            # 💡 DB에서 실제 로그인한 사용자 정보 조회
            current_user = get_current_user(user_email, db)
        except Exception as e:
            logger.warning(f"결과 페이지 유저 조회 실패: {e}")

    return templates.TemplateResponse(
        request=request, 
        name="gunghapResult.html",
        # 💡 TopMenu.html에서 인식할 수 있도록 "user" 객체를 전달
        context={"user": current_user}
    )
    
# ==========================================
# 궁합 분석 API (POST /gunghap)
# ==========================================

@router.post("/gunghap")
async def analyze_gunghap(
    request: Request, 
    user_email: Optional[str] = Cookie(None)
):
    try:
        data = await request.json()
        
        # 본인 정보
        my_name = data.get("name", "본인")
        my_gender = data.get("gender", "")
        my_birthdate = data.get("birthdate", "")
        my_birthtime = data.get("birthtime", "")
        my_calendar = data.get("calendarType", "")

        # 상대방 정보
        partner_name = data.get("partnerName", "상대방")
        partner_gender = data.get("partnerGender", "")
        partner_birthdate = data.get("partnerBirthdate", "")
        partner_birthtime = data.get("partnerBirthtime", "")
        partner_calendar = data.get("partnerCalendarType", "")

        prompt = f"""
        다음 두 사람의 명식을 대조하여 인연과 궁합을 정밀하게 분석해 주세요.
        
        [첫 번째 사람 (본인)]
        - 이름: {my_name}
        - 성별: {my_gender}
        - 생년월일: {my_birthdate} ({my_calendar})
        - 출생시간: {my_birthtime}

        [두 번째 사람 (상대방)]
        - 이름: {partner_name}
        - 성별: {partner_gender}
        - 생년월일: {partner_birthdate} ({partner_calendar})
        - 출생시간: {partner_birthtime}
        
        [분석 요청 사항]
        1. 두 사람의 음양오행적 조화와 전체적인 궁합 점수(총평)
        2. 서로에게 미치는 긍정적 영향과 주의해야 할 갈등 요소
        3. 연애 및 결혼 관점에서의 조화도 및 조언
        """

        system_instruction = """당신은 두 사람의 사주와 오행의 조화를 분석하여 인연의 깊이를 풀어주는 전통 명리 궁합 전문가입니다.
당신은 정교하고 신뢰감 있는 전문 궁합 학자입니다.
아래 두 사람의 사주 정보를 바탕으로 성격 조화, 애정운, 주의해야 할 점 등을 종합적으로 분석한 깊이 있는 궁합 풀이를 제공해 주세요.

1. 정중하고 부드러운 어조(한국어)로 작성해 주세요.
2. 두 사람의 오행 조화, 성향 차이, 그리고 함께하면 좋은 발전적인 방향을 상세히 설명해 주세요.
3. 가독성이 좋게 단락을 나누고 markdown 서식을 활용하여 친절하게 설명해 주세요."""

        # OpenAI 호출 (client 객체 사용)
        completion = client.chat.completions.create(
            model="gpt-4o-mini",
            messages=[
                {"role": "system", "content": system_instruction},
                {"role": "user", "content": prompt}
            ],
            temperature=0.7
        )

        full_result = completion.choices[0].message.content.strip()
        is_logged_in = bool(user_email)

        if is_logged_in:
            return JSONResponse({"is_logged_in": True, "result": full_result})
        else:
            one_third_len = len(full_result) // 3
            return JSONResponse({"is_logged_in": False, "result": full_result[:one_third_len]})

    except Exception as e:
        # 콘솔에 구체적인 에러 메시지 출력
        print(f"================ [GUNGHAP ERROR]: {e} ================")
        logger.error(f"Gunghap Analysis Error: {str(e)}", exc_info=True)
        return JSONResponse({"error": f"궁합 분석 중 오류가 발생했습니다: {str(e)}"}, status_code=500)
