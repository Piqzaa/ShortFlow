# Studio et séries : corrections et prochaine chaîne récurrente — 24 octobre 2026

## Ce qui est corrigé

### Un clip ou plusieurs clips

Un clip unique ne présente plus le choix d’enchaînement. Dès qu’il y a plusieurs clips, le champ contient exactement « Plusieurs scènes » et « Prolonger l’action », sans choix vide. Le prolongement est sélectionnable ; s’il manque un modèle image ou sa référence, une aide et un bouton permettent de corriger le point de départ sur le même écran. Le serveur conserve les contrôles de compatibilité avant toute dépense. Pour un clip unique, la continuité est normalisée à indépendante : aucun clip suivant n’existe à raccorder.

Le prolongement utilise la dernière image du clip précédent. Il ne constitue pas une extension vidéo native et ne garantit pas un plan continu ni une identité parfaite du personnage.

### Rédaction sans blocage de navigation

La rédaction prenait environ 80 secondes sur le poste lors de l’essai 8B. La route exécutait l’inférence pendant une requête HTTP. Le serveur PHP local intégré traite une requête à la fois : libérer la session ne suffisait pas pour rendre une autre page disponible.

La route enregistre maintenant une demande privée, renvoie HTTP 202 puis laisse le worker de rédaction effectuer les deux appels locaux. Le navigateur consulte son état par de petites requêtes séparées : attente, invention de l’histoire, rédaction des prompts, résultat ou échec. Un indicateur animé compatible reduced motion, l’étape et le temps écoulé rendent l’attente visible. L’indicateur exprime une étape, pas un pourcentage de calcul mesuré.

On peut changer de page. Au retour sur la même préparation, sa proposition est retrouvée. Un ancien résultat correspondant à d’autres réglages est ignoré lors du chargement automatique ; une modification pendant une demande en cours empêche son application. Les scènes ne sont toujours remplacées que sur clic explicite. Quitter la page arrête les requêtes du navigateur, pas la rédaction déjà enregistrée.

La demande est isolée par propriétaire. Une seule rédaction est active par compte ; une soumission répétée de la même préparation retrouve cette demande. Un message Messenger livré deux fois ne relance pas l’inférence. Les erreurs du moteur sont présentées sans détail interne. Après dix minutes, une demande qui n’a pas abouti devient explicitement en échec. Les demandes de plus d’un jour sont nettoyées à la création d’une nouvelle demande de ce propriétaire ; il ne s’agit pas d’une purge automatique à heure fixe.

Cette correction libère le serveur web ; elle n’accélère pas le modèle lui-même. Le modèle 8B et ses limites narratives restent ceux du [pilote supervisé](scenario-local-supervise.md).

### Épisodes choisis et ajoutables

Créer une série propose de 0 à 12 épisodes initiaux, avec 1 par défaut. Le nombre concerne des emplacements à préparer, pas des vidéos générées. Une série vide propose d’ajouter un épisode. Le formulaire « Épisodes à ajouter » permet ensuite d’en ajouter de 1 à 12 par action. La numérotation reprend après le dernier numéro existant ; la révision de série et les verrous empêchent un double ajout lors d’une répétition du même formulaire.

Les nouveaux épisodes ont un prompt vide. Leur carte indique « Histoire à préparer » au lieu de répéter le thème, le style et les personnages comme s’il s’agissait déjà d’un scénario. Le contexte de série reste proposé à l’ouverture du préparateur pour inventer une histoire. Les anciens prompts identiques au contexte actuel sont masqués sur les cartes à préparer, sans modification de leurs données. Les textes réellement personnalisés et les générations existantes sont conservés.

## Fichiers et rôle des composants

| Fichier ou groupe | Responsabilité |
| --- | --- |
| `AiIdeaType.php`, `preparation_controller.js`, `templates/ai/studio.html.twig` | Choix sans case vide, enchaînement uniquement pour plusieurs clips et correction du modèle/référence. |
| `StoryProposal.php` et migration `Version20261023221159` | Demande de texte privée, préparation d’origine, état et résultat durable. Aucun secret BYOK. |
| `AiStoryProposalController.php` | Enregistrement rapide, accès propriétaire, CSRF et lecture des états/résultat. |
| `StoryProposals.php` | Déduplication, traitement unique, expiration, nettoyage et orchestration du rédacteur existant. |
| `WriteStoryProposal.php`, son handler et Messenger | Transmettent uniquement l’identifiant de la demande au worker. |
| `LocalStoryWriter.php` | Rédaction existante en deux étapes ; signale désormais ses étapes au service. |
| `story_writer_controller.js`, `_story_proposal.html.twig`, `episode.css` | Consultation d’état, reprise à l’ouverture, progression et application volontaire. |
| `AiSeriesType.php`, `AiEpisodeBatchType.php`, contrôleur et service Séries | Nombre initial, ajout d’épisodes, propriété, validation et révisions. |
| `AiEpisode.php`, templates de séries | Nouveaux prompts vides et cartes à préparer sans répétition du contexte. |
| `compose.yaml`, `messenger.yaml` | Worker et file `story_writing`, séparés des publications et des générations vidéo. |

