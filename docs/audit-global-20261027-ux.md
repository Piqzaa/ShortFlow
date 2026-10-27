# Audit UX et simplicité — 27 octobre 2026

## Périmètre et méthode

Audit d’analyse uniquement : parcours d’un compte vide vers l’import et la publication ; Studio IA, scénarios, images, plans, modèles et devis ; séries, récurrence, validation et calendrier ; comptes sociaux multiples. Lecture des sources Symfony/Twig/Stimulus, formulaires, tests existants et archives synthétiques du rédacteur. Skill `frontend-review` appliqué. Aucun changement applicatif, test de base de données, appel de modèle, activation de série, publication, commit ou push effectué par cet audit. Seul ce rapport est créé.

Le navigateur partagé est réservé à la recette de l’agent principal. Les problèmes de hiérarchie, de compréhension et de densité ci-dessous sont donc des **hypothèses UX étayées par les sources**, pas des résultats de tests utilisateurs. Les tests cités sont lus, non exécutés dans ce sous-audit. Les évaluations anciennes ne mesurent pas nécessairement la version actuelle des consignes.

Gravité : P1 = point à traiter avant de recommander largement le parcours concerné ; P2 = friction ou ambiguïté importante pour un débutant ; P3 = finition. Aucun P0 établi par cette revue UX. Les recommandations ne constituent pas une autorisation de développer un nouveau lot ou de modifier les consentements existants.

## Conclusion

Le parcours import → destinations → textes → date → récapitulatif est suffisamment balisé pour une bêta accompagnée. Les préparations durables et les confirmations distinctes constituent une base solide. Le Studio et les séries restent plus difficiles : le créateur doit comprendre simultanément modèle, fournisseur, type de référence, durée finale, durée native, plans et raccords. Une présentation plus progressive est utile ; elle ne doit jamais cacher le compte facturé, les entrées réellement envoyées, les incompatibilités ou le devis.

La rédaction locale fonctionne comme **outil de proposition supervisé**. La fiabilité sémantique permettant de déléguer sans réserve une production vidéo payante n’est pas établie. L’acceptation d’un JSON et l’absence d’alerte ne suffisent pas à qualifier un scénario ou des textes de publication de bons.

## Ce qui est déjà bien guidé et doit rester visible

| Parcours | Preuve dans les sources | Appréciation |
| --- | --- | --- |
| Première bibliothèque vide | `templates/studio/workspace.html.twig:14` : action « Importer ma première vidéo », bibliothèque privée et limites | Une prochaine action concrète, sans faux contenu de démonstration. |
| Import | `templates/library/upload.html.twig:9` et `:10` : dépôt, formats, aperçu local, rien envoyé avant le bouton ; `assets/controllers/upload_controller.js:113` : vraie progression de transfert | Le créateur peut vérifier sa source et distinguer aperçu et import. L’import reste unitaire, ce qui est une limite produit explicite, pas un défaut caché. |
| Publication | `templates/publication/composer.html.twig:9`, `:13`, `:22`, `:23` ; `templates/draft/confirm.html.twig:6` | Étapes destinations, textes et moment ; sauvegarde indépendante ; récapitulatif avant l’envoi ou l’armement de la date. |
| Comptes multiples | `templates/publication/connections.html.twig:11` à `:18` ; `templates/publication/_batch_review.html.twig:2` | Identité stable, ajout distinct de reconnexion, compte destinataire dans le récapitulatif. Ne pas revenir à un compte implicite quand plusieurs existent. |
| Modèles du Studio | `templates/ai/_recommendations.html.twig:1` ; `assets/controllers/creation_controller.js:28` | Trois recommandations et options avancées déjà repliées par le contrôleur dans le cas courant ; le catalogue n’a pas besoin d’être supprimé. |
| Rédaction | `templates/ai/_story_proposal.html.twig:5`, `:12` ; `assets/controllers/story_writer_controller.js:164` | Aperçu avant application ; alertes ; rappel que l’absence d’alerte ne prouve pas la fidélité. |
| Références | `src/Form/AiIdeaType.php:48` ; `assets/controllers/image_preparation_controller.js:34` | Texte suffisant pour commencer ; mode personnage distingué des cadres ; explication de l’outil externe pour préparer une image. |
| Récurrence | `templates/series/_recurrence.html.twig:1`, `:9`, `:13` | Création de série distincte de l’activation ; coûts bornés en nombre d’épisodes ; vidéo et publication à valider séparément. |
| Calendrier et intervention | `templates/draft/calendar.html.twig:5`, `:9`, `:10` ; `templates/studio/validation.html.twig:1` | Créneau visé distinct d’une date confirmée, agenda mobile, épisodes sans date ; étapes à terminer intégrées à l’Activité plutôt qu’un nouvel onglet principal. |
| Navigation | `templates/studio/shell.html.twig:14`, `:17`, `:19` | Six rubriques, menu mobile natif, compte et déconnexion. Le contrôle responsive réel est à compléter par l’agent principal. |

