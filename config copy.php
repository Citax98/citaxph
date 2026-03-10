<?php
/**
 * citaxph* — Config
 * Proprietario: Federico Citarella
 *
 * ✅ Scopo:
 * - Centralizzare costanti di sito e credenziali DB
 * - Esporre una connessione PDO riutilizzabile
 * - Definire path utili per storage (foto fuori dalla web root)
 *
 * NOTE:
 * - Compila le credenziali DB qui sotto.
 * - Se il tuo hosting non ti permette cartelle fuori dalla web root,
 *   useremo una strategia alternativa (ma per sicurezza è meglio fuori root).
 */

declare(strict_types=1);

/* =========================================================
 * 1) Identità sito
 * ========================================================= */
const SITE_NAME  = 'citaxph*';
const SITE_OWNER = 'Federico Citarella';

/* =========================================================
 * 2) Ambiente
 * =========================================================
 * Metti a false in produzione per non mostrare errori a schermo.
 */
const APP_DEBUG = true;

if (APP_DEBUG) {
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);
} else {
  ini_set('display_errors', '0');
  error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
}

/* =========================================================
 * 3) Sessione (per area admin)
 * ========================================================= */
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
// Se hai HTTPS (consigliato), imposta a 1:
// ini_set('session.cookie_secure', '1');

/* =========================================================
 * 4) Database (MySQL/MariaDB) — COMPILA QUI
 * ========================================================= */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'citaxph_db';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

/**
 * DSN PDO.
 * Se usi una porta diversa (es. 3307) aggiungi ;port=3307
 */
function db_dsn(): string {
  return 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
}

/**
 * Connessione PDO singleton.
 */
function db(): PDO {
  static $pdo = null;

  if ($pdo instanceof PDO) {
    return $pdo;
  }

  $options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ];

  $pdo = new PDO(db_dsn(), DB_USER, DB_PASS, $options);
  return $pdo;
}

/* =========================================================
 * 5) Path e URL
 * ========================================================= */

/**
 * Root del progetto (dove si trova config.php).
 * Se config.php sta in /public, allora PROJECT_ROOT coincide con /public.
 */
define('PROJECT_ROOT', rtrim(str_replace('\\', '/', __DIR__), '/'));

/**
 * Base URL del sito.
 * ✅ Impostalo manualmente se preferisci (consigliato in produzione).
 * Esempi:
 *   define('BASE_URL', 'https://www.tuodominio.it');
 *   define('BASE_URL', 'https://www.tuodominio.it/citaxph');
 */
if (!defined('BASE_URL')) {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $path   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
  define('BASE_URL', $scheme . '://' . $host . $path);
}

/**
 * Storage foto (FUORI dalla web root) — CONSIGLIATO
 * Imposta un path assoluto sul server.
 *
 * Esempi Linux:
 *   define('STORAGE_ROOT', '/var/citaxph_storage');
 *   define('STORAGE_ROOT', '/home/<user>/citaxph_storage');
 *
 * Esempio Windows locale (XAMPP):
 *   define('STORAGE_ROOT', 'C:/xampp/citaxph_storage');
 */
if (!defined('STORAGE_ROOT')) {
  // Placeholder: cambialo appena sai dove mettere lo storage sul server
  define('STORAGE_ROOT', PROJECT_ROOT . '/_storage');
}

/**
 * Crea STORAGE_ROOT se non esiste (solo se permesso dal server).
 */
function ensure_storage_root(): void {
  if (!is_dir(STORAGE_ROOT)) {
    @mkdir(STORAGE_ROOT, 0755, true);
  }
}

/* =========================================================
 * 6) Helper (escape + redirect)
 * ========================================================= */
function h(string $v): string {
  return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function redirect(string $to): void {
  header('Location: ' . $to);
  exit;
}

/* =========================================================
 * 7) Sicurezza base: header consigliati
 * =========================================================
 * (Puoi spostare questi header a livello web server in futuro)
 */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
// CSP la configureremo quando avremo tutte le risorse definitive.
// header("Content-Security-Policy: default-src 'self' https:; img-src 'self' data: https:;");
// Watermark
define('WATERMARK_PATH', __DIR__ . '/assets/watermark.png');
define('WATERMARK_OPACITY', 35);          // 0..100
define('WATERMARK_MARGIN_PX', 24);        // distanza dai bordi
define('WEB_MAX_LONG_EDGE', 2000);        // resize web (lato lungo max)
define('WEB_JPEG_QUALITY', 85);           // 0..100