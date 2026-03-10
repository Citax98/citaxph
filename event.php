<?php
/**
 * citaxph* — Singolo Evento (con paginazione)
 * Proprietario: Federico Citarella
 *
 * Pagina pubblica: mostra dettagli evento + griglia foto (paginata).
 * Le immagini sono placeholder finché non implementiamo serve-photo.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$slug = trim((string) ($_GET['slug'] ?? ''));

// Paginazione
$page = (int) ($_GET['page'] ?? 1);
if ($page < 1)
  $page = 1;

$perPage = (int) ($_GET['per_page'] ?? 24);
if ($perPage < 6)
  $perPage = 24;
if ($perPage > 60)
  $perPage = 60;

$event = null;
$photos = [];
$totalPhotos = 0;
$totalPages = 0;
$errorMsg = null;

function fmt_date(?string $date): string
{
  if (!$date)
    return '';
  $ts = strtotime($date);
  if ($ts === false)
    return '';
  return date('d/m/Y', $ts);
}

function build_url(string $slug, int $page, int $perPage): string
{
  return 'event.php?slug=' . rawurlencode($slug) . '&page=' . $page . '&per_page=' . $perPage;
}

if ($slug === '') {
  $errorMsg = "Evento non specificato.";
} else {
  try {
    // 1) Carico evento (solo pubblicato)
    $sqlEv = "
      SELECT
        e.id,
        e.title,
        e.slug,
        e.event_date,
        e.location,
        e.description,
        e.cover_photo_id,
        e.allow_download
      FROM events e
      WHERE e.slug = :slug
        AND e.is_published = 1
      LIMIT 1
    ";
    $stmtEv = db()->prepare($sqlEv);
    $stmtEv->execute([':slug' => $slug]);
    $event = $stmtEv->fetch();

    if (!$event) {
      $errorMsg = "Evento non trovato o non pubblicato.";
    } else {
      $eventId = (int) $event['id'];

      // 2) Conteggio foto visibili
      $sqlCount = "
        SELECT COUNT(*) AS c
        FROM photos p
        WHERE p.event_id = :event_id
          AND p.is_visible = 1
      ";
      $stmtCount = db()->prepare($sqlCount);
      $stmtCount->execute([':event_id' => $eventId]);
      $totalPhotos = (int) ($stmtCount->fetch()['c'] ?? 0);

      $totalPages = (int) ceil($totalPhotos / $perPage);
      if ($totalPages < 1)
        $totalPages = 1;
      if ($page > $totalPages)
        $page = $totalPages;

      $sqlPhotos = "
  SELECT
    p.id,
    p.relative_path,
    p.original_name,
    p.width_px,
    p.height_px
  FROM photos p
  WHERE p.event_id = :event_id
    AND p.is_visible = 1
  ORDER BY p.sort_order ASC, p.id ASC
";

      $stmtPhotos = db()->prepare($sqlPhotos);
      $stmtPhotos->execute([':event_id' => $eventId]);
      $photos = $stmtPhotos->fetchAll();

    }
  } catch (Throwable $e) {
    $errorMsg = APP_DEBUG ? $e->getMessage() : "Impossibile caricare l'evento in questo momento.";
  }
}
?>
<!doctype html>
<html lang="it">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo h(SITE_NAME); ?> — <?php echo $event ? h((string) $event['title']) : 'Evento'; ?></title>
    <meta name="description" content="Album evento di <?php echo h(SITE_NAME); ?>." />

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- CSS -->
    <link rel="stylesheet"
        href="assets/css/style.css?v=<?php echo (int) @filemtime(__DIR__ . '/assets/css/style.css'); ?>">

</head>

<body>
    <!-- NAV -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="index.php"><?php echo h(SITE_NAME); ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain"
                aria-controls="navMain" aria-expanded="false" aria-label="Apri menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMain">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="portfolio.php">Portfolio</a></li>
                    <li class="nav-item"><a class="nav-link" href="events.php">Eventi</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#contatti">Contatti</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="pt-5">
        <section class="py-5">
            <div class="container pt-4">

                <div class="mb-3">
                    <a class="btn df-btn-ghost" href="events.php">← Torna agli eventi</a>
                </div>

                <?php if ($errorMsg): ?>
                <div class="alert alert-danger"><?php echo h($errorMsg); ?></div>
                <?php else: ?>
                <?php
          $title = (string) $event['title'];
          $location = (string) ($event['location'] ?? '');
          $dateStr = fmt_date($event['event_date'] ?? null);
          $desc = (string) ($event['description'] ?? '');
          ?>

                <div class="df-card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                            <div>
                                <h1 class="h3 fw-semibold mb-1 text-white"><?php echo h($title); ?></h1>
                                <div class="text-white-50">
                                    <?php if ($dateStr): ?><span><?php echo h($dateStr); ?></span><?php endif; ?>
                                    <?php if ($dateStr && $location): ?><span class="mx-2">•</span><?php endif; ?>
                                    <?php if ($location): ?><span><?php echo h($location); ?></span><?php endif; ?>
                                </div>
                            </div>

                            <div class="text-white-50 small">
                                <span class="badge text-bg-dark border"
                                    style="border-color: var(--df-border) !important;">
                                    <?php echo (int) $totalPhotos; ?> foto
                                </span>
                            </div>
                        </div>

                        <?php if ($desc): ?>
                        <p class="text-white-50 mt-3 mb-0"><?php echo nl2br(h($desc)); ?></p>
                        <?php endif; ?>

                        <div class="text-white-50 small mt-3">
                            Consegna originali: su richiesta (WeTransfer / link privato).
                        </div>
                    </div>
                </div>

                <!-- GRID FOTO -->
                <?php if ($totalPhotos === 0): ?>
                <div class="df-card">
                    <div class="card-body text-white-50">
                        Nessuna foto pubblicata per questo evento.
                    </div>
                </div>
                <?php else: ?>

                <div class="row g-3">
                    <?php foreach ($photos as $ph): ?>
                    <?php
                // Preview: usa serve-photo.php (WEB) e click apre modale
                // $imgSrc = 'serve-photo.php?id=' . (int)$ph['id'];
                ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="df-card h-100">
                            <div class="card-body">
                                <div class="df-thumb" style="overflow:hidden;">
                                    <img src="serve-photo.php?id=<?php echo (int) $ph['id']; ?>" class="w-100 js-photo"
                                        data-photo-id="<?php echo (int) $ph['id']; ?>" alt="" loading="lazy"
                                        style="display:block; height:220px; object-fit:cover; cursor:pointer;">
                                </div>
                                <div class="text-white-50 small mt-2">
                                    Foto #<?php echo (int) $ph['id']; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- PAGINAZIONE -->
                <nav class="mt-4" aria-label="Paginazione foto evento">
                    <ul class="pagination pagination-sm justify-content-center">
                        <?php
                $prev = $page - 1;
                $next = $page + 1;

                $disabledPrev = ($page <= 1);
                $disabledNext = ($page >= $totalPages);

                // Range pagine (finestra)
                $window = 2; // mostra +/-2
                $start = max(1, $page - $window);
                $end = min($totalPages, $page + $window);

                // Link helper
                function page_li(string $label, int $targetPage, bool $disabled, bool $active, string $slug, int $perPage): string
                {
                  $cls = 'page-item';
                  if ($disabled)
                    $cls .= ' disabled';
                  if ($active)
                    $cls .= ' active';
                  $href = $disabled ? '#' : build_url($slug, $targetPage, $perPage);
                  return '<li class="' . $cls . '"><a class="page-link" href="' . h($href) . '">' . h($label) . '</a></li>';
                }

                echo page_li('«', 1, $disabledPrev, false, $slug, $perPage);
                echo page_li('‹', $prev, $disabledPrev, false, $slug, $perPage);

                if ($start > 1) {
                  echo page_li('1', 1, false, ($page === 1), $slug, $perPage);
                  if ($start > 2) {
                    echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                  }
                }

                for ($p = $start; $p <= $end; $p++) {
                  echo page_li((string) $p, $p, false, ($p === $page), $slug, $perPage);
                }

                if ($end < $totalPages) {
                  if ($end < $totalPages - 1) {
                    echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                  }
                  echo page_li((string) $totalPages, $totalPages, false, ($page === $totalPages), $slug, $perPage);
                }

                echo page_li('›', $next, $disabledNext, false, $slug, $perPage);
                echo page_li('»', $totalPages, $disabledNext, false, $slug, $perPage);
                ?>
                    </ul>
                </nav>

                <?php endif; ?>
                <?php endif; ?>

            </div>
        </section>
    </main>

    <footer class="df-footer py-4">
        <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="text-white-50 small">© <?php echo date('Y'); ?> <?php echo h(SITE_NAME); ?> —
                <?php echo h(SITE_OWNER); ?>
            </div>
            <div class="d-flex gap-3 small">
                <a class="link-light link-underline-opacity-0 link-underline-opacity-50-hover"
                    href="events.php">Eventi</a>
                <a class="link-light link-underline-opacity-0 link-underline-opacity-50-hover"
                    href="index.php#contatti">Contatti</a>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>

    <!-- Photo Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-fixed">
            <div class="modal-content bg-dark">
                <div class="modal-body p-2">
                    <img id="photoModalImg" src="" alt="" style="width:100%; height:auto; display:block;">
                </div>
                <div class="modal-footer justify-content-between">
                    <a id="photoDownloadBtn" class="btn btn-primary" href="#" style="display:none;" download>Scarica
                        foto</a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const modalEl = document.getElementById("photoModal");
        const modal = new bootstrap.Modal(modalEl);
        const modalImg = document.getElementById("photoModalImg");
        const downloadBtn = document.getElementById("photoDownloadBtn");

        const allowDownload = <?php echo (int) ($event['allow_download'] ?? 0); ?> === 1;

        document.querySelectorAll(".js-photo").forEach(el => {
            el.addEventListener("click", () => {
                const id = el.dataset.photoId;

                // preview grande (WEB)
                modalImg.src = "serve-photo.php?id=" + encodeURIComponent(id);

                if (allowDownload) {
                    downloadBtn.style.display = "inline-block";
                    downloadBtn.href = "serve-photo.php?id=" + encodeURIComponent(id) +
                        "&download=1";
                } else {
                    downloadBtn.style.display = "none";
                    downloadBtn.href = "#";
                }

                modal.show();
            });
        });

        modalEl.addEventListener("hidden.bs.modal", () => {
            modalImg.src = "";
        });
    });
    </script>


</body>

</html>