## Constats prioritaires

### UX-01 — P1 — La qualité sémantique du scénario ne justifie pas encore une recommandation générale de génération automatique

**Constat et preuve.** L’évaluation réelle documentée dans `docs/redacteur-local-fiabilite.md:29` porte sur douze réponses structurées, 39 plans. Les diagnostics finaux signalent quatre propositions, mais les erreurs non signalées existent aussi (`:34`, `:37`, `:46`). Les archives synthétiques relues confirment deux exemples : `artifacts/story-local/20261024-114127-bcbdba/recette.json` conserve un préfixe « no character or props » suivi d’un couteau et d’une assiette, sans alerte ; `pedagogie.json` termine le rempotage en fermant le pot, sans alerte. `LocalStoryWriter::review()` (`src/Service/LocalStoryWriter.php:162`) contrôle des indices lexicaux, des couleurs/accessoires et des doublons stricts, pas la vérité de l’action. En récurrence, après la pause des seuls résultats signalés, le service peut confirmer les clips (`src/Service/SeriesRecurrences.php:286`, `:298`).

**Impact novice.** « Supervisée » peut être compris comme une relecture du scénario avant toute dépense ; actuellement la supervision obligatoire porte sur la vidéo finale avant publication. Une histoire plausible en français peut déclencher des clips payants malgré une contradiction dans les prompts anglais. Le consentement aux coûts existe : le problème est la qualité attendue et l’étape exacte de supervision, pas l’absence de consentement.

**Proposition minimale.** Expliquer près de l’activation que le scénario et les clips partiront sans relecture intermédiaire et que l’utilisateur relira ensuite la vidéo. Ne pas présenter le mode comme validé sur le plan narratif. Avant de recommander cette option à des utilisateurs autonomes, refaire une évaluation des consignes actuelles avec les mêmes cas et une grille humaine : fidélité, action réalisable, fin visible, absence de contradiction entre identité et action, répétition, durée réaliste. Une étape d’approbation du scénario serait un choix produit supplémentaire, à cadrer séparément plutôt qu’à ajouter discrètement.

**Critères d’acceptation.** Un test utilisateur peut expliquer ce qui sera relu avant/après dépense ; les résultats d’évaluation distinguent forme et sens ; aucun taux de « bons scénarios » n’est déduit du seul succès structurel. Les deux cas ci-dessus font partie de la recette sémantique actuelle. Les alertes et la pause côté serveur sont conservées.

**Limite.** Ces archives datent du 24 octobre ; elles prouvent les défauts de cet échantillon, pas un taux d’erreur de la version actuelle ou des futurs modèles vidéo. L’évaluation actuelle du 27 octobre est désormais terminée et synthétisée dans [le rapport qualité](audit-global-20261027-qualite.md). Elle comporte six scénarios, 18 plans, et confirme des défauts actuels, dont le cas de dialogue détaillé en UX-12. Elle ne rejoue pas tout le corpus ancien ; les exemples des deux dates ne doivent pas être confondus.

### UX-02 — P1 — La condition de disponibilité locale est trop peu visible avant l’activation d’une récurrence

**Constat et preuve.** `docs/recurrence-supervisee.md:24` précise que service local, Docker, Ollama et traitements de fond doivent rester disponibles. Le formulaire `templates/series/_recurrence.html.twig:1` à `:13` explique date visée, limites et facturation mais ne présente pas cette condition d’exploitation. La confirmation de publication ponctuelle la mentionne avec le mot technique « worker » (`templates/draft/confirm.html.twig:9`).

