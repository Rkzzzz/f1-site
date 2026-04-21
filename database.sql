-- ═══════════════════════════════════════════════════════
--  database.sql — база данных сайта Формула‑1
--  Автор: Абросимов Николай, ННГАСУ
--  Импортировать: phpMyAdmin → Импорт → выбрать этот файл
-- ═══════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS f1_site
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE f1_site;

-- ── Роли ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS roles (
    id    TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name  VARCHAR(20)      NOT NULL UNIQUE,
    label VARCHAR(40)      NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

INSERT INTO roles (name, label) VALUES ('editor','Редактор'), ('reader','Читатель');

-- ── Пользователи ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    username      VARCHAR(40)      NOT NULL UNIQUE,
    email         VARCHAR(120)     NOT NULL UNIQUE,
    password_hash VARCHAR(255)     NOT NULL,
    first_name    VARCHAR(60)      NOT NULL,
    last_name     VARCHAR(60)      NOT NULL,
    country       VARCHAR(60)      DEFAULT NULL,
    role_id       TINYINT UNSIGNED NOT NULL DEFAULT 2,
    is_active     BOOLEAN          NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login    TIMESTAMP        DEFAULT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON UPDATE CASCADE,
    INDEX idx_email(email), INDEX idx_username(username)
) ENGINE=InnoDB;

-- ── Сессии (запомнить меня) ──────────────────────────────
CREATE TABLE IF NOT EXISTS sessions (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    token      VARCHAR(64)  NOT NULL UNIQUE,
    ip_address VARCHAR(45)  DEFAULT NULL,
    expires_at TIMESTAMP    NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token(token)
) ENGINE=InnoDB;

-- ── Контент страниц (редактируемые блоки) ───────────────
CREATE TABLE IF NOT EXISTS page_content (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    page       VARCHAR(60)  NOT NULL,
    `key`      VARCHAR(100) NOT NULL,
    value      TEXT         NOT NULL,
    updated_by INT UNSIGNED DEFAULT NULL,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_page_key (page, `key`),
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Лог действий ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity_log (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED DEFAULT NULL,
    action      VARCHAR(80)  NOT NULL,
    description TEXT         DEFAULT NULL,
    ip_address  VARCHAR(45)  DEFAULT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ════════════════════════════════════════════════════════
--  ТЕСТОВЫЕ ПОЛЬЗОВАТЕЛИ
--  Пароль admin  → Admin123!
--  Пароль reader → Reader123!
--  ! После импорта запусти make_hash.php и замени хеши !
-- ════════════════════════════════════════════════════════
INSERT INTO users (username,email,password_hash,first_name,last_name,country,role_id) VALUES
('admin',  'admin@f1site.ru',  'REPLACE_WITH_HASH','Николай','Абросимов','Россия',1),
('reader', 'reader@f1site.ru', 'REPLACE_WITH_HASH','Иван','Читатель','Россия',2);

-- ════════════════════════════════════════════════════════
--  КОНТЕНТ ПО УМОЛЧАНИЮ
-- ════════════════════════════════════════════════════════
INSERT INTO page_content (page,`key`,value) VALUES
-- Главная
('index','hero_title','Последние новости Формулы‑1'),
('index','hero_subtitle','Актуальные новости, результаты и статистика сезона 2026'),
-- Результаты
('result','race_round','ROUND 03 — JAPAN'),
('result','race_title','Гран-при Японии 2026'),
('result','p1_name','Kimi Antonelli'),('result','p1_team','Mercedes AMG Petronas'),('result','p1_time','1:28:03.403'),
('result','p2_name','Oscar Piastri'),  ('result','p2_team','McLaren'),            ('result','p2_time','+13.722s'),
('result','p3_name','Charles Leclerc'),('result','p3_team','Ferrari'),            ('result','p3_time','+15.270s'),
('result','p4_name','George Russell'), ('result','p4_team','Mercedes AMG Petronas'),('result','p4_time','+15.574s'),
('result','p5_name','Lando Norris'),   ('result','p5_team','McLaren'),            ('result','p5_time','+23.479s'),
-- О нас
('about','lead_text','Я — начинающий программист, студент ННГАСУ, мечтаю стать хорошим специалистом и постепенно иду к этому. Моя задача — показать какая интересная бывает Формула‑1.'),
('about','mission_text','Сделать мир Формулы‑1 ближе к каждому болельщику. Мы собираем данные из официальных источников и предоставляем их в удобном виде — без лишнего шума.'),
('about','offer_text','Актуальный календарь гонок, подробные профили всех пилотов и команд сезона 2026, результаты каждого уикенда включая квалификации и спринты.'),
-- Команды
('teams','ferrari_desc','Начало легендарного союза Хэмилтона и Скудерии. Команда нацелена на возвращение титула в Маранелло.'),
('teams','redbull_desc','Эра собственных двигателей в партнерстве с Ford. Макс Ферстаппен продолжает защищать статус чемпиона.'),
('teams','mclaren_desc','Самый стабильный состав пилотов последних лет. Команда из Уокинга — один из главных фаворитов сезона.'),
('teams','mercedes_desc','Новая глава после ухода Хэмилтона. Ставка на молодого таланта Антонелли и лидерство Расселла.'),
('teams','audi_desc','Дебютный сезон немецкого гиганта. Полностью заводская команда с собственной силовой установкой.'),
('teams','aston_desc','Начало эксклюзивного партнерства с Honda. Новые мощности в Сильверстоуне.'),
('teams','alpine_desc','Переход на клиентские моторы Mercedes и обновление технического штаба.'),
('teams','williams_desc','Один из сильнейших составов пилотов. Сайнс и Албон ведут историческую команду обратно к вершинам.'),
('teams','rb_desc','Молодёжная команда системы Red Bull, нацеленная на регулярные очки и развитие талантов.'),
('teams','haas_desc','Новый этап с опытным Оконом и перспективным Берманом. Плотное сотрудничество с Ferrari.'),
('teams','cadillac_desc','Новое имя в Формуле-1. Американский гигант вступает в чемпионат с целью составить конкуренцию.');
