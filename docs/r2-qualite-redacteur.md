# R2 — Qualité des scénarios et textes : suivi

## État courant — grand R2 clôturé, 28 octobre 2026

**R2.1–R2.7 terminés**, [revue globale, décision et pédagogie](r2-revue-cloture.md). Décision humaine mise en œuvre : préparation récurrente automatique des scénarios, puis relecture et confirmation du devis de chaque vidéo. Les anciennes autorisations automatiques ne permettent plus un nouvel envoi ; résultats déjà transmis récupérables. Les limites de fidélité et de parole sont visibles avant dépense, les faits/hashtags sont à relire avant application. Aucune qualité audiovisuelle ni fiabilité narrative autonome certifiée.

Le correctif éditorial a son évaluation distincte : six essais locaux sur les trois cas éditoriaux inchangés, quatre packs normalisés en 23–42 s et deux refus `context` sans HTTP ; un récit contient encore `#chatgris` pour un chat blanc et des détails inventés. [Métadonnées, sources et captures complémentaires](fixtures/redacteur-cloture-r2.json) ; les 18 captures R2.6 et leur code 1 restent historiques, sans réécriture.

Contrôles R2.7 : **761 PHP / 8 715 assertions**, puis **96 / 824 ciblés** après clarification de texte Twig ; **152 frontend**, PHPStan/style/lints/schéma/check/build et recette synthétique desktop/mobile/clavier réussis. Aucun test exclu, migration, activation réelle, média, dépense, publication ou push. **Prochain point exact : R3.1**, parcours commun idée/scènes/récapitulatif. Commit local `fix: require reviewed generation for recurring stories`. Les états datés ci-dessous sont historiques.

## État courant — R2.6 terminé, 28 octobre 2026

Deux passages réels de Qwen3 8B local sur neuf cas synthétiques fixes : **18 essais, 28 appels HTTP, 12 scénarios normalisés, quatre timeouts éditoriaux de 180 s sans corps capturé et deux refus `context` sans HTTP**. Programme, corpus, captures versionnées, protocole, horloges, conditions et relectures dans [le bilan R2.6](r2-evaluation-redacteur.md). Les sources applicatives restent celles de R2.5 ; aucun changement de consignes entre passages. Campagne complète mais programme sorti avec le **code 1** pour les quatre packs non aboutis : ne pas annoncer 18 réussites.

Relectures indépendantes du protocole, des douze scénarios, des six essais éditoriaux et de la métrologie réalisées. Citations exactes et locuteurs conservés dans les deux dialogues ; détails ajoutés, actions/états incohérents et préfixes contenant des actions restent observés, souvent sans alerte. Mémoire balle/carton respectée dans ces deux exemples uniquement. **Aucune qualité vidéo/audio ou fiabilité autonome validée.** Les quatre délais ne prouvent pas un défaut de hashtags : leur contenu n'a pas été obtenu.

Mesures des douze scénarios : médiane murale **58,424257 s**, monotone **58,396488 s**, premier chargement natif **33,0442357 s**, écart absolu maximal des horloges par essai **0,140863 s**. Deux répétitions et un proxy d'observation ne constituent pas un benchmark de production ni une comparaison statistique avec l'audit ancien. Empreintes programme/corpus/trois services inchangées contrôlées avant archivage ; les 18 entrées correspondent au corpus, horodatages et corps exacts conservés.

Programme exécuté, `php -l`, style et mode sans appel réussis ; JSON/liens/repères/diff vérifiés. Aucune application modifiée : dernière suite complète verte historique **758 / 8 619**, frontend **152**, R2.5, non rejouée ici. Aucun build, changement de schéma, interface, service ni worker. Aucun essai image/vidéo payant, compte, envoi social ou activation ; conteneur CLI temporaire retiré automatiquement. Le maintien du PC éveillé était la seule précaution manuelle de mesure.

