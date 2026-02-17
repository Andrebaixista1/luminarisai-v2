import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

const THEME_KEY = 'lumia-theme';

function applyTheme(theme) {
    const root = document.documentElement;
    const normalized = theme === 'dark' ? 'dark' : 'light';

    root.classList.remove('theme-light', 'theme-dark');
    root.classList.add(normalized === 'dark' ? 'theme-dark' : 'theme-light');
    localStorage.setItem(THEME_KEY, normalized);
    syncThemeButtons(normalized);
}

function getInitialTheme() {
    const savedTheme = localStorage.getItem(THEME_KEY);
    if (savedTheme === 'dark' || savedTheme === 'light') {
        return savedTheme;
    }

    return 'dark';
}

function syncThemeButtons(theme) {
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-label', theme === 'dark' ? 'Ativar tema claro' : 'Ativar tema escuro');
        button.setAttribute('title', theme === 'dark' ? 'Tema claro' : 'Tema escuro');

        const sunIcon = button.querySelector('[data-icon-sun]');
        const moonIcon = button.querySelector('[data-icon-moon]');

        if (sunIcon && moonIcon) {
            sunIcon.classList.toggle('hidden', theme !== 'dark');
            moonIcon.classList.toggle('hidden', theme === 'dark');
        }
    });
}

window.toggleTheme = function toggleTheme() {
    const current = document.documentElement.classList.contains('theme-dark') ? 'dark' : 'light';
    applyTheme(current === 'dark' ? 'light' : 'dark');
};

document.addEventListener('DOMContentLoaded', () => {
    applyTheme(getInitialTheme());
});
