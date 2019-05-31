(function (doc, win) {
    var docEl = doc.documentElement,
        resizeEvt = 'orientationchange' in window ? 'orientationchange' : 'resize',
        recalc = function () {
            var clientWidth = docEl.clientWidth;
            if (clientWidth > 640) {
                clientWidth = 640;
            }
            if (!clientWidth) return;
            docEl.style.fontSize = 20 * (clientWidth / 320) + 'px';
        };

    if (!doc.addEventListener) return;
    win.addEventListener(resizeEvt, recalc, false);
    doc.addEventListener('DOMContentLoaded', recalc, false);
})(document, window);



var cardSwiper = new Swiper('.card-container', {
    slidesPerView: "auto",
    initialSlide: 1,
    centeredSlides: !0,
    watchSlidesProgress: !0,
    onProgress: function (a) {
        var b, c, d;
        for (b = 0; b < a.slides.length; b++) {
            c = a.slides[b];
            d = c.progress;
            scale = 1 - Math.min(Math.abs(.2 * d), 1);
            es = c.style;
           // es.opacity = 1 - Math.min(Math.abs(d / 5), 1);
            es.webkitTransform = es.MsTransform = es.msTransform = es.MozTransform = es.OTransform = es.transform = "translate3d(0px,0," + -Math.abs(280 * d) + "px)";
        }
    },
    onSetTransition: function (a, b) {
        for (var c = 0; c < a.slides.length; c++) es = a.slides[c].style, es.webkitTransitionDuration = es.MsTransitionDuration = es.msTransitionDuration = es.MozTransitionDuration = es.OTransitionDuration = es.transitionDuration = b + "ms"
    }
})