document.addEventListener('DOMContentLoaded', function () {
    // ── Подсветка активного пункта меню ───
    const currentPath = window.location.pathname;
    document.querySelectorAll('.nav-link').forEach(function (link) {
        if (link.getAttribute('href') === currentPath) {
            link.classList.add('active');
        }
    });

    // ─── Подтверждение выхода ───
    document.querySelectorAll('form[action*="logout"]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm('Вы уверены, что хотите выйти?')) {
                e.preventDefault();
            }
        });
    });

    // ─── Автогенерация slug из заголовка ───
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');
    if (titleInput && slugInput && !slugInput.value) {
        titleInput.addEventListener('input', function () {
            const slug = this.value
                .toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .trim();
            slugInput.value = slug;
        });
    }

    // ─── Автоувеличение высоты HTML-редактора контента ───
    const contentArea = document.getElementById('content');
    if (contentArea && contentArea.tagName === 'TEXTAREA') {
        const autoResize = function () {
            contentArea.style.height = 'auto';
            contentArea.style.height = Math.min(contentArea.scrollHeight + 2, 1200) + 'px';
        };
        contentArea.addEventListener('input', autoResize);
        autoResize();
    }
});