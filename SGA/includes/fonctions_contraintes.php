<?php

declare(strict_types=1);

require_once __DIR__ . '/fonctions_chargement.php';

function creneaux_pedagogiques(): array
{
    $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];
    $periodes = [
        [
            'code' => 'MATIN',
            'heure_debut' => '08:00',
            'heure_fin' => '12:00',
            'pause' => '12:00-13:00',
        ],
        [
            'code' => 'APRES_MIDI',
            'heure_debut' => '13:00',
            'heure_fin' => '17:00',
            'pause' => '12:00-13:00',
        ],
    ];

    $creneaux = [];
    $ordre = 0;

    foreach ($jours as $jour) {
        foreach ($periodes as $periode) {
            $ordre++;
            $idJour = strtoupper(substr($jour, 0, 3));

            $creneaux[] = [
                'id' => sprintf('%s_%s', $idJour, $periode['code']),
                'jour' => $jour,
                'heure_debut' => $periode['heure_debut'],
                'heure_fin' => $periode['heure_fin'],
                'pause' => $periode['pause'],
                'libelle' => sprintf('%s %s-%s', $jour, $periode['heure_debut'], $periode['heure_fin']),
                'ordre' => $ordre,
            ];
        }
    }

    return $creneaux;
}

function verifier_identifiants_uniques(array $elements, string $cle, string $libelle): array
{
    $vus = [];
    $erreurs = [];

    foreach ($elements as $element) {
        if (!is_array($element)) {
            $erreurs[] = sprintf('Une entree invalide a ete detectee dans %s.', $libelle);
            continue;
        }

        $valeur = texte_tableau($element, $cle);

        if ($valeur === '') {
            $erreurs[] = sprintf('%s sans identifiant detecte.', $libelle);
            continue;
        }

        if (isset($vus[$valeur])) {
            $erreurs[] = sprintf('Identifiant duplique dans %s : %s', $libelle, $valeur);
            continue;
        }

        $vus[$valeur] = true;
    }

    return $erreurs;
}

