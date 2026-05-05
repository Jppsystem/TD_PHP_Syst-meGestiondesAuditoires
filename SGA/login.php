<?php
/**
 * Connexion (email + mot de passe) puis envoi en 2FA.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

sga_session_start();

if (sga_auth_is_full_session()) {
    sga_redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sga_csrf_verify()) {
        $error = 'Jeton de sécurité invalide. Rechargez la page.';
    } else {
        $email = sga_sanitize_string(isset($_POST['email']) ? $_POST['email'] : '', 120);
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        if (!sga_validate_email($email)) {
            $error = 'Adresse e-mail invalide.';
        } else {
            $ok = sga_auth_check_password($email, $password);
            if ($ok === null) {
                $error = 'Identifiants incorrects.';
            } else {
                sga_auth_begin_2fa($ok);
                sga_redirect('verify_otp.php');
            }
        }
    }
}

$pageTitle = 'Connexion';
$showNav = false;
require __DIR__ . '/includes/header.php';
?>

    <div class="card glass auth-panel">
        <div class="auth-logo">
            <span class="brand-mark">SGA</span>
            <p class="page-sub" style="margin:0.5rem 0 0">Système de Gestion des Auditoires</p>
        </div>

        <?php if ($error !== '') : ?>
            <div class="alert alert-error"><?php echo sga_e($error); ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" class="stack" autocomplete="on">
            <?php echo sga_csrf_field(); ?>
            <div class="form-row">
                <label class="label" for="email">E-mail</label>
                <input class="input" type="email" id="email" name="email" required
                    value="<?php echo isset($_POST['email']) ? sga_e($_POST['email']) : ''; ?>">
            </div>
            <div class="form-row">
                <label class="label" for="password">Mot de passe</label>
                <input class="input" type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Se connecter</button>
        </form>

        <div class="alert alert-info" style="margin-top:1.25rem">
            <strong>Compte de démonstration</strong><br>
            E-mail : <code>admin@faculte.edu</code><br>
            Mot de passe : <code>Admin123!</code>
            <br><small style="color:var(--text-muted)">Le mot de passe est stocké avec <code>password_hash()</code>.</small>
        </div>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
