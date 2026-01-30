<?php
/**
 * キュー管理クラス
 *
 * @package QueueImageOptimizer
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 非同期キュー処理を管理するクラス
 */
class QIO_Queue {

	/**
	 * 圧縮クラス
	 *
	 * @var QIO_Compressor
	 */
	private $compressor;

	/**
	 * 設定
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * 処理モード設定
	 *
	 * @var array
	 */
	private $mode_config = array(
		'safe'     => array(
			'interval'   => 300, // 5分
			'batch_size' => 10,
		),
		'standard' => array(
			'interval'   => 60, // 1分
			'batch_size' => 20,
		),
		'fast'     => array(
			'interval'   => 0, // 連続実行
			'batch_size' => 50,
		),
	);

	/**
	 * コンストラクタ
	 *
	 * @param QIO_Compressor $compressor 圧縮クラスのインスタンス
	 */
	public function __construct( QIO_Compressor $compressor ) {
		$this->compressor = $compressor;
		$this->settings   = get_option( 'qio_settings', array() );

		$this->init_hooks();
	}

	/**
	 * フックの初期化
	 */
	private function init_hooks() {
		// Action Scheduler または WP-Cron のアクション
		add_action( 'qio_process_batch', array( $this, 'process_batch' ) );
		add_action( 'qio_process_single', array( $this, 'process_single' ), 10, 1 );

		// WP-Cron フォールバック用
		add_action( 'qio_cron_process_batch', array( $this, 'process_batch' ) );
		add_action( 'qio_cron_process_single', array( $this, 'cron_process_single' ) );

		// 新規アップロード時の自動圧縮
		if ( $this->is_auto_optimize_enabled() ) {
			add_action( 'add_attachment', array( $this, 'handle_new_upload' ) );
		}
	}

	/**
	 * Action Scheduler が利用可能かチェック
	 *
	 * @return bool
	 */
	private function has_action_scheduler() {
		return function_exists( 'as_schedule_single_action' );
	}

	/**
	 * 自動最適化が有効かチェック
	 *
	 * @return bool
	 */
	private function is_auto_optimize_enabled() {
		return ! empty( $this->settings['auto_optimize'] );
	}

	/**
	 * サムネイルを含めるかチェック
	 *
	 * @return bool
	 */
	private function include_thumbnails() {
		return ! empty( $this->settings['include_thumbnails'] );
	}

	/**
	 * 現在のモード設定を取得
	 *
	 * @return array
	 */
	public function get_mode_settings() {
		$mode = isset( $this->settings['processing_mode'] ) ? $this->settings['processing_mode'] : 'standard';

		if ( 'custom' === $mode ) {
			return array(
				'interval'   => isset( $this->settings['custom_interval'] ) ? (int) $this->settings['custom_interval'] * 60 : 60,
				'batch_size' => isset( $this->settings['custom_batch_size'] ) ? (int) $this->settings['custom_batch_size'] : 20,
			);
		}

		return isset( $this->mode_config[ $mode ] ) ? $this->mode_config[ $mode ] : $this->mode_config['standard'];
	}

	/**
	 * 未最適化画像をスキャン
	 *
	 * @return array スキャン結果
	 */
	public function scan_unoptimized_images() {
		global $wpdb;

		$supported_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );

