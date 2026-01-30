<?php
/**
 * 画像圧縮クラス
 *
 * @package QueueImageOptimizer
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 画像圧縮処理を行うクラス
 */
class QIO_Compressor {

	/**
	 * 利用可能なエンジン
	 *
	 * @var string imagick|gd
	 */
	private $engine;

	/**
	 * 設定
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * サポートするMIMEタイプ
	 *
	 * @var array
	 */
	private $supported_types = array(
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
	);

	/**
	 * コンストラクタ
	 */
	public function __construct() {
		$this->settings = get_option( 'qio_settings', array() );
		$this->engine   = $this->detect_engine();
	}

	/**
	 * 利用可能なエンジンを検出
	 *
	 * @return string
	 */
	private function detect_engine() {
		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			return 'imagick';
		}

		if ( extension_loaded( 'gd' ) && function_exists( 'imagecreatefromjpeg' ) ) {
			return 'gd';
		}

		return 'none';
	}

	/**
	 * 現在のエンジンを取得
	 *
	 * @return string
	 */
	public function get_engine() {
		return $this->engine;
	}

	/**
	 * エンジンが利用可能かチェック
	 *
	 * @return bool
	 */
	public function is_available() {
		return 'none' !== $this->engine;
	}

	/**
	 * サポートするファイルタイプかチェック
	 *
	 * @param string $file_path ファイルパス
	 * @return bool
	 */
	public function is_supported( $file_path ) {
		$mime_type = wp_check_filetype( $file_path );

		if ( empty( $mime_type['type'] ) ) {
			return false;
		}

		return in_array( $mime_type['type'], $this->supported_types, true );
	}

	/**
	 * 画像を圧縮
	 *
	 * @param string $file_path ファイルパス
	 * @return array|WP_Error 結果配列またはエラー
	 */
	public function compress( $file_path ) {
		if ( ! $this->is_available() ) {
			return new WP_Error( 'no_engine', __( '画像処理エンジンが利用できません。', 'queue-image-optimizer' ) );
		}

		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', __( 'ファイルが見つかりません。', 'queue-image-optimizer' ) );
		}

		if ( ! $this->is_supported( $file_path ) ) {
			return new WP_Error( 'unsupported_type', __( 'サポートされていないファイル形式です。', 'queue-image-optimizer' ) );
		}

		$original_size = filesize( $file_path );
		$mime_type     = wp_check_filetype( $file_path );

		// バックアップ
		if ( $this->should_backup() ) {
			$backup_result = $this->backup_file( $file_path );
			if ( is_wp_error( $backup_result ) ) {
				return $backup_result;
			}
		}

		// 圧縮前に一時コピーを作成（サイズ増加時の復元用）
		$temp_file = $file_path . '.qio_temp';
		copy( $file_path, $temp_file );

		// エンジンに応じた圧縮処理
		$result = 'imagick' === $this->engine
			? $this->compress_with_imagick( $file_path, $mime_type['type'] )
			: $this->compress_with_gd( $file_path, $mime_type['type'] );

		if ( is_wp_error( $result ) ) {
			// エラー時は一時ファイルから復元
			if ( file_exists( $temp_file ) ) {
				copy( $temp_file, $file_path );
				wp_delete_file( $temp_file );
			}
			return $result;
		}

		// ファイルサイズキャッシュをクリアして正確なサイズを取得
		clearstatcache( true, $file_path );
		$optimized_size = filesize( $file_path );

		// 圧縮後にサイズが増加した場合は元に戻す
		if ( $optimized_size >= $original_size ) {
			copy( $temp_file, $file_path );
			$optimized_size = $original_size;
		}

		// 一時ファイルを削除
		if ( file_exists( $temp_file ) ) {
			wp_delete_file( $temp_file );
		}

		return array(
			'original_size'  => $original_size,
			'optimized_size' => $optimized_size,
			'saved_bytes'    => $original_size - $optimized_size,
			'saved_percent'  => $original_size > 0 ? round( ( 1 - $optimized_size / $original_size ) * 100, 2 ) : 0,
		);
	}

	/**
	 * Imagickで圧縮
	 *
	 * @param string $file_path ファイルパス
	 * @param string $mime_type MIMEタイプ
	 * @return bool|WP_Error
	 */
	private function compress_with_imagick( $file_path, $mime_type ) {
		try {
			$imagick = new Imagick( $file_path );

			// カラープロファイルを削除（ファイルサイズ削減）
			$imagick->stripImage();

			switch ( $mime_type ) {
				case 'image/jpeg':
					$imagick->setImageCompression( Imagick::COMPRESSION_JPEG );
					$imagick->setImageCompressionQuality( $this->get_jpeg_quality() );
					$imagick->setInterlaceScheme( Imagick::INTERLACE_PLANE ); // プログレッシブJPEG
					break;

				case 'image/png':
					$imagick->setImageCompression( Imagick::COMPRESSION_ZIP );
					$imagick->setImageCompressionQuality( $this->get_png_compression() * 10 );
					break;

				case 'image/gif':
					// GIFはアニメーションの場合があるため、圧縮レベルを抑える
					$imagick->setImageCompression( Imagick::COMPRESSION_LZW );
					break;

				case 'image/webp':
					$imagick->setImageCompressionQuality( $this->get_jpeg_quality() );
					break;
			}

			$imagick->writeImage( $file_path );
			$imagick->clear();
			$imagick->destroy();

			return true;

		} catch ( Exception $e ) {
			return new WP_Error( 'imagick_error', $e->getMessage() );
		}
	}

	/**
	 * GDで圧縮
	 *
	 * @param string $file_path ファイルパス
	 * @param string $mime_type MIMEタイプ
	 * @return bool|WP_Error
	 */
	private function compress_with_gd( $file_path, $mime_type ) {
		switch ( $mime_type ) {
			case 'image/jpeg':
				$image = imagecreatefromjpeg( $file_path );
				if ( false === $image ) {
					return new WP_Error( 'gd_error', __( 'JPEG画像の読み込みに失敗しました。', 'queue-image-optimizer' ) );
				}
				$result = imagejpeg( $image, $file_path, $this->get_jpeg_quality() );
				imagedestroy( $image );
				break;

			case 'image/png':
				$image = imagecreatefrompng( $file_path );
				if ( false === $image ) {
					return new WP_Error( 'gd_error', __( 'PNG画像の読み込みに失敗しました。', 'queue-image-optimizer' ) );
				}
				// アルファチャンネルを保持
				imagesavealpha( $image, true );
				$result = imagepng( $image, $file_path, $this->get_png_compression() );
				imagedestroy( $image );
				break;

			case 'image/gif':
				$image = imagecreatefromgif( $file_path );
				if ( false === $image ) {
					return new WP_Error( 'gd_error', __( 'GIF画像の読み込みに失敗しました。', 'queue-image-optimizer' ) );
				}
				$result = imagegif( $image, $file_path );
				imagedestroy( $image );
				break;

			case 'image/webp':
				if ( ! function_exists( 'imagecreatefromwebp' ) ) {
					return new WP_Error( 'gd_error', __( 'WebPがサポートされていません。', 'queue-image-optimizer' ) );
				}
				$image = imagecreatefromwebp( $file_path );
				if ( false === $image ) {
					return new WP_Error( 'gd_error', __( 'WebP画像の読み込みに失敗しました。', 'queue-image-optimizer' ) );
				}
				$result = imagewebp( $image, $file_path, $this->get_jpeg_quality() );
				imagedestroy( $image );
				break;

			default:
				return new WP_Error( 'unsupported_type', __( 'サポートされていないファイル形式です。', 'queue-image-optimizer' ) );
		}

		if ( ! $result ) {
			return new WP_Error( 'gd_error', __( '画像の保存に失敗しました。', 'queue-image-optimizer' ) );
		}

		return true;
	}

	/**
	 * JPEG品質を取得
	 *
	 * @return int
	 */
	private function get_jpeg_quality() {
		$quality = isset( $this->settings['jpeg_quality'] ) ? (int) $this->settings['jpeg_quality'] : 82;
		return apply_filters( 'qio_jpeg_quality', max( 1, min( 100, $quality ) ) );
	}

	/**
	 * PNG圧縮レベルを取得
	 *
	 * @return int
	 */
	private function get_png_compression() {
		$compression = isset( $this->settings['png_compression'] ) ? (int) $this->settings['png_compression'] : 6;
		return apply_filters( 'qio_png_compression', max( 0, min( 9, $compression ) ) );
	}

	/**
	 * バックアップが必要かチェック
	 *
	 * @return bool
	 */
	private function should_backup() {
		return ! empty( $this->settings['backup_enabled'] );
	}

	/**
	 * ファイルをバックアップ
	 *
	 * @param string $file_path ファイルパス
	 * @return bool|WP_Error
	 */
	private function backup_file( $file_path ) {
		$backup_dir  = WP_CONTENT_DIR . '/qio-backup';
		$upload_dir  = wp_upload_dir();
		$upload_base = $upload_dir['basedir'];

		// アップロードディレクトリ以下の相対パスを取得
		$relative_path = str_replace( $upload_base, '', $file_path );
		$backup_path   = $backup_dir . $relative_path;
		$backup_folder = dirname( $backup_path );

		// ディレクトリがなければ作成
		if ( ! file_exists( $backup_folder ) ) {
			if ( ! wp_mkdir_p( $backup_folder ) ) {
				return new WP_Error( 'backup_dir_error', __( 'バックアップディレクトリの作成に失敗しました。', 'queue-image-optimizer' ) );
			}
		}

		// 既にバックアップが存在する場合はスキップ
		if ( file_exists( $backup_path ) ) {
			return true;
		}

		if ( ! copy( $file_path, $backup_path ) ) {
			return new WP_Error( 'backup_copy_error', __( 'バックアップのコピーに失敗しました。', 'queue-image-optimizer' ) );
		}

		return true;
	}

	/**
	 * バックアップから復元
	 *
	 * @param string $file_path ファイルパス
	 * @return bool|WP_Error
	 */
	public function restore_from_backup( $file_path ) {
		$backup_dir  = WP_CONTENT_DIR . '/qio-backup';
		$upload_dir  = wp_upload_dir();
		$upload_base = $upload_dir['basedir'];

		$relative_path = str_replace( $upload_base, '', $file_path );
		$backup_path   = $backup_dir . $relative_path;

		if ( ! file_exists( $backup_path ) ) {
			return new WP_Error( 'backup_not_found', __( 'バックアップが見つかりません。', 'queue-image-optimizer' ) );
		}

		if ( ! copy( $backup_path, $file_path ) ) {
			return new WP_Error( 'restore_copy_error', __( '復元に失敗しました。', 'queue-image-optimizer' ) );
		}

		return true;
	}

	/**
	 * サーバー環境情報を取得
	 *
	 * @return array
	 */
	public function get_server_info() {
		$info = array(
			'engine'           => $this->engine,
			'imagick_version'  => '',
			'gd_version'       => '',
			'memory_limit'     => ini_get( 'memory_limit' ),
			'max_execution'    => ini_get( 'max_execution_time' ),
			'supported_types'  => $this->supported_types,
		);

		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			$imagick               = new Imagick();
			$version               = $imagick->getVersion();
			$info['imagick_version'] = $version['versionString'] ?? 'Unknown';
		}

		if ( extension_loaded( 'gd' ) && function_exists( 'gd_info' ) ) {
			$gd_info           = gd_info();
			$info['gd_version'] = $gd_info['GD Version'] ?? 'Unknown';
		}

		return $info;
	}
}
