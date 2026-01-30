<?php
/**
 * ダッシュボード画面
 *
 * @package QueueImageOptimizer
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$progress   = $this->queue->get_progress();
$statistics = $this->queue->get_statistics();
?>
<div class="wrap qio-wrap">
	<h1><?php esc_html_e( 'Queue Image Optimizer', 'queue-image-optimizer' ); ?></h1>

	<!-- 統計サマリー（スキャン後に表示） -->
	<div class="qio-stats-grid" id="qio-stats-grid" <?php echo 'idle' === $progress['status'] ? 'style="display:none;"' : ''; ?>>
		<div class="qio-stat-box">
			<span class="qio-stat-number" id="qio-total-images"><?php echo esc_html( number_format( $statistics['total_images'] ) ); ?></span>
			<span class="qio-stat-label"><?php esc_html_e( '圧縮対象ファイル総数', 'queue-image-optimizer' ); ?></span>
		</div>
		<div class="qio-stat-box">
			<span class="qio-stat-number" id="qio-total-saved"><?php echo esc_html( QIO_Admin::format_bytes( $statistics['total_saved'] ) ); ?></span>
			<span class="qio-stat-label"><?php esc_html_e( '削減サイズ', 'queue-image-optimizer' ); ?></span>
		</div>
	</div>

	<!-- メインパネル -->
	<div class="qio-main-panel">
		<h2><?php esc_html_e( '圧縮処理進捗', 'queue-image-optimizer' ); ?></h2>

		<!-- 状態表示エリア -->
		<div id="qio-status-area" class="qio-status-area" data-status="<?php echo esc_attr( $progress['status'] ); ?>">

			<!-- アイドル状態 -->
			<div class="qio-status-idle" <?php echo 'idle' !== $progress['status'] ? 'style="display:none;"' : ''; ?>>
				<p><?php esc_html_e( '未圧縮の画像をスキャンして、圧縮処理を開始できます。', 'queue-image-optimizer' ); ?></p>
				<button type="button" class="button button-primary button-hero" id="qio-scan-btn">
					<?php esc_html_e( 'スキャン開始', 'queue-image-optimizer' ); ?>
				</button>
			</div>

			<!-- スキャン結果 -->
			<div class="qio-status-scanned" style="display:none;">
				<p class="qio-scan-result">
					<strong id="qio-scan-count">0</strong> <?php esc_html_e( '枚の未最適化画像が見つかりました', 'queue-image-optimizer' ); ?>
					<span id="qio-scan-size"></span>
				</p>
				<button type="button" class="button button-primary button-hero" id="qio-start-btn">
					<?php esc_html_e( '圧縮を開始', 'queue-image-optimizer' ); ?>
				</button>
				<button type="button" class="button" id="qio-rescan-btn">
					<?php esc_html_e( '再スキャン', 'queue-image-optimizer' ); ?>
				</button>
			</div>

			<!-- 処理中 -->
			<div class="qio-status-processing" <?php echo 'processing' !== $progress['status'] ? 'style="display:none;"' : ''; ?>>
				<div class="qio-progress-wrapper">
					<div class="qio-progress-bar">
						<div class="qio-progress-fill" id="qio-progress-fill" style="width: <?php echo esc_attr( $progress['total'] > 0 ? ( $progress['completed'] / $progress['total'] ) * 100 : 0 ); ?>%;"></div>
					</div>
					<div class="qio-progress-text">
						<span id="qio-progress-count"><?php echo esc_html( number_format( $progress['completed'] ) ); ?></span>
						/
						<span id="qio-progress-total"><?php echo esc_html( number_format( $progress['total'] ) ); ?></span>
						(<span id="qio-progress-percent"><?php echo esc_html( $progress['total'] > 0 ? round( ( $progress['completed'] / $progress['total'] ) * 100 ) : 0 ); ?></span>%)
					</div>
					<div class="qio-progress-estimate" id="qio-progress-estimate"></div>
				</div>
				<p class="qio-processing-note">
					<strong><?php esc_html_e( 'このページを開いている間は高速処理モードで実行されます。', 'queue-image-optimizer' ); ?></strong><br>
					<?php esc_html_e( 'ページを閉じても処理は継続しますが、速度は低下します。', 'queue-image-optimizer' ); ?>
				</p>
				<button type="button" class="button" id="qio-pause-btn">
					<?php esc_html_e( '一時停止', 'queue-image-optimizer' ); ?>
				</button>
			</div>

			<!-- 一時停止中 -->
			<div class="qio-status-paused" <?php echo 'paused' !== $progress['status'] ? 'style="display:none;"' : ''; ?>>
				<div class="qio-progress-wrapper">
					<div class="qio-progress-bar qio-progress-paused">
						<div class="qio-progress-fill" id="qio-progress-fill-paused" style="width: <?php echo esc_attr( $progress['total'] > 0 ? ( $progress['completed'] / $progress['total'] ) * 100 : 0 ); ?>%;"></div>
					</div>
					<div class="qio-progress-text">
						<span><?php echo esc_html( number_format( $progress['completed'] ) ); ?></span>
						/
						<span><?php echo esc_html( number_format( $progress['total'] ) ); ?></span>
						(<span><?php echo esc_html( $progress['total'] > 0 ? round( ( $progress['completed'] / $progress['total'] ) * 100 ) : 0 ); ?></span>%)
					</div>
				</div>
				<p class="qio-paused-note">
					<?php esc_html_e( '処理は一時停止中です。', 'queue-image-optimizer' ); ?>
				</p>
				<button type="button" class="button button-primary" id="qio-resume-btn">
					<?php esc_html_e( '再開', 'queue-image-optimizer' ); ?>
				</button>
				<button type="button" class="button" id="qio-clear-btn">
					<?php esc_html_e( '処理を中止してリセット', 'queue-image-optimizer' ); ?>
				</button>
			</div>

			<!-- 完了 -->
			<div class="qio-status-completed" <?php echo 'completed' !== $progress['status'] ? 'style="display:none;"' : ''; ?>>
				<div class="qio-completed-icon">&#10003;</div>
				<h3><?php esc_html_e( '最適化が完了しました', 'queue-image-optimizer' ); ?></h3>
				<div class="qio-completed-stats">
					<p>
						<?php esc_html_e( '処理画像数:', 'queue-image-optimizer' ); ?>
						<strong id="qio-completed-count"><?php echo esc_html( number_format( $progress['completed'] ) ); ?></strong>
					</p>
					<p>
						<?php esc_html_e( '削減サイズ:', 'queue-image-optimizer' ); ?>
						<strong id="qio-completed-saved"><?php echo esc_html( QIO_Admin::format_bytes( $progress['total_saved'] ) ); ?></strong>
					</p>
					<?php if ( $progress['failed'] > 0 ) : ?>
					<p class="qio-failed-note">
						<?php esc_html_e( '失敗:', 'queue-image-optimizer' ); ?>
						<strong><?php echo esc_html( number_format( $progress['failed'] ) ); ?></strong>
					</p>
					<?php endif; ?>
				</div>
				<button type="button" class="button button-primary" id="qio-new-scan-btn">
					<?php esc_html_e( '新たにスキャン', 'queue-image-optimizer' ); ?>
				</button>
			</div>

		</div>
	</div>

	<!-- ヘルプ情報 -->
	<div class="qio-help-panel">
		<h3><?php esc_html_e( '使い方', 'queue-image-optimizer' ); ?></h3>
		<ol>
			<li><?php esc_html_e( '「スキャン開始」ボタンをクリックして、未最適化の画像を検出します。', 'queue-image-optimizer' ); ?></li>
			<li><?php esc_html_e( '「圧縮を開始」ボタンをクリックすると、バックグラウンドで最適化処理が始まります。', 'queue-image-optimizer' ); ?></li>
			<li><?php esc_html_e( 'ページを開いている間は高速処理。閉じても低速で継続します。', 'queue-image-optimizer' ); ?></li>
		</ol>
	</div>
</div>
