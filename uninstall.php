<?php
/**
 * プラグインアンインストール時の処理
 *
 * @package QueueImageOptimizer
 */

// アンインストール以外からのアクセスを禁止
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// オプションを削除
delete_option( 'qio_version' );
delete_option( 'qio_settings' );
delete_option( 'qio_progress' );

// カスタムテーブルを削除
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}qio_queue" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}qio_stats" );

// postmetaを削除
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_qio_%'" );

// Action Scheduler のアクションを削除
if ( class_exists( 'ActionScheduler_DBStore' ) ) {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}actionscheduler_actions WHERE hook LIKE %s",
			'qio_%'
		)
	);
}

// バックアップディレクトリを削除（オプション）
// 安全のため、デフォルトではバックアップを残す
// 完全に削除したい場合は以下のコメントを解除
/*
$backup_dir = WP_CONTENT_DIR . '/qio-backup';
if ( is_dir( $backup_dir ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	global $wp_filesystem;
	$wp_filesystem->rmdir( $backup_dir, true );
}
*/