**Impact novice.** Le créateur peut croire qu’un créneau enregistré sera exécuté par un service hébergé même s’il éteint son ordinateur. Il peut découvrir le créneau manqué seulement après son retour.

**Proposition minimale.** Dans l’installation locale actuelle, afficher avant le consentement une phrase concrète : l’ordinateur et les services VydFlow doivent rester en fonctionnement aux heures de préparation/envoi ; fermer uniquement l’onglet n’a pas le même effet qu’arrêter les services. Dire comment retrouver un créneau manqué. Remplacer « worker » par « service de traitement » avec un détail technique facultatif. Pour un futur hébergement, adapter cette information à la réalité, sans conserver une consigne locale trompeuse.

**Critères d’acceptation.** La condition est visible avant activation, pas seulement dans les docs ; un débutant distingue fermer la page et arrêter l’ordinateur ; aucun changement de calendrier ou activation réelle n’est nécessaire pour comprendre la règle.

### UX-03 — P2 — « Plan unique » est à la fois un choix de mode et une opération séparée sur le découpage

**Constat et preuve.** Le champ « Comment filmer cette vidéo ? » propose « Plan unique · un seul appel » (`src/Form/AiIdeaType.php:41`). Plus bas, le bouton « Préparer un plan unique » modifie réellement la préparation (`templates/ai/_preparation_plans.html.twig:7`). `assets/controllers/preparation_controller.js:39` explique qu’il faut utiliser ce bouton si le mode unique est choisi avec plusieurs plans : choisir le mode seul ne réduit pas la liste.

**Impact novice.** L’utilisateur choisit une option qui semble déjà accomplir l’action, puis découvre plusieurs scènes ou une instruction supplémentaire éloignée. Une durée finale incompatible ne doit pas être arrondie silencieusement pour résoudre cette ambiguïté.

**Proposition minimale.** Rapprocher le choix et l’action de transformation : lorsque plusieurs plans existent, afficher à côté du mode le nombre actuel et le bouton explicite permettant de préparer le plan unique, avec avertissement de conservation/remplacement des textes. Garder les durées natives et le devis comme autorités.

**Critères d’acceptation.** Avec trois plans existants, le choix « unique » indique immédiatement ce qui reste à faire ; aucune suppression de scène ni conversion de durée n’intervient sans action explicite ; la prochaine action reste près du contrôle choisi.

### UX-04 — P2 — Le Studio gagnerait à montrer la prochaine décision plutôt que tous les détails de montage dès le début

**Constat et preuve.** Sur le même écran sont présentés source, format et références (`templates/ai/studio.html.twig:9` à `:15`), idée/durée/enchaînement (`:18` à `:19`), modèles/recommandations (`:23` à `:33`), personnages, assistant puis tous les plans (`:35` à `:39`). Les plans sont ouverts par défaut (`templates/ai/_preparation_plans.html.twig:1`) et chaque plan expose prompt vidéo, durée, préparation d’image et actions d’ordre. Le catalogue et le choix avancé sont déjà partiellement progressifs avec Stimulus ; il serait inexact de dire que tout le catalogue est toujours déplié.

**Impact novice.** « Quelques mots suffisent » est suivi d’un formulaire qui demande aussi des décisions sur clips, raccords et prompts anglais avant qu’un scénario existe. Sur une longue création, l’idée et le prochain bouton peuvent être séparés par beaucoup de contenu.

**Proposition minimale.** Conserver un écran commun, avec trois ensembles lisibles : idée/durée/mode ; scénario et scènes ; récapitulatif/coût. Avant proposition, résumer le découpage plutôt que développer toutes les scènes vides ; après application, ouvrir les scènes concernées. Conserver un accès explicite à l’écriture manuelle. Replier les explications longues de fonctionnement, pas les erreurs, le modèle choisi ou les références affectées. Ne pas ajouter un assistant multi-pages sans nécessité.

**Critères d’acceptation.** Un nouveau créateur sait où saisir son idée et demander un scénario sans comprendre tout le montage ; un auteur manuel garde accès à tous les champs ; les erreurs ouvrent leurs sections ; les choix de compte, modèle, audio, coût et images réellement envoyées sont visibles au récapitulatif. À confirmer en recette responsive réelle.

