import os
import logging
from fastapi import APIRouter, Request, Form, Cookie, Depends
from fastapi.responses import HTMLResponse, JSONResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from fastapi.middleware.cors import CORSMiddleware
from openai import OpenAI
from typing import Optional

from config import OPENAI_API_KEY
from database import get_db, get_current_user


# 로깅 설정
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# FastAPI 생성
app = FastAPI()

# OpenAI 및 Template 설정
client = OpenAI(api_key=OPENAI_API_KEY)
BASE_DIR = os.path.dirname(os.path.abspath(__file__))

# 1. templates 폴더 설정
templates_dir = os.path.join(BASE_DIR, "templates")
os.makedirs(templates_dir, exist_ok=True)

templates = Jinja2Templates(directory=templates_dir)
templates.env.policies.setdefault("json.dumps_kwargs", {})

# 2. static 및 하위 폴더 설정
static_dir = os.path.join(BASE_DIR, "static")
UPLOAD_DIR = os.path.join(static_dir, "uploads")
images_dir = os.path.join(static_dir, "images")

for folder in [static_dir, UPLOAD_DIR, images_dir]:
    os.makedirs(folder, exist_ok=True)

# static 디렉토리 매핑 설정
app.mount("/static", StaticFiles(directory=static_dir), name="static")

# CORS 에러 방지 설정
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


# ==========================================
# 메인 루트 페이지 (GET /)
# ==========================================
@app.get("/", response_class=HTMLResponse)
async def read_root(
    request: Request,
    user_email: Optional[str] = Cookie(None),
    db=Depends(get_db),
):
    current_user = get_current_user(user_email, db) if user_email else None

    for target_file in ["index.html"]:
        file_path = os.path.join(templates_dir, target_file)
        if os.path.exists(file_path):
            return templates.TemplateResponse(
                request, target_file, {"user": current_user}
            )

    return HTMLResponse(
        content="<h1>Error: templates 폴더 내에서 index.html 문서를 찾을 수 없습니다.</h1>",
        status_code=404,
    )
    
# ==========================================
# 기능별 라우터(모듈) 등록
# ==========================================
from routers import config, database, auth, saju, gunghap, lotto, payment

app.include_router(config.router)
app.include_router(database.router)
app.include_router(auth.router)
app.include_router(saju.router)
app.include_router(gunghap.router)
app.include_router(lotto.router)
app.include_router(payment.router)
