<?php
/**
 * citaxph* — Admin / Foto (STEP 1: Upload multiplo + preview)
 * Proprietario: Federico Citarella
 *
 * ✅ Obiettivo STEP 1:
 * - Upload multiplo di immagini associate a un evento
 * - Preview client-side (JS) prima di inviare
 * - Salvataggio su disco in STORAGE_ROOT/<storage_folder>/
 *   - originals/  (file originali)
 *   - web/        (per ora COPIA identica; watermark+resize nel prossimo step)
 *
 * ✅ DB:
 * - Se la tabella photos ha colonne (original_path, web_path) le usa.
 * - Altrimenti usa la colonna legacy relative_path (salvando il path della versione web).
 *
 * NOTE:
 * - Le foto NON devono essere accessibili direttamente via URL.
 * - In locale puoi usare STORAGE_ROOT = PROJECT_ROOT . '/_storage'.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (empty($_SESSION['admin_user_id'])) {
  redirect('admin-login.php');
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

$eventId = (int)($_GET['event_id'] ?? ($_POST['event_id'] ?? 0));
$err = null;
$ok = null;

// Upload constraints
$allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
$maxFileBytes = 12 * 1024 * 1024; // 12MB per file (modifica se vuoi)

/* =========================================================
 * Helpers
 * ========================================================= */
function csrf_ok(string $csrf, string $posted): bool {
  return hash_equals($csrf, $posted);
}

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
  // Rileva se la tabella photos ha original_path / web_path (schema nuovo) o relative_path (schema legacy)
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
  // Trasforma $_FILES['photos'] multipli in array lineare
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
 * Load event selection
 * ========================================================= */
$events = [];
try {
  $events = db()->query("SELECT id, title, slug, event_date, is_published, storage_folder FROM events ORDER BY (event_date IS NULL) ASC, event_date DESC, id DESC")->fetchAll();
} catch (Throwable $e) {
  $err = APP_DEBUG ? $e->getMessage() : "Errore caricamento eventi.";
}

// Get current event
$currentEvent = null;
if ($eventId > 0) {
  try {
    $st = db()->prepare("SELECT id, title, slug, event_date, is_published, storage_folder FROM events WHERE id = :id LIMIT 1");
    $st->execute([':id' => $eventId]);
    $currentEvent = $st->fetch();
    if (!$currentEvent) {
      $eventId = 0;
    }
  } catch (Throwable $e) {
    $err = APP_DEBUG ? $e->getMessage() : "Errore caricamento evento.";
    $eventId = 0;
  }
}

