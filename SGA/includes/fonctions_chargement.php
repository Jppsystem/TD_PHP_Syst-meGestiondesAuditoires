<?php

declare(strict_types=1);

function chemin_data(string $nomFichier): string
{
    return __DIR__ . '/../data/' . $nomFichier;
}

function liste_tableau(mixed $valeur): array
{
    return is_array($valeur) ? $valeur : [];
}

function valeur_tableau(array $source, string $cle, mixed $parDefaut = null): mixed
{
    return array_key_exists($cle, $source) ? $source[$cle] : $parDefaut;
}

function texte_depuis_valeur(mixed $valeur, string $parDefaut = ''): string
{
    if (!is_scalar($valeur) && $valeur !== null) {
        return $parDefaut;
    }

    if ($valeur === null) {
        return $parDefaut;
    }

    return trim((string) $valeur);
}

function texte_tableau(array $source, string $cle, string $parDefaut = ''): string
{
    return texte_depuis_valeur(valeur_tableau($source, $cle, $parDefaut), $parDefaut);
}

function entier_tableau(array $source, string $cle, int $parDefaut = 0): int
{
    $valeur = valeur_tableau($source, $cle, $parDefaut);

    return is_numeric($valeur) ? (int) $valeur : $parDefaut;
}

function lire_fichier_brut(string $nomFichier): string
{
    $chemin = chemin_data($nomFichier);

    if (!is_file($chemin)) {
        throw new RuntimeException(sprintf('Fichier introuvable : %s', $nomFichier));
    }

    $contenu = file_get_contents($chemin);

    if ($contenu === false) {
        throw new RuntimeException(sprintf('Lecture impossible : %s', $nomFichier));
    }

    return $contenu;
}

function charger_json(string $nomFichier): array
{
    $contenu = trim(lire_fichier_brut($nomFichier));

    if ($contenu === '') {
        return [];
    }

    $donnees = json_decode($contenu, true);

    if (!is_array($donnees)) {
        throw new RuntimeException(sprintf('JSON invalide dans %s : %s', $nomFichier, json_last_error_msg()));
    }

    return $donnees;
}

function resultat_chargement_vide(): array
{
    return [
        'donnees' => [],
        'erreurs' => [],
        'avertissements' => [],
    ];
}

function valider_entrees_chargees(string $type, array $entrees, string $nomFichier): array
{
    $erreurs = [];

    foreach ($entrees as $index => $entree) {
        $numero = $index + 1;

        if (!is_array($entree)) {
            $erreurs[] = sprintf('%s : ligne %d malformee.', $nomFichier, $numero);
            continue;
        }

        if ($type === 'salles') {
            foreach (['id', 'designation', 'capacite'] as $champ) {
                if (texte_tableau($entree, $champ) === '') {
                    $erreurs[] = sprintf('%s : valeur manquante "%s" a la ligne %d.', $nomFichier, $champ, $numero);
                }
            }
        }

        if ($type === 'promotions') {
            foreach (['id', 'libelle', 'effectif_total'] as $champ) {
                if (texte_tableau($entree, $champ) === '') {
                    $erreurs[] = sprintf('%s : valeur manquante "%s" a la ligne %d.', $nomFichier, $champ, $numero);
                }
            }

            $sousGroupes = valeur_tableau($entree, 'sous_groupes', []);
            if ($sousGroupes !== [] && !is_array($sousGroupes)) {
                $erreurs[] = sprintf('%s : sous_groupes mal formes a la ligne %d.', $nomFichier, $numero);
                continue;
            }

            foreach (liste_tableau($sousGroupes) as $position => $sousGroupe) {
                if (!is_array($sousGroupe)) {
                    $erreurs[] = sprintf('%s : sous-groupe mal forme a la ligne %d.', $nomFichier, $numero);
                    continue;
                }

                foreach (['id', 'libelle', 'effectif'] as $champ) {
                    if (texte_tableau($sousGroupe, $champ) === '') {
                        $erreurs[] = sprintf(
                            '%s : valeur manquante "%s" dans le sous-groupe %d de la ligne %d.',
                            $nomFichier,
                            $champ,
                            $position + 1,
                            $numero
                        );
                    }
                }
            }
        }

        if ($type === 'options') {
            foreach (['id', 'libelle', 'promotion_parente', 'effectif'] as $champ) {
                if (texte_tableau($entree, $champ) === '') {
                    $erreurs[] = sprintf('%s : valeur manquante "%s" a la ligne %d.', $nomFichier, $champ, $numero);
                }
            }
        }

        if ($type === 'cours') {
            foreach (['id', 'intitule', 'volume_horaire', 'type'] as $champ) {
                if (texte_tableau($entree, $champ) === '') {
                    $erreurs[] = sprintf('%s : valeur manquante "%s" a la ligne %d.', $nomFichier, $champ, $numero);
                }
            }

            $typeCours = texte_tableau($entree, 'type');
            if ($typeCours === 'tronc_commun' && texte_tableau($entree, 'promotion_id') === '') {
                $erreurs[] = sprintf('%s : promotion_id manquant a la ligne %d.', $nomFichier, $numero);
            }

            if ($typeCours === 'option' && texte_tableau($entree, 'option_id') === '') {
                $erreurs[] = sprintf('%s : option_id manquant a la ligne %d.', $nomFichier, $numero);
            }
        }
    }

    return $erreurs;
}

