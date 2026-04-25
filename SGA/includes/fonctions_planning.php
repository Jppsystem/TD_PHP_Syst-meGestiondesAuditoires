<?php

declare(strict_types=1);

require_once __DIR__ . '/fonctions_contraintes.php';
require_once __DIR__ . '/fonctions_chargement.php';

function strategie_generation_planning(): array
{
    return [
        'Les promotions et options les plus volumineuses sont placees en premier pour reduire le risque de blocage.',
        'Chaque cours est decoupe en seances de 4 heures pour respecter les creneaux pedagogiques.',
        'Le moteur choisit la plus petite salle compatible afin de limiter le gaspillage de capacite.',
        'Une penalite legere evite de surcharger plusieurs seances d un meme groupe sur la meme journee.',
        'Chaque affectation est verifiee pour garantir l absence de conflit de salle et de groupe.',
    ];
}

function generer_planning(array $salles, array $promotions, array $options, array $cours): array
{
    $promotionsParId = indexer_par_cle($promotions, 'id');
    $optionsParId = indexer_par_cle($options, 'id');
    $creneaux = creneaux_pedagogiques();
    $sallesTriees = trier_salles_par_capacite($salles);

    $coursPrepares = [];
    $coursNonResolus = [];

    foreach ($cours as $ligneCours) {
        if (!is_array($ligneCours)) {
            $coursNonResolus[] = [
                'cours_id' => 'inconnu',
                'motif' => 'Definition de cours invalide.',
            ];
            continue;
        }

        $groupe = resoudre_groupe_cours($ligneCours, $promotionsParId, $optionsParId);

        if ($groupe === null) {
            $coursNonResolus[] = [
                'cours_id' => texte_tableau($ligneCours, 'id', 'inconnu'),
                'motif' => 'Groupe cible introuvable.',
            ];
            continue;
        }

        $volumeHoraire = entier_tableau($ligneCours, 'volume_horaire');

        $coursPrepares[] = [
            'id' => texte_tableau($ligneCours, 'id', 'inconnu'),
            'intitule' => texte_tableau($ligneCours, 'intitule', 'Cours sans titre'),
            'type' => texte_tableau($ligneCours, 'type'),
            'volume_horaire' => $volumeHoraire,
            'nombre_seances' => max(1, (int) ceil($volumeHoraire / 4)),
            'groupe' => $groupe,
        ];
    }

    usort(
        $coursPrepares,
        static function (array $coursA, array $coursB): int {
            $groupeA = is_array($coursA['groupe'] ?? null) ? $coursA['groupe'] : [];
            $groupeB = is_array($coursB['groupe'] ?? null) ? $coursB['groupe'] : [];
            $effectifA = entier_tableau($groupeA, 'effectif');
            $effectifB = entier_tableau($groupeB, 'effectif');

            if ($effectifA !== $effectifB) {
                return $effectifB <=> $effectifA;
            }

            $seancesA = is_numeric($coursA['nombre_seances'] ?? null) ? (int) $coursA['nombre_seances'] : 0;
            $seancesB = is_numeric($coursB['nombre_seances'] ?? null) ? (int) $coursB['nombre_seances'] : 0;

            if ($seancesA !== $seancesB) {
                return $seancesB <=> $seancesA;
            }

            return strcmp(texte_depuis_valeur($coursA['intitule'] ?? ''), texte_depuis_valeur($coursB['intitule'] ?? ''));
        }
    );

    $occupationsSalles = [];
    $occupationsGroupes = [];
    $chargesJourGroupe = [];
    $planning = [];
    $nonPlanifies = $coursNonResolus;

    foreach ($coursPrepares as $coursPrepare) {
        $volumeRestant = (int) $coursPrepare['volume_horaire'];

        for ($numeroSeance = 1; $numeroSeance <= (int) $coursPrepare['nombre_seances']; $numeroSeance++) {
            $emplacement = choisir_emplacement_cours(
                $creneaux,
                $sallesTriees,
                $coursPrepare,
                $occupationsSalles,
                $occupationsGroupes,
                $chargesJourGroupe
            );

            if ($emplacement === null) {
                $nonPlanifies[] = [
                    'cours_id' => $coursPrepare['id'],
                    'motif' => sprintf(
                        'Aucun creneau libre compatible pour la seance %d/%d.',
                        $numeroSeance,
                        (int) $coursPrepare['nombre_seances']
                    ),
                ];
                continue;
            }

            $creneau = is_array($emplacement['creneau'] ?? null) ? $emplacement['creneau'] : [];
            $salle = is_array($emplacement['salle'] ?? null) ? $emplacement['salle'] : [];
            $groupeCours = is_array($coursPrepare['groupe'] ?? null) ? $coursPrepare['groupe'] : [];
            $volumeSeance = min(4, $volumeRestant);

            $idSalle = texte_tableau($salle, 'id');
            $idCreneau = texte_tableau($creneau, 'id');
            $idGroupe = texte_tableau($groupeCours, 'id');
            $jour = texte_tableau($creneau, 'jour');

            if ($idSalle === '' || $idCreneau === '' || $idGroupe === '' || $jour === '') {
                $nonPlanifies[] = [
                    'cours_id' => texte_depuis_valeur($coursPrepare['id'] ?? 'inconnu', 'inconnu'),
                    'motif' => 'Emplacement incomplet lors de la generation.',
                ];
                continue;
            }

            $occupationsSalles[$idSalle][$idCreneau] = true;
            $occupationsGroupes[$idGroupe][$idCreneau] = true;
            $chargesJourGroupe[$idGroupe][$jour] = ($chargesJourGroupe[$idGroupe][$jour] ?? 0) + 1;

            $planning[] = [
                'jour' => $jour,
                'creneau' => texte_tableau($creneau, 'libelle'),
                'heure_debut' => texte_tableau($creneau, 'heure_debut'),
                'heure_fin' => texte_tableau($creneau, 'heure_fin'),
                'pause' => texte_tableau($creneau, 'pause'),
                'ordre_creneau' => entier_tableau($creneau, 'ordre'),
                'salle_id' => $idSalle,
                'salle' => texte_tableau($salle, 'designation'),
                'capacite_salle' => entier_tableau($salle, 'capacite'),
                'cours_id' => texte_depuis_valeur($coursPrepare['id'] ?? 'inconnu', 'inconnu'),
                'cours' => texte_depuis_valeur($coursPrepare['intitule'] ?? 'Cours sans titre', 'Cours sans titre'),
                'type_cours' => texte_depuis_valeur($coursPrepare['type'] ?? ''),
                'groupe_id' => $idGroupe,
                'groupe' => texte_tableau($groupeCours, 'libelle'),
                'promotion_id' => texte_tableau($groupeCours, 'promotion_id'),
                'option_id' => valeur_tableau($groupeCours, 'option_id'),
                'effectif_groupe' => entier_tableau($groupeCours, 'effectif'),
                'seance' => $numeroSeance,
                'nombre_seances' => is_numeric($coursPrepare['nombre_seances'] ?? null) ? (int) $coursPrepare['nombre_seances'] : 1,
                'volume_planifie' => $volumeSeance,
            ];

            $volumeRestant -= $volumeSeance;
        }
    }

    usort(
        $planning,
        static function (array $ligneA, array $ligneB): int {
            $ordreA = (int) ($ligneA['ordre_creneau'] ?? 0);
            $ordreB = (int) ($ligneB['ordre_creneau'] ?? 0);

            if ($ordreA !== $ordreB) {
                return $ordreA <=> $ordreB;
            }

            return strcmp((string) ($ligneA['salle'] ?? ''), (string) ($ligneB['salle'] ?? ''));
        }
    );

    return [
        'planning' => $planning,
        'non_planifies' => $nonPlanifies,
        'statistiques' => [
            'total_seances' => count($planning),
            'total_cours' => count($coursPrepares),
            'total_non_planifies' => count($nonPlanifies),
        ],
    ];
}

