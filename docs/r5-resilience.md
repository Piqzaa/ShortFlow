# R5.4 — fonctionnement sans scripts et erreurs de chargement

Le 2 novembre 2026, **R5.4 terminé sur le banc local décrit ici**. Le grand R5 reste ouvert. Prochain point : R5.5, superposition de la landing, titres longs puis revue globale ; les recettes matérielles R5.2 et les contrôles manuels restants de R5.3 demeurent requis.

## Comportement corrigé

Sans chargement des scripts applicatifs, « Tout sélectionner » apparaissait sans agir. Ce raccourci attend maintenant la connexion du contrôleur ; les cases individuelles et la soumission GET restent natives. La proposition automatique démarre désactivée, avec une explication visible et la rédaction manuelle disponible. Le contrôleur l’active seulement si le moteur est disponible, puis masque l’explication JavaScript.

Les imports propres aux plans, leur cadrage et leur retrait attendent aussi leur contrôleur. L’explication indique la conservation des références enregistrées et le recours aux images communes pour un import natif. Les commandes d’ajout de sujet et de changement vers le mode image restent masquées avant leur initialisation ; les champs natifs existants restent disponibles.

Dans le montage, les commandes exclusivement interactives sont masquées en l’absence de la classe `edit-enhanced`. Les formulaires et dialogues ouverts restent dans le flux du document : le dialogue d’export ne recouvre plus le début de page. Avec les scripts, les onglets et dialogues retrouvent leur comportement existant. Aucun service métier, tarif, consentement ou protection serveur changé.

Pour comprendre le flux : Symfony construit le formulaire et Twig rend un état natif utilisable. Si les assets chargent, Stimulus connecte ses contrôleurs et révèle les raccourcis qu’il sait gérer. Un formulaire natif envoie toujours ses valeurs à Symfony, qui vérifie propriété, CSRF, validité et consentement avant toute mise en file. Une commande désactivée dans le navigateur n’est donc jamais une autorisation serveur.

Les fichiers Twig `_story_proposal`, `_reference_picker`, `_subjects`, `_timing`, `workspace` et la bibliothèque définissent cet état initial ; les contrôleurs `story_writer`, `reference_picker`, `subject_list`, `preparation` et `selection` apportent les améliorations. Les CSS `editor-desk` et `series` règlent le repli visuel. `ResilienceTest` vérifie les requêtes natives ; les tests frontend vérifient la transition d’initialisation. Pour reproduire, exporter en environnement de test puis ouvrir `/qa/creation?case=scripts-absent`, `/qa/edit?case=scripts-absent` ou `/qa/composer?case=media-missing` avec le routeur local. Ses formulaires POST sont volontairement refusés ; les vrais parcours serveur se rejouent avec PHPUnit.

## Banc et limites de méthode

`scripts/export-accessibility-fixtures.php r54` crée **19 exports** en transaction sur `shortflow_test`. Les clients vidéo, sociaux et rédaction sont simulés et refusent tout HTTP. Le rédacteur est déclaré disponible uniquement dans ce processus d’export, afin de vérifier un bouton disponible après initialisation ; aucun réglage applicatif n’est activé. Onze compteurs concordent après rollback, zéro HTTP fournisseur/rédacteur. Les séquences de la base de test peuvent être consommées.

`scripts/resilience-router.php` sert ces exports et les assets locaux sur `127.0.0.1:8022`. Toute méthode autre que GET reçoit 405. Les scénarios retirent les scripts, renvoient un média 404, retardent un MP4 synthétique de 1,8 seconde ou font échouer le suivi avec 503. Le navigateur ne peut pas soumettre une génération ou une publication à l’application par ce routeur.

**Scripts absents n’est pas moteur JavaScript désactivé.** Les balises `script` sont retirées ; le moteur du navigateur reste actif, donc les contenus `noscript` ne sont pas affichés. Cette campagne vérifie une panne de chargement des scripts et le HTML natif. Les parcours HTTP natifs sont vérifiés séparément par les tests fonctionnels, sans exécution de JavaScript. Aucun réglage JavaScript du navigateur, téléphone réel ou conformité globale n’est certifié ici.

## Observations navigateur

