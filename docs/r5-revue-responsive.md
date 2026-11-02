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

## Validation propriétaire des effets Windows désactivés

Le 2 novembre, après la procédure Accessibilité → Effets visuels, le propriétaire confirme que le rendu sans effets est plus sobre, fonctionne sans anomalie et que les liens fonctionnent. Le test de la préférence Windows réelle et du fallback de la landing est accepté sur la base de ce retour ; aucun état système n’a été mesuré automatiquement. Le rétablissement du réglage initial n’est pas confirmé. Les validations Narrateur des erreurs d’inscription restent acceptées ; l’annonce dynamique du suivi et le téléphone réel restent à vérifier avant clôture R5.3/R5.5. Aucun code applicatif changé, suite rejouée, coût ou appel réel pour cet enregistrement. Commits existants : `5fe6f0d` et `121ff8e`, sans push.

## Reprise sur ce PC et revue indépendante — 2 novembre 2026

Contact livré dans `25f0a30`, horloge tarifaire dans `d79da9d`. Revue indépendante de Contact et des changements R5.3–R5.5 en lecture seule : aucun blocage de code identifié ; les six destinations sont conservées dans le menu natif, en complément de la barre basse. Dernier ajustement visuel Contact : lien sur sa propre ligne desktop, profil non comprimé, sous-titre borné. À 320×400 sans moteur JavaScript, Tab atteint Contact visible (y=252,5–296,5), menu à huit liens et six destinations basses.

Nouvelle matrice : **23 vues × 4 largeurs = 92 contrôles sans débordement document**, dont Contact public/connecté. Landing à 390×780 et export à 768×900 inspectés sur captures ; aucun chevauchement gênant ni titre tronqué constaté sur ces cas. Mouvement réduit émulé ; aucune nouvelle mesure de préférence Windows ou téléphone revendiquée. **23 exports**, rollback/11 compteurs égaux et zéro HTTP fournisseur/inférence. [Manifeste](fixtures/contact-r5-reprise-20261102.json).

Suivi dynamique synthétique observé en onglet visible : mutations à 4 029, 24 034 et 44 040 ms, région `role=status`, `aria-live=polite`, texte final et lien Actualiser présents. Ces observations DOM ne prouvent pas l’annonce sonore.

**Suite complète finale : 825 PHP / 9 596 assertions**, 186 frontend, check/build verts ; 118 Twig, 19 YAML, conteneur, PHPStan, 323 fichiers de style et schéma test verts. Le problème temporel des tarifs de fixtures est corrigé par horloge injectée ; la garde réelle et les dates de catalogue sont conservées. [Détails des tests, incidents préparatoires et limites](tests-horloge-tarifs.md).

### Ce que le propriétaire doit encore faire

1. Sur ce PC, ouvrir `http://127.0.0.1:8024/qa/generation?case=poll-error`. Activer le Narrateur **avant Ctrl+R**, garder l’onglet visible une minute, sans Tab. Dire si le message est annoncé spontanément ; le texte final arrive vers 44 secondes. Ensuite seulement, utiliser Tab pour vérifier que « Actualiser le suivi » est trouvable. Arrêter le Narrateur avec Ctrl+Windows+Entrée.
2. Téléphone : portrait/paysage, menus public/Studio, barre basse et safe areas, saisie avec clavier virtuel, défilement et fermeture du menu. Le serveur reste en loopback ; **aucune URL téléphone n’est encore préparée**. Confirmer la disponibilité du téléphone et d’un accès privé (par exemple même Wi-Fi) avant de préparer cet accès. Ne pas utiliser 127.0.0.1 depuis le téléphone.
3. Avant ouverture R6.4 : vérifier la réception d’un email adressé à `dev@alexbuild.fr` et fixer un délai de support réaliste. Aucun email réel envoyé par l’agent.

**Décision de sortie : R5.2, R5.3, R5.5 et grand R5 restent ouverts**, uniquement dans les périmètres restant à valider décrits ici ; le bilan de revue PC est livré. Après retours, consigner les résultats et corriger/rejouer si nécessaire avant clôture. Les essais d’erreurs d’inscription et d’effets Windows désactivés déjà acceptés ne sont pas à refaire par défaut. Aucun coût, appel réel, activation, migration applicative, redémarrage worker, déploiement ou push. Le serveur synthétique GET 8024 est conservé pour l’écoute manuelle ; il refuse les POST. Les anciens ports indiqués dans les sections historiques ne sont pas le point de reprise courant.
## Retour propriétaire : annonce spontanée acceptée

