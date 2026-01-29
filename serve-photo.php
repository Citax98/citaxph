<?php
/**
 * citaxph* — Secure photo serving
 * Proprietario: Federico Citarella
 *
 * Scopo:
 * - Servire immagini salvate in STORAGE_ROOT senza esporre path reali/URL diretti.
 *
 * Uso:
 *   serve-photo.php?id=123
 *
 * Nota:
 * - Se events.allow_download = 0 => serve WEB (watermark / bassa) inline
 * - Se events.allow_download = 1 => serve ORIGINAL e forza download (attachment)
 * - Schema legacy (relative_path): serve sempre relative_path inline
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

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo 'Bad request';
  exit;
}

try {
  $col = cols_info();
  if (!$col['has_relative_path'] && !($col['has_original_path'] && $col['has_web_path'])) {
    http_response_code(500);
    echo 'Unsupported schema';
    exit;
  }

  $forceDownload = false;
  $rel = '';

  // Carica foto + verifica evento pubblicato e foto visibile
  if ($col['has_original_path'] && $col['has_web_path']) {
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

    $st = db()->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch();

    if (!$row || (int) $row['is_published'] !== 1) {
      http_response_code(404);
      echo 'Not found';
      exit;
    }

    $allowDownload = ((int) ($row['allow_download'] ?? 0) === 1);

    if ($allowDownload) {
      $rel = (string) ($row['original_path'] ?? '');
      $forceDownload = true;
    } else {
      $rel = (string) ($row['web_path'] ?? '');
      $forceDownload = false;
    }

  } else {
    // Schema legacy
    $sql = "
      SELECT
        p.relative_path AS rel,
        p.mime_type,
        p.updated_at,
        e.is_published
      FROM photos p
      JOIN events e ON e.id = p.event_id
      WHERE p.id = :id
        AND p.is_visible = 1
      LIMIT 1
    ";

    $st = db()->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch();

    if (!$row || (int) $row['is_published'] !== 1) {
      http_response_code(404);
      echo 'Not found';
      exit;
    }

    $rel = (string) $row['rel'];
    $forceDownload = false;
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

  $mime = (string) ($row['mime_type'] ?? '');
  if ($mime === '') {
    $mime = 'application/octet-stream';
  }

  $mtime = filemtime($file) ?: time();
  $etag = 'W/"' . sha1($file . '|' . $mtime . '|' . filesize($file)) . '"';

  header('Content-Type: ' . $mime);
  header('Content-Length: ' . (string) filesize($file));
  header('ETag: ' . $etag);
  header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
  header('Cache-Control: public, max-age=31536000');

  // Inline vs Attachment (download)
  if ($forceDownload) {
    header('Content-Disposition: attachment; filename="' . basename($rel) . '"');
  } else {
    header('Content-Disposition: inline');
  }

  // Conditional GET
  $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
  if ($ifNoneMatch && trim($ifNoneMatch) === $etag) {
    http_response_code(304);
    exit;
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