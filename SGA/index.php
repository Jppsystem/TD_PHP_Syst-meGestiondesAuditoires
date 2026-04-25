<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/fonctions_chargement.php';
require_once __DIR__ . '/includes/fonctions_contraintes.php';
require_once __DIR__ . '/includes/fonctions_planning.php';
require_once __DIR__ . '/includes/fonctions_affichage.php';
require_once __DIR__ . '/includes/fonctions_sauvegarde.php';

function resultat_traitement(array $donnees): array
{
    return [
        'ok' => false,
        'donnees' => $donnees,
        'succes' => [],
        'avertissements' => [],
        'erreurs' => [],
    ];
}

function traiter_formulaire_configuration(string $action, array $post, array $donnees): array
{
    $resultat = resultat_traitement($donnees);
    $donneesModifiees = $donnees;

    if ($action === 'enregistrer_salle') {
        $element = [
            'id' => strtoupper(texte_depuis_valeur($post['salle_id'] ?? '')),
            'designation' => texte_depuis_valeur($post['salle_designation'] ?? ''),
            'capacite' => is_numeric($post['salle_capacite'] ?? null) ? (int) $post['salle_capacite'] : 0,
        ];

        if ($element['id'] === '' || $element['designation'] === '' || $element['capacite'] <= 0) {
            $resultat['erreurs'][] = 'Les champs de la salle sont incomplets ou invalides.';
            return $resultat;
        }

        $donneesModifiees['salles'] = mettre_a_jour_collection_par_id($donnees['salles'], $element);
    }

    if ($action === 'enregistrer_promotion') {
        $sousGroupesBruts = texte_depuis_valeur($post['promotion_sous_groupes'] ?? '');
        $sousGroupes = parser_sous_groupes_formulaire($sousGroupesBruts);

        if ($sousGroupesBruts !== '' && $sousGroupes === []) {
            $resultat['erreurs'][] = 'Le format des sous-groupes est invalide. Utilise par exemple L1-A:Groupe A:60, L1-B:Groupe B:60.';
            return $resultat;
        }

        $element = [
            'id' => strtoupper(texte_depuis_valeur($post['promotion_id'] ?? '')),
            'libelle' => texte_depuis_valeur($post['promotion_libelle'] ?? ''),
            'effectif_total' => is_numeric($post['promotion_effectif_total'] ?? null) ? (int) $post['promotion_effectif_total'] : 0,
            'sous_groupes' => $sousGroupes,
        ];

        if ($element['id'] === '' || $element['libelle'] === '' || $element['effectif_total'] <= 0) {
            $resultat['erreurs'][] = 'Les champs de la promotion sont incomplets ou invalides.';
            return $resultat;
        }

        $donneesModifiees['promotions'] = mettre_a_jour_collection_par_id($donnees['promotions'], $element);
    }

    if ($action === 'enregistrer_option') {
        $element = [
            'id' => strtoupper(texte_depuis_valeur($post['option_id'] ?? '')),
            'libelle' => texte_depuis_valeur($post['option_libelle'] ?? ''),
            'promotion_parente' => strtoupper(texte_depuis_valeur($post['option_promotion_parente'] ?? '')),
            'effectif' => is_numeric($post['option_effectif'] ?? null) ? (int) $post['option_effectif'] : 0,
        ];

        if ($element['id'] === '' || $element['libelle'] === '' || $element['promotion_parente'] === '' || $element['effectif'] <= 0) {
            $resultat['erreurs'][] = 'Les champs de l option sont incomplets ou invalides.';
            return $resultat;
        }

        $donneesModifiees['options'] = mettre_a_jour_collection_par_id($donnees['options'], $element);
    }

    if ($action === 'enregistrer_cours') {
        $typeCours = texte_depuis_valeur($post['cours_type'] ?? '');
        $element = [
            'id' => strtoupper(texte_depuis_valeur($post['cours_id'] ?? '')),
            'intitule' => texte_depuis_valeur($post['cours_intitule'] ?? ''),
            'volume_horaire' => is_numeric($post['cours_volume_horaire'] ?? null) ? (int) $post['cours_volume_horaire'] : 0,
            'type' => $typeCours,
            'promotion_id' => strtoupper(texte_depuis_valeur($post['cours_promotion_id'] ?? '')),
            'option_id' => strtoupper(texte_depuis_valeur($post['cours_option_id'] ?? '')),
        ];

        if ($element['id'] === '' || $element['intitule'] === '' || $element['volume_horaire'] <= 0 || !in_array($typeCours, ['tronc_commun', 'option'], true)) {
            $resultat['erreurs'][] = 'Les champs du cours sont incomplets ou invalides.';
            return $resultat;
        }

        if ($typeCours === 'tronc_commun' && $element['promotion_id'] === '') {
            $resultat['erreurs'][] = 'promotion_id est obligatoire pour un cours de tronc commun.';
            return $resultat;
        }

        if ($typeCours === 'option' && $element['option_id'] === '') {
            $resultat['erreurs'][] = 'option_id est obligatoire pour un cours d option.';
            return $resultat;
        }

        if ($typeCours === 'tronc_commun') {
            $element['option_id'] = '';
        } else {
            $element['promotion_id'] = '';
        }

        $donneesModifiees['cours'] = mettre_a_jour_collection_par_id($donnees['cours'], $element);
    }

    if ($donneesModifiees === $donnees) {
        $resultat['erreurs'][] = 'Aucune action de configuration valide n a ete detectee.';
        return $resultat;
    }

    $validation = verifier_coherence_donnees(
        $donneesModifiees['salles'],
        $donneesModifiees['promotions'],
        $donneesModifiees['options'],
        $donneesModifiees['cours']
    );

    if ($validation['erreurs'] !== []) {
        $resultat['erreurs'] = array_merge($resultat['erreurs'], $validation['erreurs']);
        $resultat['avertissements'] = array_merge($resultat['avertissements'], $validation['avertissements']);
        return $resultat;
    }

    $sauvegarde = sauvegarder_donnees_sga($donneesModifiees);
    $resultat['ok'] = $sauvegarde['ok'];
    $resultat['donnees'] = $donneesModifiees;
    $resultat['succes'] = array_merge($resultat['succes'], $sauvegarde['succes']);
    $resultat['erreurs'] = array_merge($resultat['erreurs'], $sauvegarde['erreurs']);
    $resultat['avertissements'] = array_merge(
        $resultat['avertissements'],
        $validation['avertissements'],
        ['La configuration a ete mise a jour. Pense a regenerer le planning si les donnees ont change.']
    );

    return $resultat;
}

