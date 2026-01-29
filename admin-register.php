<?php
/**
 * citaxph* — Admin Register (SETUP)
 * Proprietario: Federico Citarella
 *
 * ⚠️ Pagina di setup per creare l'UTENTE ADMIN.
 * - Non è una registrazione pubblica.
 * - Funziona SOLO se non esiste ancora alcun admin (admin_users vuota).
 *
 * Consiglio: dopo aver creato l'utente, elimina questo file dal server
 * oppure rinominalo (hardening).
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

$err = null;
$okMsg = null;

// Check: se esiste già un admin, blocco la pagina
try {
  $count = (int)(db()->query("SELECT COUNT(*) AS c FROM admin_users")->fetch()['c'] ?? 0);
} catch (Throwable $e) {
  $count = 0;
  $err = APP_DEBUG ? $e->getMessage() : "Errore DB.";
}

if ($count > 0) {
  // Già configurato: niente registrazioni
  http_response_code(403);
  $err = "Registrazione disabilitata: l'admin esiste già. Usa il login.";
}

function normalize_email(string $email): string {
  return trim(mb_strtolower($email));
}

if (!$err && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedCsrf = (string)($_POST['csrf_token'] ?? '');
  if (!hash_equals($csrf, $postedCsrf)) {
    $err = "Richiesta non valida. Riprova.";
  } else {
    $email = normalize_email((string)($_POST['email'] ?? ''));
    $display = trim((string)($_POST['display_name'] ?? SITE_OWNER));
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password_confirm'] ?? '');

    if ($email === '' || $password === '' || $password2 === '') {
      $err = "Compila tutti i campi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $err = "Email non valida.";
    } elseif ($password !== $password2) {
      $err = "Le password non coincidono.";
    } elseif (mb_strlen($password) < 10) {
      $err = "Password troppo corta (min 10 caratteri).";
    } else {
      try {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO admin_users (email, password_hash, display_name, is_active) VALUES (:email, :hash, :name, 1)";
        $stmt = db()->prepare($sql);
        $stmt->execute([
          ':email' => $email,
          ':hash' => $hash,
          ':name' => ($display !== '' ? $display : SITE_OWNER),
        ]);

        // Login immediato
        session_regenerate_id(true);
        $_SESSION['admin_user_id'] = (int)db()->lastInsertId();
        $_SESSION['admin_email'] = $email;

        $okMsg = "Admin creato con successo. Ora sei loggato.";
      } catch (Throwable $e) {
        $err = APP_DEBUG ? $e->getMessage() : "Errore durante la creazione dell'admin.";
      }
    }
  }
}

?><!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo h(SITE_NAME); ?> — Setup Admin</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

  <!-- CSS -->
  <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
  <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container">
      <a class="navbar-brand fw-semibold" href="index.php"><?php echo h(SITE_NAME); ?></a>
    </div>
  </nav>

  <main class="pt-5">
    <section class="py-5">
      <div class="container pt-4" style="max-width: 560px;">
        <div class="df-card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
              <div>
                <div class="text-white-50 small">Setup</div>
                <h1 class="h4 mb-0 text-white">Crea utente Admin</h1>
              </div>
              <a class="btn btn-sm df-btn-ghost" href="admin-login.php">Vai al login</a>
            </div>

            <?php if ($okMsg): ?>
              <div class="alert alert-success mb-3"><?php echo h($okMsg); ?></div>
              <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-primary df-btn-primary" href="admin-dashboard.php">Apri dashboard</a>
                <a class="btn df-btn-ghost" href="index.php">Torna al sito</a>
              </div>

              <div class="text-white-50 small mt-3">
                Consiglio: dopo il setup, <b>rimuovi admin-register.php</b> dal server per sicurezza.
              </div>
            <?php else: ?>

              <?php if ($err): ?>
                <div class="alert alert-danger mb-3"><?php echo h($err); ?></div>
              <?php endif; ?>

              <form method="post" action="admin-register.php" class="row g-3" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">

                <div class="col-12">
                  <label class="form-label text-white-50">Email</label>
                  <input class="form-control" type="email" name="email" required autocomplete="username">
                </div>

                <div class="col-12">
                  <label class="form-label text-white-50">Nome visualizzato</label>
                  <input class="form-control" type="text" name="display_name" value="<?php echo h(SITE_OWNER); ?>" required>
                </div>

                <div class="col-12">
                  <label class="form-label text-white-50">Password (min 10 caratteri)</label>
                  <input class="form-control" type="password" name="password" required autocomplete="new-password">
                </div>

                <div class="col-12">
                  <label class="form-label text-white-50">Conferma password</label>
                  <input class="form-control" type="password" name="password_confirm" required autocomplete="new-password">
                </div>

                <div class="col-12 d-flex gap-2 flex-wrap">
                  <button class="btn btn-primary df-btn-primary" type="submit">Crea admin</button>
                </div>

                <div class="text-white-50 small">
                  Questa pagina è valida solo se non esistono admin nel DB.
                </div>
              </form>

            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