Le 2 novembre, le propriétaire confirme : « suivi indisponible est annoncé spontanément », après le reste de la lecture (titre, etc.). L’annonce dynamique est donc validée par écoute humaine sur ce PC. Sa lecture après le contenu en cours est acceptée pour cette région `aria-live=polite`. Aucun nouvel essai automatique ni changement applicatif. Avec les erreurs d’inscription et la préférence Windows déjà acceptées, **R5.3 est validé sur le périmètre contrôlé** ; aucune conformité exhaustive revendiquée.

## Téléphone sur le réseau local : préparation, pas validation

PC Ethernet et téléphone Wi-Fi sur même réseau confirmés. Serveur PHP de recette démarré caché, lié explicitement à `192.168.1.95:8025`, avec `scripts/responsive-router.php` : exports synthétiques, GET uniquement, aucun transfert vers Symfony. Landing et création répondent 200 depuis le PC via IP LAN ; POST refusé 405. Aucun tunnel, profil réseau ou service applicatif modifié. Ethernet est classé Public par Windows, sans reclassement. Tentative de règle entrante limitée à IP Ethernet, TCP 8025, programme PHP et 192.168.1.0/24 : refus Windows « Accès refusé », faute de privilèges administrateur. Aucune règle ajoutée. Accès téléphone à essayer ; si inaccessible, traiter pare-feu/isolation Wi-Fi sans désactiver les protections globales.

Liens temporaires, tant que le PC garde cette IP et le serveur tourne :

- `http://192.168.1.95:8025/qa/landing` : menu public, connexion/inscription, texte et CTA sans chevauchement, portrait/paysage.
- `http://192.168.1.95:8025/qa/creation` : menu Studio, six destinations basses, défilement, champ texte avec clavier ouvert/fermé, actions accessibles et barre basse hors de la zone d’accueil/encoche. Aucun formulaire à soumettre.

Recueillir modèle de téléphone/navigateur et résultat de ces cas, tout chevauchement/action inaccessible. **R5.2, R5.5 et grand R5 restent ouverts.** Arrêter le serveur temporaire après recette ; aucune règle pare-feu à retirer puisque l’ajout est refusé.

## Email : boîte et transport applicatif distincts

Le propriétaire confirme que `dev@alexbuild.fr` fonctionne. Cela valide sa boîte, pas un envoi depuis VydFlow. L’application utilise encore Mailpit local : transport de production, expéditeur, liens du domaine et réception des emails transactionnels restent à tester en R8.4. Délai support encore à fixer, aide complète à préparer en R6.4. Aucun email réel envoyé dans cette étape.
## Retours iPhone après ouverture LAN

Le propriétaire indique un rendu responsive correct sur iPhone 14 Pro, mais signale la flèche en emoji, la hero statique et les cartes de distribution trop hautes. Correctifs et recette PC livrés, [bilan iPhone](r5-retours-iphone.md) et [manifeste](fixtures/r5-iphone-20261102.json). Ce premier retour matériel ne clôture pas les essais téléphone : revalider les corrections et les cas clavier/zones basses. R5.2/R5.5 restent ouverts ; R5.3 reste validé. Dernier passage : 24 PHP ciblés / 336 assertions, 189 frontend, 119 Twig/check/build ; suite complète 825 / 9 596 historique, non rejouée.
## Validation propriétaire des trois correctifs

Après demande de rechargement sur son iPhone 14 Pro, le propriétaire répond « Ok oui la c’est supp ». Les trois corrections (flèches, hero, cartes) sont acceptées sur ce périmètre. Aucun état système de batterie/réduction des animations n’a été mesuré : pas de certification générale du mode économie d’énergie. Les essais ciblés clavier virtuel, portrait/paysage et zones basses restent à confirmer avant clôture R5.2/R5.5. Aucun code changé ni test rejoué pour cette validation ; compteurs précédents historiques. Commit applicatif `30f6aba`, aucun push.
## Confirmation matérielle finale et clôture R5

À la question précise « en paysage et avec le clavier ouvert, les menus, la barre basse et les actions restent-ils accessibles, sans chevauchement ? », le propriétaire répond « Oui tout fonctionne ». Cette validation sur son iPhone 14 Pro lève le dernier prérequis R5.2 dans le périmètre demandé. R5.1–R5.5 sont validés après revue globale indépendante finale sans blocage ; [bilan de clôture](r5-revue-cloture.md). Aucun nouveau test ni code applicatif pour cette confirmation. Le serveur LAN temporaire 8025 a été arrêté ; prochaine tâche R6.1. Les sections antérieures conservant R5 ouvert sont historiques.