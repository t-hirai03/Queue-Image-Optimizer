<?php
/**
 * プラグインのディアクティベーション処理
 *
 * @package QueueImageOptimizer
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ディアクティベーションクラス
 */
class QIO_Deactivator {

	/**
	 * ディアクティベーション時の処理
	 */
	public static function deactivate() {
		// スケジュールされたアクションをクリア
		self::clear_scheduled_actions();

		// 進捗状態をリセット
		self::reset_progress();

		// リライトルールをフラッシュ
		flush_rewrite_rules();
	}

	/**
	 * スケジュールされたアクションをクリア
	 */
	private static function clear_scheduled_actions() {
		// Action Scheduler
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'qio_process_batch' );
			as_unschedule_all_actions( 'qio_process_single' );
		}

		// WP-Cron
		wp_clear_scheduled_hook( 'qio_cron_process_batch' );
		wp_clear_scheduled_hook( 'qio_cron_process_single' );
	}

	/**
	 * 進捗状態をリセット
	 */
	private static function reset_progress() {
		update_option(
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
