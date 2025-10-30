<?php
// DB configuration. Set db_driver to 'mysql' to use XAMPP MySQL, or 'sqlite' to use the bundled SQLite file.
return [
    // 'sqlite' or 'mysql'
    'db_driver' => 'mysql',

    // SQLite settings (used when db_driver == 'sqlite')
    'sqlite_path' => __DIR__ . '/../../data/shoestore.db',

    // MySQL settings (used when db_driver == 'mysql')
    'mysql_host' => '127.0.0.1',
    'mysql_port' => 3306,
    'mysql_dbname' => 'ShoeStoreDemo', 
    'mysql_user' => 'root',
    'mysql_pass' => '',
    'mysql_charset' => 'utf8mb4',
];