		// 最適化済みフラグがない画像を取得
		$query = $wpdb->prepare(
			"SELECT p.ID, p.post_mime_type
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_qio_optimized'
			WHERE p.post_type = 'attachment'
			AND p.post_mime_type IN (" . implode( ',', array_fill( 0, count( $supported_types ), '%s' ) ) . ')
			AND pm.meta_value IS NULL
			ORDER BY p.ID DESC',
			...$supported_types
		);

		$attachments = $wpdb->get_results( $query );

		$total_count = 0;
		$total_size  = 0;

		foreach ( $attachments as $attachment ) {
			$file_path = get_attached_file( $attachment->ID );
			if ( $file_path && file_exists( $file_path ) ) {
				$total_count++;
				$total_size += filesize( $file_path );

				// サムネイルもカウント
				if ( $this->include_thumbnails() ) {
					$metadata = wp_get_attachment_metadata( $attachment->ID );
					if ( ! empty( $metadata['sizes'] ) ) {
						$upload_dir = dirname( $file_path );
						foreach ( $metadata['sizes'] as $size ) {
							$thumb_path = $upload_dir . '/' . $size['file'];
							if ( file_exists( $thumb_path ) ) {
								$total_count++;
								$total_size += filesize( $thumb_path );
							}
						}
					}
				}
			}
		}

		return array(
			'count' => $total_count,
			'size'  => $total_size,
			'ids'   => wp_list_pluck( $attachments, 'ID' ),
		);
	}

