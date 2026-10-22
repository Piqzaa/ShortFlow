# Fiabilité des publications — 22 octobre 2026

## Objectif et périmètre

Consolider le parcours existant avant les séries : détecter les incompatibilités connues avant l'envoi, distinguer traitement et visibilité, reprendre un suivi sans renvoyer la vidéo et isoler les tâches IA des départs programmés. Aucun envoi social réel ou appel IA payant déclenché par ce lot. Aucun abonnement, quota par offre ou objet de série ajouté.

## Contrôles vidéo

`VideoProbe` conserve son analyse ffprobe bornée et sans shell. Il relève maintenant cadence moyenne, débit vidéo, rotation, proportions des pixels, entrelacement et caractéristiques de l'audio. `VideoLibrary` enregistre ces données dans le champ JSON nullable `VideoAsset.technicalMetadata` lors de l'import, y compris pour les résultats IA et les exports.

`PublicationVideoCheck` applique les règles communes aux préparations classiques, au compositeur et à la programmation, via les services de chaque réseau. Une incompatibilité bloque la confirmation de tout le lot avec la destination, la vidéo et la correction à effectuer. Avant un nouveau transfert, les handlers vérifient également la compatibilité ; une session distante existante reste suivie sans renvoi.

- YouTube Shorts : source carrée ou verticale après rotation et proportions des pixels, durée de 3 minutes maximum. Le classement définitif appartient à YouTube ; aucune garantie contre les restrictions de compte, de droits ou de contenu.
- Instagram : MP4 H.264, 3 à 180 secondes (limite VydFlow), largeur affichée au plus 1920 pixels, proportions de 0,01:1 à 10:1, cadence 23–60 images/s, débit vidéo connu au plus 25 Mbit/s, vidéo progressive ; audio facultatif, sinon AAC mono/stéréo à 48 kHz maximum.
- TikTok : MP4 H.264, 3 secondes minimum, limite dynamique du créateur et limite VydFlow de 180 secondes, dimensions affichées de 360 à 4096 pixels par côté, cadence 23–60 images/s. Les choix de confidentialité/interactions restent contrôlés avec les capacités courantes du créateur.

Les imports sont toujours limités à 100 Mo. Une cadence inconnue bloque Instagram/TikTok ; un débit inconnu bloque Instagram. Les anciens fichiers sans métadonnées sont sondés localement à la demande, sans migration de masse des médias ni écriture en base sur un GET. Le résultat est réutilisé pendant la vie du service ; les sources privées sont immuables.