**Prochain point exact : R2.7**, revue de clôture du grand R2 avec traitement ou limitation explicite des écarts avant toute validation de sortie ; ne pas passer directement à R3. Commit local `test: evaluate local writer on repeated corpus`, aucun push.

## Historique — R2.5 terminé, 28 octobre 2026

Le contrôle de contexte refuse les sources sans description/scènes distinctes du titre avant verrou local et HTTP. Il protège aussi l'application d'une ancienne proposition insuffisamment décrite, sans modifier les textes déjà saisis ou appliqués. Le préparateur explique le besoin avec une aide liée au champ et un exemple ; saisie directe et application volontaire conservées. [Fonctionnement, fichiers, limites et recette](redacteur-contexte-factuel.md).

**Suite complète verte : 758 tests / 8 619 assertions**, code 0, et **129 tests ciblés / 989 assertions réussis**. Le corpus R2.1 est inchangé : tous ses 15 cas passent désormais. Dix-huit nouveaux cas ; fixtures temporelles enrichies de faits, aucune assertion existante affaiblie. **152 frontend** et syntaxe JS réussis ; PHPStan `src` + nouveau test unitaire : aucune erreur ; CS-Fixer final neuf fichiers : aucune correction. Migrations, lints conteneur/19 YAML/103 Twig et schéma test réussis. Revue indépendante sans blocage ; recette synthétique 390 × 844 / 1440 × 900 et clavier réussie, aucun débordement horizontal ou erreur console observé. Aucun build supplémentaire, assets inchangés.

La première tentative complète sur le conteneur `edit-worker` s'est interrompue avec le code **137**, sans bilan PHPUnit ; ce n'est pas une suite réussie. Ce conteneur héberge un worker limité à une heure puis redémarré. Le passage vert ci-dessus vient d'une relance dans un conteneur de test temporaire dédié, sans worker ni ce cycle de vie, copie Linux propre des sources et dépendances, garde `shortflow_test` et migrations avant tests : **7 min 16 s**, mémoire PHPUnit **84,5 Mio**. Une seule suite DB à la fois. Cache dev et `story-worker` local actualisés après garde de zéro rédaction active/en attente ou occurrence en rédaction, état `running` vérifié. Conteneurs temporaires de tests/recette retirés, viewport restauré et onglet fermé.

Sorties locales ignorées : `artifacts/r2-5-focused.log`, `r2-5-full.log` (interrompu), `r2-5-full-isolated.log` (vert), `r2-5-ui.log`, `r2-5-js.log`. Aucun appel modèle réel, image/vidéo payante, publication sociale ou activation ; aucune migration. Pas d'intervention manuelle nécessaire pour ce lot.

**Prochain point exact : R2.6**, évaluation sur corpus fixe avec répétitions et mesures ; aucune qualité réelle Qwen/audio déduite des tests simulés. Revue globale du grand R2 toujours en R2.7. Commit local `fix: require factual context for publication texts`, aucun push.

## Historique — R2.4 terminé, 28 octobre 2026

Les packs sociaux ont des consignes plus explicites (tous les réseaux/champs, limites et hashtags séparés), avec des diagnostics distincts et des actions de reprise. Le validateur reste strict : pas de réparation silencieuse, résultat partiel, troncature ni second appel automatique. Les causes externes brutes ne sont ni journalisées ni conservées dans la chaîne d'exception.

Les **47 tests ciblés / 271 assertions** passent. Ils couvrent le pack complet trois réseaux, les refus, le verrou occupé et le parcours asynchrone refus → diagnostic HTML → nouvelle demande manuelle, sans modifier les textes. Détails pédagogiques : [packs sociaux et diagnostic](packs-sociaux-diagnostic.md).

### Résultats exécutés R2.4

