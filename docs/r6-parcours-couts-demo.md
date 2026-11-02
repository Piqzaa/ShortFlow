# R6.2 — parcours, coûts, validations et démonstration préparée

Travail des 2–3 novembre 2026. **R6.2 terminé, grand R6 en cours. Prochain point exact : R6.3**, regrouper fonctionnement, coûts/bêta, réseaux et premiers pas. Le propriétaire a choisi explicitement « Le film de présentation du projet » pour préparer la démonstration locale, sans génération IA ni publication réelle.

## Ce que voit le visiteur

La zone Studio de la landing explique trois points de départ en HTML :

- **Vidéo existante** : importer dans la bibliothèque privée, montage facultatif, puis textes/comptes/créneau.
- **Création IA** : idée ou image, relecture des scènes/modèle/devis, confirmation du coût, puis validation de la vidéo obtenue.
- **Série d’épisodes** : thème et épisodes, préparation possible des scénarios automatiquement, mais relecture et devis confirmé pour chaque vidéo.

La confirmation de publication reste une étape séparée, avec vérification des comptes, textes et date pour chaque vidéo. Un nouveau bloc « Ton studio. Ton compte fournisseur. » distingue le logiciel VydFlow actuellement sans abonnement facturé des générations payées sur le compte fournisseur. L’estimation et sa confirmation sont expliquées sans prix, crédit offert ou capacité illimitée inventés. L’import ne lance pas d’IA vidéo et la rédaction des textes reste distincte de cette génération payante.

Bêta, accès dépendants des réseaux et TikTok Sandbox restent explicites. Les illustrations sont présentées comme concepts/parcours, sans garantie de rendu, voix ou personnages. Le lien natif depuis la note IA mène au bloc Coûts ; Contact reste accessible. Aucun onglet/assistant imposé, nouveau paiement ni contrôle métier ajouté.

## Démonstration : un vrai import local, puis une relecture des écrans

Source approuvée : `public/media/films/hero-v4.webm`, **540×960, 14,960081 s**, SHA256 dans [le manifeste](fixtures/r62-parcours-demo-20261102.json).

`scripts/prepare-product-demo.php` démarre exclusivement en environnement test et refuse toute base autre que shortflow_test. Il crée un propriétaire de recette, soumet le véritable formulaire d’import avec son CSRF, puis passe par VideoProbe/FFprobe et le stockage privé réel. Le fichier stocké est byte-identique à la source, dont l’empreinte reste intacte.

Il ouvre ensuite le véritable préparateur, choisit YouTube sans connecter de compte, renseigne titre/description et enregistre un brouillon incomplet. Le titre sauvegardé est « Mon premier brouillon VydFlow ». **État draft**, aucun passage à la confirmation, programmation armée ou publication. Le résumé « Publication bloquée » visible dans la capture est attendu : aucun compte destinataire n’est connecté. Rien ne prétend à un envoi réussi.

Quatre réponses HTML réellement obtenues sont conservées : formulaire d’import, vidéo importée, préparation, brouillon enregistré. Le serveur `scripts/product-demo-router.php` les propose sur `http://127.0.0.1:8027/demo`, avec le film copié pour la lecture. C’est une **relecture de captures issues d’un import et d’un brouillon réellement exécutés en local**, pas une démonstration interactive active. Le bandeau l’explique ; aucun formulaire n’est relié à Symfony, les POST sont refusés 405, les routes et assets sont bornés. Le serveur reste en loopback avec noindex/no-store, sans réseau public ni règle de pare-feu.

La transaction est annulée et le fichier privé retiré. Onze compteurs identiques avant/après, file Messenger inchangée, cinq clients fournisseur/rédacteur remplacés par des refus d’HTTP : **zéro HTTP externe**. Les séquences test peuvent être consommées. Les quatre écrans et le film de relecture restent dans `artifacts/r62` ignoré pour examen ; ils ne sont pas publiés ni injectés dans la landing comme preuve d’une génération IA.

