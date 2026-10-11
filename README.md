# DaikoLink（代講マッチング・シフト共有システム）

個別指導塾の講師が **全講師のシフトを見て、サイト上で代講の「依頼 → 応募 → 確定」までを講師どうしで完結できる** Webアプリです。
techmeets Week 22〜24 の総合プロジェクトとして開発します（この README は Week 21 の企画・設計段階のものです）。

## 解決したい課題
個別指導塾では、講師が授業に出られないとき、本人が直接の声かけや LINE で代わりの講師（代講者）を探しています。急な欠勤では見つからないことが多く、教室スタッフが当日に講師へ1人ずつ電話をかけていますが、つながらない・断られることが多く、代講者が見つかるまで数時間かかっています。

- 講師は他の講師のシフトが分からず、誰に頼めばよいか判断できない
- 声かけ・LINE・電話は1人ずつなので時間がかかる
- やり取りが散らばり、依頼状況や履歴が残らない

## 主な機能（MVP）
| 機能 | 内容 |
|---|---|
| 認証・ロール | 講師 / スタッフのログイン（アカウントはスタッフが発行） |
| シフト管理 | スタッフがシフトを登録・編集 |
| マイシフト・全体シフト表 | 自分のシフトと、日付×コマごとの全講師のシフト・空いている講師を表示 |
| 代講依頼 | 自分のコマから引き継ぎメモ付きで依頼を作成 |
| 募集一覧・応募 | 募集中の依頼に応募・応募の取り消し |
| 確定・シフト自動更新 | 依頼者が応募者から代講者を確定すると、シフトの担当者が自動で変わる |
| メール通知 | 依頼作成時（空いている講師へ）・確定時（当事者とスタッフへ） |
| スタッフ向け状況一覧 | 代講依頼の状態を一覧・絞り込み |

## 成功の基準（KPI）
- 依頼から確定までの時間（中央値）：数時間 → **1時間以内**
- スタッフの電話なしで確定した依頼の割合：**70%以上**
- 応募が1件以上あった依頼の割合：**90%以上**

## 技術スタック
PHP 8.3 / Laravel 12 / MySQL 8.0 / Blade + Tailwind CSS + Alpine.js / SendGrid / Docker / AWS EC2 / GitHub Actions
選定理由は [技術選定資料](docs/tech-stack.md) を参照。

## ドキュメント
| 分類 | ドキュメント |
|---|---|
| 要件定義 | [要件定義書（v1.2）](docs/requirements/requirements.md) — 概要・ユーザーストーリー20個・機能要件（MoSCoW）・非機能要件・制約 |
| 設計 | [設計書の概要とv1.2の変更点](docs/design/00_overview.md) |
| | [画面設計書](docs/design/01_screen-design.md) — 画面一覧・画面遷移図・ワイヤーフレーム7画面 |
| | [DB設計書](docs/design/02_database-design.md) — ER図・テーブル定義 |
| | [API仕様書](docs/design/03_api-specification.md) — エンドポイント13個 |
| | [インフラ構成図](docs/design/05_infrastructure.md) |
| 技術選定 | [技術選定資料](docs/tech-stack.md) |
| 開発計画 | [工数見積もり・スケジュール](docs/design/04_estimation.md) — Must 83時間＋バッファ |
| | [プロダクトバックログ](docs/plan/01_user-stories.md) |
| | [スプリント計画（Week 22〜24）](docs/plan/02_sprint-plan.md) |
| | [カンバンボードの運用](docs/plan/03_kanban.md) |
| | [KPI](docs/plan/05_kpi.md) |
| | [見積書](docs/plan/06_estimate.md) |

## 画面設計（Figma）
ワイヤーフレーム（講師画面6・スタッフ画面1）の書き出し画像は [画面設計書](docs/design/01_screen-design.md) に掲載しています。

## タスク管理
- Issue（23件：Must 17・Should 6）：[techmeets-month2 の Issue](https://github.com/tomoki-omigawa-tech/techmeets-month2/issues)
  - Week 20 で作成したため現在は month2 リポジトリにあります。Week 20 のレビュー完了後、このリポジトリへ移動します
- カンバンボード：[GitHub Projects「DaikoLink」](https://github.com/users/tomoki-omigawa-tech/projects/1)

## 開発スケジュール
| スプリント | 期間 | ゴール |
|---|---|---|
| Sprint 1 | Week 22 | ログインして全講師のシフトを確認し、自分のコマから代講依頼を出せる |
| Sprint 2 | Week 23 | 依頼 → 応募 → 確定 → シフト反映 → メール通知までが一通りつながる（MVP 機能完成） |
| Sprint 3 | Week 24 | テスト（カバレッジ70%）と CI を整え、本番 URL で MVP が動く |

## 作成者
小見川 朋輝
