# main.py
from fastapi import FastAPI, Request
from fastapi.responses import HTMLResponse
from fastapi.templating import Jinja2Templates

app = FastAPI()

# templates 폴더 위치 지정
templates = Jinja2Templates(directory="templates")

@app.get("/", response_class=HTMLResponse)
def read_root(request: Request):
    # HTML 템플릿으로 전달할 데이터
    data_to_pass = "Hello Render!"
    
    # index.html 파일에 message라는 이름으로 변수 전달
    return templates.TemplateResponse(
        "index.html", 
        {"request": request, "message": data_to_pass}
    )
