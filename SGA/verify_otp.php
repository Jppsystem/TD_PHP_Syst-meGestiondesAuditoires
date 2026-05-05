<?php
/**
 * Vérification du code OTP (2FA) — expiration 5 minutes.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

sga_session_start();

if (sga_auth_is_full_session()) {
    sga_redirect('dashboard.php');
}

if (empty($_SESSION['sga_2fa_pending']) || empty($_SESSION['sga_user_email'])) {
    sga_redirect('login.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sga_csrf_verify()) {
        $error = 'Jeton de sécurité invalide.';
    } else {
        $code = isset($_POST['otp']) ? trim($_POST['otp']) : '';
        $error = sga_auth_verify_otp($code);
        if ($error === '') {
            sga_redirect('dashboard.php');
        }
    }
}

$demoOtp = sga_auth_get_pending_otp();
$emailUser = isset($_SESSION['sga_user_email']) ? $_SESSION['sga_user_email'] : '';

$pageTitle = 'Vérification 2FA';
$showNav = false;
require __DIR__ . '/includes/header.php';
?>

    <div class="card glass auth-panel">
        <h1 class="page-title" style="font-size:1.35rem">Double authentification</h1>
        <p class="page-sub">Un code à 6 chiffres a été généré pour <strong><?php echo sga_e($emailUser); ?></strong>.
            Saisissez-le ci-dessous (valide 5 minutes).</p>

        <?php if ($error !== '') : ?>
            <div class="alert alert-error"><?php echo sga_e($error); ?></div>
        <?php endif; ?>

        <form method="post" action="verify_otp.php" class="stack">
            <?php echo sga_csrf_field(); ?>
            <div class="form-row">
                <label class="label" for="otp">Code OTP</label>
                <input class="input" type="text" id="otp" name="otp" inputmode="numeric" pattern="\d{6}" maxlength="6" required
                    placeholder="000000" autocomplete="one-time-code">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Valider</button>
        </form>

        <div class="alert alert-info" style="margin-top:1.25rem">
            <strong>Simulation d’envoi</strong><br>
            Le code est écrit dans <code>data/otp_log.txt</code> et affiché ici pour les tests.
            <?php if ($demoOtp !== null) : ?>
                <div class="otp-demo"><?php echo sga_e($demoOtp); ?></div>
            <?php else : ?>
                <p style="margin:0.5rem 0 0;font-size:0.85rem;color:var(--text-muted)">Code expiré ou absent — reconnectez-vous.</p>
            <?php endif; ?>
        </div>

        <p style="text-align:center;margin-top:1rem">
            <a href="login.php" class="btn btn-ghost">Retour à la connexion</a>
        </p>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