- Suite complète : **740 tests / 8 517 assertions, deux échecs**, code 1. Le premier attendait le mot « occupé » dans le nouveau diagnostic de concurrence : libellé corrigé sans changer le test. Le second est la reproduction de contexte pauvre R2.5, conservée sans exclusion ni affaiblissement.
- **Rejeu final après correction : 61 tests / 507 assertions réussis**, code 0, incluant tous les tests de concurrence multi-processus, les tests éditoriaux et les tests unitaires des packs/du writer. Le cas du premier échec passe avec la reprise manuelle et l'isolation propriétaire ; aucun autre défaut observé. La suite complète n'a pas été rejouée après cette correction de libellé : ne pas présenter son résultat historique de deux échecs comme un nouveau passage à un seul échec.
- **152 tests frontend** et `npm run check` réussis. PHPStan sur `src` et les nouveaux tests unitaires : aucune erreur. CS-Fixer sur cinq fichiers PHP : aucun fichier à corriger ; PHPStan et style rejoués après la dernière correction du libellé.
- Garde `shortflow_test`, migrations puis lints conteneur/YAML/Twig et schéma réussis avant les suites ; aucune suite DB concurrente. Corpus historique inchangé, aucun test existant modifié pour masquer le défaut.

Sorties ignorées : `artifacts/r2-4-focused.log`, `r2-4-full.log`, `r2-4-final.log`. Pour rejouer les contrôles finaux : `php bin/phpunit --filter 'PublicationPackDiagnosisTest|PublicationTextsTest|PublicationEditorialTest|LocalStoryWriterTest|StoryProposalConcurrencyTest'` après garde de base et migrations. La suite par défaut reste rouge jusqu'au traitement de R2.5.

Revue indépendante : risque de fuite dans la chaîne d'exception externe corrigé et couvert, puis aucun autre blocage. Cache dev actualisé et `story-worker` redémarré après garde d'inactivité (zéro rédaction/occurrence en cours), état `running` confirmé. Aucune migration, aucun appel modèle réel ou essai payant/social. Aucun nouveau build ou recette navigateur, frontend inchangé ; scénario HTML couvert en fonctionnel.

**Prochain point exact : R2.5**, contexte factuel suffisant et refus d'inventer depuis un titre seul. Ce dernier cas reste volontairement rouge. R2 reste en cours ; revue globale en R2.7. Commit local `fix: clarify editorial pack failures`, aucun push.

## Historique — R2.3 terminé, 28 octobre 2026

**Prochain point exact : R2.4**, fiabiliser les packs sociaux complets et leur diagnostic sans relâcher la validation des hashtags. Le contexte pauvre reste ensuite en R2.5. R2 demeure en cours et aura sa revue globale en R2.7.

Les trois rouges scénario de R2.1 sont désormais verts, sans modifier le corpus ni leurs assertions. Le [correctif de langue/fidélité](redacteur-langue-fidelite.md) précise les consignes, distingue citations et directions, contrôle les répliques et conserve une alerte humaine sur les locuteurs/capacités. Il n'annonce pas de rendu parlé validé ni de script structuré entièrement pris en charge.

### Fichiers et garanties de R2.3

`StoryTextChecks` contient les contrôles purs ; `LocalStoryWriter` l'utilise pour préparer les repères de citations et relire les sorties. Ses deux appels HTTP restent locaux et inchangés en nombre/délais. `SeriesRecurrences` garde sa pause avant coût et enregistre un résumé complet borné à 255 caractères. `StoryTextChecksTest` ajoute 27 cas ; `SeriesRecurrencesTest` prouve la pause d'une demande de parole avant toute génération créée/appel vidéo et la conservation des plans éditables.

Les textes d'origine ne sont pas réécrits par la relecture ; les répétitions explicitement demandées restent conservées. Citations inconnues, modifiées ou non fermées ne masquent pas les consignes françaises. Absence, fragmentation, répétition excédentaire et ordre des répliques sont distingués. Les tenues nommées et contradictions d'actions sont limitées à des formes explicites, avec des témoins de non-régression.

