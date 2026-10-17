# Publication multi-réseaux — préparation commune du 17 octobre 2026

## Objectif et périmètre

Depuis la bibliothèque, sélectionner de 1 à 20 vidéos, choisir une ou plusieurs destinations, adapter les textes et réglages, consulter un récapitulatif puis confirmer une seule fois. Si TikTok est inclus, le lot est limité à cinq vidéos. Deux vidéos vers trois réseaux donnent six publications indépendantes.

Ce lot termine le parcours local de publication immédiate. Les API officielles et les workers déjà présents sont réutilisés. Les tests remplacent les réponses HTTP sociales par `MockHttpClient` : ils n’envoient aucune vidéo aux plateformes et ne prouvent pas que Meta/TikTok ont accepté l’application. Aucun compte de démonstration ni simulation n’est activé dans le studio du propriétaire. Les connexions réelles Meta/TikTok et la configuration YouTube sur ce PC restent en attente. Calendrier, IA et statistiques supplémentaires restent hors périmètre.

## Fichiers et composants Symfony

- `src/Controller/MultiNetworkPublicationController.php` : sélection privée, étapes HTTP, formulaires CSRF, lecture et écriture du brouillon en session, confirmation et redirection vers le suivi. `ROLE_VERIFIED` reste exigé par Security.
- `src/Form/MultiNetworkPrepareType.php` : compose les trois FormTypes existants, sans leurs confirmations individuelles. Le formulaire parent porte le CSRF et la version de préparation. Les validations par réseau restent appliquées.
- `src/Form/TikTokPublishType.php` : autorise uniquement dans le récapitulatif hors connexion une visibilité manquante. Aucune visibilité ni permission n’est inventée. Sans capacités réelles et réglages conformes, le service refuse l’envoi.
- `src/Service/MultiNetworkPublishing.php` : état des comptes, compatibilité des sources, lecture des capacités TikTok, contrôle des comptes attendus, idempotence et transaction commune. Les trois services de publication existants créent leurs entités et messages sans nouvelle couche d’adaptateurs.
- `templates/publication/batch_*.html.twig` et `_batch_*.html.twig` : choix des réseaux, formulaires par destination, comptes, avertissements et récapitulatif de tous les réglages. Le bouton final reste désactivé si une destination manque ; le POST applique le même blocage côté serveur.
- `assets/styles/publication.css` : cartes de destinations, étapes sans numérotation native supplémentaire, espaces entre les actions, récapitulatif et dispositions mobiles.
- `tests/Functional/MultiNetworkPublicationTest.php` : parcours et protections sur `shortflow_test` uniquement, HTTP simulé.

L’entrée principale conserve `/studio/publications/preparer` et le nom `app_publication_prepare` : les boutons existants de la bibliothèque et du détail vidéo ouvrent désormais le parcours commun. L’ancien formulaire YouTube est conservé à `/studio/publications/youtube/preparer` (`app_youtube_prepare`) ; les tests historiques et liens des anciens formulaires sont adaptés à cette route. Les formulaires Instagram/TikTok existants restent disponibles.

## Flux d’exécution

