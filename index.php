<?php
// citaxph* — Home (public)
// Proprietario: Federico Citarella
// NOTE: Pagina pubblica. L'accesso alle gallery clienti avverrà tramite pagine protette (es. client-login.php).
?>
<!doctype html>
<html lang="it">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>citaxph* — Fotografia Eventi & Shooting</title>
    <meta name="description"
        content="citaxph* — Shooting, eventi e reportage. Gallerie clienti protette con accesso tramite codice." />

    <!-- Bootstrap 5 (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- CSS (separato) -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <!-- NAV -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="index.php">citaxph*</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain"
                aria-controls="navMain" aria-expanded="false" aria-label="Apri menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMain">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="#portfolio">Portfolio</a></li>
                    <li class="nav-item"><a class="nav-link" href="#servizi">Servizi</a></li>
                    <li class="nav-item"><a class="nav-link" href="#chi-sono">Chi sono</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contatti">Contatti</a></li>
                </ul>
                <div class="d-flex gap-2 ms-lg-3">
                    <a class="btn btn-sm btn-primary df-btn-primary" href="#contatti">Richiedi Preventivo</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- HERO -->
    <header class="df-hero">
        <div class="container pt-5">
            <div class="df-grid align-items-stretch">
                <div class="df-hero-left">
                    <span class="df-chip mb-3">📷 Eventi • Shooting • Reportage</span>
                    <h1 class="display-5 fw-bold mb-3">Foto che raccontano davvero.</h1>
                    <p class="lead text-white-50 mb-4">
                        Mi chiamo <b>Federico Citarella</b> e realizzo servizi fotografici per eventi, brand e privati.
                        Consegna tramite <b>gallerie clienti protette</b>.
                        Nessun accesso pubblico agli originali: solo anteprime web ottimizzate.
                    </p>

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-primary df-btn-primary btn-lg" href="#contatti">Prenota uno shooting</a>
                        <a class="btn df-btn-ghost btn-lg" href="events.php">Accedi alla gallery degli eventi</a>
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
                                <div class="col-12">
                                    <div class="df-thumb"></div>
                                </div>
                                <div class="col-6">
                                    <div class="df-thumb" style="height:120px;"></div>
                                </div>
                                <div class="col-6">
                                    <div class="df-thumb" style="height:120px;"></div>
                                </div>
                            </div>

                            <div class="mt-3 text-white-50 small">
                                Qui inserirò foto reali / slider appena carichiamo la struttura delle gallery.
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
            <div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h3 fw-semibold mb-1">Portfolio</h2>
                    <p class="text-white-50 mb-0">Selezione lavori — qui metteremo categorie e highlight.</p>
                </div>
                <a class="btn df-btn-ghost" href="#contatti">Vuoi uno shooting simile?</a>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="df-card h-100">
                        <div class="card-body">
                            <div class="df-thumb mb-3"></div>
                            <h3 class="h5 mb-1">Eventi</h3>
                            <p class="text-white-50 mb-0">Reportage completo, momenti reali, consegna rapida.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="df-card h-100">
                        <div class="card-body">
                            <div class="df-thumb mb-3"></div>
                            <h3 class="h5 mb-1">Ritratti</h3>
                            <p class="text-white-50 mb-0">Shooting in esterna o location, editing naturale.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="df-card h-100">
                        <div class="card-body">
                            <div class="df-thumb mb-3"></div>
                            <h3 class="h5 mb-1">Brand / Prodotti</h3>
                            <p class="text-white-50 mb-0">Foto per social e e-commerce, set puliti e coerenti.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

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

    <!-- CHI SONO -->
    <section id="chi-sono" class="py-5">
        <div class="container">
            <div class="row g-3 align-items-stretch">
                <div class="col-lg-5">
                    <div class="df-card h-100">
                        <div class="card-body">
                            <h2 class="h3 fw-semibold mb-2">Chi sono</h2>
                            <p class="text-white-50 mb-0">
                                Mi chiamo <b>Federico Citarella</b>. Qui inserirai una bio breve: stile fotografico,
                                esperienza, area geografica, ecc.
                                (testo placeholder)
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="df-card h-100">
                        <div class="card-body">
                            <h3 class="h5 mb-2">Come funziona la consegna</h3>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="df-card px-3 py-3 h-100">
                                        <div class="fw-semibold">1) Upload</div>
                                        <div class="text-white-50 small">Carico anteprime web nel gestionale eventi.
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="df-card px-3 py-3 h-100">
                                        <div class="fw-semibold">2) Protezione</div>
                                        <div class="text-white-50 small">Accesso con codice/token: niente link pubblici.
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="df-card px-3 py-3 h-100">
                                        <div class="fw-semibold">3) Condivisione</div>
                                        <div class="text-white-50 small">Inviti a ospiti/cliente e download consentiti
                                            (se abilitati).</div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 text-white-50 small">
                                Questa sezione si collega a quello che implementeremo nelle prossime pagine (admin +
                                eventi + gallery protette).
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CONTATTI -->
    <section id="contatti" class="py-5">
        <div class="container">
            <h2 class="h3 fw-semibold mb-1">Contatti</h2>
            <p class="text-white-50 mb-4">Compila e ti rispondo appena possibile. (form placeholder)</p>

            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="df-card">
                        <div class="card-body">
                            <form method="post" action="contact-submit.php" class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-white-50">Nome</label>
                                    <input class="form-control" name="name" autocomplete="name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-white-50">Email</label>
                                    <input class="form-control" name="email" type="email" autocomplete="email" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-white-50">Messaggio</label>
                                    <textarea class="form-control" name="message" rows="4" required></textarea>
                                </div>
                                <div class="col-12 d-flex gap-2 flex-wrap">
                                    <button class="btn btn-primary df-btn-primary" type="submit">Invia</button>
                                    <a class="btn df-btn-ghost" href="client-login.php">Sono un cliente: accedi</a>
                                </div>
                                <small class="text-white-50">Nota: la pagina contact-submit.php la creeremo dopo (invio
                                    mail + anti-spam).</small>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="df-card h-100">
                        <div class="card-body">
                            <h3 class="h5 mb-2">Info rapide</h3>
                            <div class="text-white-50">
                                <div><b>Zona:</b> (placeholder)</div>
                                <div><b>Disponibilità:</b> (placeholder)</div>
                                <div><b>Instagram:</b> (placeholder)</div>
                            </div>
                            <div class="mt-3 text-white-50 small">
                                Qui potrai inserire link social e recapiti.
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
            <div class="text-white-50 small">© <?php echo date('Y'); ?> citaxph* — Federico Citarella — Tutti i diritti
                riservati</div>
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