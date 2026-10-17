# Brouillons, programmation et calendrier — 18 octobre 2026

## Objectif et périmètre livré

Lot local autorisé après validation du parcours. Une préparation peut être enregistrée, reprise après déconnexion, publiée immédiatement ou programmée à une date commune pour ses vidéos et destinations. Le calendrier présente les préparations datées et leur résultat ; Publications conserve le suivi indépendant de chaque destination.

Les tests utilisent des clients HTTP simulés. Aucun compte social, accès réel, service public, publication réelle ou programmation de données dev n’a été ajouté pendant ce lot. Sur ce PC, les intégrations attendent leur configuration privée. Les brouillons fonctionnent sans compte social ; la confirmation d’un départ exige toutes les destinations prêtes. IA, montage, récurrences, séries et notifications restent hors de ce lot.

## Parcours dans l’interface

1. Dans **Bibliothèque**, sélectionner les vidéos puis **Préparer la publication**.
2. Choisir les réseaux. **Enregistrer le brouillon** fonctionne même sans réseau sélectionné.
3. Adapter les textes, donner éventuellement un titre de travail et enregistrer. Les champs obligatoires pour publier peuvent rester incomplets ; formats, longueurs et choix restent contrôlés. Pas de sauvegarde automatique. Un avertissement navigateur protège les changements non enregistrés lorsque JavaScript et le navigateur le permettent.
4. **Voir le récapitulatif** enregistre aussi la préparation durable. Choisir directement **Publier maintenant** ou **Choisir une date** : le formulaire **Programmer ce lot** est dans le même écran, sans visite obligatoire de Brouillons. **Vérifier ce créneau** ouvre le récapitulatif daté avant confirmation. La sauvegarde technique est commune aux deux parcours ; aucun enregistrement manuel préalable n’est nécessaire.
5. **Garder pour plus tard** permet de reprendre ensuite dans **Brouillons** : modifier textes/réseaux, remplacer les vidéos ou choisir le créneau. La liste montre la première vidéo de chaque lot ; le détail et la confirmation montrent les vidéos sélectionnées, avec contrôles natifs, métadonnées préchargées et aucune lecture automatique. Source absente : message explicite, pas de lecteur cassé. Les médias passent par la route privée existante et ne sont pas recopiés.
6. Seule la confirmation finale arme la programmation ou engage les envois immédiats. Enregistrer la date seule ne publie rien.
7. Dans **Calendrier**, consulter le mois, changer le fuseau d’affichage et filtrer réseau/état. Sur mobile, les jours avec préparation sont listés en agenda. Une carte ouvre le détail. Un lien retrouve les brouillons sans date.

Brouillons présente aussi les préparations programmées, annulées et déjà engagées, par pages de 20 : l’ensemble du travail enregistré reste accessible.

## Règles de programmation

| Sujet | Comportement |
| --- | --- |
| Sélection | 1 à 20 vidéos privées ; maximum 5 si TikTok est choisi |
| Créneau | Une date commune au lot, entre 2 minutes et 90 jours dans le futur lors de la confirmation finale |
| Fuseau | IANA explicite, initialement Europe/Paris ; instant UTC, zone et saisie locale conservés séparément |
| Changement d’heure | Heure inexistante ou ambiguë refusée ; choisir une autre heure |
| Départ | Jamais avant l’échéance ; tolérance de 2 minutes après, recontrôlée après les validations distantes |
| Retard | Au-delà de 2 minutes, aucun nouveau lot créé ; nouvelle date et confirmation nécessaires |
| Compte ou source changé | Recontrôle au départ, blocage du lot entier avant création des envois |
| Modification | Confirmation explicite pour désarmer et revenir au brouillon ; l’ancien réveil devient caduc |
| Annulation | POST avec CSRF et version, jusqu’à l’engagement du départ du lot |
| Après départ | Textes et créneau figés ; états/actions par destination dans Publications |
| Suppression source | Une programmation armée bloque toute la sélection de suppression ; annuler d’abord. Un brouillon non armé conserve le titre et indique la source supprimée |

