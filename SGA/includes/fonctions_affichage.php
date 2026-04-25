<?php

declare(strict_types=1);

require_once __DIR__ . '/fonctions_chargement.php';
require_once __DIR__ . '/fonctions_contraintes.php';
require_once __DIR__ . '/fonctions_planning.php';

function e(mixed $valeur): string
{
    return htmlspecialchars(texte_depuis_valeur($valeur), ENT_QUOTES, 'UTF-8');
}

function afficher_alertes(array $messages, string $type): string
{
    if ($messages === []) {
        return '';
    }

    $html = sprintf('<div class="alerte alerte-%s"><ul>', e($type));

    foreach (liste_tableau($messages) as $message) {
        $contenu = texte_depuis_valeur($message, 'Message indisponible');
        if ($contenu === '') {
            continue;
        }

        $html .= sprintf('<li>%s</li>', e($contenu));
    }

    $html .= '</ul></div>';

    return $html;
}

function afficher_cartes_resume(array $cartes): string
{
    $html = '<section class="resume-grid">';

    foreach (liste_tableau($cartes) as $carte) {
        if (!is_array($carte)) {
            continue;
        }

        $html .= '<article class="resume-card">';
        $html .= sprintf('<span class="resume-label">%s</span>', e(texte_tableau($carte, 'label')));
        $html .= sprintf('<strong class="resume-value">%s</strong>', e(texte_depuis_valeur(valeur_tableau($carte, 'valeur', '0'), '0')));
        $html .= sprintf('<p class="resume-note">%s</p>', e(texte_tableau($carte, 'note')));
        $html .= '</article>';
    }

    $html .= '</section>';

    return $html;
}

function badge_type_cours(mixed $type): string
{
    $typeNormalise = texte_depuis_valeur($type);

    if ($typeNormalise === 'option') {
        return '<span class="badge badge-option">Option</span>';
    }

    if ($typeNormalise === 'tronc_commun') {
        return '<span class="badge badge-tronc">Tronc commun</span>';
    }

    return '<span class="badge badge-inconnu">Type inconnu</span>';
}

