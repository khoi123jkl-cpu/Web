(function () {
    var cartBubble = document.querySelector('.floating-cart');
    var siteHeader = document.querySelector('.site-header');

    if (!cartBubble || !siteHeader) {
        return;
    }

    var updateVisibility = function () {
        var headerBottom = siteHeader.getBoundingClientRect().bottom;
        var isPastHeader = headerBottom < 0;
        cartBubble.classList.toggle('is-visible', isPastHeader);
        cartBubble.setAttribute('aria-hidden', isPastHeader ? 'false' : 'true');
        cartBubble.setAttribute('tabindex', isPastHeader ? '0' : '-1');
    };

    window.addEventListener('scroll', updateVisibility, { passive: true });
    window.addEventListener('resize', updateVisibility);
    updateVisibility();
}());
