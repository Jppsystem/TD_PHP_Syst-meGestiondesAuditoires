<?php

declare(strict_types=1);

require_once __DIR__ . '/fonctions_chargement.php';

function sauvegarder_json(string $nomFichier, array $donnees): bool
{
    $chemin = chemin_data($nomFichier);
    $json = json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        return false;
    }

    return file_put_contents($chemin, $json . PHP_EOL, LOCK_EX) !== false;
}

function ecrire_texte(string $nomFichier, string $contenu): bool
{
    return file_put_contents(chemin_data($nomFichier), $contenu, LOCK_EX) !== false;
}

function sauvegarder_planning_txt(array $planning): bool
{
    $lignes = ['jour;heure_debut;heure_fin;salle_id;cours_id;groupe_id'];

    foreach ($planning as $seance) {
        if (!is_array($seance)) {
            continue;
        }

        $lignes[] = implode(';', [
            texte_tableau($seance, 'jour'),
            texte_tableau($seance, 'heure_debut'),
            texte_tableau($seance, 'heure_fin'),
            texte_tableau($seance, 'salle_id'),
            texte_tableau($seance, 'cours_id'),
            texte_tableau($seance, 'groupe_id'),
        ]);
    }

    return ecrire_texte('planning.txt', implode(PHP_EOL, $lignes) . PHP_EOL);
}

function sauvegarder_planning(array $planning): array
{
    $succes = [];
    $erreurs = [];

    if (sauvegarder_json('planning.json', $planning)) {
        $succes[] = 'planning.json enregistre avec succes.';
    } else {
        $erreurs[] = 'Echec de sauvegarde de planning.json.';
    }

    if (sauvegarder_planning_txt($planning)) {
        $succes[] = 'planning.txt enregistre avec succes.';
    } else {
        $erreurs[] = 'Echec de sauvegarde de planning.txt.';
    }

    return [
        'ok' => $erreurs === [],
        'succes' => $succes,
        'erreurs' => $erreurs,
    ];
}

function serialiser_sous_groupes_csv(array $sousGroupes): string
{
    $segments = [];

    foreach ($sousGroupes as $sousGroupe) {
        if (!is_array($sousGroupe)) {
            continue;
        }

        $segments[] = sprintf(
            '%s:%s:%d',
            texte_tableau($sousGroupe, 'id'),
            texte_tableau($sousGroupe, 'libelle'),
            entier_tableau($sousGroupe, 'effectif')
        );
    }

    return implode(', ', $segments);
}

function sauvegarder_csv(string $nomFichier, array $entetes, array $lignes): bool
{
    $poignee = fopen(chemin_data($nomFichier), 'wb');

    if ($poignee === false) {
        return false;
    }

    $ok = fputcsv($poignee, $entetes, ';') !== false;

    foreach ($lignes as $ligne) {
        if (!$ok) {
            break;
        }

        $ok = fputcsv($poignee, $ligne, ';') !== false;
    }

    fclose($poignee);

    return $ok;
}

function exporter_donnees_csv(array $donnees): array
{
    $succes = [];
    $erreurs = [];

    $exports = [
        [
            'fichier' => 'salles.csv',
            'entetes' => ['id', 'designation', 'capacite'],
            'lignes' => array_map(
                static fn (array $salle): array => [
                    texte_tableau($salle, 'id'),
                    texte_tableau($salle, 'designation'),
                    (string) entier_tableau($salle, 'capacite'),
                ],
                array_filter($donnees['salles'] ?? [], 'is_array')
            ),
        ],
        [
            'fichier' => 'promotions.csv',
            'entetes' => ['id', 'libelle', 'effectif_total', 'sous_groupes'],
            'lignes' => array_map(
                static fn (array $promotion): array => [
                    texte_tableau($promotion, 'id'),
                    texte_tableau($promotion, 'libelle'),
                    (string) entier_tableau($promotion, 'effectif_total'),
                    serialiser_sous_groupes_csv(liste_tableau(valeur_tableau($promotion, 'sous_groupes', []))),
                ],
                array_filter($donnees['promotions'] ?? [], 'is_array')
            ),
        ],
        [
            'fichier' => 'options.csv',
            'entetes' => ['id', 'libelle', 'promotion_parente', 'effectif'],
            'lignes' => array_map(
                static fn (array $option): array => [
                    texte_tableau($option, 'id'),
                    texte_tableau($option, 'libelle'),
                    texte_tableau($option, 'promotion_parente'),
                    (string) entier_tableau($option, 'effectif'),
                ],
                array_filter($donnees['options'] ?? [], 'is_array')
            ),
        ],
        [
            'fichier' => 'cours.csv',
            'entetes' => ['id', 'intitule', 'volume_horaire', 'type', 'promotion_id', 'option_id'],
            'lignes' => array_map(
                static fn (array $cours): array => [
                    texte_tableau($cours, 'id'),
                    texte_tableau($cours, 'intitule'),
                    (string) entier_tableau($cours, 'volume_horaire'),
                    texte_tableau($cours, 'type'),
                    texte_tableau($cours, 'promotion_id'),
                    texte_tableau($cours, 'option_id'),
                ],
                array_filter($donnees['cours'] ?? [], 'is_array')
            ),
        ],
    ];

    foreach ($exports as $export) {
        if (sauvegarder_csv($export['fichier'], $export['entetes'], $export['lignes'])) {
            $succes[] = sprintf('%s mis a jour.', $export['fichier']);
        } else {
            $erreurs[] = sprintf('Echec de sauvegarde de %s.', $export['fichier']);
        }
    }

    return [
        'ok' => $erreurs === [],
        'succes' => $succes,
        'erreurs' => $erreurs,
    ];
}

