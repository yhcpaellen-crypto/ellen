# 會計帳務基本版

PHP 8 + MySQL 8 的單一帳套、單一功能性幣別複式記帳系統，供 XAMPP 本機開發使用。

## 功能

- 使用者登入、角色權限（管理者、記帳人員、審核人員、唯讀）。
- 會計科目及會計期間管理。
- 傳票草稿、送審、核准／退回、過帳與已過帳沖銷。
- 借貸平衡及期間開放檢查。
- 總帳、科目明細、試算表、損益表、資產負債表；可匯出 CSV。
- 關鍵操作稽核紀錄。
- OpenAI API 語音轉錄及收據照片辨識；人工校對後只建立傳票草稿。

## XAMPP 本機安裝

1. 將 `accounting-app` 放在 XAMPP 的 `htdocs` 下，或為 `public` 目錄設定 Apache VirtualHost。網站根目錄應指向 `public`。
2. 由 phpMyAdmin 建立資料庫 `accounting_basic`，字元集選 `utf8mb4`，匯入 `database/schema.sql`。
3. 在 XAMPP MySQL root 有權限建立資料庫帳號的環境，匯入資料庫結構後執行 `php scripts/setup_db_account.php`。此腳本會建立最小權限的專用帳號並產生 `.env`；若 root 有密碼，請先設定 `MYSQL_ROOT_PASSWORD` 環境變數。正式環境請將設定檔放在網站根目錄之外，並限制讀取權限。
4. PHP 啟用 PDO MySQL、Session、mbstring。以瀏覽器開啟專案網址。
5. 在專案目錄執行 `php scripts/create_admin.php admin` 建立第一個管理者；命令會互動詢問密碼。不要在正式環境使用預設密碼。
6. 第一次登入後，新增使用者、會計期間及科目，然後輸入期初分錄。
7. 升級既有資料庫時，先備份後在 phpMyAdmin 選取 `accounting_basic`，匯入 `database/migration_ai_intake.sql`。全新安裝可直接匯入已含 AI 資料表的 `database/schema.sql`。
8. 在伺服器私有 `.env` 設定 `OPENAI_API_KEY` 及選用的 `OPENAI_TIMEOUT_SECONDS`；勿放入網站目錄、前端程式或版本控制。XAMPP PHP 需啟用 `curl`、`fileinfo`、`gd` 及 `mbstring`。登入管理者後到「組織設定 → AI 語音／照片辨識設定」開啟功能並設定模型、單檔大小、每日次數與來源保存期限。
9. 記帳人員從「AI 語音／照片入帳」錄音／上傳音檔或拍攝收據。每次送出須勾選傳輸告知；原始檔暫存在網站目錄外的私有 `storage/ai-intake`，確認建立草稿、取消或到期清理時刪除。OpenAI 端資料保留依 API 端點與組織設定而異，請先確認資料處理與費用政策。

## Hostinger 子目錄部署

- 將專案放在網域 `public_html/elle4336/` 後，網址為 `https://你的網域/elle4336/`。根目錄 `index.php` 會轉入 `public/index.php`；根目錄 `.htaccess` 禁止直接瀏覽程式、SQL、設定與暫存資料夾。請確認主機允許 `.htaccess` 規則並已啟用 HTTPS。
- 先在 hPanel 確認網站的 PHP 版本、MySQL 主機名稱及 SSH 存取狀態。SSH 若被停用或主機端拒絕連線，需先在 hPanel 開啟或使用主機提供的檔案管理器上傳。
- 主機資料庫與帳號名稱須照 hPanel 顯示填寫；資料庫主機 `DB_HOST` 也以 hPanel 顯示值為準。部署入口會優先從 `/home/帳號/.private/elle4336/.env` 載入設定；在該檔設定 `DB_NAME`、`DB_USER`、`DB_PASS`、`DB_HOST`、`DB_PORT`、`DB_TABLE_PREFIX=elle4336_`、`APP_DEBUG=0`、`SESSION_SECURE=1` 及 `AI_STORAGE_DIR=/home/帳號/.private/elle4336/ai-intake`。
- 建立空資料表後產生帶前綴的初始化檔：`php scripts/build_prefixed_schema.php elle4336_ > /tmp/elle4336_schema.sql`，再匯入該 SQL。資料表與外鍵名稱都使用此前綴，以隔離同資料庫中的其他系統。初始化管理者請以 CLI 腳本建立，勿把初始密碼寫入程式碼或 SQL 檔。
- 若要搬移既有帳務資料，先在來源資料庫製作並保存加密備份；匯入目標前確認資料庫為空，避免覆寫或混併不同帳套。正式上線前更換分享過的資料庫與 SSH 密碼，並完成登入、傳票、報表、權限、HTTPS 與備份還原檢查。

## 安全及上線注意事項

本專案是基本記帳工具骨架。正式使用前須設定 HTTPS、妥善保存備份、定期執行還原演練、確認所在地法規及報表格式，並由會計專業人員核對期初資料與會計政策。XAMPP 適用於開發及展示，不建議直接暴露到公網。附件上傳、多幣別、稅務申報、薪資、庫存及固定資產均不在此版本範圍。

AI 辨識僅作欄位輸入建議，可能錯讀日期、金額、對象或科目；作業人員須逐筆核對原始憑證及借貸科目。未設定 API key 或管理者關閉功能時，手動帳務仍可使用。AI 功能無法自動核准、過帳、付款或申報。

資料庫使用 InnoDB、utf8mb4 與 DECIMAL 金額。傳票過帳以資料庫交易執行，已過帳傳票不得直接編輯或刪除；更正請建立反向沖銷分錄。
