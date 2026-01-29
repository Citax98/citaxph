<?php
/**
 * citaxph* — Admin Login
 * Proprietario: Federico Citarella
 *
 * Login singolo admin (nessuna registrazione pubblica).
 * Usa la tabella: admin_users (email + password_hash).
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Avvio sessione
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Se già loggato → vai al gestionale (pagina che creeremo dopo)
if (!empty($_SESSION['admin_user_id'])) {
  redirect('admin-dashboard.php');
}

$err = null;

// CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

function normalize_email(string $email): string {
  $email = trim(mb_strtolower($email));
  return $email;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedCsrf = (string)($_POST['csrf_token'] ?? '');
  if (!hash_equals($csrf, $postedCsrf)) {
    $err = "Richiesta non valida. Riprova.";
  } else {
    $email = normalize_email((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
      $err = "Inserisci email e password.";
    } else {
      try {
        $sql = "
          SELECT id, email, password_hash, is_active
          FROM admin_users
          WHERE email = :email
          LIMIT 1
        ";
        $stmt = db()->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        $ok = false;
        if ($user && (int)$user['is_active'] === 1) {
          $hash = (string)$user['password_hash'];
          $ok = password_verify($password, $hash);
        }

        if ($ok) {
          // harden session
          session_regenerate_id(true);

          $_SESSION['admin_user_id'] = (int)$user['id'];
          $_SESSION['admin_email'] = (string)$user['email'];

          // aggiorna last_login_at (best effort)
          try {
            $upd = db()->prepare("UPDATE admin_users SET last_login_at = NOW() WHERE id = :id");
            $upd->execute([':id' => (int)$user['id']]);
          } catch (Throwable $e) {
            // ignora
          }

          redirect('admin-dashboard.php');
        } else {
          // piccola mitigazione brute-force (minima)
          usleep(250000); // 250ms
          $err = "Credenziali non valide.";
        }
      } catch (Throwable $e) {
        $err = APP_DEBUG ? $e->getMessage() : "Errore durante il login.";
      }
    }
  }
}
?><!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo h(SITE_NAME); ?> — Admin Login</title>

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
      <div class="container pt-4" style="max-width: 520px;">
        <div class="df-card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
              <div>
                <div class="text-white-50 small">Area riservata</div>
                <h1 class="h4 mb-0 text-white">Login Admin</h1>
              </div>
              <a class="btn btn-sm df-btn-ghost" href="index.php">Torna al sito</a>
            </div>

            <?php if ($err): ?>
              <div class="alert alert-danger mb-3"><?php echo h($err); ?></div>
            <?php endif; ?>

            <form method="post" action="admin-login.php" class="row g-3" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">

              <div class="col-12">
                <label class="form-label text-white-50">Email</label>
                <input class="form-control" type="email" name="email" required autofocus autocomplete="username">
              </div>

              <div class="col-12">
                <label class="form-label text-white-50">Password</label>
                <input class="form-control" type="password" name="password" required autocomplete="current-password">
              </div>

              <div class="col-12 d-flex gap-2 flex-wrap">
                <button class="btn btn-primary df-btn-primary" type="submit">Accedi</button>
              </div>

              <div class="text-white-50 small">
                Nota: non esiste registrazione pubblica. Solo l'account di <?php echo h(SITE_OWNER); ?>.
              </div>
            </form>
          </div>
        </div>

        <?php if (APP_DEBUG): ?>
          <div class="text-white-50 small mt-3">
            <b>Debug tip:</b> assicurati di aver inserito un record in <code>admin_users</code> con una password hashata via <code>password_hash()</code>.
          </div>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
