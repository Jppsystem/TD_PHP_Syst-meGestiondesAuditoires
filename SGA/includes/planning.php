<?php
/**
 * SGA — Logique métier : créneaux, génération du planning, conflits, occupation.
 */

require_once __DIR__ . '/functions.php';

define('SGA_PLANNING_FILE', 'planning.json');

/**
 * Définition des créneaux : Lundi–Vendredi, 4 blocs de 2h par jour.
 *
 * @return array Liste de créneaux avec id_numérique (0..19), jour_idx (0-4), slot_idx (0-3)
 */
function sga_planning_creneaux_definitions()
{
    $jours = array('Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi');
    $horaires = array(
        array('08:00', '10:00'),
        array('10:00', '12:00'),
        array('14:00', '16:00'),
        array('16:00', '18:00'),
    );
    $out = array();
    $id = 0;
    foreach ($jours as $ji => $jourNom) {
        foreach ($horaires as $si => $h) {
            $out[] = array(
                'id' => $id,
                'jour_idx' => $ji,
                'jour_nom' => $jourNom,
                'slot_idx' => $si,
                'debut' => $h[0],
                'fin' => $h[1],
                'label' => $jourNom . ' ' . $h[0] . '-' . $h[1],
            );
            $id++;
        }
    }
    return $out;
}

/**
 * Tableau indexé par id de créneau.
 *
 * @return array
 */
function sga_planning_creneaux_by_id()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = array();
    foreach (sga_planning_creneaux_definitions() as $c) {
        $cache[$c['id']] = $c;
    }
    return $cache;
}

/**
 * Retourne les ids de créneaux consécutifs sur la même journée pour une durée (1 ou 2).
 *
 * @param int $startId
 * @param int $duree 1 ou 2
 * @return int[]|null
 */
function sga_planning_creneau_sequence($startId, $duree)
{
    $defs = sga_planning_creneaux_by_id();
    if (!isset($defs[$startId])) {
        return null;
    }
    $duree = (int) $duree;
    if ($duree < 1) {
        $duree = 1;
    }
    if ($duree > 2) {
        $duree = 2;
    }
    $seq = array($startId);
    if ($duree === 1) {
        return $seq;
    }
    $ji = $defs[$startId]['jour_idx'];
    $si = $defs[$startId]['slot_idx'];
    $nextId = $startId + 1;
    if (!isset($defs[$nextId])) {
        return null;
    }
    if ($defs[$nextId]['jour_idx'] !== $ji || $defs[$nextId]['slot_idx'] !== $si + 1) {
        return null;
    }
    $seq[] = $nextId;
    return $seq;
}

/**
 * Index des promotions par niveau => effectif.
 *
 * @param array $promotions depuis JSON
 * @return array L1 => int, ...
 */
function sga_planning_effectifs_par_niveau($promotions)
{
    $map = array('L1' => 0, 'L2' => 0, 'L3' => 0, 'L4' => 0);
    foreach ($promotions as $p) {
        if (empty($p['niveau'])) {
            continue;
        }
        $n = $p['niveau'];
        if (isset($map[$n]) && isset($p['effectif'])) {
            $map[$n] = (int) $p['effectif'];
        }
    }
    return $map;
}

/**
 * Capacité requise pour un cours selon sa promotion.
 *
 * @param array $cours
 * @param array $effectifs
 * @return int
 */
function sga_planning_capacite_requise($cours, $effectifs)
{
    $n = isset($cours['promotion_niveau']) ? $cours['promotion_niveau'] : 'L1';
    return isset($effectifs[$n]) ? (int) $effectifs[$n] : 0;
}

/**
 * Liste des (start_creneau_id) possibles pour une durée.
 *
 * @param int $duree
 * @return int[]
 */
function sga_planning_valid_starts($duree)
{
    $defs = sga_planning_creneaux_definitions();
    $starts = array();
    foreach ($defs as $c) {
        if (sga_planning_creneau_sequence($c['id'], $duree) !== null) {
            $starts[] = $c['id'];
        }
    }
    return $starts;
}

/**
 * Génère le planning (greedy avec tri par effectif décroissant).
 *
 * @return array 'success' => bool, 'planning' => array, 'message' => string, 'unplaced' => array
 */
