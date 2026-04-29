/**
 * assets/js/main.js
 * Community Issue Reporter — Global JavaScript
 * Handles sidebar toggle, image previews, and UI helpers.
 */

document.addEventListener('DOMContentLoaded', function () {

    // ── Sidebar Toggle (mobile) ─────────────────────────────────
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar       = document.getElementById('cirSidebar');

    // Create overlay element dynamically
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        });

        overlay.addEventListener('click', function () {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        });
    }

    // ── Image upload preview ────────────────────────────────────
    const photoInput   = document.getElementById('photoInput');
    const photoPreview = document.getElementById('photoPreview');

    if (photoInput && photoPreview) {
        photoInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    photoPreview.style.display = 'block';
                    photoPreview.querySelector('img').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // ── Auto-dismiss alerts ─────────────────────────────────────
    setTimeout(function () {
        document.querySelectorAll('.auto-dismiss').forEach(function (el) {
            el.style.transition = 'opacity .5s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 500);
        });
    }, 4000);

    // ── Confirm delete / action dialogs ────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(this.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

});
