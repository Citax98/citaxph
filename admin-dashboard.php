<?php
/**
 * citaxph* — Admin Dashboard
 * Proprietario: Federico Citarella
 *
 * Home gestionale: accesso riservato.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Guard: richiede login
if (empty($_SESSION['admin_user_id'])) {
  redirect('admin-login.php');
}

$adminEmail = (string)($_SESSION['admin_email'] ?? '');

// KPI semplici (best effort)
$kpiEvents = 0;
$kpiPhotos = 0;
$err = null;

try {
  $kpiEvents = (int)(db()->query("SELECT COUNT(*) AS c FROM events")->fetch()['c'] ?? 0);
  $kpiPhotos = (int)(db()->query("SELECT COUNT(*) AS c FROM photos")->fetch()['c'] ?? 0);
} catch (Throwable $e) {
  $err = APP_DEBUG ? $e->getMessage() : null;
}
?><!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo h(SITE_NAME); ?> — Dashboard</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

  <!-- CSS -->
  <link rel="stylesheet" href="assets/css/style.css">
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
          <li class="nav-item"><a class="nav-link" href="admin-photos.php">Foto</a></li>
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
            <div class="text-white-50 small">Bentornato</div>
            <h1 class="h3 fw-semibold mb-0 text-white">Dashboard</h1>
          </div>
          <div class="text-white-50 small">
            <?php if ($adminEmail): ?>
              Loggato come: <b class="text-white"><?php echo h($adminEmail); ?></b>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($err): ?>
          <div class="alert alert-danger"><?php echo h($err); ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
          <div class="col-md-6 col-lg-3">
            <div class="df-card h-100">
              <div class="card-body">
                <div class="text-white-50 small">Eventi</div>
                <div class="display-6 fw-bold text-white"><?php echo (int)$kpiEvents; ?></div>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-lg-3">
            <div class="df-card h-100">
              <div class="card-body">
                <div class="text-white-50 small">Foto</div>
                <div class="display-6 fw-bold text-white"><?php echo (int)$kpiPhotos; ?></div>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-lg-6">
            <div class="df-card h-100">
              <div class="card-body">
                <div class="text-white-50 small">Azioni rapide</div>
                <div class="d-flex flex-wrap gap-2 mt-2">
                  <a class="btn btn-primary df-btn-primary" href="admin-events.php">Gestisci eventi</a>
                  <a class="btn df-btn-ghost" href="admin-photos.php">Carica foto</a>
                  <a class="btn df-btn-ghost" href="events.php" target="_blank" rel="noopener">Lista eventi pubblica</a>
                </div>
                <div class="text-white-50 small mt-3">
                  Suggerimento: crea prima un evento, poi carica le foto associandole all’evento.
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-lg-6">
            <div class="df-card h-100">
              <div class="card-body">
                <h2 class="h5 text-white mb-2">Checklist iniziale</h2>
                <ul class="text-white-50 mb-0">
                  <li>Verifica <code>config.php</code> (DB + STORAGE_ROOT)</li>
                  <li>Crea il tuo evento (titolo, slug, data, ecc.)</li>
                  <li>Carica foto (consigliate: anteprime compresse)</li>
                  <li>Controlla <code>events.php</code> e <code>event.php</code></li>
                </ul>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="df-card h-100">
              <div class="card-body">
                <h2 class="h5 text-white mb-2">Note sicurezza</h2>
                <ul class="text-white-50 mb-0">
                  <li>Imposta <b>APP_DEBUG = false</b> in produzione</li>
                  <li>Se hai HTTPS, abilita <code>session.cookie_secure</code></li>
                  <li>Metti le foto fuori dalla web root (STORAGE_ROOT)</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
