<?php
/**
 * citaxph — Portfolio
 * Proprietario: Federico Citarella
 *
 * Pagina pubblica: chi sono + servizi (sezioni) con descrizione a sinistra
 * e carosello a destra (Bootstrap).
 *
 * NOTE:
 * - Inserisci le tue immagini nelle cartelle indicate (assets/img/portfolio/...).
 * - Ogni carosello usa immagini placeholder: sostituisci i path.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$sections = [
  [
    'id' => 'moto',
    'title' => 'Moto & Trackday',
    'subtitle' => 'Racing, paddock, action e dettagli.',
    'desc' => "Foto e contenuti pensati per il mondo racing: azione in pista, scatti dinamici, dettagli del mezzo e storytelling dell’intera giornata (paddock, box, atmosfera).",
    'bullets' => [
      'Scatti action + panning',
      'Dettagli moto / livrea / componenti',
      'Paddock & lifestyle racing',
      'Consegna preview online + originali su richiesta',
    ],
    'images' => [
      'assets/img/portfolio/moto/01.jpg',
      'assets/img/portfolio/moto/02.jpg',
      'assets/img/portfolio/moto/03.jpg',
    ],
  ],
  [
    'id' => 'eventi',
    'title' => 'Eventi',
    'subtitle' => 'Reportage pulito e naturale.',
    'desc' => "Copertura completa dell’evento con taglio reportage: momenti chiave, persone, dettagli e atmosfera. Ideale per feste, serate, inaugurazioni e eventi privati.",
    'bullets' => [
      'Reportage completo',
      'Scatti spontanei + ritratti',
      'Color grading coerente',
      'Selezione e consegna rapida',
    ],
    'images' => [
      'assets/img/portfolio/eventi/01.jpg',
      'assets/img/portfolio/eventi/02.jpg',
      'assets/img/portfolio/eventi/03.jpg',
    ],
  ],
  [
    'id' => 'ritratti',
    'title' => 'Ritratti',
    'subtitle' => 'Luce, vibe e identità.',
    'desc' => "Ritratti curati e moderni: singoli, coppie, creator, brand personale. Direzione semplice e risultati naturali (sia outdoor che indoor).",
    'bullets' => [
      'Outdoor / indoor',
      'Direzione posa (semplice)',
      'Look cinematic / clean',
      'Set di scatti selezionati',
    ],
    'images' => [
      'assets/img/portfolio/ritratti/01.jpg',
      'assets/img/portfolio/ritratti/02.jpg',
      'assets/img/portfolio/ritratti/03.jpg',
    ],
  ],
  [
    'id' => 'brand',
    'title' => 'Brand & Prodotti',
    'subtitle' => 'Contenuti per social e campagne.',
    'desc' => "Foto prodotto e contenuti per comunicazione: e‑commerce, social, campagne, ADV. Setup flessibile e coerenza estetica con il tuo brand.",
    'bullets' => [
      'Foto prodotto (clean) o ambientate',
      'Contenuti social (carousel / cover)',
      'Formato e crop ottimizzati',
      'Palette e mood coerenti al brand',
    ],
    'images' => [
      'assets/img/portfolio/brand/01.jpg',
      'assets/img/portfolio/brand/02.jpg',
      'assets/img/portfolio/brand/03.jpg',
    ],
  ],
];

function css_bust(string $path): string {
  $full = __DIR__ . '/' . ltrim($path, '/');
  $v = @filemtime($full);
  return $path . '?v=' . (int)($v ?: time());
}
?>
<!doctype html>
<html lang="it">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo h(SITE_NAME); ?> — Portfolio</title>
    <meta name="description" content="Portfolio e servizi fotografici di <?php echo h(SITE_NAME); ?>." />

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        crossorigin="anonymous">

    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo h(css_bust('assets/css/style.css')); ?>">
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
                <div class="d-flex gap-2 ms-lg-3">
                    <a href="https://wa.me/393248389883?text=Ciao%20Federico%2C%20ho%20visto%20il%20tuo%20portfolio%20su%20citaxph%20e%20vorrei%20richiedere%20un%20preventivo%20per%20un%20servizio%20fotografico."
                        class="btn df-btn-ghost" target="_blank" rel="noopener">
                        Richiedi un preventivo
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="pt-5">
        <!-- HERO / CHI SONO -->
        <section class="py-5">
            <div class="container pt-4">
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <h1 class="display-6 fw-semibold mb-2 text-white">Portfolio</h1>
                        <p class="text-white-50 mb-3">
                            Sono <?php echo h(SITE_OWNER); ?>. Realizzo contenuti fotografici con taglio moderno e
                            pulito,
                            dal mondo <strong>moto & racing</strong> al reportage eventi, ritratti e contenuti per
                            brand.
                        </p>

                        <div class="d-flex flex-wrap gap-2 mb-4">
                            <a class="btn df-btn-primary" href="index.php#contatti">Contattami</a>
                            <a class="btn df-btn-ghost" href="#servizi">Vai ai servizi</a>
                        </div>

                        <div class="text-white-50 small">
                            Consegna: preview online + originali su richiesta (link privato / WeTransfer).
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="df-card">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between gap-3">
                                    <div>
                                        <div class="text-white fw-semibold">Cosa ottieni</div>
                                        <div class="text-white-50 small">Un flusso semplice, risultati coerenti.</div>
                                    </div>
                                    <span class="badge text-bg-dark border"
                                        style="border-color: var(--df-border) !important;">citaxph</span>
                                </div>

                                <div class="df-divider my-3"></div>

                                <ul class="text-white-50 small mb-0" style="padding-left: 1rem;">
                                    <li>Scelta mood / stile</li>
                                    <li>Scatto + selezione</li>
                                    <li>Consegna preview e originali</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="df-divider my-4"></div>
            </div>
        </section>

        <!-- SERVIZI -->
        <section id="servizi" class="pb-5">
            <div class="container">
                <div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                        <h2 class="h3 fw-semibold mb-1 text-white">Servizi</h2>
                    </div>
                    <a class="btn df-btn-ghost" href="index.php#contatti">Richiedi Preventivo</a>
                </div>

                <?php foreach ($sections as $idx => $s): ?>
                <?php
            $carouselId = 'carousel_' . $s['id'];
          ?>

                <div class="df-section-events mt-3">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-5">
                            <div class="text-white-50 small mb-1"><?php echo h($s['subtitle']); ?></div>
                            <h3 class="h4 fw-semibold text-white mb-2"><?php echo h($s['title']); ?></h3>
                            <p class="text-white-50 mb-3"><?php echo h($s['desc']); ?></p>

                            <ul class="text-white-50 small mb-0" style="padding-left: 1rem;">
                                <?php foreach ($s['bullets'] as $b): ?>
                                <li><?php echo h($b); ?></li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="mt-3">
                                <a class="btn btn-sm df-btn-ghost" href="index.php#contatti">Chiedi info</a>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div id="<?php echo h($carouselId); ?>" class="carousel slide" data-bs-ride="carousel">
                                <div class="carousel-inner">
                                    <?php foreach ($s['images'] as $k => $img): ?>
                                    <div class="carousel-item <?php echo $k === 0 ? 'active' : ''; ?>">
                                        <div class="df-thumb" style="height: 360px;">
                                            <img src="<?php echo h($img); ?>" alt="" loading="lazy"
                                                style="width:100%;height:100%;object-fit:cover;display:block;">
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <button class="carousel-control-prev" type="button"
                                    data-bs-target="#<?php echo h($carouselId); ?>" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Precedente</span>
                                </button>
                                <button class="carousel-control-next" type="button"
                                    data-bs-target="#<?php echo h($carouselId); ?>" data-bs-slide="next">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Successivo</span>
                                </button>
                            </div>

                        </div>
                    </div>
                </div>

                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <footer class="df-footer py-4">
        <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="text-white-50 small">© <?php echo date('Y'); ?> <?php echo h(SITE_NAME); ?> —
                <?php echo h(SITE_OWNER); ?></div>
            <div class="d-flex gap-3 small">
                <a class="link-light link-underline-opacity-0 link-underline-opacity-50-hover"
                    href="events.php">Eventi</a>
                <a class="link-light link-underline-opacity-0 link-underline-opacity-50-hover"
                    href="index.php#contatti">Contatti</a>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous">
    </script>
</body>

</html>