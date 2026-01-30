<?php
/**
 * 管理画面クラス
 *
 * @package QueueImageOptimizer
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 管理画面を管理するクラス
 */
class QIO_Admin {

	/**
	 * キュークラス
	 *
	 * @var QIO_Queue
	 */
	private $queue;

	/**
	 * コンストラクタ
	 *
	 * @param QIO_Queue $queue キュークラスのインスタンス
	 */
	public function __construct( QIO_Queue $queue ) {
		$this->queue = $queue;
		$this->init_hooks();
	}

	/**
	 * フックの初期化
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Ajax ハンドラ
		add_action( 'wp_ajax_qio_scan', array( $this, 'ajax_scan' ) );
		add_action( 'wp_ajax_qio_start', array( $this, 'ajax_start' ) );
		add_action( 'wp_ajax_qio_pause', array( $this, 'ajax_pause' ) );
		add_action( 'wp_ajax_qio_resume', array( $this, 'ajax_resume' ) );
		add_action( 'wp_ajax_qio_progress', array( $this, 'ajax_progress' ) );
		add_action( 'wp_ajax_qio_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_qio_clear_queue', array( $this, 'ajax_clear_queue' ) );
		add_action( 'wp_ajax_qio_reset_flags', array( $this, 'ajax_reset_flags' ) );
		add_action( 'wp_ajax_qio_process_now', array( $this, 'ajax_process_now' ) );
	}

	/**
	 * 管理メニューを追加
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Queue Image Optimizer', 'queue-image-optimizer' ),
			__( 'Queue Image Optimizer', 'queue-image-optimizer' ),
			'manage_options',
			'queue-image-optimizer',
			array( $this, 'render_dashboard' ),
			'dashicons-images-alt2',
			80
		);

		add_submenu_page(
			'queue-image-optimizer',
			__( 'ダッシュボード', 'queue-image-optimizer' ),
			__( 'ダッシュボード', 'queue-image-optimizer' ),
			'manage_options',
			'queue-image-optimizer',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'queue-image-optimizer',
			__( '設定', 'queue-image-optimizer' ),
			__( '設定', 'queue-image-optimizer' ),
			'manage_options',
			'queue-image-optimizer-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * スクリプトとスタイルを読み込み
	 *
	 * @param string $hook 現在のページフック
	 */
	public function enqueue_scripts( $hook ) {
		if ( ! in_array( $hook, array( 'toplevel_page_queue-image-optimizer', 'image-optimizer_page_queue-image-optimizer-settings' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'qio-admin',
			QIO_PLUGIN_URL . 'admin/css/qio-admin.css',
			array(),
			QIO_VERSION
		);

		wp_enqueue_script(
			'qio-admin',
			QIO_PLUGIN_URL . 'admin/js/qio-admin.js',
			array( 'jquery' ),
			QIO_VERSION,
			true
		);

		wp_localize_script(
			'qio-admin',
			'qioAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'qio_nonce' ),
				'strings' => array(
					'scanning'          => __( 'スキャン中...', 'queue-image-optimizer' ),
					'starting'          => __( '開始中...', 'queue-image-optimizer' ),
					'processing'        => __( '処理中...', 'queue-image-optimizer' ),
					'paused'            => __( '一時停止中', 'queue-image-optimizer' ),
					'completed'         => __( '完了', 'queue-image-optimizer' ),
					'error'             => __( 'エラーが発生しました', 'queue-image-optimizer' ),
					'confirm_clear'     => __( '処理待ちリストをリセットしますか？', 'queue-image-optimizer' ),
					'settings_saved'    => __( '設定を保存しました', 'queue-image-optimizer' ),
					/* translators: %d: number of images */
					'images_found'      => __( '%d 枚の未最適化画像が見つかりました', 'queue-image-optimizer' ),
					'no_images'         => __( '未最適化の画像はありません', 'queue-image-optimizer' ),
					/* translators: %s: estimated time */
					'estimated_time'    => __( '推定残り時間: %s', 'queue-image-optimizer' ),
				),
			)
		);
	}

	/**
	 * ダッシュボードを表示
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'アクセス権限がありません。', 'queue-image-optimizer' ) );
		}

		// 処理中以外の状態でキューにデータがあればクリア（古いデータ）
		$progress = get_option( 'qio_progress', array() );
		$status   = $progress['status'] ?? 'idle';
		if ( 'processing' !== $status ) {
			$this->queue->clear_queue( 'all', true );
		}

		include QIO_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * 設定画面を表示
	 */
	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'アクセス権限がありません。', 'queue-image-optimizer' ) );
		}

		include QIO_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Ajax: スキャン
	 */
	public function ajax_scan() {
		$this->verify_ajax_request();

		$result = $this->queue->scan_unoptimized_images();

		// スキャン結果をキューに追加
		if ( ! empty( $result['ids'] ) ) {
			$added = $this->queue->add_to_queue( $result['ids'] );
			$result['queued'] = $added;
		}

		wp_send_json_success( $result );
	}

	/**
	 * Ajax: 処理開始
	 */
	public function ajax_start() {
		$this->verify_ajax_request();

		$started = $this->queue->start_processing();

		if ( $started ) {
			wp_send_json_success( array( 'message' => __( '処理を開始しました', 'queue-image-optimizer' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( '既に処理中です', 'queue-image-optimizer' ) ) );
		}
	}

	/**
	 * Ajax: 一時停止
	 */
	public function ajax_pause() {
		$this->verify_ajax_request();

		$this->queue->pause_processing();
		wp_send_json_success( array( 'message' => __( '処理を一時停止しました', 'queue-image-optimizer' ) ) );
	}

	/**
	 * Ajax: 再開
	 */
	public function ajax_resume() {
		$this->verify_ajax_request();

		$resumed = $this->queue->resume_processing();

		if ( $resumed ) {
			wp_send_json_success( array( 'message' => __( '処理を再開しました', 'queue-image-optimizer' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( '再開できません', 'queue-image-optimizer' ) ) );
		}
	}

	/**
	 * Ajax: 進捗取得
	 */
	public function ajax_progress() {
		$this->verify_ajax_request();

		$progress   = $this->queue->get_progress();
		$statistics = $this->queue->get_statistics();

		wp_send_json_success(
			array(
				'progress'   => $progress,
				'statistics' => $statistics,
			)
		);
	}

	/**
	 * Ajax: 設定保存
	 */
	public function ajax_save_settings() {
		$this->verify_ajax_request();

		$settings = array(
			'jpeg_quality'       => min( 100, max( 1, absint( $_POST['jpeg_quality'] ?? 82 ) ) ),
			'png_compression'    => min( 9, max( 0, absint( $_POST['png_compression'] ?? 6 ) ) ),
			'auto_optimize'      => ! empty( $_POST['auto_optimize'] ),
			'backup_enabled'     => ! empty( $_POST['backup_enabled'] ),
			'include_thumbnails' => ! empty( $_POST['include_thumbnails'] ),
		);

		update_option( 'qio_settings', $settings );

		wp_send_json_success( array( 'message' => __( '設定を保存しました', 'queue-image-optimizer' ) ) );
	}

	/**
	 * Ajax: キュークリア
	 */
	public function ajax_clear_queue() {
		$this->verify_ajax_request();

		$status  = sanitize_text_field( wp_unslash( $_POST['status'] ?? 'all' ) );
		$deleted = $this->queue->clear_queue( $status );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: deleted count */
					__( '%d 件の処理待ちをリセットしました', 'queue-image-optimizer' ),
					$deleted
				),
			)
		);
	}

	/**
	 * Ajax: 最適化フラグリセット
	 */
	public function ajax_reset_flags() {
		$this->verify_ajax_request();

		global $wpdb;

		// 最適化済みフラグを削除
		$deleted = $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_qio_%'" );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: deleted count */
					__( '%d 件のフラグをリセットしました', 'queue-image-optimizer' ),
					$deleted
				),
			)
		);
	}

	/**
	 * Ajax: 即座にバッチ処理を実行（Ajax連続実行用）
	 */
	public function ajax_process_now() {
		$this->verify_ajax_request();

		$progress = get_option( 'qio_progress' );

		if ( 'processing' !== $progress['status'] ) {
			wp_send_json_error( array( 'message' => __( '処理中ではありません', 'queue-image-optimizer' ) ) );
		}

		// 直接バッチ処理を実行
		$this->queue->process_batch();

		// 最新の進捗を返す
		$new_progress = $this->queue->get_progress();
		$statistics   = $this->queue->get_statistics();

		wp_send_json_success(
			array(
				'progress'   => $new_progress,
				'statistics' => $statistics,
			)
		);
	}

	/**
	 * Ajaxリクエストを検証
	 */
	private function verify_ajax_request() {
		if ( ! check_ajax_referer( 'qio_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( '不正なリクエストです', 'queue-image-optimizer' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'アクセス権限がありません', 'queue-image-optimizer' ) ) );
		}
	}

	/**
	 * バイト数を人間が読める形式に変換
	 *
	 * @param int $bytes バイト数
	 * @return string
	 */
	public static function format_bytes( $bytes ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );

		$bytes = max( $bytes, 0 );
		$pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow   = min( $pow, count( $units ) - 1 );

		$bytes /= pow( 1024, $pow );

		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}
}
