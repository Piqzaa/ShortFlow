# Rédacteur local : fidélité, relecture et évaluation — 24 octobre 2026

## Objectif et résultat

Améliorer le rédacteur Qwen3 8B existant avant toute autonomie éditoriale : respecter le format demandé, garder une identité visuelle commune et faire apparaître les problèmes détectables avant une génération vidéo. Le lot inclut la remise en route locale décrite dans [la première recette](redacteur-local-recette.md).

Les consignes séparent désormais un récit, un tutoriel et une présentation contemplative. Un tutoriel ne reçoit plus systématiquement une chute humoristique ou un héros animal. La première étape écrit en français ; la seconde reçoit des consignes en anglais et traduit l'identité avant de préparer les plans. Les durées sont fournies dès l'écriture du scénario. Un clip unique doit conserver le début et la fin demandés, dans une phrase courte.

Le serveur préfixe chaque prompt par la même identité traduite. Il conserve les contrôles de durée, ordre, doublon strict et réponse complète. L'identité est limitée à 300 caractères, l'action à 750 : le prompt assemblé reste sous la limite vidéo de 1100 caractères. Cette répétition stabilise le texte transmis à chaque plan ; l'identité proposée par le LLM peut encore contenir des caractéristiques inventées et la cohérence d'une vidéo réelle n'est pas garantie.

## Contrôles et parcours utilisateur

`LocalStoryWriter::review()` produit des **alertes de relecture**, sans API ni accès à la base :

- Présence de plusieurs marqueurs fréquents de français dans un prompt ou d'anglais dans le scénario français. Ce sont des indices lexicaux, pas un détecteur universel de langue.
- Mention de certaines tenues ou accessoires absents du sujet, du thème, des personnages et du style fournis. Les négations simples ou coordonnées, comme « no clothing or collar », sont retirées pour cette comparaison. Une mention positive contradictoire reste détectable. Le verbe français « suit » n'est pas traité comme un costume anglais.
- Certaines couleurs d'yeux ajoutées ou changées, avec correspondances françaises/anglaises. Les autres traits visuels et les formulations non reconnues ne sont pas entièrement contrôlés.
- Description approchant la limite de 300 caractères sans ponctuation finale : risque de phrase coupée.
- Résumé identique à un résumé précédent, après normalisation de la casse et de la ponctuation. Les répétitions sémantiques proches ne sont pas détectées.

Dans le studio, les alertes restent attachées à la proposition privée et sont affichées en texte simple avant les scènes. L'utilisateur peut relire, modifier après application ou demander une nouvelle proposition ; aucune relance automatique n'est ajoutée. Les propositions anciennes sans champ `warnings` restent lisibles. L'absence d'alerte est accompagnée d'un rappel de relecture : elle ne prouve pas la fidélité ou l'exactitude du contenu.

En récurrence, une proposition signalée est conservée comme préparation modifiable de l'épisode, avec son résumé. L'occurrence passe en attention et la récurrence en pause **avant réservation du budget et confirmation vidéo**. Le créneau déjà commencé reste consommé conformément au fonctionnement existant, mais aucun montant de génération n'est réservé. Le propriétaire peut ouvrir l'épisode et reprendre manuellement avec les confirmations habituelles. Les prochains réveils devenus périmés ne relancent pas la production. Aucun consentement de publication n'est créé.

## Évaluation réelle et limites

Même matériel que la première recette : i5-9300HF, 16 Go de RAM, RTX 2060 6 Go, Ollama 0.40.6 et Qwen3 8B. Dix sujets synthétiques et deux répétitions des premiers sujets, après préchargement explicite. Les consignes du modèle sont restées identiques pendant ces douze essais. Les contrôles ont ensuite été affinés sur les défauts observés et recalculés **hors ligne** sur les sorties conservées, sans modifier les réponses originales.

Résultats dans le dossier ignoré `artifacts/story-local/20261024-114127-bcbdba/` : **12 réponses structurées sur 12**, 39 plans. Les textes de scénario sont en français et les prompts en anglais dans cet échantillon. Les durées vont de **26,963 à 62,488 secondes**, médiane **33,693 secondes**, préchargement exclu. L'écart entre horloge murale et monotone est nul dans les douze relevés. Ce n'est pas une mesure de capacité multi-utilisateur, ni une comparaison isolée avec les résultats de l'ancien poste.

