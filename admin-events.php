<?php
/**
 * citaxph* — Admin / Gestione Eventi
 * Proprietario: Federico Citarella
 *
 * CRUD base eventi (album):
 * - Lista eventi
 * - Crea / Modifica
 * - Elimina (con cascade su photos)
 * - Pubblica/Non pubblicare
 *
 * NOTE: pagina riservata (richiede login).
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

function events_cols_info(): array {
  $cols = db()->query("SHOW COLUMNS FROM events")->fetchAll();
  $names = array_map(fn($c) => (string)$c['Field'], $cols);
  return [
    'has_allow_download' => in_array('allow_download', $names, true),
  ];
}


$action = (string)($_GET['action'] ?? 'list'); // list | new | edit
$id = (int)($_GET['id'] ?? 0);

$err = null;
$ok = null;

/* =========================================================
 * Helpers
 * ========================================================= */
function slugify(string $text): string {
  $text = trim($text);
  $text = mb_strtolower($text, 'UTF-8');

  // translit
  if (function_exists('iconv')) {
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if ($t !== false) $text = $t;
  }

  $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '';
  $text = trim($text, '-');
  if ($text === '') $text = 'evento';
  return $text;
}

function parse_date_or_null(string $v): ?string {
  $v = trim($v);
  if ($v === '') return null;
  // expected YYYY-MM-DD from <input type="date">
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return null;
  return $v;
}

function storage_folder_from(string $dateOrNull, string $slug): string {
  $prefix = $dateOrNull ? $dateOrNull : date('Y-m-d');
  return 'events/' . $prefix . '_' . $slug;
}

function csrf_check(string $csrf, string $posted): bool {
  return hash_equals($csrf, $posted);
}

function bool_from_post(string $key): int {
  return isset($_POST[$key]) ? 1 : 0;
}

function fetch_event(int $id): ?array {
  $stmt = db()->prepare("SELECT * FROM events WHERE id = :id LIMIT 1");
  $stmt->execute([':id' => $id]);
  $row = $stmt->fetch();
  return $row ?: null;
}

