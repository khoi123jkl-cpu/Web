(function () {
    var countdown = document.querySelector('[data-login-countdown]');
    var button = document.querySelector('[data-login-submit]');

    if (!countdown || !button) {
        return;
    }

    var seconds = Number(countdown.dataset.seconds);
    var update = function () {
        countdown.textContent = seconds > 0
            ? 'Còn ' + seconds + ' giây.'
            : 'Bạn có thể thử lại.';

        if (seconds <= 0) {
            button.disabled = false;
            return;
        }

        seconds -= 1;
        window.setTimeout(update, 1000);
    };

    update();
}());