/* =========================================================
 * Handle upload
 * ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$err) {
  $postedCsrf = (string)($_POST['csrf_token'] ?? '');
  if (!csrf_ok($csrf, $postedCsrf)) {
    $err = "Richiesta non valida (CSRF).";
  } else {
    $eventId = (int)($_POST['event_id'] ?? 0);
    if ($eventId <= 0) {
      $err = "Seleziona un evento.";
    } else {
      try {
        // Reload event (trust DB)
        $st = db()->prepare("SELECT id, storage_folder FROM events WHERE id = :id LIMIT 1");
        $st->execute([':id' => $eventId]);
        $ev = $st->fetch();
        if (!$ev) {
          throw new RuntimeException("Evento non trovato.");
        }

        ensure_storage_root();

        $storageFolder = trim((string)$ev['storage_folder']);
        if ($storageFolder === '') {
          throw new RuntimeException("storage_folder dell'evento è vuoto. Apri l'evento e salva.");
        }

        $baseDir = rtrim(STORAGE_ROOT, '/\\') . '/' . ltrim($storageFolder, '/\\');
        $origDir = $baseDir . '/originals';
        $webDir  = $baseDir . '/web';

        ensure_dir($origDir);
        ensure_dir($webDir);

        if (!isset($_FILES['photos'])) {
          throw new RuntimeException("Nessun file ricevuto.");
        }

        $files = normalize_files_array($_FILES['photos']);
        if (empty($files)) {
          throw new RuntimeException("Nessun file selezionato.");
        }

        $col = cols_info();
        if (!$col['has_relative_path'] && !($col['has_original_path'] && $col['has_web_path'])) {
          throw new RuntimeException("Schema tabella photos non supportato: manca relative_path oppure (original_path, web_path).");
        }

        // Ordinamento: aggiungiamo dopo l'ultimo sort_order esistente
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

          if ($errCode === UPLOAD_ERR_NO_FILE) {
            continue;
          }
          if ($errCode !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Errore upload file: $name (codice $errCode).");
          }
          if (!is_uploaded_file($tmp)) {
            throw new RuntimeException("File non valido: $name.");
          }
          if ($size <= 0) {
            throw new RuntimeException("File vuoto: $name.");
          }
          if ($size > $maxFileBytes) {
            throw new RuntimeException("File troppo grande: $name (max " . round($maxFileBytes/1024/1024) . "MB).");
          }

          $safe = safe_basename($name);
          $ext = ext_of($safe);
          if (!in_array($ext, $allowedExt, true)) {
            throw new RuntimeException("Estensione non supportata: $safe");
          }

          // Nome unico per evitare collisioni
          $uniq = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
          $origFile = $uniq . '_' . $safe;
          $webFile  = $uniq . '_' . $safe;

          $origFull = $origDir . '/' . $origFile;
          $webFull  = $webDir  . '/' . $webFile;

          // Salva original
          if (!@move_uploaded_file($tmp, $origFull)) {
            throw new RuntimeException("Impossibile salvare file originale: $safe");
          }

          // STEP 1: per ora la versione web è una COPIA identica.
          if (!@copy($origFull, $webFull)) {
            throw new RuntimeException("Impossibile creare copia web: $safe");
          }

          // Metadata immagini (best effort)
          $w = null; $h = null;
          $info = @getimagesize($webFull);
          if (is_array($info)) {
            $w = isset($info[0]) ? (int)$info[0] : null;
            $h = isset($info[1]) ? (int)$info[1] : null;
          }

          $sort += 1;

          // Relative paths for DB (relative to STORAGE_ROOT)
          $origRel = ltrim($storageFolder . '/originals/' . $origFile, '/\\');
          $webRel  = ltrim($storageFolder . '/web/' . $webFile, '/\\');

          // Insert
          if ($col['has_original_path'] && $col['has_web_path']) {
            $sql = "INSERT INTO photos (event_id, original_path, web_path, original_name, mime_type, file_size_bytes, width_px, height_px, sort_order, is_visible" . ($col['has_has_watermark'] ? ", has_watermark" : "") . ")
                    VALUES (:event_id, :original_path, :web_path, :original_name, :mime_type, :size, :w, :h, :sort, 1" . ($col['has_has_watermark'] ? ", :wm" : "") . ")";
            $stmt = db()->prepare($sql);
            $params = [
              ':event_id' => $eventId,
              ':original_path' => $origRel,
              ':web_path' => $webRel,
              ':original_name' => $safe,
              ':mime_type' => mime_from_ext($ext),
              ':size' => $size,
              ':w' => $w,
              ':h' => $h,
              ':sort' => $sort,
            ];
            if ($col['has_has_watermark']) $params[':wm'] = 0;
            $stmt->execute($params);
          } else {
            // schema legacy: salva la versione web in relative_path
            $sql = "INSERT INTO photos (event_id, relative_path, original_name, mime_type, file_size_bytes, width_px, height_px, sort_order, is_visible" . ($col['has_has_watermark'] ? ", has_watermark" : "") . ")
                    VALUES (:event_id, :rel, :original_name, :mime_type, :size, :w, :h, :sort, 1" . ($col['has_has_watermark'] ? ", :wm" : "") . ")";
            $stmt = db()->prepare($sql);
            $params = [
              ':event_id' => $eventId,
              ':rel' => $webRel,
              ':original_name' => $safe,
              ':mime_type' => mime_from_ext($ext),
              ':size' => $size,
              ':w' => $w,
              ':h' => $h,
              ':sort' => $sort,
            ];
            if ($col['has_has_watermark']) $params[':wm'] = 0;
            $stmt->execute($params);
          }

          $inserted += 1;
        }

        db()->commit();

        $ok = ($inserted === 0) ? "Nessun file caricato." : "Caricate $inserted foto con successo.";

      } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        $err = APP_DEBUG ? $e->getMessage() : "Errore durante l'upload.";
      }
    }
  }
}

/* =========================================================
 * Load recent photos for selected event (simple list)
 * ========================================================= */
