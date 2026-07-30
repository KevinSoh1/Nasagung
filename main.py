import os
import logging
from fastapi import FastAPI, Request
from fastapi.responses import HTMLResponse, JSONResponse
from fastapi.templating import Jinja2Templates

# 로그 출력 설정 (Render 로그에 상세 내역 표시)
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI()

# 현재 main.py 기준 절대 경로 설정
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
templates_dir = os.path.join(BASE_DIR, "templates")

templates = Jinja2Templates(directory=templates_dir)

@app.get("/", response_class=HTMLResponse)
async def read_root(request: Request):
    try:
        data_to_pass = "Hello Render!"
        
        # templates 폴더 존재 여부 체크
        if not os.path.exists(templates_dir):
            logger.error(f"templates 디렉토리를 찾을 수 없습니다: {templates_dir}")
            return HTMLResponse(content="<h1>Error: templates 폴더가 없습니다.</h1>", status_code=500)

        return templates.TemplateResponse(
            request=request,
            name="index.html",
            context={"message": data_to_pass}
        )
    except Exception as e:
        logger.error(f"렌더링 중 에러 발생: {str(e)}", exc_info=True)
        return JSONResponse(
            status_code=500,
            content={"error_detail": str(e), "message": "서버 내부 에러가 발생했습니다."}
        )
