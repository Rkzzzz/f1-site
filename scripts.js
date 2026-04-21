/**
 * scripts.js — основной скрипт сайта Формула‑1
 * Содержит: навигация, валидация форм, система inline-редактирования
 * Автор: Абросимов Николай, ННГАСУ
 */

'use strict';

// ════════════════════════════════════════════════════════
//  1. НАВИГАЦИЯ — выпадающее меню
// ════════════════════════════════════════════════════════
(function initNav() {
    const btn  = document.querySelector('.nav-menu-btn');
    const drop = document.getElementById('nav-dropdown');
    if (!btn || !drop) return;

    btn.addEventListener('click', () => {
        const open = drop.classList.toggle('open');
        btn.setAttribute('aria-expanded', open);
    });

    document.addEventListener('click', e => {
        if (!e.target.closest('.nav')) {
            drop.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            drop.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
        }
    });
})();


// ════════════════════════════════════════════════════════
//  2. ПОКАЗАТЬ / СКРЫТЬ ПАРОЛЬ
// ════════════════════════════════════════════════════════
(function initPasswordToggles() {
    document.querySelectorAll('.pwd-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            // data-target или data-t
            const targetId = btn.dataset.target || btn.dataset.t;
            const input = document.getElementById(targetId);
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? '🙈' : '👁';
        });
    });
})();


// ════════════════════════════════════════════════════════
//  3. ИНДИКАТОР СИЛЫ ПАРОЛЯ
// ════════════════════════════════════════════════════════
(function initStrengthMeter() {
    const pwdInput = document.getElementById('password') || document.getElementById('pr');
    const bars     = [1,2,3,4].map(i => document.getElementById('s'+i)).filter(Boolean);
    const label    = document.getElementById('strength-label') || document.getElementById('slbl');
    if (!pwdInput || !bars.length) return;

    const COLORS = ['#e10600', '#f97316', '#eab308', '#22c55e'];
    const LABELS = ['Очень слабый', 'Слабый', 'Средний', 'Сильный'];

    function calcScore(pwd) {
        let s = 0;
        if (pwd.length >= 8)  s++;
        if (pwd.length >= 12) s++;
        if (/[A-Z]/.test(pwd) && /[a-z]/.test(pwd)) s++;
        if (/\d/.test(pwd))   s++;
        if (/[^A-Za-z0-9]/.test(pwd)) s++;
        return Math.min(4, s);
    }

    pwdInput.addEventListener('input', function () {
        const score = this.value ? calcScore(this.value) : 0;
        bars.forEach((b, i) => {
            b.style.background = i < score ? COLORS[score - 1] : 'rgba(255,255,255,0.1)';
        });
        if (label) {
            label.textContent  = this.value ? LABELS[score - 1] || '' : '';
            label.style.color  = this.value ? COLORS[score - 1] : '';
        }
    });
})();


// ════════════════════════════════════════════════════════
//  4. ВАЛИДАЦИЯ ФОРМЫ РЕГИСТРАЦИИ
// ════════════════════════════════════════════════════════
(function initRegisterForm() {
    const form = document.getElementById('reg-form');
    if (!form) return;

    function markField(id, valid) {
        const wrap  = document.getElementById('field-' + id);
        const input = document.getElementById(id) || form.querySelector('[name="'+id+'"]');
        if (!wrap || !input) return;
        wrap.classList.toggle('has-error', !valid);
        input.classList.toggle('error', !valid);
        input.classList.toggle('valid', valid);
    }

    const isEmail = v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
    const isUser  = v => /^[A-Za-z0-9_а-яА-ЯёЁ]{3,30}$/.test(v);

    // Real-time blur validation
    const rules = {
        firstname: v => v.trim().length >= 2,
        lastname:  v => v.trim().length >= 2,
        username:  v => isUser(v.trim()),
        email:     v => isEmail(v.trim()),
        password:  v => v.length >= 8,
        confirm:   v => v === (document.getElementById('password')?.value || ''),
    };

    Object.keys(rules).forEach(name => {
        const el = form.querySelector('[name="'+name+'"]') || document.getElementById(name);
        if (el) el.addEventListener('blur', () => markField(name, rules[name](el.value)));
    });

    // Submit
    form.addEventListener('submit', e => {
        let ok = true;
        Object.keys(rules).forEach(name => {
            const el = form.querySelector('[name="'+name+'"]') || document.getElementById(name);
            if (el) { const v = rules[name](el.value); markField(name, v); if (!v) ok = false; }
        });
        const agree = form.querySelector('[name="agree"]');
        if (agree && !agree.checked) {
            agree.style.outline = '2px solid #e10600'; ok = false;
        }
        if (!ok) e.preventDefault();
    });
})();