La présence des mots ne certifie pas leur locuteur ni l'audio. Une parole identifiable reçoit une alerte de relecture même si ses mots sont présents ; en récurrence, elle passe donc par une pause et une reprise manuelle avant vidéo. Les préparations anciennes ne sont pas modifiées et leurs résultats/alertes enregistrés ne sont pas recalculés automatiquement. Voir les limites et le flux Symfony dans [la documentation du correctif](redacteur-langue-fidelite.md).

### Résultats R2.3 exécutés

| Contrôle | Résultat |
| --- | --- |
| Suite PHP complète | **720 tests / 8 419 assertions, 719 réussites et un échec attendu**, code 1 : invention depuis le seul titre, prévu en R2.5 |
| Rejeu ciblé final writer/contrôles/API/récurrence | **57 tests / 379 assertions réussis**, code 0 |
| Reproductions R2.1 rejouées séparément | **15 cas / 90 assertions : 14 réussites, seul le contexte pauvre encore rouge** ; aucun test exclu ni oracle affaibli |
| Frontend et syntaxe JS | **152 tests réussis**, `npm run check` réussi |
| PHPStan niveau 5 sur `src` et les nouveaux tests unitaires | Aucune erreur |
| CS-Fixer final, cinq fichiers PHP | Aucun fichier à corriger |
| Lints conteneur/YAML/Twig et schéma test | Réussis ; 19 YAML, 103 Twig, mapping et base synchronisés |
| Installation de test | `shortflow_test` vérifiée, migrations à jour avant les suites, aucune suite DB concurrente |
| Application locale | Cache dev actualisé, worker `story-worker` redémarré après garde : zéro rédaction active/en attente et zéro occurrence en rédaction ; état `running` vérifié |
| Documentation | Liens locaux, identifiants roadmap et `git diff --check` vérifiés avant commit |

Revue indépendante ciblée sans blocage : ajout d'un contrôle de répétitions excédentaires et de ses témoins, sans supprimer les répétitions demandées. Le suivi récurrent tronquait auparavant des alertes longues au milieu d'un mot : il affiche maintenant leur nombre et une première indication complète, ou une consigne générale complète si nécessaire.

Aucun appel modèle réel, vidéo/image payante, publication sociale ou activation réelle n'a été lancé. Pas de nouvelle recette navigateur ni de build : templates et assets inchangés, contrôleur d'affichage des alertes couvert par les tests frontend rejoués ; la recette visuelle R2.2 reste historique. La qualité des nouvelles consignes sur Qwen réel reste à évaluer en R2.6, et celle des voix en R7 avec autorisation. Aucune migration.

Sorties locales ignorées : `artifacts/r2-3-full.log`, `r2-3-final-focused.log`, `r2-3-phpstan-final.log`, `r2-3-style-check.log`, `r2-3-ui-tests.log`, `r2-3-js-check.log`, `r2-3-refresh.log`. Pour rejouer après garde/migration de `shortflow_test` : `php bin/phpunit --filter 'StoryTextChecksTest|LocalStoryWriterTest|SeriesRecurrencesTest|AiStoryProposalTest'`, puis `php bin/phpunit tests/Unit/WriterQualityRegressionTest.php` (un rouge attendu). La suite par défaut reste rouge jusqu'au traitement de R2.5 ; ne pas masquer ce cas.

## Historique — R2.2 terminé

**Prochain point exact : R2.3**, corrections de langue/fidélité, citations françaises intentionnelles et alertes honnêtes. R2 reste en cours ; la prise en charge structurée et la qualité réelle des paroles ne sont pas annoncées comme livrées. Les quatre échecs R2.1 restent présents, sans exclusion.

Suite complète exécutée : **692 tests / 8 318 assertions, 688 réussites et uniquement les quatre échecs R2.1 connus**, code 1. Les **152 tests frontend** passent. Le dernier socle entièrement vert reste R1 ; ne pas présenter ce passage R2 comme une suite verte.

