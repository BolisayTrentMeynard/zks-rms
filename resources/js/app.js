import './bootstrap';

// Mobile menu toggle
const toggle = document.querySelector('[data-nav-toggle]');
const menu = document.querySelector('[data-nav-menu]');

if (toggle && menu) {
    toggle.addEventListener('click', () => {
        const open = menu.classList.toggle('hidden') === false;
        toggle.setAttribute('aria-expanded', String(open));
    });
}

// Live countdown for a Slot Held reservation (the server also prints the starting time).
document.querySelectorAll('[data-countdown]').forEach((el) => {
    const end = new Date(el.dataset.countdown).getTime();

    const tick = () => {
        const left = end - Date.now();

        if (left <= 0) {
            el.textContent = 'The hold has ended. Reload this page to see the current status.';
            return false;
        }

        const h = Math.floor(left / 3600000);
        const m = Math.floor((left % 3600000) / 60000);
        const sec = Math.floor((left % 60000) / 1000);
        el.textContent = `${h}h ${String(m).padStart(2, '0')}m ${String(sec).padStart(2, '0')}s`;

        return true;
    };

    if (tick()) {
        const timer = setInterval(() => {
            if (!tick()) {
                clearInterval(timer);
            }
        }, 1000);
    }
});
