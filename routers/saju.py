# ==========================================
# 명반 분석 API (POST /chat)
# ==========================================

# 1. Standard Library (파이썬 기본 라이브러리)
import logging
from typing import Optional

# 2. Third-Party Packages (외부 패키지)
import openai  # openai.chat.completions.create(...) 직접 호출 시 필요
from fastapi import APIRouter, Request, Cookie, Depends

# 3. Local / Project Imports (내부 파일 및 모듈)
# ※ 프로젝트 구조에 맞춰 database 모듈 경로는 수정해 주세요.
from database import get_db

router = APIRouter()

@router.post("/chat")
async def analyze_saju(
    request: Request,
    user_email: Optional[str] = Cookie(None),
    db=Depends(get_db)
):
    try:
        data = await request.json()
        name = data.get("name", "미입력")
        gender = data.get("gender", "미입력")
        birthdate = data.get("birthdate", "")
        birthtime = data.get("birthtime", "")
        calendar_type = data.get("calendarType", "양력")

        # OpenAI 프롬프트 구성
        prompt = f"""
        다음 사용자의 사주 및 명반을 바탕으로 운세와 종합 분석을 상세하게 작성해 주세요.
        - 이름: {name}
        - 성별: {gender}
        - 생년월일: {birthdate} ({calendar_type})
        - 출생시간: {birthtime}
        """

        # GPT-4o-mini 호출
        completion = openai.chat.completions.create(
            model="gpt-4o-mini",
            messages=[
                {"role": "system", "content": "당신은 정교하고 신뢰감 있는 전문적인 명반 및 사주 전문 명리학자입니다.\n 사용자 정보를 바탕으로 깊이 있는 사주/운세 풀이를 제공해 주세요.\n가독성이 좋게 단락을 나누고 markdown 서식을 활용하여 친절하게 설명해 주세요."},
                {"role": "user", "content": prompt}
            ],
            temperature=0.7
        )

        full_result = completion.choices[0].message.content.strip()

        # 로그인 여부 검증
        is_logged_in = bool(user_email)

        if is_logged_in:
            return JSONResponse({
                "is_logged_in": True,
                "result": full_result
            })
        else:
           # 전체 텍스트 길이를 구한 뒤 1/3 지점 계산 (정수 나눗셈 //)
            one_third_length = len(full_result) // 3
            
            return JSONResponse({
                "is_logged_in": False,
                "result": full_result[:one_third_length]
            })

    except Exception as e:
        logger.error(f"Saju Analysis Error: {str(e)}")
        return JSONResponse({"error": "분석 중 오류가 발생했습니다."}, status_code=500)


# ==========================================
# 명반 결과물 출력 Response.html 라우터 등록
# ==========================================
@router.get("/response.html", response_class=HTMLResponse)
async def read_response_page(
    request: Request,
    user_email: Optional[str] = Cookie(None),
    db=Depends(get_db),
):
    current_user = get_current_user(user_email, db) if user_email else None
    
    file_path = os.path.join(templates_dir, "response.html")
    if os.path.exists(file_path):
        return templates.TemplateResponse(
            request=request,
            name="response.html",
            context={"user": current_user, "user_email": user_email}
        )
    return HTMLResponse(content="<h1>response.html 파일을 찾을 수 없습니다.</h1>", status_code=404)

