<?php
require_once __DIR__ . '/includes/db/db.php';
$cfg = require __DIR__ . '/includes/db/config.php';

$db = get_db();

$driver = $cfg['db_driver'] ?? 'sqlite';
if ($driver === 'mysql') {
    // MySQL DDL
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) UNIQUE,
        name VARCHAR(255),
        password VARCHAR(255)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255),
        description TEXT,
        price DECIMAL(10,2),
        category_id INT,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->beginTransaction();
    $db->exec("INSERT IGNORE INTO categories (id, name) VALUES (1,'Sneakers'),(2,'Boots');");
    $db->exec("INSERT IGNORE INTO products (id, name, description, price, category_id) VALUES
        (1,'Runner 1','Comfortable running shoe',59.99,1),
        (2,'Trail Boot','Durable trail boot',89.99,2)");
    $db->commit();

    echo "Migration complete. MySQL DB updated (database: {$cfg['mysql_dbname']}).\n";
} else {
    // SQLite DDL
    $db->exec("PRAGMA foreign_keys = ON;
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT UNIQUE,
        name TEXT,
        password TEXT
    );
    CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT
    );
    CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        description TEXT,
        price REAL,
        category_id INTEGER,
        FOREIGN KEY(category_id) REFERENCES categories(id)
    );
    ");

    $db->beginTransaction();
    $db->exec("INSERT OR IGNORE INTO categories (id, name) VALUES (1,'Sneakers'),(2,'Boots');");
    $db->exec("INSERT OR IGNORE INTO products (id, name, description, price, category_id) VALUES
        (1,'Runner 1','Comfortable running shoe',59.99,1),
        (2,'Trail Boot','Durable trail boot',89.99,2);");
    $db->commit();

    echo "Migration complete. DB created at {$cfg['sqlite_path']}.\n";
}
