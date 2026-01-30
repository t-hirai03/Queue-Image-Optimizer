=== Queue Image Optimizer ===
Contributors: t-hirai03
Tags: image optimization, compression, performance, media, background processing
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safely optimize large numbers of images with background queue processing. No external API costs, no timeouts.

== Description ==

Queue Image Optimizer is a WordPress plugin that compresses images using background queue processing. Unlike other optimization plugins, it doesn't block your admin interface or cause timeouts when processing thousands of images.

= Key Features =

* **High-Speed Processing**: When the dashboard is open, parallel Ajax requests enable rapid continuous processing
* **Background Processing**: Processing continues even when you close the browser (using WP-Cron)
* **No External APIs**: All compression is done locally using Imagick or GD Library
* **No Timeouts**: Process thousands of images without server timeouts
* **Resume Capability**: Processing can be paused and resumed at any time
* **Multiple Processing Modes**: Safe, Standard, Fast, or Custom modes for different server configurations
* **Thumbnail Support**: Optionally optimize WordPress-generated thumbnails
* **Backup Option**: Keep original images before compression
* **Progress Tracking**: Real-time progress display with estimated completion time
* **Smart Compression**: Automatically reverts if compression increases file size

= Supported Formats =

* JPEG
* PNG
* GIF
* WebP (WordPress 5.8+)

= Requirements =

* WordPress 5.0 or higher
* PHP 8.0 or higher
* Imagick extension (recommended) or GD Library

== Installation ==

1. Upload the `queue-image-optimizer` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Queue Image Optimizer' in the admin menu to start optimizing

== Frequently Asked Questions ==

= What's the difference between processing modes? =

* **Safe Mode**: 5-minute intervals, 10 images per batch. Best for shared hosting.
* **Standard Mode**: 1-minute intervals, 20 images per batch. Best for most VPS servers.
* **Fast Mode**: Continuous processing, 300 images per batch. Best for dedicated servers.
* **Custom Mode**: Set your own intervals and batch sizes.

= How does high-speed mode work? =

When you keep the dashboard page open, the plugin sends parallel Ajax requests to process images rapidly. This is much faster than background-only processing.

= Can I close the browser while processing? =

Yes! Processing continues in the background using WP-Cron. However, it will be slower than keeping the page open.

= Will this affect my original images? =

By default, original images are replaced with optimized versions. Enable the backup option in settings to keep original files.

= What if compression makes the file larger? =

The plugin automatically detects this and keeps the original file. You'll never end up with a larger file after optimization.

== Screenshots ==

1. Dashboard with progress tracking
2. Settings page with processing modes
3. Compression quality settings

== Changelog ==

= 1.0.0 =
* Initial release
* High-speed Ajax continuous processing
* Background processing with WP-Cron fallback
* Bulk optimization feature
* Auto-optimize on upload
* Multiple processing modes (Safe/Standard/Fast/Custom)
* Backup functionality
* Real-time progress tracking
* Smart compression (prevents file size increase)
* PHP 8.0 - 8.4 compatibility

== Upgrade Notice ==

= 1.0.0 =
Initial release of Queue Image Optimizer.
