# 技術選定資料

## 選定の方針
1. **3週間・1人で MVP を完成させる**ことを最優先し、Week 1〜20 で実際に使った技術に揃える（新しく学ぶ技術は最小限にする）
2. コアバリュー「急な欠勤でも、講師どうしがサイト上で代講者を見つけて確定できる」に必要な **確定処理の整合性（トランザクション・ロック）** と **メール通知** を確実に作れること
3. 講師は主に **スマートフォン** で使うため、スマホ優先の画面を手早く作れること
4. **費用は既存の AWS 環境・独自ドメイン・無料枠の範囲**に収めること

## 使用技術一覧
| 分類 | 技術 | バージョン | 用途 |
|---|---|---|---|
| 言語 | PHP | 8.3 | アプリケーション |
| フレームワーク | Laravel | 12.x | 画面・API・認証・キュー・スケジューラ |
| 認証 | Laravel Breeze / Sanctum | — | ログイン・パスワードリセット、画面からの API 呼び出し（セッション認証） |
| DB | MySQL | 8.0 | アプリデータ、キュー（database ドライバ） |
| テンプレート | Blade | — | 画面 |
| CSS | Tailwind CSS | 3.x | スマホ優先のレスポンシブ UI |
| JavaScript | Alpine.js | 3.x | 応募・確定ボタンの二重送信防止、確認ダイアログ、API 呼び出し |
| ビルド | Vite | — | CSS・JS のビルド |
| メール | SendGrid | 無料枠 | 通知メールの送信（キューで非同期） |
| 開発環境 | Docker / docker-compose | — | nginx + PHP-FPM + MySQL + phpMyAdmin |
| 本番環境 | AWS EC2（t3.micro / Ubuntu 24.04）+ docker-compose | — | アプリ・キューワーカー・スケジューラの実行 |
| ドメイン・SSL | お名前.com / Let's Encrypt | — | 独自ドメイン（tomoki-omigawa.com）・HTTPS |
| バックアップ | AWS S3 | — | DB の日次バックアップ |
| CI/CD | GitHub Actions | — | lint（PHP CS Fixer・PHPStan）・test（PHPUnit）・SSH 自動デプロイ |
| テスト | PHPUnit | — | Feature・Unit テスト、カバレッジ 70% 以上 |
| 静的解析 | PHPStan + Larastan / PHP CS Fixer | — | 型チェック・PSR-12 |
| 開発ツール | Git / GitHub、GitHub Projects、Claude Code、Laravel Debugbar、Figma | — | バージョン管理・タスク管理・開発支援・N+1 の確認・画面設計 |

## 選定理由

### バックエンド：PHP 8.3 + Laravel 12
- Week 6〜17 で Laravel を使い、CRUD・認証・Policy・テスト・CI まで一通り経験しているため、3週間で MVP を作りきれる
- 代講の確定に必要な **トランザクション（`DB::transaction`）と行ロック（`lockForUpdate`）**、**Policy による認可**（確定は依頼者本人のみ）が標準機能で書ける
- 通知メールの **キュー**、不成立の自動更新・正午の未確定通知の **スケジューラ** も Laravel の標準機能だけで実現でき、別のサービスを増やさなくてよい
- PHP は CI（`composer.json` の platform）で 8.3 に固定しており、それに揃える

### 認証：Laravel Breeze（+ Sanctum）
- Week 8 で使用済み。ログイン・ログアウト・パスワードリセットがすぐ用意できる
- アカウントはスタッフが発行するため、新規登録のルートは無効化する
- 画面から JSON API を呼ぶ設計なので、同じドメインのセッション認証が使える Sanctum を使う（トークン管理が不要）

### DB：MySQL 8.0
- これまでの開発・本番（docker-compose）で使ってきた構成をそのまま使える
- InnoDB の行ロックで「同じ依頼の二重確定」「同じ講師の同時刻の二重シフト」を防げる。ユニーク制約（`shifts` の日付×コマ×講師）も併用する

### フロントエンド：Blade + Tailwind CSS + Alpine.js
- **React を使わない理由**：Week 10 で React は経験したが、今回は画面数が少なく（Must 10画面程度）、1人・3週間ではフロントとバックを分けるコストが大きい。Blade なら Laravel の中で完結する
- **Tailwind CSS**：Breeze に最初から入っており、スマホ優先のレスポンシブ（講師画面は 393px 幅基準）を素早く作れる
- **Alpine.js**：Breeze に最初から入っている。応募・確定ボタンの二重送信防止や確認ダイアログなど、少しだけ動きが必要な部分に使う。React ほど大きくなく、Blade に直接書ける
- Tailwind CSS・Alpine.js の詳しい使い方は Week 23 で学びながら実装する

### メール：SendGrid
- Week 12 で導入済み。無料枠（1日100通）で、講師約15人・1日数件の依頼なら十分足りる
- SPF・DKIM を設定し、通知メールが迷惑メールに入るリスクを下げる
- 送信はキューで非同期にし、依頼作成・確定ボタンの応答を遅らせない

### インフラ：Docker + AWS EC2（Render / Railway ではなく）
- 教材の推奨は Render / Railway だが、**Week 11〜17 で構築・運用してきた EC2 環境（docker-compose・HTTPS・独自ドメイン・GitHub Actions の SSH 自動デプロイ）を流用できる**ため、追加の学習コストがほとんどない
- 本アプリは **キューワーカーと cron（スケジューラ）が常時必要**。EC2 なら docker-compose にコンテナを追加するだけで動かせる。Render / Railway の無料枠ではワーカーや常時起動のプロセスに制限があり、費用がかかる可能性がある
- 開発環境と本番環境を同じ docker-compose 構成にでき、「ローカルでは動くのに本番で動かない」を減らせる
- 画像アップロードがないため、S3 は DB バックアップの保存にだけ使い、CloudFront は使わない

### CI/CD・品質：GitHub Actions + PHPUnit + PHPStan + PHP CS Fixer
- Week 14〜16 で作った lint・test・deploy のワークフローを流用する
- 非機能要件（カバレッジ 70%、PSR-12、PHPStan）をプルリクエストごとに自動で確認できる
- 完了の定義「lint・test の GitHub Actions が通る」をそのまま判定に使える

### タスク管理：GitHub Issues + GitHub Projects
- Issue・PR・コミットを紐づけられ、PR のマージで自動的に Done に移せる
- Week 20 で作ったバックログ（Must 17件・Should 6件）をそのまま使う

## 検討したが採用しなかった技術
| 技術 | 採用しなかった理由 |
|---|---|
| React / Inertia.js | 画面数が少なく、フロントを分けるとスケジュールに収まらない |
| Render / Railway | キューワーカー・cron の常時実行に制限があり、既存の EC2 環境を流用するほうが速い |
| LINE 通知（Messaging API） | 要件で Won't。まずはメールで仮説を検証する |
| Redis（キュー・キャッシュ） | 利用者が約15人と少なく、database ドライバのキューで十分。t3.micro のメモリも節約する |
| AWS RDS | 無料枠の期限と費用を考え、MySQL は docker-compose 内で動かし、S3 にバックアップする |

## 新しく学ぶ必要がある技術
| 技術 | いつ学ぶか |
|---|---|
| Tailwind CSS・Alpine.js の本格的な使い方 | Week 23（スタイリング・動的 UI） |
| Laravel のキュー（本番のワーカー常駐）・スケジューラ | Week 23（通知の実装時） |
| MySQL の行ロック（`lockForUpdate`）と同時実行テスト | Week 23（確定処理の実装時） |