### UX-05 — P2 — La création de série perd le guidage de choix du modèle disponible dans le Studio

**Constat et preuve.** `templates/series/configure.html.twig:11` affiche directement `seriesForm.model` avec source, format, connexion et durée. `src/Form/AiSeriesType.php:44` construit un choix modèle/fournisseur, sans les trois recommandations, recherche et comparaison proposées par `templates/ai/_recommendations.html.twig` et le Studio.

**Impact novice.** Une personne qui sait commencer une vidéo ponctuelle doit de nouveau décoder une grande liste de noms/résolutions pour créer une série. La configuration d’une série semble plus technique, alors qu’elle réutilise la même production.

**Proposition minimale.** Réutiliser le petit choix guidé existant ou présenter le modèle retenu avec une action « Changer » et les mêmes descriptions de capacités. Conserver les sélections explicites, les limitations propres au modèle et les anciennes préparations ; aucune sélection cachée d’un fournisseur ou tarif.

**Critères d’acceptation.** Les mêmes modes sont nommés de la même manière ; un débutant peut expliquer le modèle et le compte qui paieront ; passer à un autre modèle conserve les textes et signale les références incompatibles avant enregistrement.

### UX-06 — P2 — Le résumé français peut rester rassurant alors que le prompt anglais a été modifié

**Constat et preuve.** La description française est un champ caché (`src/Form/AiShotType.php:17`) affiché par `templates/ai/_shot_description.html.twig:2`. L’utilisateur édite le prompt vidéo, mais `assets/controllers/shot_editor_controller.js:37` réaffiche la description conservée sans la rapprocher du prompt modifié. Le texte d’aide invite déjà à vérifier la correspondance : c’est une mitigation utile, pas une synchronisation.

**Impact novice.** Une personne peu à l’aise en anglais risque de continuer à relire l’ancien résumé comme si c’était une traduction de ce qui sera envoyé. L’application ne valide pas cette équivalence.

**Proposition minimale.** Marquer le résumé « issu de la proposition initiale » et « à vérifier après modification » quand le prompt change ; proposer la correction du résumé français ou une traduction à la demande si un lot ultérieur le prévoit. Ne pas faire croire à une retraduction automatique inexistante.

**Critères d’acceptation.** Modifier l’action dans un prompt ne laisse pas un résumé présenté comme courant sans avertissement ; le devis montre le texte réellement envoyé ; le créateur peut distinguer description d’aide et prompt effectif.

### UX-07 — P2 — L’assistant de scénario ne dit pas assez directement qu’il ne voit pas les références importées

**Constat et preuve.** Les consignes serveur disent explicitement que les photos ne sont pas transmises (`src/Service/LocalStoryWriter.php:44`, `:62`). L’assistant `templates/ai/_story_proposal.html.twig:5` annonce une proposition locale, sans cette précision près du bouton. L’éditorial, lui, expose clairement cette limite (`templates/publication/_editorial.html.twig:5`).

**Impact novice.** Après avoir chargé le visage d’un personnage ou le décor, l’utilisateur peut penser que le rédacteur en déduit son apparence et sa situation. Les images guident la génération vidéo selon le modèle, mais pas l’analyse du rédacteur textuel.

**Proposition minimale.** Près de « Proposer mon scénario », préciser que le rédacteur utilise idée, noms et descriptions et ne voit pas les photos ; inviter à décrire le trait important dans le texte. Maintenir la distinction entre identité commune et composition d’un cadre.

**Critères d’acceptation.** Après import d’une image non décrite, le parcours ne suggère pas qu’elle a été analysée ; le débutant sait où fournir le contexte visuel utile ; aucune nouvelle API de vision n’est nécessaire.

### UX-08 — P2 — L’aide aux légendes ne couvre pas le parcours d’une vidéo importée

**Constat et preuve.** Tout le composant `templates/publication/_editorial.html.twig:1` dépend de `sourceEpisode`. Une vidéo importée publiée directement n’a pas cet épisode ; ses champs restent manuels. Le périmètre actuel est volontairement documenté dans `docs/editorial-supervise.md:5`, limité aux versions validées du Studio/épisodes.

