/**
 * Queue Image Optimizer - Admin JavaScript
 *
 * @package QueueImageOptimizer
 */

(function($) {
	'use strict';

	const QIO = {
		progressInterval: null,
		processInterval: null,
		lastProcessed: 0,
		processStartTime: null,
		isProcessing: false,

		init() {
			this.bindEvents();
			this.initRangeSliders();
			this.checkInitialStatus();
		},

		bindEvents() {
			// Dashboard events
			$('#qio-scan-btn, #qio-rescan-btn, #qio-new-scan-btn').on('click', () => this.scan());
			$('#qio-start-btn').on('click', () => this.start());
			$('#qio-pause-btn').on('click', () => this.pause());
			$('#qio-resume-btn').on('click', () => this.resume());
			$('#qio-clear-btn').on('click', () => this.clearQueue('pending'));

			// Settings events
			$('#qio-settings-form').on('submit', (e) => this.saveSettings(e));
			$('input[name="processing_mode"]').on('change', () => this.toggleCustomSettings());
			$('#qio-clear-pending').on('click', () => this.clearQueue('pending'));
			$('#qio-clear-all').on('click', () => this.clearQueue('all'));
			$('#qio-reset-flags').on('click', () => this.resetFlags());
		},

		initRangeSliders() {
			$('.qio-range').on('input', function() {
				const $this = $(this);
				const valueId = $this.attr('id') + '_value';
				$('#' + valueId).text($this.val());
			});
		},

		checkInitialStatus() {
			const status = $('#qio-status-area').data('status');
			console.log('[QIO] Initial status:', status);
			if (status === 'processing') {
				this.isProcessing = true;
				this.processStartTime = Date.now();
				this.startContinuousProcessing();
			}
		},

		toggleCustomSettings() {
			const mode = $('input[name="processing_mode"]:checked').val();
			$('.qio-custom-settings').toggle(mode === 'custom');
		},

		showStatus(statusClass) {
			$('.qio-status-area > div').hide();
			$('.qio-status-' + statusClass).show();
		},

		setButtonLoading($btn, loading) {
			if (loading) {
				$btn.addClass('qio-loading').prop('disabled', true);
			} else {
				$btn.removeClass('qio-loading').prop('disabled', false);
			}
		},

		formatBytes(bytes) {
			if (bytes === 0) return '0 B';
			const k = 1024;
			const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
			const i = Math.floor(Math.log(bytes) / Math.log(k));
			return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
		},

		formatTime(seconds) {
			if (seconds < 60) {
				return Math.round(seconds) + '秒';
			}
			if (seconds < 3600) {
				const mins = Math.floor(seconds / 60);
				const secs = Math.round(seconds % 60);
				return mins + '分' + (secs > 0 ? secs + '秒' : '');
			}
			const hours = Math.floor(seconds / 3600);
			const mins = Math.floor((seconds % 3600) / 60);
			return hours + '時間' + (mins > 0 ? mins + '分' : '');
		},

		ajax(action, data = {}) {
			return $.ajax({
				url: qioAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'qio_' + action,
					nonce: qioAdmin.nonce,
					...data
				}
			});
		},

		scan() {
			const $btn = $('#qio-scan-btn, #qio-rescan-btn, #qio-new-scan-btn');
			this.setButtonLoading($btn, true);

			this.ajax('scan')
				.done((response) => {
					if (response.success) {
						const data = response.data;
						if (data.count > 0) {
							$('#qio-scan-count').text(data.count.toLocaleString());
							$('#qio-scan-size').text('(' + this.formatBytes(data.size) + ')');
							this.showStatus('scanned');
						} else {
							alert(qioAdmin.strings.no_images);
							this.showStatus('idle');
						}
					} else {
						alert(response.data.message || qioAdmin.strings.error);
					}
				})
				.fail(() => {
					alert(qioAdmin.strings.error);
				})
				.always(() => {
					this.setButtonLoading($btn, false);
				});
		},

		start() {
			const $btn = $('#qio-start-btn');
			this.setButtonLoading($btn, true);

			this.ajax('start')
				.done((response) => {
					if (response.success) {
						this.showStatus('processing');
						this.processStartTime = Date.now();
						this.lastProcessed = 0;
						this.isProcessing = true;
						this.startContinuousProcessing();
					} else {
						alert(response.data.message || qioAdmin.strings.error);
					}
				})
				.fail(() => {
					alert(qioAdmin.strings.error);
				})
				.always(() => {
					this.setButtonLoading($btn, false);
				});
		},

		pause() {
			const $btn = $('#qio-pause-btn');
			this.setButtonLoading($btn, true);

			this.ajax('pause')
				.done((response) => {
					if (response.success) {
						this.stopContinuousProcessing();
						this.showStatus('paused');
						this.updatePausedProgress();
					}
				})
				.fail(() => {
					alert(qioAdmin.strings.error);
				})
				.always(() => {
					this.setButtonLoading($btn, false);
				});
		},

		resume() {
			const $btn = $('#qio-resume-btn');
			this.setButtonLoading($btn, true);

			this.ajax('resume')
				.done((response) => {
					if (response.success) {
						this.showStatus('processing');
						this.processStartTime = Date.now();
						this.isProcessing = true;
						this.startContinuousProcessing();
					} else {
						alert(response.data.message || qioAdmin.strings.error);
					}
				})
				.fail(() => {
					alert(qioAdmin.strings.error);
				})
				.always(() => {
					this.setButtonLoading($btn, false);
				});
		},

		startContinuousProcessing() {
			// 既存のタイマーをクリア（isProcessingはそのまま）
			if (this.processInterval) {
				clearTimeout(this.processInterval);
				this.processInterval = null;
			}
			this.isProcessing = true;
			console.log('[QIO] Starting continuous processing');
			this.processNow();
		},

		stopContinuousProcessing() {
			console.log('[QIO] Stopping continuous processing');
			this.isProcessing = false;
			if (this.processInterval) {
				clearTimeout(this.processInterval);
				this.processInterval = null;
			}
		},

		processNow() {
			if (!this.isProcessing) {
				console.log('[QIO] processNow skipped - not processing');
				return;
			}

			console.log('[QIO] processNow called (parallel)');

			// 並列で3リクエスト送信
			const requests = [
				this.ajax('process_now'),
				this.ajax('process_now'),
				this.ajax('process_now')
			];

			Promise.all(requests.map(r => r.catch(e => e)))
				.then((responses) => {
					// 最後のレスポンスを使用
					const response = responses.find(r => r && r.success) || responses[0];
					console.log('[QIO] processNow response:', response);

					if (response && response.success) {
						const { progress, statistics } = response.data;

						// Update progress bar
						const percent = progress.total > 0
							? (progress.completed / progress.total) * 100
							: 0;

						$('#qio-progress-fill').css('width', percent + '%');
						$('#qio-progress-count').text(progress.completed.toLocaleString());
						$('#qio-progress-total').text(progress.total.toLocaleString());
						$('#qio-progress-percent').text(percent.toFixed(1));

						// Update statistics
						$('#qio-total-images').text(statistics.total_images.toLocaleString());
						$('#qio-total-saved').text(this.formatBytes(statistics.total_saved));

						// Estimate remaining time
						if (progress.completed > this.lastProcessed && this.processStartTime) {
							const elapsed = (Date.now() - this.processStartTime) / 1000;
							const rate = progress.completed / elapsed;
							const remaining = progress.total - progress.completed;
							const estimatedSeconds = remaining / rate;

							if (estimatedSeconds > 0 && estimatedSeconds < 86400) {
								$('#qio-progress-estimate').text(
									qioAdmin.strings.estimated_time.replace('%s', this.formatTime(estimatedSeconds))
								);
							}
						}
						this.lastProcessed = progress.completed;

						// Check status
						if (progress.status === 'completed' || progress.pending === 0) {
							this.stopContinuousProcessing();
							this.showStatus('completed');
							$('#qio-completed-count').text(progress.completed.toLocaleString());
							$('#qio-completed-saved').text(this.formatBytes(progress.total_saved));
						} else if (progress.status === 'paused') {
							this.stopContinuousProcessing();
							this.showStatus('paused');
						} else {
							// 次のバッチを即座に実行
							this.processInterval = setTimeout(() => this.processNow(), 200);
						}
					} else {
						// エラー時は少し待って再試行
						this.processInterval = setTimeout(() => this.processNow(), 2000);
					}
				});
		},

		startProgressPolling() {
			this.stopProgressPolling();
			this.updateProgress();
			this.progressInterval = setInterval(() => this.updateProgress(), 5000);
		},

		stopProgressPolling() {
			if (this.progressInterval) {
				clearInterval(this.progressInterval);
				this.progressInterval = null;
			}
		},

		updateProgress() {
			this.ajax('progress')
				.done((response) => {
					if (response.success) {
						const { progress, statistics } = response.data;

						// Update progress bar
						const percent = progress.total > 0
							? Math.round((progress.completed / progress.total) * 100)
							: 0;

						$('#qio-progress-fill').css('width', percent + '%');
						$('#qio-progress-count').text(progress.completed.toLocaleString());
						$('#qio-progress-total').text(progress.total.toLocaleString());
						$('#qio-progress-percent').text(percent);

						// Update statistics
						$('#qio-total-images').text(statistics.total_images.toLocaleString());
						$('#qio-total-saved').text(this.formatBytes(statistics.total_saved));

						// Estimate remaining time
						if (progress.completed > this.lastProcessed) {
							const elapsed = (Date.now() - this.processStartTime) / 1000;
							const rate = progress.completed / elapsed;
							const remaining = progress.total - progress.completed;
							const estimatedSeconds = remaining / rate;

							if (estimatedSeconds > 0 && estimatedSeconds < 86400) {
								$('#qio-progress-estimate').text(
									qioAdmin.strings.estimated_time.replace('%s', this.formatTime(estimatedSeconds))
								);
							}
						}
						this.lastProcessed = progress.completed;

						// Check status
						if (progress.status === 'completed') {
							this.stopProgressPolling();
							this.showStatus('completed');
							$('#qio-completed-count').text(progress.completed.toLocaleString());
							$('#qio-completed-saved').text(this.formatBytes(progress.total_saved));
						} else if (progress.status === 'paused') {
							this.stopProgressPolling();
							this.showStatus('paused');
						} else if (progress.status === 'idle' && progress.total === 0) {
							this.stopProgressPolling();
							this.showStatus('idle');
						}
					}
				});
		},

		updatePausedProgress() {
			this.ajax('progress')
				.done((response) => {
					if (response.success) {
						const { progress } = response.data;
						const percent = progress.total > 0
							? Math.round((progress.completed / progress.total) * 100)
							: 0;

						$('#qio-progress-fill-paused').css('width', percent + '%');
						$('.qio-status-paused .qio-progress-text span').eq(0).text(progress.completed.toLocaleString());
						$('.qio-status-paused .qio-progress-text span').eq(1).text(progress.total.toLocaleString());
						$('.qio-status-paused .qio-progress-text span').eq(2).text(percent);
					}
				});
		},

		clearQueue(status) {
			if (!confirm(qioAdmin.strings.confirm_clear)) {
				return;
			}

			this.ajax('clear_queue', { status })
				.done((response) => {
					if (response.success) {
						alert(response.data.message);
						this.showStatus('idle');
						location.reload();
					} else {
						alert(response.data.message || qioAdmin.strings.error);
					}
				})
				.fail(() => {
					alert(qioAdmin.strings.error);
				});
		},

		resetFlags() {
			if (!confirm('本当に最適化済みフラグをリセットしますか？')) {
				return;
			}

			this.ajax('reset_flags')
				.done((response) => {
					if (response.success) {
						alert(response.data.message);
						location.reload();
					} else {
						alert(response.data.message || qioAdmin.strings.error);
					}
				})
				.fail(() => {
					alert(qioAdmin.strings.error);
				});
		},

		saveSettings(e) {
			e.preventDefault();

			const $form = $('#qio-settings-form');
			const $btn = $('#qio-save-settings');
			const $spinner = $form.find('.spinner');
			const $message = $('#qio-save-message');

			this.setButtonLoading($btn, true);
			$spinner.addClass('is-active');
			$message.text('').removeClass('error');

			this.ajax('save_settings', $form.serialize())
				.done((response) => {
					if (response.success) {
						$message.text(qioAdmin.strings.settings_saved);
					} else {
						$message.text(response.data.message || qioAdmin.strings.error).addClass('error');
					}
				})
				.fail(() => {
					$message.text(qioAdmin.strings.error).addClass('error');
				})
				.always(() => {
					this.setButtonLoading($btn, false);
					$spinner.removeClass('is-active');
				});
		}
	};

	$(document).ready(() => QIO.init());

})(jQuery);
