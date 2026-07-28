<?php
// ============================================================
// db.php - Connessione al database
// Progetto PW 2025-2026 - DB11 Quiz
// ============================================================

// Configurazione - cambia questi valori per Altervista
define('DB_HOST', 'localhost');
define('DB_NAME', 'my_quizconsolelog');
define('DB_USER', 'root');       // Per Altervista: il tuo username
define('DB_PASS', '');           // Per Altervista: la tua password
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Connessione al database fallita: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}
