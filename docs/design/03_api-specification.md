# API仕様書

## 共通事項
- ベースURL：`/api`
- 認証：すべて必要（Laravel Sanctum のセッション認証。ログイン済みブラウザから CSRF トークン付きで呼び出す）
- 形式：リクエスト・レスポンスとも JSON。日時は ISO 8601（JST）
- 認可：Policy で制御。他人の依頼の操作は 403、スタッフ用は role=staff のみ

## エンドポイント一覧
| No | メソッド | パス | 説明 | ロール | 優先度 |
|---|---|---|---|---|---|
| 1 | GET | /api/shifts | 全体シフト表（日付指定） | 全員 | Must |
| 2 | GET | /api/me/shifts | 自分のシフト（週単位） | 講師 | Must |
| 3 | POST | /api/substitute-requests | 代講依頼の作成 | 講師 | Must |
| 4 | GET | /api/substitute-requests | 募集中の代講一覧 | 講師 | Must |
| 5 | GET | /api/me/substitute-requests | 自分の代講依頼一覧 | 講師 | Must |
| 6 | GET | /api/substitute-requests/{id} | 代講依頼の詳細（応募者を含む） | 講師 | Must |
| 7 | POST | /api/substitute-requests/{id}/applications | 応募 | 講師 | Must |
| 8 | DELETE | /api/applications/{id} | 応募の取り消し | 講師 | Must |
| 9 | POST | /api/substitute-requests/{id}/confirm | 代講者の確定 | 依頼者 | Must |
| 10 | POST | /api/substitute-requests/{id}/cancel | 依頼の取り消し | 依頼者 | Should |
| 11 | GET | /api/admin/substitute-requests | 代講依頼の状況一覧 | スタッフ | Must |
| 12 | POST | /api/admin/shifts | シフトの登録 | スタッフ | Must |
| 13 | PATCH / DELETE | /api/admin/shifts/{id} | シフトの編集・削除 | スタッフ | Must |
| 14 | GET / POST | /api/substitute-requests/{id}/comments | コメント一覧・投稿 | 依頼者・応募者 | Should |

---

## 1. GET /api/shifts（全体シフト表）
クエリパラメータ
- date (date, required)：表示する日付（例：2026-10-20）

レスポンス例（200 OK）
```json
{
  "data": {
    "date": "2026-10-20",
    "time_slots": [
      {
        "id": 1, "name": "1限", "start_time": "16:00", "end_time": "17:20",
        "teachers": [ { "id": 3, "name": "佐藤", "shift_id": 120, "has_open_request": true } ],
        "available_teachers": [ { "id": 5, "name": "鈴木" } ]
      }
    ]
  }
}
```

## 2. GET /api/me/shifts（自分のシフト）
クエリパラメータ
- week_start (date, optional)：週の開始日（デフォルト：今週の月曜）

レスポンス例（200 OK）
```json
{
  "data": [
    { "shift_id": 120, "date": "2026-10-20", "time_slot": { "id": 1, "name": "1限", "start_time": "16:00", "end_time": "17:20" }, "is_substitute": false, "request": { "id": 31, "status": "applied" } }
  ]
}
```

## 3. POST /api/substitute-requests（代講依頼の作成）
リクエストボディ
```json
{ "shift_id": 120, "reason": "大学の試験のため", "handover_note": "中2数学、教科書p.45の一次関数から", "subject": "数学", "grade": "中2" }
```

バリデーション
- shift_id：必須、自分が担当するシフトであること
- handover_note：必須、1〜1000文字
- reason：任意、255文字以内
- subject / grade：任意（Should）
- 授業開始時刻を過ぎていないこと、同じシフトに未確定（open / applied）の依頼がないこと

処理
- status を open で作成し、その日時に授業がない在籍中の講師へ通知メールをキュー投入

レスポンス例（201 Created）
```json
{ "data": { "id": 31, "status": "open", "shift": { "id": 120, "date": "2026-10-20", "time_slot": "1限" }, "notified_count": 9, "created_at": "2026-10-19T21:05:00+09:00" } }
```

エラー例（422 Unprocessable Entity）
```json
{ "message": "このコマにはすでに募集中の代講依頼があります。", "errors": { "shift_id": ["このコマにはすでに募集中の代講依頼があります。"] } }
```

## 4. GET /api/substitute-requests（募集中の代講一覧）
クエリパラメータ
- status (string, optional)：デフォルト open,applied
- page (integer, optional)：デフォルト 1（1ページ20件）

レスポンス例（200 OK）
```json
{
  "data": [
    { "id": 31, "date": "2026-10-20", "time_slot": { "name": "1限", "start_time": "16:00" }, "requester": { "id": 3, "name": "佐藤" }, "status": "applied", "applications_count": 2, "can_apply": true, "hours_until_start": 18 }
  ],
  "meta": { "current_page": 1, "total": 1, "per_page": 20 }
}
```

## 5. GET /api/me/substitute-requests（自分の依頼一覧）
レスポンス例（200 OK）
```json
{ "data": [ { "id": 31, "date": "2026-10-20", "time_slot": "1限", "status": "confirmed", "substitute": { "id": 5, "name": "鈴木" }, "confirmed_at": "2026-10-19T21:40:00+09:00" } ] }
```