**Impact novice.** Deux vidéos dans la même bibliothèque ont une aide différente, sans explication visible. Le créateur qui importe une vidéo déjà terminée peut penser avoir raté l’outil de titres/légendes.

**Proposition minimale.** Pour le lot actuel, expliquer la limite dans le préparateur d’un import. Si l’extension est retenue, demander une description factuelle volontaire de la vidéo importée et réutiliser aperçu/application par champs ; ne pas prétendre analyser l’image ou inventer des scènes.

**Critères d’acceptation.** Le créateur sait si l’aide est disponible et pourquoi ; une éventuelle proposition pour import se base sur son brief, reste modifiable et conserve les confirmations et réglages sociaux. Il s’agit d’un manque produit, pas d’une régression établie.

### UX-09 — P2 — Le retour sur la sélection multiple résume les réseaux mais pas les comptes choisis

**Constat et preuve.** L’organisation par vidéo recueille les comptes (`templates/publication/selection.html.twig:18`, `src/Form/SocialAccountsType.php:16`). Après enregistrement, les cartes de résultat listent réseau et date, sans nom de compte (`templates/publication/selection.html.twig:9`). La confirmation individuelle montre ensuite le compte (`templates/publication/_batch_review.html.twig:2`) : le garde-fou final existe.

**Impact novice.** Avec deux chaînes YouTube ou deux comptes TikTok, la vue d’ensemble ne permet pas de vérifier rapidement que les vidéos ont été réparties vers les bons profils. Le créateur doit ouvrir chaque brouillon.

**Proposition minimale.** Ajouter aux cartes enregistrées le nom lisible du compte choisi par réseau, ou « compte à choisir/indisponible ». Garder l’identité stable côté serveur. Ne pas remplacer une destination disparue par un autre compte.

**Critères d’acceptation.** Deux vidéos destinées à deux comptes du même réseau sont distinguables dès le retour à la sélection ; un compte retiré est signalé ; le récapitulatif individuel conserve sa vérification complète.

### UX-10 — P2 — Les étapes à terminer existent, mais sont peu signalées depuis l’entrée « Vidéos »

**Constat et preuve.** Le menu principal contient six rubriques, sans entrée séparée de validation (`templates/studio/shell.html.twig:14`), conformément au retour utilisateur. Les tâches sont intégrées à l’Activité, dans un bloc replié avec compteur (`templates/studio/validation.html.twig:1`). Depuis la page d’entrée, le lien « Activité » ne porte pas de compteur (`templates/studio/workspace.html.twig:6`) ; « La suite » affiche des préparations de publication et leurs problèmes, pas un résumé des vidéos générées à approuver (`:18`).

**Impact novice.** Le créateur qui revient dans « Vidéos » peut ne pas savoir qu’une production de série est terminée et attend son accord. Le calendrier enrichi permet désormais de la retrouver, mais il faut encore penser à l’ouvrir.

**Proposition minimale.** Signaler le nombre d’étapes ou la prochaine intervention sur le lien Activité existant ou dans « La suite ». Ne pas recréer un septième onglet, une notification intrusive ou un nouveau moteur de validation.

**Critères d’acceptation.** Une vidéo prête non approuvée produit un signal visible depuis l’entrée habituelle ; une approbation suivie d’un brouillon à confirmer fait évoluer le signal sans doublon ; les contenus annulés/arrêtés volontairement ne deviennent pas de fausses alertes.

### UX-11 — P2 — La préparation commune d’un épisode perd le contexte de navigation Séries

**Constat et preuve.** L’agent principal a observé dans sa recette navigateur du 27 octobre qu’un GET de préparation `/studio/series/episodes/{id}` active « Vidéos » dans la navigation basse au lieu de « Séries ». La source corrobore le mécanisme : `src/Controller/AiEpisodeController.php:63` fait un `forward()` vers `AiStudioController::index` avec `preparingEpisode`. Le shell se base sur `_route` de la requête courante (`templates/studio/shell.html.twig:4`), puis exige précisément `app_series_episode` avant d’utiliser le contexte de l’épisode (`:9`). Les autres cas retombent sur `videosActive` (`:12`). Le sous-appel ne conserve pas nécessairement la route du parcours public.

**Impact novice.** Le créateur peut croire avoir quitté sa série ou ouvrir une création indépendante, alors que le contenu est bien celui de son épisode. Un lien de retour au nom de la série existe et atténue le problème.

