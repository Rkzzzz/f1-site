-- ═══════════════════════════════════════════════════════
--  База данных: f1_site
--  Описание: пользователи сайта Формула‑1
--  Автор: Абросимов Николай, ННГАСУ
-- ═══════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS f1_site
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE f1_site;

-- ───────────────────────────────────────────────────────
--  Таблица ролей
-- ───────────────────────────────────────────────────────
CREATE TABLE roles (
    id          TINYINT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name        VARCHAR(20)         NOT NULL UNIQUE,   -- 'editor' / 'reader'
    label       VARCHAR(40)         NOT NULL,          -- 'Редактор' / 'Читатель'
    created_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

-- Вставляем две роли сразу
INSERT INTO roles (name, label) VALUES
    ('editor', 'Редактор'),
    ('reader', 'Читатель');

-- ───────────────────────────────────────────────────────
--  Основная таблица пользователей
-- ───────────────────────────────────────────────────────
CREATE TABLE users (
    id              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    username        VARCHAR(40)         NOT NULL UNIQUE,
    email           VARCHAR(120)        NOT NULL UNIQUE,
    password_hash   VARCHAR(255)        NOT NULL,       -- password_hash() из PHP
    first_name      VARCHAR(60)         NOT NULL,
    last_name       VARCHAR(60)         NOT NULL,
    country         VARCHAR(60)         DEFAULT NULL,
    role_id         TINYINT UNSIGNED    NOT NULL DEFAULT 2,  -- 2 = reader по умолчанию
    is_active       BOOLEAN             NOT NULL DEFAULT TRUE,
    avatar_url      VARCHAR(255)        DEFAULT NULL,
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,
    last_login      TIMESTAMP           DEFAULT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (role_id) REFERENCES roles(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    INDEX idx_email    (email),
    INDEX idx_username (username),
    INDEX idx_role     (role_id)
) ENGINE=InnoDB;

-- ───────────────────────────────────────────────────────
--  Таблица сессий (для "запомнить меня")
-- ───────────────────────────────────────────────────────
CREATE TABLE sessions (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED    NOT NULL,
    token       VARCHAR(64)     NOT NULL UNIQUE,    -- случайный токен
    ip_address  VARCHAR(45)     DEFAULT NULL,
    user_agent  VARCHAR(255)    DEFAULT NULL,
    expires_at  TIMESTAMP       NOT NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_token   (token),
    INDEX idx_user    (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- ───────────────────────────────────────────────────────
--  Таблица логов действий (кто что делал)
-- ───────────────────────────────────────────────────────
CREATE TABLE activity_log (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED    DEFAULT NULL,        -- NULL = незарегистрированный
    action      VARCHAR(80)     NOT NULL,            -- 'login', 'edit_news', и т.д.
    description TEXT            DEFAULT NULL,
    ip_address  VARCHAR(45)     DEFAULT NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_user   (user_id),
    INDEX idx_action (action)
) ENGINE=InnoDB;

-- ───────────────────────────────────────────────────────
--  Тестовые пользователи
--  Пароли: admin123 и reader123
--  (хеши сгенерированы через password_hash в PHP)
-- ───────────────────────────────────────────────────────
INSERT INTO users (username, email, password_hash, first_name, last_name, country, role_id)
VALUES
    (
        'admin',
        'admin@f1site.ru',
        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- admin123
        'Николай',
        'Абросимов',
        'Россия',
        1   -- editor
    ),
    (
        'reader',
        'reader@f1site.ru',
        '$2y$12$eImiTXuWVxfM37uY4JANjQ==.....placeholder',                 -- reader123
        'Иван',
        'Читатель',
        'Россия',
        2   -- reader
    );