## Vérifications

- Migrations shortflow_test déjà à jour et cache test régénéré avant exécution. Script de démonstration réussi, empreinte source/stockage, statut draft, rollback et suppression du fichier privé vérifiés.
- **14 PHP ciblés / 146 assertions** (AccountTest), **189 frontend**, **119 Twig**, check et build verts. Deux scripts PHP : syntaxe et style verts. Pas de changement de service PHP applicatif ou schéma ; pas de nouvelle suite complète, 825 / 9 596 demeure historique R5. PHPStan/YAML/conteneur/schéma non rejoués ici.
- **18 contextes navigateur**, six tailles 320×740, 390×780, 768×900, 1280×900, 844×390 et 740×320, en normal/reduced motion/JS désactivé : zéro débordement et erreur JS. Écart téléphone/CTA toujours positif, connexion/inscription visibles puis suivies sans JS. Animations mobile/WebGL desktop et modes statiques conservés.
- Nouveaux blocs inspectés à 390 et 1280 px. Trois parcours et confirmation séparée présents ; coûts lisibles et lien natif opérationnel. Position finale de l’ancre ≈129,64 px (scroll-margin 90 + scroll-padding racine 40). Deux attentes initiales du banc sous JS désactivé ont expiré : inspection directe puis attente hors page ont confirmé le scroll natif ; aucun correctif applicatif requis pour cela.
- Viewer : navigation réelle entre les quatre écrans, titre sauvegardé retrouvé, métadonnées du film chargé 14,960081 s, GET 200 / POST 405. Première 404 favicon du wrapper corrigée en réutilisant le favicon existant. Les captures sont examinées et leurs empreintes consignées.
- Revue indépendante en lecture seule : règles coûts/comptes/récurrence et confirmation séparée cohérentes ; isolation test et whitelist du viewer vérifiées, aucun blocage identifié. Le relecteur n’a pas exécuté de tests/DB. Sa réserve de formulation (relecture, pas démo interactive ni publication réussie) est explicitement intégrée.

Les captures/manifests portent sur ce banc local. Pas de nouvel essai matériel iPhone, d’inférence ou de qualité audiovisuelle fournisseur certifiée. Les limites du grand R5 ne sont pas élargies par ces captures.

## Fichiers et pédagogie

- `templates/landing/index.html.twig` : contenu éditorial, liste native non ordonnée pour les trois départs, confirmation commune, ancre Coûts et paragraphes.
- `assets/styles/experience.css` : grille de coûts à deux colonnes desktop, une colonne mobile, espacements et typographie dans les tokens existants. Le bloc de coûts est hors de la séquence animée ; aucun nouveau chapitre 3D n’est ajouté.
- `scripts/prepare-product-demo.php` : utilise le KernelBrowser et les formulaires/services existants ; le rollback empêche de conserver ses données test.
- `scripts/product-demo-router.php` : relecture locale de sorties capturées, indépendante du serveur applicatif ; jamais un substitut au contrôle d’accès Symfony.

Pour reproduire : lancer le script dans le conteneur PHP avec la base test migrée, puis le serveur PHP loopback indiqué en tête du routeur. Sur la landing, ouvrir « Comprendre les coûts et la bêta », y compris sans JavaScript. Pour revoir la démo, utiliser les quatre liens du bandeau ; ne pas essayer de publier depuis ces captures.

**Suite R6.3** : organiser ces premiers contenus en fonctionnement, coûts/bêta, réseaux compatibles et premiers pas, sans tarif provisoire achetable. Aucun coût, appel fournisseur/rédacteur réel, compte social connecté, génération IA, publication, abonnement, activation, migration applicative, worker redémarré, déploiement ou push. Le serveur de relecture local 8027 est conservé pour le propriétaire ; les navigateurs de recette sont fermés à la livraison.