/* =========================================================
 * POST actions: save / delete / toggle publish
 * ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedCsrf = (string)($_POST['csrf_token'] ?? '');
  if (!csrf_check($csrf, $postedCsrf)) {
    $err = "Richiesta non valida (CSRF).";
  } else {
    $op = (string)($_POST['op'] ?? '');

    try {
      if ($op === 'save') {
        $eventId = (int)($_POST['id'] ?? 0);

        $title = trim((string)($_POST['title'] ?? ''));
        $slug  = trim((string)($_POST['slug'] ?? ''));
        $date  = parse_date_or_null((string)($_POST['event_date'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $isPublished = bool_from_post('is_published');
        $allowDownload = isset($_POST['allow_download']) ? 1 : 0;

        if ($title === '') {
          throw new RuntimeException("Il titolo è obbligatorio.");
        }

        if ($slug === '') {
          $slug = slugify($title);
        } else {
          $slug = slugify($slug);
        }

        // Se create, calcolo storage_folder di default; se edit, lo mantengo (a meno che sia vuoto)
        $storageFolder = trim((string)($_POST['storage_folder'] ?? ''));
        if ($storageFolder === '') {
          $storageFolder = storage_folder_from($date ?? '', $slug);
        }

        // Unicità slug (a livello applicativo per messaggio migliore)
        $q = "SELECT id FROM events WHERE slug = :slug" . ($eventId > 0 ? " AND id <> :id" : "") . " LIMIT 1";
        $st = db()->prepare($q);

        $params = [':slug' => $slug];
        if ($eventId > 0) {
          $params[':id'] = $eventId;
        }

        $st->execute($params);
        $exists = $st->fetch();

        if ($exists) {
          throw new RuntimeException("Slug già esistente. Cambia slug o titolo.");
        }

        if ($eventId > 0) {
          // Update
          $sql = "
            UPDATE events
SET
  title = :title,
  slug = :slug,
  event_date = :event_date,
  location = :location,
  description = :description,
  is_published = :is_published,
  allow_download = :allow_download,
  storage_folder = :storage_folder
WHERE id = :id
          ";
          $stmt = db()->prepare($sql);
          $stmt->execute([
            ':title' => $title,
            ':slug' => $slug,
            ':event_date' => $date,
            ':location' => ($location !== '' ? $location : null),
            ':description' => ($desc !== '' ? $desc : null),
            ':is_published' => $isPublished,
            ':allow_download' => $allowDownload,
            ':storage_folder' => $storageFolder,
            ':id' => $eventId
          ]);
          $ok = "Evento aggiornato.";
          $action = 'edit';
          $id = $eventId;
        } else {
          // Insert
          $sql = "
            INSERT INTO events (
  title,
  slug,
  event_date,
  location,
  description,
  is_published,
  allow_download,
  storage_folder,
  cover_photo_id
)
VALUES (
  :title,
  :slug,
  :event_date,
  :location,
  :description,
  :is_published,
  :allow_download,
  :storage_folder,
  NULL
)
          ";
          $stmt = db()->prepare($sql);
          $stmt->execute([
            ':title' => $title,
            ':slug' => $slug,
            ':event_date' => $date,
            ':location' => ($location !== '' ? $location : null),
            ':description' => ($desc !== '' ? $desc : null),
            ':is_published' => $isPublished,
            ':allow_download' => $allowDownload,
            ':storage_folder' => $storageFolder,
          ]);
          $newId = (int)db()->lastInsertId();
          $ok = "Evento creato.";
          $action = 'edit';
          $id = $newId;
        }

      } elseif ($op === 'delete') {
        $eventId = (int)($_POST['id'] ?? 0);
        if ($eventId <= 0) throw new RuntimeException("Evento non valido.");

        // Elimina evento (cascade elimina photos)
        $stmt = db()->prepare("DELETE FROM events WHERE id = :id");
        $stmt->execute([':id' => $eventId]);
        $ok = "Evento eliminato.";
        $action = 'list';
        $id = 0;

      } elseif ($op === 'toggle_publish') {
        $eventId = (int)($_POST['id'] ?? 0);
        if ($eventId <= 0) throw new RuntimeException("Evento non valido.");

        $stmt = db()->prepare("UPDATE events SET is_published = 1 - is_published WHERE id = :id");
        $stmt->execute([':id' => $eventId]);
        $ok = "Stato pubblicazione aggiornato.";
        $action = 'list';
        $id = 0;

      } else {
        throw new RuntimeException("Operazione non valida.");
      }
    } catch (Throwable $e) {
      $err = APP_DEBUG ? $e->getMessage() : "Errore operazione.";
    }
  }
}

/* =========================================================
 * View data
 * ========================================================= */
$event = null;
if ($action === 'edit' && $id > 0) {
  try {
    $event = fetch_event($id);
    if (!$event) {
      $err = $err ?? "Evento non trovato.";
      $action = 'list';
      $id = 0;
    }
  } catch (Throwable $e) {
    $err = APP_DEBUG ? $e->getMessage() : "Errore caricamento evento.";
    $action = 'list';
  }
}

// Lista eventi
$events = [];
if ($action === 'list') {
  try {
    $sql = "
      SELECT
        e.id, e.title, e.slug, e.event_date, e.location, e.is_published,
        (SELECT COUNT(*) FROM photos p WHERE p.event_id = e.id) AS photos_count,
        e.updated_at
      FROM events e
      ORDER BY (e.event_date IS NULL) ASC, e.event_date DESC, e.id DESC
    ";
    $events = db()->query($sql)->fetchAll();
  } catch (Throwable $e) {
    $err = APP_DEBUG ? $e->getMessage() : "Errore caricamento lista.";
  }
}