function verifier_coherence_donnees(array $salles, array $promotions, array $options, array $cours): array
{
    $erreurs = [];
    $avertissements = [];

    $erreurs = array_merge(
        $erreurs,
        verifier_identifiants_uniques($salles, 'id', 'les salles'),
        verifier_identifiants_uniques($promotions, 'id', 'les promotions'),
        verifier_identifiants_uniques($options, 'id', 'les options'),
        verifier_identifiants_uniques($cours, 'id', 'les cours')
    );

    if ($salles === []) {
        $erreurs[] = 'Aucune salle n est disponible.';
    }

    $capaciteMax = 0;
    foreach ($salles as $salle) {
        if (!is_array($salle)) {
            $erreurs[] = 'Une salle mal formee a ete ignoree.';
            continue;
        }

        $capacite = entier_tableau($salle, 'capacite');
        $capaciteMax = max($capaciteMax, $capacite);

        if (texte_tableau($salle, 'designation') === '' || $capacite <= 0) {
            $erreurs[] = sprintf('Salle invalide detectee : %s', texte_tableau($salle, 'id', 'inconnue'));
        }
    }

    $promotionsParId = [];
    foreach ($promotions as $promotion) {
        if (!is_array($promotion)) {
            $erreurs[] = 'Une promotion mal formee a ete ignoree.';
            continue;
        }

        $id = texte_tableau($promotion, 'id');
        $effectifTotal = entier_tableau($promotion, 'effectif_total');

        if ($id === '' || texte_tableau($promotion, 'libelle') === '' || $effectifTotal <= 0) {
            $erreurs[] = sprintf('Promotion invalide detectee : %s', $id !== '' ? $id : 'inconnue');
            continue;
        }

        if ($effectifTotal > $capaciteMax) {
            $erreurs[] = sprintf('La promotion %s depasse la plus grande salle disponible.', $id);
        }

        $sousGroupes = liste_tableau(valeur_tableau($promotion, 'sous_groupes', []));
        $totalSousGroupes = 0;
        foreach ($sousGroupes as $sousGroupe) {
            if (!is_array($sousGroupe)) {
                $erreurs[] = sprintf('Sous-groupe mal forme dans la promotion %s.', $id);
                continue;
            }

            $effectifSousGroupe = entier_tableau($sousGroupe, 'effectif');
            if (texte_tableau($sousGroupe, 'id') === '' || texte_tableau($sousGroupe, 'libelle') === '' || $effectifSousGroupe <= 0) {
                $erreurs[] = sprintf('Sous-groupe invalide dans la promotion %s.', $id);
            }
            $totalSousGroupes += $effectifSousGroupe;
        }

        if ($sousGroupes !== [] && $totalSousGroupes !== $effectifTotal) {
            $avertissements[] = sprintf(
                'La somme des sous-groupes de %s (%d) ne correspond pas a l effectif total (%d).',
                $id,
                $totalSousGroupes,
                $effectifTotal
            );
        }

        $promotionsParId[$id] = $promotion;
    }

    $optionsParId = [];
    $totauxOptionsParPromotion = [];
    foreach ($options as $option) {
        if (!is_array($option)) {
            $erreurs[] = 'Une option mal formee a ete ignoree.';
            continue;
        }

        $id = texte_tableau($option, 'id');
        $promotionParente = texte_tableau($option, 'promotion_parente');
        $effectif = entier_tableau($option, 'effectif');

        if ($id === '' || texte_tableau($option, 'libelle') === '' || $promotionParente === '' || $effectif <= 0) {
            $erreurs[] = sprintf('Option invalide detectee : %s', $id !== '' ? $id : 'inconnue');
            continue;
        }

        if (!isset($promotionsParId[$promotionParente])) {
            $erreurs[] = sprintf('L option %s reference la promotion inconnue %s.', $id, $promotionParente);
            continue;
        }

        if (!in_array($promotionParente, ['L3', 'L4'], true)) {
            $erreurs[] = sprintf('L option %s doit etre rattachee a L3 ou L4.', $id);
        }

        if ($effectif > $capaciteMax) {
            $erreurs[] = sprintf('L option %s depasse la capacite maximale des salles.', $id);
        }

        $totauxOptionsParPromotion[$promotionParente] = ($totauxOptionsParPromotion[$promotionParente] ?? 0) + $effectif;
        $optionsParId[$id] = $option;
    }

    foreach ($totauxOptionsParPromotion as $promotionId => $effectifTotalOptions) {
        $effectifPromotion = isset($promotionsParId[$promotionId]) && is_array($promotionsParId[$promotionId])
            ? entier_tableau($promotionsParId[$promotionId], 'effectif_total')
            : 0;
        if ($effectifPromotion > 0 && $effectifTotalOptions !== $effectifPromotion) {
            $avertissements[] = sprintf(
                'La somme des effectifs des options de %s (%d) ne correspond pas a l effectif de la promotion (%d).',
                $promotionId,
                $effectifTotalOptions,
                $effectifPromotion
            );
        }
    }

    foreach ($cours as $ligneCours) {
        if (!is_array($ligneCours)) {
            $erreurs[] = 'Un cours mal forme a ete ignore.';
            continue;
        }

        $id = texte_tableau($ligneCours, 'id');
        $type = texte_tableau($ligneCours, 'type');
        $volume = entier_tableau($ligneCours, 'volume_horaire');

        if ($id === '' || texte_tableau($ligneCours, 'intitule') === '' || $volume <= 0) {
            $erreurs[] = sprintf('Cours invalide detecte : %s', $id !== '' ? $id : 'inconnu');
            continue;
        }

        if (!in_array($type, ['tronc_commun', 'option'], true)) {
            $erreurs[] = sprintf('Le cours %s possede un type non supporte : %s', $id, $type);
            continue;
        }

        if ($volume % 4 !== 0) {
            $avertissements[] = sprintf(
                'Le volume horaire du cours %s n est pas multiple de 4h. Le dernier creneau sera partiellement occupe.',
                $id
            );
        }

        if ($type === 'tronc_commun') {
            $promotionId = texte_tableau($ligneCours, 'promotion_id');
            if ($promotionId === '' || !isset($promotionsParId[$promotionId])) {
                $erreurs[] = sprintf('Le cours %s reference une promotion invalide.', $id);
            }
        }

        if ($type === 'option') {
            $optionId = texte_tableau($ligneCours, 'option_id');
            if ($optionId === '' || !isset($optionsParId[$optionId])) {
                $erreurs[] = sprintf('Le cours %s reference une option invalide.', $id);
            }
        }
    }

    return [
        'erreurs' => array_values(array_unique($erreurs)),
        'avertissements' => array_values(array_unique($avertissements)),
    ];
}