**Proposition minimale.** Déduire la section de navigation d’un contexte explicite de rendu ou de l’épisode effectivement préparé, même après `forward`, au lieu d’utiliser uniquement la route courante. Conserver Studio pour une création ponctuelle et Séries pour un épisode lié ; pas de nouvelle rubrique.

**Critères d’acceptation.** En lecture de préparation puis en réponse à un formulaire invalide, une série garde Séries actif et une création ponctuelle garde Studio actif ; desktop, menu mobile et barre basse sont cohérents. L’ownership et les URL ne changent pas.

**Limite.** La capture est celle de la recette de l’agent principal, pas une manipulation du navigateur par l’auteur de ce sous-rapport.

### UX-12 — P1 pour le besoin d’histoire parlante — Les consignes du rédacteur interdisent les dialogues demandés

**Constat et preuve.** `src/Service/LocalStoryWriter.php:43` demande « Aucun texte à afficher ni dialogue ». La seconde phase demande également de ne pas demander de texte, sous-titres ou dialogue (`:63`). Ce sont des instructions générales, pas une règle limitée aux modèles sans audio. L’option « Avec audio » des recommandations du Studio (`templates/ai/_recommendations.html.twig:5`) décrit la capacité de génération du modèle vidéo, pas la prise en charge d’un script parlé par le rédacteur.

**Impact novice.** Une personne qui décrit deux personnages ayant des répliques et choisit un modèle avec audio peut recevoir un scénario muet ou seulement des actions. Le parcours peut rester valide techniquement tout en échouant sur son intention créative. La présence d’audio ne garantit par ailleurs ni les mots prononcés, ni le français, ni la continuité des voix, ni la synchronisation labiale.

**Proposition minimale.** Rendre explicite la limite du rédacteur actuel pour ce besoin ; ne pas présenter « avec audio » comme un mode de dialogue contrôlé. Cadrer séparément la conservation de dialogues explicitement demandés, les capacités du modèle vidéo et les contrôles du devis. Ne pas modifier globalement les consignes ni ajouter de synthèse vocale dans ce rapport.

**Critères d’acceptation.** Une demande synthétique comportant deux répliques françaises reçoit soit une proposition qui les conserve selon un parcours effectivement pris en charge, soit une limitation visible avant tout lancement. Choisir un modèle audio ne suffit jamais à annoncer une prise en charge du dialogue. Aucun appel vidéo payant n’est requis pour caractériser la limitation textuelle.

**Résultat actuel constaté — complément du 27 octobre.** L’essai local de l’agent principal est conservé dans `artifacts/audit-global-20261027-writer/dialogue.json` : demande de deux répliques audibles en français (« Tu as trouvé mon chat ? », puis « Oui, il dort sur ta chaise ! »), trois plans de cinq secondes, réponse en 164,391 secondes. Les descriptions françaises conservent les deux répliques, mais les trois prompts effectivement préparés sont entièrement en français et ne contiennent plus ces répliques. Le premier indique seulement que les lèvres s’ouvrent pour parler ; le second remplace la réponse par un sourire et un geste. Le résultat porte `warnings: []`. C’est un échec actuel de langue attendue et de conservation de l’intention parlée, malgré un résultat structurellement accepté. Ce constat ne signifie pas que tous les scénarios ou tous les dialogues échouent.

**Pourquoi la détection ne signale rien.** `src/Service/LocalStoryWriter.php:169` exige au moins trois marqueurs français dans chaque prompt, dans une liste de dix mots. `markers()` (`:210`) compte les mots distincts (`array_unique`), pas leur fréquence. Lecture hors ligne de ce fixture : plan 1 = `ses`, `sur` ; plan 2 = `avec`, `sur` ; plan 3 = `les`, `sur`. Chaque prompt atteint deux marqueurs distincts seulement ; la répétition du préfixe français ne suffit donc pas. Le texte peut être français avec beaucoup de noms, de verbes et d’articles absents de la liste. Il n’existe ni vérification positive de l’anglais ni contrôle de conservation des répliques entre la demande, la description et les prompts. Les autres contrôles de durée, longueur, tenue ou yeux ne détectent pas cette divergence.

