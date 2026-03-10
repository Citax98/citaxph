<?php
// citaxph* — Home (public)
// Proprietario: Federico Citarella
// NOTE: Pagina pubblica. Le gallery sono accessibili tramite events.php e singolo evento (event.php).

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Ultimi eventi pubblicati (con cover fallback alla prima foto visibile)
$latestEvents = [];
$latestEventsError = null;
$stats = ['events' => 0, 'photos' => 0];

try {
    // stats rapide
    $stats['events'] = (int) (db()->query("SELECT COUNT(*) AS c FROM events WHERE is_published = 1")->fetch()['c'] ?? 0);
    $stats['photos'] = (int) (db()->query("SELECT COUNT(*) AS c FROM photos WHERE is_visible = 1")->fetch()['c'] ?? 0);

    $sql = "
    SELECT
      e.id,
      e.title,
      e.slug,
      e.event_date,
      e.location,
      COALESCE(
        e.cover_photo_id,
        (SELECT p.id
         FROM photos p
         WHERE p.event_id = e.id AND p.is_visible = 1
         ORDER BY p.sort_order ASC, p.id ASC
         LIMIT 1)
      ) AS cover_photo_id
    FROM events e
    WHERE e.is_published = 1
    ORDER BY
      (e.event_date IS NULL) ASC,
      e.event_date DESC,
      e.id DESC
    LIMIT 6
  ";
    $latestEvents = db()->query($sql)->fetchAll() ?: [];
} catch (Throwable $e) {
    $latestEventsError = APP_DEBUG ? $e->getMessage() : 'Impossibile caricare i contenuti in questo momento.';
}

