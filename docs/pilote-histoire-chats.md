# Pilote supervisé — Le bouton interdit

Préparation du 26 octobre 2026. Le propriétaire délègue le pilote d’une histoire originale de deux chats. Le scénario, les trois visuels et une maquette muette de 30 secondes sont prêts ; le brouillon privé contient les trois plans dans VydFlow. Aucune vidéo fournisseur n’a été générée et aucun coût vidéo n’a été confirmé. La maquette locale est un assemblage d’images fixes, pas une preuve des mouvements, des voix ou de la synchronisation d’un modèle vidéo.

## But et décision sur l’interface

Évaluer une histoire verticale de 30 secondes avec deux personnages reconnaissables et des dialogues français. Le parcours existant suffit pour saisir des plans, affecter leurs images de départ, confirmer un devis, générer puis assembler. Aucun nouvel écran n’est nécessaire pour ce pilote supervisé.

La préparation réutilisable des images et la gestion structurée des répliques/voix restent des améliorations à développer après l’essai. L’audio natif actuel permet de demander des répliques dans les prompts ; il ne garantit ni le texte exact, ni une voix stable entre appels. Le pilote ne démontre pas encore une série automatisée de qualité constante.

## Personnages et décor communs

- **Milo** : petit chat roux humanoïde, museau crème, yeux ambrés, sweat bleu uni. Curieux et impulsif. Voix jeune, masculine, française.
- **Nox** : chatte anthracite humanoïde, petite tache blanche au menton, yeux verts, tablier orange uni. Calme et pragmatique. Voix féminine française, posée, distincte de Milo.
- **Style** : animation 3D originale, fourrure détaillée, visages expressifs, proportions identiques entre images, pattes stylisées cohérentes. Aucun personnage de franchise.
- **Lieu** : petite cuisine claire, parquet, réfrigérateur bleu au fond, deux tabourets, bol de croquettes violet. Un gros bouton rouge sans inscription est posé sur le comptoir. Aspirateur robot rond gris foncé.
- **Cadre** : vertical 9:16, personnages non coupés aux articulations, éclairage doux de journée. Pas de texte généré dans l’image. Audio natif conservé ; aucune musique commerciale nécessaire.

## Scénario et découpage

| Plan | Durée | Image de départ et action | Répliques exactes, dans l’ordre |
| --- | --- | --- | --- |
| 1 — Curiosité | 8 s | Milo à gauche regarde le bouton, Nox à droite. Milo le montre sans le toucher ; Nox l’arrête d’un geste. | Milo : « Ce bouton commande des croquettes ? » Nox : « Surtout, ne touche pas. » |
| 2 — Mauvaise idée | 11 s | Même cuisine et mêmes tenues. La patte de Milo approche le bouton. Il appuie ; l’aspirateur entre lentement dans le cadre. | Nox : « Il réveille l’aspirateur. » Milo : « Pourquoi tu me le dis maintenant ? » |
| 3 — Négociation | 11 s | Coupe franche : les deux chats sont déjà sur les tabourets ; Milo tient le bol violet. L’aspirateur passe en dessous. Pas de saut complexe à générer. Invitation VydFlow en fin de plan. | Milo : « On lui laisse l’appartement ? » Nox : « Oui. Mais prends les croquettes. » Puis Milo, vers la caméra : « Envie de créer tes histoires ? Découvre VydFlow. » |

Le propriétaire souhaite juger la succession des plans et ne dispose actuellement pas de crédits Fal. Les 30 secondes sont donc préparées sans lancement. Un éventuel essai limité au premier plan nécessitera une préparation et un devis distincts de 8 secondes : confirmer le devis actuel des trois plans autoriserait leur génération ordonnée, pas uniquement le premier.

## Références visuelles à produire avant vidéo