// default new form
if ($action === 'new') {
  $event = [
    'id' => 0,
    'title' => '',
    'slug' => '',
    'event_date' => null,
    'location' => null,
    'description' => null,
    'is_published' => 1,
    'storage_folder' => '',
  ];
}
?>
<!doctype html>
<html lang="it">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo h(SITE_NAME); ?> — Admin / Eventi</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="admin-dashboard.php"><?php echo h(SITE_NAME); ?> <span
                    class="text-white-50">/ Admin</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navAdmin"
                aria-controls="navAdmin" aria-expanded="false" aria-label="Apri menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navAdmin">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="admin-dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="admin-events.php">Eventi</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin-photos.php">Foto</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php" target="_blank" rel="noopener">Vai al
                            sito</a></li>
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
                        <h1 class="h3 fw-semibold mb-0 text-white">Eventi</h1>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a class="btn df-btn-ghost" href="admin-events.php">Lista</a>
                        <a class="btn btn-primary df-btn-primary" href="admin-events.php?action=new">+ Nuovo evento</a>
                    </div>
                </div>

                <?php if ($ok): ?>
                <div class="alert alert-success"><?php echo h($ok); ?></div>
                <?php endif; ?>
                <?php if ($err): ?>
                <div class="alert alert-danger"><?php echo h($err); ?></div>
                <?php endif; ?>

                <?php if ($action === 'list'): ?>
                <div class="df-card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:90px;">ID</th>
                                        <th>Titolo</th>
                                        <th>Data</th>
                                        <th>Slug</th>
                                        <th>Foto</th>
                                        <th>Stato</th>
                                        <th class="text-end">Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($events)): ?>
                                    <tr>
                                        <td colspan="7" class="text-white-50">Nessun evento. Clicca “Nuovo evento”.</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($events as $e): ?>
                                    <?php
                          $date = $e['event_date'] ? date('d/m/Y', strtotime((string)$e['event_date'])) : '—';
                          $published = (int)$e['is_published'] === 1;
                        ?>
                                    <tr>
                                        <td class="text-white-50"><?php echo (int)$e['id']; ?></td>
                                        <td class="text-white">
                                            <div class="fw-semibold"><?php echo h((string)$e['title']); ?></div>
                                            <div class="text-white-50 small">
                                                <?php echo h((string)($e['location'] ?? '')); ?></div>
                                        </td>
                                        <td class="text-white-50"><?php echo h($date); ?></td>
                                        <td class="text-white-50"><code><?php echo h((string)$e['slug']); ?></code></td>
                                        <td class="text-white-50"><?php echo (int)$e['photos_count']; ?></td>
                                        <td>
                                            <?php if ($published): ?>
                                            <span class="badge text-bg-success">Pubblico</span>
                                            <?php else: ?>
                                            <span class="badge text-bg-secondary">Bozza</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2 flex-wrap">
                                                <a class="btn btn-sm df-btn-ghost"
                                                    href="admin-events.php?action=edit&id=<?php echo (int)$e['id']; ?>">Modifica</a>
                                                <a class="btn btn-sm df-btn-ghost" target="_blank" rel="noopener"
                                                    href="event.php?slug=<?php echo rawurlencode((string)$e['slug']); ?>">Apri</a>

                                                <form method="post" action="admin-events.php" class="d-inline">
                                                    <input type="hidden" name="csrf_token"
                                                        value="<?php echo h($csrf); ?>">
                                                    <input type="hidden" name="op" value="toggle_publish">
                                                    <input type="hidden" name="id" value="<?php echo (int)$e['id']; ?>">
                                                    <button
                                                        class="btn btn-sm <?php echo $published ? 'btn-outline-warning' : 'btn-outline-success'; ?>"
                                                        type="submit">
                                                        <?php echo $published ? 'Nascondi' : 'Pubblica'; ?>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php elseif ($action === 'new' || $action === 'edit'): ?>
                <?php
            $isEdit = ($action === 'edit' && !empty($event['id']));
            $eventId = (int)($event['id'] ?? 0);
          ?>
                <div class="row g-3">
                    <div class="col-lg-8">
                        <div class="df-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                    <div>
                                        <div class="text-white-50 small">
                                            <?php echo $isEdit ? 'Modifica' : 'Creazione'; ?></div>
                                        <h2 class="h4 mb-0 text-white">
                                            <?php echo $isEdit ? 'Evento #' . $eventId : 'Nuovo evento'; ?></h2>
                                    </div>
                                    <?php if ($isEdit): ?>
                                    <a class="btn btn-sm df-btn-ghost" target="_blank" rel="noopener"
                                        href="event.php?slug=<?php echo rawurlencode((string)$event['slug']); ?>">Apri
                                        pagina pubblica</a>
                                    <?php endif; ?>
                                </div>

                                <form method="post"
                                    action="admin-events.php?action=<?php echo $isEdit ? 'edit&id=' . $eventId : 'new'; ?>"
                                    class="row g-3">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
                                    <input type="hidden" name="op" value="save">
                                    <input type="hidden" name="id" value="<?php echo $eventId; ?>">

                                    <div class="col-12">
                                        <label class="form-label text-white-50">Titolo *</label>
                                        <input class="form-control" name="title"
                                            value="<?php echo h((string)($event['title'] ?? '')); ?>" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label text-white-50">Data evento</label>
                                        <input class="form-control" type="date" name="event_date"
                                            value="<?php echo h((string)($event['event_date'] ?? '')); ?>">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label text-white-50">Luogo</label>
                                        <input class="form-control" name="location"
                                            value="<?php echo h((string)($event['location'] ?? '')); ?>">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label text-white-50">Slug (URL)</label>
                                        <input class="form-control" name="slug"
                                            value="<?php echo h((string)($event['slug'] ?? '')); ?>"
                                            placeholder="lascia vuoto per auto">
                                        <div class="text-white-50 small mt-1">Esempio:
                                            <code>matrimonio-luca-maria</code>. Deve essere unico.
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label text-white-50">Cartella storage</label>
                                        <input class="form-control" name="storage_folder"
                                            value="<?php echo h((string)($event['storage_folder'] ?? '')); ?>"
                                            placeholder="events/2026-01-29_nome-evento">
                                        <div class="text-white-50 small mt-1">Dove finiranno le foto (relativo a
                                            <code>STORAGE_ROOT</code>).
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label text-white-50">Descrizione</label>
                                        <textarea class="form-control" name="description"
                                            rows="4"><?php echo h((string)($event['description'] ?? '')); ?></textarea>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="allow_download"
                                            name="allow_download" value="1" <?php if
  (!empty($editingEvent) && (int) $editingEvent['allow_download'] === 1)
    echo 'checked'; ?>>
                                        <label class="form-check-label" for="allow_download">
                                            Consenti download foto
                                        </label>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-check-label text-white-50">
                                            <input class="form-check-input me-2" type="checkbox" name="is_published"
                                                <?php echo ((int)($event['is_published'] ?? 0) === 1) ? 'checked' : ''; ?>>
                                            Pubblica evento (visibile in <code>events.php</code>)
                                        </label>
                                    </div>

                                    <div class="col-12 d-flex gap-2 flex-wrap">
                                        <button class="btn btn-primary df-btn-primary"
                                            type="submit"><?php echo $isEdit ? 'Salva modifiche' : 'Crea evento'; ?></button>
                                        <a class="btn df-btn-ghost" href="admin-events.php">Annulla</a>
                                        <?php if ($isEdit): ?>
                                        <a class="btn df-btn-ghost"
                                            href="admin-photos.php?event_id=<?php echo $eventId; ?>">Carica foto per
                                            questo evento</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="df-card h-100">
                            <div class="card-body">
                                <h3 class="h6 text-white mb-2">Azioni</h3>
                                <div class="d-grid gap-2">
                                    <a class="btn df-btn-ghost" href="admin-events.php">Torna alla lista</a>
                                    <a class="btn df-btn-ghost" href="admin-dashboard.php">Dashboard</a>
                                </div>

                                <?php if ($isEdit): ?>
                                <hr style="border-color: var(--df-border);">
                                <h3 class="h6 text-white mb-2">Elimina</h3>
                                <div class="text-white-50 small mb-2">
                                    Elimina evento e tutte le foto collegate (cascade).
                                </div>
                                <form method="post" action="admin-events.php"
                                    onsubmit="return confirm('Eliminare definitivamente questo evento? Verranno eliminate anche le foto collegate nel DB.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
                                    <input type="hidden" name="op" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $eventId; ?>">
                                    <button class="btn btn-outline-danger w-100" type="submit">Elimina evento</button>
                                </form>
                                <?php endif; ?>

                                <hr style="border-color: var(--df-border);">
                                <div class="text-white-50 small">
                                    Suggerimento: lo <b>slug</b> diventa parte dell’URL pubblico:
                                    <code>event.php?slug=...</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
</body>

</html>