// ════════════════════════════════════════════════════════
//  5. ВАЛИДАЦИЯ ФОРМЫ ВХОДА
// ════════════════════════════════════════════════════════
(function initLoginForm() {
    const form = document.getElementById('login-form');
    if (!form) return;

    const email    = form.querySelector('[name="email"]') || document.getElementById('email');
    const password = form.querySelector('[name="password"]') || document.getElementById('password');
    const isEmail  = v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);

    function markField(fieldId, valid) {
        const wrap  = document.getElementById('field-' + fieldId);
        const input = document.getElementById(fieldId);
        if (!wrap || !input) return;
        wrap.classList.toggle('has-error', !valid);
        input.classList.toggle('error', !valid);
        input.classList.toggle('valid', valid);
    }

    if (email)    email.addEventListener('blur',    () => markField('email',    isEmail(email.value)));
    if (password) password.addEventListener('blur', () => markField('password', password.value.length >= 1));

    form.addEventListener('submit', e => {
        const ev = email    ? isEmail(email.value) : true;
        const pv = password ? password.value.length >= 1 : true;
        markField('email', ev);
        markField('password', pv);
        if (!ev || !pv) e.preventDefault();
    });
})();


// ════════════════════════════════════════════════════════
//  6. INLINE-РЕДАКТОР (только для роли editor)
//  Принцип работы:
//   - На каждой странице элементы с data-page="result" data-key="p1_name"
//     автоматически становятся редактируемыми при включении Edit Mode
//   - Изменения сохраняются через content_api.php
// ════════════════════════════════════════════════════════
const F1Editor = (function () {

    let editMode = false;
    const ROLE   = window.F1_USER_ROLE || 'guest'; // передаётся из auth.php

    // Стили для режима редактирования
    const EDIT_STYLE = `
        [data-key].f1-editable {
            outline: 1px dashed rgba(225,6,0,0.4) !important;
            border-radius: 2px;
            cursor: text !important;
            position: relative;
            transition: outline 0.2s;
        }
        [data-key].f1-editable:hover {
            outline: 1px solid #e10600 !important;
            background: rgba(225,6,0,0.04) !important;
        }
        [data-key].f1-editable:focus {
            outline: 2px solid #e10600 !important;
            background: rgba(225,6,0,0.06) !important;
        }
        .f1-edit-tooltip {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            background: #e10600; color: #fff; padding: 8px 20px; border-radius: 6px;
            font-size: 0.82rem; z-index: 9999; pointer-events: none;
            opacity: 0; transition: opacity 0.3s;
        }
        .f1-edit-tooltip.show { opacity: 1; }
        #edit-mode-btn.active {
            background: #e10600 !important;
            color: #fff !important;
            border-color: #e10600 !important;
        }
    `;

    function injectStyles() {
        if (document.getElementById('f1-editor-styles')) return;
        const style = document.createElement('style');
        style.id = 'f1-editor-styles';
        style.textContent = EDIT_STYLE;
        document.head.appendChild(style);
    }

    // Получить все редактируемые элементы на странице
    function getEditables() {
        return document.querySelectorAll('[data-key][data-page]');
    }

    // Показать уведомление
    let tooltip = null;
    function showToast(msg, isError = false) {
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.className = 'f1-edit-tooltip';
            document.body.appendChild(tooltip);
        }
        tooltip.textContent = msg;
        tooltip.style.background = isError ? '#b00' : '#e10600';
        tooltip.classList.add('show');
        setTimeout(() => tooltip.classList.remove('show'), 2500);
    }

    // Сохранить значение через API
    async function saveContent(page, key, value) {
        try {
            const res = await fetch('content_api.php?action=save', {
                method:  'POST',
                headers: {'Content-Type': 'application/json'},
                body:    JSON.stringify({ page, key, value })
            });
            const data = await res.json();
            if (data.ok) {
                showToast('✓ Сохранено');
            } else {
                showToast('Ошибка: ' + (data.error || 'неизвестная'), true);
            }
        } catch (err) {
            showToast('Ошибка соединения', true);
        }
    }

    // Включить/выключить режим редактирования
    function toggleEditMode() {
        if (ROLE !== 'editor') return;
        editMode = !editMode;
        injectStyles();

        const btn = document.getElementById('edit-mode-btn');
        if (btn) {
            btn.textContent = editMode ? '✕ Выйти из редактирования' : '✏ Режим редактирования';
            btn.classList.toggle('active', editMode);
        }

        getEditables().forEach(el => {
            if (editMode) {
                el.classList.add('f1-editable');
                el.setAttribute('contenteditable', 'true');
                el.dataset.original = el.innerHTML;

                // Сохранение по Ctrl+Enter или при потере фокуса
                el.addEventListener('blur', onBlurSave);
                el.addEventListener('keydown', onKeydownSave);
            } else {
                el.classList.remove('f1-editable');
                el.removeAttribute('contenteditable');
                el.removeEventListener('blur', onBlurSave);
                el.removeEventListener('keydown', onKeydownSave);
            }
        });

        if (editMode) {
            showToast('Режим редактирования включён. Кликайте на текст чтобы изменить.');
        }
    }

    function onBlurSave(e) {
        const el    = e.currentTarget;
        const page  = el.dataset.page;
        const key   = el.dataset.key;
        const value = el.innerText.trim();
        if (value !== (el.dataset.original || '')) {
            saveContent(page, key, value);
            el.dataset.original = value;
        }
    }

    function onKeydownSave(e) {
        // Ctrl+Enter — сохранить и снять фокус
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            e.currentTarget.blur();
        }
        // Escape — отменить изменения
        if (e.key === 'Escape') {
            e.currentTarget.innerHTML = e.currentTarget.dataset.original || '';
            e.currentTarget.blur();
        }
    }

    // Загрузить контент страницы из БД и подставить в элементы
    async function loadPageContent(page) {
        try {
            const res  = await fetch(`content_api.php?action=load&page=${page}`);
            const data = await res.json();
            if (!data.ok) return;
            Object.entries(data.data).forEach(([key, value]) => {
                const el = document.querySelector(`[data-key="${key}"][data-page="${page}"]`);
                if (el) el.innerText = value;
            });
        } catch (err) {
            console.warn('[F1Editor] Не удалось загрузить контент:', err);
        }
    }

    // Авто-инициализация: определяем страницу и загружаем контент
    function init() {
        // Определяем имя страницы из URL или из data-page на body
        const bodyPage = document.body.dataset.page;
        const urlPage  = location.pathname.replace(/.*\//, '').replace(/\.(html|php)$/, '');
        const page     = bodyPage || urlPage || 'index';

        // Загружаем контент если есть редактируемые блоки
        if (document.querySelector('[data-key]')) {
            loadPageContent(page);
        }
    }

    // Запуск после загрузки DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return { toggleEditMode, loadPageContent, isEditMode: () => editMode };
})();

// Делаем F1Editor доступным глобально
window.F1Editor = F1Editor;