function sga_planning_generer()
{
    $salles = sga_json_read('salles.json');
    $promotions = sga_json_read('promotions.json');
    $cours = sga_json_read('cours.json');

    $effectifs = sga_planning_effectifs_par_niveau($promotions);
    $creneauxParId = sga_planning_creneaux_by_id();

    // Préparer les cours à placer avec métadonnées
    $items = array();
    foreach ($cours as $c) {
        if (empty($c['id'])) {
            continue;
        }
        $duree = isset($c['duree_creneaux']) ? (int) $c['duree_creneaux'] : 1;
        if ($duree < 1) {
            $duree = 1;
        }
        if ($duree > 2) {
            $duree = 2;
        }
        $cap = sga_planning_capacite_requise($c, $effectifs);
        $items[] = array(
            'cours' => $c,
            'duree' => $duree,
            'cap_requise' => $cap,
            'niveau' => isset($c['promotion_niveau']) ? $c['promotion_niveau'] : 'L1',
        );
    }

    usort($items, function ($a, $b) {
        return $b['cap_requise'] - $a['cap_requise'];
    });

    $validStartsCache = array();
    $roomSlotUsed = array(); // salle_id => creneau_id => true
    $promoSlotUsed = array(); // niveau => creneau_id => true
    $planning = array();
    $unplaced = array();

    foreach ($items as $item) {
        $c = $item['cours'];
        $duree = $item['duree'];
        $capReq = $item['cap_requise'];
        $niveau = $item['niveau'];

        if (!isset($validStartsCache[$duree])) {
            $validStartsCache[$duree] = sga_planning_valid_starts($duree);
        }
        $placed = false;

        foreach ($validStartsCache[$duree] as $startId) {
            $seq = sga_planning_creneau_sequence($startId, $duree);
            if ($seq === null) {
                continue;
            }
            // Salles triées par capacité croissante (meilleur ajustement)
            $sallesTri = $salles;
            usort($sallesTri, function ($s1, $s2) {
                $c1 = isset($s1['capacite']) ? (int) $s1['capacite'] : 0;
                $c2 = isset($s2['capacite']) ? (int) $s2['capacite'] : 0;
                return $c1 - $c2;
            });

            foreach ($sallesTri as $salle) {
                if (empty($salle['id'])) {
                    continue;
                }
                $capSalle = isset($salle['capacite']) ? (int) $salle['capacite'] : 0;
                if ($capSalle < $capReq) {
                    continue;
                }
                $sid = $salle['id'];
                $conflict = false;
                foreach ($seq as $cid) {
                    if (!empty($roomSlotUsed[$sid][$cid])) {
                        $conflict = true;
                        break;
                    }
                    if (!empty($promoSlotUsed[$niveau][$cid])) {
                        $conflict = true;
                        break;
                    }
                }
                if ($conflict) {
                    continue;
                }
                foreach ($seq as $cid) {
                    $roomSlotUsed[$sid][$cid] = true;
                    $promoSlotUsed[$niveau][$cid] = true;
                }
                $label0 = $creneauxParId[$seq[0]]['label'];
                if (count($seq) > 1) {
                    $label0 .= ' + ' . $creneauxParId[$seq[1]]['debut'] . '-' . $creneauxParId[$seq[1]]['fin'];
                }
                $planning[] = array(
                    'id' => sga_generate_id('p_'),
                    'cours_id' => $c['id'],
                    'cours_intitule' => isset($c['intitule']) ? $c['intitule'] : '',
                    'salle_id' => $sid,
                    'salle_nom' => isset($salle['nom']) ? $salle['nom'] : '',
                    'creneau_ids' => $seq,
                    'creneau_label' => $label0,
                    'promotion_niveau' => $niveau,
                    'duree_creneaux' => $duree,
                );
                $placed = true;
                break 2;
            }
        }

        if (!$placed) {
            $unplaced[] = array(
                'cours_id' => $c['id'],
                'intitule' => isset($c['intitule']) ? $c['intitule'] : '',
                'raison' => 'Aucune combinaison salle/créneau libre respectant la capacité et l\'absence de conflit de promotion.',
            );
        }
    }

    $success = count($unplaced) === 0;
    $msg = $success
        ? 'Planning généré : toutes les affectations sont réalisées.'
        : 'Planning partiel : ' . count($unplaced) . ' cours non placés.';

    return array(
        'success' => $success,
        'planning' => $planning,
        'message' => $msg,
        'unplaced' => $unplaced,
        'generated_at' => date('c'),
    );
}

/**
 * Enregistre le résultat de génération (meta + entrées).
 *
 * @param array $result retour de sga_planning_generer
 * @return bool
 */