function fmt_date(?string $date): string
{
    if (!$date)
        return '';
    $ts = strtotime($date);
    if ($ts === false)
        return '';
    return date('d/m/Y', $ts);
}
?>
<!doctype html>
<html lang="it">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>CITAXPH — Fotografia Eventi & Shooting</title>
    <meta name="description"
        content="Shooting, eventi e reportage. Gallerie clienti protette con accesso tramite codice." />

    <!-- Bootstrap 5 (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <!-- NAV -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="index.php">CITAXPH</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain"
                aria-controls="navMain" aria-expanded="false" aria-label="Apri menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMain">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="portfolio.php">Portfolio</a></li>
                    <li class="nav-item"><a class="nav-link" href="#servizi">Servizi</a></li>
                    <li class="nav-item"><a class="nav-link" href="events.php">Eventi</a></li>
                    <li class="nav-item"><a class="nav-link" href="#chi-sono">Chi sono</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contatti">Contatti</a></li>
                </ul>
                <div class="d-flex gap-2 ms-lg-3">
                    <a href="https://wa.me/393331234567?text=Ciao%20Federico%2C%20ho%20visto%20il%20tuo%20portfolio%20su%20citaxph%20e%20vorrei%20richiedere%20un%20preventivo%20per%20un%20servizio%20fotografico."
                        class="btn df-btn-ghost" target="_blank" rel="noopener">
                        Richiedi un preventivo
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- HERO -->
    <header class="df-hero">
        <div class="container pt-5">
            <div class="df-grid align-items-stretch">
                <div class="df-hero-left">
                    <span class="df-chip mb-3">Eventi • Shooting </span>
                    <h1 class="display-5 fw-bold mb-3">Foto a tutto Gas!</h1>
                    <p class="lead text-white-50 mb-4">
                        Racconto l’azione quando conta davvero.
                        Scatti che fermano il momento, anche quando tutto corre.

                    </p>

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-primary df-btn-primary btn-lg" href="#contatti">Prenota uno shooting</a>
                        <a class="btn df-btn-ghost btn-lg" href="events.php">Accedi alla gallery degli eventi</a>
                    </div>

                    <div class="df-kpi">
                        <div class="df-card px-3 py-2"><b>Consegna</b> 24–72h*</div>
                        <div class="df-card px-3 py-2"><b>Watermark</b> su anteprime</div>
                    </div>
                    <small class="text-white-50 d-block mt-2">*In base al tipo di evento e volume di scatti.</small>
                </div>

                <div class="df-hero-right">
                    <div class="df-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <div class="text-white-50 small">In evidenza</div>
                                    <div class="h5 mb-0">Ultimi lavori</div>
                                </div>
                                <span class="badge text-bg-dark border"
                                    style="border-color: var(--df-border) !important;">Preview</span>
                            </div>

                            <div class="row g-3">
                                <?php
                                // Mostra fino a 3 anteprime dagli ultimi eventi
                                $heroItems = array_slice($latestEvents, 0, 3);
                                ?>
                                <?php if (empty($heroItems)): ?>
                                    <div class="col-12">
                                        <div class="df-thumb"></div>
                                    </div>
                                    <div class="col-6">
                                        <div class="df-thumb" style="height:120px;"></div>
                                    </div>
                                    <div class="col-6">
                                        <div class="df-thumb" style="height:120px;"></div>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($heroItems as $i => $ev): ?>
                                        <?php
                                        $coverId = $ev['cover_photo_id'] ?? null;
                                        $href = 'event.php?slug=' . rawurlencode((string) $ev['slug']);
                                        $h = ($i === 0) ? '220px' : '120px';
                                        ?>
                                        <div class="<?php echo ($i === 0) ? 'col-12' : 'col-6'; ?>">
                                            <a href="<?php echo h($href); ?>" class="text-decoration-none d-block">
                                                <div class="df-thumb" style="height:<?php echo $h; ?>; overflow:hidden;">
                                                    <?php if ($coverId): ?>
                                                        <img src="serve-photo.php?id=<?php echo (int) $coverId; ?>" alt=""
                                                            loading="lazy"
                                                            style="width:100%; height:100%; object-fit:cover; display:block;">
                                                    <?php endif; ?>
                                                </div>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($heroItems) === 1): ?>
                                        <div class="col-6">
                                            <div class="df-thumb" style="height:120px;"></div>
                                        </div>
                                        <div class="col-6">
                                            <div class="df-thumb" style="height:120px;"></div>
                                        </div>
                                    <?php elseif (count($heroItems) === 2): ?>
                                        <div class="col-6">
                                            <div class="df-thumb" style="height:120px;"></div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <div class="mt-3 text-white-50 small">
                                <?php if ($latestEventsError): ?>
                                    <?php echo h($latestEventsError); ?>
                                <?php else: ?>
                                    <?php echo (int) $stats['events']; ?> eventi pubblicati •
                                    <?php echo (int) $stats['photos']; ?> foto online
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="df-divider"></div>
        </div>
    </header>

    <!-- PORTFOLIO -->
    <section id="portfolio" class="pb-5">
        <div class="container">
            <div class="row g-3">

                <div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                        <h3 class="h4 fw-semibold mb-1">Ultimi eventi</h3>
                        <p class="text-white-50 mb-0">Anteprime dall’archivio eventi (accesso pubblico alle preview).
                        </p>
                    </div>
                    <a class="btn df-btn-ghost" href="events.php">Vedi tutti gli eventi</a>
                </div>

                <?php if (!empty($latestEvents)): ?>
                    <div class="row g-3">
                        <?php foreach ($latestEvents as $ev): ?>
                            <?php
                            $title = (string) ($ev['title'] ?? '');
                            $location = (string) ($ev['location'] ?? '');
                            $dateStr = fmt_date($ev['event_date'] ?? null);
                            $slug = (string) ($ev['slug'] ?? '');
                            $href = 'event.php?slug=' . rawurlencode($slug);
                            $coverId = $ev['cover_photo_id'] ?? null;
                            ?>
                            <div class="col-6 col-md-4 col-lg-3">
                                <a class="text-decoration-none" href="<?php echo h($href); ?>">
                                    <div class="df-card h-100">
                                        <div class="card-body">
                                            <div class="df-thumb mb-3" style="overflow:hidden; height:180px;">
                                                <?php if ($coverId): ?>
                                                    <img src="serve-photo.php?id=<?php echo (int) $coverId; ?>" alt=""
                                                        loading="lazy"
                                                        style="width:100%; height:100%; object-fit:cover; display:block;">
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex align-items-start justify-content-between gap-2">
                                                <div class="text-white fw-semibold"
                                                    style="line-height:1.2; display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                                                    <?php echo h($title); ?>
                                                </div>
                                                <?php if ($dateStr): ?>
                                                    <span class="badge text-bg-dark border"
                                                        style="border-color: var(--df-border) !important;"><?php echo h($dateStr); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($location): ?>
                                                <div class="text-white-50 small mt-1"><?php echo h($location); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="df-card">
                        <div class="card-body text-white-50">
                            <?php echo $latestEventsError ? h($latestEventsError) : 'Nessun evento pubblicato al momento.'; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <div class="df-divider"></div>
    <!-- SERVIZI -->
    <section id="servizi" class="py-5">
        <div class="container">
            <h2 class="h3 fw-semibold mb-1">Servizi</h2>
            <p class="text-white-50 mb-4">Pacchetti personalizzabili in base alle esigenze.</p>

            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="df-card h-100">
                        <div class="card-body">
                            <h3 class="h5">Evento</h3>
                            <ul class="text-white-50 mb-3">
                                <li>Copertura completa</li>
                                <li>Selezione + editing</li>
                                <li>Gallery protetta per invitati</li>
                            </ul>
                            <a href="#contatti" class="btn btn-sm df-btn-ghost">Chiedi disponibilità</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="df-card h-100">
                        <div class="card-body">
                            <h3 class="h5">Shooting</h3>
                            <ul class="text-white-50 mb-3">
                                <li>Ritratto / lifestyle</li>
                                <li>Consulenza location</li>
                                <li>Consegna digitale</li>
                            </ul>
                            <a href="#contatti" class="btn btn-sm df-btn-ghost">Prenota shooting</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="df-card h-100">
                        <div class="card-body">
                            <h3 class="h5">Brand</h3>
                            <ul class="text-white-50 mb-3">
                                <li>Foto prodotto</li>
                                <li>Contenuti social</li>
                                <li>Coerenza di stile</li>
                            </ul>
                            <a href="#contatti" class="btn btn-sm df-btn-ghost">Parliamone</a>
                        </div>
                    </div>
                </div>
            </div>

            <small class="text-white-50 d-block mt-3">
                Nota: le gallerie clienti mostrano solo versioni ottimizzate (non gli originali).
            </small>
        </div>
    </section>
    <div class="df-divider"></div>
    <!-- CHI SONO -->
    <section id="chi-sono" class="py-5">
        <div class="container">
            <div class="row g-4 align-items-center">

                <div class="col-lg-6">
                    <div class="df-card h-100">
                        <div class="card-body">
                            <div class="text-white-50 small mb-2">Chi sono</div>

                            <h2 class="h3 fw-semibold mb-3">
                                Ciao, sono <?php echo h(SITE_OWNER); ?>.
                            </h2>

                            <p class="text-white-50 mb-3">
                                Sono un fotografo con un approccio orientato al <strong>reportage</strong> e allo
                                <strong>storytelling visivo</strong>.
                                Mi occupo principalmente di <strong>moto, trackday ed eventi</strong>, raccontando
                                l’azione e le emozioni in modo naturale e diretto.
                            </p>

                            <p class="text-white-50 mb-3">
                                Lavoro sia su <strong>progetti personali</strong> che su <strong>collaborazioni con
                                    brand</strong>,
                                creando contenuti pensati per comunicazione, social e utilizzo editoriale.
                            </p>

                            <div class="row g-2 text-white-50 small mb-4">
                                <div class="col-12 col-md-6">• Fotografia action & racing</div>
                                <div class="col-12 col-md-6">• Eventi e reportage</div>
                                <div class="col-12 col-md-6">• Ritratti ambientati</div>
                                <div class="col-12 col-md-6">• Contenuti per brand</div>
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <a href="portfolio.php" class="btn df-btn-primary">
                                    Guarda il portfolio
                                </a>
                                <a href="index.php#contatti" class="btn df-btn-ghost">
                                    Contattami
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="df-card h-100 overflow-hidden">
                        <img src="assets/img/federico.jpg" alt="Federico Citarella fotografo" class="w-100 h-100"
                            style="object-fit: cover;">
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- CONTATTI -->
    <section id="contatti" class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="df-card text-center">
                        <div class="card-body py-5">

                            <div class="text-white-50 small mb-2">Contatti</div>

                            <h2 class="h3 fw-semibold text-white mb-3">
                                Parliamo del tuo progetto
                            </h2>

                            <p class="text-white-50 mb-4">
                                Scrivimi su WhatsApp o via email per raccontarmi cosa hai in mente.
                                Rispondo il prima possibile.
                            </p>

                            <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">

                                <!-- WhatsApp -->
                                <a href="https://wa.me/393248389883?text=Ciao%20Federico%2C%20ho%20visto%20citaxph%20e%20vorrei%20parlarti%20di%20un%20servizio%20fotografico."
                                    target="_blank" rel="noopener" class="btn df-btn-primary btn-lg">
                                    Contattami su WhatsApp
                                </a>

                                <!-- Email -->
                                <a href="mailto:citarella15@gmail.com?subject=Richiesta%20informazioni%20-%20citaxph&body=Ciao%20Federico%2C%0Aho%20visitato%20citaxph%20e%20vorrei%20avere%20maggiori%20informazioni%20su%20un%20servizio%20fotografico."
                                    class="btn df-btn-ghost btn-lg">
                                    Scrivimi via email
                                </a>

                            </div>

                            <div class="text-white-50 small mt-4">
                                Servizi fotografici • Eventi • Moto • Brand
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- FOOTER -->
    <footer class="df-footer py-4">
        <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="text-white-50 small">© <?php echo date('Y'); ?> CITAXPH — Tutti i diritti riservati</div>
            <div class="d-flex gap-3 small">
                <a class="link-light link-underline-opacity-0 link-underline-opacity-50-hover"
                    href="privacy.php">Privacy</a>
                <a class="link-light link-underline-opacity-0 link-underline-opacity-50-hover"
                    href="client-login.php">Area Clienti</a>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
        </script>
</body>

</html>