function choisir_emplacement_cours(
    array $creneaux,
    array $salles,
    array $cours,
    array $occupationsSalles,
    array $occupationsGroupes,
    array $chargesJourGroupe
): ?array {
    $meilleurChoix = null;
    $meilleurScore = null;
    $groupe = is_array($cours['groupe'] ?? null) ? $cours['groupe'] : [];
    $groupeId = texte_tableau($groupe, 'id');
    $effectif = entier_tableau($groupe, 'effectif');

    if ($groupeId === '' || $effectif <= 0) {
        return null;
    }

    foreach ($creneaux as $creneau) {
        if (!is_array($creneau)) {
            continue;
        }

        $creneauId = texte_tableau($creneau, 'id');
        $jour = texte_tableau($creneau, 'jour');

        if ($creneauId === '' || $jour === '') {
            continue;
        }

        if (isset($occupationsGroupes[$groupeId][$creneauId])) {
            continue;
        }

        $seancesMemeJour = (int) ($chargesJourGroupe[$groupeId][$jour] ?? 0);

        foreach ($salles as $salle) {
            if (!is_array($salle)) {
                continue;
            }

            $capacite = entier_tableau($salle, 'capacite');
            $idSalle = texte_tableau($salle, 'id');

            if ($capacite < $effectif || $idSalle === '') {
                continue;
            }

            if (isset($occupationsSalles[$idSalle][$creneauId])) {
                continue;
            }

            $score = ($seancesMemeJour * 1000)
                + ($capacite - $effectif)
                + (entier_tableau($creneau, 'ordre') * 10);

            if ($meilleurScore === null || $score < $meilleurScore) {
                $meilleurScore = $score;
                $meilleurChoix = [
                    'creneau' => $creneau,
                    'salle' => $salle,
                ];
            }
        }
    }

    return $meilleurChoix;
}