Les trois images de début ont été créées avec l’outil imagegen intégré, sans appel de génération Fal : [plan 1](pilotes/le-bouton-interdit/plan-01-v1.png), [plan 2](pilotes/le-bouton-interdit/plan-02-v1.png) et [plan 3](pilotes/le-bouton-interdit/plan-03-v1.png). Les deux variantes utilisent la première image comme référence. Vérifier les visages, les couleurs, les vêtements et la géographie de la cuisine avant toute animation.

[La maquette locale de 30 secondes](pilotes/le-bouton-interdit/maquette-30s-v1.mp4) présente les trois images pendant 8 + 11 + 11 secondes, les répliques à lire et l’invitation finale. L’étiquette « MAQUETTE · IMAGES FIXES · SANS VOIX » reste visible. Le fichier de sous-titres de préparation [maquette.fr.ass](pilotes/le-bouton-interdit/maquette.fr.ass) conserve les timings. Les animations et le bruit d’aspirateur décrits ci-dessous ne sont pas simulés dans cette maquette.

**Image maître :** « Image verticale 9:16, animation 3D originale soignée. Dans une petite cuisine claire au parquet, réfrigérateur bleu en arrière-plan. Milo, petit chat roux humanoïde au museau crème, yeux ambrés et sweat bleu uni, est à gauche. Nox, chatte anthracite humanoïde avec petite tache blanche au menton, yeux verts et tablier orange uni, est à droite. Un gros bouton rouge sans inscription repose sur le comptoir entre eux. Bol violet et deux tabourets visibles. Fourrure détaillée, expressions lisibles, proportions naturelles pour ces personnages stylisés, lumière douce. Garder les deux visages et leurs pattes visibles. Aucun texte, logo ou personnage supplémentaire. »

**Variante plan 2 :** conserver exactement les personnages, tenues, lumière et décor de l’image maître ; cadrer leur interaction près du bouton, patte de Milo juste avant l’appui, aspirateur encore hors cadre.

**Variante plan 3 :** conserver ces mêmes éléments ; placer les chats sur les deux tabourets, bol violet dans les pattes de Milo, aspirateur gris au sol. Les personnages restent ceux de l’image maître, sans ajout ni changement de tenue.

## Prompts vidéo prêts à relire

Utiliser une image de début propre à chaque plan, avec scènes indépendantes assemblées par coupes. Ne pas répéter automatiquement l’image maître pour les trois appels. Aucun cadre de fin requis pour ce protocole.

**Plan 1 — 8 s :** « Anime l’image de départ en une scène de 8 secondes. Caméra fixe, une seule prise. Milo, le chat roux en bleu à gauche, montre le bouton rouge sans le toucher et demande en français : “Ce bouton commande des croquettes ?” Nox, la chatte anthracite en orange à droite, répond calmement en français : “Surtout, ne touche pas.” Un seul personnage parle à la fois, dans cet ordre, avec mouvement de bouche correspondant. Conserver apparence, vêtements, placement et décor. Gestes courts, expression curieuse puis avertissement. Son d’ambiance discret, voix intelligibles, sans musique ni texte incrusté. Finir les deux répliques avant la dernière demi-seconde. »

**Plan 2 — 11 s :** « Anime l’image en une scène de 11 secondes, caméra fixe. Milo appuie une seule fois sur le bouton rouge ; le robot aspirateur gris arrive lentement au sol. Nox dit en français, voix féminine calme : “Il réveille l’aspirateur.” Puis Milo demande en français, voix masculine inquiète : “Pourquoi tu me le dis maintenant ?” Un locuteur à la fois, lèvres correspondant aux paroles. Même roux en sweat bleu, même chatte anthracite en tablier orange, même cuisine. Réaction de surprise lisible, sans saut ni transformation. Léger bruit mécanique sous les voix, sans musique ni texte incrusté. Terminer l’action et les paroles avant la coupe. »

