<?php
/**
 * citaxph* — Admin / Foto Upload Endpoint (STEP 2: batching sequenziale + WATERMARK AUTO)
 * Proprietario: Federico Citarella
 *
 * Endpoint chiamato via fetch() da admin-photos.php per caricare un BATCH.
 * Risponde JSON.
 *
 * POST multipart/form-data:
 * - csrf_token
 * - event_id
 * - photos[]  (batch)
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (empty($_SESSION['admin_user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
  exit;
}

// CSRF (double submit cookie + session)
$cookieName = 'citaxph_csrf';
$expected = '';

if (!empty($_SESSION['csrf_token'])) {
  $expected = (string)$_SESSION['csrf_token'];
} elseif (!empty($_COOKIE[$cookieName])) {
  $expected = (string)$_COOKIE[$cookieName];
  $_SESSION['csrf_token'] = $expected;
} else {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Missing CSRF context']);
  exit;
}

$posted = (string)($_POST['csrf_token'] ?? '');
if ($posted === '' || !hash_equals($expected, $posted)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid CSRF']);
  exit;
}

// Constraints
$allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
$maxFileBytes = 12 * 1024 * 1024; // 12MB per file
$maxBatchFiles = 50;

/* =========================================================
 * Helpers
 * ========================================================= */

function ensure_dir(string $dir): void {
  if (!is_dir($dir)) {
    if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
      throw new RuntimeException("Impossibile creare cartella: $dir");
    }
  }
}

function safe_basename(string $name): string {
  $name = basename($name);
  $name = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $name) ?? 'file';
  if ($name === '' || $name === '.' || $name === '..') $name = 'file';
  return $name;
}

function ext_of(string $name): string {
  return strtolower(pathinfo($name, PATHINFO_EXTENSION));
}

function mime_from_ext(string $ext): string {
  return match ($ext) {
    'jpg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    default => 'application/octet-stream',
  };
}

function cols_info(): array {
  $cols = db()->query("SHOW COLUMNS FROM photos")->fetchAll();
  $names = array_map(fn($c) => (string)$c['Field'], $cols);

  return [
    'has_original_path' => in_array('original_path', $names, true),
    'has_web_path'      => in_array('web_path', $names, true),
    'has_relative_path' => in_array('relative_path', $names, true),
    'has_has_watermark' => in_array('has_watermark', $names, true),
  ];
}

function normalize_files_array(array $files): array {
  $out = [];
  if (!isset($files['name']) || !is_array($files['name'])) return $out;

  $count = count($files['name']);
  for ($i = 0; $i < $count; $i++) {
    $out[] = [
      'name' => $files['name'][$i] ?? '',
      'type' => $files['type'][$i] ?? '',
      'tmp_name' => $files['tmp_name'][$i] ?? '',
      'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
      'size' => $files['size'][$i] ?? 0,
    ];
  }
  return $out;
}

/* =========================================================
 * Watermark (GD)
 * ========================================================= */

function gd_available(): bool {
  return extension_loaded('gd') && function_exists('imagecreatetruecolor');
}

function img_load(string $path, string $ext) {
  return match ($ext) {
    'jpg', 'jpeg' => @imagecreatefromjpeg($path),
    'png'         => @imagecreatefrompng($path),
    'webp'        => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
    default       => false
  };
}

function img_save($im, string $dest, string $ext): bool {
  // output nello stesso formato dell'originale (così mime_type resta coerente)
  if ($ext === 'png') {
    imagesavealpha($im, true);
    return imagepng($im, $dest, 6);
  }
  if ($ext === 'webp' && function_exists('imagewebp')) {
    return imagewebp($im, $dest, 85);
  }
  // default jpeg
  $q = defined('WEB_JPEG_QUALITY') ? (int)WEB_JPEG_QUALITY : 85;
  return imagejpeg($im, $dest, $q);
}

/**
 * Crea versione web: resize (lato lungo max) + watermark PNG con trasparenza.
 * $src = originale, $dest = web finale (stesso ext dell'originale)
 */