function ordonner_planning(array $planning): array
{
    usort(
        $planning,
        static function (array $ligneA, array $ligneB): int {
            $ordreA = entier_tableau($ligneA, 'ordre_creneau');
            $ordreB = entier_tableau($ligneB, 'ordre_creneau');

            if ($ordreA !== $ordreB) {
                return $ordreA <=> $ordreB;
            }

            $salleA = texte_tableau($ligneA, 'salle');
            $salleB = texte_tableau($ligneB, 'salle');
            if ($salleA !== $salleB) {
                return strcmp($salleA, $salleB);
            }

            return strcmp(texte_tableau($ligneA, 'cours_id'), texte_tableau($ligneB, 'cours_id'));
        }
    );

    return $planning;
}

function trouver_index_seance(array $planning, string $coursId, int $numeroSeance): ?int
{
    foreach ($planning as $index => $seance) {
        if (!is_array($seance)) {
            continue;
        }

        if (texte_tableau($seance, 'cours_id') === $coursId && entier_tableau($seance, 'seance', 1) === $numeroSeance) {
            return $index;
        }
    }

    return null;
}

function appliquer_mise_a_jour_manuelle(
    array $planning,
    array $salles,
    string $coursId,
    int $numeroSeance,
    string $nouveauCreneauId = '',
    string $nouvelleSalleId = ''
): array {
    $resultat = [
        'ok' => false,
        'planning' => $planning,
        'erreurs' => [],
        'avertissements' => [],
        'succes' => [],
    ];

    $indexSeance = trouver_index_seance($planning, $coursId, $numeroSeance);
    if ($indexSeance === null) {
        $resultat['erreurs'][] = 'Seance introuvable dans le planning courant.';
        return $resultat;
    }

    $seance = $planning[$indexSeance];
    if (!is_array($seance)) {
        $resultat['erreurs'][] = 'La seance selectionnee est invalide.';
        return $resultat;
    }

    $sallesParId = indexer_par_cle($salles, 'id');
    $creneauxParId = indexer_par_cle(creneaux_pedagogiques(), 'id');

    if ($nouvelleSalleId !== '') {
        if (!isset($sallesParId[$nouvelleSalleId])) {
            $resultat['erreurs'][] = 'La nouvelle salle est introuvable.';
            return $resultat;
        }

        $nouvelleSalle = $sallesParId[$nouvelleSalleId];
        $capacite = entier_tableau($nouvelleSalle, 'capacite');
        $effectif = entier_tableau($seance, 'effectif_groupe');

        if ($effectif > $capacite) {
            $resultat['erreurs'][] = sprintf(
                'La salle %s ne peut pas accueillir le groupe (%d/%d).',
                $nouvelleSalleId,
                $effectif,
                $capacite
            );
            return $resultat;
        }

        $seance['salle_id'] = $nouvelleSalleId;
        $seance['salle'] = texte_tableau($nouvelleSalle, 'designation');
        $seance['capacite_salle'] = $capacite;
    }

    if ($nouveauCreneauId !== '') {
        if (!isset($creneauxParId[$nouveauCreneauId])) {
            $resultat['erreurs'][] = 'Le nouveau creneau est introuvable.';
            return $resultat;
        }

        $creneau = $creneauxParId[$nouveauCreneauId];
        $seance['jour'] = texte_tableau($creneau, 'jour');
        $seance['creneau'] = texte_tableau($creneau, 'libelle');
        $seance['heure_debut'] = texte_tableau($creneau, 'heure_debut');
        $seance['heure_fin'] = texte_tableau($creneau, 'heure_fin');
        $seance['pause'] = texte_tableau($creneau, 'pause');
        $seance['ordre_creneau'] = entier_tableau($creneau, 'ordre');
    }

    $planningModifie = $planning;
    $planningModifie[$indexSeance] = $seance;
    $planningModifie = ordonner_planning($planningModifie);

    $validation = verifier_planning($planningModifie, $salles);
    if ($validation['erreurs'] !== []) {
        $resultat['erreurs'] = $validation['erreurs'];
        return $resultat;
    }

    $resultat['ok'] = true;
    $resultat['planning'] = $planningModifie;
    $resultat['succes'][] = sprintf(
        'La seance %d du cours %s a ete mise a jour avec succes.',
        $numeroSeance,
        $coursId
    );

    return $resultat;
}

