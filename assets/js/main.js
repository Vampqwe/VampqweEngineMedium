// FAQ Accordion
document.querySelectorAll('.faq-question').forEach(q => {
q.addEventListener('click', () => {
const item = q.parentElement;
const isActive = item.classList.contains('active');
document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('active'));
if (!isActive) item.classList.add('active');
});
});

// Мобильное выдвижное меню (бургер)
(function () {
var burger = document.getElementById('nav-burger');
var mobileMenu = document.getElementById('mobile-menu');
var overlay = document.getElementById('mobile-overlay');
if (!burger || !mobileMenu || !overlay) return;

function openMenu() {
burger.classList.add('active');
mobileMenu.classList.add('active');
overlay.classList.add('active');
burger.setAttribute('aria-expanded', 'true');
mobileMenu.setAttribute('aria-hidden', 'false');
document.body.style.overflow = 'hidden';
}
function closeMenu() {
burger.classList.remove('active');
mobileMenu.classList.remove('active');
overlay.classList.remove('active');
burger.setAttribute('aria-expanded', 'false');
mobileMenu.setAttribute('aria-hidden', 'true');
document.body.style.overflow = '';
}
burger.addEventListener('click', function () {
mobileMenu.classList.contains('active') ? closeMenu() : openMenu();
});
overlay.addEventListener('click', closeMenu);
document.addEventListener('keydown', function (e) {
if (e.key === 'Escape') closeMenu();
});

// Раскрытие подменю на мобильном
document.querySelectorAll('.mobile-parent').forEach(function (btn) {
btn.addEventListener('click', function () {
btn.parentElement.classList.toggle('open');
});
});

// Закрытие меню при переходе по ссылке
document.querySelectorAll('.mobile-menu-list a').forEach(function (link) {
link.addEventListener('click', closeMenu);
});

// Тач-устройства: открытие выпадающих меню по клику
if ('ontouchstart' in window) {
document.querySelectorAll('.nav-menu > li.has-dropdown > a').forEach(function (link) {
link.addEventListener('click', function (e) {
var li = link.parentElement;
if (!li.classList.contains('open')) {
e.preventDefault();
document.querySelectorAll('.nav-menu > li.has-dropdown.open').forEach(function (other) {
if (other !== li) other.classList.remove('open');
});
li.classList.add('open');
}
});
});
}
})();

 // Мобильное меню
        const burger = document.getElementById('navBurger');
        const mobileMenu = document.getElementById('mobileMenu');
        const mobileOverlay = document.getElementById('mobileOverlay');
        
        burger.addEventListener('click', () => {
            burger.classList.toggle('active');
            mobileMenu.classList.toggle('active');
            mobileOverlay.classList.toggle('active');
        });
        
        mobileOverlay.addEventListener('click', () => {
            burger.classList.remove('active');
            mobileMenu.classList.remove('active');
            mobileOverlay.classList.remove('active');
        });

        // Мобильные подменю
        document.querySelectorAll('.mobile-parent').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.parentElement.classList.toggle('open');
            });
        });