$recentPhotos = [];
if (!$err && $eventId > 0) {
  try {
    $col = cols_info();
    if ($col['has_original_path'] && $col['has_web_path']) {
      $sql = "SELECT id, web_path AS rel, original_name, created_at FROM photos WHERE event_id = :event_id ORDER BY id DESC LIMIT 24";
    } else {
      $sql = "SELECT id, relative_path AS rel, original_name, created_at FROM photos WHERE event_id = :event_id ORDER BY id DESC LIMIT 24";
    }
    $st = db()->prepare($sql);
    $st->execute([':event_id' => $eventId]);
    $recentPhotos = $st->fetchAll();
  } catch (Throwable $e) {
    $recentPhotos = [];
  }
}
?><!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo h(SITE_NAME); ?> — Admin / Foto</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

  <!-- CSS -->
  <link rel="stylesheet" href="assets/css/style.css">

  <style>
    /* Solo micro-stili per preview (il resto resta in style.css) */
    .df-preview-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap:12px; }
    .df-preview-item { border: 1px solid rgba(255,255,255,.12); border-radius: 12px; padding: 10px; background: rgba(0,0,0,.20); }
    .df-preview-thumb { width: 100%; aspect-ratio: 1/1; object-fit: cover; border-radius: 10px; display:block; }
    .df-preview-meta { font-size: 12px; color: rgba(255,255,255,.7); margin-top: 8px; word-break: break-word; }
    .df-preview-actions { margin-top: 8px; display:flex; justify-content: space-between; gap:6px; }
    .df-preview-actions button { width: 100%; }
  </style>
</head>

