# Personnages : sélection, aide et catalogue de références

5 novembre 2026. Correctif demandé par le propriétaire après son essai du Studio. Livraison technique locale ; le grand R7 et la validation audiovisuelle restent ouverts.

## Parcours livré

- La bibliothèque et les présences par plan apparaissent seulement avec **Garder un personnage de référence**, dans Studio et les réglages de série. Les autres points de départ les masquent. Une sélection déjà saisie reste récupérable, avec une action explicite pour la retirer.
- **Ajouter un personnage** précède la liste ; bibliothèque vide : **Créer mon premier personnage**. Le formulaire rapide ouvre un autre onglet pour conserver le brouillon. Nom et portrait sont visibles ; description et vues sont facultatives. Retour à la préparation : actualisation automatique au focus, ou bouton Actualiser la liste. Aucune génération image implicite.
- Cases cochables lisibles, compteur et Tout retirer. Retirer une fiche de la sélection commune retire seulement sa présence des plans concernés ; la recocher ne l’ajoute pas automatiquement partout. Une présence reste modifiable par plan.
- L’ordre historique des références est conservé au rendu et à la soumission : remplacer un select multiple par des cases ne doit pas échanger Reference1/Reference2 ni modifier un ancien devis. Les listes explicitement vides sont enregistrées comme telles.
- Les nouveaux parcours utilisent les fiches enregistrées. Les anciennes images isolées restent récupérables dans les préparations qui en contiennent. Pour les endpoints photo seulement, le portrait principal est envoyé ; les vues supplémentaires de la fiche sont conservées mais pas envoyées comme des personnages supplémentaires.
- L’aide **?**, à côté de Point de départ, explique texte/image/personnage : survol, clic/tactile et clavier. Le long paragraphe commun a été retiré. La limite sur les dialogues figure dans la courte note « Relis le scénario avant de générer ».
- Les échecs de proposition de scénario, validations et propositions à corriger sont rouges et annoncés ; un nouvel essai et un résultat positif rétablissent le statut normal.

## Modèles et prix

La recherche publique Fal `reference-to-video` a renvoyé 75 entrées. Les schémas ont été triés : entraînement, adaptation nécessitant une vidéo, anciennes variantes et modèles sans entrée photo exploitable ne sont pas des choix interchangeables de portrait. Les endpoints Veo 3.1 et LTX Ingredient ont aussi été consultés. **29 familles / 68 variantes de résolution ajoutées**, en plus de Vidu Q1 : **30 familles / 69 choix de référence photographique chez Fal**. Ce décompte est un état du catalogue inspecté, pas une promesse d’exhaustivité permanente sur tous les fournisseurs.

Les contrats officiels d’entrée et les tarifs spécifiques aux endpoints ont été lus le 5 novembre. Le connecteur n’a servi qu’à la découverte et à la lecture de schémas publics ; aucune inférence, aucun Playground, aucune lecture de clé, solde ou historique. Les anciens identifiants n’ont pas été réaffectés à un autre endpoint.

Les corps préparés respectent les types/durées/champs propres à chaque endpoint : listes ou image unique, objets PixVerse, marqueurs des portraits dans le prompt, durées entières/chaînes/images et formats permis. Les plafonds applicatifs peuvent être plus faibles que ceux du fournisseur. Le français, les voix, la ressemblance et les proportions **n’ont pas été validés par une nouvelle vidéo réelle**.

### Tarification étudiée

