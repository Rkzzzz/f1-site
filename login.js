/**
 * login.js — специфичный скрипт для страницы входа
 * Дополнение к scripts.js
 */

'use strict';

(function initLoginSpecific() {
    const form = document.getElementById('login-form');
    if (!form) return;

    const submitBtn = document.getElementById('submit-btn');
    const formWrap = document.getElementById('login-form-wrap');
    const successScreen = document.getElementById('success-screen');

    form.addEventListener('submit', e => {
        // Проверка на валидность уже есть в scripts.js
        // Если submit сработал, значит форма валидна
        if (!form.checkValidity()) {
            e.preventDefault();
            return;
        }

        e.preventDefault();
        submitBtn.disabled = true;
        submitBtn.textContent = 'Вход...';

        // Simulate login (replace with real API call)
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Войти';
            formWrap.style.display = 'none';
            successScreen.style.display = 'block';
        }, 1000);
    });
})();