<?php
/**
 * citaxph* — Lista Eventi
 * Proprietario: Federico Citarella
 *
 * Pagina pubblica che mostra gli eventi/album pubblicati (is_published = 1).
 * (Le foto originali NON vengono esposte; qui mostriamo solo metadata e, se presente,
 * una cover in futuro servita da un endpoint controllato.)
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Carico eventi pubblicati
$events = [];
$errorMsg = null;

try {
  $sql = "
    SELECT
      e.id,
      e.title,
      e.slug,
      e.event_date,
      e.location,
      e.description,
      e.storage_folder,
      e.cover_photo_id,
      e.created_at
    FROM events e
    WHERE e.is_published = 1
    ORDER BY
      (e.event_date IS NULL) ASC,
      e.event_date DESC,
      e.id DESC
  ";

  $stmt = db()->query($sql);
  $events = $stmt->fetchAll();
} catch (Throwable $e) {
  if (APP_DEBUG) {
    $errorMsg = $e->getMessage();
  } else {
    $errorMsg = "Impossibile caricare gli eventi in questo momento.";
  }
}

function fmt_date(?string $date): string {
  if (!$date) return '';
  // $date è in formato YYYY-MM-DD (DATE)
  $ts = strtotime($date);
  if ($ts === false) return '';
  return date('d/m/Y', $ts);
}
?><!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo h(SITE_NAME); ?> — Eventi</title>
  <meta name="description" content="Eventi e album fotografici di <?php echo h(SITE_NAME); ?>." />

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

  <!-- CSS -->
  <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
  <!-- NAV (coerente con index) -->
  <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container">
      <a class="navbar-brand fw-semibold" href="index.php"><?php echo h(SITE_NAME); ?></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-controls="navMain" aria-expanded="false" aria-label="Apri menu">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navMain">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link" href="index.php#portfolio">Portfolio</a></li>
          <li class="nav-item"><a class="nav-link" href="events.php">Eventi</a></li>
          <li class="nav-item"><a class="nav-link" href="index.php#contatti">Contatti</a></li>
        </ul>
        <div class="d-flex gap-2 ms-lg-3">
          <a class="btn btn-sm df-btn-ghost" href="index.php#contatti">Richiedi Preventivo</a>
        </div>
      </div>
    </div>
  </nav>

  <main class="pt-5">
    <section class="py-5">
      <div class="container pt-4">
        <div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-3">
          <div>
            <h1 class="h2 fw-semibold mb-1">Eventi</h1>
            <p class="text-white-50 mb-0">Album e reportage pubblicati.</p>
          </div>
          <a class="btn df-btn-ghost" href="index.php#contatti">Contattami</a>
        </div>

        <?php if ($errorMsg): ?>
          <div class="alert alert-danger"><?php echo h($errorMsg); ?></div>
        <?php endif; ?>

        <?php if (!$errorMsg && empty($events)): ?>
          <div class="df-card">
            <div class="card-body text-white-50">
              Nessun evento pubblicato al momento.
            </div>
          </div>
        <?php endif; ?>

        <?php if (!$errorMsg && !empty($events)): ?>
          <div class="row g-3">
            <?php foreach ($events as $ev): ?>
              <?php
                $title = (string)($ev['title'] ?? '');
                $location = (string)($ev['location'] ?? '');
                $dateStr = fmt_date($ev['event_date'] ?? null);
                $desc = (string)($ev['description'] ?? '');
                $slug = (string)($ev['slug'] ?? '');

                // Link alla pagina evento (da creare dopo)
                $href = 'event.php?slug=' . rawurlencode($slug);

                // Cover: in futuro useremo un endpoint tipo serve-photo.php?id=...
                $coverId = $ev['cover_photo_id'] ?? null;
              ?>
              <div class="col-md-6 col-lg-4">
                <a class="text-decoration-none" href="<?php echo h($href); ?>">
                  <div class="df-card h-100">
                    <div class="card-body">
                      <?php if ($coverId): ?>
                        <!-- Placeholder: implementeremo serve-photo.php più avanti -->
                        <div class="df-thumb mb-3"></div>
                      <?php else: ?>
                        <div class="df-thumb mb-3"></div>
                      <?php endif; ?>

                      <div class="d-flex align-items-start justify-content-between gap-2">
                        <h2 class="h5 mb-1 text-white"><?php echo h($title); ?></h2>
                        <?php if ($dateStr): ?>
                          <span class="badge text-bg-dark border" style="border-color: var(--df-border) !important;"><?php echo h($dateStr); ?></span>
                        <?php endif; ?>
                      </div>

                      <?php if ($location): ?>
                        <div class="text-white-50 small mb-2"><?php echo h($location); ?></div>
                      <?php else: ?>
                        <div class="text-white-50 small mb-2">&nbsp;</div>
                      <?php endif; ?>

                      <?php if ($desc): ?>
                        <p class="text-white-50 mb-0" style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">
                          <?php echo h($desc); ?>
                        </p>
                      <?php else: ?>
                        <p class="text-white-50 mb-0">Apri l’album per vedere i dettagli.</p>
                      <?php endif; ?>
                    </div>
                  </div>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      </div>
    </section>
  </main>

  <footer class="df-footer py-4">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="text-white-50 small">© <?php echo date('Y'); ?> <?php echo h(SITE_NAME); ?> — <?php echo h(SITE_OWNER); ?></div>
      <div class="d-flex gap-3 small">
        <a class="link-light link-underline-opacity-0 link-underline-opacity-50-hover" href="privacy.php">Privacy</a>
        <a class="link-light link-underline-opacity-0 link-underline-opacity-50-hover" href="index.php#contatti">Contatti</a>
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
