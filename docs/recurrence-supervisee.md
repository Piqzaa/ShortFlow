# Récurrence supervisée — 24 octobre 2026

Complément du même jour : le [contrôle de relecture du rédacteur](redacteur-local-fiabilite.md) conserve les scènes signalées comme brouillon modifiable et met la récurrence en pause avant réservation du budget ou confirmation vidéo. L'absence d'alerte ne valide pas la qualité narrative ; la validation finale de la vidéo reste manuelle.

## Objectif et parcours

Une série peut préparer chaque semaine une nouvelle vidéo à partir de son thème, de ses personnages et de ses références. Le créneau est une date de publication **visée**, pas une autorisation de publier sans validation. Le scénario, les clips et l’assemblage sont automatiques après activation ; la vidéo attend ensuite le créateur.

Dans la série, ouvrir **Chaque semaine, une nouvelle histoire** :

1. Vérifier la durée totale, le modèle et les références dans **Configurer la série**. Ils sont réutilisés, sans formulaire vidéo parallèle.
2. Choisir le jour, l’heure, le fuseau et les réseaux proposés à la validation.
3. Autoriser de 1 à 12 épisodes maximum, avec estimation par épisode et total. Une nouvelle autorisation sera nécessaire ensuite ; il n’y a pas d’autorisation illimitée cachée.
4. Confirmer explicitement les coûts fournisseur. Les générations sont facturées directement sur l’accès BYOK existant ; VydFlow ne vend pas de crédits.
5. La veille du créneau, le worker écrit l’histoire et les prompts, puis réutilise la génération ordonnée et l’assemblage existants. Les nouveaux épisodes sont ajoutés à la suite de la série ; les épisodes manuels ne sont pas écrasés.
6. Vérifier la vidéo et **Valider cette version**. **Préparer et programmer** retrouve les réseaux et la date proposés, sans armer de publication. Vérifier les textes et réglages, puis confirmer le créneau dans le préparateur habituel. Une date dépassée doit être modifiée ; aucun envoi immédiat de remplacement.

L’ambiance et les scènes/prolongement sont repliés. Automatique respecte le thème ; en l’absence de ton explicite, le rédacteur vise l’humour léger. Le prolongement demande un modèle image compatible et sa référence ; un clip unique est normalisé par le moteur existant. Les références communes aident la cohérence, sans garantir l’identité du personnage.

## Planification et limites

- Une préparation est prévue 24 heures avant le créneau. Si l’activation se fait plus tard, elle peut commencer immédiatement, à condition de conserver plus de deux heures avant publication ; sinon le premier créneau passe à la semaine suivante.
- Le fuseau IANA est conservé. Les heures inexistantes ou ambiguës lors du changement d’heure sont sautées ; aucun décalage n’est deviné. L’heure locale hebdomadaire est recalculée chaque semaine.
- Les messages différés sont en PostgreSQL. Le service local, Docker, Ollama et les workers doivent rester disponibles. Une indisponibilité peut faire manquer un créneau ; cela ne provoque pas une rafale d’anciens épisodes ni un envoi social tardif.
- Un seul épisode est créé par récurrence et instant UTC. La consommation du créneau et la mise en file du suivant sont transactionnelles. Une double livraison ou une remise en file retrouve l’état courant.
- Un créneau manqué consomme une place de l’autorisation mais aucune estimation de génération. Une rédaction invalide ou une préparation modifiée pendant l’écriture met la récurrence en pause, avec reprise manuelle possible depuis l’épisode.
- Les résumés narratifs sont persistés ; les cinq derniers disponibles servent de contexte au rédacteur, avec le thème, le style et l’identité commune. Cette mémoire bornée ne garantit pas l’absence de répétition sémantique ; la validation humaine reste importante.
- Modifier thème, durée, modèle, référence, compte BYOK ou estimation demande une nouvelle confirmation avant le prochain épisode automatique. La fréquence et les réseaux sont remplacés dans le formulaire d’autorisation. Les occurrences existantes gardent leurs destinations et leur date ; les vidéos/programmes déjà confirmés ne changent pas silencieusement.

## Consentement de génération et pause

Le consentement durable porte sur une recette, un tarif estimé et un accès fournisseur, avec un nombre limité de créneaux. L’estimation totale d’un épisode est réservée avant la confirmation du moteur vidéo ; elle n’est pas remboursée automatiquement dans le compteur après un échec partiel. C’est une borne conservatrice sur les lancements estimés, **pas un plafond garanti de facturation chez le fournisseur**, notamment pour les taxes, conversions et tarifs réels.

L’identité d’une clé API repose sur son empreinte chiffrée ; pour Fal navigateur, elle repose sur le compte. Ces empreintes restent côté serveur. L’autorisation est revérifiée avant le prochain plan **et sous le verrou propriétaire juste avant le POST fournisseur**. Une clé remplacée après mise en file ne peut pas lancer silencieusement une génération sur un nouvel accès.

