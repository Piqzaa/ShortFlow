# Audit global Studio, Séries et génération IA — 25 octobre 2026

Ce rapport décrit l’état initial au commit `b54189e`. Les corrections autorisées ensuite et leurs vérifications sont suivies dans [la suite de l’audit](suite-audit-studio-series.md) ; les constats ci-dessous restent conservés comme référence historique.

## Verdict

Le socle est exploitable et l’identité graphique est cohérente. Il reste des incohérences importantes : le contexte d’un épisode confirmé n’est pas complètement figé, deux contrôles peuvent se contredire sur les cadres, les capacités ne sont pas représentées de façon identique dans Studio et Séries, et la préparation éditoriale des publications est encore minimale. Les quatre lots précédents ont amélioré ces parcours ; ils n’ont pas démontré que toute la chaîne est parfaite.

La priorité est de corriger l’intégrité des préparations et la sélection des références, puis de compléter les textes de publication. Une nouvelle refonte graphique complète et l’ajout de nombreux modèles ne sont pas prioritaires.

## Méthode et limites

Base examinée : commit `b54189e`, dépôt propre au début de l’audit. Trois agents spécialisés, en plus de la synthèse principale :

- `audit_ux` : design, parcours, Stimulus, responsive et captures existantes ; compétence `frontend-review`.
- `audit_models` : catalogue, contrats, prompts, références et consentement ; compétence `backend-review`.
- `audit_recurrence` : production récurrente, reprise, montage et publication ; compétence `backend-review`.

Ils ont confronté leurs constats : UX ↔ modèles pour la priorité des cadres et les personnages ; modèles ↔ récurrence pour le contexte mutable, les empreintes et les interruptions ; UX ↔ récurrence pour les états et la préparation éditoriale. Les doublons sont regroupés ci-dessous. Une hypothèse contestée n’est pas présentée comme un échec fournisseur constaté.

Le rapport consolidé a ensuite été relu par les trois agents sur leurs périmètres. Cette seconde lecture a précisé le test du contrat Kling encore ambigu et le statut d’une occurrence après reprise manuelle.

Revue du code courant, de la documentation et des tests existants. Analyse visuelle de captures de recette antérieure, notamment `generation-studio-desktop.png`, `generation-lot4-mobile.png` et `generation-progress-mobile.png` ; pas de nouvelle recette navigateur complète pendant cet audit. Les contrôles clavier/mobile de la livraison précédente restent des résultats antérieurs.

Deux reproductions sur de vrais objets/services, sans démarrer Symfony, sans base et sans HTTP : changement du prompt après modification de l’univers de la série ; acceptation de 3 000 caractères par le validateur catalogue Kling. Script local ignoré : `artifacts/audit-studio-series-2026-10-25-repro.php`. Les suites PHP/frontend de la livraison précédente n’ont pas été réexécutées dans cet audit ; leurs succès ne couvrent pas les nouveaux cas trouvés ici.

Aucun code applicatif modifié, génération vidéo payante, publication, activation de récurrence ou push. Les capacités déclarées dans le code, les contrats documentés, les simulations et la qualité réelle sont quatre niveaux distincts. Les contrats de tous les endpoints n’ont pas été revérifiés en direct.

## Le parcours réel aujourd’hui

**Studio / épisode manuel, enchaînement métier** : idée et durée → choix du modèle et des références → scénario local facultatif → plans → devis → confirmation des coûts → générations ordonnées → assemblage → validation d’une version → préparateur de publication → confirmation immédiate ou calendrier. L’ordre visuel ne suit pas entièrement cet enchaînement, comme expliqué en F15.

**Récurrence** : autorisation bornée à 1–12 créneaux → préparation la veille → rédaction locale → clips et assemblage → vidéo à valider → brouillon avec réseaux/date proposés → textes et réglages à relire → confirmation de publication.

Choisir des réseaux dans une récurrence ne programme pas leur envoi. La publication reste supervisée, conformément aux libellés et au périmètre autorisé. L’absence d’envoi autonome n’est pas un bug. Les choix YouTube « Publique » et « conçue pour les enfants » ont été explicitement demandés par le propriétaire ; ils restent modifiables et ne sont pas signalés comme anomalie.

## Image de départ et personnage : répondre à l’intention

Une image de départ peut représenter un décor, un objet, une composition ou déjà un personnage. Elle n’est pas automatiquement une photo d’identité. Les références de personnages peuvent être séparées si l’endpoint le permet.

