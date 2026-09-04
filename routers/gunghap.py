# 1. Standard Library (파이썬 기본 라이브러리)
import logging
from typing import Optional

# 2. Third-Party Packages (외부 패키지)
from openai import OpenAI  # client 사용을 위해 필요
from fastapi import APIRouter, Request, Cookie, Depends
from fastapi.responses import HTMLResponse, JSONResponse
from sqlalchemy.orm import Session

# 3. Local / Project Imports (내부 파일 및 모듈)
# ※ 아래 모듈 이름과 경로(main, database, models 등)는 실제 프로젝트 구조에 맞춰 수정하세요.
from database import get_db             # DB 세션 의존성 Injection 함수
from database import get_current_user       # 유저 조회 함수
from main import templates              # Jinja2Templates 인스턴스

router = APIRouter()

# ==========================================
# 궁합 입력 처리 부분
# ==========================================

# 1. 궁합 입력 페이지 & 결과 페이지 GET 라우트
@router.app.get("/gunghap.html", response_class=HTMLResponse)
@router.app.get("/gunghap", response_class=HTMLResponse)
async def get_gunghap_page(request: Request, user_email: Optional[str] = Cookie(None), db: Session = Depends(get_db)):
    # 1. 로그인 쿠키(user_email)가 있는 경우 DB에서 유저 조회
    current_user = get_current_user(user_email, db) if user_email else None
    return templates.TemplateResponse(
        request=request, 
        name="gunghap.html",
        context={
            "user": current_user
        }
    )

@router.app.get("/gunghapResult.html", response_class=HTMLResponse)
async def get_gunghap_result_page(request: Request, user_email: Optional[str] = Cookie(None)):
    is_logged_in = bool(user_email)
    return templates.TemplateResponse(
        request=request, 
        name="gunghapResult.html",
        context={"is_logged_in": is_logged_in}
    )

# 2. 궁합 분석 API (POST /gunghap)
@router.app.post("/gunghap")
async def analyze_gunghap(request: Request, user_email: Optional[str] = Cookie(None)):
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

        # OpenAI 궁합 분석 전용 프롬프트 구성
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

        completion = client.chat.completions.create(
            model="gpt-4o-mini",
            messages=[
                {
                    "role": "system", 
                    "content": system_instruction
                },
                {
                    "role": "user", 
                    "content": prompt
                }
            ],
            temperature=0.7
        )

        full_result = completion.choices[0].message.content.strip()
        is_logged_in = bool(user_email)

        # 로그인 여부에 따라 결과 텍스트 길이 제어 (비로그인 시 1/3 제공)
        if is_logged_in:
            return JSONResponse({"is_logged_in": True, "result": full_result})
        else:
            one_third_len = len(full_result) // 3
            return JSONResponse({"is_logged_in": False, "result": full_result[:one_third_len]})

    except Exception as e:
        logger.error(f"Gunghap Analysis Error: {str(e)}")
        return JSONResponse({"error": "궁합 분석 중 오류가 발생했습니다."}, status_code=500)