Flux Symfony : formulaire → contrôleur → service transactionnel → entité Doctrine et message → réponse rapide → worker Messenger → service local → état enregistré → consultation par Stimulus → aperçu → application → préparation vidéo et confirmation existantes.

À comprendre : l’asynchronisme consiste à déplacer le travail long hors de la requête web, pas simplement à utiliser `fetch`. Un worker exécute le même monolithe Symfony avec un autre point d’entrée. La base permet de retrouver le résultat après navigation. Les contrôles propriétaire et CSRF restent nécessaires sur ces nouvelles routes.

## Lancer et tester

Après migration et configuration locale de rédaction :

```powershell
docker compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -T php php bin/console cache:clear --env=dev
docker compose up -d --no-deps story-worker
npm run build
```

La migration additive a été appliquée localement en dev et test ; le worker privé de rédaction a été démarré. Il ne consomme ni générations vidéo ni publications. Aucun changement d’écoute d’Ollama ni de pare-feu. Aucun nouveau modèle téléchargé dans ce lot.

Recette : ouvrir un clip unique ; vérifier l’absence de choix d’enchaînement. Ajouter des clips et sélectionner Prolonger l’action ; vérifier l’aide puis choisir le mode image et sa référence. Demander un scénario, visiter Séries, revenir : retrouver son état/résultat. Tester un autre propriétaire, un moteur indisponible et une préparation modifiée pendant le travail. Créer une série avec 0, 1 ou plusieurs épisodes ; en ajouter puis renvoyer le même formulaire : aucun doublon, aucun clip payant créé.

Les vérifications UI utilisent des objets et une rédaction simulés, sans modifier les données du propriétaire. Les tests PHP utilisent exclusivement shortflow_test. Résultats exacts : [verification.md](verification.md).

## Objectif récurrent : état et prochain chantier

Le mode « thème + personnage → nouvel épisode chaque mardi à 18 h → publication » reste à implémenter. Ce lot ne l’annonce pas disponible et ne crée pas un bouton automatique sans moteur derrière.

Le prochain lot doit relier les moteurs déjà présents :

1. **Configuration récurrente** : jour, heure, fuseau, destinations et comptes, durée finale, modèle, références stables et ambiance. Séparer l’heure de production de l’heure de publication ; préparer par exemple la veille pour disposer d’une marge, plutôt que commencer l’inférence à 18 h.
2. **Mémoire narrative durable** : identité et références du personnage, événements des épisodes terminés, résumé et contrôle des répétitions. Les descriptions de scènes tronquées des trois derniers épisodes du pilote ne constituent pas encore cette mémoire.
3. **Occurrence unique et production** : un épisode par série et créneau, écriture en arrière-plan, découpage compatible (par exemple 3 × 10 s pour 30 s), génération des clips et assemblage via `AiEpisodeFlow`, BYOK et `VideoEdits`. Une référence peut stabiliser le personnage ; elle ne garantit pas son identité.
4. **Validation et publication** : validation manuelle par défaut. Le mode entièrement automatique doit être choisi explicitement et réutiliser les contrôles et brouillons programmés de `PublicationDrafts`. La programmation ponctuelle d’une vidéo existe ; son pilotage récurrent depuis une série manque encore.
5. **Consentement et limites de dépense** : autorisation durable liée au fournisseur/modèle, à la recette, à la fréquence et aux comptes ; estimation par épisode et période, limite de générations et de tentatives. Refuser de démarrer une étape si ces réglages ou tarifs sortent du consentement. Une limite d’estimation dans VydFlow n’est pas un plafond de facturation garanti chez le fournisseur.
6. **Pause et reprise** : contrôler l’autorisation à chaque étape, conserver les identifiants distants, ne pas renvoyer aveuglément une demande payante incertaine. Une vidéo en retard ne doit pas être publiée immédiatement à la place du créneau manqué. Les modifications de durée ou fréquence concernent les prochaines occurrences, avec nouvelle estimation si nécessaire.

Les tests de ce lot récurrent devront couvrir doublons de messages, changement d’heure, pause pendant une génération, clé expirée, tarif modifié, résultat incertain, assemblage échoué et annulation avant départ. Ils devront commencer avec fournisseurs et réseaux simulés. Les essais payants et sociaux réels restent distincts.
