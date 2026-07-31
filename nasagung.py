from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from dotenv import load_dotenv
from openai import OpenAI
import os
from typing import Optional  # 💡 필수 모듈 임포트 추가 (스키마 검증 에러 방어)

# 환경변수 로드
load_dotenv()

# FastAPI 생성
app = FastAPI()

# CORS 에러 방지 설정
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# OpenAI 클라이언트 초기화
client = OpenAI(
    api_key=os.getenv("OPENAI_API_KEY")
)

# 💡 구버전 파이썬 파서 컴파일러용 클래스 정의형 매핑
class SajuRequest(BaseModel):
    name: str
    gender: str
    birthdate: str
    birthtime: str
    calendarType: str
    productID: str = "mBan"
    fiveElements: str = ""
    
    # 궁합용 상대방 데이터 필드 추가 (기존 API 하위 호환성을 위해 Optional 처리)
    partnerName: Optional[str] = ""
    partnerGender: Optional[str] = ""
    partnerBirthdate: Optional[str] = ""
    partnerBirthtime: Optional[str] = ""
    partnerCalendarType: Optional[str] = ""


@app.post("/nasagung/analyze")
async def get_saju_analysis(request: SajuRequest):
    try:
        # 프런트엔드에서 넘어온 데이터 추출
        name = request.name
        gender = request.gender
        birthdate = request.birthdate
        birthtime = request.birthtime
        calendar_type = request.calendarType
        product_id = request.productID
        five_elements = request.fiveElements
        
        # 상대방 데이터 추출
        partner_name = request.partnerName
        partner_gender = request.partnerGender
        partner_birthdate = request.partnerBirthdate
        partner_birthtime = request.partnerBirthtime
        partner_calendar_type = request.partnerCalendarType
        
        
        # productID 값에 따라 시스템 역할 분기 정의
        if product_id == "toDay":
            service_title = "오늘의 운세"
            system_role = "당신은 전통 사주 명리학자입니다."
            specific_requirement = "오늘의 운세와 하루의 흐름에 대해 상세히 설명해 주세요."
            
            # 일반 상품용 통합 프롬프트 구성 (문자열 안전 결합 처리)
            prompt_content = "당신은 정교하고 신뢰감 있는 전문 명리학자입니다.\n아래의 사용자 정보를 바탕으로 깊이 있는 사주/운세 풀이를 제공해 주세요.\n\n[사용자 정보]\n- 이름: " + str(name) + "\n- 성별: " + str(gender) + "\n- 생년월일: " + str(birthdate) + " (" + str(calendar_type) + ")\n- 출생시간: " + str(birthtime) + "\n\n[답변 요구사항]\n1. 정중하고 부드러운 어조(한글)로 작성해 주세요.\n2. " + str(specific_requirement) + "\n3. 가독성이 좋게 단락을 나누고 markdown 서식을 활용하여 친절하게 설명해 주세요."
            
        elif product_id == "lotto":
            service_title = "로또 번호 예측"
            system_role = "당신은 타고난 사주 오행과 천기의 흐름을 바탕으로 행운의 숫자를 산출하는 전문 숫자 분석가입니다."
            
            # 💡 [SyntaxError 원인 완전 제거]: 
            # 구형 파서에서 오작동을 일으킬 수 있는 복잡한 문자열 구조 및 줄바꿈 기호를 
            # 개별 줄 단위 문자열로 조각내어 더하기 연산(+)으로 안전하게 안전망을 구축했습니다.
            prompt_content = "아래 사용자 정보를 분석하여 오직 '숫자'와 '쉼표', '줄바꿈' 기호만 사용하여 답변을 작성하세요. 절대로 인사말, 사주 풀이 설명, 마크다운(###, **, -) 등의 일반 텍스트를 포함해서는 안 됩니다.\n\n"
            prompt_content = prompt_content + "[사용자 정보]\n- 이름: " + str(name) + "\n- 성별: " + str(gender) + "\n- 생년월일: " + str(birthdate) + " (" + str(calendar_type) + ")\n- 출생시간: " + str(birthtime) + "\n- 집중오행기운: " + str(five_elements) + "\n\n"
            prompt_content = prompt_content + "[출력 형식 및 제한 요구사항]\n1. 사용자의 사주 음양오행과 집중 기운을 참고하여 1부터 45 사이의 무작위 로또 번호 6개를 한 줄에 출력하세요.\n2. 총 5줄(5게임, 총 30개 숫자)을 엔터(줄바꿈)로 구분하여 출력하세요.\n3. 각 줄의 숫자는 쉼표(,)로만 구분되어야 합니다.\n4. ★중요: 각 줄의 숫자 6개는 절대로 작은 수부터 정렬(1, 2, 3...)하지 말고, 무작위로 추출된 천기의 순서 그대로 뒤섞어 출력해야 합니다.\n\n"
            prompt_content = prompt_content + "[올바른 출력 예시]\n42,7,19,3,32,11\n14,28,5,44,22,1\n33,9,18,25,41,12\n2,21,39,17,30,8\n45,13,6,24,35,16"
       
        elif product_id == "gunghap":
            print("[ProductID :]" + product_id)
            service_title = "전통 궁합 분석"
            system_role = "당신은 두 사람의 사주와 오행의 조화를 분석하여 인연의 깊이를 풀어주는 전통 명리 궁합 전문가입니다."
            
            # 기존 가독성 안전망 구조를 유지한 문자열 결합 방식의 프롬프트
            prompt_content = "당신은 정교하고 신뢰감 있는 전문 궁합 학자입니다.\n아래 두 사람의 사주 정보를 바탕으로 성격 조화, 애정운, 주의해야 할 점 등을 종합적으로 분석한 깊이 있는 궁합 풀이를 제공해 주세요.\n\n"
            prompt_content = prompt_content + "[본인 정보]\n- 이름: " + str(name) + "\n- 성별: " + str(gender) + "\n- 생년월일: " + str(birthdate) + " (" + str(calendar_type) + ")\n- 출생시간: " + str(birthtime) + "\n\n"
            prompt_content = prompt_content + "[상대방 정보]\n- 이름: " + str(partner_name) + "\n- 성별: " + str(partner_gender) + "\n- 생년월일: " + str(partner_birthdate) + " (" + str(partner_calendar_type) + ")\n- 출생시간: " + str(partner_birthtime) + "\n\n"
            prompt_content = prompt_content + "[답변 요구사항]\n1. 정중하고 부드러운 어조(한글)로 작성해 주세요.\n2. 두 사람의 오행 조화, 성향 차이, 그리고 함께하면 좋은 발전적인 방향을 상세히 설명해 주세요.\n3. 가독성이 좋게 단락을 나누고 markdown 서식을 활용하여 친절하게 설명해 주세요."

        else:
            # 기본값 혹은 mBan 일 때
            service_title = "명반 분석"
            system_role = "당신은 전통 자미두수 및 명반 분석 전문가입니다."
            specific_requirement = "전체적인 타고난 운명의 흐름과 성향을 분석해 주세요."
            
            # 일반 상품용 통합 프롬프트 구성
            prompt_content = "당신은 정교하고 신뢰감 있는 전문 명리학자입니다.\n아래의 사용자 정보를 바탕으로 깊이 있는 사주/운세 풀이를 제공해 주세요.\n\n[사용자 정보]\n- 이름: " + str(name) + "\n- 성별: " + str(gender) + "\n- 생년월일: " + str(birthdate) + " (" + str(calendar_type) + ")\n- 출생시간: " + str(birthtime) + "\n\n[답변 요구사항]\n1. 정중하고 부드러운 어조(한글)로 작성해 주세요.\n2. " + str(specific_requirement) + "\n3. 가독성이 좋게 단락을 나누고 markdown 서식을 활용하여 친절하게 설명해 주세요."

        # 콘솔 로그 출력 (낮은 버전 표준 포맷 매핑 구조 기술)
        print("[요청 알림] 이름: %s / 생년월일: %s / 시간: %s / 성별: %s / 달력: %s / 상품: %s -> *%s* 진행" % (name, birthdate, birthtime, gender, calendar_type, product_id, service_title))
        
        # OpenAI API 호출
        response = client.chat.completions.create(
            model="gpt-4o-mini",
            messages=[
                {"role": "system", "content": system_role},
                {"role": "user", "content": prompt_content}
            ]
        )
        
        # 결과 추출 및 반환
        analysis_result = response.choices[0].message.content
        
        # 로또 번호 추출 결과 터미널 정밀 세션 출력
        if product_id == "lotto":
            print("\n=========================================")
            print("[로또 번호 예측 결과 터미널 출력]")
            print(str(analysis_result).strip())
            print("=========================================\n")
        
        if product_id == "gunghap":
            print("\n=========================================")
            print(str(analysis_result))
            print("=========================================\n")
        
        return {
            "result": analysis_result
        }

    except Exception as e:
        print("[오류 발생]: " + str(e))
        # 낮은 버전 표준 구조 예외 응답 처리
        raise HTTPException(status_code=500, detail="서버 내부 처리 중 오류 발생: " + str(e))

# 실행 명령어: uvicorn nasagung:app --host 0.0.0.0 --port 8000 --reload