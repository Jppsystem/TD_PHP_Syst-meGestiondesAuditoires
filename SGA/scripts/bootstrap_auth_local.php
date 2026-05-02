<?php

declare(strict_types=1);

/**
 * Usage (ligne de commande, depuis le dossier SGA) :
 *   php scripts/bootstrap_auth_local.php "MonMotDePasseSecurise"
 *
 * Crée config/auth.local.php avec hash du mot de passe et un secret TOTP.
 * Scanner le QR affiché (ou saisir le secret) dans une appli TOTP.
 */
$racine = dirname(__DIR__);
$cible = $racine . '/config/auth.local.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script s’exécute en ligne de commande uniquement.\n");
    exit(1);
}

$mot = $argv[1] ?? '';

if ($mot === '') {
    fwrite(STDERR, "Usage: php scripts/bootstrap_auth_local.php \"MotDePasse\"\n");
    exit(1);
}

if (is_file($cible)) {
    fwrite(STDERR, "Le fichier existe déjà : $cible\nSupprime-le ou renomme-le avant de relancer.\n");
    exit(1);
}

$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
$raw = random_bytes(20);
$secret = '';

for ($i = 0; $i < strlen($raw); ++$i) {
    $secret .= $alphabet[ord($raw[$i]) % 32];
}

$hash = password_hash($mot, PASSWORD_DEFAULT);

if ($hash === false) {
    fwrite(STDERR, "Impossible de générer le hash du mot de passe.\n");
    exit(1);
}

$issuer = 'SGA-Auditoires';
$account = 'admin';
$uri = sprintf(
    'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
    rawurlencode($issuer),
    rawurlencode($account),
    $secret,
    rawurlencode($issuer)
);

$contenu = <<<PHP
<?php

declare(strict_types=1);

return [
    'username' => 'admin',
    'password_hash' => '{$hash}',
    'totp_secret' => '{$secret}',
];

PHP;

if (file_put_contents($cible, $contenu) === false) {
    fwrite(STDERR, "Échec d’écriture : $cible\n");
    exit(1);
}

echo "Fichier créé : $cible\n\n";
echo "Secret TOTP (Base32) : {$secret}\n";
echo "URI otpauth (à convertir en QR si besoin) :\n{$uri}\n";
echo "\nConnecte-toi sur l’interface avec le mot de passe fourni puis le code à 6 chiffres.\n";