function make_web_with_watermark(string $src, string $dest, string $srcExt): void {
  // fallback: se GD o watermark mancano, copia
  if (!gd_available() || !defined('WATERMARK_PATH') || !is_file(WATERMARK_PATH)) {
    if (!@copy($src, $dest)) throw new RuntimeException('Impossibile creare copia web (fallback)');
    return;
  }

  $base = img_load($src, $srcExt);
  if (!$base) throw new RuntimeException('Impossibile leggere immagine (GD): ' . basename($src));

  // EXIF orientation (solo JPEG)
  if (in_array($srcExt, ['jpg','jpeg'], true) && function_exists('exif_read_data')) {
    $exif = @exif_read_data($src);
    $ori = (int)($exif['Orientation'] ?? 1);
    if ($ori === 3) $base = imagerotate($base, 180, 0);
    if ($ori === 6) $base = imagerotate($base, -90, 0);
    if ($ori === 8) $base = imagerotate($base, 90, 0);
  }

  $w = imagesx($base);
  $h = imagesy($base);
  if ($w <= 0 || $h <= 0) throw new RuntimeException('Dimensioni immagine non valide');

  $maxLong = defined('WEB_MAX_LONG_EDGE') ? (int)WEB_MAX_LONG_EDGE : 2000;

  $long = max($w, $h);
  $scale = ($long > $maxLong) ? ($maxLong / $long) : 1.0;
  $nw = max(1, (int)round($w * $scale));
  $nh = max(1, (int)round($h * $scale));

  $dst = imagecreatetruecolor($nw, $nh);
  imagealphablending($dst, false);
  imagesavealpha($dst, true);
  $transparent = imagecolorallocatealpha($dst, 0,0,0,127);
  imagefill($dst, 0, 0, $transparent);
  imagealphablending($dst, true);

  imagecopyresampled($dst, $base, 0,0,0,0, $nw,$nh, $w,$h);
  imagedestroy($base);

  $wm = @imagecreatefrompng(WATERMARK_PATH);
  if (!$wm) {
    // solo resize
    if (!img_save($dst, $dest, $srcExt)) {
      imagedestroy($dst);
      throw new RuntimeException('Impossibile salvare web (resize)');
    }
    imagedestroy($dst);
    return;
  }

  imagesavealpha($wm, true);
  imagealphablending($wm, true);

  $wmW = imagesx($wm);
  $wmH = imagesy($wm);

  // watermark ~ 22% della larghezza
  $targetW = (int)round($nw * 0.22);
  $ratio = ($wmW > 0) ? ($targetW / $wmW) : 1.0;
  $tW = max(1, (int)round($wmW * $ratio));
  $tH = max(1, (int)round($wmH * $ratio));

  $wm2 = imagecreatetruecolor($tW, $tH);
  imagealphablending($wm2, false);
  imagesavealpha($wm2, true);
  $transparent2 = imagecolorallocatealpha($wm2, 0,0,0,127);
  imagefill($wm2, 0, 0, $transparent2);
  imagealphablending($wm2, true);

  imagecopyresampled($wm2, $wm, 0,0,0,0, $tW,$tH, $wmW,$wmH);
  imagedestroy($wm);

  $margin = defined('WATERMARK_MARGIN_PX') ? (int)WATERMARK_MARGIN_PX : 24;
  $x = max($margin, $nw - $tW - $margin);
  $y = max($margin, $nh - $tH - $margin);

  $opacity = defined('WATERMARK_OPACITY') ? (int)WATERMARK_OPACITY : 35;
  $opacity = max(0, min(100, $opacity));

  imagecopymerge($dst, $wm2, $x, $y, 0,0, $tW,$tH, $opacity);
  imagedestroy($wm2);

  if (!img_save($dst, $dest, $srcExt)) {
    imagedestroy($dst);
    throw new RuntimeException('Impossibile salvare web watermarked');
  }
  imagedestroy($dst);
}