**Plan 3 — 11 s :** « Anime l’image en une scène de 11 secondes. Les deux chats sont déjà perchés sur leurs tabourets, Milo tient le bol violet ; ne pas montrer de saut. Le robot passe lentement dessous. De 0 à 3 secondes, Milo, roux en sweat bleu, demande en français : “On lui laisse l’appartement ?” De 3 à 6 secondes, Nox, anthracite en tablier orange, répond : “Oui. Mais prends les croquettes.” De 6,5 à 10,5 secondes, Milo regarde la caméra et dit : “Envie de créer tes histoires ? Découvre VydFlow.” Prononcer le nom Vid-flo. Un seul locuteur à la fois, voix masculine inquiète puis féminine calme, lèvres correspondantes. Garder les visages, tenues et accessoires stables. Finir sur un regard complice. Aucune musique ni texte généré dans le clip. »

Ces consignes sont des demandes au modèle, pas un système garantissant deux identités vocales persistantes.

## Modèle candidat, coût et point technique découvert

Le catalogue local contient `seedance-25-image`, chemin `bytedance/seedance-2.5/image-to-video`, audio activé, H.264, 720p, durées compatibles avec 8 + 11 + 11 secondes. Le tarif indicatif public consulté le 26 octobre est d’environ 0,473 USD/seconde à 720p, facturé aux tokens. Source : [fiche Fal](https://fal.ai/models/bytedance/seedance-2.5/image-to-video).

Le contrôle MCP en lecture seule a toutefois donné un écart : l’identifiant local sans `/us/` ne se résout pas dans le catalogue connecté ; la recherche retourne `bytedance/seedance-2.5/us/image-to-video`, décrit comme hébergé aux États-Unis. Cela ne prouve pas l’indisponibilité universelle de l’ancien chemin ni un défaut du compte. Le nouveau choix `seedance-25-us-image` est désormais ajouté explicitement ; aucun ancien endpoint n’est remplacé dans une préparation ou dans le code.

Le schéma MCP de cette variante accepte une image de début, une fin facultative, les durées de 4 à 30 secondes, l’audio natif et les résolutions 480p/720p/1080p ; le format est toujours `auto`, donc dépend de l’image. Le tarif retourné pour cette variante est **0,0214 USD par 1 000 tokens**, pas un prix forfaitaire garanti par seconde. La configuration VydFlow ajoutée se limite à 720p, conserve l’image entière avec le format final choisi et indique le chemin US dans son nom et sa description. Son payload est vérifié en simulation, pas par une génération réelle.

Ordre de grandeur au taux indicatif du catalogue local, avec arrondi conservateur par plan :

| Étape | Estimation vidéo indicative |
| --- | --- |
| Plan 1, 8 s | 3,79 USD |
| Plans 2 et 3, 11 s chacun | 5,21 USD chacun |
| Épisode complet, premier plan réutilisé | 14,21 USD |

Ces montants ne sont pas un devis utilisable ni une autorisation de dépense. Ils excluent images, taxes, conversions et reprises. La facturation aux tokens dépend du contrat effectif. Aucune somme n’est réservée chez le fournisseur.

## Critères de sortie et travail suivant

Après accord sur les images et le coût du seul premier plan, contrôler : compréhension des deux répliques en français, bon personnage parlant, visages reconnaissables, deux voix distinctes, lèvres plausibles et pattes stables. En cas d’échec, arrêter et examiner le résultat avant toute nouvelle dépense. Ne pas fabriquer les plans suivants pour compenser un premier essai raté.

Si le premier plan convient : préparer les deux autres, confirmer leur devis puis assembler avec le moteur existant, conserver les sources et valider la version finale. Comparer la continuité des voix, des vêtements et du décor entre clips. Le pilote reste supervisé ; il ne déclenche aucune récurrence ni publication.

Le travail de compatibilité fournisseur est réalisé par l’ajout d’une configuration distincte, sans réécrire les anciens devis. Les fonctions d’images de scène, de voix persistantes et de répliques structurées ne sont pas nécessaires pour constater les limites du pilote ; elles deviennent le lot suivant si l’essai justifie leur intégration.

## Réalisation locale et vérifications

- `src/Service/AiCatalog.php` ajoute le choix explicite US. Le champ `aspect_ratio` envoyé reste `auto`, conformément au schéma ; les anciens contrats gardent leurs identifiants et paramètres. `FalVideoApi` et `FalMcpVideoApi` existants suffisent, sans nouvel adaptateur.
- `tests/Fixtures/seedance-25-us-image-contract.json` conserve le schéma réel lu en MCP le 26 octobre. Les tests `AiImageInputsTest`, `AiCapabilitiesTest` et `AiStudioTest` couvrent les cadres début/fin, durées, champs admis, format, audio, compte MCP et conservation du modèle historique.
- Revue croisée : elle a relevé la suppression du format par `omitRatio`, corrigée sur la seule nouvelle entrée. Premier lancement : 100 tests, un échec et une alerte associés à ce champ. Rejeu après correction : **100 tests, 1 965 assertions réussis**, fournisseurs simulés. PHPStan sans erreur et CS-Fixer sans correction au contrôle final.
- `npm run check`, **128 tests frontend** et build Vite réussis. Avertissements statiques/Three.js existants. Aucune nouvelle suite globale ni recette navigateur n’est revendiquée pour cet ajout au catalogue.
- Dans la base locale `shortflow`, création par `AiSeriesStudio` d’une nouvelle série privée **4**, « Pilote — Le bouton interdit (essai 8 s) », pour le même propriétaire que la série « Le chat farceur ». Épisode **7**, texte et image préremplis, accès Fal navigateur, format 9:16, image entière, modèle US explicite et durée 8 s. Les séries existantes ne sont pas modifiées. La source PNG est conservée dans le projet et sa copie normalisée est dans le stockage privé du propriétaire.
- Vérification après création : référence privée présente, aucune génération liée et aucune récurrence pour cette série. Le script ponctuel idempotent et les journaux sont ignorés dans `artifacts/`. Aucune confirmation ni aucun envoi social effectué.

Complément demandé ensuite : la série **4** s’appelle maintenant « Pilote — Le bouton interdit (30 s) » ; son épisode **7** contient trois plans et trois images de début distinctes, préparés par `AiEpisodeFlow` avec son devis habituel. Le brouillon précédent est sauvegardé dans un instantané ignoré avant modification. Le script ponctuel utilise un conteneur CLI isolé pour accéder aux services ; il ne change pas la configuration ni la visibilité des services du serveur web.

Contrôle après préparation : état `draft`, durée 30 s, trois plans, estimation indicative 1 421 cents USD, aucune génération liée, consentement nul et aucune récurrence. La maquette FFmpeg est vérifiée par ffprobe : durée exacte 30 s, 720 × 1280, flux vidéo seul. L’image extraite à 27 s confirme la lisibilité de l’invitation et de l’étiquette de maquette. Aucun appel API requis pour ce rendu local.

L’aide du choix de mode dans `AiIdeaType` et `AiSeriesType` recommande désormais les images pour retrouver les mêmes personnages sur plusieurs plans, rappelle que le texte seul permet de commencer et ne promet pas la fiabilité des mouvements ou des voix. CS-Fixer passe après cette modification de libellés ; aucune nouvelle suite globale n’a été relancée pour ces textes.

Accès local : [ouvrir le pilote](http://127.0.0.1:8000/studio/series/4) ou [ouvrir son épisode](http://127.0.0.1:8000/studio/series/episodes/7). La connexion habituelle au compte reste requise. Le récapitulatif du parcours commun permet de relire les entrées exactes et le coût avant la confirmation humaine.

Flux Symfony à retenir : formulaire de préparation → services avec contrôles de propriétaire/révision → devis et confirmation distincte → Messenger → adaptateur Fal → stockage privé → montage. L’ajout au catalogue rend le nouveau choix disponible ; il ne démarre aucun traitement payant.
