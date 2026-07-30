import os
from fastapi import FastAPI, Request
from fastapi.responses import HTMLResponse
from fastapi.templating import Jinja2Templates

app = FastAPI()

# 현재 main.py가 위치한 폴더 기준으로 templates 경로 설정 (안전성 강화)
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
templates = Jinja2Templates(directory=os.path.join(BASE_DIR, "templates"))

@app.get("/", response_class=HTMLResponse)
async def read_root(request: Request):
    # HTML 템플릿으로 전달할 데이터
    data_to_pass = "Hello Render!"
    
    # index.html 파일에 message라는 이름으로 변수 전달
    return templates.TemplateResponse(
        name="index.html", 
        context={"request": request, "message": data_to_pass}
    )