function afficher_tableau_salles(array $salles): string
{
    $html = '<div class="table-wrapper"><table><thead><tr><th>ID</th><th>Salle</th><th>Capacite</th></tr></thead><tbody>';

    foreach (liste_tableau($salles) as $salle) {
        if (!is_array($salle)) {
            continue;
        }

        $html .= '<tr>';
        $html .= sprintf('<td>%s</td>', e(texte_tableau($salle, 'id')));
        $html .= sprintf('<td>%s</td>', e(texte_tableau($salle, 'designation')));
        $html .= sprintf('<td>%s places</td>', e(entier_tableau($salle, 'capacite')));
        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';

    return $html;
}

function afficher_tableau_promotions(array $promotions): string
{
    $html = '<div class="table-wrapper"><table><thead><tr><th>ID</th><th>Libelle</th><th>Effectif</th><th>Sous-groupes</th></tr></thead><tbody>';

    foreach (liste_tableau($promotions) as $promotion) {
        if (!is_array($promotion)) {
            continue;
        }

        $sousGroupes = [];
        foreach (liste_tableau(valeur_tableau($promotion, 'sous_groupes', [])) as $sousGroupe) {
            if (!is_array($sousGroupe)) {
                continue;
            }

            $sousGroupes[] = sprintf(
                '%s (%d)',
                texte_tableau($sousGroupe, 'libelle'),
                entier_tableau($sousGroupe, 'effectif')
            );
        }

        $html .= '<tr>';
        $html .= sprintf('<td>%s</td>', e(texte_tableau($promotion, 'id')));
        $html .= sprintf('<td>%s</td>', e(texte_tableau($promotion, 'libelle')));
        $html .= sprintf('<td>%s etudiants</td>', e(entier_tableau($promotion, 'effectif_total')));
        $html .= sprintf('<td>%s</td>', e($sousGroupes !== [] ? implode(', ', $sousGroupes) : 'Aucun'));
        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';

    return $html;
}

function afficher_tableau_options(array $options): string
{
    $html = '<div class="table-wrapper"><table><thead><tr><th>ID</th><th>Option</th><th>Promotion</th><th>Effectif</th></tr></thead><tbody>';

    foreach (liste_tableau($options) as $option) {
        if (!is_array($option)) {
            continue;
        }

        $html .= '<tr>';
        $html .= sprintf('<td>%s</td>', e(texte_tableau($option, 'id')));
        $html .= sprintf('<td>%s</td>', e(texte_tableau($option, 'libelle')));
        $html .= sprintf('<td>%s</td>', e(texte_tableau($option, 'promotion_parente')));
        $html .= sprintf('<td>%s etudiants</td>', e(entier_tableau($option, 'effectif')));
        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';

    return $html;
}

function afficher_tableau_cours(array $cours): string
{
    $html = '<div class="table-wrapper"><table><thead><tr><th>ID</th><th>Cours</th><th>Type</th><th>Volume</th><th>Rattachement</th></tr></thead><tbody>';

    foreach (liste_tableau($cours) as $ligneCours) {
        if (!is_array($ligneCours)) {
            continue;
        }

        $typeCours = texte_tableau($ligneCours, 'type');
        $rattachement = $typeCours === 'option'
            ? 'Option ' . texte_tableau($ligneCours, 'option_id')
            : 'Promotion ' . texte_tableau($ligneCours, 'promotion_id');

        $html .= '<tr>';
        $html .= sprintf('<td>%s</td>', e(texte_tableau($ligneCours, 'id')));
        $html .= sprintf('<td>%s</td>', e(texte_tableau($ligneCours, 'intitule')));
        $html .= sprintf('<td>%s</td>', badge_type_cours($typeCours));
        $html .= sprintf('<td>%s h / semaine</td>', e(entier_tableau($ligneCours, 'volume_horaire')));
        $html .= sprintf('<td>%s</td>', e($rattachement));
        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';

    return $html;
}

function afficher_tableau_planning(array $planning): string
{
    if ($planning === []) {
        return '<div class="etat-vide">Aucun planning genere pour le moment.</div>';
    }

    $html = '<div class="table-wrapper"><table><thead><tr><th>Jour</th><th>Creneau</th><th>Salle</th><th>Cours</th><th>Groupe</th><th>Type</th><th>Effectif</th></tr></thead><tbody>';

    foreach (liste_tableau($planning) as $seance) {
        if (!is_array($seance)) {
            continue;
        }

        $seanceLabel = sprintf(
            '%d/%d',
            entier_tableau($seance, 'seance', 1),
            entier_tableau($seance, 'nombre_seances', 1)
        );

        $html .= '<tr>';
        $html .= sprintf('<td>%s</td>', e(texte_tableau($seance, 'jour')));
        $html .= sprintf('<td>%s<br><span class="muted">Seance %s</span></td>', e(texte_tableau($seance, 'creneau')), e($seanceLabel));
        $html .= sprintf('<td>%s<br><span class="muted">%s places</span></td>', e(texte_tableau($seance, 'salle')), e(entier_tableau($seance, 'capacite_salle')));
        $html .= sprintf('<td>%s</td>', e(texte_tableau($seance, 'cours')));
        $html .= sprintf('<td>%s</td>', e(texte_tableau($seance, 'groupe')));
        $html .= sprintf('<td>%s</td>', badge_type_cours(valeur_tableau($seance, 'type_cours')));
        $html .= sprintf('<td>%s</td>', e(entier_tableau($seance, 'effectif_groupe')));
        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';

    return $html;
}

function afficher_grille_hebdomadaire(array $planning): string
{
    if ($planning === []) {
        return '<div class="etat-vide">Le tableau hebdomadaire sera affiche ici apres la generation ou le rechargement du planning.</div>';
    }

    $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];
    $slots = [
        ['heure_debut' => '08:00', 'heure_fin' => '12:00', 'libelle' => '08:00 - 12:00'],
        ['heure_debut' => '13:00', 'heure_fin' => '17:00', 'libelle' => '13:00 - 17:00'],
    ];
    $index = [];

    foreach ($planning as $seance) {
        if (!is_array($seance)) {
            continue;
        }

        $cle = implode('|', [
            texte_tableau($seance, 'jour'),
            texte_tableau($seance, 'heure_debut'),
            texte_tableau($seance, 'heure_fin'),
        ]);

        $index[$cle][] = $seance;
    }

    $html = '<div class="table-wrapper"><table class="planning-grid"><thead><tr><th>Creneau</th>';

    foreach ($jours as $jour) {
        $html .= sprintf('<th>%s</th>', e($jour));
    }

    $html .= '</tr></thead><tbody>';

    foreach ($slots as $slot) {
        $html .= sprintf('<tr><th class="slot-heading">%s</th>', e($slot['libelle']));

        foreach ($jours as $jour) {
            $cle = implode('|', [$jour, $slot['heure_debut'], $slot['heure_fin']]);
            $elements = liste_tableau($index[$cle] ?? []);

            usort(
                $elements,
                static fn (array $a, array $b): int => strcmp(texte_tableau($a, 'salle'), texte_tableau($b, 'salle'))
            );

            $html .= '<td class="planning-cell">';

            if ($elements === []) {
                $html .= '<span class="cell-empty">Libre</span>';
            }

            foreach ($elements as $element) {
                $html .= '<article class="slot-item">';
                $html .= sprintf('<strong>%s</strong>', e(texte_tableau($element, 'cours')));
                $html .= sprintf('<span>%s</span>', e(texte_tableau($element, 'groupe')));
                $html .= sprintf('<span class="muted">%s</span>', e(texte_tableau($element, 'salle')));
                $html .= '</article>';
            }

            $html .= '</td>';
        }

        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';

    return $html;
}

function afficher_contraintes(): string
{
    $regles = [
        'Semaine pedagogique du lundi au vendredi.',
        'Deux creneaux de 4h par jour : 08:00-12:00 et 13:00-17:00.',
        'Pause definie de 12:00 a 13:00.',
        'Une salle ne peut jamais accueillir deux groupes sur le meme creneau.',
        'Un groupe ne peut suivre qu un seul cours sur un meme creneau.',
        'Les cours de tronc commun ciblent une promotion complete.',
        'Les cours d option ciblent un sous-groupe L3 ou L4.',
    ];

    $html = '<section class="bloc-regles"><h2>Contraintes prises en compte</h2><ul class="regles-liste">';

    foreach ($regles as $regle) {
        $html .= sprintf('<li>%s</li>', e($regle));
    }

    $html .= '</ul></section>';

    return $html;
}

function afficher_strategie_generation(array $etapes): string
{
    $html = '<section class="bloc"><h2>Strategie d affectation retenue</h2><ol class="strategie-liste">';

    foreach (liste_tableau($etapes) as $etape) {
        $html .= sprintf('<li>%s</li>', e($etape));
    }

    $html .= '</ol></section>';

    return $html;
}

function afficher_tableau_conflits(array $conflits): string
{
    if ($conflits === []) {
        return '<div class="etat-vide">Aucun conflit detecte dans le planning texte analyse.</div>';
    }

    $html = '<div class="table-wrapper"><table><thead><tr><th>#</th><th>Conflit detecte</th></tr></thead><tbody>';

    foreach ($conflits as $index => $conflit) {
        $html .= '<tr>';
        $html .= sprintf('<td>%s</td>', e($index + 1));
        $html .= sprintf('<td>%s</td>', e($conflit));
        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';

    return $html;
}

function afficher_tableau_rapport_occupation(array $rapport): string
{
    if ($rapport === []) {
        return '<div class="etat-vide">Aucun rapport d occupation n a encore ete calcule.</div>';
    }

    $html = '<div class="table-wrapper"><table><thead><tr><th>Salle</th><th>Creneaux occupes</th><th>Creneaux libres</th><th>Taux d occupation</th></tr></thead><tbody>';

    foreach ($rapport as $ligne) {
        if (!is_array($ligne)) {
            continue;
        }

        $html .= '<tr>';
        $html .= sprintf('<td>%s<br><span class="muted">%s</span></td>', e(texte_tableau($ligne, 'designation')), e(texte_tableau($ligne, 'salle_id')));
        $html .= sprintf('<td>%s</td>', e(entier_tableau($ligne, 'creneaux_occupes')));
        $html .= sprintf('<td>%s</td>', e(entier_tableau($ligne, 'creneaux_libres')));
        $html .= sprintf('<td>%s %%</td>', e(number_format((float) valeur_tableau($ligne, 'taux_occupation', 0.0), 2, '.', '')));
        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';

    return $html;
}

function afficher_fichier_texte(string $contenu): string
{
    if (trim($contenu) === '') {
        return '<div class="etat-vide">Le fichier texte n est pas encore disponible.</div>';
    }

    return sprintf('<pre class="texte-brut">%s</pre>', e($contenu));
}

function afficher_formulaire_mise_a_jour_planning(array $planning, array $salles): string
{
    if ($planning === []) {
        return '<div class="etat-vide">Le planning doit d abord etre genere avant une modification manuelle.</div>';
    }

    $optionsSeances = [];
    foreach ($planning as $seance) {
        if (!is_array($seance)) {
            continue;
        }

        $valeur = texte_tableau($seance, 'cours_id') . '::' . entier_tableau($seance, 'seance', 1);
        $libelle = sprintf(
            '%s | seance %d/%d | %s | %s | %s',
            texte_tableau($seance, 'cours'),
            entier_tableau($seance, 'seance', 1),
            entier_tableau($seance, 'nombre_seances', 1),
            texte_tableau($seance, 'groupe'),
            texte_tableau($seance, 'jour'),
            texte_tableau($seance, 'salle')
        );
        $optionsSeances[] = sprintf('<option value="%s">%s</option>', e($valeur), e($libelle));
    }

    $optionsSalles = ['<option value="">Conserver la salle actuelle</option>'];
    foreach ($salles as $salle) {
        if (!is_array($salle)) {
            continue;
        }

        $optionsSalles[] = sprintf(
            '<option value="%s">%s (%s places)</option>',
            e(texte_tableau($salle, 'id')),
            e(texte_tableau($salle, 'designation')),
            e(entier_tableau($salle, 'capacite'))
        );
    }

    $optionsCreneaux = ['<option value="">Conserver le creneau actuel</option>'];
    foreach (creneaux_pedagogiques() as $creneau) {
        if (!is_array($creneau)) {
            continue;
        }

        $optionsCreneaux[] = sprintf(
            '<option value="%s">%s</option>',
            e(texte_tableau($creneau, 'id')),
            e(texte_tableau($creneau, 'libelle'))
        );
    }

    return '
        <form method="post" class="config-form">
            <input type="hidden" name="action" value="modifier_planning">
            <div class="field-grid">
                <label class="field">
                    <span>Seance a modifier</span>
                    <select name="session_cible" required>' . implode('', $optionsSeances) . '</select>
                </label>
                <label class="field">
                    <span>Nouveau creneau</span>
                    <select name="nouveau_creneau_id">' . implode('', $optionsCreneaux) . '</select>
                </label>
                <label class="field">
                    <span>Nouvelle salle</span>
                    <select name="nouvelle_salle_id">' . implode('', $optionsSalles) . '</select>
                </label>
            </div>
            <p class="help-text">Tu peux changer la salle, le creneau, ou les deux. La verification des contraintes est faite avant l ecriture.</p>
            <button type="submit">Mettre a jour le planning</button>
        </form>
    ';
}

function afficher_formulaires_configuration(): string
{
    return '
        <section class="bloc">
            <h2>Saisie et modification des donnees</h2>
            <div class="forms-grid">
                <form method="post" class="config-form">
                    <input type="hidden" name="action" value="enregistrer_salle">
                    <h3>Salle</h3>
                    <div class="field-grid">
                        <label class="field"><span>ID</span><input type="text" name="salle_id" required></label>
                        <label class="field"><span>Designation</span><input type="text" name="salle_designation" required></label>
                        <label class="field"><span>Capacite</span><input type="number" name="salle_capacite" min="1" required></label>
                    </div>
                    <button type="submit">Enregistrer la salle</button>
                </form>

                <form method="post" class="config-form">
                    <input type="hidden" name="action" value="enregistrer_promotion">
                    <h3>Promotion</h3>
                    <div class="field-grid">
                        <label class="field"><span>ID</span><input type="text" name="promotion_id" required></label>
                        <label class="field"><span>Libelle</span><input type="text" name="promotion_libelle" required></label>
                        <label class="field"><span>Effectif total</span><input type="number" name="promotion_effectif_total" min="1" required></label>
                        <label class="field field-full"><span>Sous-groupes</span><input type="text" name="promotion_sous_groupes" placeholder="L1-A:Groupe A:60, L1-B:Groupe B:60"></label>
                    </div>
                    <button type="submit">Enregistrer la promotion</button>
                </form>

                <form method="post" class="config-form">
                    <input type="hidden" name="action" value="enregistrer_option">
                    <h3>Option</h3>
                    <div class="field-grid">
                        <label class="field"><span>ID</span><input type="text" name="option_id" required></label>
                        <label class="field"><span>Libelle</span><input type="text" name="option_libelle" required></label>
                        <label class="field"><span>Promotion parente</span><input type="text" name="option_promotion_parente" placeholder="L3 ou L4" required></label>
                        <label class="field"><span>Effectif</span><input type="number" name="option_effectif" min="1" required></label>
                    </div>
                    <button type="submit">Enregistrer l option</button>
                </form>

                <form method="post" class="config-form">
                    <input type="hidden" name="action" value="enregistrer_cours">
                    <h3>Cours</h3>
                    <div class="field-grid">
                        <label class="field"><span>ID</span><input type="text" name="cours_id" required></label>
                        <label class="field"><span>Intitule</span><input type="text" name="cours_intitule" required></label>
                        <label class="field"><span>Volume horaire</span><input type="number" name="cours_volume_horaire" min="1" required></label>
                        <label class="field"><span>Type</span>
                            <select name="cours_type" required>
                                <option value="tronc_commun">Tronc commun</option>
                                <option value="option">Option</option>
                            </select>
                        </label>
                        <label class="field"><span>Promotion cible</span><input type="text" name="cours_promotion_id" placeholder="Obligatoire pour tronc commun"></label>
                        <label class="field"><span>Option cible</span><input type="text" name="cours_option_id" placeholder="Obligatoire pour option"></label>
                    </div>
                    <button type="submit">Enregistrer le cours</button>
                </form>
            </div>
        </section>
    ';
}
