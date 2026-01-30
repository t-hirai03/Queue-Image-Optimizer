<?php
/**
 * 設定画面
 *
 * @package QueueImageOptimizer
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = get_option( 'qio_settings', array() );

// デフォルト値
$defaults = array(
	'processing_mode'    => 'standard',
	'custom_interval'    => 1,
	'custom_batch_size'  => 20,
	'jpeg_quality'       => 82,
	'png_compression'    => 6,
	'auto_optimize'      => true,
	'backup_enabled'     => false,
	'include_thumbnails' => true,
);

$settings = wp_parse_args( $settings, $defaults );
?>
<div class="wrap qio-wrap">
	<h1><?php esc_html_e( '設定', 'queue-image-optimizer' ); ?></h1>

	<form id="qio-settings-form" class="qio-settings-form">
		<!-- 処理モード -->
		<div class="qio-settings-section">
			<h2><?php esc_html_e( '処理モード', 'queue-image-optimizer' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'モード選択', 'queue-image-optimizer' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="processing_mode" value="safe" <?php checked( $settings['processing_mode'], 'safe' ); ?>>
								<strong><?php esc_html_e( '安全モード', 'queue-image-optimizer' ); ?></strong>
								<span class="description"><?php esc_html_e( '5分間隔 / 10枚ずつ - 低スペックサーバー向け', 'queue-image-optimizer' ); ?></span>
							</label>
							<br>
							<label>
								<input type="radio" name="processing_mode" value="standard" <?php checked( $settings['processing_mode'], 'standard' ); ?>>
								<strong><?php esc_html_e( '標準モード', 'queue-image-optimizer' ); ?></strong>
								<span class="description"><?php esc_html_e( '1分間隔 / 20枚ずつ - 一般的なVPS向け', 'queue-image-optimizer' ); ?></span>
							</label>
							<br>
							<label>
								<input type="radio" name="processing_mode" value="fast" <?php checked( $settings['processing_mode'], 'fast' ); ?>>
								<strong><?php esc_html_e( '高速モード', 'queue-image-optimizer' ); ?></strong>
								<span class="description"><?php esc_html_e( '連続実行 / 200枚ずつ - 高スペックサーバー向け', 'queue-image-optimizer' ); ?></span>
							</label>
							<br>
							<label>
								<input type="radio" name="processing_mode" value="custom" <?php checked( $settings['processing_mode'], 'custom' ); ?>>
								<strong><?php esc_html_e( 'カスタム', 'queue-image-optimizer' ); ?></strong>
								<span class="description"><?php esc_html_e( '上級者向け', 'queue-image-optimizer' ); ?></span>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr class="qio-custom-settings" <?php echo 'custom' !== $settings['processing_mode'] ? 'style="display:none;"' : ''; ?>>
					<th scope="row"><?php esc_html_e( 'カスタム設定', 'queue-image-optimizer' ); ?></th>
					<td>
						<p>
							<label>
								<?php esc_html_e( '処理間隔:', 'queue-image-optimizer' ); ?>
								<input type="number" name="custom_interval" value="<?php echo esc_attr( $settings['custom_interval'] ); ?>" min="0" max="60" class="small-text">
								<?php esc_html_e( '分（0で連続実行）', 'queue-image-optimizer' ); ?>
							</label>
						</p>
						<p>
							<label>
								<?php esc_html_e( 'バッチサイズ:', 'queue-image-optimizer' ); ?>
								<input type="number" name="custom_batch_size" value="<?php echo esc_attr( $settings['custom_batch_size'] ); ?>" min="1" max="100" class="small-text">
								<?php esc_html_e( '枚', 'queue-image-optimizer' ); ?>
							</label>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- 圧縮品質 -->
		<div class="qio-settings-section">
			<h2><?php esc_html_e( '圧縮品質', 'queue-image-optimizer' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="jpeg_quality"><?php esc_html_e( 'JPEG品質', 'queue-image-optimizer' ); ?></label>
					</th>
					<td>
						<input type="range" name="jpeg_quality" id="jpeg_quality" value="<?php echo esc_attr( $settings['jpeg_quality'] ); ?>" min="1" max="100" class="qio-range">
						<span class="qio-range-value" id="jpeg_quality_value"><?php echo esc_html( $settings['jpeg_quality'] ); ?></span>
						<p class="description"><?php esc_html_e( '推奨: 75〜85。値が小さいほど圧縮率が高くなりますが、画質は低下します。', 'queue-image-optimizer' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="png_compression"><?php esc_html_e( 'PNG圧縮レベル', 'queue-image-optimizer' ); ?></label>
					</th>
					<td>
						<input type="range" name="png_compression" id="png_compression" value="<?php echo esc_attr( $settings['png_compression'] ); ?>" min="0" max="9" class="qio-range">
						<span class="qio-range-value" id="png_compression_value"><?php echo esc_html( $settings['png_compression'] ); ?></span>
						<p class="description"><?php esc_html_e( '推奨: 6〜8。値が大きいほど圧縮率が高くなります（0〜9）。', 'queue-image-optimizer' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- オプション -->
		<div class="qio-settings-section">
			<h2><?php esc_html_e( 'オプション', 'queue-image-optimizer' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( '自動最適化', 'queue-image-optimizer' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="auto_optimize" value="1" <?php checked( $settings['auto_optimize'] ); ?>>
							<?php esc_html_e( '新規アップロード時に自動で最適化する', 'queue-image-optimizer' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'サムネイル', 'queue-image-optimizer' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="include_thumbnails" value="1" <?php checked( $settings['include_thumbnails'] ); ?>>
							<?php esc_html_e( 'WordPressが生成するサムネイルも最適化対象に含める', 'queue-image-optimizer' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'バックアップ', 'queue-image-optimizer' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="backup_enabled" value="1" <?php checked( $settings['backup_enabled'] ); ?>>
							<?php esc_html_e( '圧縮前のオリジナル画像をバックアップする', 'queue-image-optimizer' ); ?>
						</label>
						<p class="description">
							<?php
							printf(
								/* translators: %s: backup directory path */
								esc_html__( 'バックアップ先: %s', 'queue-image-optimizer' ),
								'<code>wp-content/qio-backup/</code>'
							);
							?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary" id="qio-save-settings">
				<?php esc_html_e( '設定を保存', 'queue-image-optimizer' ); ?>
			</button>
			<span class="spinner"></span>
			<span class="qio-save-message" id="qio-save-message"></span>
		</p>
	</form>

	<!-- 危険ゾーン -->
	<div class="qio-settings-section qio-danger-zone">
		<h2><?php esc_html_e( '危険ゾーン', 'queue-image-optimizer' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( '処理待ちリスト', 'queue-image-optimizer' ); ?></th>
				<td>
					<button type="button" class="button" id="qio-clear-pending">
						<?php esc_html_e( '未処理の画像リストを削除', 'queue-image-optimizer' ); ?>
					</button>
					<button type="button" class="button" id="qio-clear-all">
						<?php esc_html_e( '全ての処理履歴を削除', 'queue-image-optimizer' ); ?>
					</button>
					<p class="description"><?php esc_html_e( 'スキャン結果や処理履歴がリセットされます。最適化済みの画像はそのままです。', 'queue-image-optimizer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( '最適化のやり直し', 'queue-image-optimizer' ); ?></th>
				<td>
					<button type="button" class="button" id="qio-reset-flags">
						<?php esc_html_e( '全画像を未最適化に戻す', 'queue-image-optimizer' ); ?>
					</button>
					<p class="description"><?php esc_html_e( '全ての画像を「未最適化」としてマークし直します。再スキャン後に再度最適化できます。', 'queue-image-optimizer' ); ?></p>
				</td>
			</tr>
		</table>
	</div>
</div>
