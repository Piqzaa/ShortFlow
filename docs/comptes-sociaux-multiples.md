# Comptes sociaux multiples et choix des destinataires

## Objectif

Un utilisateur peut connecter plusieurs chaînes YouTube, comptes Instagram et comptes TikTok. Chaque préparation choisit un compte par réseau : une vidéo peut viser une chaîne YouTube et un profil Instagram, tandis que la suivante vise d’autres comptes, à une autre date. La confirmation habituelle reste obligatoire. Ce lot ne lance aucun essai social réel ni génération payante.

Dans **Connexions**, « Ajouter un compte » conserve les autres accès. « Reconnecter » renouvelle uniquement l’identité demandée ; un retour OAuth correspondant à un autre compte est refusé. Chaque carte propose sa propre déconnexion. Les jetons restent chiffrés côté serveur.

## Fichiers et responsabilités

- `src/Service/SocialAccounts.php` centralise la recherche par propriétaire et identité distante, les libellés et les choix. Un compte unique peut être proposé ; plusieurs comptes exigent un choix explicite.
- Les trois entités `*Connection` utilisent une relation Doctrine `ManyToOne` vers l’utilisateur et une unicité propriétaire + identité distante. La migration `Version20261026200000` conserve les lignes et jetons existants.
- Les contrôleurs `*ConnectionController` distinguent ajout, reconnexion ciblée et déconnexion ciblée. `templates/publication/connections.html.twig` affiche les cartes par réseau.
- `src/Form/SocialAccountsType.php` expose uniquement les comptes du propriétaire. Le compositeur, la préparation par vidéo et la récurrence partagent ce formulaire.
- `PublicationComposerController`, `PublicationBatch` et `SeriesRecurrences` enregistrent les identités choisies. `SeriesOccurrence` fige les comptes d’un créneau ; `EpisodePublications` transmet cette sélection à sa préparation durable. Une ancienne occurrence sans identité demande un choix humain.
- Les services `*Publishing`, les handlers `Publish*Handler`, `PublicationRetry` et `PublicationStatistics` retrouvent le compte figé dans la publication. Déconnecter A puis connecter B ne détourne pas les envois ou statistiques prévus pour A.
- `tests/Functional/SocialAccountsTest.php` vérifie les comptes multiples, l’isolation propriétaire, les retours OAuth simulés, le destinataire enregistré, les occurrences et l’utilisation des bons jetons.

## Flux d’exécution

1. Le retour OAuth fournit une identité distante vérifiée auprès du fournisseur. Sous verrou du propriétaire, VydFlow met à jour cette identité ou ajoute une ligne.
2. Le formulaire propose les identités appartenant à l’utilisateur. Un choix forgé échoue à la validation serveur.
3. La préparation conserve les identités, avec ses textes et sa programmation. Chaque vidéo sélectionnée possède sa propre préparation.
4. La confirmation vérifie que le compte enregistré est toujours disponible et compatible. Un compte disparu bloque l’envoi jusqu’à une correction explicite.
5. Le worker et la collecte de statistiques utilisent cette même identité. Ils ne prennent jamais arbitrairement le premier compte connecté.

## Vérification manuelle

Avec des accès de test autorisés, connecter deux comptes sur un réseau, sélectionner B dans une préparation puis enregistrer. Revenir au brouillon : B doit rester sélectionné. Ajouter un compte C ne doit pas changer ce brouillon. Déconnecter B doit empêcher la confirmation tant qu’un autre compte n’est pas choisi explicitement. Aucun envoi réel n’est nécessaire pour vérifier le formulaire et ces blocages.

Pour plusieurs vidéos, choisir des réseaux, comptes et dates différents, enregistrer les préparations puis ouvrir chacune pour revoir ses textes et confirmer individuellement. Une date proposée pour un épisode n’active pas sa publication.

## À comprendre en Symfony

Une relation `ManyToOne` permet plusieurs connexions pour le même utilisateur ; l’index unique composite empêche le doublon d’une même identité chez ce propriétaire. Le `ChoiceType` limite les valeurs du formulaire, mais le service vérifie aussi la propriété pour les autres points d’entrée. Le message Messenger transporte une publication durable : son identité distante doit donc être stable entre la confirmation et l’exécution asynchrone. Le verrou et la transaction protègent les changements concurrents ; ils ne remplacent pas le contrôle de propriété.

Les tests fonctionnels utilisent exclusivement `shortflow_test`, des identités fictives et des clients HTTP simulés. Les résultats exacts de validation sont consignés à la fin du lot.

## Résultats du 26 octobre 2026

- Migrations appliquées à la base de test avant PHPUnit et à la base locale, sans suppression de connexion. Schémas Doctrine valides ; cache local actualisé.
- Suite PHP complète réussie : **601 tests, 7 268 assertions**. Les trois échecs du premier passage étaient des assertions à actualiser : compter les plans plutôt que tous leurs champs texte, comparer la valeur d’une date et attendre le lien TikTok ciblant son compte.
- Parcours ciblés réussis avant le passage global : **37 tests, 471 assertions**. Les fixtures OAuth incluent les scopes attendus et les entités sont rechargées après les requêtes qui réinitialisent Doctrine.
- Dernier contrôle après actualisation des sources et nettoyage complet du cache de test : **72 tests, 800 assertions**, conteneur et Twig valides. Il couvre notamment le bouton d’application des comptes et l’absence de soumission automatique de leurs listes.
- **136 tests frontend**, `npm run check` et `npm run build` réussis. Avertissements de build existants concernant les polices statiques et la taille du module Three.js.
- PHPStan sans erreur, CS-Fixer sans correction, conteneur Symfony et **100 templates Twig** valides.
- Deux revues indépendantes de sécurité/routage et une revue UX en lecture seule. Pas de nouvelle anomalie bloquante trouvée. Les listes de comptes utilisent une application explicite, sans rechargement à chaque flèche du clavier.
- Recette navigateur sur données synthétiques : six cartes de connexion, comptes distincts par vidéo, créneau propre à chaque ligne, contrôle mobile à 390 × 844 sans débordement horizontal. Aucune mutation sociale depuis le navigateur ; les propriétaires de recette sont supprimés après le rendu.
- Au clavier, passer de B à A puis utiliser Tab conserve la page et déplace le focus vers le compte suivant ; le bouton d’application reste visible. Trois boutons d’ajout et six boutons de reconnexion ciblée vérifiés. Console du compositeur sans erreur JavaScript.

Les accès sociaux commerciaux, les limites propres aux plateformes et les essais avec de vrais comptes supplémentaires restent à vérifier dans leurs lots autorisés. Ce lot ne constitue pas un nouvel essai d’envoi réel.
