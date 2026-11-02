# Clôture globale R5 — 2 novembre 2026

**R5 terminé, R5.1 à R5.5 validés sur les périmètres documentés.** La dernière réponse du propriétaire, « Oui tout fonctionne », répond à la question précise sur le paysage, le clavier ouvert, les menus, la barre basse et les actions accessibles sans chevauchement. Elle clôt le prérequis matériel restant sur son iPhone 14 Pro. Le navigateur exact et les états batterie/préférence système ne sont pas déduits de la capture.

## Grille du lot et décision de sortie

| Étape | Résultat | Preuves et limites |
| --- | --- | --- |
| R5.1 | Matrice réalisée : 28 états × 4 largeurs, quatre défauts identifiés, cas longs/actifs/erreurs conservés. | [Matrice initiale](r5-matrice-responsive.md), 112 contrôles historiques, non assimilés à 112 cas verts. |
| R5.2 | Connexion/inscription dans menu public natif, six destinations Studio conservées, libellés lisibles et noms complets ; flèches monochromes sur surfaces concernées. Recette matérielle iPhone acceptée. | [Navigation](r5-navigation-mobile.md), [retours iPhone](r5-retours-iphone.md). Portrait/paysage, clavier et zones basses confirmés humainement sur un appareil, pas certification tous téléphones. |
| R5.3 | Focus/lien d’évitement, panneaux invalides, libellés/erreurs et annonces, parcours clavier, contraste borné et zoom natif contrôlés. Narrateur erreurs puis annonce dynamique et préférence Windows acceptés. | [Accessibilité](r5-accessibilite.md). Zoom 200 % observé sur préparateur, contrastes complexes exclus, aucune conformité exhaustive. |
| R5.4 | Replis des commandes sans scripts, références conservées, montage, médias manquants/lents et suivi 503 simulés. Contrôles serveur payants/sociaux conservés. | [Résilience](r5-resilience.md). Banc scripts absents distinct d’un moteur JS désactivé ; dernier menu Contact testé aussi avec moteur désactivé. |
| R5.5 | D1 landing remis dans le flux ; D3 titres longs complets et retours accessibles ; finitions iPhone acceptées. Revue globale indépendante sans blocage de clôture. | [Revue progressive](r5-revue-responsive.md). 92 contrôles DOM récents distincts des 112 initiaux ; trois retours iPhone corrigés, puis acceptés. |

Les quatre défauts R5-D1/D2/D3/D4 ne restent plus ouverts. La porte de sortie est satisfaite dans les états contrôlés : accès et actions conservés, corrections vérifiées, derniers essais matériels explicitement acceptés. Aucun défaut bloquant restant identifié par la revue globale indépendante.

## Sources, pédagogie et protections

Twig rend les liens natifs, les noms accessibles et les erreurs ; le thème de formulaire relie les descriptions/champs et Stimulus ouvre les panneaux invalides. CSS fixe le flux mobile, la largeur des longs titres et les cartes compactes sans modifier les textes humains. L’icône SVG partagée est décorative et ne remplace pas le nom du lien.

La hero mobile réutilise cinq images et les textes de démonstration ; son canvas léger ne dépend pas de la lecture vidéo automatique. Arrêt hors vue/onglet et invalidation des chargements tardifs préservent pause et préférences. Desktop conserve son rendu 3D. Rien de ce code de présentation n’autorise une génération ou publication : les devis, confirmations de coûts, accès propriétaire et contrôles serveur restent distincts.

L’horloge tarifaire injectée rend les fixtures déterministes sans changer les tarifs réels. [Bilan spécifique](tests-horloge-tarifs.md) : une date de tarif ancienne reste refusée avant nouveau lancement en runtime normal ; aucun tarif fournisseur n’a été revérifié à l’occasion de R5.

## Contrôles datés, non rejoués pour cette clôture documentaire

- Dernière suite PHP complète : **825 tests / 9 596 assertions**, après correctif d’horloge, avant derniers ajustements UI iPhone. Copie Linux isolée sans `.env.local`, shortflow_test migrée, image de montage déjà installée pour transcription hors réseau. Les passages rouges préparatoires sont conservés dans leur bilan ; aucune exclusion de test.
- Après corrections iPhone : **24 PHP ciblés / 336 assertions**, **189 frontend**, **119 Twig**, check et build verts. Les nouveaux tests couvrent cycle de vie du canvas, arrêt hors vue/onglet, chargements tardifs et repli image. Aucun nouveau service PHP ni schéma changé dans ce complément.
- Contrôles qualité antérieurs pertinents : 19 YAML, conteneur, PHPStan, 323 fichiers de style et schéma test verts après correctif tarifaire. Non présentés comme exécutés aujourd’hui à nouveau.
- Dernier banc : 23 exports, rollback et onze compteurs concordants, zéro HTTP fournisseur/inférence ; 92 contrôles sans débordement. Mobile tactile Chromium à autoplay refusé : changement image/texte et flottement, pause/reduced motion ; desktop atteint WebGL. Captures et empreintes conservées dans [le manifeste iPhone](fixtures/r5-iphone-20261102.json) et [la reprise PC](fixtures/contact-r5-reprise-20261102.json).
- Retours humains : erreurs d’inscription lues, préférence Windows réduisant les animations acceptée, annonce spontanée du suivi acceptée après le texte en cours, trois corrections iPhone acceptées, puis paysage/clavier/menu/barre/actions confirmés par « Oui tout fonctionne ».
- Revue globale indépendante finale en lecture seule : R5.1–R5.5 et porte de sortie confrontés aux sources/manifests/journaux, protections et limites vérifiées ; aucun test/DB lancé par le relecteur, aucun blocage identifié.

## Fin de recette et prochain travail

Le serveur LAN temporaire `192.168.1.95:8025` a été arrêté après la confirmation finale. Aucun worker ni serveur applicatif arrêté, aucune règle pare-feu à retirer (ajout refusé par Windows), aucun profil réseau modifié. Les anciennes URLs LAN des rapports sont des liens de recette historiques, pas un service permanent.

Commits principaux : `e6628f8` (navigation), `5fe6f0d` (accessibilité/replis/responsive), `d79da9d` (horloge tarifs), `30f6aba` (finitions iPhone) ; validations documentaires `0344754` et `c716764`. La clôture est enregistrée dans un nouveau commit documentaire local identifié par Git. Aucun push demandé ou réalisé ici ; aucun coût, appel réel, activation, publication, migration applicative ou déploiement.

**Prochain point exact : R6.1**, relire/corriger les mentions obsolètes, descriptions SEO et promesse publique, en conservant identité/animations et acquis responsive. R6 non clôturé : Contact anticipé livré, aide complète et délai de support restent à préparer. Boîte dev@alexbuild.fr fonctionnelle confirmée ; Mailpit reste le transport local, emails applicatifs de production à configurer/tester en R8.4. Aucun abonnement, essai payant, publication autonome ou déploiement n’est autorisé par cette clôture.