function calculer_rapport_occupation(array $planning, array $salles): array
{
    $rapport = [];
    $totalCreneaux = count(creneaux_pedagogiques());
    $occupationsParSalle = [];

    foreach ($planning as $seance) {
        if (!is_array($seance)) {
            continue;
        }

        $salleId = texte_tableau($seance, 'salle_id');
        $jour = texte_tableau($seance, 'jour');
        $heureDebut = texte_tableau($seance, 'heure_debut');
        $heureFin = texte_tableau($seance, 'heure_fin');

        if ($salleId === '' || $jour === '' || $heureDebut === '' || $heureFin === '') {
            continue;
        }

        $occupationsParSalle[$salleId][$jour . '|' . $heureDebut . '|' . $heureFin] = true;
    }

    foreach ($salles as $salle) {
        if (!is_array($salle)) {
            continue;
        }

        $salleId = texte_tableau($salle, 'id');
        $occupes = isset($occupationsParSalle[$salleId]) ? count($occupationsParSalle[$salleId]) : 0;
        $libres = max(0, $totalCreneaux - $occupes);
        $taux = $totalCreneaux > 0 ? round(($occupes / $totalCreneaux) * 100, 2) : 0.0;

        $rapport[] = [
            'salle_id' => $salleId,
            'designation' => texte_tableau($salle, 'designation'),
            'creneaux_occupes' => $occupes,
            'creneaux_libres' => $libres,
            'taux_occupation' => $taux,
        ];
    }

    return $rapport;
}