function charger_collection_json(string $nomFichier, string $type, bool $optionnel = false): array
{
    $resultat = resultat_chargement_vide();

    try {
        $donnees = charger_json($nomFichier);
    } catch (Throwable $exception) {
        if ($optionnel && str_contains($exception->getMessage(), 'Fichier introuvable')) {
            $resultat['avertissements'][] = sprintf('%s absent pour le moment.', $nomFichier);
            return $resultat;
        }

        $resultat['erreurs'][] = $exception->getMessage();
        return $resultat;
    }

    $resultat['donnees'] = liste_tableau($donnees);
    $resultat['erreurs'] = array_merge(
        $resultat['erreurs'],
        valider_entrees_chargees($type, $resultat['donnees'], $nomFichier)
    );

    return $resultat;
}

function charger_donnees_sga(): array
{
    $resultat = [
        'donnees' => [
            'salles' => [],
            'promotions' => [],
            'cours' => [],
            'options' => [],
        ],
        'erreurs' => [],
        'avertissements' => [],
    ];

    $configurations = [
        'salles' => 'salles.json',
        'promotions' => 'promotions.json',
        'cours' => 'cours.json',
        'options' => 'options.json',
    ];

    foreach ($configurations as $type => $fichier) {
        $chargement = charger_collection_json($fichier, $type);
        $resultat['donnees'][$type] = $chargement['donnees'];
        $resultat['erreurs'] = array_merge($resultat['erreurs'], $chargement['erreurs']);
        $resultat['avertissements'] = array_merge($resultat['avertissements'], $chargement['avertissements']);
    }

    return $resultat;
}

function charger_planning_json(bool $optionnel = true): array
{
    return charger_collection_json('planning.json', 'planning', $optionnel);
}

function charger_planning_existant(): array
{
    $chargement = charger_planning_json();

    return $chargement['donnees'];
}

function charger_planning_texte(bool $optionnel = true): array
{
    $resultat = resultat_chargement_vide();
    $colonnes = ['jour', 'heure_debut', 'heure_fin', 'salle_id', 'cours_id', 'groupe_id'];
    $chemin = chemin_data('planning.txt');

    if (!is_file($chemin)) {
        if ($optionnel) {
            $resultat['avertissements'][] = 'planning.txt absent pour le moment.';
            return $resultat;
        }

        $resultat['erreurs'][] = 'Fichier introuvable : planning.txt';
        return $resultat;
    }

    $lignes = file($chemin, FILE_IGNORE_NEW_LINES);
    if ($lignes === false) {
        $resultat['erreurs'][] = 'Lecture impossible : planning.txt';
        return $resultat;
    }

    foreach ($lignes as $index => $ligne) {
        $numero = $index + 1;
        $ligne = trim((string) $ligne);

        if ($ligne === '') {
            continue;
        }

        $valeurs = str_getcsv($ligne, ';');
        $valeursNormalisees = array_map(
            static fn (mixed $valeur): string => trim((string) $valeur),
            $valeurs
        );

        if ($numero === 1 && $valeursNormalisees === $colonnes) {
            continue;
        }

        if (count($valeursNormalisees) !== count($colonnes)) {
            $resultat['erreurs'][] = sprintf('planning.txt : ligne %d malformee.', $numero);
            continue;
        }

        $entree = array_combine($colonnes, $valeursNormalisees);
        if (!is_array($entree)) {
            $resultat['erreurs'][] = sprintf('planning.txt : ligne %d malformee.', $numero);
            continue;
        }

        $manquants = [];
        foreach ($colonnes as $colonne) {
            if (texte_tableau($entree, $colonne) === '') {
                $manquants[] = $colonne;
            }
        }

        if ($manquants !== []) {
            $resultat['erreurs'][] = sprintf(
                'planning.txt : valeur(s) manquante(s) %s a la ligne %d.',
                implode(', ', $manquants),
                $numero
            );
            continue;
        }

        $entree['ligne_source'] = $numero;
        $resultat['donnees'][] = $entree;
    }

    return $resultat;
}

function lire_contenu_rapport_occupation(): string
{
    $chemin = chemin_data('rapport_occupation.txt');

    if (!is_file($chemin)) {
        return '';
    }

    $contenu = file_get_contents($chemin);

    return $contenu === false ? '' : trim($contenu);
}

function indexer_par_cle(array $elements, string $cle): array
{
    $index = [];

    foreach ($elements as $element) {
        if (!is_array($element)) {
            continue;
        }

        $identifiant = texte_tableau($element, $cle);

        if ($identifiant === '') {
            continue;
        }

        $index[$identifiant] = $element;
    }

    return $index;
}
