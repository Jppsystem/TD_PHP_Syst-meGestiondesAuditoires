<?php

declare(strict_types=1);

/**
 * Copier ce fichier en auth.local.php (même dossier) ou le modifier depuis le fichier
 * livré auth.local.php (défaut développement : utilisateur admin, mot de passe demo2026).
 *
 * Générer un autre hash de mot de passe :
 *   php -r "echo password_hash('VotreMotDePasse', PASSWORD_DEFAULT), PHP_EOL;"
 *
 * Générer un secret TOTP (Base32) et l’URI d’enrôlement :
 *   php scripts/bootstrap_auth_local.php
 *
 * Laisser totp_secret vide ('') pour désactiver la 2FA (mot de passe seul).
 */
return [
    'username' => 'admin',
    // Hash bcrypt du mot de passe de démo : demo2026
    'password_hash' => '$2y$12$2BXgAFPBr/4K62kcGIlaMOg1rqrk0Or1tBgeXKxSTJ0TYqhGAmwOK',
    // Secret Base32 (ex. sortie de bootstrap_auth_local.php). Vide = pas de 2FA.
    'totp_secret' => '',
];
