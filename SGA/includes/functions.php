<?php
/**
 * SGA — Fonctions utilitaires générales (chemins, fichiers JSON).
 * Une fonction = une responsabilité.
 */

if (!defined('SGA_ROOT')) {
    define('SGA_ROOT', dirname(__DIR__));
}

define('SGA_DATA_DIR', SGA_ROOT . DIRECTORY_SEPARATOR . 'data');

/**
 * Retourne le chemin absolu d'un fichier dans /data.
 */
function sga_data_path($filename)
{
    return SGA_DATA_DIR . DIRECTORY_SEPARATOR . $filename;
}

/**
 * Lit un fichier JSON ; retourne un tableau vide si absent ou invalide.
 *
 * @param string $filename Nom du fichier dans /data
 * @return array
 */
function sga_json_read($filename)
{
    $path = sga_data_path($filename);
    if (!file_exists($path) || !is_readable($path)) {
        return array();
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return array();
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return array();
    }
    return $data;
}

/**
 * Écrit un tableau en JSON (pretty print). Crée le dossier si nécessaire.
 *
 * @param string $filename
 * @param array  $data
 * @return bool
 */
function sga_json_write($filename, $data)
{
    if (!is_dir(SGA_DATA_DIR)) {
        if (!@mkdir(SGA_DATA_DIR, 0755, true)) {
            return false;
        }
    }
    $path = sga_data_path($filename);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    $tmp = $path . '.tmp';
    $ok = @file_put_contents($tmp, $json, LOCK_EX);
    if ($ok === false) {
        return false;
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

/**
 * Compteur auto-incrémenté simple pour générer des IDs.
 *
 * @param string $prefix Préfixe optionnel
 * @return string
 */
function sga_generate_id($prefix = '')
{
    return $prefix . bin2hex(random_bytes(8));
}

/**
 * Redirige et termine le script.
 *
 * @param string $url
 */
function sga_redirect($url)
{
    header('Location: ' . $url);
    exit;
}
