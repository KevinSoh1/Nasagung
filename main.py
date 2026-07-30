from fastapi import FastAPI, Request
from fastapi.responses import HTMLResponse
from fastapi.templating import Jinja2Templates

app = FastAPI()

# templates 폴더 안의 HTML 파일을 사용할 수 있도록 설정
templates = Jinja2Templates(directory="templates")

@app.get("/", response_class=HTMLResponse)
def read_root(request: Request):
    message = "Hello Render!"
    # index.html에 message 값을 전달
    return templates.TemplateResponse("index.html", {"request": request, "message": message})
