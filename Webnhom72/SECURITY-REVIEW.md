# Bao cao kiem tra bao mat Webnhom72

## 1. Pham vi va ket luan

- Pham vi: xac thuc, phan quyen, session/cookie, CSRF, SQL injection,
  XSS, upload tep, MFA, cau hinh database va HTTP security headers.
- Ket luan: khong phat hien lo hong bao mat da duoc xac nhan trong cac luong
  ma nguon da kiem tra.
- Muc do tin cay: cao doi voi cac luong va tep da xem xet; cac rui ro phu
  thuoc moi truong trien khai duoc liet ke rieng ben duoi.

## 2. Findings

| # | Severity | File | Lines | Vulnerability | Confidence |
|---|----------|------|-------|---------------|------------|
| - | - | - | - | Khong co lo hong bao mat da duoc xac nhan | - |

## 3. Cac co che bao mat da xac minh

### Xac thuc va phan quyen

- Mat khau duoc xu ly bang `password_hash()` va `password_verify()` trong
  `Public_html/webbanhang/admin/authen/loginregister.php` va
  `Public_html/webbanhang/admin/authen/admin_login.php`.
- Session ID duoc thay doi sau khi dang nhap thanh cong trong cac luong dang
  nhap thuong, admin va MFA.
- Session duoc doi chieu lai voi database va kiem tra `updated_at` trong
  `Private/security/session.php`.
- Cac trang quan tri yeu cau session hop le va `role_id === 1`.
- MFA TOTP duoc ma hoa truoc khi luu va duoc kiem tra trong buoc xac thuc hai
  lop.
- Remember-token duoc luu dang hash, xoay token sau khi su dung va co thoi han
  30 ngay. Khi bat MFA, token cu bi thu hoi.

### CSRF

- Token duoc tao bang `random_bytes(32)`.
- Token duoc so sanh bang `hash_equals()`.
- Cac form thay doi trang thai da kiem tra, gom dang nhap, dang xuat, gio hang,
  thanh toan, binh luan, lien he va thao tac quan tri.

### SQL injection

- Cac truy van xac thuc, phan quyen, don hang, gio hang, binh luan, upload va
  thao tac quan tri su dung prepared statements.
- Luong tim kiem cong khai su dung escaping sau khi dat charset `utf8mb4`.
- API tim kiem truc tiep su dung prepared statement.

### XSS

- Du lieu tu nguoi dung va database thuong duoc encode bang
  `htmlspecialchars()`.
- Noi dung binh luan duoc encode truoc khi dung `nl2br()`.
- Ket qua tim kiem tren client duoc gan bang API text thay vi `innerHTML`.

### Upload tep

- Upload binh luan yeu cau dang nhap va CSRF.
- MIME type duoc kiem tra bang `finfo`, gioi han kich thuoc va allowlist.
- Ten tep duoc tao lai bang gia tri ngau nhien.
- Thu muc upload chan tep co phan mo rong PHP bang `.htaccess`.

### Session, HTTPS va HTTP headers

- Cookie session co `HttpOnly`, `SameSite=Lax` va `Secure` khi dung HTTPS.
- Host duoc kiem tra theo allowlist.
- Moi truong production bat buoc HTTPS theo cau hinh.
- CSP co `object-src 'none'`, `base-uri 'self'`, `form-action 'self'` va
  `frame-ancestors 'self'`.
- `.htaccess` bat `X-Content-Type-Options: nosniff`,
  `X-Frame-Options: SAMEORIGIN` va HSTS khi dung HTTPS.

## 4. Rui ro phu thuoc cau hinh trien khai

Day khong phai la loi da xac nhan tu ma nguon, nhung can kiem tra truoc khi
dua len production:

1. **Ket noi database tu xa**
   - `Public_html/webbanhang/database/config.php` cho phep host database tu
     bien moi truong.
   - Chua cau hinh TLS cho ket noi MySQL. Neu database nam tren mang khong tin
     cay, thong tin dang nhap va du lieu co the bi nghe trom.
   - Khuyen nghi: dung private network/VPN hoac bat TLS va chung chi MySQL.

2. **HTTPS va reverse proxy**
   - Can cau hinh dung `APP_ALLOWED_HOSTS`, `APP_FORCE_HTTPS` va
     `APP_TRUST_PROXY`.
   - Chi dat `APP_TRUST_PROXY=1` khi request chi di qua reverse proxy tin cay.

3. **Cau hinh MFA**
   - MFA phu thuoc `WEBBANHANG_MFA_KEY` dai it nhat 32 ky tu va bang
     `user_mfa` ton tai trong database.
   - Neu thieu cau hinh, tinh nang MFA khong hoat dong; day la rui ro kha dung,
     khong phai loi bypass da xac nhan.

4. **Sinh ID bang `MAX(id) + 1`**
   - Mot so luong tao du lieu su dung mau `MAX(id) + 1`, co the gay trung ID khi
     co nhieu request dong thoi.
   - Day la rui ro on dinh va toan ven du lieu, chua xac dinh duoc kha nang
     nang thanh loi phan quyen hoac lo du lieu.
   - Khuyen nghi: dung cot `AUTO_INCREMENT` va de database sinh ID.

## 5. Khuyen nghi van hanh

- Khong dua mat khau database, MFA key hoac SMTP secret vao source code.
- Gioi han quyen database theo dung schema can thiet.
- Sao luu database dinh ky va kiem tra kha nang phuc hoi.
- Theo doi log dang nhap that bai, MFA that bai va loi database.
- Chay lai kiem tra sau moi thay doi lon ve xac thuc, upload hoac phan quyen.
- Kiem tra header va cookie tren moi truong production bang trinh duyet hoac
  cong cu HTTP.

## 6. Cac tep chinh da kiem tra

- `Private/security/session.php`
- `Private/security/csrf.php`
- `Private/security/mfa.php`
- `Private/security/auth_throttle.php`
- `Public_html/webbanhang/database/config.php`
- `Public_html/webbanhang/database/dbhelper.php`
- `Public_html/webbanhang/admin/authen/loginregister.php`
- `Public_html/webbanhang/admin/authen/admin_login.php`
- `Public_html/webbanhang/admin/authen/mfa_verify.php`
- `Public_html/webbanhang/admin/authen/mfa_setup.php`
- `Public_html/webbanhang/utils/detail.php`
- `Public_html/webbanhang/utils/search_api.php`
- `Public_html/webbanhang/.htaccess`