Ces contrôles ne constituent pas une certification complète des formats par les plateformes. Les particularités des conteneurs MP4 (edit lists, position de l'atome moov), les pics de débit, les droits musicaux et la modération restent des limites. L'export existant normalise en H.264/AAC, 30 images/s, pixels carrés et faststart ; les refus distants restent traités par le suivi.

## Statuts et reprise

`YouTubePublication.effectivePrivacy` conserve uniquement la visibilité renvoyée par YouTube au succès du traitement. Une réponse sans visibilité laisse le champ vide et affiche « visibilité à vérifier ». Une visibilité différente de la demande déclenche un message ; aucune modification de visibilité ni republication automatique. Un rejet distant l'emporte sur un état de traitement réussi.

`PublicationOverview` prépare les actions affichées dans `publication/_job.html.twig` :

- **Suspendue** : reprendre l'envoi ou le suivi selon l'étape déjà atteinte.
- **Résultat incertain** : reprendre uniquement un suivi existant, si un identifiant permet de l'interroger sans nouvel envoi. Pour Instagram, l'intention de publication doit déjà être enregistrée ; le POST de publication ne sera pas répété. Sans identifiant TikTok après une tentative, aucun bouton de reprise ni nouveau POST.
- **Échec confirmé** : « Corriger et préparer à nouveau » ouvre le compositeur pour la vidéo et la seule destination concernées. `PublicationRetry` vérifie propriétaire, statut courant et source ; reprend textes et réglages ; TikTok exige de choisir à nouveau la visibilité. Le compte courant est affiché et une nouvelle confirmation reste obligatoire.
- **Réussie** : aucune action de nouvel envoi proposée. Les autres destinations du lot ne sont pas recopiées dans une correction.

Ouvrir une nouvelle préparation ne publie rien. Les clés de soumission, transactions, verrous et marqueurs de tentative existants restent les protections contre les doubles traitements. Deux confirmations distinctes de préparations différentes représentent toujours deux demandes explicites : aucune déduplication par contenu n'est promise.

## Files et concurrence

`messenger.yaml` route `GenerateAiVideo` et `RefreshPublicationStatistics` vers `background`, consommée par le service Docker `background-worker`. Le worker `publications` est réservé aux départs programmés et aux envois ; `edit-worker` conserve `video_edits`.

Le traitement IA utilise un verrou PostgreSQL distinct (85404) de celui des publications (85402), afin qu'une requête fournisseur ou un téléchargement IA ne monopolise pas le verrou d'envoi du même utilisateur. Les courtes transactions de quota/import restent protégées par le verrou de ligne utilisateur. Les tâches d'une même catégorie restent sérialisées pour le propriétaire.

La migration additive `Version20261022030000` ajoute les deux champs et déplace les anciens messages IA/statistiques non pris en charge de `publications` vers `background`, en conservant corps, délais et identifiants. Elle reconnaît le format PHP natif de Messenger (même sans en-tête de type) sans instancier le message, ainsi que l'en-tête de type du format JSON. Les messages déjà pris en charge ne sont pas réassignés à chaud ; ils terminent avec l'ancien consommateur ou sont retraités selon les protections existantes. Mettre à jour les workers ensemble. Ne pas vider les files pour effectuer cette migration.

Le matériel, PostgreSQL et le stockage restent partagés : cette séparation supprime l'attente derrière un appel IA dans le même consommateur, sans garantir une publication à la seconde. Le contrôle des créneaux dépassés reste actif.

## Repères Symfony et flux

1. **Controller/Form** : chargent les ressources du propriétaire et valident champs, CSRF et confirmation.
2. **Service métier** : les services de publication appellent `PublicationVideoCheck`, puis créent les demandes et les messages dans les transactions existantes.
3. **Entity/Doctrine** : conservent les caractéristiques de la source et les résultats de suivi ; une migration additive fait évoluer le schéma sans remplacer les données.
4. **Messenger/Handler** : exécutent les appels longs hors requête web, enregistrent les états et reprennent les identifiants existants.
5. **Twig** : présente statut, visibilité et action adaptés ; les textes restent échappés.

La distinction importante est qu'un message Messenger peut être livré plusieurs fois. La sécurité vient des états et identifiants persistés avant les effets distants, pas seulement d'un bouton désactivé dans l'interface.

## Vérifier localement

Après mise à jour, appliquer les migrations puis recharger les caches/services selon le README. Les tests fonctionnels utilisent exclusivement `shortflow_test` et des clients HTTP simulés. Ne pas lancer les consommateurs sur cette base pendant PHPUnit.

- `php scripts/check.php` dans Docker : migrations test, schémas, lints, PHPStan, formatage et PHPUnit. Le lanceur et Composer imposent l'environnement test avant les contrôles ; le schéma dev reste vérifié explicitement en lecture seule. Cela empêche l'héritage des valeurs par défaut dev dans les processus de test. PHPStan dispose de 256 Mo pour éviter un arrêt prématuré à 128 Mo.
- `npm run check`, `npm run test:ui`, `npm run build` ; le lanceur des tests frontend énumère désormais les fichiers pour fonctionner aussi sous Windows.
- Tests ajoutés : limites de cadence/débit/audio, rotation et pixels non carrés, lecture des anciens fichiers, blocage de tout le lot, visibilité réelle/inconnue, rejet prioritaire, reprise d'un seul conteneur, refus de reprise TikTok ambiguë, préparation d'une seule destination en échec et isolation des files/verrous.
- Parcours manuel sans envoi : sélectionner une vidéo incompatible, lire l'erreur et vérifier que la confirmation est bloquée ; consulter les actions sur des états simulés, au clavier et sur petit écran. Une publication réelle ou une génération facturable nécessite un essai séparément autorisé.

## Sources techniques primaires consultées

- [YouTube — vidéos courtes jusqu'à trois minutes](https://support.google.com/youtube/answer/15424877).
- [Meta — spécifications Reels du dépôt officiel](https://github.com/fbsamples/reels_publishing_apis/blob/main/insta_reels_publishing_api_sample/README.md#reels-requirements-for-publishing).
- [TikTok — règles de consentement et capacités du créateur](https://developers.tiktok.com/docs/en/content-sharing-guidelines).
- [TikTok — transfert et caractéristiques vidéo](https://developers.tiktok.com/docs/en/content-posting-api-media-transfer-guide).

## Suite produit

Série enregistrée et épisodes préparés manuellement, puis génération d'un lot fini et programmation de chaque épisode. Génération récurrente autonome, mémoire narrative, quotas Gratuit/Creator et facturation sont des lots distincts.

## Résultats du lot

- Migration appliquée sur dev et `shortflow_test` ; schémas synchronisés. Avant le rechargement local : files vides, aucune programmation armée ni traitement vidéo/IA/social actif. Les trois workers démarrent sur leurs files respectives.
- Contrôle global final depuis une copie temporaire Docker, sans `.env.local` ni médias privés : **433 tests, 5 706 assertions, succès**, 58,460 s. Lancement depuis `APP_ENV=dev` volontairement, le script impose ensuite test. Comparaison SHA-256 de 522 fichiers source/tests/configuration/templates/migrations/scripts/Docker/assets/public et configurations racine : aucune différence avec le projet.
- 19 YAML, 75 Twig, conteneur, PHPStan niveau 5 et CS-Fixer (0/206) valides. Les premiers passages ont permis de corriger les messages conservés dans les anciens tests, des références Doctrine périmées dans les nouveaux tests, la limite mémoire PHPStan, l'environnement hérité du lanceur et le format réel des messages Messenger. Le résultat final ne comporte ni échec ni avertissement PHPUnit.
- Frontend : `npm run check`, `npm run test:ui` (**91 tests**) et `npm run build` réussis. Avertissements Vite habituels sur les fichiers publics servis à l'exécution et la taille du module Three.js.
- QA réelle du rendu Twig sur une fixture synthétique loopback : bureau 1280×800, mobile 390×844, visibilité demandée/confirmée distincte, reprise Instagram présente et reprise TikTok ambiguë absente, correction limitée à l'échec, aperçu repliable et accès clavier au bouton de correction. Aucun débordement horizontal, console sans avertissement/erreur. Les actions serveur sont couvertes par les tests fonctionnels ; l'aperçu statique ne déclenche pas de traitement métier. Captures ignorées dans `artifacts/reliability/`, serveur de QA arrêté et viewport rétabli.
- Vérifications exclusivement simulées pour les réseaux et fournisseurs IA. Aucun nouveau transfert réel, appel IA facturable, déploiement, commit ou push.