$titrePage = 'SGA - Planning hebdomadaire';
$messagesSucces = [];
$messagesAvertissement = [];
$messagesErreur = [];
$conflitsDetectes = [];
$rapportOccupation = [];
$donnees = [
    'salles' => [],
    'promotions' => [],
    'cours' => [],
    'options' => [],
];

$chargementDonnees = charger_donnees_sga();
$donnees = $chargementDonnees['donnees'];
$messagesErreur = array_merge($messagesErreur, $chargementDonnees['erreurs']);
$messagesAvertissement = array_merge($messagesAvertissement, $chargementDonnees['avertissements']);

$chargementPlanningJson = charger_planning_json();
$planningAffiche = $chargementPlanningJson['donnees'];
$messagesErreur = array_merge($messagesErreur, $chargementPlanningJson['erreurs']);

$chargementPlanningTexte = charger_planning_texte();
$planningTexte = $chargementPlanningTexte['donnees'];
$messagesErreur = array_merge($messagesErreur, $chargementPlanningTexte['erreurs']);

$rapportTexte = lire_contenu_rapport_occupation();
$action = texte_depuis_valeur($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'generer_planning') {
        $validation = verifier_coherence_donnees(
            $donnees['salles'],
            $donnees['promotions'],
            $donnees['options'],
            $donnees['cours']
        );

        $messagesAvertissement = array_merge($messagesAvertissement, $validation['avertissements']);

        if ($validation['erreurs'] !== []) {
            $messagesErreur = array_merge($messagesErreur, $validation['erreurs']);
        } else {
            $resultat = generer_planning(
                $donnees['salles'],
                $donnees['promotions'],
                $donnees['options'],
                $donnees['cours']
            );

            $planningAffiche = $resultat['planning'];
            $controlePlanning = verifier_planning($planningAffiche, $donnees['salles']);

            if ($controlePlanning['erreurs'] !== []) {
                $messagesErreur = array_merge($messagesErreur, $controlePlanning['erreurs']);
            } else {
                $sauvegarde = sauvegarder_planning($planningAffiche);
                $messagesSucces = array_merge($messagesSucces, $sauvegarde['succes']);
                $messagesErreur = array_merge($messagesErreur, $sauvegarde['erreurs']);

                $chargementPlanningTexte = charger_planning_texte(false);
                $planningTexte = $chargementPlanningTexte['donnees'];

                if ($resultat['non_planifies'] === []) {
                    $messagesSucces[] = 'Le planning a ete genere sans conflit ni seance non affectee.';
                } else {
                    foreach ($resultat['non_planifies'] as $element) {
                        if (!is_array($element)) {
                            continue;
                        }

                        $messagesAvertissement[] = sprintf(
                            'Cours %s : %s',
                            texte_tableau($element, 'cours_id', 'inconnu'),
                            texte_tableau($element, 'motif', 'non planifie')
                        );
                    }
                }
            }
        }
    }

    if ($action === 'detecter_conflits') {
        $chargementPlanningTexte = charger_planning_texte(false);
        $planningTexte = $chargementPlanningTexte['donnees'];
        $messagesErreur = array_merge($messagesErreur, $chargementPlanningTexte['erreurs']);

        if ($chargementPlanningTexte['erreurs'] === []) {
            $conflitsDetectes = detecter_conflits_planning($planningTexte);
            if ($conflitsDetectes === []) {
                $messagesSucces[] = 'Aucun conflit detecte dans planning.txt.';
            } else {
                $messagesAvertissement[] = sprintf('%d conflit(s) detecte(s) dans planning.txt.', count($conflitsDetectes));
            }
        }
    }

    if ($action === 'generer_rapport') {
        $chargementPlanningTexte = charger_planning_texte(false);
        $planningTexte = $chargementPlanningTexte['donnees'];
        $messagesErreur = array_merge($messagesErreur, $chargementPlanningTexte['erreurs']);

        if ($chargementPlanningTexte['erreurs'] === []) {
            $rapportOccupation = calculer_rapport_occupation($planningTexte, $donnees['salles']);
            $sauvegardeRapport = sauvegarder_rapport_occupation_txt($rapportOccupation);
            $messagesSucces = array_merge($messagesSucces, $sauvegardeRapport['succes']);
            $messagesErreur = array_merge($messagesErreur, $sauvegardeRapport['erreurs']);
            $rapportTexte = lire_contenu_rapport_occupation();
        }
    }

    if ($action === 'modifier_planning') {
        $sessionCible = texte_depuis_valeur($_POST['session_cible'] ?? '');
        $nouveauCreneauId = texte_depuis_valeur($_POST['nouveau_creneau_id'] ?? '');
        $nouvelleSalleId = texte_depuis_valeur($_POST['nouvelle_salle_id'] ?? '');

        if ($sessionCible === '' || ($nouveauCreneauId === '' && $nouvelleSalleId === '')) {
            $messagesErreur[] = 'Selectionne une seance et au moins une modification de salle ou de creneau.';
        } else {
            $parties = explode('::', $sessionCible);
            $coursId = $parties[0] ?? '';
            $numeroSeance = isset($parties[1]) && is_numeric($parties[1]) ? (int) $parties[1] : 0;

            if ($coursId === '' || $numeroSeance <= 0) {
                $messagesErreur[] = 'La seance selectionnee est invalide.';
            } else {
                $chargementPlanningJson = charger_planning_json(false);
                $planningAffiche = $chargementPlanningJson['donnees'];
                $messagesErreur = array_merge($messagesErreur, $chargementPlanningJson['erreurs']);

                if ($chargementPlanningJson['erreurs'] === []) {
                    $miseAJour = appliquer_mise_a_jour_manuelle(
                        $planningAffiche,
                        $donnees['salles'],
                        $coursId,
                        $numeroSeance,
                        $nouveauCreneauId,
                        $nouvelleSalleId
                    );

                    if ($miseAJour['ok']) {
                        $planningAffiche = $miseAJour['planning'];
                        $sauvegarde = sauvegarder_planning($planningAffiche);
                        $messagesSucces = array_merge($messagesSucces, $miseAJour['succes'], $sauvegarde['succes']);
                        $messagesErreur = array_merge($messagesErreur, $sauvegarde['erreurs']);

                        $chargementPlanningTexte = charger_planning_texte(false);
                        $planningTexte = $chargementPlanningTexte['donnees'];
                    } else {
                        $messagesErreur = array_merge($messagesErreur, $miseAJour['erreurs']);
                        $messagesAvertissement = array_merge($messagesAvertissement, $miseAJour['avertissements']);
                    }
                }
            }
        }
    }

    if (in_array($action, ['enregistrer_salle', 'enregistrer_promotion', 'enregistrer_option', 'enregistrer_cours'], true)) {
        $traitement = traiter_formulaire_configuration($action, $_POST, $donnees);
        $messagesSucces = array_merge($messagesSucces, $traitement['succes']);
        $messagesAvertissement = array_merge($messagesAvertissement, $traitement['avertissements']);
        $messagesErreur = array_merge($messagesErreur, $traitement['erreurs']);

        if ($traitement['ok']) {
            $donnees = $traitement['donnees'];
        }
    }
}

