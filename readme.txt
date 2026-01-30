=== Queue Image Optimizer ===
Contributors: yourname
Tags: image optimization, compression, performance, media, background processing
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safely optimize large numbers of images with background queue processing. No external API costs, no timeouts.

== Description ==

Queue Image Optimizer is a WordPress plugin that compresses images using background queue processing. Unlike other optimization plugins, it doesn't block your admin interface or cause timeouts when processing thousands of images.

= Key Features =

* **Background Processing**: Images are optimized in the background using Action Scheduler or WP-Cron
* **No External APIs**: All compression is done locally using Imagick or GD Library
* **No Timeouts**: Process thousands of images without server timeouts
* **Resume Capability**: Processing can be paused and resumed at any time
* **Multiple Processing Modes**: Safe, Standard, Fast, or Custom modes for different server configurations
* **Thumbnail Support**: Optionally optimize WordPress-generated thumbnails
* **Backup Option**: Keep original images before compression
* **Progress Tracking**: Real-time progress display with estimated completion time

= Supported Formats =

* JPEG
* PNG
* GIF
* WebP (WordPress 5.8+)

= Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* Imagick extension (recommended) or GD Library

== Installation ==

1. Upload the `queue-image-optimizer` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Image Optimizer' in the admin menu to start optimizing

= Optional: Install Action Scheduler =

For best performance, install Action Scheduler:

`cd wp-content/plugins/queue-image-optimizer
mkdir -p vendor
cd vendor
curl -L https://github.com/woocommerce/action-scheduler/archive/refs/tags/3.7.4.tar.gz | tar xz
mv action-scheduler-3.7.4 action-scheduler`

Without Action Scheduler, the plugin will use WP-Cron as a fallback.

== Frequently Asked Questions ==

= What's the difference between processing modes? =

* **Safe Mode**: 5-minute intervals, 10 images per batch. Best for shared hosting.
* **Standard Mode**: 1-minute intervals, 20 images per batch. Best for most VPS servers.
* **Fast Mode**: Continuous processing, 50 images per batch. Best for dedicated servers.
* **Custom Mode**: Set your own intervals and batch sizes.

= Can I close the browser while processing? =

Yes! Processing continues in the background. When you return to the dashboard, you'll see the current progress.

= Will this affect my original images? =

By default, original images are replaced with optimized versions. Enable the backup option in settings to keep original files.

= Why is Action Scheduler recommended? =

Action Scheduler provides more reliable background processing than WP-Cron, especially for large queues. However, the plugin works fine with WP-Cron as a fallback.

== Screenshots ==

1. Dashboard with statistics and progress bar
2. Settings page with processing modes
3. Compression quality settings

== Changelog ==

= 1.0.0 =
* Initial release
* Background queue processing with Action Scheduler
* Bulk optimization feature
* Auto-optimize on upload
* Multiple processing modes
* Backup functionality
* Progress tracking

== Upgrade Notice ==

= 1.0.0 =
Initial release of Queue Image Optimizer.
