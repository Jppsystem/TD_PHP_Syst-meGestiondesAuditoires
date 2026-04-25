SGA - Systeme de gestion automatique du planning

1. Structure du projet

SGA/
|- index.php
|- README.txt
|- data/
|  |- salles.json
|  |- promotions.json
|  |- cours.json
|  |- options.json
|  `- planning.json
|- includes/
|  |- fonctions_chargement.php
|  |- fonctions_contraintes.php
|  |- fonctions_planning.php
|  |- fonctions_affichage.php
|  `- fonctions_sauvegarde.php
|- assets/
|  `- style.css
`- templates/
   `- header.php

2. Format des fichiers JSON

salles.json
- id : identifiant unique de la salle
- designation : nom de la salle
- capacite : nombre total de places

promotions.json
- id : identifiant de promotion
- libelle : nom de la promotion
- effectif_total : nombre total d etudiants
- sous_groupes : liste optionnelle de sous-groupes

options.json
- id : identifiant de l option
- libelle : nom de l option
- promotion_parente : promotion source (L3 ou L4)
- effectif : effectif du sous-groupe

cours.json
- id : identifiant du cours
- intitule : nom du cours
- volume_horaire : volume horaire hebdomadaire
- type : tronc_commun ou option
- promotion_id : promotion cible pour un cours tronc commun
- option_id : option cible pour un cours d option

planning.json
- fichier genere automatiquement
- chaque entree contient : jour, creneau, salle, cours, groupe, effectif et type

3. Contraintes appliquees

- Semaine pedagogique du lundi au vendredi
- Horaires de 08:00 a 17:00
- Deux creneaux de 4h par jour : 08:00-12:00 et 13:00-17:00
- Pause de 12:00 a 13:00
- Une salle ne peut pas accueillir deux groupes sur le meme creneau
- Un groupe ne peut suivre qu un seul cours sur le meme creneau
- Les cours de tronc commun concernent une promotion complete
- Les cours d option concernent uniquement des sous-groupes L3 et L4

4. Lancer l application

Depuis le dossier parent du projet :

php -S localhost:8000 -t SGA

Puis ouvrir :

http://localhost:8000

5. Fonctionnement

- index.php charge les donnees JSON
- les contraintes sont verifiees
- le planning est genere a la demande
- le resultat est sauvegarde dans data/planning.json

6. Remarques

- Les exemples fournis utilisent 8 salles, 4 promotions, 6 options et 14 cours
- Les volumes horaires non multiples de 4h sont acceptes mais occupent un dernier creneau partiel
- Les donnees peuvent etre adaptees directement dans les fichiers JSON sans modifier le code