### R2.2 — Contrat et information avant coût

Le [contrat des scènes parlantes](scenes-parlantes-contrat.md) définit plan, locuteur stable, texte exact, langue et ordre, ainsi que la capacité du chemin fournisseur réellement intégré. Il distingue explicitement le comportement attendu de son portage futur : **aucun champ de dialogue structuré n'est ajouté ou annoncé comme fonctionnel**. Les quatre rouges R2.1 restent à corriger.

Une note commune informe avant la proposition de scénario, les confirmations individuelles `prepared`/`waiting_key`, le devis Studio/Séries, le remplacement d'un plan, la reprise après pause et l'autorisation récurrente. Elle distingue modèle sans audio, audio sans paroles garanties et son des clips coupé au montage. La variante récurrente explique que les futurs épisodes sont écrits puis générés sans relecture intermédiaire ; elle oriente vers la préparation manuelle si les mots exacts sont essentiels.

### Fichiers et flux

| Fichier | Rôle |
| --- | --- |
| `templates/ai/_speech_notice.html.twig` | Texte commun visible sans JS ; variantes audio, montage muet et récurrence |
| `templates/ai/_story_proposal.html.twig` | Information avant de demander un scénario, dans la préparation commune |
| `templates/ai/generation.html.twig` | Information avant coût individuel et reprise de connexion, extensions incluses |
| `templates/series/episode.html.twig` | Information au devis initial/remplacement et à la reprise des étapes en pause |
| `templates/series/_recurrence.html.twig` | Information visible hors panneau replié, avant toute autorisation de coûts récurrents |
| `tests/Functional/AiSpeechNoticeTest.php` | Dix cas sur les routes réelles avec API interdites : présence/ordre/visibilité, devis et texte inchangés, aucun message créé |
| `tests/Functional/AiEpisodeFlowTest.php` | Ajout d'une vérification sur le devis de remplacement d'un clip existant |

Le contrôleur fournit les mêmes données privées → Twig réutilise une seule note avec des variables explicites et `only` → les formulaires d'origine gardent leurs tokens, choix et confirmations → les services valident les mêmes prompts/devis. Aucun service métier, catalogue, tarif, prompt, modèle, hash de consentement ou schéma n'est modifié. La note informe ; elle ne constitue ni un nouveau blocage serveur ni une preuve de paroles fidèles.

### Recette et limites

Pages fraîchement rendues sur `shortflow_test` avec propriétaire et clés synthétiques, API interdites, puis servies sur loopback par un routeur refusant les POST. Aucun lancement vidéo/récurrent/social effectué.

- Desktop **1440 × 900**, mobile **390 × 844** : Studio, devis avec clips muets, génération sans audio et récurrence ; notices visibles, texte lisible et aucun débordement horizontal mesuré sur ces vues.
- Navigation au clavier après le bouton de confirmation : focus accessible sur la commande suivante ; aucune soumission du formulaire. Console de recette : aucune erreur/alerte relevée.
- Récurrence : note visible alors que sa configuration reste repliée, sans invitation impossible à relire les prompts futurs avant qu'ils existent.
- Revue indépendante : la formulation propre à la récurrence a été corrigée ; dernière relecture sans blocage. La revue globale de tout R2 demeure en R2.7.
- Onglet créé fermé, viewport remis à sa taille normale, serveur temporaire retiré et cache dev actualisé pour les templates.

### Contrôles R2.2 exécutés