**Pause** invalide les prochains réveils et suspend les étapes restantes des épisodes en cours. Une demande déjà transmise peut se terminer et être facturée ; son résultat peut toujours être récupéré. Une programmation sociale déjà confirmée doit être annulée depuis le calendrier si souhaité.

Il n’y a pas de relance payante automatique d’un plan incertain. La reprise/refabrication manuelle garde les confirmations existantes et reprend la main sur l’autorisation automatique de cet épisode uniquement. Elle peut entraîner des coûts supplémentaires explicitement confirmés hors du compteur récurrent. Un échec de confirmation manuelle ne retire pas le contrôle récurrent.

## Fichiers et responsabilités

| Composant | Rôle |
| --- | --- |
| `SeriesRecurrence` | Autorisation hebdomadaire privée, recette, version, prochain instant, nombre de créneaux et estimation réservée |
| `SeriesOccurrence` | Créneau unique, épisode ajouté, date/fuseau/réseaux proposés, résumé narratif, montant réservé et origine de l’autorisation |
| `WeeklySeriesTime` | Calcul strict de l’heure locale, réutilisation de `PublicationTime` et gestion des changements d’heure |
| `SeriesRecurrences` | Estimation, activation, pause, réveil durable, écriture hors transaction puis préparation et confirmation des clips |
| `SeriesGenerationConsent` | Vérification de l’autorisation avant les étapes payantes, et passage atomique à une reprise manuelle confirmée |
| `AiKeys::identity` | Empreinte d’accès locale, sans exposer clé ou jeton dans les messages ou les templates |
| `PrepareSeriesEpisode` et son handler | Message minimal livré sur la file de rédaction existante ; aucune nouvelle infrastructure cloud |
| `SeriesRecurrenceType`, `AiSeriesController`, `_recurrence.html.twig` | Configuration dans la page de série, CSRF/propriétaire/révision, état et pause |
| `recurrence_budget_controller.js` | Total estimé visible dès que le nombre d’épisodes change ; aucun calcul financier serveur délégué au navigateur |
| `EpisodePublications` | Réseaux, texte de départ et date proposés dans le brouillon après validation, sans confirmation implicite |
| `Version20261024020000` | Deux tables, clés étrangères et contraintes uniques additives |
| `RecoverSeriesRecurrencesCommand` | Remise en file locale du prochain réveil d’une autorisation encore active |

## Flux Symfony à comprendre

**Activation** : formulaire/CSRF → propriétaire → verrou propriétaire et série → contrôle version/estimation/consentement → enregistrement Doctrine → message Messenger différé sur la même connexion transactionnelle → redirection 303. Les anciennes versions de message deviennent inoffensives.

**Production** : message livré → vérification état/version/créneau → création unique du nouvel épisode et de son occurrence, prochain réveil enregistré → transaction terminée → appels locaux du rédacteur sans bloquer la navigation web → nouvelle transaction → contrôle des changements et du délai → `AiEpisodeFlow::prepare`, estimation et réservation → confirmation existante → moteurs IA et FFmpeg existants.

**Publication** : résultat prêt → validation utilisateur → brouillon lié, prérempli → confirmation habituelle du calendrier. Une relation Doctrine ou un message de génération ne vaut jamais consentement de publication.

## Vérifier et exploiter localement

La migration a été appliquée en dev et test. Aucune série du propriétaire n’a été activée pendant le développement et aucun essai payant/social réel n’a été lancé. Les services existants de génération et de rédaction doivent être activés et configurés pour utiliser le formulaire.

Après modification de configuration avec le debug local désactivé :

```powershell
docker compose exec -T php php bin/console cache:clear --env=dev --no-debug
docker compose restart story-worker background-worker worker edit-worker
```

Après une perte de réveil suspectée, cette commande remet uniquement en file les autorisations encore actives ; les doublons sont protégés :

```powershell
docker compose exec -T php php bin/console app:series:recover --no-debug
```

Les tests fonctionnels utilisent exclusivement `shortflow_test`, des horloges pilotées et des clients HTTP simulés. Ils couvrent consentement/CSRF/isolation/version, créneau anticipé, double message, préparation ordonnée et budget, date/réseaux préremplis sans envoi, pause pendant l’écriture, changement de clé après mise en file, créneau dépassé, durée modifiée, mauvaise réponse texte, dernier épisode autorisé et reprise manuelle atomique. Les tests horaires couvrent les deux changements d’heure. Les moteurs de génération/assemblage restent couverts par la suite existante, avec FFmpeg réel sur médias synthétiques. Résultats exacts : [verification.md](verification.md).

## Ce qui reste

La publication entièrement automatique après génération n’est pas activée dans ce lot. Avant de la proposer, il faut améliorer et évaluer la qualité narrative du rédacteur, valider le fonctionnement récurrent sur l’hébergement choisi et les capacités réelles des plateformes, puis ajouter une autorisation de publication autonome explicite. Le modèle local reste lent ; aucun benchmark ou téléchargement supplémentaire n’a été effectué ici.
