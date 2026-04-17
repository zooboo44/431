DROP DATABASE IF EXISTS FORMULA_ONE;
CREATE DATABASE IF NOT EXISTS FORMULA_ONE;
USE FORMULA_ONE;

-------------------------------------------------------------------------------
-- TABLES
-------------------------------------------------------------------------------

CREATE TABLE driver(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY;
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50)NOT NULL,
    street VARCHAR(250),
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100),
    zip CHAR(10),
    last_modified TIMESTAMP CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX index_full_name (Name_Last, Name_First),
    INDEX index_last_name (Name_Last),
    CONSTRAINT check_zip_code CHECK (ZipCode REGEXP '^(?!0{5})(?!9{5})\\d{5}(-(?!0{4})(?!9{4})\\d{4})?$'),
    CONSTRAINT check_first_name CHECK (Name_First REGEXP '^[a-zA-Z0-9]+$'),
    CONSTRAINT check_last_name CHECK (Name_Last REGEXP '^[a-zA-Z0-9]+$')
);

-------------------------------------------------------------------------------
-- USERS (ROLES)
-------------------------------------------------------------------------------