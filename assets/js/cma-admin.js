(function ($) {
	'use strict';

	function cfg() {
		var base = window.CMAAdmin || {};
		var el = document.querySelector('.cma-upload-root') || document.getElementById('cma-mobile-app-settings');
		if (!el) {
			return base;
		}
		return {
			ajaxUrl: el.getAttribute('data-ajax') || base.ajaxUrl || '',
			nonce: el.getAttribute('data-nonce') || base.nonce || '',
			removeLabel: base.removeLabel,
			uploading: base.uploading,
			uploadFailed: base.uploadFailed,
			saved: base.saved,
			noticeCache: base.noticeCache,
			noticeSent: base.noticeSent,
			noticeScheduled: base.noticeScheduled,
			noticeFailed: base.noticeFailed,
			noticeMissing: base.noticeMissing,
			noticeNoSchedule: base.noticeNoSchedule,
			noticeSaveFailed: base.noticeSaveFailed,
			noticeDeleted: base.noticeDeleted,
			noticeDeleteFail: base.noticeDeleteFail,
		};
	}

	function ajaxUrl() {
		if (cfg().ajaxUrl) {
			return cfg().ajaxUrl;
		}
		if (window.CEM && CEM.ajax_url) {
			return CEM.ajax_url;
		}
		return window.ajaxurl || '';
	}

	function showStatus($el, message, isError) {
		if (!$el.length) {
			return;
		}
		if (!message) {
			$el.prop('hidden', true).text('');
			return;
		}
		$el.prop('hidden', false)
			.toggleClass('text-danger', !!isError)
			.toggleClass('text-muted', !isError)
			.text(message);
	}

	function toast(message, type) {
		if (window.CemAdmin && typeof window.CemAdmin.showToast === 'function') {
			window.CemAdmin.showToast(message, type || 'danger');
			return;
		}
		window.alert(message);
	}

	function takeFiles(input) {
		if (!input || !input.files || !input.files.length) {
			return [];
		}
		return Array.prototype.slice.call(input.files);
	}

	function uploadFile(file, context) {
		var url = ajaxUrl();
		if (!url) {
			return $.Deferred().reject({ responseJSON: { data: { message: 'Missing upload URL.' } } }).promise();
		}

		var form = new FormData();
		form.append('action', 'cma_upload_image');
		form.append('nonce', cfg().nonce || '');
		form.append('context', context || '');
		form.append('file', file);

		return $.ajax({
			url: url,
			method: 'POST',
			data: form,
			processData: false,
			contentType: false,
			dataType: 'json',
		});
	}

	function removeSavedImage(context, id) {
		return $.post(ajaxUrl(), {
			action: 'cma_remove_image',
			nonce: cfg().nonce || '',
			context: context,
			id: id || 0,
		});
	}

	function errorMessage(xhr, fallback) {
		if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
			return xhr.responseJSON.data.message;
		}
		if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
			return xhr.responseJSON.message;
		}
		return fallback || cfg().uploadFailed || 'Upload failed.';
	}

	function syncHiddenInputs($list, $container) {
		$container.empty();
		$list.find('.maps-home-image-item').each(function () {
			var id = $(this).attr('data-id');
			if (!id) {
				return;
			}
			$('<input>', {
				type: 'hidden',
				name: 'maps_home_screen_image_ids[]',
				value: id,
			}).appendTo($container);
		});
	}

	function addImageItem($list, attachment) {
		var id = attachment.id;
		if ($list.find('.maps-home-image-item[data-id="' + id + '"]').length) {
			return;
		}
		var $item = $('<li>', {
			class: 'maps-home-image-item',
			'data-id': id,
		});
		$item.append($('<img>', { src: attachment.thumb || attachment.url, alt: '', width: 50, height: 50 }));
		$item.append(
			$('<button>', {
				type: 'button',
				class: 'button-link maps-home-image-remove',
				text: cfg().removeLabel || 'Remove',
			})
		);
		$list.append($item);
	}

	function uploadQueue(files) {
		var deferred = $.Deferred();
		var index = 0;
		var uploaded = [];

		function step() {
			if (index >= files.length) {
				deferred.resolve(uploaded);
				return;
			}

			uploadFile(files[index], 'map')
				.done(function (res) {
					if (res && res.success && res.data) {
						uploaded.push(res.data);
						deferred.notify(res.data);
					}
					index += 1;
					step();
				})
				.fail(function (xhr) {
					deferred.reject(xhr);
				});
		}

		step();
		return deferred.promise();
	}

	function setUploadBusy($btn, busy) {
		var $label = $btn.find('.cma-upload-btn-label');
		var $input = $btn.find('input[type="file"]');
		if (!$btn.data('cma-label')) {
			$btn.data('cma-label', $label.text());
		}
		$input.prop('disabled', busy);
		$btn.css('pointer-events', busy ? 'none' : '').attr('aria-busy', busy ? 'true' : 'false');
		$label.text(busy ? cfg().uploading || 'Uploading…' : $btn.data('cma-label'));
	}

	function handleLogoChange(input) {
		var files = takeFiles(input);
		var $btn = $('#cma-header-logo-add');
		var $status = $('#cma-header-logo-status');
		if (!files.length) {
			return;
		}

		setUploadBusy($btn, true);
		showStatus($status, cfg().uploading || 'Uploading…', false);

		uploadFile(files[0], 'logo')
			.done(function (res) {
				if (!res || !res.success || !res.data) {
					showStatus($status, cfg().uploadFailed || 'Upload failed.', true);
					toast(cfg().uploadFailed || 'Upload failed.');
					return;
				}
				$('#cma-header-logo-preview').empty().append($('<img>', { src: res.data.thumb || res.data.url, alt: '' }));
				$('#cma_header_logo_id').val(res.data.id);
				$('#cma-header-logo-remove').prop('hidden', false);
				showStatus($status, cfg().saved || 'Saved.', false);
				toast(cfg().saved || 'Saved.', 'success');
			})
			.fail(function (xhr) {
				var message = errorMessage(xhr);
				showStatus($status, message, true);
				toast(message);
			})
			.always(function () {
				input.value = '';
				setUploadBusy($btn, false);
			});
	}

	function handleMapChange(input) {
		var files = takeFiles(input);
		var $btn = $('#maps-home-screen-images-add');
		var $status = $('#cma-map-images-status');
		var $list = $('#maps-home-screen-images-list');
		var $container = $('#maps-home-screen-images-inputs');
		if (!files.length) {
			return;
		}
		if (!$list.length) {
			showStatus($status, cfg().uploadFailed || 'Upload failed.', true);
			return;
		}

		setUploadBusy($btn, true);
		showStatus($status, cfg().uploading || 'Uploading…', false);

		uploadQueue(files)
			.progress(function (attachment) {
				addImageItem($list, attachment);
				syncHiddenInputs($list, $container);
			})
			.done(function () {
				showStatus($status, cfg().saved || 'Saved.', false);
				toast(cfg().saved || 'Saved.', 'success');
			})
			.fail(function (xhr) {
				var message = errorMessage(xhr);
				showStatus($status, message, true);
				toast(message);
			})
			.always(function () {
				input.value = '';
				setUploadBusy($btn, false);
			});
	}

	function queryNotice() {
		try {
			return new URLSearchParams(window.location.search).get('cma_notice') || '';
		} catch (e) {
			return '';
		}
	}

	function noticeIsNotification(notice) {
		return (
			notice === 'sent' ||
			notice === 'scheduled' ||
			notice === 'failed' ||
			notice === 'missing_fields' ||
			notice === 'no_schedule' ||
			notice === 'save_failed' ||
			notice === 'deleted' ||
			notice === 'delete_failed'
		);
	}

	function toastNotice(notice) {
		if (!notice) {
			return;
		}
		var map = {
			cache_cleared: { text: cfg().noticeCache, type: 'success' },
			sent: { text: cfg().noticeSent, type: 'success' },
			scheduled: { text: cfg().noticeScheduled, type: 'success' },
			failed: { text: cfg().noticeFailed, type: 'warning' },
			missing_fields: { text: cfg().noticeMissing, type: 'warning' },
			no_schedule: { text: cfg().noticeNoSchedule, type: 'warning' },
			save_failed: { text: cfg().noticeSaveFailed, type: 'warning' },
			deleted: { text: cfg().noticeDeleted, type: 'success' },
			delete_failed: { text: cfg().noticeDeleteFail, type: 'warning' },
		};
		if (!map[notice]) {
			return;
		}
		toast(map[notice].text, map[notice].type);
	}

	function cemAjax() {
		if (window.CEM && CEM.ajax_url) {
			return CEM.ajax_url;
		}
		return ajaxUrl();
	}

	function cemNonce() {
		return (window.CEM && CEM.nonce) || '';
	}

	function serializeContentFields($root) {
		var data = {};
		$root.find('input, select, textarea').each(function () {
			var $el = $(this);
			var name = $el.attr('name');
			if (!name || $el.is(':disabled') || $el.is('[type="button"], [type="submit"], [type="file"]')) {
				return;
			}
			if (name === 'action' || name === 'nonce' || name === '_wpnonce' || name === '_wp_http_referer' || name === 'tab') {
				return;
			}
			if ($el.is(':checkbox')) {
				data[name] = $el.is(':checked') ? 1 : 0;
				return;
			}
			if ($el.is(':radio')) {
				if ($el.is(':checked')) {
					data[name] = $el.val();
				}
				return;
			}
			if (name.slice(-2) === '[]') {
				var key = name.slice(0, -2);
				if (!Array.isArray(data[key])) {
					data[key] = [];
				}
				data[key].push($el.val());
				return;
			}
			data[name] = $el.val();
		});
		return data;
	}

	function setAppContentTab(tab) {
		$('#cma-app-content-tab-nav .list-group-item').removeClass('active');
		$('#cma-app-content-tab-nav .list-group-item[data-tab="' + tab + '"]').addClass('active');
		$('#cma-app-content-tab-select').val(tab);
	}

	function loadAppContentTab(tab) {
		var $content = $('#cma-app-content-tab-content');
		if (!$content.length) {
			return;
		}

		setAppContentTab(tab);
		if (window.CemAdmin && typeof window.CemAdmin.spinner === 'function') {
			$content.html(window.CemAdmin.spinner());
		} else {
			$content.html('<div class="d-flex justify-content-center py-4"><div class="spinner-border text-primary"></div></div>');
		}

		$.post(cemAjax(), {
			action: 'cem_load_settings_tab',
			nonce: cemNonce(),
			tab: tab,
		})
			.done(function (res) {
				if (!res || !res.success || !res.data || !res.data.html) {
					$content.html('<div class="alert alert-danger mb-0">Could not load this tab.</div>');
					return;
				}
				$content.html(res.data.html);
				$(document).trigger('cem:settings:tab:loaded', [tab]);
			})
			.fail(function () {
				$content.html('<div class="alert alert-danger mb-0">Could not load this tab.</div>');
			});
	}

	function saveContentTab(tab) {
		var $root = $('#cma-app-content-tab-content');
		$root.find('textarea[id]').each(function () {
			if (window.CemWysiwyg) {
				window.CemWysiwyg.sync(this.id);
			}
		});

		var data = serializeContentFields($root);
		data.action = 'cem_save_settings_tab';
		data.nonce = cemNonce();
		data.tab = tab;

		$.post(cemAjax(), data).done(function (res) {
			if (!res || !res.success) {
				toast((res && res.data && res.data.message) || cfg().uploadFailed || 'Could not save.');
				return;
			}
			toast((res.data && res.data.message) || cfg().saved || 'Saved.', 'success');
		}).fail(function () {
			toast(cfg().uploadFailed || 'Could not save.');
		});
	}

	function initAppContentPanel() {
		var notice = queryNotice();
		var startTab = noticeIsNotification(notice) ? 'cma-app-notifications' : 'cma-event-details';
		loadAppContentTab(startTab);
		if (notice) {
			toastNotice(notice);
		}
	}

	function initEventTitleToggle() {
		var $select = $('#cma_schedule_id');
		var $wrap = $('#cma-event-title-wrap');
		var $input = $('#cma_event_title');
		if (!$select.length || !$wrap.length) {
			return;
		}

		function sync() {
			var hasSchedule = $select.val() !== '';
			$wrap.toggleClass('d-none', !hasSchedule);
			if (hasSchedule) {
				$input.attr('placeholder', $.trim($select.find('option:selected').text()));
			}
		}

		$select.off('change.cmaEventTitle').on('change.cmaEventTitle', sync);
		sync();
	}

	function initWysiwyg() {
		if (window.CemWysiwyg) {
			window.CemWysiwyg.attach('cma_home_screen_message');
		}
	}

	function initSelectAll() {
		var selectAll = document.getElementById('cma-select-all-notifications');
		if (!selectAll || selectAll.dataset.cmaBound === '1') {
			return;
		}
		selectAll.dataset.cmaBound = '1';
		selectAll.addEventListener('change', function () {
			document.querySelectorAll('.cma-notification-row-checkbox').forEach(function (box) {
				box.checked = selectAll.checked;
			});
		});
	}

	document.addEventListener(
		'change',
		function (event) {
			var target = event.target;
			if (!target || !target.id) {
				return;
			}
			if (target.id === 'cma-header-logo-file') {
				handleLogoChange(target);
			}
			if (target.id === 'cma-map-images-file') {
				handleMapChange(target);
			}
		},
		true
	);

	$(document).on('click', '#cma-header-logo-remove', function (e) {
		e.preventDefault();
		var $btn = $(this);
		removeSavedImage('logo', 0)
			.done(function (res) {
				if (!res || !res.success) {
					toast(cfg().uploadFailed || 'Upload failed.');
					return;
				}
				$('#cma-header-logo-preview').empty();
				$('#cma_header_logo_id').val('0');
				$btn.prop('hidden', true);
				showStatus($('#cma-header-logo-status'), '');
			})
			.fail(function (xhr) {
				toast(errorMessage(xhr));
			});
	});

	$(document).on('click', '.maps-home-image-remove', function (e) {
		e.preventDefault();
		var $item = $(this).closest('.maps-home-image-item');
		var id = $item.attr('data-id');
		var $list = $('#maps-home-screen-images-list');
		var $container = $('#maps-home-screen-images-inputs');

		removeSavedImage('map', id)
			.done(function (res) {
				if (!res || !res.success) {
					toast(cfg().uploadFailed || 'Upload failed.');
					return;
				}
				$item.remove();
				syncHiddenInputs($list, $container);
			})
			.fail(function (xhr) {
				toast(errorMessage(xhr));
			});
	});

	$(document).on('cem:settings:tab:loaded', function (e, tab) {
		if (tab === 'cma-event-details') {
			initWysiwyg();
			initEventTitleToggle();
			return;
		}
		if (tab === 'cma-app-notifications') {
			initSelectAll();
		}
	});

	$(document).on('cem:panel:loaded', function (e, panel) {
		if (panel === 'app-content') {
			initAppContentPanel();
			return;
		}
		if (panel === 'settings' && queryNotice() === 'cache_cleared') {
			toastNotice('cache_cleared');
		}
	});

	$(document).on('click', '#cma-app-content-tab-nav .list-group-item', function (e) {
		e.preventDefault();
		loadAppContentTab($(this).attr('data-tab'));
	});

	$(document).on('change', '#cma-app-content-tab-select', function () {
		loadAppContentTab($(this).val());
	});

	$(document).on('click', '.cma-save-content-tab', function (e) {
		e.preventDefault();
		saveContentTab($(this).data('tab'));
	});

	$(document).on('input', '.cma-color-picker', function () {
		var target = this.getAttribute('data-target');
		var $hex = target ? $('#' + target) : $();
		if ($hex.length) {
			$hex.val(this.value);
		}
	});

	$(document).on('input', '.cma-color-hex', function () {
		var value = String(this.value || '').trim();
		if (!/^#[0-9A-Fa-f]{6}$/.test(value)) {
			return;
		}
		value = value.toLowerCase();
		this.value = value;
		$('.cma-color-picker[data-target="' + this.id + '"]').val(value);
	});
})(jQuery);
