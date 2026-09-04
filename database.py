import pymysql
import urllib.parse
from config import RAW_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME

if RAW_HOST.startswith("mysql://") or RAW_HOST.startswith("postgres://"):
    parsed = urllib.parse.urlparse(RAW_HOST)
    DB_HOST = parsed.hostname
else:
    DB_HOST = RAW_HOST

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


# 로그인 유저 정보 조회 함수 (helper)
def get_current_user(user_email: str, db):
    if not user_email:
        return None
    with db.cursor() as cursor:
        sql = "SELECT email, name, gender, birthyear, birthday, birthtime, calendarType, current_point FROM nasagung_users WHERE email = %s"
        cursor.execute(sql, (user_email,))
        return cursor.fetchone()