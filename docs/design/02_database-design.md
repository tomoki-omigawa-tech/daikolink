# DB設計書

## ER図
```mermaid
erDiagram
    users ||--o{ shifts : "担当する"
    time_slots ||--o{ shifts : "コマ"
    shifts ||--o{ substitute_requests : "代講依頼"
    users ||--o{ substitute_requests : "依頼する"
    users |o--o{ substitute_requests : "代講者になる"
    substitute_requests ||--o{ substitute_applications : "応募"
    users ||--o{ substitute_applications : "応募する"
    substitute_requests ||--o{ request_comments : "コメント"
    users ||--o{ request_comments : "書く"
    users ||--o{ user_subject : ""
    subjects ||--o{ user_subject : ""

    users {
        bigint id PK
        varchar name
        varchar email UK
        varchar password
        enum role
        boolean is_active
    }
    time_slots {
        bigint id PK
        varchar name
        time start_time
        time end_time
        int sort_order
    }
    shifts {
        bigint id PK
        bigint user_id FK
        date date
        bigint time_slot_id FK
    }
    substitute_requests {
        bigint id PK
        bigint shift_id FK
        bigint requester_id FK
        bigint substitute_id FK
        enum status
        varchar subject
        varchar grade
        varchar reason
        text handover_note
        timestamp confirmed_at
    }
    substitute_applications {
        bigint id PK
        bigint substitute_request_id FK
        bigint applicant_id FK
        enum status
    }
    request_comments {
        bigint id PK
        bigint substitute_request_id FK
        bigint user_id FK
        text body
    }
    subjects {
        bigint id PK
        varchar name UK
    }
    user_subject {
        bigint user_id FK
        bigint subject_id FK
    }
```

## テーブル一覧
| テーブル | 内容 | 優先度 |
|---|---|---|
| users | 講師・スタッフ（role で区別） | Must |
| time_slots | コマの定義（Seederで固定データ） | Must |
| shifts | 日付 × コマ × 担当講師 | Must |
| substitute_requests | 代講依頼 | Must |
| substitute_applications | 応募 | Must |
| request_comments | 依頼ごとのコメント | Should |
| subjects / user_subject | 科目と講師の担当科目 | Should |

※ Laravel 標準の sessions、password_reset_tokens、jobs、failed_jobs、cache も使用する。
※ 生徒の個人情報を保存するテーブルは作らない。

## テーブル定義

### users（講師・スタッフ）
| カラム名 | 型 | NULL | デフォルト | 説明 |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | ユーザーID（PK） |
| name | VARCHAR(50) | NO | - | 氏名 |
| email | VARCHAR(100) | NO | - | メールアドレス（通知先） |
| email_verified_at | TIMESTAMP | YES | NULL | メール確認日時 |
| password | VARCHAR(255) | NO | - | パスワード（ハッシュ） |
| role | ENUM('teacher','staff') | NO | 'teacher' | ロール |
| is_active | BOOLEAN | NO | true | 在籍中か（退職者は false にしてログイン不可・通知対象外） |
| remember_token | VARCHAR(100) | YES | NULL | ログイン保持 |
| created_at / updated_at | TIMESTAMP | YES | NULL | 作成・更新日時 |

- インデックス：PRIMARY KEY (id)、UNIQUE (email)、INDEX (role, is_active)

### time_slots（コマ）
| カラム名 | 型 | NULL | デフォルト | 説明 |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | コマID（PK） |
| name | VARCHAR(20) | NO | - | 表示名（例：1限） |
| start_time | TIME | NO | - | 開始時刻（例：16:00） |
| end_time | TIME | NO | - | 終了時刻（例：17:20） |
| sort_order | TINYINT UNSIGNED | NO | 0 | 表示順 |
| created_at / updated_at | TIMESTAMP | YES | NULL | 作成・更新日時 |

- インデックス：PRIMARY KEY (id)、UNIQUE (name)
- データは Seeder で投入（管理画面は Could）

### shifts（シフト）
| カラム名 | 型 | NULL | デフォルト | 説明 |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | シフトID（PK） |
| user_id | BIGINT UNSIGNED | NO | - | 担当講師ID（FK）。代講確定時に代講者へ更新 |
| date | DATE | NO | - | 授業日 |
| time_slot_id | BIGINT UNSIGNED | NO | - | コマID（FK） |
| created_at / updated_at | TIMESTAMP | YES | NULL | 作成・更新日時 |

- インデックス：PRIMARY KEY (id)、UNIQUE (user_id, date, time_slot_id)、INDEX (date, time_slot_id)
- 外部キー：user_id → users(id) ON DELETE RESTRICT、time_slot_id → time_slots(id) ON DELETE RESTRICT
- UNIQUE (user_id, date, time_slot_id) により、同じ講師の同時刻の二重シフトをDBレベルで防ぐ