| Contrôle | Résultat |
| --- | --- |
| Premier passage ciblé des dix cas de notice | 10 tests / 176 assertions réussis ; les assertions spécifiques de formulation récurrente ont ensuite été ajoutées et vérifiées dans la suite complète |
| Rejeu ciblé final des notices et du remplacement | **11 tests / 225 assertions réussis**, code 0, sur les fichiers finaux |
| Suite PHP complète, aucun test exclu | 692 tests / 8 318 assertions, quatre échecs connus de `WriterQualityRegressionTest` uniquement ; aucun nouveau défaut |
| Suite frontend et syntaxe JS | 152 tests réussis ; `npm run check` réussi |
| Build Vite | Réussi ; avertissement connu sur le chunk Three.js > 500 ko |
| PHPStan ciblé sur `AiSpeechNoticeTest` | Aucune erreur |
| CS-Fixer final sur les deux fichiers PHP modifiés | Aucun fichier à corriger |
| Conteneur, YAML, Twig et Doctrine test | Réussis : 19 YAML, 103 Twig, schéma synchronisé |
| Migration/base | `shortflow_test` vérifiée avant PHPUnit, migration `Version20261027160000` déjà à jour |
| Cache dev | Actualisé ; aucune migration ni redémarrage de worker nécessaire pour les textes Twig |

Les quatre échecs sont exactement les alertes de langue absentes, répliques perdues, faux positif de citation dense et invention depuis titre pauvre de R2.1. Aucun test de reproduction ou corpus historique modifié. Les sorties locales ignorées sont `artifacts/r2-2-full.log`, `r2-2-focused.log`, `r2-2-final-focused.log`, `r2-2-ui-tests.log`, `r2-2-js-check.log`, `r2-2-build.log`, `r2-2-phpstan.log`, `r2-2-style-final.log` et `r2-2-dev-cache.log` ; les tests et résultats sont versionnés dans ce suivi.

Ce n'est pas une validation du rendu parlé. Aucun essai payant nécessaire pour cette étape. Une évaluation réelle des voix/répliques nécessitera son autorisation propre, au lot prévu. Pour rejouer les notices : après vérification/migration de `shortflow_test`, `php bin/phpunit tests/Functional/AiSpeechNoticeTest.php` et le cas `AiEpisodeFlowTest::testRepairOnlyRegeneratesChosenShotAndInvalidatesApproval`. Ne pas exécuter d'autres suites DB simultanément.

## Historique — R2.1 terminé en phase rouge

**R2 est en cours, pas livré.** R2.1 ajoute les reproductions automatisées ; il ne corrige pas encore le rédacteur. **15 cas / 90 assertions : 11 réussites et 4 échecs attendus**, code de sortie 1. Les quatre rouges sont des défauts applicatifs déjà capturés, pas des pannes d'installation. Aucun test exclu, ignoré ou marqué artificiellement vert. La suite par défaut inclut ce fichier et reste rouge jusqu'aux corrections correspondantes.

**Prochain point exact : R2.2**, contrat des scènes parlantes et capacité du chemin vidéo avant coût. Les corrections de langue/citations relèvent ensuite de R2.3, les packs sociaux de R2.4 et le contexte factuel de R2.5. Ne pas retirer les assertions pour passer ces étapes.

## Objectif et responsabilités

`tests/Unit/WriterQualityRegressionTest.php` est le seul fichier de code ajouté. Il utilise les services réels `LocalStoryWriter::review()`, `LocalStoryWriter::publication()` et `PublicationTexts::normalize()`, sans modifier leur fonctionnement. Le [corpus historique](fixtures/redacteur-audit-20261027.json) et le [rapport qualité](audit-global-20261027-qualite.md) sont conservés intacts.

| Composant | Rôle dans le test |
| --- | --- |
| `LocalStoryWriter::review()` | Relecture hors ligne des sorties sauvegardées ; moteur désactivé et client HTTP qui échoue si un appel est tenté |
| `PublicationTexts::normalize()` | Vraie validation du pack : réseaux, champs et séparation des hashtags ; elle ne vérifie pas la vérité des faits |
| `LocalStoryWriter::publication()` | Rejoue une enveloppe synthétique/capturée via `MockHttpClient` ; aucune connexion Ollama ni autre API |
| Nouveau test | Charge le corpus, distingue captures brutes et résultats normalisés, ajoute des témoins synthétiques et vérifie les protections attendues |
| README, AGENTS et roadmap | Repère de reprise, cases, état rouge explicite et prochaine tâche |

