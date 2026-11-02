# R5.5 — corrections responsive et recette restante

2 novembre 2026. **R5-D1 et R5-D3 corrigés sur le banc PC ; R5.5 et le grand R5 restent ouverts.** La recette téléphone R5.2, l’écoute ciblée des erreurs/annonces et la préférence système réelle R5.3 sont encore requises. Aucun résultat synthétique ne les remplace.

## Corrections et fonctionnement

Le téléphone décoratif de la landing est maintenant dans le même conteneur que son titre et son appel à l’action. À 1000 px et moins, il occupe sa propre place dans le flux : le texte vient ensuite et la section grandit selon son contenu. La commande de pause suit le contenu. À l’écran peu haut, on défile ; le texte ne remonte plus sur le téléphone. Il n’y a qu’un téléphone de fallback et une vidéo, pas de duplication du média. Sur desktop, le rendu WebGL masque toujours ce fallback ; la pause le rétablit.

Le titre des en-têtes Studio peut revenir à la ligne au milieu d’un segment sans espace. Son conteneur flex peut rétrécir et reste limité à la largeur disponible. La chaîne humaine de 118 caractères est conservée intégralement ; aucune troncature, modification métier ni règle globale de masquage de débordement ajoutée.

Fichiers : `templates/landing/index.html.twig` place le média et la pause ; `assets/styles/experience.css` définit le flux mobile et la visibilité desktop ; `assets/styles/workspace-responsive.css` borne les titres. La revue a aussi trouvé deux attributs `data-preparation-target` sur le même bouton dans `_timing.html.twig` : ils sont réunis en une liste de cibles Stimulus. Le serveur rend l’état initial avec Twig ; CSS règle les dimensions ; Stimulus conserve les animations et améliorations existantes. Le serveur continue à contrôler toutes les soumissions.

## Recette locale

**21 vues × 4 largeurs (320/390/768/1280, hauteur 900) = 84 contrôles DOM sans débordement document.** Cette nouvelle matrice ne couvre pas les 28 états de R5.1 : elle reprend les 19 vues R5.4 et ajoute génération à vérifier et export actif. Génération active, génération à vérifier et export actif utilisent un titre valide de 118 caractères, dont un segment de 112 caractères sans espace. Les douze cas à titre long restent dans la page ; le lien de retour de l’export reste visible aux quatre largeurs. Les titres calculés gardent la chaîne complète et leur largeur de défilement reste dans leur boîte à l’arrondi près.

Landing : six tailles, 320×740, 390×780, 768×900, 1280×900, 844×390 et 740×320, en normal, pause et scripts applicatifs absents, soit **18 relevés**. En mobile, écart téléphone/texte d’environ **23 à 25 px**. Les mêmes écarts persistent après pause ; le clic a fait défiler la page pour atteindre le bouton, les coordonnées de viewport négatives de ces relevés ne sont pas assimilées à une occlusion. Certains relevés normaux ont été pris en état `loading` : ils vérifient la géométrie initiale, pas un rendu final WebGL.

Un contrôle desktop distinct attend réellement l’état **webgl** : canvas opaque, fallback à opacité zéro. Après pause : état **static**, canvas transparent et fallback opaque. Aucun avertissement/erreur relevé par le journal navigateur de ce contrôle ponctuel ; cela ne certifie pas tous les GPU. Inspection visuelle ciblée à 390×780 sans scripts et export actif à 768×900 : espacement et titre complet confirmés ; Tab depuis le retour d’export atteint « Actualiser le suivi ». Pas de capture archivée pour ces inspections.

Le scénario sans scripts retire les balises applicatives et laisse le moteur JavaScript du navigateur actif ; les contenus `noscript` ne sont donc pas rendus. La préférence reduced motion réelle reste à tester. Les résultats de contraste/zoom/clavier R5.3 sont historiques et bornés ; cette matrice ne les transforme pas en audit exhaustif.

## Tests, isolation et incidents de recette

- **186 tests frontend réussis**, check et build réussis ; avertissements connus médias/polices et taille du bundle Three.js. Journaux `artifacts/r55-ui.log`, `r55-check.log`, `r55-build.log`.
- **34 PHP / 376 assertions réussis** : AccessibilityTest, ResilienceTest, VideoEditTest, avant la fusion finale des attributs de cibles. Après ce correctif Twig : **3 PHP / 31 assertions réussis**, 115 Twig et syntaxe des trois scripts de recette réussies ; conteneur vérifié auparavant. Journaux `r55-final-php.log`, `r55-final-review.log` et `r55-php.log` pour les premiers lints.
- **21 exports finaux**, empreintes vérifiées, onze compteurs identiques après rollback et zéro HTTP fournisseur/rédacteur. HTMLParser contrôle aussi l’absence d’attribut dupliqué dans les 21 documents. Toutes les écritures de fixtures sont dans `shortflow_test` et annulées ; séquences de test consommées possibles.
- Aucune nouvelle suite PHP complète : le passage R5.4 **820 / 9 019, 35 erreurs + 2 échecs** reste historique, mêmes 37 cas liés aux tarifs datés que le départ. Aucun tarif/garde modifié, aucune suite verte globale revendiquée.

