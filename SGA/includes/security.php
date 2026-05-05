<?php
/**
 * SGA — Sécurité : XSS, CSRF, validation des entrées.
 */

if (!function_exists('sga_session_start')) {
    /**
     * Démarre la session de façon idempotente.
     */
    function sga_session_start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

/**
 * Échappe pour affichage HTML (protection XSS).
 *
 * @param mixed $value
 * @return string
 */
function sga_escape($value)
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Alias court pour les vues.
 */
function sga_e($value)
{
    return sga_escape($value);
}

/**
 * Génère ou retourne le token CSRF en session.
 *
 * @return string
 */
function sga_csrf_token()
{
    sga_session_start();
    if (empty($_SESSION['sga_csrf_token'])) {
        $_SESSION['sga_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['sga_csrf_token'];
}

/**
 * Vérifie le token CSRF (POST).
 *
 * @return bool
 */
function sga_csrf_verify()
{
    sga_session_start();
    if (empty($_POST['csrf_token']) || empty($_SESSION['sga_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['sga_csrf_token'], (string) $_POST['csrf_token']);
}

/**
 * Champ hidden CSRF pour formulaires.
 *
 * @return string HTML
 */
function sga_csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . sga_escape(sga_csrf_token()) . '">';
}

/**
 * Valide un email.
 *
 * @param string $email
 * @return bool
 */
function sga_validate_email($email)
{
    $email = trim((string) $email);
    return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valide un entier positif ou nul dans une plage.
 *
 * @param mixed $value
 * @param int   $min
 * @param int   $max
 * @return int|null
 */
function sga_validate_int_range($value, $min, $max)
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_numeric($value)) {
        return null;
    }
    $n = (int) $value;
    if ($n < $min || $n > $max) {
        return null;
    }
    return $n;
}

/**
 * Valide un niveau de promotion.
 *
 * @param string $niveau
 * @return bool
 */
function sga_validate_niveau($niveau)
{
    return in_array($niveau, array('L1', 'L2', 'L3', 'L4'), true);
}

/**
 * Valide le code OTP (6 chiffres).
 *
 * @param string $code
 * @return bool
 */
function sga_validate_otp($code)
{
    $code = preg_replace('/\s+/', '', (string) $code);
    return (bool) preg_match('/^\d{6}$/', $code);
}

/**
 * Nettoie une chaîne courte (nom, titre).
 *
 * @param string $s
 * @param int    $maxLen
 * @return string
 */
function sga_sanitize_string($s, $maxLen = 255)
{
    $s = trim((string) $s);
    if (function_exists('mb_substr')) {
        $s = mb_substr($s, 0, $maxLen, 'UTF-8');
    } else {
        $s = substr($s, 0, $maxLen);
    }
    return $s;
}