**Critères de correction supplémentaires.** Le fixture actuel doit être classé à relire au minimum pour langue et perte des paroles, avant un appel vidéo récurrent payant, ou aboutir à des prompts corrigés par un parcours explicitement pris en charge. Ne pas corriger uniquement en abaissant arbitrairement le seuil sur cet exemple : vérifier aussi des prompts anglais courts, des noms français et des instructions anglaises contenant volontairement les deux répliques françaises exactes. La langue des instructions du prompt et la langue des paroles sont deux données différentes. Un scénario qui conserve les répliques dans son résumé mais les retire des entrées fournisseur ne doit pas paraître conforme. Une éventuelle représentation séparée locuteur/réplique/langue est à cadrer dans un lot, pas à substituer discrètement au contrat actuel.

**Limite.** L’auteur de ce sous-rapport a seulement relu le JSON et recalculé ses marqueurs par lecture locale ; aucun appel de modèle n’a été effectué ici. Aucune vidéo n’a été générée pour ce cas : ni audibilité, ni fidélité des voix, ni synchronisation labiale, ni qualité du fournisseur vidéo ne sont évaluées.

## Évaluation des scénarios et textes : ce que les preuves permettent de dire

- **Scénarios :** dans la suite réelle archivée du 24 octobre, douze réponses sur douze ont une structure exploitable, mais cela n’équivaut pas à douze scénarios publiables. Les cas récit, tutoriel, objet et contemplatif sont présents. Des histoires de mug/exploratrice/lac sont jugées proches de la demande dans la lecture documentée ; d’autres contiennent des contradictions ou une résolution faible. Quatre propositions sont signalées par les heuristiques finales. Les latences de 26,963 à 62,488 secondes, médiane 33,693, excluent le préchargement et ne sont pas une capacité multi-utilisateur (`docs/redacteur-local-fiabilite.md:29`).
- **Limites de détection :** la revue compare certaines langues, tenues/couleurs et répétitions exactes, pas la plausibilité générale ni les répétitions proches (`src/Service/LocalStoryWriter.php:162`). Les descriptions françaises et prompts anglais peuvent être chacun fluides mais se contredire dans le détail. La mémoire bornée ne prouve pas une variété narrative suffisante.
- **Évaluation actuelle des scénarios, ajout du 27 octobre :** le [rapport qualité final](audit-global-20261027-qualite.md) documente six réponses structurées sur six, 18 plans, un essai par sujet. Sur la lecture des résultats, cinq ensembles de prompts sont en anglais ; le dialogue est en français et perd les paroles, sans alerte. Une seule proposition porte une alerte malgré d’autres défauts de fidélité ou de gestes. La médiane murale est d’environ 159 secondes, de 126 à 171 secondes selon le cas, sur ce poste et dans ce protocole. Les consignes, la charge et le préchargement ne sont pas isolés pour une comparaison causale avec le 24 octobre ; les 33,693 secondes historiques ne sont pas un délai actuel promis.
- **Textes sociaux :** les consignes demandent des faits fournis, aucune analyse visuelle prétendue, pas de hashtags « tendance », CTA seulement demandé (`src/Service/LocalStoryWriter.php:108`). Les tests fonctionnels lus utilisent un `MockHttpClient` (`tests/Functional/PublicationEditorialTest.php:44` et scénarios à partir de `:98`) : intégrité/propriété/provenance/application, pas qualité moyenne. L’agent principal a désormais terminé quatre tentatives locales réelles, détaillées dans le rapport qualité. Un seul pack est normalisé, et ce pack invente des préparatifs à partir du titre « Vacances 2026 ». Les causes des deux premiers échecs ne sont pas attribuables avec précision, faute de réponse brute. Le quatrième diagnostic capture une réponse complète Ollama (`done_reason=stop`), contenant des hashtags dans la description YouTube en plus du tableau prévu ; le normaliseur refuse correctement ce contenu. Il ne faut donc ni qualifier ce cas de sortie tronquée ni supprimer la validation pour augmenter artificiellement le taux de succès.
- **Conclusion qualité :** les sorties restent des brouillons supervisés, avec des erreurs actuelles observées. L’assistance éditoriale n’est pas encore assez fiable pour être recommandée comme production systématique de packs prêts à appliquer : un pack normalisé sur quatre essais est un résultat de cette petite recette, pas un taux d’échec général. Les nouveaux cas renforcent la priorité de qualité sans démontrer la solution (modèle, consignes ou hébergement). Aucun rendu vidéo, audio, chorégraphie ou voix n’a été testé.