Le PC, Docker, PostgreSQL et le worker doivent fonctionner. Le créneau désigne le démarrage des traitements, sans garantie d’apparition publique simultanée. Après un arrêt prolongé, les messages restent en base mais les créneaux dépassés ne partent pas automatiquement. Hébergement permanent et supervision demanderaient un lot distinct autorisé.

Consulter calendrier et détail n’appelle aucune API sociale et n’écrit pas en base. Avant même le retour du worker, un créneau dépassé apparaît **À reprogrammer**. Le récapitulatif de confirmation effectue les vérifications nécessaires, dont les capacités TikTok si disponibles.

## Fichiers et responsabilités

| Fichier ou groupe | Rôle |
| --- | --- |
| `src/Entity/PublicationDraft.php` | Propriétaire, titre, comptes attendus, réseaux, état, version, dates, fuseau et clé commune |
| `src/Entity/PublicationDraftItem.php` | Vidéo ordonnée, source nullable, titre conservé et champs par réseau validés en JSON |
| `migrations/Version20261017221235.php` | Deux tables, index et clés étrangères ; migration additive sans reprise artificielle des anciennes publications |
| `src/Repository/PublicationDraftRepository.php` | Recherche propriétaire et intervalle UTC du calendrier, tri chronologique commun |
| `src/Service/PublicationDrafts.php` | Sauvegarde partielle, version, transitions, relecture des sources/comptes et engagement atomique |
| `src/Service/PublicationTime.php` | Résolution stricte des dates/fuseaux et limites avec horloge injectable |
| `src/Service/PublicationCalendar.php` | Projection locale des retards et des résultats des jobs existants, filtrés par propriétaire |
| `src/Message/DispatchScheduledPublication.php` et son handler | Réveil différé : seulement identifiant/version, traitement délégué au service |
| `src/Command/RecoverPublicationScheduleCommand.php` | Remise en file manuelle du réveil d’une version encore autorisée |
| `src/Controller/PublicationDraftController.php` | Listes, calendrier, détail, date, récapitulatif et confirmations HTTP |
| `MultiNetworkPublicationController` et FormTypes | Sauvegarde explicite, titre de travail, saisie partielle et raccordement au stockage durable |
| `src/Form/PublicationPlanType.php` et `templates/draft/_plan_fields.html.twig` | Champs de programmation communs au récapitulatif direct et au détail ; même POST propriétaire/CSRF/révision et mêmes règles de date |
| `templates/draft/_preview.html.twig` | Lecteur privé commun liste/détail/confirmation ; pause à la déconnexion via le contrôleur Stimulus existant |
| `MultiNetworkPublishing` et `VideoLibrary` | Réutilisation du moteur d’envoi et protection des sources programmées |
| `templates/draft`, `_batch_review`, navigation studio et `publication.css` | Vues serveur, récapitulatif partagé, mois desktop/agenda mobile et actions accessibles |
| `assets/controllers/unsaved_controller.js` | Avertissement avant fermeture/navigation après changements non enregistrés |
| Configurations services, Messenger et limiteur | Horloge UTC, file Doctrine existante et limite de 30 sauvegardes/minute/propriétaire |
| `PublicationScheduleTest`, `PublicationTimeTest`, `unsaved.test.js` | Métier, HTTP/CSRF/isolation, horloge, changements d’heure et navigation |

Les secrets restent dans les connexions chiffrées existantes : aucun jeton OAuth dans les brouillons, messages ou Twig. Les vidéos ne sont pas recopiées. Le JSON contient uniquement des valeurs validées par les FormTypes.

## Flux d’exécution et garanties Symfony

**Sauvegarde** : propriétaire vérifié → formulaire/CSRF → service → verrou propriétaire et transaction → vidéos privées et validation partielle → écriture Doctrine → redirection. La révision soumise doit correspondre à celle relue sous verrou : un ancien onglet ne peut pas écraser la modification récente.