| Sujet | Durée | Lecture humaine et diagnostics finaux |
| --- | --- | --- |
| Milo | 41,295 s | Couleur d'yeux inventée signalée ; plusieurs objets et progression narrative encore faible |
| Rempotage | 32,951 s | Pas de chat inventé ; « fermer le pot » reste une erreur de fond que les contrôles ne repèrent pas |
| Robot et reflet | 42,130 s | Langue et préfixe commun respectés ; reflet vivant et résolution encore faibles, sans alerte automatique |
| Milo avec mémoire | 30,792 s | Balle et carton évités dans cet exemple ; couleur d'yeux inventée signalée, fin peu marquée |
| Tartine | 36,409 s | Format pédagogique respecté ; le préfixe « no props » contredit les objets de cuisine, à corriger manuellement |
| Mug | 29,119 s | Sujet et cadrages proches de la demande, sans personnage ajouté |
| Exploratrice | 34,025 s | Veste verte conservée et fin contemplative proche de la demande |
| Lac | 33,361 s | Lieu et progression visuelle proches de la demande ; continuité vidéo réelle non testée |
| Clip unique | 27,818 s | Phrase coupée et fin demandée perdue ; alerte de coupure ajoutée après observation |
| Avion en papier, 8 plans / 60 s | 62,488 s | Structure et durées respectées ; gestes de pliage à vérifier, résultat physique non testé |
| Milo, répétition | 35,737 s | Couleur d'yeux différente et inventée signalée ; variété narrative limitée |
| Rempotage, répétition | 26,963 s | Pas de personnage animal ; fin avec ajout de terre et arrosage plus fidèle au sujet |

Les diagnostics finaux signalent **4 propositions sur 12** : trois ajouts de couleur d'yeux et une coupure de description. Les alertes initiales sur les négations de collier ont été corrigées et ne sont pas comptées comme défauts réels. `review.json` conserve le recalcul et l'empreinte SHA-256 du fichier de contrôle utilisé. Une proposition non signalée peut encore contenir une erreur factuelle ou narrative, comme le montrent plusieurs lignes du tableau.

Après correction de la consigne propre au clip unique, une rédaction supplémentaire avec le même sujet a abouti : **23,332 secondes**, phrase complète, chat qui se couche et ferme les yeux, aucune alerte. Le résultat est conservé dans `artifacts/story-local/clip-unique-correction-20261024.json`. Ce dernier essai technique, effectué pendant les vérifications PHP, ne fait pas partie de la série de mesures ci-dessus et ne démontre pas à lui seul une amélioration statistique.

Le progrès observé porte surtout sur la langue des prompts et le respect général du format. La relecture humaine reste nécessaire pour les actions plausibles, l'exactitude des tutoriels et les chutes. Aucun modèle supplémentaire n'a été téléchargé dans ce lot. Une comparaison ultérieure avec un autre modèle devra conserver les mêmes sujets et critères.

## Fichiers, responsabilités et Symfony

| Composant | Responsabilité |
| --- | --- |
| `src/Service/LocalStoryWriter.php` | Deux appels locaux, validation structurelle, préfixe commun et diagnostics de relecture purs |
| `src/Service/SeriesRecurrences.php` | Préparation durable puis pause des propositions signalées avant toute génération payante |
| `templates/ai/_story_proposal.html.twig` | Explication des formats et liste accessible des alertes |
| `assets/controllers/story_writer_controller.js` | Affichage en `textContent`, restauration, application volontaire et compatibilité des anciennes propositions |
| `scripts/story-local-cases.php` | Dix sujets synthétiques reproductibles, dont mémoire, clip unique et huit plans |
| `scripts/try-story-local.php` | Exécution séquentielle, répétitions bornées, préchargement optionnel, archivage et relevés d'horloges |
| `scripts/review-story-local.php` | Recalcul des alertes sur une archive synthétique, sans inférence ; réponses originales conservées |
| Tests PHP et frontend concernés | Isolation, absence d'appels payants, pause, persistance des alertes, diagnostics et rendu sûr |

Flux : contrôleur privé/CSRF → `StoryProposals` et message Messenger → worker de rédaction → histoire française → identité/prompts anglais → contrôle PHP et préfixe commun → résultat JSON conservé par Doctrine → aperçu Stimulus et application volontaire. La récurrence réutilise le même service et contrôle les alertes avant la confirmation du moteur vidéo.

À comprendre en Symfony : un nouveau champ dans le JSON de résultat existant ne demande pas une colonne Doctrine supplémentaire. Le service PHP reste l'autorité pour la pause ; masquer ou modifier un message côté navigateur ne peut pas déclencher la génération récurrente. Les tests utilisent le même code métier avec des clients HTTP simulés et exclusivement `shortflow_test`. La recette du modèle réel passe directement par le service de rédaction et ne constitue pas une publication ou une activation de série.

## Reproduire

Avec Ollama et Qwen3 déjà installés, Docker actif et les dépendances du projet disponibles :

```powershell
docker compose exec -T php php scripts/try-story-local.php qwen3:8b --suite --repeat --warm
docker compose exec -T php php scripts/try-story-local.php qwen3:8b --case=clip-unique --warm
docker compose exec -T php php scripts/review-story-local.php artifacts/story-local/20261024-114127-bcbdba
```

`--suite` choisit les dix sujets ; `--repeat` ajoute une répétition des deux premiers sujets sélectionnés ; `--case=identifiant` sélectionne un seul sujet connu ; `--warm` charge le modèle avant les mesures. Le préchargement fait une requête locale sans prompt. Les commandes ne téléchargent pas de modèle et ne contactent aucun fournisseur vidéo. Le code de sortie vaut 1 si une rédaction échoue ; les alertes ne sont pas assimilées à des erreurs de transport.

Vérifications finales et recette visuelle : voir [verification.md](verification.md). Aucun appel vidéo payant, publication réelle, activation de série ou push. Le commit demandé est local.
