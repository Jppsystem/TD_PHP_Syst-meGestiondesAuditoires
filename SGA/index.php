<?php
/**
 * Point d'entrée : redirection selon l'état de session.
 */
require_once __DIR__ . '/includes/auth.php';

sga_session_start();

if (sga_auth_is_full_session()) {
    sga_redirect('dashboard.php');
}

if (!empty($_SESSION['sga_2fa_pending'])) {
    sga_redirect('verify_otp.php');
}

sga_redirect('login.php');
