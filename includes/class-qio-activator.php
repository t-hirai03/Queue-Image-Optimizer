<?php
/**
 * プラグインのアクティベーション処理
 *
 * @package QueueImageOptimizer
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * アクティベーションクラス
 */
class QIO_Activator {

	/**
	 * アクティベーション時の処理
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
		self::create_backup_directory();

		// バージョン保存
		update_option( 'qio_version', QIO_VERSION );

		// リライトルールをフラッシュ
		flush_rewrite_rules();
	}

	/**
	 * カスタムテーブルの作成
	 */
	private static function create_tables() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'qio_queue';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			attachment_id BIGINT(20) UNSIGNED NOT NULL,
			file_path VARCHAR(500) NOT NULL,
			file_type VARCHAR(20) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			priority INT(11) NOT NULL DEFAULT 10,
			original_size BIGINT(20) UNSIGNED DEFAULT NULL,
			optimized_size BIGINT(20) UNSIGNED DEFAULT NULL,
			error_message TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			processed_at DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			KEY attachment_id (attachment_id),
			KEY status (status),
			KEY priority (priority),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// 統計テーブル
		$stats_table = $wpdb->prefix . 'qio_stats';
		$sql_stats   = "CREATE TABLE {$stats_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			date DATE NOT NULL,
			images_processed INT(11) NOT NULL DEFAULT 0,
			bytes_saved BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY date (date)
		) {$charset_collate};";

		dbDelta( $sql_stats );
	}

	/**
	 * デフォルトオプションの設定
	 */
	private static function set_default_options() {
		$default_options = array(
			'processing_mode'    => 'standard',
			'custom_interval'    => 1,
			'custom_batch_size'  => 20,
			'jpeg_quality'       => 82,
			'png_compression'    => 6,
			'auto_optimize'      => true,
			'backup_enabled'     => false,
			'include_thumbnails' => true,
		);

		// 既存のオプションがない場合のみ設定
		if ( false === get_option( 'qio_settings' ) ) {
			add_option( 'qio_settings', $default_options );
		}

		// 進捗状態の初期化
		if ( false === get_option( 'qio_progress' ) ) {
			add_option(
				'qio_progress',
				array(
					'status'           => 'idle',
					'total'            => 0,
					'processed'        => 0,
					'current_batch_id' => 0,
				)
			);
		}
	}

	/**
	 * バックアップディレクトリの作成
	 */
	private static function create_backup_directory() {
		$backup_dir = WP_CONTENT_DIR . '/qio-backup';

		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );

			// .htaccess でアクセス制限
			$htaccess_content = "Order Deny,Allow\nDeny from all";
			file_put_contents( $backup_dir . '/.htaccess', $htaccess_content );

			// index.php で直接アクセス防止
			file_put_contents( $backup_dir . '/index.php', '<?php // Silence is golden.' );
		}
	}
}
