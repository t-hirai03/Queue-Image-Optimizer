<?php
/**
 * Plugin Name: Queue Image Optimizer
 * Plugin URI: https://github.com/your-username/queue-image-optimizer
 * Description: 大量の画像を安全に最適化できるWordPressプラグイン。非同期キュー処理により、サーバー負荷を分散し、バックグラウンドで画像圧縮を実行します。
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-website.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: queue-image-optimizer
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 8.0
 *
 * @package QueueImageOptimizer
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// プラグイン定数
define( 'QIO_VERSION', '1.0.0' );
define( 'QIO_PLUGIN_FILE', __FILE__ );
define( 'QIO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'QIO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'QIO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Action Scheduler の読み込み
 */
function qio_load_action_scheduler() {
	$action_scheduler_path = QIO_PLUGIN_DIR . 'vendor/action-scheduler/action-scheduler.php';
	if ( file_exists( $action_scheduler_path ) ) {
		require_once $action_scheduler_path;
	}
}
add_action( 'plugins_loaded', 'qio_load_action_scheduler', -10 );

/**
 * プラグインのメインクラス
 */
final class Queue_Image_Optimizer {

	/**
	 * シングルトンインスタンス
	 *
	 * @var Queue_Image_Optimizer|null
	 */
	private static $instance = null;

	/**
	 * 管理画面クラス
	 *
	 * @var QIO_Admin|null
	 */
	public $admin = null;

	/**
	 * キュークラス
	 *
	 * @var QIO_Queue|null
	 */
	public $queue = null;

	/**
	 * 圧縮クラス
	 *
	 * @var QIO_Compressor|null
	 */
	public $compressor = null;

	/**
	 * シングルトンインスタンスを取得
	 *
	 * @return Queue_Image_Optimizer
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * コンストラクタ
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * 依存ファイルの読み込み
	 */
	private function load_dependencies() {
		require_once QIO_PLUGIN_DIR . 'includes/class-qio-activator.php';
		require_once QIO_PLUGIN_DIR . 'includes/class-qio-deactivator.php';
		require_once QIO_PLUGIN_DIR . 'includes/class-qio-compressor.php';
		require_once QIO_PLUGIN_DIR . 'includes/class-qio-queue.php';

		if ( is_admin() ) {
			require_once QIO_PLUGIN_DIR . 'includes/class-qio-admin.php';
		}
	}

	/**
	 * フックの初期化
	 */
	private function init_hooks() {
		// アクティベーション・ディアクティベーション
		register_activation_hook( QIO_PLUGIN_FILE, array( 'QIO_Activator', 'activate' ) );
		register_deactivation_hook( QIO_PLUGIN_FILE, array( 'QIO_Deactivator', 'deactivate' ) );

		// プラグイン初期化
		add_action( 'init', array( $this, 'init' ) );

		// 翻訳ファイル読み込み
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * プラグイン初期化
	 */
	public function init() {
		// 圧縮クラス
		$this->compressor = new QIO_Compressor();

		// キュークラス
		$this->queue = new QIO_Queue( $this->compressor );

		// 管理画面
		if ( is_admin() ) {
			$this->admin = new QIO_Admin( $this->queue );
		}
	}

	/**
	 * 翻訳ファイルの読み込み
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'queue-image-optimizer',
			false,
			dirname( QIO_PLUGIN_BASENAME ) . '/languages'
		);
	}
}

/**
 * プラグインのインスタンスを取得
 *
 * @return Queue_Image_Optimizer
 */
function qio() {
	return Queue_Image_Optimizer::get_instance();
}

// プラグイン起動
qio();