- **19 vues à 1280 × 720** : landing, connexion, inscription, informations légales, inscription refusée, vidéos vide/remplie, création, nouvelle série, calendrier, connexions, publications, profil, import, série, épisode, montage, génération et préparation de publication. Zéro script, cible `main#contenu` focalisable, aucun bouton `type=button` visible et actif sans contrôleur ; largeur document 1265 px pour 1280 px de viewport. Les éléments cachés et panneaux repliés sont exclus de ce relevé.
- **Quatre vues à 390 × 780** : montage, création, épisode et vidéos remplies. Zéro script, aucune commande interactive morte dans le même périmètre ; document 375 px pour un viewport réellement mesuré à 390 px. Une première tentative restée à 1280 px est exclue des résultats mobiles. Ce test ne remplace pas un téléphone.
- Montage sans scripts : export situé vers 2789–3232 px, musique 3280–3411 px et projet 3781–3975 px, en position statique ; l’ancien export absolu vers 28–470 px recouvrait le début de page. Avec scripts : classe `edit-enhanced`, dialogues fermés et onglet Clip affiché. L’onglet Médias réservé au mobile, masqué sur desktop, est exclu du contrôle de visibilité desktop.
- Avec scripts : proposition du rédacteur activée dans la fixture disponible, message JavaScript masqué ; sélection globale coche la vidéo et active la préparation. Aucun scénario demandé au rédacteur, aucun POST fournisseur.
- Média manquant : le lecteur expose « Impossible de lire les contenus multimédias. » ; titre et métadonnées restent présents. Choisir une date ouvre les champs, le bouton de sauvegarde du brouillon reste actif. Aucun brouillon soumis par le navigateur.
- Média ralenti : lecteur observé en mise en mémoire tampon, formulaire et bouton de sauvegarde exposés ; son GET synthétique renvoie 200, 3638 octets en environ 1,813 s. Aucune mesure de réseau réel ou de performance production.
- Suivi en erreur : après les échecs simulés, message « Le suivi automatique est indisponible. Utilise “Actualiser le suivi” ; la génération peut continuer en arrière-plan. » et lien manuel conservé. Un 503 de suivi ne signifie pas que la génération a échoué.

Les contrôles HTTP du banc confirment : page sans scripts 200 sans balise script, POST 405, média manquant 404 et suivi défaillant 503. Les médias de recette sont synthétiques ; aucune qualité audiovisuelle réelle évaluée. Les défauts R5-D1/D3 ne sont pas clos par ces observations. Pas de nouvelle campagne de captures archivée.

## Tests et preuves

| Contrôle | Résultat | Journal local |
| --- | --- | --- |
| Frontend final | **186 réussis**, aucun ignoré | `artifacts/r54-ui-final.log` |
| Syntaxe frontend et build final | Réussis ; avertissements connus médias/polices et taille Three.js | `r54-check-final.log`, `r54-build-final.log` |
| PHP ciblés finaux | **53 tests / 626 assertions réussis** | `artifacts/r54-final-targeted.log` |
| Twig/style/syntaxe finaux | **115 Twig**, style du nouveau test et routeur PHP réussis | `artifacts/r54-final-static.log` |
| Export final | **19**, rollback/11 compteurs/HTTP zéro | `artifacts/r54-export-final.log` |
| Suite complète, passage antérieur aux derniers ajustements UI | **820 tests / 9 019 assertions, 35 erreurs + 2 échecs** | `artifacts/r54-full.log` |

Le passage complet a les **mêmes 37 noms de cas en défaut** que le commit de départ `54423f8` rejoué en R5.3 : tarifs datés du 19 octobre et garde de quatorze jours. Aucun tarif ni garde assoupli. Schémas dev/test, YAML, conteneur, PHPStan et style passent dans ce contrôle complet. **Ne pas annoncer la suite PHP complète verte**, ni ce passage comme postérieur aux derniers ajustements sujet/mode image/CSS montage. Ces derniers sont couverts par les contrôles finaux ciblés et navigateur.

Les trois nouveaux cas fonctionnels vérifient une sélection GET vers le préparateur, un POST natif d’ajout de plan conservant les textes, et un POST direct de génération refusé sans CSRF puis sans consentement au coût. Dans ce dernier cas, état `prepared` et taille de file inchangés. Les suites ciblées incluent aussi AccessibilityTest, AccountTest, ValidationQueueTest et VideoEditTest. Les premiers essais ont corrigé des erreurs de fixture/API de test : argument de redirection, jeton de session, lecture d’un textarea et exclusion du prototype de carte. Aucune attente métier affaiblie.

Le manifeste [r5-resilience-20261102.json](fixtures/r5-resilience-20261102.json) conserve les empreintes des sources, exports et journaux. Il distingue les résultats historiques R5.3 des sources reprises et modifiées ici. Aucun commit, push, migration applicative, worker redémarré, appel réel, coût, activation ou publication. La revue globale reste en R5.5.

Après récupération des preuves, la copie Linux `/tmp/r54-project` et son archive temporaire ont été retirées. Le serveur de recette R5.4 sur 8022 a été arrêté après vérification de sa commande ; le serveur 8021 du préparateur existant a été conservé. L’override de viewport du navigateur a été réinitialisé. `git diff --check` ne signale pas de défaut d’espacement.
