<?php

declare(strict_types=1);

require_once __DIR__ . '/fonctions_chargement.php';
require_once __DIR__ . '/fonctions_totp.php';

const SGA_SESSION_NAME = 'sga_session';

function sga_config_auth_personnalisee_path(): string
{
    return __DIR__ . '/../config/auth.local.php';
}

function sga_config_auth_chargee(): array
{
    $chemin = sga_config_auth_personnalisee_path();

    if (!is_readable($chemin)) {
        return ['_erreur_configuration' => 'Fichier de configuration absent. Copier config/auth.config.example.php vers config/auth.local.php.'];
    }

    $valeur = require $chemin;

    if (!is_array($valeur)) {
        return ['_erreur_configuration' => 'auth.local.php doit retourner un tableau.'];
    }

    return $valeur;
}

function sga_sess_regenerer_id(): void
{
    session_regenerate_id(true);
}

function sga_demarrer_session_auth(): void
{
    $params = session_get_cookie_params();

    if (PHP_SESSION_ACTIVE !== session_status()) {
        session_name(SGA_SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => $params['lifetime'],
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'] ?: '',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function sga_csrf_token(): string
{
    if (empty($_SESSION['sga_csrf']) || !is_string($_SESSION['sga_csrf'])) {
        $_SESSION['sga_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['sga_csrf'];
}

function sga_csrf_valider(?string $token): bool
{
    if ($token === null || $token === '' || empty($_SESSION['sga_csrf']) || !is_string($_SESSION['sga_csrf'])) {
        return false;
    }

    return hash_equals($_SESSION['sga_csrf'], $token);
}

function sga_deconnexion(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            !empty($params['secure']),
            !empty($params['httponly'])
        );
    }

    session_destroy();
}

function sga_utilisateur_session(): string
{
    $u = $_SESSION['sga_user'] ?? '';

    return is_string($u) ? $u : '';
}

function sga_est_connecte_complet(): bool
{
    return !empty($_SESSION['sga_auth_ok']) && is_string($_SESSION['sga_user'] ?? null) && $_SESSION['sga_user'] !== '';
}

function sga_attente_totp(): bool
{
    return !empty($_SESSION['sga_totp_pending']) && is_string($_SESSION['sga_pending_user'] ?? null);
}

function sga_traiter_authentification(array $post): array
{
    $erreurs = [];
    $config = sga_config_auth_chargee();

    if (($config['_erreur_configuration'] ?? null) !== null) {
        $erreurs[] = (string) $config['_erreur_configuration'];

        return ['erreurs' => $erreurs, 'configure' => false];
    }

    $usernameCfg = texte_depuis_valeur($config['username'] ?? '');
    $hashCfg = texte_depuis_valeur($config['password_hash'] ?? '');

    if ($usernameCfg === '' || $hashCfg === '') {
        $erreurs[] = 'Configurer username et password_hash dans config/auth.local.php.';

        return ['erreurs' => $erreurs, 'configure' => false];
    }

    return ['erreurs' => [], 'configure' => true, 'cfg' => $config, 'username_cfg' => $usernameCfg, 'hash_cfg' => $hashCfg];
}

function sga_traiter_formulaire_login(array $post): array
{
    $prep = sga_traiter_authentification($post);

    if ($prep['erreurs'] !== []) {
        return $prep;
    }

    /** @var array<string, mixed> $config */
    $config = $prep['cfg'];
    $usernameCfg = $prep['username_cfg'];
    $hashCfg = $prep['hash_cfg'];

    if (!sga_csrf_valider(texte_depuis_valeur($post['csrf_token'] ?? ''))) {
        return ['erreurs' => ['Session expirée ou jeton invalide. Recharge la page.'], 'configure' => true];
    }

    $nom = texte_depuis_valeur($post['username'] ?? '');
    $mot = $post['password'] ?? '';

    if (!hash_equals(strtolower($usernameCfg), strtolower($nom)) || !is_string($mot) || $mot === '') {
        return ['erreurs' => ['Identifiant ou mot de passe incorrect.'], 'configure' => true];
    }

    if (!password_verify($mot, $hashCfg)) {
        return ['erreurs' => ['Identifiant ou mot de passe incorrect.'], 'configure' => true];
    }

    unset($_SESSION['sga_auth_ok'], $_SESSION['sga_user'], $_SESSION['sga_totp_pending'], $_SESSION['sga_pending_user']);

    $secretTotp = texte_depuis_valeur($config['totp_secret'] ?? '');

    if ($secretTotp !== '') {
        $_SESSION['sga_totp_pending'] = true;
        $_SESSION['sga_pending_user'] = $usernameCfg;
        sga_sess_regenerer_id();

        return ['erreurs' => [], 'configure' => true, 'etape' => 'totp'];
    }

    $_SESSION['sga_auth_ok'] = true;
    $_SESSION['sga_user'] = $usernameCfg;
    sga_sess_regenerer_id();

    return ['erreurs' => [], 'configure' => true, 'etape' => 'ok'];
}

function sga_traiter_formulaire_totp(array $post): array
{
    $prep = sga_traiter_authentification($post);

    if ($prep['erreurs'] !== []) {
        return $prep;
    }

    if (!sga_attente_totp()) {
        return ['erreurs' => ['Aucune vérification en deux étapes en cours.'], 'configure' => true];
    }

    if (!sga_csrf_valider(texte_depuis_valeur($post['csrf_token'] ?? ''))) {
        return ['erreurs' => ['Session expirée ou jeton invalide. Recharge la page.'], 'configure' => true];
    }

    /** @var array<string, mixed> $config */
    $config = $prep['cfg'];
    $secretTotp = texte_depuis_valeur($config['totp_secret'] ?? '');
    $code = texte_depuis_valeur($post['totp_code'] ?? '');

    if ($secretTotp === '') {
        return ['erreurs' => ['La double authentification n est pas activee sur ce serveur.'], 'configure' => true];
    }

    if (!sga_totp_verifier($secretTotp, $code, 1)) {
        return ['erreurs' => ['Code à six chiffres invalide ou expiré.'], 'configure' => true];
    }

    $user = texte_depuis_valeur($_SESSION['sga_pending_user'] ?? '');
    unset($_SESSION['sga_totp_pending'], $_SESSION['sga_pending_user']);
    $_SESSION['sga_auth_ok'] = true;
    $_SESSION['sga_user'] = $user;
    sga_sess_regenerer_id();

    return ['erreurs' => [], 'configure' => true, 'etape' => 'ok'];
}
