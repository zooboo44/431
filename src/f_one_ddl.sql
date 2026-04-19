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

    FOREIGN KEY (driver_a_id) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_b_id) REFERENCES drivers(id) ON DELETE CASCADE
);

CREATE TABLE circuits(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    length_km FLOAT NOT NULL,
    last_modified TIMESTAMP CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Only the driver statistic is necessary to make as an object for its own tracking.
-- Anything we'd want from circuit statistic can just be derived from this, saving us data space.
-- We could skip race statistics if we really want to, because it could make sense to have everything in a race statistic be tied to the driver stats
CREATE TABLE driver_statistic(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    driver_id INT UNSIGNED NOT NULL,
    circuit_id INT UNSIGNED NOT NULL,
    pit_stops INT UNSIGNED NOT NULL,
    laps INT UNSIGNED NOT NULL,
    best_lap_time_ms INT UNSIGNED NOT NULL,
    last_modified TIMESTAMP CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (circuit_id) REFERENCES circuits(id) ON DELETE CASCADE
);

CREATE TABLE races(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    laps INT NOT NULL,
    winner INT UNSIGNED NOT NULL,
    circuit_id INT UNSIGNED NOT NULL,
    last_modified TIMESTAMP CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (winner) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (circuit_id) REFERENCES circuits(id) ON DELETE CASCADE
);

-------------------------------------------------------------------------------
-- USERS (ROLES)
-------------------------------------------------------------------------------