La validation automatique a refusé une commande qui aurait archivé la configuration privée `.env.local`. Cette commande n’a pas été exécutée. La copie de test a ensuite utilisé uniquement les `.env`/`.env.test` publics suivis par Git et les valeurs synthétiques, sans `.env.local`. Premières fixtures rejetées : titre trop long pour la limite de 120 et état d’export inexistant `exporting`, remplacés par 118 caractères et l’état métier `rendering`. Transactions annulées, protections inchangées. Le premier routeur inclus ne propageait pas `return false` pour les assets : corrigé avant toute mesure retenue. Un premier parseur local a échoué en lecture Windows CP1252, puis a été relancé explicitement en UTF-8.

Le [manifeste R5.5](fixtures/r5-corrections-20261102.json) conserve mesures, empreintes finales et limites. La copie Linux isolée et son archive ont été retirées après récupération des preuves, sans `.env.local` ; le viewport est réinitialisé, `git diff --check` passe. Le serveur GET 8023 est conservé pour la courte recette manuelle ci-dessous ; le serveur 8021 existant n’a pas été arrêté.

## Revue globale à ce stade

Relecture locale des changements R5.3–R5.5 : cible d’évitement, ouverture des détails invalides, thème de formulaire et liens ARIA, nom du compte compact, contrôles activés seulement après connexion Stimulus, conservation des références, repli du montage, modèle/connexion et protections serveur. L’attribut de cible dupliqué ci-dessus a été corrigé. Cette relecture est celle de l’agent qui a implémenté ; **aucune revue indépendante nouvelle n’est revendiquée**, aucun commit automatique effectué. Les preuves R5.1/R5.2 restent distinctes et historiques.

Pour terminer R5.5 : effectuer les essais manuels ci-dessous, compléter la revue finale avec leurs résultats, décider du traitement des défauts éventuellement trouvés, puis le commit de clôture. **Ne pas cocher R5.2, R5.3 ou R5.5 maintenant, ni passer R6 comme si R5 était clos.** Aucun appel réel, coût, publication, activation, migration applicative ou redémarrage de worker.

## Ce que le propriétaire peut tester ensuite sur PC

Les exports de recette sur `http://127.0.0.1:8023` ne créent ni compte ni vidéo ; le routeur refuse les POST. Les données affichées sont synthétiques.

1. Ouvrir `/qa/register-error`, activer le Narrateur avec **Ctrl + Windows + Entrée**, puis avancer avec Tab jusqu’à l’adresse email et aux mots de passe. Vérifier l’annonce du libellé, de l’état invalide et d’une explication compréhensible. Ne pas soumettre le formulaire. Noter précisément ce qui est lu ou manque.
2. Ouvrir `/qa/generation?case=poll-error`, garder le lecteur actif et attendre le message de suivi indisponible. Vérifier si l’annonce est entendue, puis si le lien « Actualiser le suivi » est trouvable. Ce scénario ne lance aucune génération. Indiquer séparément « annonce automatique entendue » et « texte accessible en parcourant la page ».
3. Dans Windows, désactiver temporairement les effets d’animation dans Accessibilité → Effets visuels, puis recharger `/qa/landing`. Vérifier que le téléphone reste statique et les liens utilisables. Rétablir ensuite le réglage initial. Le retour général « le Narrateur fonctionne » est utile, mais ne suffit pas à valider ces trois cas ciblés.

Arrêter le Narrateur avec **Ctrl + Windows + Entrée**. Aucun essai payant ou social demandé. Si le serveur 8023 a été arrêté, le relancer localement avec la commande en tête de `scripts/responsive-router.php`.

Suivi du 2 novembre : le propriétaire confirme le Narrateur sur les erreurs d’inscription après le correctif d’ordre des descriptions. Cette étape est acceptée ; il reste l’annonce dynamique du suivi et la préférence système de mouvement. Les correctifs et tests sont désormais enregistrés dans `5fe6f0d`, les outils/preuves séparément ; cela ne constitue pas le commit de clôture R5. Les mentions antérieures d’absence de commit restent historiques.

L’essai sur téléphone reste séparé : menu public et Studio, portrait/paysage, barre basse et safe areas, champs avec clavier virtuel, défilement et fermeture du menu. **127.0.0.1 sur un téléphone désigne le téléphone lui-même**, pas ce PC ; aucun accès réseau/public n’a été ouvert pour cet essai. Préparer un accès de recette autorisé avant de demander de le faire.
