
router = APIRouter()

# ==========================================
# [결제하기] pay_popup.html 처리
# ==========================================
@router.app.api_route("/pay_popup", methods=["GET", "POST"], response_class=HTMLResponse)
async def pay_popup(
    request: Request,
    action_pay: Optional[str] = Form(None),
    user_email: Optional[str] = Cookie(None),
    db=Depends(get_db)
):
    # 1. 로그인 여부 확인
    current_user = get_current_user(user_email, db) if user_email else None
    
    if not current_user:
        return HTMLResponse(
            "<script>alert('로그인이 필요한 서비스입니다.'); window.close();</script>"
        )

    PRICE = 500
    msg = ""
    pay_success = False
    
    # DB에서 사용자의 보유 포인트 가져오기
    current_point = getattr(current_user, 'current_point', 0) if hasattr(current_user, 'current_point') else current_user.get('current_point', 0)
    current_point = int(current_point or 0)

    # 2. 결제 버튼 클릭 시 (POST)
    if request.method == "POST" and action_pay == "1":
        if current_point < PRICE:
            msg = "보유 포인트가 부족합니다. 충전 후 이용해 주세요."
        else:
            cursor = None
            try:
                new_point = current_point - PRICE
                target_id = int(time.time())  # target_id를 정수(타임스탬프)로 처리
                
                # DB 커서(Cursor) 생성
                cursor = db.cursor()

                # 1. nasagung_users 테이블 포인트 차감
                update_user_sql = "UPDATE nasagung_users SET current_point = %s WHERE email = %s"
                cursor.execute(update_user_sql, (new_point, user_email))

                # 2. point_history 테이블 내역 추가
                insert_history_sql = """
                    INSERT INTO point_history (email, type, amount, description, target_id, created_at)
                    VALUES (%s, 'use', %s, 'AI 로또 예측 번호 전체 해금 구매', %s, NOW())
                """
                cursor.execute(insert_history_sql, (user_email, PRICE, target_id))

                # 3. DB 변경사항 커밋
                db.commit()

                pay_success = True
                current_point = new_point  # ?? 차감된 포인트로 갱신하여 템플릿에 전달
                
            except Exception as e:
                db.rollback()
                logger.error(f"Payment error: {str(e)}")
                msg = "결제 처리 중 오류가 발생했습니다."
            finally:
                if cursor:
                    cursor.close()

    # 3. templates/pay_popup.html 렌더링
    return templates.TemplateResponse(
        request=request,
        name="pay_popup.html",
        context={
            "user_point": current_point,  # 차감 후 변경된 current_point가 들어갑니다.
            "price": PRICE,
            "pay_success": pay_success,
            "msg": msg
        }
    )

# ==========================================
# 1. 포인트 충전 팝업 페이지 오픈 (/charge)
# ==========================================
@router.app.get("/charge", response_class=HTMLResponse)
async def charge_popup(
    request: Request,
    user_email: Optional[str] = Cookie(None),
    db=Depends(get_db)
):
    # 로그인 체크
    current_user = get_current_user(user_email, db) if user_email else None
    if not current_user:
        return HTMLResponse(
            "<script>alert('로그인이 필요한 서비스입니다.'); window.close();</script>"
        )

    # 현재 보유 포인트 조회
    current_point = getattr(current_user, 'current_point', 0) if hasattr(current_user, 'current_point') else current_user.get('current_point', 0)

    return templates.TemplateResponse(
        request=request,
        name="charge.html",
        context={
            "user_email": user_email,
            "user_point": int(current_point or 0)
        }
    )


# ==========================================
# 2. 포인트 충전 성공 처리 (/charge/success)
# ==========================================
@router.app.get("/charge/success", response_class=HTMLResponse)
async def charge_success(
    request: Request,
    user_email: str,
    amount: int,
    orderId: str,
    paymentKey: str = "",
    db=Depends(get_db)
):
    cursor = None
    try:
        cursor = db.cursor()

        # 1. 기존 사용자의 현재 포인트 조회
        cursor.execute("SELECT current_point FROM nasagung_users WHERE email = %s", (user_email,))
        row = cursor.fetchone()
        
        current_p = 0
        if row:
            current_p = row[0] if isinstance(row, tuple) else row.get('current_point', 0)

        new_point = int(current_p or 0) + int(amount)

        # 2. nasagung_users 포인트 업데이트
        update_sql = "UPDATE nasagung_users SET current_point = %s WHERE email = %s"
        cursor.execute(update_sql, (new_point, user_email))

        # 3. point_history 내역 추가
        target_id = int(time.time())
        insert_history_sql = """
            INSERT INTO point_history (email, type, amount, description, target_id, created_at)
            VALUES (%s, 'charge', %s, '포인트 충전', %s, NOW())
        """
        cursor.execute(insert_history_sql, (user_email, amount, target_id))

        db.commit()

        # 4. 결제 성공 시 부모 창 포인트 업데이트 및 팝업 닫기 Script 반환
        return HTMLResponse(f"""
            <script>
                alert('{amount:,} 포인트 충전이 완료되었습니다.');
                if (window.opener && !window.opener.closed) {{
                    if (typeof window.opener.updateTopPoint === 'function') {{
                        window.opener.updateTopPoint({new_point});
                    }} else {{
                        window.opener.location.reload();
                    }}
                }}
                window.close();
            </script>
        """)

    except Exception as e:
        db.rollback()
        logger.error(f"Charge Success Processing Error: {str(e)}")
        return HTMLResponse("<script>alert('포인트 적립 처리 중 오류가 발생했습니다.'); window.close();</script>")
    finally:
        if cursor:
            cursor.close()

