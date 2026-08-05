import os
import logging
import pymysql
from typing import Optional
from fastapi import FastAPI, HTTPException, Request
from fastapi.responses import HTMLResponse
from fastapi.middleware.cors import CORSMiddleware
from fastapi.templating import Jinja2Templates
from pydantic import BaseModel
from openai import OpenAI
from fastapi.staticfiles import StaticFiles  # Static Files 임포트

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

# Render 환경 변수 로드
DB_HOST = os.getenv("DB_HOST", "nasagung-nasagung.g.aivencloud.com")
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

# 💡 DB 연결 테스트 전용 엔드포인트
@app.get("/db-test")
async def test_db():
    try:
        conn = get_db()
        cursor = conn.cursor()
        cursor.execute("SELECT VERSION();")
        version = cursor.fetchone()
        conn.close()
        return {"status": "success", "message": f"Aiven DB 연결 성공! (MySQL 버젼: {version})" }
    except Exception as e:
        return {"status": "error", "message": f"DB 연결 실패: {str(e)}"}

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

@app.get("/", response_class=HTMLResponse)
async def read_root(request: Request):
    """templates 폴더 안의 index.html 또는 index.php를 Jinja2 템플릿으로 감지하여 반환하는 라우터"""
    
    # 1. templates 폴더 안에서 index.html 또는 index.php 검색
    for target_file in ["index.html"]:
        file_path = os.path.join(templates_dir, target_file)
        if os.path.exists(file_path):
            # 💡 f.read() 대신 TemplateResponse를 사용해야 {% include %}가 동작합니다!
            return templates.TemplateResponse(request, target_file)
            
    # 2. 찾지 못했을 경우 예외 처리
    return HTMLResponse(
        content="<h1>Error: templates 폴더 내에서 index.html 또는 index.php 문서를 찾을 수 없습니다.</h1>", 
        status_code=404
    )

@app.get("/login", response_class=HTMLResponse)
async def login_page(request: Request):
    return templates.TemplateResponse(request, "login.html")


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