**Apport de la recette responsive de l’agent principal.** Celui-ci rapporte, à 390 px, des hauteurs d’environ 3 857 px pour le Studio, 5 688 px pour une préparation d’épisode à trois plans, 3 206 px pour le préparateur de publication à deux réseaux et 2 947 px pour une nouvelle série, sans débordement horizontal sur ces pages. Ces observations corroborent la longueur des parcours, mais ne prouvent pas à elles seules un défaut d’ergonomie : la quantité d’édition utile diffère selon l’écran. Aucun nombre brut de champs n’est utilisé ici, car il inclurait contrôles cachés et éléments de formulaires techniques. Les mesures et captures originales restent sous la responsabilité de la recette principale.

Élargissement de recette proposé, sans vidéo payante : compléter la recette du 27 octobre par les sujets non rejoués du corpus ancien et ses répétitions, figer les consignes et évaluer indépendamment résumé/scène/prompt ; ajouter des packs sociaux pour vidéo scénarisée et vidéo montée différemment, avec source complète et contexte factuel suffisant. Compter séparément échecs structurels, erreurs factuelles, fidélité au profil et éditions humaines nécessaires. Le cas d’import par titre seul reste exploratoire : ce service n’est pas exposé sur un import sans épisode. Aucune nouvelle exécution n’est faite par l’auteur de ce sous-rapport.

## Compacité : ce qui peut être replié, ce qui doit rester apparent

| Garder apparent | Peut être progressif |
| --- | --- |
| Idée, durée finale, mode choisi et modèle retenu | Catalogue complet, variantes/résolutions alternatives, explications techniques longues |
| Référence affectée et incompatibilité à corriger | Vues complémentaires, préparation de consignes d’image, recadrage avancé quand non pertinent |
| Action suivante, état réel et erreurs | Instructions de montage répétées pour chaque plan, détails de prompts avant édition |
| Compte destinataire et compte facturé | Identifiant technique complet en l’absence d’homonymie, détails OAuth/API |
| Coût total estimé, nombre de clips, consentement | Détail du tarif par plan, conservé accessible au devis |
| Source/version de vidéo, visibilité/audience, date/fuseau avant confirmation | Déclarations sociales avancées si leurs valeurs sont encore résumées au récapitulatif |
| Pause et effet sur demandes déjà transmises | Historique détaillé et diagnostics techniques |

Le bon compromis est d’améliorer les regroupements et les résumés du monolithe existant, pas de masquer arbitrairement des champs avec CSS, ni de créer un nouvel éditeur/framework. Les éléments masqués car incompatibles doivent conserver leurs données historiques et donner un chemin de correction. Les erreurs serveur doivent ouvrir le bon groupe, y compris sans JavaScript.

## Ordre conseillé et limites finales

1. Clarifier l’étape de supervision et l’exploitation locale de la récurrence ; séparer la mesure de qualité de la validation de structure.
2. Rendre la prochaine action du Studio plus évidente, unifier « plan unique » et guider la sélection de modèle en série.
3. Rendre explicites les limites du rédacteur face aux photos et le caractère ancien d’un résumé après édition.
4. Améliorer le suivi dans les écrans existants : comptes dans le résumé de sélection et signal d’intervention dans Vidéos/Activité.
5. Fiabiliser puis élargir l’évaluation actuelle des textes sociaux ; cadrer ensuite l’aide éditoriale pour les imports. La présentation publique et l’aide, puis les testeurs avec les prérequis d’exploitation, relèvent de la synthèse [de l’audit principal](audit-global-20261027.md), après qualité et compacité du produit.

Ne sont pas établis ici : taux de conversion, réussite sans accompagnement, contraste mesuré, positions réelles des boutons, ergonomie tactile ou absence de débordement. Ces points relèvent de la recette navigateur de l’agent principal et de tests avec de vrais créateurs. Les contrôles de propriété, de coûts, de version et de confirmation ne doivent pas être retirés pour raccourcir les écrans.
