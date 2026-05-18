-- Dedicated database for Dusk browser tests.
-- Dusk needs persistent state across the HTTP requests it makes (login,
-- form submit, follow redirect, …) so an in-memory SQLite won't work. We
-- isolate browser-test data here so a Dusk run never touches the dev
-- `library` database.
--
-- The MYSQL_USER from docker-compose only gets implicit GRANTs on
-- MYSQL_DATABASE, so we explicitly grant access to this second database
-- as well.
CREATE DATABASE IF NOT EXISTS library_dusk
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON library_dusk.* TO 'library'@'%';
FLUSH PRIVILEGES;
