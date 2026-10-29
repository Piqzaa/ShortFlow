# R3.4 — Choix des modèles et connexions

Le 29 octobre 2026, le propriétaire demande la suite de la roadmap après les derniers ajustements du Studio. Ce lot harmonise les réglages vidéo de la configuration des Séries avec ceux du Studio. R3.5 et la revue globale R3.7 restent distincts.

## Comportement livré

Les deux parcours utilisent les mêmes trois propositions et le même sélecteur détaillé : recherche, modèle, résolution et favoris marqués d’une étoile. Les variantes sont filtrées selon le point de départ texte, image ou personnage. Rechercher un modèle ne le sélectionne pas ; le choix explicite reste nécessaire. Le modèle et la résolution déjà enregistrés sont conservés au chargement, même hors des trois propositions.

Le volet de connexion distingue la clé API Fal de la connexion navigateur Fal. Avoir l’une ne signifie pas disposer de l’autre. Les informations ne concernent que le propriétaire connecté. Une méthode navigateur invalide sur un autre fournisseur reste à corriger et ne peut pas être annoncée comme enregistrée grâce à une clé API.

La proposition de scénario utilise le rédacteur local facultatif ; la connexion vidéo sert à générer les clips. Une série peut être enregistrée sans clé vidéo. Enregistrer une configuration ne lance ni rédaction ni vidéo. Les devis et confirmations existants restent nécessaires avant génération. La disponibilité technique du rédacteur local n’est pas garantie par l’absence de clé vidéo.

Les listes natives restent utilisables sans JavaScript. En cas de modèle vide ou de méthode incompatible, le serveur refuse la soumission, conserve les saisies et rend le champ à corriger accessible. Le lien vers les connexions rappelle d’enregistrer la configuration avant de quitter ; la protection existante contre la perte des modifications est conservée.

## Lecture du code

- `templates/ai/_model_picker.html.twig` et `_video_connection.html.twig` produisent les mêmes contrôles dans les deux formulaires. Seul le champ natif du formulaire transmet la sélection au serveur.
- `assets/guided-model-picker.js` partage les interactions entre les contrôleurs Stimulus `creation` et `series-settings`. Twig fournit le catalogue et les états de connexion ; Stimulus filtre et synchronise les contrôles sans appeler de fournisseur.
- `AiSeriesController` transmet au formulaire les connexions du propriétaire et ses favoris. `AiSeriesType` valide le modèle et rattache l’erreur de méthode au champ de connexion.
- Le service de génération, le stockage des préparations, les confirmations et le schéma de base ne changent pas.

## Vérifications

Les tests de données utilisent exclusivement `shortflow_test`, sans suite DB concurrente. Tous les appels fournisseurs et le rédacteur sont simulés.

Passage final complet réussi : **790 tests PHP / 9 033 assertions**, **173 tests frontend**. Schémas dev/test, YAML/Twig/conteneur, PHPStan, style, syntaxe JS et build réussis. Avertissement Vite connu sur la taille du module Three.js. Aucun test ignoré. Cache dev actualisé et quatre workers relancés après vérification qu’aucune rédaction, génération, occurrence ou montage n’était actif.

Commandes : `npm run test:ui`, `npm run check`, `npm run build` ; `php scripts/check.php` dans un conteneur `edit-worker` avec `APP_ENV=test` et `APP_DEBUG=0`. Ce dernier crée/vérifie la base de test, applique ses migrations, valide les schémas dev/test et exécute `composer check` : YAML, Twig, conteneur, PHPStan, style et PHPUnit. Pour limiter le coût du montage Windows, cette campagne utilise une copie Linux temporaire des sources et du vendor ; les chemins de chargement du Kernel sont vérifiés, les `.env.*.local`, caches, artifacts et dépendances frontend sont exclus. Le conteneur temporaire est supprimé à sa sortie.

Deux premiers passages complets : 790 tests / 9 006 puis 9 010 assertions, deux échecs uniquement dans les nouveaux sélecteurs de test. Les assertions visaient initialement des `input`, puis `:checked` pour des options ; correction vers `textarea` et `[selected]`, sans changer les attentes de conservation. Rejeu ciblé : **2 tests / 36 assertions réussis**. Aucun test exclu ni garde métier assouplie.

La recette utilise de vrais GET Symfony authentifiés avec un propriétaire synthétique en base de test, puis leurs pages exportées et les assets de production servis sur une boucle locale. Studio et nouvelle Série : recherche sans mutation, choix explicite, résolution, source personnage, qualification API/navigateur, bouton de scénario accessible sans clé. Vérification desktop, mobile 375 × 812 et clavier ; aucune erreur console relevée, aucun débordement horizontal. Les changements de formulaire restent dans la page exportée ; la sauvegarde réelle et les erreurs HTTP sont vérifiées par les tests fonctionnels, pas par un POST navigateur. Captures locales non versionnées : `artifacts/r3-light/r34-series-desktop.png`, `r34-series-mobile.png`, `r34-studio-mobile.png`.

La revue indépendante a demandé de rattacher l’erreur MCP au champ de connexion, puis de corriger le faux état « navigateur enregistré » pour un fournisseur non-Fal disposant d’une clé API. Ces deux corrections sont intégrées et couvertes par les tests.

Pour tester manuellement : ouvrir la configuration d’une série, déplier le choix détaillé, rechercher un modèle puis choisir une résolution ; enregistrer et rouvrir pour retrouver le même choix. Sans clé vidéo, la configuration reste enregistrable. Dans le Studio, changer la méthode Fal doit mettre à jour son état sans changer le modèle. Ne pas confirmer de génération pour cette recette.

En Symfony, un champ `ChoiceType` garde la validation côté serveur même lorsque Stimulus masque sa liste native. Le navigateur améliore le choix ; il ne remplace ni la validation de formulaire ni les contrôles métier avant devis et soumission au fournisseur. Une erreur attachée au champ `accessMode` permet au Twig d’ouvrir précisément le volet utile.

## Limites et reprise

Aucun appel modèle réel, coût, génération audiovisuelle, publication, activation de récurrence, migration applicative ou push. La recette synthétique ne certifie pas le fonctionnement d’un compte fournisseur réel. Prochain point après validation : **R3.5**, accès à l’action suivante, erreurs et protections du récapitulatif. Le grand R3 reste en cours.

Commit local : `feat: share model and connection choices across studio and series`.