$capacites = array_map(
    static fn (array $salle): int => entier_tableau($salle, 'capacite'),
    array_filter($donnees['salles'], 'is_array')
);
$cartes = [
    [
        'label' => 'Salles',
        'valeur' => (string) count($donnees['salles']),
        'note' => 'Capacite max : ' . ($capacites !== [] ? max($capacites) : 0) . ' places',
    ],
    [
        'label' => 'Promotions',
        'valeur' => (string) count($donnees['promotions']),
        'note' => 'Fichiers charges en tableaux associatifs',
    ],
    [
        'label' => 'Options',
        'valeur' => (string) count($donnees['options']),
        'note' => 'Specialisations L3 et L4',
    ],
    [
        'label' => 'Cours',
        'valeur' => (string) count($donnees['cours']),
        'note' => 'Tronc commun et options',
    ],
    [
        'label' => 'Seances',
        'valeur' => (string) count($planningAffiche),
        'note' => 'Sauvegarde en JSON et TXT',
    ],
];

require __DIR__ . '/templates/header.php';
?>

<?= afficher_alertes($messagesSucces, 'success'); ?>
<?= afficher_alertes($messagesAvertissement, 'warning'); ?>
<?= afficher_alertes($messagesErreur, 'danger'); ?>

<section class="bloc">
    <h2>Script principal</h2>
    <div class="actions-grid">
        <form method="post" class="action-card">
            <input type="hidden" name="action" value="generer_planning">
            <h3>1. Generer le planning</h3>
            <p>Charge les donnees, verifie les contraintes, construit un planning hebdomadaire sans conflit puis l enregistre dans <code>planning.json</code> et <code>planning.txt</code>.</p>
            <button type="submit">Generer</button>
        </form>

        <form method="post" class="action-card">
            <input type="hidden" name="action" value="detecter_conflits">
            <h3>2. Detecter les conflits</h3>
            <p>Analyse le fichier <code>planning.txt</code> deja sauvegarde pour repérer les conflits de salle et de groupe sur un meme creneau.</p>
            <button type="submit">Analyser</button>
        </form>

        <form method="post" class="action-card">
            <input type="hidden" name="action" value="generer_rapport">
            <h3>3. Generer le rapport</h3>
            <p>Lit le planning sauvegarde et calcule le nombre de creneaux occupes, libres et le taux d occupation de chaque salle.</p>
            <button type="submit">Produire le rapport</button>
        </form>
    </div>
    <p class="help-text">Toutes les etapes du traitement affichent des messages clairs en cas de succes, de validation ou d erreur.</p>