| Intention | Entrées cohérentes | Ce qu’il faut expliquer |
| --- | --- | --- |
| Animer une rue vide, puis faire entrer Milo | Rue comme premier cadre ; références nommées de Milo sur un modèle compatible ; scène décrivant son entrée | Le premier cadre reste la rue vide. La référence guide l’apparence du personnage qui entre ensuite. |
| Commencer avec Milo déjà dans la rue | Premier cadre contenant déjà Milo, éventuellement références d’identité complémentaires | Le premier cadre fourni ne contient pas spontanément un personnage absent de ses pixels. Préparer une composition est une étape distincte. |
| Inventer plusieurs scènes avec Milo sans imposer leur premier cadre | Références d’identité avec le parcours Vidu référence | La référence ne fixe pas une pose de départ identique pour chaque scène. |
| Prolonger un mouvement déjà filmé | Vidéo en entrée dans l’extension native compatible | Un JPEG extrait du dernier clip transmet une image, pas son mouvement ni son audio. |

La séparation `start_image_url` / `elements` existe réellement pour Kling : le problème n’est donc pas de supprimer cette combinaison, mais de mieux l’exprimer et de fiabiliser son contrat. La [documentation Kling image](https://fal.ai/models/fal-ai/kling-video/v3/pro/image-to-video/api) expose les deux entrées ; [Vidu référence](https://fal.ai/models/fal-ai/vidu/q1/reference-to-video/api) reçoit des images d’apparence sans premier cadre imposé.

## Constats prioritaires

### F01 — P1, confirmé et reproduit : l’univers d’une série peut modifier un épisode déjà accepté

Le prompt v3 relit les personnages et le style actuels de la série. Le devis inclut le prompt effectif, mais le lancement suivant reconstruit ce texte après un contrôle tarif/contrat, sans comparer à nouveau son contenu au devis. Modifier la série entre deux clips peut donc changer l’identité ou le style du plan suivant. La protection de l’occurrence automatique utilise son brief enregistré et ne neutralise pas cette lecture de la série courante.

Preuves : [construction du prompt](<C:/Users/alexb/OneDrive/Bureau/Outils dev/Projet/ShortFlow/src/Service/AiEpisodeFlow.php:534>), même fichier lignes 429–449 ; [modification de la série](<C:/Users/alexb/OneDrive/Bureau/Outils dev/Projet/ShortFlow/src/Service/AiSeriesStudio.php:112>) ; [consentement récurrent](<C:/Users/alexb/OneDrive/Bureau/Outils dev/Projet/ShortFlow/src/Service/SeriesGenerationConsent.php:33>).

La reproduction passe de « chat blanc, aquarelle » à « chat noir, photoréaliste » : le prompt change alors que la recette et la révision de l’épisode restent identiques. Aucun POST fournisseur n’a été exécuté pour cette reproduction.

**Correction** : figer le contexte visuel de l’épisode et les prompts effectifs acceptés. Les modifications de série ne doivent concerner que les futures préparations, sauf révision explicite avec nouveau devis.

### F02 — P1, confirmé par deux agents : une image commune écrase un cadre explicite

Une image A présente dans le raccourci commun remplace systématiquement B choisi comme début du premier plan ; même conflit entre fin commune et fin explicite du dernier plan. Le devis peut révéler A avant paiement, mais le formulaire ne donne pas la priorité attendue au choix explicite.

Preuve : [affectation des cadres](<C:/Users/alexb/OneDrive/Bureau/Outils dev/Projet/ShortFlow/src/Service/AiEpisodeFlow.php:66>), lignes 66–71.

**Correction** : une seule source de vérité. Le raccourci doit affecter le cadre une fois, puis l’éditeur du plan devient la référence. À défaut, refuser les valeurs contradictoires ; ne pas les écraser silencieusement.

### F03 — P1, confirmé en revue croisée : empreinte récurrente incomplète

Le hash de l’autorisation parent contient recette, brief, durées, prix, date de vérification et identité BYOK, mais pas le contrat complet du catalogue ni la version des consignes. Une évolution d’endpoint ou de paramètres à prix/date identiques peut être adoptée par la prochaine production automatique. Le nouvel épisode fige le contrat courant, ce qui ne corrige pas rétroactivement le consentement parent.

Preuves : [prévol récurrent](<C:/Users/alexb/OneDrive/Bureau/Outils dev/Projet/ShortFlow/src/Service/SeriesRecurrences.php:50>) ; `SeriesGenerationConsent::assertAllowed`, ligne 33 ; comparaison avec `AiEpisodeFlow::tariff`, lignes 563–566.

**Correction** : construire une empreinte partagée couvrant contrat et versions pertinentes de génération. En cas de différence : pause et nouvelle autorisation, sans dépenser davantage automatiquement.

### F04 — P1 de qualification, contrat à clarifier : éléments Kling incomplets selon la documentation descriptive

Le payload envoie uniquement `frontal_image_url` par élément. La documentation décrit un ensemble avec vue frontale et une à trois références supplémentaires, dont au moins une, ou une vidéo d’élément. Le schéma expose cependant ces champs comme optionnels : la contrainte métier n’est pas clairement portée par la structure JSON.

Preuve : [payload actuel](<C:/Users/alexb/OneDrive/Bureau/Outils dev/Projet/ShortFlow/src/Service/FalVideoApi.php:65>). Sources primaires : [documentation](https://fal.ai/models/fal-ai/kling-video/v3/pro/image-to-video/api) et [OpenAPI](https://fal.ai/api/openapi/queue/openapi.json?endpoint_id=fal-ai/kling-video/v3/pro/image-to-video).

**Ce constat n’établit pas un rejet réel du payload actuel.** Il impose de lever l’ambiguïté avant de promettre un personnage fiable avec une seule image. Prévoir plusieurs vues par élément si le contrat confirmé l’exige ; ne pas dupliquer artificiellement une photo pour contourner une contrainte supposée.

## Autres incohérences et manques

| ID / priorité | Constat et preuve | Suite minimale |
| --- | --- | --- |
| F05 / P2 | Passer de « image avec fin » à « personnage » conserve `useEnd`, puis filtre les modèles personnages dépourvus de fin. `creation_controller.js`, lignes 40 et 294. | Désactiver les paramètres incompatibles lors du changement de rôle, en conservant les fichiers et en expliquant leur désaffectation. Aucun modèle substitué. |
| F06 / P2 | Séries active l’import de personnages pour tout modèle image, sans connaître `subjectLimit`, alors que le serveur refuse ceux qui ne le supportent pas. `series/configure.html.twig:6`, `series_settings_controller.js:34`, `AiSeriesStudio.php:57`. | Partager les capacités Studio/Séries ; montrer limite restante et retrait des anciennes images, même si leur ajout devient indisponible. |
| F07 / P2 | Les références sont des fichiers anonymes ; aucune correspondance structurée nom → élément → rôle → présence au début. Le rédacteur reçoit seulement un rôle général et ne voit pas les photos. `AiShotPrompts.php:23–27,54`, `_subjects.html.twig`. | Nommer les personnages/objets et leur rôle dans le plan ; traduire ces noms vers la syntaxe fournisseur côté serveur. |
| F08 / P2 | Le validateur accepte 3 000 caractères pour Kling 3 image ; l’OpenAPI annonce une limite de 2 500. Le risque vise surtout les anciennes recettes qui ajoutent le contexte entier. `AiCatalog.php:148`, `AiEpisodeFlow.php:543`. | Limites par endpoint sur le prompt final, au devis et avant POST ; révision explicite si trop long, jamais troncature cachée. Reproduction pure réussie. |
| F09 / P2 | Les tests de contrats images ne contrôlent pas récursivement les objets, `anyOf`, les longueurs et contraintes entre champs. `AiImageInputsTest.php:145–161`. | Cas ciblés pour les contraintes sensibles, dont éléments Kling, longueurs et combinaisons début/fin/sujet. Une simulation valide ne garantit pas une acceptation réelle. |
| F10 / P2 | La récurrence peut accepter un ratio `auto`, mais `AiEpisodeFlow::prepare:42` le refuse après rédaction locale. `AiSeriesType.php:46`, `SeriesRecurrences::preview/activate`. | Même prévol que le moteur réel avant autorisation : ratio explicite, durée, mode et références. Ce défaut ne démontre pas un paiement vidéo. |
| F11 / P2 | Après mort brutale du worker entre le commit de l’occurrence `writing` et la fin du rédacteur, le calendrier est déjà avancé ; le vieux message est ignoré et recover ne reprend que le prochain créneau. `SeriesRecurrences.php:130,163–184`, `RecoverSeriesRecurrencesCommand.php:23`. | Claim/expiration durable, transition « à vérifier », pause et reprise explicite. Les exceptions ordinaires sont déjà traitées ; distinguer OOM/arrêt brutal. Aucun renvoi vidéo automatique. |
| F12 / P2 produit | Les titres/captions récurrents sont seulement le titre de l’épisode et le même résumé narratif sur les réseaux. Pas de stratégie ni proposition de hashtags. `EpisodePublications.php:50–52`. | Pack éditorial adapté et modifiable, détaillé ci-dessous. Les hashtags peuvent déjà être saisis manuellement dans les textes. |
| F13 / P2 | Le résumé d’occurrence est écrit une fois, puis réutilisé après réparation/montage/nouvelle validation ; il peut raconter une ancienne version. `SeriesRecurrences.php:198`, `EpisodePublications.php:50–52`. | Lier les propositions éditoriales à la version approuvée ; signaler « à relire » et préserver les textes saisis par l’utilisateur. |
| F14 / P2 produit | La récurrence assemble les clips mais n’applique pas une recette de série musique/textes/sous-titres. Elle impose `mute=false`. `SeriesRecurrences.php:197`, `VideoEdits::assembleEpisode:227–269`. | Ajouter ensuite une petite recette utilisant les capacités existantes, avec aperçu et versionnement ; aucune création média payante implicite. |
| F15 / P2 UX | Le bouton scénario précède le modèle pourtant nécessaire. Les notions sont dispersées entre cadres communs, cartes de références et plans. `_recurrence` et `show` séparent encore insuffisamment les actions attendues par occurrence. | Ordre progressif, prérequis visibles, états vidéo/textes/publication distincts ; rappel interne avant échéance. |
| F16 / P3 | Deux retraits proches pour une référence ; libellé « scènes distinctes » en mode unique ; compteur fallback incluant l’extension cachée ; token `--border` inexistant sur le cadre image. `_subjects`, `series/episode:17`, `ai/studio:51`, `series.css:17`. | Un retrait principal, libellés par mode, compteur du même catalogue affiché et token `--workspace-border`. |

Précision F11 : `SeriesOccurrence::manageManually` ne modifie que le booléen `automatic`, pas son statut `writing`. Même après reprise manuelle et progression de l’épisode, la page série peut conserver « Rédaction en cours » et sa branche de liens spécifique. La correction doit donc aussi réconcilier le statut d’occurrence avec la reprise effective, pas seulement permettre de rouvrir l’épisode.

## Matrice des modèles intégrés

Cette matrice décrit l’intégration actuelle, pas toutes les capacités commerciales de chaque famille. Les variantes de résolution et de fournisseur conservent leur identifiant : aucune substitution n’est recommandée. Les contrats Kling éléments et Vidu références ont fait l’objet d’une vérification primaire dans cette passe ; les autres lignes reposent sur catalogue, adaptateurs et tests présents.

| Famille / mode | Premier cadre | Fin | Identité séparée |
| --- | --- | --- | --- |
| Vidu Q1 référence | Non imposé | Non | Jusqu’à 7 images au total |
| Kling 3 image | Requis | Facultative | 2 éléments dans l’application ; contrat des vues à clarifier |
| Kling 2.5 image | Requis | Facultative | Non |
| Wan 2.5 image, Fal / Replicate | Requis | Non | Non |
| Pika 2.2 image | Requis | Non | Non |
| Pika début + fin | Requis | Requise | Non |
| Vidu Q1 image | Requis | Non | Non |
| Vidu Q1 début + fin | Requis | Requise | Non |
| Grok image, variantes présentes | Requis | Non | Non |
| Hailuo 02 image | Requis | Facultative selon catalogue | Non |
| PixVerse image | Requis | Non | Non |
| PixVerse transition | Requis | Requise | Non |
| Seedance 2.5 image | Requis | Facultative | Non |
| LTX Fast image | Requis | Facultative | Non |
| Veo Fast Replicate image | Requis | Facultative | Non |
| Variantes texte présentes | Aucun | Aucun | Aucune image transmise |
| LTX 2.3 Pro extension | Vidéo existante en entrée | Prolongement natif | Pas d’import de personnage séparé |
| Kling multi-plan natif | Prototype simulé | Selon contrat prototype | Pas de parcours de production activé |

À afficher séparément dans le produit : référence d’identité, cadre début, cadre fin, vidéo en entrée, audio, durées, formats, résolution et niveau de vérification. « Accepte des images » n’exprime pas ces différences. « Avec audio » n’atteste pas toutes les langues parlées : par exemple la documentation Kling image restreint ses langues vocales natives ; le parcours actuel demande surtout des actions sans dialogue. Le langage visuel et la parole doivent rester des capacités distinctes.

Le catalogue porte aussi un retrait Pika au 29/10/2026. Conserver les résultats et identifiants historiques ; respecter le blocage existant, avertir clairement et demander un remplacement explicite pour les préparations futures. Aucun classement de qualité réelle de ces modèles n’a été établi par cet audit.

## Design et parcours cible

Conserver les tokens anthracite/violet, la typographie et les cartes existantes. Le changement utile est l’ordre des décisions et la visibilité de leurs effets :

1. **Intention et format** : une prise, plusieurs scènes ou une extension ; durée finale et ratio.
2. **Modèle compatible** : capacités utiles au projet, accès disponible et estimation du projet complet. Le catalogue détaillé reste accessible.
3. **Références nommées** : identité commune séparée des cadres. Un modèle peut combiner les deux ; l’interface montre seulement ses combinaisons réellement disponibles.
4. **Scénario et plans** : rédaction locale facultative, actions courtes, premier/dernier cadre de chaque plan dans une seule carte, aperçu recadrage/bandes et retrait.
5. **Devis figé** : prompts, références et nombre d’appels effectifs ; origine et portée de chaque élément ; confirmation des coûts.
6. **Résultat et publication** : correction supervisée, validation vidéo, textes par réseau, puis programmation confirmée.

La série fournit des valeurs par défaut et une identité réutilisable ; l’épisode conserve sa propre version. Éviter un second formulaire parallèle ou une nouvelle architecture frontend. Les détails techniques du fournisseur restent des informations avancées, sans imposer `@Element1` au créateur.

États proposés par occurrence : **production prévue → rédaction/génération/assemblage → vidéo à valider → textes à relire → publication à confirmer → programmée → résultat par réseau**. Un créneau visé ne doit pas avoir la même présentation qu’une publication déjà armée. Afficher l’action suivante et un rappel interne ; maintenir une actualisation manuelle et les états d’échec réels.

## Compléter titres, descriptions et hashtags

Il existe déjà des champs de préparation par réseau. Il manque une vraie proposition éditoriale adaptée à l’épisode, pas un nouveau moteur d’envoi.

**Au niveau série** : langue, ton, public visé, formulation de marque facultative, mots-clés et hashtags autorisés/exclus, appel à l’action facultatif. Les réglages de visibilité, audience, droits et déclarations restent des choix explicites ; un rédacteur ne doit pas les deviner.

**Au niveau de la version validée** : titre d’épisode, histoire/scènes finales connues et informations fournies par l’utilisateur → proposition locale structurée → aperçu par réseau → édition humaine → brouillon durable lié à cette vidéo → confirmation existante. Le moteur local textuel ne doit pas prétendre avoir regardé le résultat vidéo.

| Destination | Proposition utile | Intégration attendue |
| --- | --- | --- |
| YouTube | Titre dédié, description, hashtags pertinents ; tags YouTube éventuellement en option avancée | Titre et description distincts ; tags API séparés des hashtags écrits dans le texte |
| Instagram | Légende adaptée, hashtags modifiables, CTA facultatif | Composer le texte final du champ `caption` déjà intégré |
| TikTok | Légende courte adaptée et hashtags modifiables | Composer le texte envoyé dans `post_info.title`, utilisé comme caption par l’API |

Les [champs YouTube](https://developers.google.com/youtube/data/reference/rest/v3/videos) imposent notamment 100 caractères de titre et 5 000 octets de description. Les [exemples officiels TikTok](https://developers.tiktok.com/docs/en/content-posting-api-get-started) placent légende et hashtags dans `post_info.title`. Pour Instagram, le constat `caption` vient de l’adaptateur actuel ; la documentation Meta a répondu HTTP 429 pendant cette vérification, donc ses limites actuelles ne sont pas revérifiées ici.

Garder l’origine de chaque proposition et un indicateur « modifié par toi ». Une nouvelle génération de texte ne doit pas écraser les éditions humaines. Après réparation/montage/nouvelle validation vidéo, demander la relecture du pack ; ne pas déplacer ni modifier silencieusement les anciennes publications programmées. Pas d’invention de hashtags « tendance », de promesse de performance, de spoiler imposé ou d’allégation non fournie.

## Ce qui est solide et doit être conservé

- Propriété des fichiers privés, CSRF, révisions et verrous ; accès API/MCP distincts.
- Devis avant génération, entrées effectives visibles, récupération d’une demande incertaine sans renvoi payant automatique.
- Plans ordonnés, pause/arrêt des étapes restantes, contrôle du ratio et de la durée avant le suivant.
- Personnages entiers distincts des cadres ; protection des imports contre les réponses tardives.
- Récurrences bornées, DST et créneaux dépassés traités sans rafale de rattrapage.
- Validation vidéo et publication distinctes ; source du brouillon figée ; ancienne programmation protégée lors d’un changement de version.
- États par destination et succès partiels ; pause d’une série distincte de l’annulation d’une publication déjà confirmée.

La revue n’établit pas une certification générale de sécurité. F01 et F03 montrent précisément pourquoi il faut vérifier le contenu et l’enveloppe du consentement, même lorsque le montant reste inchangé.

## Feuille de route proposée

Ces lots sont des propositions de suite, pas une autorisation d’implémentation ou d’envoi réel.

| Ordre | Lot | Contenu | Critère de sortie sans coût vidéo |
| --- | --- | --- | --- |
| 1 | Intégrité des préparations | F01–F03 : contexte/prompts figés, priorité des cadres, empreinte récurrente complète | Modifier série ou contrat après accord ne change aucun appel accepté ; conflit A/B impossible ou explicite |
| 2 | Références et modèles cohérents | F04–F10 : contrat Kling clarifié, sujets nommés, capacités partagées, transitions et limites complètes, prévol récurrent | Matrice texte/sujet/cadres/combinaisons et anciens projets couverte ; aucun modèle proposé pour une entrée refusée |
| 3 | Éditorial supervisé | F12–F13 : profil de série, proposition locale par réseau, lien à la version validée, éditions humaines conservées | Du résultat fictif au brouillon complet : bons textes par réseau, limites validées, aucune programmation implicite |
| 4 | Reprise et suivi | F11, F15 : expiration des rédactions interrompues, états par occurrence, échéance et rappel interne | Crash simulé récupérable sans doublon payant ; action suivante compréhensible sans consulter les logs |
| 5 | Montage et finition UX | F14, F16 : recette de série limitée aux capacités existantes, libellés, retraits, tokens | Aperçu reproductible, consentements conservés, parcours bureau/mobile/clavier vérifié |
| 6 | Recette finale bornée | Contrats simulés puis un petit essai vidéo séparément confirmé | Vidéo réellement contrôlée : format, identité, action terminée, raccord/audio, textes et source de publication corrects |

L’envoi entièrement autonome resterait un chantier distinct après ces étapes : autorisation explicite, capacités réelles des plateformes et fonctionnement de l’hébergement à vérifier. Il ne faut pas le présenter comme déjà disponible.

## Tests de sortie à ajouter en priorité

1. Confirmer deux plans, modifier personnages/style de la série, puis lancer le suivant : prompt accepté inchangé.
2. Choisir A en raccourci et B dans le plan 1 ; même test sur la fin du dernier plan : priorité explicite et devis fidèle.
3. Image + fin → personnage ; changement Kling → Wan avec références existantes : aucune impasse ni ajout proposé à tort.
4. Décor vide + personnage nommé : mapping d’élément correct et entrée en scène explicitée ; premier cadre composé traité comme intention différente.
5. Ancien prompt trop long et élément Kling incompatible avec le contrat clarifié : refus local explicite avant tout POST.
6. Autorisation récurrente puis modification de contrat/version : pause avant nouvel appel ; format `auto` refusé dès le prévol.
7. Mort du worker après création d’occurrence : état durable récupérable, aucune nouvelle demande vidéo implicite.
8. Réparer/montage/revalider : texte précédent signalé, nouvelle proposition liée à la bonne source, éditions humaines préservées.
9. Trois réseaux : textes et limites distincts ; hashtags intégrés au bon champ ; brouillon non armé avant confirmation.
10. Mobile, clavier et retour navigateur : prérequis visibles, aperçu/remplacement/retrait fiables, états de production et de publication distincts.

## Repères Symfony pour la suite

Les formulaires et Stimulus recueillent une intention et montrent les capacités. Les services contrôlent compatibilité, propriété et coût. Doctrine conserve la version effectivement acceptée ; Messenger exécute cette version durable en arrière-plan. Le rédacteur local prépare du texte, les adaptateurs traduisent les entrées vers chaque fournisseur, et le calendrier ne s’arme qu’après sa propre confirmation.

Les corrections prioritaires portent sur ces frontières de responsabilité. Elles peuvent être faites dans le monolithe existant, sans React, nouveau moteur de workflow ou architecture supplémentaire.
