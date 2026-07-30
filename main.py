import os
import logging
from fastapi import FastAPI, Request
from fastapi.responses import HTMLResponse, JSONResponse
from fastapi.staticfiles import StaticFiles

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI()

# 1. 메인 페이지 (index.html 파일 전달)
@app.get("/", response_class=HTMLResponse)
async def read_root():
    try:
        # index.html 파일 읽어서 반환
        if os.path.exists("index.html"):
            with open("index.html", "r", encoding="utf-8") as f:
                return f.read()
        elif os.path.exists("templates/index.html"):
            with open("templates/index.html", "r", encoding="utf-8") as f:
                return f.read()
        else:
            return "<h1>index.html 파일을 찾을 수 없습니다.</h1>"
    except Exception as e:
        logger.error(f"HTML 로딩 에러: {str(e)}")
        return f"<h1>서버 에러: {str(e)}</h1>"

# 2. JavaScript가 호출하는 API 엔드포인트
@app.get("/api/data")
async def get_data():
    try:
        # 프론트엔드로 전달할 데이터 ('message' 키 사용)
        return {"message": "Hello Render! 백엔드 연결 성공!"}
    except Exception as e:
        logger.error(f"API 에러: {str(e)}")
        return JSONResponse(
            status_code=500,
            content={"message": f"백엔드 오류: {str(e)}"}
        )
