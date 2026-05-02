SGA - Systeme de gestion automatique du planning

1. Structure du projet

SGA/
|- index.php
|- README.txt
|- config/
|  |- auth.config.example.php (modele pour auth.local.php)
|- scripts/
|  `- bootstrap_auth_local.php (genere auth.local.php + secret TOTP)
|- auth/
|  |- session.php (demarrage session + fonctions_auth)
|  |- login.php (formulaire connexion et TOTP)
|  |- login_view.php (HTML)
|  `- logout.php (fin de session)
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
|  |- fonctions_auth.php (session et garde applicative)
|  |- fonctions_totp.php (verification TOTP)
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

6. Authentification et double facteur (TOTP)

Fichier par defaut inclus : config/auth.local.php avec identifiant admin et mot de passe demo2026
(sans double authentification tant que totp_secret reste vide). A remplacer en environnement reel.

Sinon deux modes de configuration :

A) Generation automatique (recommande)
   Depuis le dossier SGA :
   php scripts/bootstrap_auth_local.php "MotDePasseFort"
   Puis importer l URI otpauth affiche dans une application TOTP ou saisir le secret Base32.

B) Manuel
   Copier config/auth.config.example.php vers config/auth.local.php
   Definir password_hash avec PHP :
   php -r "echo password_hash('TonMotDePasse', PASSWORD_DEFAULT), PHP_EOL;"
   Definir totp_secret avec un secret Base32 (voir script ci-dessus) ou chaine vide pour desactiver la 2FA.

Session PHP : cookie httponly, samesite Lax ; deconnexion via le bandeau en haut de l interface.

7. Remarques

- Les exemples fournis utilisent 8 salles, 4 promotions, 6 options et 14 cours
- Les volumes horaires non multiples de 4h sont acceptes mais occupent un dernier creneau partiel
- Les donnees peuvent etre adaptees directement dans les fichiers JSON sans modifier le code