### substitute_requests（代講依頼）
| カラム名 | 型 | NULL | デフォルト | 説明 |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | 依頼ID（PK） |
| shift_id | BIGINT UNSIGNED | NO | - | 対象シフトID（FK） |
| requester_id | BIGINT UNSIGNED | NO | - | 依頼者ID（FK） |
| substitute_id | BIGINT UNSIGNED | YES | NULL | 確定した代講者ID（FK） |
| status | ENUM('open','applied','confirmed','cancelled','expired') | NO | 'open' | 状態 |
| subject | VARCHAR(30) | YES | NULL | 科目（Should） |
| grade | VARCHAR(20) | YES | NULL | 学年（Should） |
| reason | VARCHAR(255) | YES | NULL | 理由 |
| handover_note | TEXT | NO | - | 引き継ぎメモ |
| confirmed_at | TIMESTAMP | YES | NULL | 確定日時（確定までの時間の計測に使用） |
| staff_notified_at | TIMESTAMP | YES | NULL | 正午の未確定通知を送った日時（Should） |
| created_at / updated_at | TIMESTAMP | YES | NULL | 作成・更新日時 |

- インデックス：PRIMARY KEY (id)、INDEX (shift_id, status)、INDEX (status, created_at)、INDEX (requester_id)
- 外部キー：shift_id → shifts(id) ON DELETE CASCADE、requester_id → users(id) ON DELETE RESTRICT、substitute_id → users(id) ON DELETE SET NULL
- 状態の意味：open＝募集中、applied＝応募あり、confirmed＝確定、cancelled＝取消、expired＝不成立

### substitute_applications（応募）
| カラム名 | 型 | NULL | デフォルト | 説明 |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | 応募ID（PK） |
| substitute_request_id | BIGINT UNSIGNED | NO | - | 依頼ID（FK） |
| applicant_id | BIGINT UNSIGNED | NO | - | 応募者ID（FK） |
| status | ENUM('pending','accepted','rejected','withdrawn') | NO | 'pending' | 応募中・採用・見送り・取り消し |
| created_at / updated_at | TIMESTAMP | YES | NULL | 作成・更新日時 |

- インデックス：PRIMARY KEY (id)、UNIQUE (substitute_request_id, applicant_id)、INDEX (applicant_id)
- 外部キー：substitute_request_id → substitute_requests(id) ON DELETE CASCADE、applicant_id → users(id) ON DELETE CASCADE
- UNIQUE により同じ依頼への重複応募を防ぐ（取り消し後の再応募は status を pending に戻す）

### request_comments（コメント・Should）
| カラム名 | 型 | NULL | デフォルト | 説明 |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | コメントID（PK） |
| substitute_request_id | BIGINT UNSIGNED | NO | - | 依頼ID（FK） |
| user_id | BIGINT UNSIGNED | NO | - | 投稿者ID（FK） |
| body | TEXT | NO | - | 本文（1000文字以内） |
| created_at / updated_at | TIMESTAMP | YES | NULL | 作成・更新日時 |

- インデックス：PRIMARY KEY (id)、INDEX (substitute_request_id, created_at)
- 外部キー：substitute_request_id → substitute_requests(id) ON DELETE CASCADE、user_id → users(id) ON DELETE CASCADE

### subjects / user_subject（担当科目・Should）
| テーブル | カラム | 説明 |
|---|---|---|
| subjects | id (PK)、name VARCHAR(30) UNIQUE、timestamps | 科目マスタ（Seeder） |
| user_subject | user_id (FK)、subject_id (FK)、PRIMARY KEY (user_id, subject_id) | 講師が担当できる科目 |

## データ整合性の設計
| ルール | 実現方法 |
|---|---|
| 同じ講師の同時刻の二重シフトを防ぐ | shifts の UNIQUE (user_id, date, time_slot_id) |
| 同じ依頼を二重に確定しない | 確定処理をトランザクション内で行い、依頼行を `lockForUpdate()` で取得して status を再確認 |
| 同じコマに未確定の依頼を重複作成しない | 作成時にトランザクション内で shift 行をロックし、status が open / applied の依頼がないか確認 |
| 応募者が同時刻に別のシフトを持っていない | 応募時と確定時の両方で shifts を確認（確定時は UNIQUE 制約が最後の砦） |
| 確定時のシフト更新 | 同じトランザクション内で shifts.user_id を代講者に更新し、他の応募を rejected に変更 |
| 授業開始後の不成立 | スケジューラで5分ごとに、開始時刻を過ぎた open / applied の依頼を expired に更新 |

## 全体シフト表のN+1対策
日付で shifts を取得する際に `with(['user', 'timeSlot'])` で一括取得し、空いている講師は「在籍中の講師一覧 − そのコマのシフト担当者」をアプリ側で計算する（クエリ数は日付あたり固定）。
