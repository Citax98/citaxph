<?php
/**
 * citaxph* — Secure photo serving (preview + download original)
 * Proprietario: Federico Citarella
 *
 * Scopo:
 * - Servire immagini salvate in STORAGE_ROOT senza esporre path reali/URL diretti.
 *
 * Uso:
 *   Preview (sempre WEB):      serve-photo.php?id=123
 *   Download (se consentito):  serve-photo.php?id=123&download=1
 *
 * Regole:
 * - Preview: serve SEMPRE la versione WEB (watermarked)
 * - Download: serve ORIGINAL solo se l'evento ha allow_download=1
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** Rileva colonne presenti in photos (schema nuovo o legacy) */
function cols_info(): array
{
  $cols = db()->query("SHOW COLUMNS FROM photos")->fetchAll();
  $names = array_map(fn($c) => (string) $c['Field'], $cols);

  return [
    'has_original_path' => in_array('original_path', $names, true),
    'has_web_path' => in_array('web_path', $names, true),
    'has_relative_path' => in_array('relative_path', $names, true),
  ];
}

/** Sicurezza: risolve path dentro STORAGE_ROOT */
function resolve_storage_file(string $relative): string
{
  ensure_storage_root();

  $root = rtrim(STORAGE_ROOT, '/\\');
  $relative = ltrim($relative, '/\\');

  $full = $root . DIRECTORY_SEPARATOR . $relative;

  $realRoot = realpath($root);
  $realFile = realpath($full);

  if ($realRoot === false || $realFile === false) {
    throw new RuntimeException('File not found');
  }

  // Impedisce traversal: il file deve stare sotto STORAGE_ROOT
  $realRootNorm = rtrim(str_replace('\\', '/', $realRoot), '/') . '/';
  $realFileNorm = str_replace('\\', '/', $realFile);

  if (strpos($realFileNorm, $realRootNorm) !== 0) {
    throw new RuntimeException('Invalid path');
  }

  return $realFile;
}

/** MIME robusto: DB -> finfo -> estensione */
function detect_mime(string $file, ?string $dbMime): string
{
  $mime = (string) ($dbMime ?? '');
  if ($mime !== '' && $mime !== 'application/octet-stream')
    return $mime;

  if (function_exists('finfo_open')) {
    $fi = @finfo_open(FILEINFO_MIME_TYPE);
    if ($fi) {
      $m = @finfo_file($fi, $file);
      @finfo_close($fi);
      if (is_string($m) && $m !== '')
        return $m;
    }
  }

  $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
  return match ($ext) {
    'jpg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    default => 'application/octet-stream',
  };
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo 'Bad request';
  exit;
}
$downloadRequested = isset($_GET['download']) && (string) $_GET['download'] === '1';

try {
  $col = cols_info();
  $hasNew = ($col['has_original_path'] && $col['has_web_path']);
  $hasLegacy = $col['has_relative_path'];

  if (!$hasNew && !$hasLegacy) {
    http_response_code(500);
    echo 'Unsupported schema';
    exit;
  }

  // Carica foto + verifica evento pubblicato e foto visibile
  if ($hasNew) {
    $sql = "
      SELECT
        p.web_path,
        p.original_path,
        p.mime_type,
        p.updated_at,
        e.is_published,
        e.allow_download
      FROM photos p
      JOIN events e ON e.id = p.event_id
      WHERE p.id = :id
        AND p.is_visible = 1
      LIMIT 1
    ";
  } else {
    // legacy: non abbiamo original_path; preview ok, download non supportato
    $sql = "
      SELECT
        p.relative_path AS web_path,
        NULL AS original_path,
        p.mime_type,
        p.updated_at,
        e.is_published,
        e.allow_download
      FROM photos p
      JOIN events e ON e.id = p.event_id
      WHERE p.id = :id
        AND p.is_visible = 1
      LIMIT 1
    ";
  }

  $st = db()->prepare($sql);
  $st->execute([':id' => $id]);
  $row = $st->fetch();

  if (!$row || (int) $row['is_published'] !== 1) {
    http_response_code(404);
    echo 'Not found';
    exit;
  }

  ($row['allow_download'] ?? 0) === 1;

  $allowDownload = ((int) ($row['allow_download'] ?? 0) === 1);
  $forceDownload = false;

  // Preview: se allow_download=1 -> ORIGINAL, altrimenti WEB
  $rel = $allowDownload
    ? (string) ($row['original_path'] ?? '')
    : (string) ($row['web_path'] ?? '');

  // Download richiesto: solo se evento consente e c'è original_path
  if ($downloadRequested) {
    if (!$allowDownload) {
      http_response_code(404);
      echo 'Not found';
      exit;
    }

    $orig = (string) ($row['original_path'] ?? '');
    if ($orig === '') {
      http_response_code(404);
      echo 'Not found';
      exit;
    }

    $rel = $orig;          // serve l'originale
    $forceDownload = true; // header attachment
  }


  if ($rel === '') {
    http_response_code(404);
    echo 'Not found';
    exit;
  }

  $file = resolve_storage_file($rel);
  if (!is_file($file) || !is_readable($file)) {
    http_response_code(404);
    echo 'Not found';
    exit;
  }

  $mime = detect_mime($file, $row['mime_type'] ?? null);

  $mtime = filemtime($file) ?: time();
  $etag = 'W/"' . sha1($file . '|' . $mtime . '|' . filesize($file)) . '"';

  header('Content-Type: ' . $mime);
  header('Content-Length: ' . (string) filesize($file));
  header('ETag: ' . $etag);
  header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

  if ($forceDownload) {
    // meglio non cache-are download di originali
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('Content-Disposition: attachment; filename="' . basename($rel) . '"');
  } else {
    header('Cache-Control: public, max-age=31536000');
    header('Content-Disposition: inline');
    // Conditional GET solo per preview
    $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
    if ($ifNoneMatch && trim($ifNoneMatch) === $etag) {
      http_response_code(304);
      exit;
    }
  }

  // Stream
  $fp = fopen($file, 'rb');
  if ($fp === false) {
    throw new RuntimeException('Cannot open file');
  }
  fpassthru($fp);
  fclose($fp);
  exit;

} catch (Throwable $e) {
  http_response_code(404);
  echo 'Not found';
  exit;
}