# Wikipedia Adminbacklog Telegram

把中文維基百科的管理員積壓工作（速刪、VIP、RFPP、各種申請……）同步到 Telegram 頻道：有新任務時發訊息，任務處理完就刪除或標記訊息，並在頻道描述顯示各類別的數量。

## 架構

```
觸發端（監看維基的頁面編輯與分類變動）
  └─ 依頁名或分類名執行 php sender.php -r <type>
       ├─ 抓維基頁面（action=raw）或分類成員（API）
       ├─ 和 DB 的 task 表比對：新任務 sendMessage、內容變了 editMessage、消失了 deleteMessage
       └─ 有變動時更新頻道描述
```

`sender.php` 主要由觸發端在事件發生時執行。另外由 cron 執行 `sender.php --stale` 作為保底：每次挑一個超過 `stalelimit`（預設 24 小時）沒有執行的類別來跑，讓執行失敗或頁面一直沒被編輯的類別，也能每天至少更新一次。每次只跑一個類別，是為了避開 Telegram 的發送上限。

### 程式

| 檔案 | 用途 |
|---|---|
| `sender.php` | 主程式。`-r <type>` 執行指定類別（可重複），`--stale` 執行最久沒跑的類別，`-f` 忽略鎖，`-d` 強制更新頻道描述，`--del <message_id>` 刪除一則訊息 |
| `renew.php` | 重發快超過 48 小時的訊息（Telegram bot 只能刪除 48 小時內的訊息）。建議每 5 分鐘執行 |
| `cleanlog.php` | 刪除超過 `logkeep` 的 `log`。建議每天執行 |
| `delete.php` | 手動刪除訊息：`php delete.php <id>` 或 `<起>-<迄>` |
| `function/wikipedia.php` | 各類別的抓取與解析（handler） |
| `function/telegram.php` | Telegram API：發送、編輯、刪除訊息與更新描述 |
| `function/function.php` | 鎖、`fetchUrl` 等共用函式 |

### 資料表

| 表 | 內容 |
|---|---|
| `task` | 頻道上目前的任務，每則訊息一列。`starttime` 為首次偵測時間，`date` 為訊息送出時間。沒有實際發送的任務（hidden 類別、超過 `csdbotlimit` 的速刪）`message_id` 為負數的佔位值，編輯與刪除時不會呼叫 Telegram API |
| `task_type` | 每個類別一列：`locked_at`（執行中的鎖）、`last_run`（最後執行結束）、`last_found`（最後偵測到新任務） |
| `log` | 觸發紀錄（`request: <type>`、`--stale` 的 `stale: <type>`）與錯誤（`send fail`、`edit fail`、中止原因等） |

## 安裝

1. `git submodule update --init`（`function/Mediawiki-urlencode`）
2. 匯入 `install/database.sql`
3. 建立 `config/config.php` 覆寫 `config/config.default.php` 的設定，至少要有 `token`、`chat_id` 與 DB 連線資訊
4. 設定排程：

   ```
   */5 * * * * php /path/to/renew.php
   3-59/10 * * * * php /path/to/sender.php --stale
   16 2 * * * php /path/to/cleanlog.php
   ```

   `--stale` 每 10 分鐘跑一個類別，一天 144 次，足夠讓所有類別每天輪到。分鐘錯開 `renew.php`，避免同時發訊息。

5. 讓觸發端在對應頁面或分類有變動時執行 `php sender.php -r <type>`

需要 PHP 8.0 以上（使用 `str_starts_with`），以及 `pdo_mysql`、`mbstring`。

## 設定

| 設定 | 說明 |
|---|---|
| `typelist` | 頻道描述中每個 `%name%` 由哪些類別加總。新增類別要記得加進對應群組與 `sum` |
| `ChatDescription` | 頻道描述範本 |
| `disabletype` | 停用的類別：照常解析，但不發訊息也不存 DB |
| `hiddentype` | 隱藏的類別：不發訊息，但存進 DB（只計入頻道描述的數量），`renew.php` 也會略過 |
| `renewlimit` | 訊息送出多久後由 `renew.php` 重發 |
| `locklimit` | 鎖的逾時秒數，超過就視為失效 |
| `stalelimit` | `--stale` 視為過久沒執行的秒數，預設 86400 |
| `csdbotlimit` | 同一個機器人掛的速刪最多發幾則 |
| `BadRequester` | VIP 中要忽略的提報人 |
| `EPFULL.ignore_pattern` | epfull 中要忽略的頁名 |

## 新增或修改類別

類別名稱和頁名都是精確比對，任何一處對不上都會被靜默略過，不會報錯。

- **新增類別**：
  1. 在觸發端加上頁名或分類名到 `-r <type>` 的對應
  2. 在 `sender.php` 加上 `if (in_array("<type>", $run))` 區塊
  3. 在 `config.default.php` 的 `typelist` 加入對應群組與 `sum`，需要的話也改 `ChatDescription`
- **頁面改名**：觸發端和 `sender.php` 兩邊都要改成新頁名。sender 用 `action=raw` 抓頁面，**不會跟隨重定向**，抓到舊頁名只會拿到 `#REDIRECT`。

## 維護注意事項

- **頁面格式會變。** 類別突然變成 0 筆時，先懷疑 regex。曾出現過的格式變化有：
  - `====[[User:X]]====` 加了空格
  - `*{{status` 變成 `* {{status`
  - VIP 從 `===` 改為 `==` 標題
  - `{{User|X}}` 改為 `{{vandal|X}}`
  - 機器人使用繁體「處理：」
- **regex 沒有 `/u` 修飾詞。** 不要在字元類別中放中文，`[处處]` 會逐 byte 比對。要寫成 `(?:处|處)`。
- **抓取失敗必須中止。** `fetchUrl()` 遇到網路錯誤或非 2xx 回應時，會呼叫 `abortRun()` 解鎖並結束。如果把錯誤頁當成內容解析，會得到空清單，進而刪光頻道上該類別的所有訊息。Wikimedia 要求 User-Agent，沒帶會回 403。
- **Telegram 的錯誤描述可能附加說明。** 判斷可忽略的錯誤時用前綴比對（`str_starts_with`），不要用精確比對。
- **Telegram 群組發送上限約每分鐘 20 則。** 超過會回 429，`sendMessage()` 目前不會重試，所以一次補發大量訊息時，會有一部分送不出去。

## 除錯

各類別最後觸發、執行與偵測到新任務的時間：

```sql
SELECT t.type, t.last_run, t.last_found, t.locked_at,
       (SELECT MAX(time) FROM log WHERE msg REGEXP CONCAT('^request: (.*, )?', t.type, '(,|$)')) AS last_request,
       (SELECT COUNT(*) FROM task WHERE task.type = t.type) AS tasks
FROM task_type t ORDER BY t.last_run;
```

最近的錯誤：

```sql
SELECT * FROM log WHERE msg NOT LIKE 'request:%' ORDER BY id DESC LIMIT 20;
```

試跑解析結果而不發出訊息：直接執行 `sender.php` 一定會發 Telegram 訊息。要試跑時，另寫一支腳本，載入 config、`function.php`、`database.php`、`wikipedia.php`，並自行定義假的 `sendMessage`、`editMessage`、`deleteMessage`（不要載入 `telegram.php`），再呼叫對應的 handler，例如：

```php
CategoryMemberHandler("unblock", "#封禁申訴", "Category:封禁及禁制申诉", "page");
(new VIPHandler())->run();   // class handler 也可以繼承後覆寫 send_message / delete_message
```

只想看 regex 比對結果時，可以直接呼叫 `PageMatchList($page, $regex)`。