</section>

<?= afficher_cartes_resume($cartes); ?>
<?= afficher_contraintes(); ?>
<?= afficher_strategie_generation(strategie_generation_planning()); ?>

<section class="bloc">
    <h2>Planning hebdomadaire recharge depuis planning.json</h2>
    <?= afficher_grille_hebdomadaire($planningAffiche); ?>
</section>

<section class="bloc">
    <h2>Planning detaille</h2>
    <?= afficher_tableau_planning($planningAffiche); ?>
</section>

<section class="bloc">
    <h2>Mise a jour manuelle du planning</h2>
    <?= afficher_formulaire_mise_a_jour_planning($planningAffiche, $donnees['salles']); ?>
</section>

<section class="bloc">
    <h2>Conflits detectes dans planning.txt</h2>
    <?= afficher_tableau_conflits($conflitsDetectes); ?>
</section>

<section class="bloc">
    <h2>Rapport d occupation des salles</h2>
    <?= afficher_tableau_rapport_occupation($rapportOccupation); ?>
    <h3>Contenu actuel de rapport_occupation.txt</h3>
    <?= afficher_fichier_texte($rapportTexte); ?>
</section>

<?= afficher_formulaires_configuration(); ?>

<section class="bloc">
    <h2>Salles</h2>
    <?= afficher_tableau_salles($donnees['salles']); ?>
</section>

<section class="bloc">
    <h2>Promotions</h2>
    <?= afficher_tableau_promotions($donnees['promotions']); ?>
</section>

<section class="bloc">
    <h2>Options</h2>
    <?= afficher_tableau_options($donnees['options']); ?>
</section>

<section class="bloc">
    <h2>Cours</h2>
    <?= afficher_tableau_cours($donnees['cours']); ?>
</section>

        </main>
        <footer class="footer">
            SGA charge les donnees JSON, exporte aussi les configurations en CSV, sauvegarde le planning en JSON et TXT, puis permet l analyse des conflits et de l occupation des salles.
        </footer>
    </div>
</body>
</html>