## 6. GET /api/substitute-requests/{id}（詳細）
レスポンス例（200 OK・依頼者本人が見た場合）
```json
{
  "data": {
    "id": 31, "status": "applied", "date": "2026-10-20",
    "time_slot": { "name": "1限", "start_time": "16:00", "end_time": "17:20" },
    "requester": { "id": 3, "name": "佐藤" },
    "reason": "大学の試験のため", "handover_note": "中2数学、教科書p.45の一次関数から",
    "applications": [ { "id": 77, "applicant": { "id": 5, "name": "鈴木" }, "status": "pending", "created_at": "2026-10-19T21:20:00+09:00" } ],
    "can_confirm": true, "can_apply": false
  }
}
```
- 応募者リスト（applications）は依頼者とスタッフにのみ返す

エラー例（404 Not Found）
```json
{ "message": "代講依頼が見つかりません。" }
```

## 7. POST /api/substitute-requests/{id}/applications（応募）
リクエストボディ：なし

条件
- status が open / applied であること
- 自分の依頼でないこと、同じ日時・コマに自分のシフトがないこと

処理
- 応募を pending で作成し、依頼の status が open なら applied に変更

レスポンス例（201 Created）
```json
{ "data": { "id": 77, "substitute_request_id": 31, "status": "pending" } }
```

エラー例（422 Unprocessable Entity）
```json
{ "message": "同じ時間に担当授業があるため応募できません。" }
```

## 8. DELETE /api/applications/{id}（応募の取り消し）
条件：自分の応募であること、依頼が未確定であること
処理：status を withdrawn に変更。応募中の人が0人になったら依頼を open に戻す
レスポンス：204 No Content

## 9. POST /api/substitute-requests/{id}/confirm（代講者の確定）
リクエストボディ
```json
{ "application_id": 77 }
```

条件
- 依頼者本人であること（それ以外は 403）
- status が applied であること、application_id がこの依頼への pending の応募であること

処理（1つのトランザクション内）
1. 依頼行を `lockForUpdate()` で取得し、status を再確認
2. 依頼を confirmed、substitute_id・confirmed_at を設定
3. 選ばれた応募を accepted、他の pending を rejected に変更
4. shifts.user_id を代講者に更新
5. コミット後、依頼者・代講者・スタッフ・見送りになった応募者へメールをキュー投入

レスポンス例（200 OK）
```json
{ "data": { "id": 31, "status": "confirmed", "substitute": { "id": 5, "name": "鈴木" }, "confirmed_at": "2026-10-19T21:40:00+09:00" } }
```

エラー例（409 Conflict）
```json
{ "message": "この代講依頼はすでに確定済みです。" }
```

## 10. POST /api/substitute-requests/{id}/cancel（依頼の取り消し・Should）
条件：依頼者本人、status が open / applied
処理：status を cancelled、応募をすべて rejected にして応募者に通知
レスポンス例（200 OK）
```json
{ "data": { "id": 31, "status": "cancelled" } }
```

## 11. GET /api/admin/substitute-requests（状況一覧・スタッフ）
クエリパラメータ
- status (string, optional)：open / applied / confirmed / cancelled / expired（カンマ区切り可）
- date_from / date_to (date, optional)：授業日で絞り込み

レスポンス例（200 OK）
```json
{
  "data": [ { "id": 31, "date": "2026-10-20", "time_slot": "1限", "requester": "佐藤", "status": "applied", "applications_count": 2, "substitute": null, "is_today_unconfirmed": false } ],
  "meta": { "current_page": 1, "total": 1, "per_page": 50 }
}
```
- role が staff でない場合は 403

## 12. POST /api/admin/shifts（シフト登録・スタッフ）
リクエストボディ
```json
{ "user_id": 5, "date": "2026-10-20", "time_slot_id": 1 }
```
バリデーション：user_id は在籍中の講師、同じ講師・日付・コマのシフトがないこと
レスポンス例（201 Created）
```json
{ "data": { "id": 121, "user_id": 5, "date": "2026-10-20", "time_slot_id": 1 } }
```

## 13. PATCH / DELETE /api/admin/shifts/{id}（シフト編集・削除・スタッフ）
PATCH リクエストボディ（変更する項目のみ）
```json
{ "user_id": 6 }
```
- 未確定の代講依頼があるシフトは編集・削除不可（422）
- PATCH は 200 OK で更新後のシフト、DELETE は 204 No Content

## 14. GET / POST /api/substitute-requests/{id}/comments（コメント・Should）
POST リクエストボディ
```json
{ "body": "プリントは教室の棚の2段目にあります" }
```
- 閲覧・投稿できるのは依頼者・応募者・スタッフのみ。body は必須、1〜1000文字
- POST は 201 Created で作成したコメントを返す

## ステータスコード一覧
| コード | 意味 |
|---|---|
| 200 | 成功 |
| 201 | 作成成功 |
| 204 | 削除・取り消し成功 |
| 401 | 未ログイン |
| 403 | 権限なし（他人の依頼の確定、スタッフ用APIへのアクセスなど） |
| 404 | 存在しない |
| 409 | 状態の競合（確定済みの依頼への確定・応募） |
| 422 | バリデーションエラー |
| 429 | ログイン試行回数の超過 |

## メール通知一覧
| タイミング | 宛先 | 優先度 |
|---|---|---|
| 依頼作成時 | その日時に授業がない在籍中の講師 | Must |
| 確定時 | 依頼者・代講者・スタッフ（見送りの応募者にも結果を通知） | Must |
| 応募時 | 依頼者 | Should |
| 当日12:00に未確定 | スタッフ（当日分をまとめて1通） | Should |