function sga_planning_save($result)
{
    $payload = array(
        'version' => 1,
        'generated_at' => isset($result['generated_at']) ? $result['generated_at'] : date('c'),
        'success' => !empty($result['success']),
        'message' => isset($result['message']) ? $result['message'] : '',
        'unplaced' => isset($result['unplaced']) ? $result['unplaced'] : array(),
        'entries' => isset($result['planning']) ? $result['planning'] : array(),
    );
    return sga_json_write(SGA_PLANNING_FILE, $payload);
}

/**
 * Charge le planning sauvegardé.
 *
 * @return array
 */
function sga_planning_load()
{
    return sga_json_read(SGA_PLANNING_FILE);
}

/**
 * Détecte les conflits dans un ensemble d'entrées (salles / promotions sur même créneau).
 *
 * @param array $entries
 * @return array liste de messages de conflit
 */
function sga_planning_detect_conflicts($entries)
{
    $conflicts = array();
    if (!is_array($entries)) {
        return $conflicts;
    }
    $roomCreneau = array();
    $promoCreneau = array();

    foreach ($entries as $idx => $e) {
        $ids = isset($e['creneau_ids']) && is_array($e['creneau_ids'])
            ? $e['creneau_ids']
            : array();
        if (empty($ids) && isset($e['creneau_id'])) {
            $ids = array((int) $e['creneau_id']);
        }
        $salle = isset($e['salle_id']) ? $e['salle_id'] : '';
        $promo = isset($e['promotion_niveau']) ? $e['promotion_niveau'] : '';
        $label = isset($e['cours_intitule']) ? $e['cours_intitule'] : $idx;

        foreach ($ids as $cid) {
            $keyR = $salle . '|' . $cid;
            if (isset($roomCreneau[$keyR])) {
                $conflicts[] = "Salle occupée deux fois : créneau $cid — « {$roomCreneau[$keyR]} » et « {$label} ».";
            } else {
                $roomCreneau[$keyR] = $label;
            }
            $keyP = $promo . '|' . $cid;
            if ($promo !== '') {
                if (isset($promoCreneau[$keyP])) {
                    $conflicts[] = "Promotion {$promo} en double sur le même créneau ($cid) : « {$promoCreneau[$keyP]} » et « {$label} ».";
                } else {
                    $promoCreneau[$keyP] = $label;
                }
            }
        }
    }
    return $conflicts;
}

/**
 * Vérifie capacité salle vs effectif promotion pour chaque entrée.
 *
 * @param array $entries
 * @param array $salles par id
 * @param array $effectifs par niveau
 * @return array avertissements
 */
function sga_planning_check_capacities($entries, $salles, $effectifs)
{
    $warn = array();
    $salleById = array();
    foreach ($salles as $s) {
        if (!empty($s['id'])) {
            $salleById[$s['id']] = $s;
        }
    }
    foreach ($entries as $e) {
        $sid = isset($e['salle_id']) ? $e['salle_id'] : '';
        $niv = isset($e['promotion_niveau']) ? $e['promotion_niveau'] : 'L1';
        $need = isset($effectifs[$niv]) ? (int) $effectifs[$niv] : 0;
        $cap = 0;
        if ($sid !== '' && isset($salleById[$sid]['capacite'])) {
            $cap = (int) $salleById[$sid]['capacite'];
        }
        if ($cap < $need) {
            $nom = isset($e['cours_intitule']) ? $e['cours_intitule'] : '?';
            $warn[] = "Capacité insuffisante pour « {$nom} » : salle {$cap} places, promotion {$need} étudiants.";
        }
    }
    return $warn;
}

/**
 * Indexe les entrées par id de créneau (une entrée peut couvrir plusieurs créneaux).
 *
 * @param array $entries
 * @return array<int, array> creneau_id => liste d'entrées
 */
function sga_planning_map_by_creneau($entries)
{
    $map = array();
    if (!is_array($entries)) {
        return $map;
    }
    foreach ($entries as $e) {
        $ids = array();
        if (isset($e['creneau_ids']) && is_array($e['creneau_ids'])) {
            $ids = $e['creneau_ids'];
        }
        foreach ($ids as $cid) {
            $cid = (int) $cid;
            if (!isset($map[$cid])) {
                $map[$cid] = array();
            }
            $map[$cid][] = $e;
        }
    }
    return $map;
}

/**
 * Rapport d'occupation des salles en % (créneaux occupés / total créneaux * nb salles ou par salle).
 *
 * @param array $entries
 * @param array $salles
 * @return array statistiques
 */
