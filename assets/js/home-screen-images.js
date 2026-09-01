(function () {
	'use strict';

	function openModal(modal, trigger) {
		var full = trigger.getAttribute('data-full') || '';
		var thumb = trigger.querySelector('img');
		if (!full && thumb) {
			full = thumb.src;
		}
		var alt = thumb ? thumb.getAttribute('alt') || '' : '';
		modal.querySelector('.maps-home-images-modal__img').src = full;
		modal.querySelector('.maps-home-images-modal__img').alt = alt;
		modal.hidden = false;
		document.body.classList.add('maps-home-images-modal-open');
		modal.querySelector('.maps-home-images-modal__close').focus();
	}

	function closeModal(modal) {
		modal.hidden = true;
		document.body.classList.remove('maps-home-images-modal-open');
		var img = modal.querySelector('.maps-home-images-modal__img');
		img.removeAttribute('src');
	}

	document.addEventListener('click', function (e) {
		var trigger = e.target.closest('.maps-home-images__thumb');
		if (!trigger) {
			return;
		}
		e.preventDefault();
		var gallery = trigger.closest('.maps-home-images');
		if (!gallery) {
			return;
		}
		var modal = gallery.querySelector('.maps-home-images-modal');
		if (!modal) {
			return;
		}
		openModal(modal, trigger);
	});

	document.addEventListener('click', function (e) {
		if (e.target.matches('.maps-home-images-modal__backdrop, .maps-home-images-modal__close')) {
			var modal = e.target.closest('.maps-home-images-modal');
			if (modal) {
				closeModal(modal);
			}
		}
	});

	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') {
			return;
		}
		document.querySelectorAll('.maps-home-images-modal:not([hidden])').forEach(closeModal);
	});
})();
