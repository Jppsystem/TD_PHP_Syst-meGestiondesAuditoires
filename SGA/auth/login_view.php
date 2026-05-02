<?php

declare(strict_types=1);

$sgaAssetsBase = $sga_assets_base ?? '..';
$titrePage = $titrePage ?? 'Connexion — SGA';
$erreursAuth = $erreursAuth ?? [];
$csrf = sga_csrf_token();
$attenteTotp = sga_attente_totp();

$authE = static function (string $valeur): string {
    return htmlspecialchars($valeur, ENT_QUOTES, 'UTF-8');
};
$styleHref = str_replace('\\', '/', $sgaAssetsBase . '/assets/style.css');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $authE((string) $titrePage); ?></title>
    <link rel="stylesheet" href="<?= $authE($styleHref); ?>">
</head>
<body>
    <div class="page-shell">
        <header class="hero">
            <div class="hero-copy">
                <p class="eyebrow">SGA</p>
                <h1>Accès sécurisé</h1>
                <p class="hero-text">
                    <?php if ($attenteTotp): ?>
                        Saisis le code à six chiffres généré par ton application d’authentification (Google Authenticator, Microsoft Authenticator, etc.).
                    <?php else: ?>
                        Connecte-toi avec ton identifiant et ton mot de passe. Si la double authentification est activée, un second écran te demandera le code TOTP.
                    <?php endif; ?>
                </p>
            </div>
        </header>
        <main class="contenu">
            <?php if ($erreursAuth !== []): ?>
                <div class="alerte alerte-danger"><ul>
                    <?php foreach ($erreursAuth as $msg): ?>
                        <li><?= $authE(is_string($msg) ? $msg : (string) $msg); ?></li>
                    <?php endforeach; ?>
                </ul></div>
            <?php endif; ?>

            <section class="bloc auth-card-wrap">
                <?php if ($attenteTotp): ?>
                    <form method="post" action="login.php" class="auth-form" autocomplete="one-time-code">
                        <input type="hidden" name="action" value="auth_totp">
                        <input type="hidden" name="csrf_token" value="<?= $authE($csrf); ?>">
                        <h2>Double authentification</h2>
                        <label for="totp_code">Code TOTP (6 chiffres)</label>
                        <input id="totp_code" name="totp_code" type="text" inputmode="numeric" pattern="\d{6}" maxlength="6" required autofocus>
                        <button type="submit">Valider</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="login.php" class="auth-form" autocomplete="username">
                        <input type="hidden" name="action" value="auth_login">
                        <input type="hidden" name="csrf_token" value="<?= $authE($csrf); ?>">
                        <h2>Connexion</h2>
                        <label for="username">Identifiant</label>
                        <input id="username" name="username" type="text" required autofocus>
                        <label for="password">Mot de passe</label>
                        <input id="password" name="password" type="password" required>
                        <button type="submit">Continuer</button>
                    </form>
                <?php endif; ?>
            </section>
        </main>
        <footer class="footer">
            Configuration : copier <code>config/auth.config.example.php</code> vers <code>config/auth.local.php</code> ou exécuter <code>php scripts/bootstrap_auth_local.php</code>.
        </footer>
    </div>
</body>
</html>
