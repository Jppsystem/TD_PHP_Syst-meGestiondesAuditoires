<?php
/**
 * SGA — Authentification, sessions, 2FA OTP.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/security.php';

define('SGA_USERS_FILE', 'users.json');
define('SGA_OTP_TTL', 300); // 5 minutes

/**
 * Charge la liste des utilisateurs depuis users.json
 *
 * @return array liste de [ 'email' => ..., 'password_hash' => ... ]
 */
function sga_auth_load_users()
{
    $data = sga_json_read(SGA_USERS_FILE);
    if (isset($data['users']) && is_array($data['users'])) {
        return $data['users'];
    }
    if (isset($data[0]) && is_array($data)) {
        return $data;
    }
    return array();
}

/**
 * Recherche un utilisateur par email (insensible à la casse pour le login).
 *
 * @param string $email
 * @return array|null
 */
function sga_auth_find_user($email)
{
    $email = strtolower(trim($email));
    foreach (sga_auth_load_users() as $u) {
        if (!isset($u['email'], $u['password_hash'])) {
            continue;
        }
        if (strtolower($u['email']) === $email) {
            return $u;
        }
    }
    return null;
}

/**
 * Vérifie identifiants ; retourne l'email normalisé ou null.
 *
 * @param string $email
 * @param string $password
 * @return string|null
 */
function sga_auth_check_password($email, $password)
{
    $user = sga_auth_find_user($email);
    if ($user === null) {
        return null;
    }
    if (!password_verify((string) $password, $user['password_hash'])) {
        return null;
    }
    return strtolower(trim($email));
}

/**
 * Indique si l'utilisateur est entièrement connecté (après 2FA).
 *
 * @return bool
 */
function sga_auth_is_full_session()
{
    sga_session_start();
    return !empty($_SESSION['sga_user_email'])
        && !empty($_SESSION['sga_auth_ok'])
        && $_SESSION['sga_auth_ok'] === true
        && empty($_SESSION['sga_2fa_pending']);
}

/**
 * Exige une session complète ; sinon redirection.
 *
 * @param string $redirectTo Page de login ou OTP
 */
function sga_auth_require_full($redirectTo = 'login.php')
{
    sga_session_start();
    if (!empty($_SESSION['sga_2fa_pending'])) {
        sga_redirect('verify_otp.php');
    }
    if (!sga_auth_is_full_session()) {
        sga_redirect($redirectTo);
    }
}

/**
 * Démarre la phase 2FA après mot de passe correct.
 *
 * @param string $email
 */
function sga_auth_begin_2fa($email)
{
    sga_session_start();
    $_SESSION['sga_2fa_pending'] = true;
    $_SESSION['sga_user_email'] = $email;
    $_SESSION['sga_auth_ok'] = false;

    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['sga_otp_code'] = $otp;
    $_SESSION['sga_otp_expires'] = time() + SGA_OTP_TTL;

    // Simulation d'envoi : journalisation
    $logLine = date('c') . " | OTP pour {$email} : {$otp} (expire dans " . SGA_OTP_TTL . "s)\n";
    $logPath = sga_data_path('otp_log.txt');
    @file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * Retourne le code OTP actuel (pour affichage démo sur verify_otp).
 * Ne pas utiliser en production si l'affichage est désactivé.
 *
 * @return string|null
 */
function sga_auth_get_pending_otp()
{
    sga_session_start();
    if (empty($_SESSION['sga_otp_code'])) {
        return null;
    }
    if (empty($_SESSION['sga_otp_expires']) || time() > (int) $_SESSION['sga_otp_expires']) {
        return null;
    }
    return (string) $_SESSION['sga_otp_code'];
}

/**
 * Vérifie le code OTP et finalise la session.
 *
 * @param string $code
 * @return string message d'erreur ou chaîne vide si OK
 */
function sga_auth_verify_otp($code)
{
    sga_session_start();
    if (empty($_SESSION['sga_2fa_pending']) || empty($_SESSION['sga_user_email'])) {
        return 'Session invalide. Reconnectez-vous.';
    }
    if (empty($_SESSION['sga_otp_code']) || empty($_SESSION['sga_otp_expires'])) {
        return 'Code expiré ou manquant. Reconnectez-vous.';
    }
    if (time() > (int) $_SESSION['sga_otp_expires']) {
        sga_auth_clear_2fa_state();
        return 'Le code a expiré (5 minutes). Reconnectez-vous.';
    }
    if (!sga_validate_otp($code)) {
        return 'Le code doit contenir exactement 6 chiffres.';
    }
    if (!hash_equals($_SESSION['sga_otp_code'], $code)) {
        return 'Code incorrect.';
    }

    $_SESSION['sga_2fa_pending'] = false;
    $_SESSION['sga_auth_ok'] = true;
    unset($_SESSION['sga_otp_code'], $_SESSION['sga_otp_expires']);
    session_regenerate_id(true);
    return '';
}

/**
 * Réinitialise l'état 2FA en session (sans détruire l'utilisateur si besoin).
 */
function sga_auth_clear_2fa_state()
{
    unset(
        $_SESSION['sga_otp_code'],
        $_SESSION['sga_otp_expires'],
        $_SESSION['sga_2fa_pending']
    );
}

/**
 * Déconnexion sécurisée.
 */
function sga_auth_logout()
{
    sga_session_start();
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
