/**
 * register.js — специфичный скрипт для страницы регистрации
 * Дополнение к scripts.js
 */

'use strict';

(function initRegisterSpecific() {
    const form = document.getElementById('reg-form');
    if (!form) return;

    const submitBtn = document.getElementById('submit-btn');
    const formWrap = document.getElementById('reg-form-wrap');
    const successScreen = document.getElementById('success-screen');

    function markField(name, valid) {
        const wrap = document.getElementById('field-' + name);
        const input = document.getElementById(name) || form.querySelector('[name="' + name + '"]');
        if (!wrap || !input) return;
        wrap.classList.toggle('has-error', !valid);
        input.classList.toggle('error', !valid);
        input.classList.toggle('valid', valid);
    }

    const isEmail = v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
    const isUser = v => /^[A-Za-z0-9_а-яА-ЯёЁ]{3,30}$/.test(v);

    const rules = {
        firstname: v => v.trim().length >= 2,
        lastname: v => v.trim().length >= 2,
        username: v => isUser(v.trim()),
        email: v => isEmail(v.trim()),
        country: v => v !== '',
        password: v => v.length >= 8,
        confirm: v => v === (document.getElementById('password')?.value || '')
    };

    // Real-time validation
    Object.keys(rules).forEach(name => {
        const el = form.querySelector('[name="' + name + '"]') || document.getElementById(name);
        if (el) {
            el.addEventListener('blur', () => markField(name, rules[name](el.value)));
            el.addEventListener('input', () => {
                if (el.classList.contains('error') || el.classList.contains('valid')) {
                    markField(name, rules[name](el.value));
                }
            });
        }
    });

    // Submit
    form.addEventListener('submit', e => {
        e.preventDefault();
        
        let ok = true;
        Object.keys(rules).forEach(name => {
            const el = form.querySelector('[name="' + name + '"]') || document.getElementById(name);
            if (el) {
                const v = rules[name](el.value);
                markField(name, v);
                if (!v) ok = false;
            }
        });

        const agree = form.querySelector('[name="agree"]');
        if (agree && !agree.checked) {
            agree.style.outline = '2px solid #e10600';
            ok = false;
        } else if (agree) {
            agree.style.outline = '';
        }

        if (!ok) return;

        submitBtn.disabled = true;
        submitBtn.textContent = 'Создание аккаунта...';

        // Simulate registration (replace with real API call)
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Создать аккаунт';
            formWrap.style.display = 'none';
            successScreen.style.display = 'block';
        }, 1500);
    });
})();