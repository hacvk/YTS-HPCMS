(function () {
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof jQuery === 'undefined') {
            return;
        }
        jQuery('.equipment-image img').on('error', function () {
            var basePath = document.body ? document.body.getAttribute('data-base-path') : '/';
            if (!basePath) {
                basePath = '/';
            }
            var fallback = basePath + 'static/picture/default-equipment.jpg';
            jQuery(this).attr('src', fallback);
        });
    });
})();
