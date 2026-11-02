# R6.1 — mentions publiques et description SEO, 2 novembre 2026

**R6.1 terminé ; grand R6 en cours. Prochain point exact : R6.2**, explication des parcours, coûts séparés et validations. Cette étape corrige les mentions obsolètes ; les guides/pages d’aide, le domaine, le partage social et les décisions d’indexation restent dans leurs étapes prévues.

## Comportement et sources

| Ancien texte | Texte livré | Justification locale |
| --- | --- | --- |
| Description commune : Studio IA et publication « à venir » | Description actuelle : bibliothèque, montage guidé, Studio IA et séries en bêta, accès des comptes connectés | Routes/services Studio, séries, montage et publications déjà présents ; périmètre bêta R2/R3 et restrictions de réseaux conservés |
| Accueil sans titre/description propres | Titre « VydFlow — Crée, prépare et publie tes vidéos courtes », description import/création/montage/publications, coût confirmé avant chaque génération | Promesse conforme au parcours existant, aucune génération gratuite ou autonome annoncée |
| « Séries à venir » | Préparer ses épisodes en série ; devis à relire/confirmer pour chaque vidéo | Préparation des séries livrée ; chaque génération exige sa confirmation, décision propriétaire R2 |
| « 03 / MONTAGE À VENIR » et aperçu canvas « BIENTÔT DISPONIBLE / futur studio » | « 03 / MONTAGE GUIDÉ », canvas « DÉMONSTRATION · BÊTA / Illustration du Studio IA » | Montage disponible, illustration distincte d’une preuve de rendu réel |

Trois fichiers : `templates/base.html.twig` fournit un bloc `meta_description` avec valeur par défaut ; l’accueil le remplace et définit son titre dans `templates/landing/index.html.twig`. `assets/screen-composer.js` actualise deux chaînes dessinées dans le téléphone desktop. Twig rend titre et description en HTML, donc disponibles sans JavaScript. Le canvas reste pure présentation : aucun coût, modèle ou consentement modifié.

Les textes bêta, films de démonstration, coûts sur compte fournisseur, accès navigateur/clé selon intégration, absence d’abonnement VydFlow actuellement facturé et restriction TikTok Sandbox restent affichés. La capacité locale de préparer une génération n’est pas une certification de qualité audiovisuelle ni de disponibilité générale des fournisseurs/réseaux. Les gardes tarifaires réelles restent actives, aucune date ni prix fournisseur actualisé.

## Vérifications adaptées au changement

- `docker compose exec -T php ... doctrine:migrations:migrate --env=test` : shortflow_test déjà à jour ; cache test régénéré.
- `lint:twig templates` : **119 Twig** valides.
- `APP_ENV=test APP_DEBUG=0 php vendor/bin/phpunit tests/Functional/AccountTest.php` : **14 tests / 146 assertions**, dont liens publics natifs connexion/inscription et refus d’accès non vérifié. Journaux locaux `artifacts/r61/php.log`, `twig.log`, `migrations.log`.
- `npm run test:ui` : **189 frontend** verts ; `npm run check` et `npm run build` verts. Avertissement taille bundle existant. Aucune nouvelle suite PHP complète : 825 / 9 596 demeure le passage R5 antérieur aux dernières finitions UI. PHPStan/style/YAML/schéma non rejoués ici (pas de service PHP ni schéma changé).
- **18 cas navigateur** : 320×740, 390×780, 768×900, 1280×900, 844×390 et 740×320, en normal, mouvement réduit et moteur JS désactivé. Zéro débordement document, un H1 et une description par page, écart téléphone/CTA mobile de 23 à 25 px. Les 15 cas étroits gardent connexion et inscription visibles dans le menu. Clics réels natifs connexion puis inscription réussis sans JS, sans soumission de formulaire.
- Mode normal : état mobile actif ou WebGL desktop ; reduced motion statique ; sans JS repli natif. Aucun changement CSS, de structure hero, d’animation, de média ou de navigation introduit par R6.1.
- Note IA/Séries inspectée à 390 et 1280 px, captures après chargement effectif de l’image différée. À 390, note puis illustration séparées de 45 px ; à 1280, colonne de texte et composition séparées de 72 px. Texte bêta/Sandbox et démonstration encore présents. Zéro erreur JS dans les contextes contrôlés ; warnings WebGL desktop déjà connus non interprétés comme validation tous GPU.
- Revue indépendante ciblée en lecture seule : capacités rapprochées des sources et bilans R2/R3, aucune promesse autonome, de tarif actualisé ou de qualité garantie ; héritage Twig et changements canvas sans blocage identifié. Le relecteur n’a pas exécuté de test/DB.

[Manifeste](fixtures/r61-landing-20261102.json) : mesures, captures et empreintes. Les SHA des sources portent sur les fichiers bruts de ce PC, fins de ligne comprises. La recette est locale Chromium ; aucun nouvel essai sur iPhone réalisé en R6.1, aucune mise en ligne/indexation/référencement réel validé. Les premières captures de la note montraient l’image avant fin du chargement différé ; elles ont été remplacées par les captures finales chargées, aucune correction applicative requise.

## Pédagogie et suite

Le bloc Twig évite de dupliquer tout le `<head>` : la base garde une description cohérente, l’accueil précise sa propre promesse. Le titre de navigateur est distinct du H1 visible, conservé pour l’identité validée. Le code canvas dessine du texte ; modifier ses libellés ne modifie ni le parcours réel ni l’animation.

Pour vérifier : ouvrir `/`, lire le titre de l’onglet, la note Studio IA et le libellé de timeline ; ouvrir Menu puis connexion/inscription sur écran étroit. Désactiver JavaScript conserve les contenus et liens. Aucune génération ou publication n’est nécessaire.

**Prochain point R6.2** : expliquer import/création/série, coûts VydFlow/fournisseur, validations et bêta en HTML, sans présenter les illustrations comme démonstration réelle. Un essai réel ou média privé requiert son autorisation propre ; cette étape n’en a déclenché aucun. Aucun canonical, sitemap, Open Graph, service SEO, budget, compte externe, abonnement, migration applicative, activation, worker redémarré, déploiement ou push.