/* =========================================================
 * Main
 * ========================================================= */

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
  }

  $eventId = (int)($_POST['event_id'] ?? 0);
  if ($eventId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing event_id']);
    exit;
  }

  if (!isset($_FILES['photos'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No files']);
    exit;
  }

  $files = normalize_files_array($_FILES['photos']);
  if (empty($files)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No files selected']);
    exit;
  }

  if (count($files) > $maxBatchFiles) {
    $files = array_slice($files, 0, $maxBatchFiles);
  }

  $col = cols_info();
  if (!$col['has_relative_path'] && !($col['has_original_path'] && $col['has_web_path'])) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Unsupported photos schema']);
    exit;
  }

  $st = db()->prepare("SELECT id, storage_folder FROM events WHERE id = :id LIMIT 1");
  $st->execute([':id' => $eventId]);
  $ev = $st->fetch();
  if (!$ev) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Event not found']);
    exit;
  }

  ensure_storage_root();

  $storageFolder = trim((string)$ev['storage_folder']);
  if ($storageFolder === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'storage_folder empty']);
    exit;
  }

  $baseDir = rtrim(STORAGE_ROOT, '/\\') . '/' . ltrim($storageFolder, '/\\');
  $origDir = $baseDir . '/originals';
  $webDir  = $baseDir . '/web';
  ensure_dir($origDir);
  ensure_dir($webDir);

  $stMax = db()->prepare("SELECT COALESCE(MAX(sort_order),0) AS m FROM photos WHERE event_id = :event_id");
  $stMax->execute([':event_id' => $eventId]);
  $sort = (int)($stMax->fetch()['m'] ?? 0);

  $inserted = 0;
  db()->beginTransaction();

  foreach ($files as $f) {
    $name = (string)$f['name'];
    $tmp  = (string)$f['tmp_name'];
    $size = (int)$f['size'];
    $errCode = (int)$f['error'];

    if ($errCode === UPLOAD_ERR_NO_FILE) continue;
    if ($errCode !== UPLOAD_ERR_OK) throw new RuntimeException("Errore upload file: $name (codice $errCode).");
    if (!is_uploaded_file($tmp)) throw new RuntimeException("File non valido: $name.");
    if ($size <= 0) throw new RuntimeException("File vuoto: $name.");
    if ($size > $maxFileBytes) throw new RuntimeException("File troppo grande: $name (max " . round($maxFileBytes/1024/1024) . "MB).");

    $safe = safe_basename($name);
    $ext = ext_of($safe);
    if (!in_array($ext, $allowedExt, true)) throw new RuntimeException("Estensione non supportata: $safe");

    $uniq = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    $origFile = $uniq . '_' . $safe;
    $webFile  = $uniq . '_' . $safe; // stessa estensione (coerenza mime_type)

    $origFull = $origDir . '/' . $origFile;
    $webFull  = $webDir  . '/' . $webFile;

    if (!@move_uploaded_file($tmp, $origFull)) throw new RuntimeException("Impossibile salvare file originale: $safe");

    // ✅ genera web con resize + watermark
    make_web_with_watermark($origFull, $webFull, $ext);

    $w = null; $h = null;
    $info = @getimagesize($webFull);
    if (is_array($info)) {
      $w = isset($info[0]) ? (int)$info[0] : null;
      $h = isset($info[1]) ? (int)$info[1] : null;
    }

    $sort += 1;

    $origRel = ltrim($storageFolder . '/originals/' . $origFile, '/\\');
    $webRel  = ltrim($storageFolder . '/web/' . $webFile, '/\\');

    if ($col['has_original_path'] && $col['has_web_path']) {
      $sql = "INSERT INTO photos (event_id, original_path, web_path, original_name, mime_type, file_size_bytes, width_px, height_px, sort_order, is_visible" . ($col['has_has_watermark'] ? ", has_watermark" : "") . ")
              VALUES (:event_id, :original_path, :web_path, :original_name, :mime_type, :size, :w, :h, :sort, 1" . ($col['has_has_watermark'] ? ", :wm" : "") . ")";
      $stmt = db()->prepare($sql);
      $params = [
        ':event_id'       => $eventId,
        ':original_path'  => $origRel,
        ':web_path'       => $webRel,
        ':original_name'  => $safe,
        ':mime_type'      => mime_from_ext($ext),
        ':size'           => $size,
        ':w'              => $w,
        ':h'              => $h,
        ':sort'           => $sort,
      ];
      if ($col['has_has_watermark']) $params[':wm'] = 1;
      $stmt->execute($params);
    } else {
      $sql = "INSERT INTO photos (event_id, relative_path, original_name, mime_type, file_size_bytes, width_px, height_px, sort_order, is_visible" . ($col['has_has_watermark'] ? ", has_watermark" : "") . ")
              VALUES (:event_id, :rel, :original_name, :mime_type, :size, :w, :h, :sort, 1" . ($col['has_has_watermark'] ? ", :wm" : "") . ")";
      $stmt = db()->prepare($sql);
      $params = [
        ':event_id'       => $eventId,
        ':rel'            => $webRel,
        ':original_name'  => $safe,
        ':mime_type'      => mime_from_ext($ext),
        ':size'           => $size,
        ':w'              => $w,
        ':h'              => $h,
        ':sort'           => $sort,
      ];
      if ($col['has_has_watermark']) $params[':wm'] = 1;
      $stmt->execute($params);
    }

    $inserted += 1;
  }

  db()->commit();

  echo json_encode(['ok' => true, 'inserted' => $inserted, 'batch_limit' => $maxBatchFiles]);
  exit;

} catch (Throwable $e) {
  if (db()->inTransaction()) db()->rollBack();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => APP_DEBUG ? $e->getMessage() : 'Upload error']);
  exit;
}
