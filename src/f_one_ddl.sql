DROP DATABASE IF EXISTS FORMULA_ONE;
CREATE DATABASE IF NOT EXISTS FORMULA_ONE;
USE FORMULA_ONE;

-- -----------------------------------------------------------------------------
-- TABLES
-- -----------------------------------------------------------------------------

CREATE TABLE drivers(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50)NOT NULL,
    street VARCHAR(250),
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100),
    zip CHAR(10),
    last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX index_full_name (last_name, first_name),
    INDEX index_last_name (last_name),
    CONSTRAINT check_zip_code CHECK (zip REGEXP '^(?!0{5})(?!9{5})\\d{5}(-(?!0{4})(?!9{4})\\d{4})?$'),
    CONSTRAINT check_first_name CHECK (first_name REGEXP '^[a-zA-Z0-9]+$'),
    CONSTRAINT check_last_name CHECK (last_name REGEXP '^[a-zA-Z0-9]+$')
);

-- Teams have exactly two drivers
CREATE TABLE teams(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_name VARCHAR(255) NOT NULL,
    driver_a_id INT UNSIGNED NOT NULL,
    driver_b_id INT UNSIGNED NOT NULL,
    last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (driver_a_id) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_b_id) REFERENCES drivers(id) ON DELETE CASCADE
);

CREATE TABLE circuits(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    length_km FLOAT NOT NULL,
    last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Only the driver statistic is necessary to make as an object for its own tracking.
-- Anything we'd want from circuit statistic can just be derived from this, saving us data space.
-- We could skip race statistics if we really want to, because it could make sense to have everything in a race statistic be tied to the driver stats
CREATE TABLE driver_statistics(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    driver_id INT UNSIGNED NOT NULL,
    circuit_id INT UNSIGNED NOT NULL,
    pit_stops INT UNSIGNED NOT NULL,
    laps INT UNSIGNED NOT NULL,
    best_lap_time_ms INT UNSIGNED NOT NULL,
    last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (circuit_id) REFERENCES circuits(id) ON DELETE CASCADE
);

CREATE TABLE races(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    laps INT NOT NULL,
    winner INT UNSIGNED NOT NULL,
    circuit_id INT UNSIGNED NOT NULL,
    last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (winner) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (circuit_id) REFERENCES circuits(id) ON DELETE CASCADE
);

CREATE TABLE accounts(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    user_role TINYINT UNSIGNED NOT NULL DEFAULT 1,
    driver_id INT UNSIGNED NOT NULL,
    last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
);

CREATE TABLE roles(
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    display_name VARCHAR(30) NOT NULL UNIQUE,
    internal_name VARCHAR(255) NOT NULL UNIQUE,
    last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
);

-- -----------------------------------------------------------------------------
-- USERS (ROLES)
-- -----------------------------------------------------------------------------

-- Manager: Edit access to the entire database
DROP USER IF EXISTS 'manager'@'localhost';
CREATE USER 'manager'@'localhost' IDENTIFIED BY 'manager_secret';
GRANT SELECT, INSERT, DELETE, UPDATE, EXECUTE ON FORMULA_ONE.* TO 'manager'@'localhost';

-- Coach: Read access to entire database, may edit and create new drivers, but may only edit statistics
DROP USER IF EXISTS 'coach'@'localhost';
CREATE USER 'coach'@'localhost' IDENTIFIED BY 'coach_secret';
GRANT SELECT ON FORMULA_ONE.* TO 'coach'@'localhost';
GRANT INSERT, UPDATE (first_name, last_name, street, city, state, country, zip) ON FORMULA_ONE.drivers TO 'coach'@'localhost';
GRANT DELETE ON FORMULA_ONE.drivers TO 'coach'@'localhost';
GRANT UPDATE ON FORMULA_ONE.driver_statistics TO 'coach'@'localhost';

-- Driver: Read access to entire database, but may only edit their own address and statistics
DROP USER IF EXISTS 'driver'@'localhost';
CREATE USER 'driver'@'localhost' IDENTIFIED BY 'driver_secret';
GRANT SELECT ON FORMULA_ONE.* TO 'driver'@'localhost';
GRANT INSERT, UPDATE (first_name, last_name, street, city, state, country, zip) ON FORMULA_ONE.drivers TO 'driver'@'localhost';
GRANT DELETE ON FORMULA_ONE.drivers TO 'driver'@'localhost';
GRANT UPDATE (pit_stops, laps, best_lap_time_ms) ON FORMULA_ONE.driver_statistics TO 'driver'@'localhost';

-- Observer: Only has access to roles and accounts
DROP USER IF EXISTS 'observer'@'localhost';
CREATE USER 'observer'@'localhost' IDENTIFIED BY 'observer_secret';
GRANT SELECT ON FORMULA_ONE.roles TO 'observer'@'localhost';
GRANT SELECT ON FORMULA_ONE.accounts TO 'observer'@'localhost';