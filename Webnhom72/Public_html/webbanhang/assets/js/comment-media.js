(function () {
    'use strict';

    var modal;
    var modalImage;
    var lastFocusedImage;

    function closeModal() {
        if (!modal) {
            return;
        }

        modal.hidden = true;
        document.body.classList.remove('comment-image-modal-open');
        if (lastFocusedImage) {
            lastFocusedImage.focus();
        }
    }

    function openModal(image) {
        if (!modal) {
            modal = document.createElement('div');
            modal.className = 'comment-image-modal';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            modal.setAttribute('aria-label', 'Ảnh bình luận kích thước lớn');
            modal.hidden = true;

            modalImage = document.createElement('img');
            modalImage.alt = image.alt;

            var closeButton = document.createElement('button');
            closeButton.className = 'comment-image-modal-close';
            closeButton.type = 'button';
            closeButton.setAttribute('aria-label', 'Đóng ảnh');
            closeButton.textContent = '×';
            closeButton.addEventListener('click', closeModal);

            modal.appendChild(modalImage);
            modal.appendChild(closeButton);
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });
            document.body.appendChild(modal);
        }

        lastFocusedImage = image;
        modalImage.src = image.src;
        modalImage.alt = image.alt;
        modal.hidden = false;
        document.body.classList.add('comment-image-modal-open');
        modal.querySelector('.comment-image-modal-close').focus();
    }

    document.querySelectorAll('.comment-media-image').forEach(function (image) {
        image.addEventListener('click', function () {
            openModal(image);
        });
        image.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openModal(image);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal && !modal.hidden) {
            closeModal();
        }
    });
}());