1. **Choix des vidéos** : chaque identifiant est contrôlé avec son propriétaire. Une sélection privée crée une clé aléatoire et redirige vers le choix des réseaux ; aucune publication n’est créée.
2. **Réseaux** : formulaire CSRF, au moins une destination, maximum cinq vidéos si TikTok est choisi. L’identité du compte de chaque destination est figée pour cette préparation. Les comptes indisponibles restent sélectionnables pour préparer les textes, avec un état explicite.
3. **Textes et réglages** : les mêmes contraintes que les formulaires individuels sont appliquées. TikTok propose seulement les visibilités et interactions confirmées par son API, sans visibilité présélectionnée. Les choix devenus indisponibles sont retirés du formulaire et doivent être revérifiés. Les légendes, titres, déclarations et options restent distincts par vidéo/réseau.
4. **Récapitulatif** : les valeurs validées sont stockées côté serveur. Il affiche chaque compte, texte, visibilité, audience, interaction et déclaration. Aucun POST social de publication n’a encore lieu. Une éventuelle lecture des capacités TikTok ne publie rien.
5. **Confirmation** : checkbox obligatoire, CSRF, clé du récapitulatif, limiteur partagé, propriété et disponibilité recontrôlés. Le POST utilise uniquement le contenu du brouillon serveur ; il ne reçoit pas de nouvelles légendes à publier. Un compte changé bloque tout le lot. Les capacités TikTok sont vérifiées de nouveau par le service du réseau.
6. **Mise en file atomique** : verrou propriétaire partagé, clé de soumission commune, transaction Doctrine contenant tous les jobs et messages Messenger. Les transactions internes utilisent les savepoints du même PostgreSQL. Si une destination échoue à cette étape, aucun job ni message du lot n’est conservé. Les réponses distantes de publication, ultérieures, ne font pas partie de cette transaction locale.
7. **Suivi** : redirection vers Publications. Les workers existants traitent chaque destination séparément. Une réussite YouTube et un échec Instagram peuvent donc coexister ; les reprises gardent les protections de chaque handler et ne recréent pas les réussites.

## Brouillons et limites

L’espace de travail temporaire reste lié à la session (une heure, dix préparations récentes). **Enregistrer le brouillon** et **Voir le récapitulatif** sauvegardent aussi une préparation durable et versionnée. Le récapitulatif propose directement la publication immédiate ou le choix d’un créneau, sans enregistrement manuel ni détour obligatoire par Brouillons. La confirmation datée réutilise les contrôles existants. Voir [calendrier.md](calendrier.md). Chaque nouvelle sélection ouvre une préparation distincte.

Enregistrer les textes avec **Voir le récapitulatif** avant de changer les réseaux. Les textes enregistrés sont conservés, y compris pour une destination retirée puis rajoutée. Les modifications non enregistrées ne sont pas conservées lors d’une navigation. Une modification des réseaux impose une nouvelle validation des textes ; chaque nouvel enregistrement change la version du récapitulatif. Un ancien formulaire de confirmation est refusé. Un lot déjà confirmé redirige vers le suivi ; la clé commune empêche également les doublons côté service.

La prévisualisation hors connexion n’autorise pas la publication : reconnecter un compte exige de revenir au choix des réseaux puis de vérifier les réglages. Une visibilité TikTok absente ne devient jamais automatiquement privée ou publique.

## Comment tester

Vérifications automatiques : suivre les commandes globales du README, qui migrent `shortflow_test` avant PHPUnit. Test ciblé après migration :

```sh
docker compose exec -T -e SHELL_VERBOSITY=-1 php php vendor/bin/phpunit --filter MultiNetworkPublicationTest
```

Parcours manuel sans accès sociaux : Bibliothèque → sélectionner les vidéos → Préparer la publication → cocher plusieurs réseaux → Adapter les publications → renseigner les options YouTube si choisi → Voir le récapitulatif. Vérifier le nombre de destinations et les textes ; TikTok ne propose aucune visibilité fictive ; le bouton de publication reste désactivé. Revenir aux textes puis aux réseaux et contrôler les valeurs enregistrées. Ne pas saisir de faux identifiants pour activer les boutons.

Les essais réels restent distincts, après configuration des accès et HTTPS et autorisation du propriétaire. Les résultats effectivement exécutés sont consignés dans `verification.md`.

## À comprendre en Symfony

Un **FormType composé** réutilise des formulaires existants et conserve leur validation. Le contrôleur traite les étapes HTTP ; la **session** conserve temporairement les valeurs serveur ; le service coordonne les règles métier. La **transaction Doctrine** garantit que les demandes de tout le lot sont enregistrées ensemble. Elle ne rend pas les API sociales transactionnelles. **Messenger** exécute ensuite les envois hors de la requête navigateur ; chaque destination conserve son propre état durable.
