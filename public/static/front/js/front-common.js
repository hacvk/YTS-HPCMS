/* global jQuery */
(function () {
    function smoothScrollToTop() {
        var doc = document.documentElement || document.body;
        var scrollTop = doc.scrollTop || document.body.scrollTop;
        if (scrollTop > 0) {
            window.scrollTo(0, Math.floor(scrollTop - scrollTop / 8));
            setTimeout(smoothScrollToTop, 16);
        }
    }

    function bindScrollTop() {
        var buttons = document.getElementsByClassName('side_toolbar_top');
        if (!buttons || buttons.length === 0) {
            return;
        }

        for (var i = 0; i < buttons.length; i++) {
            buttons[i].onclick = function (event) {
                if (event && event.preventDefault) {
                    event.preventDefault();
                } else if (window.event) {
                    window.event.returnValue = false;
                }

                if (window.jQuery) {
                    jQuery('body,html').animate({ scrollTop: 0 }, 500);
                } else {
                    smoothScrollToTop();
                }
                return false;
            };
        }
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        bindScrollTop();
    } else if (document.addEventListener) {
        document.addEventListener('DOMContentLoaded', bindScrollTop);
    } else {
        window.attachEvent('onload', bindScrollTop);
    }
})();