<body>
  <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container">
      <a class="navbar-brand fw-semibold" href="admin-dashboard.php"><?php echo h(SITE_NAME); ?> <span class="text-white-50">/ Admin</span></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navAdmin" aria-controls="navAdmin" aria-expanded="false" aria-label="Apri menu">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navAdmin">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link" href="admin-dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="admin-events.php">Eventi</a></li>
          <li class="nav-item"><a class="nav-link active" href="admin-photos.php">Foto</a></li>
          <li class="nav-item"><a class="nav-link" href="index.php" target="_blank" rel="noopener">Vai al sito</a></li>
        </ul>
        <div class="d-flex gap-2 ms-lg-3">
          <a class="btn btn-sm df-btn-ghost" href="admin-logout.php">Logout</a>
        </div>
      </div>
    </div>
  </nav>

  <main class="pt-5">
    <section class="py-5">
      <div class="container pt-4">

        <div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-3">
          <div>
            <div class="text-white-50 small">Gestione</div>
            <h1 class="h3 fw-semibold mb-0 text-white">Foto</h1>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <a class="btn df-btn-ghost" href="admin-events.php">Gestisci eventi</a>
          </div>
        </div>

        <?php if ($ok): ?>
          <div class="alert alert-success"><?php echo h($ok); ?></div>
        <?php endif; ?>
        <?php if ($err): ?>
          <div class="alert alert-danger"><?php echo h($err); ?></div>
        <?php endif; ?>

        <div class="row g-3">
          <div class="col-lg-5">
            <div class="df-card">
              <div class="card-body">
                <h2 class="h5 text-white mb-3">Carica foto</h2>

                <form id="uploadForm" method="post" action="admin-photos.php" enctype="multipart/form-data" class="row g-3">
                  <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">

                  <div class="col-12">
                    <label class="form-label text-white-50">Seleziona evento *</label>
                    <select class="form-select" name="event_id" id="eventSelect" required>
                      <option value="">— Seleziona —</option>
                      <?php foreach ($events as $e): ?>
                        <?php
                          $id = (int)$e['id'];
                          $sel = ($eventId === $id) ? 'selected' : '';
                          $date = $e['event_date'] ? date('d/m/Y', strtotime((string)$e['event_date'])) : '—';
                          $pub = ((int)$e['is_published'] === 1) ? 'Pubblico' : 'Bozza';
                        ?>
                        <option value="<?php echo $id; ?>" <?php echo $sel; ?>>
                          #<?php echo $id; ?> — <?php echo h((string)$e['title']); ?> (<?php echo h($date); ?> • <?php echo $pub; ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <div class="text-white-50 small mt-1">
                      La cartella di destinazione viene calcolata da <code>storage_folder</code> dell'evento.
                    </div>
                  </div>

                  <div class="col-12">
                    <label class="form-label text-white-50">Seleziona foto (multiple) *</label>
                    <input class="form-control" type="file" name="photos[]" id="photosInput" accept=".jpg,.jpeg,.png,.webp" multiple required>
                    <div class="text-white-50 small mt-1">
                      Max <?php echo (int)round($maxFileBytes/1024/1024); ?>MB per file. Formati: jpg/jpeg/png/webp.
                    </div>
                  </div>

                  <div class="col-12">
                    <button class="btn btn-primary df-btn-primary w-100" type="submit" id="submitBtn" disabled>
                      Carica foto
                    </button>
                  </div>

                  <div class="text-white-50 small">
                    STEP 1: la versione <b>web</b> è una copia dell'originale. Nel prossimo step aggiungiamo watermark+resize automatici.
                  </div>
                </form>
              </div>
            </div>

            <?php if ($eventId > 0 && !empty($recentPhotos)): ?>
              <div class="df-card mt-3">
                <div class="card-body">
                  <h2 class="h6 text-white mb-2">Ultime foto caricate</h2>
                  <div class="text-white-50 small mb-2">Nel prossimo step creiamo lo serving (serve-photo.php) per vederle in pagina.</div>
                  <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($recentPhotos as $rp): ?>
                      <span class="badge text-bg-dark border">#<?php echo (int)$rp['id']; ?></span>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            <?php endif; ?>

          </div>

          <div class="col-lg-7">
            <div class="df-card">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                  <h2 class="h5 text-white mb-0">Anteprima prima dell'upload</h2>
                  <div class="d-flex gap-2">
                    <button class="btn btn-sm df-btn-ghost" type="button" id="resumeBtn" style="display:none;">Riprendi upload</button>
                    <button class="btn btn-sm df-btn-ghost" type="button" id="clearBtn" disabled>Svuota selezione</button>
                  </div>
                </div>

                <div class="text-white-50 small mt-2">
                  Qui vedi le foto selezionate (nome + peso) prima di caricarle.
                </div>

                <div id="previewInfo" class="text-white-50 small mt-2"></div>

                <div class="mt-3">
                  <div class="progress" style="height: 10px; background: rgba(255,255,255,.12);">
                    <div id="uploadProgressBar" class="progress-bar" role="progressbar" style="width:0%"></div>
                  </div>
                  <div id="uploadStatus" class="text-white-50 small mt-2"></div>
                </div>

                <div class="mt-3 df-preview-grid" id="previewGrid"></div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

  <script>
    (function(){
      const form = document.getElementById('uploadForm');
      const input = document.getElementById('photosInput');
      const grid = document.getElementById('previewGrid');
      const info = document.getElementById('previewInfo');
      const clearBtn = document.getElementById('clearBtn');
      const resumeBtn = document.getElementById('resumeBtn');
      const submitBtn = document.getElementById('submitBtn');
      const eventSelect = document.getElementById('eventSelect');

      const progressBar = document.getElementById('uploadProgressBar');
      const statusEl = document.getElementById('uploadStatus');

      const CSRF = document.querySelector('input[name="csrf_token"]').value;

      // Config batching (sequenziale automatico)
      const BATCH_SIZE = 50;
      const RETRY_ONCE = true;

      let dt = new DataTransfer();
      let isUploading = false;

      function formatBytes(bytes){
        const mb = bytes / (1024*1024);
        return mb.toFixed(2) + ' MB';
      }

      function updateSubmitState(){
        const hasFiles = dt.files && dt.files.length > 0;
        const hasEvent = !!eventSelect.value;
        submitBtn.disabled = !(hasFiles && hasEvent) || isUploading;
        clearBtn.disabled = !hasFiles || isUploading;
        eventSelect.disabled = isUploading;
        input.disabled = isUploading;
      }

      function escapeHtml(str){
        return str.replace(/[&<>"']/g, (m) => ({
          '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
        }[m]));
      }

      function setProgress(doneBatches, totalBatches){
        const pct = totalBatches === 0 ? 0 : Math.round((doneBatches / totalBatches) * 100);
        progressBar.style.width = pct + '%';
      }

      function setStatus(msg){
        statusEl.textContent = msg;
      }

      function render(){
        grid.innerHTML = '';

        let totalBytes = 0;
        Array.from(dt.files).forEach((file, idx) => {
          totalBytes += file.size;

          const card = document.createElement('div');
          card.className = 'df-preview-item';

          const img = document.createElement('img');
          img.className = 'df-preview-thumb';
          img.alt = file.name;

          const url = URL.createObjectURL(file);
          img.src = url;
          img.onload = () => URL.revokeObjectURL(url);

          const meta = document.createElement('div');
          meta.className = 'df-preview-meta';
          meta.innerHTML = `<div><b>${escapeHtml(file.name)}</b></div><div>${formatBytes(file.size)}</div>`;

          const actions = document.createElement('div');
          actions.className = 'df-preview-actions';

          const rm = document.createElement('button');
          rm.type = 'button';
          rm.className = 'btn btn-sm btn-outline-light';
          rm.textContent = 'Rimuovi';
          rm.disabled = isUploading;
          rm.addEventListener('click', () => {
            const ndt = new DataTransfer();
            Array.from(dt.files).forEach((f, i) => {
              if (i !== idx) ndt.items.add(f);
            });
            dt = ndt;
            input.files = dt.files;
            render();
          });

          actions.appendChild(rm);
          card.appendChild(img);
          card.appendChild(meta);
          card.appendChild(actions);
          grid.appendChild(card);
        });

        if (dt.files.length === 0){
          info.textContent = 'Nessuna foto selezionata.';
        } else {
          info.textContent = `Selezionate ${dt.files.length} foto — Totale ${formatBytes(totalBytes)}.`;
        }

        updateSubmitState();
      }

      input.addEventListener('change', () => {
        Array.from(input.files).forEach(f => dt.items.add(f));
        input.files = dt.files;
        render();
      });

      clearBtn.addEventListener('click', () => {
        if (isUploading) return;
        dt = new DataTransfer();
        input.files = dt.files;
        render();
        setProgress(0, 0);
        setStatus('');
      });

      eventSelect.addEventListener('change', updateSubmitState);

      function chunkArray(arr, size){
        const out = [];
        for (let i = 0; i < arr.length; i += size) out.push(arr.slice(i, i + size));
        return out;
      }

      async function postBatch(files, eventId){
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('event_id', String(eventId));
        files.forEach(f => fd.append('photos[]', f, f.name));

        const res = await fetch('admin-photos-upload.php', {
          method: 'POST',
          body: fd,
          credentials: 'same-origin'
        });

        let payload = null;
        try { payload = await res.json(); } catch(e) {}

        if (!res.ok || !payload || payload.ok !== true){
          const msg = (payload && payload.error) ? payload.error : ('Errore HTTP ' + res.status);
          throw new Error(msg);
        }
        return payload;
      }

      async function postBatchWithRetry(files, eventId){
        try {
          return await postBatch(files, eventId);
        } catch (e) {
          if (!RETRY_ONCE) throw e;
          return await postBatch(files, eventId);
        }
      }

      form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        if (isUploading) return;

        const eventId = Number(eventSelect.value || 0);
        if (!eventId) { setStatus('Seleziona un evento.'); return; }

        const all = Array.from(dt.files);
        if (all.length === 0) { setStatus('Seleziona almeno una foto.'); return; }

        const batches = chunkArray(all, BATCH_SIZE);
        const totalBatches = batches.length;

        isUploading = true;
        setProgress(0, totalBatches);
        setStatus(`Avvio upload: ${all.length} foto in ${totalBatches} batch (da ${BATCH_SIZE}).`);
        updateSubmitState();
        render();

        let done = 0;
        let insertedTotal = 0;

        try {
          for (let i = 0; i < totalBatches; i++){
            const batchNo = i + 1;
            setStatus(`Caricamento batch ${batchNo}/${totalBatches}...`);

            const payload = await postBatchWithRetry(batches[i], eventId);
            insertedTotal += Number(payload.inserted || 0);

            done += 1;
            setProgress(done, totalBatches);
            setStatus(`Completato batch ${batchNo}/${totalBatches}. Totale inserite: ${insertedTotal}.`);
          }

          dt = new DataTransfer();
          input.files = dt.files;
          render();

          setStatus(`Upload completato ✅ Inserite ${insertedTotal} foto.`);
        } catch (e) {
          setStatus(`Errore upload ❌ ${e.message || e}`);
        } finally {
          isUploading = false;
          updateSubmitState();
          render();
        }
      });

      render();
    })();
  </script>
</body>
</html>
