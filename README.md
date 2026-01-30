# Queue Image Optimizer

大量の画像を安全に最適化できるWordPressプラグイン。非同期キュー処理により、サーバー負荷を分散し、バックグラウンドで画像圧縮を実行します。

## 機能

- 非同期バックグラウンド処理（Action Scheduler使用）
- 一括最適化機能
- 新規アップロード時の自動圧縮
- サムネイル対応
- 処理モード設定（安全/標準/高速/カスタム）
- オリジナル画像のバックアップ機能
- 進捗管理とリアルタイム更新

## 動作要件

- WordPress 5.0以上
- PHP 7.4以上
- Imagick または GD Library

## インストール

### 1. Action Scheduler のインストール

このプラグインは [Action Scheduler](https://github.com/woocommerce/action-scheduler) を使用します。

```bash
cd wp-content/plugins/queue-image-optimizer
mkdir -p vendor
cd vendor
curl -L https://github.com/woocommerce/action-scheduler/archive/refs/tags/3.7.4.tar.gz | tar xz
mv action-scheduler-3.7.4 action-scheduler
```

または Composer を使用:

```bash
cd wp-content/plugins/queue-image-optimizer
composer require woocommerce/action-scheduler
```

**注意**: Action Scheduler がインストールされていない場合、WP-Cron によるフォールバック処理が使用されます。

### 2. プラグインを有効化

WordPress管理画面からプラグインを有効化してください。

## 使い方

1. 管理画面 > Image Optimizer にアクセス
2. 「スキャン開始」ボタンをクリックして未最適化の画像を検出
3. 「圧縮を開始」ボタンをクリックしてバックグラウンド処理を開始
4. ページを閉じても処理は継続されます

## 設定

### 処理モード

| モード | 間隔 | バッチサイズ | 用途 |
|--------|------|------------|------|
| 安全モード | 5分 | 10枚 | 低スペックサーバー |
| 標準モード | 1分 | 20枚 | 一般的なVPS |
| 高速モード | 連続 | 50枚 | 高スペックサーバー |
| カスタム | 任意 | 任意 | 上級者向け |

### 圧縮品質

- JPEG: 1〜100（推奨: 75〜85）
- PNG: 0〜9（推奨: 6〜8）

## フック

### アクション

- `qio_process_batch` - バッチ処理実行時
- `qio_completed` - 全処理完了時

### フィルター

- `qio_jpeg_quality` - JPEG品質
- `qio_png_compression` - PNG圧縮レベル
- `qio_batch_size` - バッチサイズ変更
- `qio_skip_image` - 特定画像のスキップ

## ライセンス

GPL v2 or later
