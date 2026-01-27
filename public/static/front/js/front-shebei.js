(function () {
    function setFont(size) {
        var divBody = document.getElementById("news_content");
        if (!divBody) {
            return;
        }
        divBody.style.fontSize = size + "px";
        var divChildBody = divBody.childNodes;
        for (var i = 0; i < divChildBody.length; i++) {
            if (divChildBody[i].nodeType === 1) {
                divChildBody[i].style.fontSize = size + "px";
            }
        }
    }

    window.SetFont = setFont;

    document.addEventListener('DOMContentLoaded', function () {
        var printButton = document.querySelector('.a-print');
        if (printButton) {
            printButton.addEventListener('click', function (event) {
                event.preventDefault();
                if (typeof jQuery !== 'undefined' && typeof jQuery.fn.printArea === 'function') {
                    jQuery('#print-area').printArea();
                    return;
                }
                if (typeof window.print === 'function') {
                    window.print();
                }
            });
        }

        var closeButton = document.getElementById('btn-article-close');
        if (closeButton) {
            closeButton.addEventListener('click', function (event) {
                event.preventDefault();
                window.location.href = '/';
            });
        }
    });
})();