Flux : fixture versionnée → service réel avec dépendances remplacées → assertion ciblée → comparaison au comportement attendu. Aucune base n'est interrogée par ces tests unitaires. La préparation du lancement a cependant vérifié `shortflow_test` et appliqué les migrations avant PHPUnit, conformément au protocole du projet.

## Contrats et résultats finaux

| Cas | Résultat actuel | Attendu / suite |
| --- | --- | --- |
| Six scénarios sauvegardés | Six réussites : durées présentes, scènes non vides et relecture sans mutation ni HTTP ; pas d'alerte de paroles systématique sur les cinq cas sans dialogue | Préserver les préparations ; ces témoins ne certifient pas leur sens |
| Dialogue capturé, consignes vidéo françaises | **Rouge** : aucune alerte de langue | Alerte visant les prompts, distincte d'une alerte de paroles ; R2.3 |
| Deux répliques présentes dans l'idée/descriptions mais absentes des prompts | **Rouge** : aucune alerte de paroles perdues | Signaler le défaut avant coût ; contrat R2.2 puis fidélité R2.3 |
| Consignes anglaises et courte citation française historique | Réussite | Ne pas interdire les citations intentionnelles |
| Consignes anglaises et citation synthétique contenant plusieurs marqueurs français | **Rouge** : fausse alerte de langue | Distinguer citation parlée et direction technique ; R2.3 |
| Vraie réponse brute du diagnostic mug, hashtags dans la description | Deux réussites : refus par le normaliseur et par le rédacteur, un seul appel simulé | Conserver le validateur et l'absence de relance implicite ; R2.4 |
| Pack « Vacances 2026 » dérivé : conformité structurelle | Réussite : le pack est accepté par la validation de structure et reconstitue exactement la capture normalisée | La forme valide ne prouve pas la fidélité |
| Même pack inventé depuis le seul titre | **Rouge** : préparation/projets/absence de plan acceptés sans contexte | Refus actionnable pour manque d'informations ou sortie strictement neutre ; R2.5 |
| Pack témoin littéral neutre depuis le même titre | Réussite | Autoriser un texte neutre ou une demande de contexte, sans imposer l'invention |

Les alertes de langue et de paroles sont testées séparément. Le test de langue ne se contente pas d'un tableau d'alertes non vide : il exige une indication concernant les prompts et leur langue. Le témoin de citation dense évite un faux succès dû à la brièveté de la citation historique.

## Provenance et limites de sens

Le cas historique `dialogue.result` est utilisé tel quel pour la relecture ; il n'est pas présenté comme une réponse brute des deux appels du modèle, qui n'a pas été conservée. Les témoins de citations sont explicitement synthétiques. Seul `diagnosticResponse.message.content` fournit ici une vraie réponse éditoriale brute capturée et directement rejouable.

`text-import-minimal.result` contient déjà les hashtags concaténés par l'application. Le rejouer directement comme réponse brute ferait échouer sur des champs manquants/hashtags et masquerait l'invention. Le helper de test sépare donc le suffixe de hashtags dans une **fixture dérivée**, tout en conservant les phrases inventées. Un test de roundtrip prouve que sa normalisation redonne exactement le résultat capturé ; cette reconstruction ne prétend pas retrouver la réponse brute originale.

Pour le titre pauvre, l'oracle fini accepte un refus `PublicationException` expliquant le besoin de contexte, ou un pack complet dont les textes sont uniquement `Vacances 2026`, `Vidéo : Vacances 2026` ou `À découvrir : Vacances 2026`, et les hashtags uniquement issus de ce titre. Ce sont des variantes neutres de contrôle pour **ce cas précis**, pas un style éditorial général imposé au produit. Une adaptation future de cet oracle doit être justifiée par le contrat R2.5, sans réintroduire les faits inventés historiques. Les tableaux réseaux vides ne passent pas le test ; tous les champs sont requis.