function sauvegarder_donnees_sga(array $donnees): array
{
    $succes = [];
    $erreurs = [];
    $fichiers = [
        'salles' => 'salles.json',
        'promotions' => 'promotions.json',
        'options' => 'options.json',
        'cours' => 'cours.json',
    ];

    foreach ($fichiers as $cle => $nomFichier) {
        if (sauvegarder_json($nomFichier, liste_tableau($donnees[$cle] ?? []))) {
            $succes[] = sprintf('%s mis a jour.', $nomFichier);
        } else {
            $erreurs[] = sprintf('Echec de sauvegarde de %s.', $nomFichier);
        }
    }

    $csv = exporter_donnees_csv($donnees);
    $succes = array_merge($succes, $csv['succes']);
    $erreurs = array_merge($erreurs, $csv['erreurs']);

    return [
        'ok' => $erreurs === [],
        'succes' => $succes,
        'erreurs' => $erreurs,
    ];
}

function sauvegarder_rapport_occupation_txt(array $rapport): array
{
    $lignes = ['salle_id;designation;creneaux_occupes;creneaux_libres;taux_occupation'];

    foreach ($rapport as $ligne) {
        if (!is_array($ligne)) {
            continue;
        }

        $lignes[] = implode(';', [
            texte_tableau($ligne, 'salle_id'),
            texte_tableau($ligne, 'designation'),
            (string) entier_tableau($ligne, 'creneaux_occupes'),
            (string) entier_tableau($ligne, 'creneaux_libres'),
            number_format((float) (valeur_tableau($ligne, 'taux_occupation', 0.0)), 2, '.', '') . '%',
        ]);
    }

    $ok = ecrire_texte('rapport_occupation.txt', implode(PHP_EOL, $lignes) . PHP_EOL);

    return [
        'ok' => $ok,
        'succes' => $ok ? ['rapport_occupation.txt genere avec succes.'] : [],
        'erreurs' => $ok ? [] : ['Echec de sauvegarde de rapport_occupation.txt.'],
    ];
}

function parser_sous_groupes_formulaire(string $brut): array
{
    $sousGroupes = [];
    $brut = trim($brut);

    if ($brut === '') {
        return $sousGroupes;
    }

    $segments = array_filter(array_map('trim', explode(',', $brut)), static fn (string $segment): bool => $segment !== '');

    foreach ($segments as $segment) {
        $parties = array_map('trim', explode(':', $segment));
        if (count($parties) !== 3) {
            continue;
        }

        $sousGroupes[] = [
            'id' => $parties[0],
            'libelle' => $parties[1],
            'effectif' => is_numeric($parties[2]) ? (int) $parties[2] : 0,
        ];
    }

    return $sousGroupes;
}

function mettre_a_jour_collection_par_id(array $collection, array $element): array
{
    $id = texte_tableau($element, 'id');

    if ($id === '') {
        return $collection;
    }

    $miseAJour = false;

    foreach ($collection as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        if (texte_tableau($item, 'id') === $id) {
            $collection[$index] = $element;
            $miseAJour = true;
            break;
        }
    }

    if (!$miseAJour) {
        $collection[] = $element;
    }

    usort(
        $collection,
        static fn (array $a, array $b): int => strcmp(texte_tableau($a, 'id'), texte_tableau($b, 'id'))
    );

    return $collection;
}