function detecter_conflits_planning(array $planning): array
{
    $conflits = [];
    $occupationsSalles = [];
    $occupationsGroupes = [];

    foreach ($planning as $index => $seance) {
        if (!is_array($seance)) {
            $conflits[] = sprintf('Entree de planning invalide a la position %d.', $index + 1);
            continue;
        }

        $jour = texte_tableau($seance, 'jour');
        $heureDebut = texte_tableau($seance, 'heure_debut');
        $heureFin = texte_tableau($seance, 'heure_fin');
        $salleId = texte_tableau($seance, 'salle_id');
        $coursId = texte_tableau($seance, 'cours_id');
        $groupeId = texte_tableau($seance, 'groupe_id');

        if ($jour === '' || $heureDebut === '' || $heureFin === '' || $salleId === '' || $coursId === '' || $groupeId === '') {
            $conflits[] = sprintf('Seance incomplete detectee a la position %d.', $index + 1);
            continue;
        }

        $creneauCle = sprintf('%s %s-%s', $jour, $heureDebut, $heureFin);
        $cleSalle = $salleId . '|' . $creneauCle;
        $cleGroupe = $groupeId . '|' . $creneauCle;

        if (isset($occupationsSalles[$cleSalle])) {
            $conflits[] = sprintf(
                'Conflit de salle : %s est occupee simultanement pour %s et %s (%s).',
                $salleId,
                $occupationsSalles[$cleSalle],
                $coursId,
                $creneauCle
            );
        } else {
            $occupationsSalles[$cleSalle] = $coursId;
        }

        if (isset($occupationsGroupes[$cleGroupe])) {
            $conflits[] = sprintf(
                'Conflit de groupe : %s suit simultanement %s et %s (%s).',
                $groupeId,
                $occupationsGroupes[$cleGroupe],
                $coursId,
                $creneauCle
            );
        } else {
            $occupationsGroupes[$cleGroupe] = $coursId;
        }
    }

    return array_values(array_unique($conflits));
}

function verifier_structure_planning(array $planning): array
{
    $erreurs = [];

    foreach ($planning as $index => $seance) {
        if (!is_array($seance)) {
            $erreurs[] = sprintf('Seance malformee a la position %d.', $index + 1);
            continue;
        }

        foreach (['jour', 'heure_debut', 'heure_fin', 'salle_id', 'cours_id', 'groupe_id'] as $champ) {
            if (texte_tableau($seance, $champ) === '') {
                $erreurs[] = sprintf('Champ "%s" manquant dans la seance %d.', $champ, $index + 1);
            }
        }
    }

    return array_values(array_unique($erreurs));
}

function verifier_capacites_planning(array $planning, array $salles): array
{
    $erreurs = [];
    $sallesParId = indexer_par_cle($salles, 'id');

    foreach ($planning as $index => $seance) {
        if (!is_array($seance)) {
            continue;
        }

        $salleId = texte_tableau($seance, 'salle_id');
        $effectif = entier_tableau($seance, 'effectif_groupe');

        if ($salleId === '' || !isset($sallesParId[$salleId])) {
            $erreurs[] = sprintf('Salle inconnue dans la seance %d.', $index + 1);
            continue;
        }

        $capacite = entier_tableau($sallesParId[$salleId], 'capacite');
        if ($effectif > $capacite) {
            $erreurs[] = sprintf(
                'Capacite insuffisante pour %s dans la salle %s (%d/%d).',
                texte_tableau($seance, 'cours_id', 'cours inconnu'),
                $salleId,
                $effectif,
                $capacite
            );
        }
    }

    return array_values(array_unique($erreurs));
}

function verifier_planning(array $planning, array $salles): array
{
    $erreurs = array_merge(
        verifier_structure_planning($planning),
        verifier_capacites_planning($planning, $salles),
        detecter_conflits_planning($planning)
    );

    return [
        'erreurs' => array_values(array_unique($erreurs)),
        'avertissements' => [],
    ];
}

function resoudre_groupe_cours(array $cours, array $promotionsParId, array $optionsParId): ?array
{
    $type = texte_tableau($cours, 'type');

    if ($type === 'tronc_commun') {
        $promotionId = texte_tableau($cours, 'promotion_id');
        if (!isset($promotionsParId[$promotionId])) {
            return null;
        }

        $promotion = $promotionsParId[$promotionId];
        if (!is_array($promotion)) {
            return null;
        }

        return [
            'id' => 'PROMO-' . $promotionId,
            'libelle' => texte_tableau($promotion, 'libelle'),
            'effectif' => entier_tableau($promotion, 'effectif_total'),
            'promotion_id' => $promotionId,
            'option_id' => null,
            'nature' => 'promotion',
        ];
    }

    if ($type === 'option') {
        $optionId = texte_tableau($cours, 'option_id');
        if (!isset($optionsParId[$optionId])) {
            return null;
        }

        $option = $optionsParId[$optionId];
        if (!is_array($option)) {
            return null;
        }

        return [
            'id' => 'OPTION-' . $optionId,
            'libelle' => texte_tableau($option, 'libelle'),
            'effectif' => entier_tableau($option, 'effectif'),
            'promotion_id' => texte_tableau($option, 'promotion_parente'),
            'option_id' => $optionId,
            'nature' => 'option',
        ];
    }

    return null;
}

function trier_salles_par_capacite(array $salles): array
{
    usort(
        $salles,
        static function (array $salleA, array $salleB): int {
            $capaciteA = entier_tableau($salleA, 'capacite');
            $capaciteB = entier_tableau($salleB, 'capacite');

            if ($capaciteA === $capaciteB) {
                return strcmp(texte_tableau($salleA, 'designation'), texte_tableau($salleB, 'designation'));
            }

            return $capaciteA <=> $capaciteB;
        }
    );

    return $salles;
}