Les tests ne déduisent pas la vérité de quelques mots-clés absents et ne prétendent pas comprendre tous les textes. Ils ne certifient ni fidélité narrative générale, ni mouvement filmable, ni voix, lipsync ou parole audible. L'évaluation humaine du corpus et ses répétitions restent en R2.6. Le cas d'import titre seul demeure exploratoire au niveau du service : R2.1 n'expose pas une nouvelle aide éditoriale dans l'interface des imports.

## Vérifications exécutées

| Contrôle | Résultat |
| --- | --- |
| Nouveau test, fichier final | **15 cas / 90 assertions, 11 réussites et 4 échecs attendus**, code 1 |
| Tests existants `LocalStoryWriterTest` et `PublicationTextsTest` | **17 tests / 60 assertions réussis**, code 0 |
| PHPStan sur le nouveau test | Aucune erreur |
| CS-Fixer final `--dry-run --diff` | Aucun fichier à corriger |
| Liens locaux, identifiants roadmap et `git diff --check` | Vérifiés avant commit |
| Revue indépendante ciblée | Contrats/provenance relus ; oracle de champs et témoin sans dialogue renforcés, aucun blocage restant dans cette étape de reproduction |

Base vérifiée `shortflow_test`, migration déjà à jour `Version20261027160000`, aucune migration ajoutée. Exécution dans la copie Linux temporaire du projet d'`edit-worker`, avec la fixture versionnée copiée également ; aucune suite DB concurrente. Aucun appel modèle réel, téléchargement, texte payant, vidéo, image, réseau social, récurrence ou changement des données de développement.

Pas de nouvelle suite complète, build ou recette navigateur : aucun code d'application, asset ou template ne change. **Dernière suite complète verte : R1, 667 tests PHP / 8 044 assertions et 152 frontend.** Ce résultat historique n'est pas une validation verte de R2.1. Les journaux locaux ignorés sont `artifacts/r2-1-final.log`, `r2-1-phpstan-final.log` et `r2-1-style-final.log` ; cette documentation et les tests suffisent à reprendre sans eux.

## Reproduire sur un autre PC

Installer selon le README, vérifier/migrer `shortflow_test` avant PHPUnit, puis :

```sh
docker compose exec -T -e APP_ENV=test -e APP_DEBUG=0 php php bin/phpunit tests/Unit/WriterQualityRegressionTest.php
docker compose exec -T -e APP_ENV=test -e APP_DEBUG=0 php php bin/phpunit --filter 'LocalStoryWriterTest|PublicationTextsTest' tests/Unit
docker compose exec -T php php vendor/bin/phpstan analyse tests/Unit/WriterQualityRegressionTest.php --no-progress --memory-limit=256M
docker compose exec -T php php vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.dist.php tests/Unit/WriterQualityRegressionTest.php
```

La première commande échoue actuellement sur les quatre protections manquantes ; les autres passent. Ollama et les crédits fournisseur ne sont pas nécessaires.

## À comprendre en Symfony et PHPUnit

Un test unitaire peut appeler un vrai service Symfony directement, sans kernel, Doctrine ni worker. Remplacer seulement son client HTTP permet de rejouer une mauvaise sortie de façon déterministe. Cela prouve le comportement du code face à cette sortie, pas ce qu'un modèle produira lors d'un autre appel. La validation de structure protège les champs ; l'alerte métier protège une intention spécifique ; la relecture humaine reste nécessaire pour juger le sens global.

La phase rouge écrit d'abord le comportement attendu, puis les prochains points le rendent vert en corrigeant l'application. La suite n'est pas rendue verte en supprimant les tests, en désactivant le validateur de hashtags ou en ajoutant une alerte universelle. Le grand lot R2 aura sa revue globale après ses corrections et son évaluation, en R2.7.