	/**
	 * キューに画像を追加
	 *
	 * @param array $attachment_ids 添付ファイルIDの配列
	 * @return int 追加された件数
	 */
	public function add_to_queue( $attachment_ids ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'qio_queue';
		$added      = 0;

		foreach ( $attachment_ids as $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );

			if ( ! $file_path || ! file_exists( $file_path ) ) {
				continue;
			}

			// 既にキューにあるかチェック
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table_name} WHERE attachment_id = %d AND file_path = %s AND status IN ('pending', 'processing')",
					$attachment_id,
					$file_path
				)
			);

			if ( $exists ) {
				continue;
			}

			// オリジナル画像を追加
			$wpdb->insert(
				$table_name,
				array(
					'attachment_id' => $attachment_id,
					'file_path'     => $file_path,
					'file_type'     => 'original',
					'status'        => 'pending',
					'original_size' => filesize( $file_path ),
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s', '%d', '%s' )
			);
			$added++;

			// サムネイルも追加
			if ( $this->include_thumbnails() ) {
				$metadata = wp_get_attachment_metadata( $attachment_id );
				if ( ! empty( $metadata['sizes'] ) ) {
					$upload_dir = dirname( $file_path );
					foreach ( $metadata['sizes'] as $size_name => $size ) {
						$thumb_path = $upload_dir . '/' . $size['file'];
						if ( file_exists( $thumb_path ) ) {
							// サムネイルが既にキューにあるかチェック
							$thumb_exists = $wpdb->get_var(
								$wpdb->prepare(
									"SELECT id FROM {$table_name} WHERE attachment_id = %d AND file_path = %s AND status IN ('pending', 'processing')",
									$attachment_id,
									$thumb_path
								)
							);

							if ( ! $thumb_exists ) {
								$wpdb->insert(
									$table_name,
									array(
										'attachment_id' => $attachment_id,
										'file_path'     => $thumb_path,
										'file_type'     => $size_name,
										'status'        => 'pending',
										'original_size' => filesize( $thumb_path ),
										'created_at'    => current_time( 'mysql' ),
									),
									array( '%d', '%s', '%s', '%s', '%d', '%s' )
								);
								$added++;
							}
						}
					}
				}
			}
		}

		return $added;
	}

	/**
	 * 処理を開始
	 *
	 * @return bool
	 */
	public function start_processing() {
		$progress = get_option( 'qio_progress' );

		if ( 'processing' === $progress['status'] ) {
			return false; // 既に処理中
		}

		// 進捗を更新
		$total = $this->get_pending_count();
		update_option(
			'qio_progress',
			array(
				'status'           => 'processing',
				'total'            => $total,
				'processed'        => 0,
				'current_batch_id' => 0,
				'started_at'       => current_time( 'mysql' ),
			)
		);

		// 最初のバッチをスケジュール
		$this->schedule_next_batch();

		return true;
	}

	/**
	 * 処理を一時停止
	 *
	 * @return bool
	 */
	public function pause_processing() {
		$progress           = get_option( 'qio_progress' );
		$progress['status'] = 'paused';
		update_option( 'qio_progress', $progress );

		// スケジュールされたアクションをキャンセル
		if ( $this->has_action_scheduler() ) {
			as_unschedule_all_actions( 'qio_process_batch' );
		} else {
			wp_clear_scheduled_hook( 'qio_cron_process_batch' );
		}

		return true;
	}

	/**
	 * 処理を再開
	 *
	 * @return bool
	 */
	public function resume_processing() {
		$progress = get_option( 'qio_progress' );

		if ( 'paused' !== $progress['status'] ) {
			return false;
		}

		$progress['status'] = 'processing';
		update_option( 'qio_progress', $progress );

		$this->schedule_next_batch();

		return true;
	}

	/**
	 * 次のバッチをスケジュール
	 */
	private function schedule_next_batch() {
		$mode_settings = $this->get_mode_settings();
		$interval      = $mode_settings['interval'];

		if ( $this->has_action_scheduler() ) {
			// Action Scheduler を使用
			if ( as_has_scheduled_action( 'qio_process_batch' ) ) {
				return;
			}

			if ( 0 === $interval ) {
				as_enqueue_async_action( 'qio_process_batch' );
			} else {
				as_schedule_single_action( time() + $interval, 'qio_process_batch' );
			}
		} else {
			// WP-Cron フォールバック
			if ( wp_next_scheduled( 'qio_cron_process_batch' ) ) {
				return;
			}

			// WP-Cron は最小間隔が必要なため、0の場合は60秒に設定
			$cron_interval = max( 60, $interval );
			wp_schedule_single_event( time() + $cron_interval, 'qio_cron_process_batch' );
		}
	}

	/**
	 * バッチ処理を実行
	 */
	public function process_batch() {
		global $wpdb;

		$progress = get_option( 'qio_progress' );

		// 一時停止中なら処理しない
		if ( 'processing' !== $progress['status'] ) {
			return;
		}

		$table_name    = $wpdb->prefix . 'qio_queue';
		$mode_settings = $this->get_mode_settings();
		$batch_size    = apply_filters( 'qio_batch_size', $mode_settings['batch_size'] );

		// 処理対象を取得
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE status = 'pending' ORDER BY priority ASC, id ASC LIMIT %d",
				$batch_size
			)
		);

		if ( empty( $items ) ) {
			// 全て完了
			$this->complete_processing();
			return;
		}

		$processed = 0;

		foreach ( $items as $item ) {
			// ステータスを処理中に更新
			$wpdb->update(
				$table_name,
				array( 'status' => 'processing' ),
				array( 'id' => $item->id ),
				array( '%s' ),
				array( '%d' )
			);

			// 圧縮実行
			$result = $this->compressor->compress( $item->file_path );

			if ( is_wp_error( $result ) ) {
				// エラー
				$wpdb->update(
					$table_name,
					array(
						'status'        => 'failed',
						'error_message' => $result->get_error_message(),
						'processed_at'  => current_time( 'mysql' ),
					),
					array( 'id' => $item->id ),
					array( '%s', '%s', '%s' ),
					array( '%d' )
				);
			} else {
				// 成功
				$wpdb->update(
					$table_name,
					array(
						'status'         => 'completed',
						'optimized_size' => $result['optimized_size'],
						'processed_at'   => current_time( 'mysql' ),
					),
					array( 'id' => $item->id ),
					array( '%s', '%d', '%s' ),
					array( '%d' )
				);

				// 統計を更新
				$this->update_stats( $result['saved_bytes'] );

				$processed++;
			}

			// オリジナル画像が完了したらメタデータを更新
			if ( 'original' === $item->file_type && ! is_wp_error( $result ) ) {
				update_post_meta( $item->attachment_id, '_qio_optimized', 1 );
				update_post_meta( $item->attachment_id, '_qio_original_size', $item->original_size );
				update_post_meta( $item->attachment_id, '_qio_optimized_size', $result['optimized_size'] );
			}
		}

		// 進捗を更新
		$progress['processed'] += $processed;
		update_option( 'qio_progress', $progress );

		// 次のバッチをスケジュール
		$this->schedule_next_batch();
	}

	/**
	 * 処理完了
	 */
	private function complete_processing() {
		$progress               = get_option( 'qio_progress' );
		$progress['status']     = 'completed';
		$progress['completed_at'] = current_time( 'mysql' );
		update_option( 'qio_progress', $progress );

		// 完了アクションをトリガー
		do_action( 'qio_completed', $progress );
	}

	/**
	 * 統計を更新
	 *
	 * @param int $saved_bytes 削減バイト数
	 */
	private function update_stats( $saved_bytes ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'qio_stats';
		$today      = current_time( 'Y-m-d' );

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table_name} (date, images_processed, bytes_saved)
				VALUES (%s, 1, %d)
				ON DUPLICATE KEY UPDATE
				images_processed = images_processed + 1,
				bytes_saved = bytes_saved + %d",
				$today,
				$saved_bytes,
				$saved_bytes
			)
		);
	}

	/**
	 * 新規アップロード時の処理
	 *
	 * @param int $attachment_id 添付ファイルID
	 */
	public function handle_new_upload( $attachment_id ) {
		$mime_type = get_post_mime_type( $attachment_id );

		if ( ! in_array( $mime_type, array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ), true ) ) {
			return;
		}

		// キューに追加
		$this->add_to_queue( array( $attachment_id ) );

		// 即座に処理をスケジュール
		if ( $this->has_action_scheduler() ) {
			as_enqueue_async_action( 'qio_process_single', array( $attachment_id ) );
		} else {
			// WP-Cron フォールバック: attachment_id をオプションに保存してスケジュール
			$pending_singles = get_option( 'qio_pending_singles', array() );
			$pending_singles[] = $attachment_id;
			update_option( 'qio_pending_singles', array_unique( $pending_singles ) );

			if ( ! wp_next_scheduled( 'qio_cron_process_single' ) ) {
				wp_schedule_single_event( time() + 10, 'qio_cron_process_single' );
			}
		}
	}

	/**
	 * WP-Cron フォールバック: 単一画像を処理
	 */
	public function cron_process_single() {
		$pending_singles = get_option( 'qio_pending_singles', array() );

		if ( empty( $pending_singles ) ) {
			return;
		}

		// 最初の1件を処理
		$attachment_id = array_shift( $pending_singles );
		update_option( 'qio_pending_singles', $pending_singles );

		$this->process_single( $attachment_id );

		// まだ残りがあれば再スケジュール
		if ( ! empty( $pending_singles ) ) {
			wp_schedule_single_event( time() + 10, 'qio_cron_process_single' );
		}
	}

	/**
	 * 単一画像を処理
	 *
	 * @param int $attachment_id 添付ファイルID
	 */
	public function process_single( $attachment_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'qio_queue';

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE attachment_id = %d AND status = 'pending'",
				$attachment_id
			)
		);

		foreach ( $items as $item ) {
			$wpdb->update(
				$table_name,
				array( 'status' => 'processing' ),
				array( 'id' => $item->id ),
				array( '%s' ),
				array( '%d' )
			);

			$result = $this->compressor->compress( $item->file_path );

			if ( is_wp_error( $result ) ) {
				$wpdb->update(
					$table_name,
					array(
						'status'        => 'failed',
						'error_message' => $result->get_error_message(),
						'processed_at'  => current_time( 'mysql' ),
					),
					array( 'id' => $item->id ),
					array( '%s', '%s', '%s' ),
					array( '%d' )
				);
			} else {
				$wpdb->update(
					$table_name,
					array(
						'status'         => 'completed',
						'optimized_size' => $result['optimized_size'],
						'processed_at'   => current_time( 'mysql' ),
					),
					array( 'id' => $item->id ),
					array( '%s', '%d', '%s' ),
					array( '%d' )
				);

				$this->update_stats( $result['saved_bytes'] );

				if ( 'original' === $item->file_type ) {
					update_post_meta( $attachment_id, '_qio_optimized', 1 );
					update_post_meta( $attachment_id, '_qio_original_size', $item->original_size );
					update_post_meta( $attachment_id, '_qio_optimized_size', $result['optimized_size'] );
				}
			}
		}
	}

	/**
	 * 保留中の件数を取得
	 *
	 * @return int
	 */
	public function get_pending_count() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'qio_queue';
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending'" );
	}

	/**
	 * 進捗情報を取得
	 *
	 * @return array
	 */
	public function get_progress() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'qio_queue';
		$progress   = get_option( 'qio_progress' );

		// 実際の処理状況をDBから取得
		$stats = $wpdb->get_row(
			"SELECT
				COUNT(*) as total,
				SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
				SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
				SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
				SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
				SUM(COALESCE(original_size, 0)) as total_original_size,
				SUM(CASE WHEN status = 'completed' THEN COALESCE(optimized_size, 0) ELSE 0 END) as total_optimized_size,
				SUM(CASE WHEN status = 'completed' THEN COALESCE(original_size, 0) - COALESCE(optimized_size, 0) ELSE 0 END) as total_saved
			FROM {$table_name}"
		);

		return array(
			'status'               => $progress['status'] ?? 'idle',
			'total'                => (int) $stats->total,
			'completed'            => (int) $stats->completed,
			'failed'               => (int) $stats->failed,
			'pending'              => (int) $stats->pending,
			'processing'           => (int) $stats->processing,
			'total_original_size'  => (int) $stats->total_original_size,
			'total_optimized_size' => (int) $stats->total_optimized_size,
			'total_saved'          => (int) $stats->total_saved,
			'started_at'           => $progress['started_at'] ?? null,
			'completed_at'         => $progress['completed_at'] ?? null,
		);
	}

	/**
	 * 統計情報を取得
	 *
	 * @return array
	 */
	public function get_statistics() {
		global $wpdb;

		$stats_table = $wpdb->prefix . 'qio_stats';
		$queue_table = $wpdb->prefix . 'qio_queue';

		// 全体の統計
		$total_stats = $wpdb->get_row(
			"SELECT
				SUM(images_processed) as total_processed,
				SUM(bytes_saved) as total_saved
			FROM {$stats_table}"
		);

		// 今日の統計
		$today       = current_time( 'Y-m-d' );
		$today_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT images_processed, bytes_saved FROM {$stats_table} WHERE date = %s",
				$today
			)
		);

		// メディアライブラリの総画像数
		$total_images = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE post_type = 'attachment'
			AND post_mime_type IN ('image/jpeg', 'image/png', 'image/gif', 'image/webp')"
		);

		// 最適化済み画像数
		$optimized_images = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_qio_optimized' AND meta_value = '1'"
		);

		return array(
			'total_images'     => (int) $total_images,
			'optimized_images' => (int) $optimized_images,
			'total_processed'  => (int) ( $total_stats->total_processed ?? 0 ),
			'total_saved'      => (int) ( $total_stats->total_saved ?? 0 ),
			'today_processed'  => (int) ( $today_stats->images_processed ?? 0 ),
			'today_saved'      => (int) ( $today_stats->bytes_saved ?? 0 ),
		);
	}

	/**
	 * キューをクリア
	 *
	 * @param string $status クリアするステータス（all, pending, failed）
	 * @return int 削除された件数
	 */
	public function clear_queue( $status = 'all' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'qio_queue';

		if ( 'all' === $status ) {
			$deleted = $wpdb->query( "DELETE FROM {$table_name}" );
		} else {
			$deleted = $wpdb->delete(
				$table_name,
				array( 'status' => $status ),
				array( '%s' )
			);
		}

		// 進捗をリセット
		update_option(
			'qio_progress',
			array(
				'status'           => 'idle',
				'total'            => 0,
				'processed'        => 0,
				'current_batch_id' => 0,
			)
		);

		return $deleted;
	}
}