- **Seedance 2.5 US** : 480/720p, 0,0214 USD pour 1 000 tokens ; 1080p, 0,0234 USD. Formule publique : largeur × hauteur × 24 × secondes / 1024, avec les secondes vidéo d’entrée ajoutées le cas échéant. Ici, références photo seulement : aucune remise réservée aux vidéos d’entrée n’est appliquée. Les équivalents indicatifs utilisés sont 0,2205 / 0,473 / 1,164 USD/s. Pour 35 s : environ **7,72 / 16,56 / 40,74 USD**, avant taxes/conversion, et selon découpage compatible. Le débit réel dépend des dimensions/images/tokens retenus par le fournisseur : ce n’est pas un plafond de facturation.
- Les valeurs 480p de **Seedance 2.0 Fast et Mini** sont des approximations calculées ; les descriptions les signalent comme estimations aux tokens. Les variantes 720p utilisent les équivalents publiés. Le détail des hypothèses est conservé dans le manifeste.
- La page vivante Seedance US Image confirme 0,0214/1 000 tokens et l’équivalent 0,473 USD/s en 720p. Le taux existant n’a donc pas été changé ; sa date de vérification a été actualisée. L’ancien résultat de recherche à 0,02568 ne prime pas sur la page actuelle. Aucun devis de l’histoire Milo n’a été confirmé ni lancé.
- **Grok 1.5** : ajout du coût par référence au devis de chaque plan et au contrôle du coût avant envoi. Le comparateur utilise le nombre maximal de références comme borne conservatrice ; le devis utilise les références réelles.
- **LTX Ingredient** : facturation au mégapixel arrondi supérieur ; dimensions explicites d’aire constante, 24 images/s + 1. Tableau des prix par durée, pas un faux tarif linéaire. Une seule fiche/photo ou planche de référence par appel.
- **MiniMax H3 Max/Max Turbo** : quatre portraits maximum, normalisés carrés pour rester dans l’allocation de tokens image documentée. Les originaux privés et leurs snapshots restent conservés. H3 classique limité à cinq pour éviter les frais par image supplémentaires.
- **HeyGen** : tarif normal, promotion terminée le 31 octobre exclue. **Vidu Q2 Pro** : forfaits par demande respectés ; résolutions au tarif ambigu exclues. Les autres frais fixes et variantes audio sont explicités dans le catalogue.
- **MiniMax Video-01** est un ancien modèle de portrait humain, une seule image et une durée fixe ; pas de validation de personnages animaliers. Le cadrage/durée proviennent aussi de sa documentation générale Video-01, distinction conservée dans le manifeste.

Les sources, configurations et hypothèses sont dans [le manifeste](fixtures/personnages-ergonomie-modeles-20261105.json). Les documents publics bruts téléchargés restent dans les artefacts locaux ignorés ; seules leurs empreintes et des données contractuelles réduites sont conservées dans le dépôt.

## Vérifications et limites

Les tests PHP utilisent des images synthétiques et des clients HTTP interdits ou simulés. Les tests fonctionnels de fiches annulent leurs transactions, comparent tous les compteurs de tables avant/après et retirent leurs fichiers synthétiques. La création rapide est testée par un vrai formulaire Symfony ; les contrôles privés excluent les autres propriétaires et les fiches archivées.

Six GET natifs du profil local existant ont été capturés dans un kernel/cache/session de banc isolés : Studio vierge, préparations 21/22, choix privés, création rapide et nouvelle série. Les 27 compteurs sont identiques, aucune tentative HTTP fournisseur. Aucun de ces brouillons n’a été modifié.

La recette navigateur rejoue ces HTML dans sa propre session Playwright. Les mutations et les appels externes sont bloqués ; l’actualisation d’une nouvelle fiche est synthétique côté navigateur, complétée par le test serveur de création réelle avec rollback. Pour permettre la navigation du banc, les copies de relecture retirent seulement le contrôleur d’alerte de sortie `unsaved`, conservé dans les HTML originaux et dans l’application. Les médias privés affichés proviennent des fichiers locaux correspondant aux captures autorisées.

Sélection/retrait/retrait total, ordre des plans, masquage texte/image, retour au mode personnage, ajout dans un autre onglet et actualisation sans perte, aide et erreur rouge vérifiés. Douze vues à 320/390/768/1280 et deux sans JavaScript à 390/1280 : aucun débordement, aucune image chargée cassée, aucune erreur JavaScript observée. Sans JavaScript : cases natives et aide au clavier vérifiées ; masquage dynamique et actualisation nécessitent JavaScript.

