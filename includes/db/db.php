<?php
// PDO connection that can use SQLite (default) or MySQL (XAMPP).
declare(strict_types=1);

function get_db(): PDO
{
	static $pdo = null;
	if ($pdo instanceof PDO) {
		return $pdo;
	}

	$cfg = require __DIR__ . '/config.php';
	if (($cfg['db_driver'] ?? 'sqlite') === 'mysql') {
		$host = $cfg['mysql_host'] ?? '127.0.0.1';
		$port = $cfg['mysql_port'] ?? 3306;
		$dbname = $cfg['mysql_dbname'] ?? 'shoestoredemo';
		$user = $cfg['mysql_user'] ?? 'root';
		$pass = $cfg['mysql_pass'] ?? '';
		$charset = $cfg['mysql_charset'] ?? 'utf8mb4';
		$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
		$pdo = new PDO($dsn, $user, $pass);
	} else {
		$dbPath = $cfg['sqlite_path'] ?? __DIR__ . '/../../data/shoestore.db';
		$dir = dirname($dbPath);
		if (!is_dir($dir)) {
			mkdir($dir, 0777, true);
		}
		$dsn = 'sqlite:' . $dbPath;
		$pdo = new PDO($dsn);
	}

	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

	return $pdo;
}

 
