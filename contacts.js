/**
 * contacts.js — специфичный скрипт для страницы контактов
 * Дополнение к scripts.js
 */

'use strict';

(function initContactForm() {
    const form = document.getElementById('contact-form');
    if (!form) return;

    const sendBtn = document.getElementById('send-btn');
    const formWrap = document.getElementById('form-wrap');
    const success = document.getElementById('send-success');

    function fieldErr(id, show) {
        const w = document.getElementById('cf-field-' + id);
        if (!w) return;
        w.classList.toggle('has-error', show);
        const el = w.querySelector('input, select, textarea');
        if (el) el.classList.toggle('error', show);
    }

    function isEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }

    form.addEventListener('submit', e => {
        e.preventDefault();
        let ok = true;

        const name = document.getElementById('cf-name').value.trim();
        const email = document.getElementById('cf-email').value.trim();
        const subject = document.getElementById('cf-subject').value;
        const message = document.getElementById('cf-message').value.trim();

        if (name.length < 2) { fieldErr('name', true); ok = false; } else fieldErr('name', false);
        if (!isEmail(email)) { fieldErr('email', true); ok = false; } else fieldErr('email', false);
        if (!subject) { fieldErr('subject', true); ok = false; } else fieldErr('subject', false);
        if (message.length < 10) { fieldErr('message', true); ok = false; } else fieldErr('message', false);

        if (!ok) return;

        sendBtn.disabled = true;
        sendBtn.textContent = 'Отправляем…';

        setTimeout(() => {
            formWrap.style.display = 'none';
            success.style.display = 'block';
        }, 700);
    });
})();