function sga_planning_rapport_occupation($entries, $salles)
{
    $totalCreneaux = count(sga_planning_creneaux_definitions());
    $nbSalles = max(1, count($salles));
    $totalSlots = $totalCreneaux * $nbSalles;

    $occupied = array();
    foreach ($entries as $e) {
        $sid = isset($e['salle_id']) ? $e['salle_id'] : '';
        if ($sid === '') {
            continue;
        }
        $cids = isset($e['creneau_ids']) && is_array($e['creneau_ids'])
            ? $e['creneau_ids']
            : array();
        foreach ($cids as $cid) {
            $key = $sid . '_' . $cid;
            $occupied[$key] = true;
        }
    }
    $occupiedCount = count($occupied);
    $pct = $totalSlots > 0 ? round(100 * $occupiedCount / $totalSlots, 1) : 0;

    $bySalle = array();
    foreach ($salles as $s) {
        if (empty($s['id'])) {
            continue;
        }
        $sid = $s['id'];
        $occ = 0;
        for ($c = 0; $c < $totalCreneaux; $c++) {
            $k = $sid . '_' . $c;
            if (!empty($occupied[$k])) {
                $occ++;
            }
        }
        $bySalle[$sid] = array(
            'nom' => isset($s['nom']) ? $s['nom'] : $sid,
            'occupes' => $occ,
            'total_creneaux' => $totalCreneaux,
            'taux_pct' => $totalCreneaux > 0 ? round(100 * $occ / $totalCreneaux, 1) : 0,
        );
    }

    return array(
        'taux_global_pct' => $pct,
        'creneaux_occupes' => $occupiedCount,
        'creneaux_totaux' => $totalSlots,
        'par_salle' => $bySalle,
    );
}

/**
 * Met à jour une entrée du planning (modification manuelle).
 *
 * @param string $entryId
 * @param string $salleId
 * @param array  $creneau_ids liste d'ids (1 ou 2 éléments)
 * @return array 'error' => string ou '', 'entries' => nouvelles entrées complètes
 */
function sga_planning_update_entry($entryId, $salleId, $creneau_ids)
{
    $data = sga_planning_load();
    $entries = isset($data['entries']) && is_array($data['entries']) ? $data['entries'] : array();
    $salles = sga_json_read('salles.json');
    $promotions = sga_json_read('promotions.json');
    $effectifs = sga_planning_effectifs_par_niveau($promotions);

    $salleNom = '';
    foreach ($salles as $s) {
        if (isset($s['id']) && $s['id'] === $salleId) {
            $salleNom = isset($s['nom']) ? $s['nom'] : '';
            break;
        }
    }

    $creneauxParId = sga_planning_creneaux_by_id();
    $newIds = array();
    foreach ($creneau_ids as $cid) {
        $newIds[] = (int) $cid;
    }
    sort($newIds);
    $duree = count($newIds);

    if ($duree < 1 || $duree > 2) {
        return array('error' => 'Durée invalide (1 ou 2 créneaux).', 'entries' => $entries);
    }
    if ($duree === 2) {
        $seq = sga_planning_creneau_sequence($newIds[0], 2);
        if ($seq === null || $seq[0] !== $newIds[0] || $seq[1] !== $newIds[1]) {
            return array('error' => 'Les deux créneaux doivent être consécutifs le même jour.', 'entries' => $entries);
        }
    }

    $found = false;
    foreach ($entries as &$e) {
        if (isset($e['id']) && $e['id'] === $entryId) {
            $found = true;
            $e['salle_id'] = $salleId;
            $e['salle_nom'] = $salleNom;
            $e['creneau_ids'] = $duree === 1 ? array($newIds[0]) : array($newIds[0], $newIds[1]);
            $c0 = $creneauxParId[$newIds[0]];
            $label = $c0['label'];
            if ($duree === 2) {
                $c1 = $creneauxParId[$newIds[1]];
                $label .= ' + ' . $c1['debut'] . '-' . $c1['fin'];
            }
            $e['creneau_label'] = $label;
            break;
        }
    }
    unset($e);

    if (!$found) {
        return array('error' => 'Entrée introuvable.', 'entries' => $entries);
    }

    $conf = sga_planning_detect_conflicts($entries);
    if (count($conf) > 0) {
        return array('error' => implode(' ', $conf), 'entries' => $entries);
    }

    $capWarn = sga_planning_check_capacities($entries, $salles, $effectifs);
    if (count($capWarn) > 0) {
        return array('error' => $capWarn[0], 'entries' => $entries);
    }

    $data['entries'] = $entries;
    $data['last_manual_edit'] = date('c');
    sga_json_write(SGA_PLANNING_FILE, $data);

    return array('error' => '', 'entries' => $entries);
}
