(function ($) {
	'use strict';

	function scrollBodyTop() {
		var body = document.querySelector('.cma-app-body');
		if (body) {
			body.scrollTop = 0;
		}
	}

	$(document).on('shown.bs.tab', '#eventTabs a[data-toggle="tab"]', function () {
		scrollBodyTop();
	});
})(jQuery);