Les résultats finaux des tests et les empreintes sont consignés dans le manifeste. Pas de nouvelle suite PHP globale ni de revue indépendante ; relecture locale effectuée. Pas de migration, activation de drapeau, coût nouveau, upload fournisseur, email, publication, push ou déploiement. Le cache web local est actualisé pour rendre les nouveaux services/routes disponibles. Le seul worker `background-worker` est actualisé après contrôle de la file vide et de l’absence de génération/assemblage actif ; aucun autre worker n’est redémarré. Le propriétaire recharge son onglet après conservation de sa saisie.

Résultats finaux : **205 tests PHP ciblés / 7 284 assertions**, **197 tests frontend**, vérification syntaxique et build JS, **128 Twig**, conteneur Symfony, PHPStan et style verts. Les 16 scénarios de fiches ont chacun leurs 27 compteurs identiques après rollback et zéro HTTP fournisseur. L’ajout du premier personnage à une bibliothèque vide, ses présences sans rechargement, le changement de mode dans les séries et l’aide tactile passent aussi la recette navigateur.

Une reprise du lot ciblé lancée en même temps que les anciennes régressions a produit six différences de compteurs dans la base de test partagée : ces anciennes classes créent et nettoient leurs données hors des transactions du nouveau banc. Ce passage n’est pas présenté comme réussi. Les lots sont repris séparément pour le résultat final ; aucun défaut applicatif n’est déduit de ce conflit de bancs. Lors de la relecture, le masquage du sélecteur de présence d’une bibliothèque initialement vide a aussi été corrigé : après création/actualisation du premier personnage, ses présences sont accessibles sans recharger le brouillon.

Un test de persistance enregistre toutes les nouvelles variantes dans la base de test puis annule la transaction. Il protège notamment la limite de 32 caractères des identifiants de générations, respectée sans migration. Le passage élargi a exécuté **105 tests / 1 156 assertions avec deux échecs d’export synthétique**. Ces deux scénarios passent ensuite seuls : **2 tests / 90 assertions**. Ce rejeu n’est pas une nouvelle exécution verte des 105 tests ; le premier résultat reste signalé dans le manifeste. Aucune modification du moteur de rendu ni assouplissement de ses assertions.

## Repères de code et flux Symfony

Le formulaire Symfony reste l’autorité : `AiCharacterPoolType` et `AiShotType` utilisent `AiCharacterChoicesType`, qui conserve l’ordre des références. Twig rend des cases natives ; `character_picker_controller.js` gère l’affichage, le retrait et l’actualisation privée sans remplacer les textes des plans. Le contrôleur `AiCharacterController` enregistre la fiche et fournit uniquement les choix appartenant à l’utilisateur connecté.

Le catalogue `CharacterVideoModels` décrit les capacités et les prix. `AiPlanInputs` résout les portraits privés de chaque plan, `AiShotPrompts` prépare leurs correspondances, puis `AiEpisodeFlow` chiffre et fige le devis. Après une confirmation distincte seulement, `FalVideoApi` transforme ce contrat en paramètres propres au fournisseur. C’est la séparation à retenir en Symfony : formulaire/validation → service métier/devis → adaptateur fournisseur ; le JavaScript aide la saisie, sans autoriser ni facturer une demande.

Pour vérifier manuellement : choisir le mode personnage, créer/importer une fiche, revenir et la cocher, préparer deux plans avec des présences différentes, retirer/recocher puis passer à texte et revenir. Ouvrir l’aide, provoquer une proposition sans idée et vérifier le message rouge. Relire le récapitulatif sans confirmer suffit pour contrôler le parcours sans lancer de vidéo.

Prochaine étape : le propriétaire peut préparer lui-même un court essai, choisir ses personnages et sa présence par plan, puis relire le devis. Toute nouvelle vidéo réelle nécessite sa confirmation distincte. Aucun accord précédent n’est réutilisé.