**Confirmation** : formulaire complet/CSRF/version → relecture verrouillée des sources/comptes → validation des réglages/créneau → statut programmé, nouvelle clé et nouvelle version → message Messenger avec `DelayStamp`. Message et programmation utilisent la même connexion transactionnelle. Aucun job social n’est créé à cette étape.

**Échéance** : Messenger livre identifiant/version → verrou consultatif propriétaire, ligne propriétaire puis préparation/sources → ancienne version ou état inactif ignorés → horloge/comptes/sources/capacités revérifiés → moteur multi-réseaux existant → dernier contrôle de l’heure → départ marqué engagé → transaction validée. Les jobs Instagram/TikTok et leur validité de 24 heures commencent à l’échéance.

Une erreur tardive annule tous les jobs et messages du départ. Le service réinitialise l’EntityManager, relit état/version sous verrou dans une nouvelle transaction puis enregistre le blocage si la même programmation reste active. Le message suivant peut être traité avec le même service. Une contention du verrou suit les reprises Messenger sans diagnostic définitif artificiel. Une redélivrance après succès n’ajoute aucun envoi.

**Annulation ou modification** : POST propriétaire/CSRF/version → même verrou → nouvelle version et état annulé/brouillon. L’ancien réveil reste éventuellement en file mais n’a plus d’autorité. Si l’engagement a gagné la course, l’annulation est refusée ; aucun faux message de rappel d’un envoi déjà engagé.

Le verrou de ligne propriétaire est pris avant les sources, comme dans la suppression de bibliothèque. Cette coordination protège suppression/programmation. Les tests emploient aussi une seconde connexion PostgreSQL pour vérifier l’exclusion des actions ; ils ne constituent pas un essai de charge en production.

Le calendrier dérive **En cours**, **Publiée**, **Réussite partielle** et **À traiter** des jobs existants via leur clé commune. Publiée exige toutes les destinations réussies. Les anciennes publications restent dans Publications ; aucune date de programmation inventée.

À comprendre en Symfony : le **contrôleur** traite HTTP, les **FormTypes** contrôlent la saisie, les **entités Doctrine** rendent le brouillon durable, le **service** porte les règles métier et **Messenger** exécute le travail différé. La transaction rend la création des demandes atomique ; les API sociales gardent leurs résultats indépendants.

## Comment tester

Après pull sur un autre PC : commandes README (`composer db:sync`, reconstruction frontend et redémarrage du worker). La migration est additive ; ne jamais supprimer le volume pour actualiser le schéma.

Sans accès sociaux : sélectionner une vidéo, enregistrer un brouillon incomplet, se déconnecter/reconnecter, retrouver les valeurs, modifier la sélection et proposer une date. Le récapitulatif indique les destinations à configurer et interdit de confirmer. Aucune fausse connexion ni visibilité TikTok inventée pour activer le bouton.

Vérifications automatiques exclusivement dans `shortflow_test` :

```sh
docker compose exec php php scripts/check.php
docker compose --profile tools run --rm --no-deps node sh -c 'npm run check && npm run test:ui && npm run build'
```

Après migration de test, suite ciblée :

```sh
docker compose exec -T php php vendor/bin/phpunit --filter 'PublicationScheduleTest|PublicationTimeTest|MultiNetworkPublicationTest'
```

Scénarios : sauvegarde sans compte, reprise, accès croisés/CSRF/version, date sans armement, confirmation/rejeu, annulation/modification, fuseaux/DST/limites, réveil anticipé, retard, compte remplacé, rollback tardif, validation lente, message suivant après reset Doctrine, verrou d’une autre connexion, protection des sources et états partiels.

Diagnostic local seulement si un message a été perdu ou placé en échec :

```sh
docker compose exec php php bin/console app:publication:schedule:recover ID
```

Remplacer ID par une préparation encore programmée. La commande refuse brouillon, annulation, départ engagé ou créneau hors délai ; aucun réarmement. Elle réutilise la même version et les mêmes garde-fous.

Résultats effectivement exécutés et limites de QA : `docs/verification.md`. Les essais sociaux réels et un éventuel hébergement restent à autoriser séparément.
