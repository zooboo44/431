DROP DATABASE IF EXISTS FORMULA_ONE;
CREATE DATABASE IF NOT EXISTS FORMULA_ONE;
USE FORMULA_ONE;

-------------------------------------------------------------------------------
-- TABLES
-------------------------------------------------------------------------------

CREATE TABLE drivers(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50)NOT NULL,
    street VARCHAR(250),
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100),
    zip CHAR(10),
    last_modified TIMESTAMP CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

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
    last_modified TIMESTAMP CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (driver_a_id) REFERENCES driver(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_b_id) REFERENCES driver(id) ON DELETE CASCADE
);

CREATE TABLE circuits(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    length_km FLOAT NOT NULL,
    laps INT NOT NULL,
    lap_record_ms INT UNSIGNED,
    lap_record_holder INT UNSIGNED
    last_modified TIMESTAMP CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (lap_record_holder) REFERENCES driver(id) ON DELETE CASCADE
);

-------------------------------------------------------------------------------
-- USERS (ROLES)
-------------------------------------------------------------------------------