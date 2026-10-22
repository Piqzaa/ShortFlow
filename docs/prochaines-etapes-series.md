# VydFlow — rapport de suite du chantier

État au 22 octobre 2026, après le commit local `8531512`.

## Ce qui est livré

Le premier lot Séries est enregistré dans Git : configuration privée d’un univers, trois épisodes initiaux, prompts modifiables, modèles et accès BYOK existants, images communes et préparation individuelle d’une génération. Le suivi réutilise les statuts IA réels. Une confirmation du coût devenue obsolète après modification du prompt est refusée.

Les briques bibliothèque, génération, montage, brouillons, calendrier et publication existent déjà. Le chantier consiste à les relier au parcours Série, puis à préparer l’exploitation commerciale.

Validation du lot avant commit : **442 tests PHP / 5 797 assertions**, **92 tests frontend**, contrôles qualité et build réussis. Les derniers ajustements du parcours ont également passé les 9 tests Séries / 91 assertions. Vérification visuelle desktop/mobile sur données synthétiques. Aucun test payant ou social réel ajouté. Détails : [verification.md](verification.md) et [series-mvp.md](series-mvp.md).

## Ordre recommandé

| Ordre | Lot | Résultat pour le créateur | Complexité estimée |
| --- | --- | --- | --- |
| 1 | Génération d’un lot fini | Sélectionner plusieurs épisodes et confirmer une estimation totale une seule fois | Moyenne à élevée |
| 2 | Validation et programmation depuis la série | Vérifier les vidéos et remplir son calendrier depuis la liste des épisodes | Moyenne à élevée |
| 3 | Gestion quotidienne des séries | Ajouter des épisodes et archiver une série sans perdre son historique | Faible à moyenne |
| 4 | Quotas de bibliothèque par offre | Comprendre son espace disponible et les limites Gratuit/Creator | Moyenne |
| 5 | Préparation de la commercialisation et abonnement | Utiliser le service dans un environnement durable avec une offre claire | Élevée pour l’ensemble, à découper |

Ces estimations comparent les lots entre eux ; elles ne constituent pas un calendrier de livraison. Les prérequis d’accès aux plateformes et d’hébergement doivent être recensés dès maintenant, car ils peuvent retarder l’ouverture publique indépendamment du code.

## Lot 1 à attaquer ensuite : génération par lots

**Périmètre conseillé : les trois épisodes actuels, lancés à la demande.** Garder la page commune, ajouter la sélection des épisodes et un récapitulatif total. La génération récurrente selon une fréquence reste ultérieure.

À réaliser :

- Enregistrer et vérifier les prompts sélectionnés avant de préparer le récapitulatif. Montrer les épisodes inclus, leurs réglages effectivement utilisés, leur coût estimé individuel et le total indicatif en dollars US.
- Réutiliser `AiCatalog`, `AiKeys`, `AiSeriesStudio`, `AiGenerations`, les messages existants et le worker `background`. Mutualiser les contrôles nécessaires sans créer de second moteur IA.
- Contrôler l’ensemble du lot avant sa mise en file : propriété, révisions, images, accès au fournisseur, fraîcheur des tarifs, places disponibles et stockage réservé.
- Conserver la limite actuelle de trois générations actives par compte. Si d’autres vidéos sont déjà en cours, expliquer la capacité restante ; commencer par bloquer le lot trop grand plutôt que construire une file d’attente supplémentaire.
- Confirmer le contenu exact et le coût total sous verrou. Éviter une simple boucle de confirmations indépendantes qui laisserait une partie du lot engagée si un contrôle suivant échoue.
- Rendre la confirmation idempotente : double clic, rafraîchissement ou reprise réseau ne doivent pas lancer de deuxième demande fournisseur.
- Afficher ensuite les états indépendants dans la liste. Un échec d’épisode ne doit pas masquer les réussites des autres. La récupération d’une demande distante existante conserve les protections actuelles contre les doubles facturations.

La confirmation locale peut être cohérente pour tout le lot ; les résultats chez les fournisseurs restent indépendants et ne peuvent pas être annulés comme une transaction en base. Le récapitulatif doit donc annoncer une estimation et les erreurs doivent distinguer refus certain et résultat à vérifier.

**Critères de fin du lot :** aucune requête fournisseur avant confirmation ; un total correspondant exactement aux épisodes acceptés ; refus d’un ancien récapitulatif modifié ; absence de doublon ; refus avant lancement si la capacité manque ; résultats réussis retrouvés dans la bibliothèque et la série. Tests avec API simulées, sans nouvel essai payant implicite.

## Lot 2 : valider et programmer les épisodes

Ajouter une validation éditoriale explicite par épisode, activée par défaut dans le parcours : une vidéo générée n’est pas automatiquement prête à publier. Conserver l’accès aux outils de montage existants et permettre de choisir le résultat final destiné à la publication.

Depuis l’épisode validé, réutiliser `PublicationDrafts`, le formulaire de préparation, le calendrier et les adaptateurs sociaux existants. Prévoir la date, le fuseau, les destinations et les descriptions propres à chaque réseau. Une proposition de créneaux peut ensuite aider à remplir la semaine ; elle ne doit pas devenir une génération automatique.

Relier les publications à l’épisode pour afficher les états par destination sans recopier leurs statuts. Vérifier le comportement lorsqu’un média est remplacé, une programmation annulée, un créneau manqué ou une seule destination en échec. Une réussite sur un réseau doit rester conservée lors d’une reprise sur un autre.

## Lots 3 et 4 : gestion simple et stockage

Pour les séries : ajout manuel d’épisodes, archivage et retrait d’une image de fin facultative. Donner la priorité aux opérations qui permettent une deuxième semaine de production. La suppression et le nettoyage des références doivent préserver les images encore utilisées par une préparation ou un historique.

Pour les offres : remplacer progressivement les limites fixes par une politique de stockage commune, configurable côté serveur. Elle doit être utilisée par les imports, les résultats IA et les exports de montage, avec prise en compte des réservations de traitements actifs. Afficher l’espace utilisé et disponible ainsi que la raison d’un refus.

La bibliothèque limite actuellement chaque compte à **1 Go / 100 vidéos**, avec **100 Mo par fichier**. Les images IA disposent d’un plafond technique distinct de **100 Mo**. Les plafonds Gratuit/Creator restent à choisir ; aucune valeur commerciale n’est fixée dans ce rapport. Séparer la limite totale du compte, la taille maximale par vidéo et la conservation éventuelle des fichiers.

En cas de dépassement après changement d’offre, recommandation : conserver l’accès aux médias existants et bloquer les nouveaux ajouts jusqu’à libération de place. Ne pas supprimer automatiquement les vidéos d’un utilisateur.

## Avant l’ouverture commerciale

Faire une recette complète sur un petit ensemble de modèles annoncés comme éprouvés : série → génération → montage facultatif → validation → programmation → résultat réel par destination. Le catalogue étendu existe, mais un essai réussi sur un modèle ne valide pas tous les autres.

Vérifier dans un lot distinct les accès et validations nécessaires pour des utilisateurs extérieurs sur chaque réseau, les redirections OAuth, la programmation TikTok réelle encore à tester, les statuts de visibilité et les limites de publication constatées. Les essais historiques d’un compte propriétaire ne prouvent pas à eux seuls la disponibilité générale du service.

Préparer ensuite l’hébergement durable : workers et calendrier opérationnels en continu, stockage privé persistant, sauvegardes avec essai de restauration, secrets et suivi des erreurs. Le fonctionnement local et le tunnel d’essai ne constituent pas une exploitation commerciale. Aucun déploiement ni service payant n’est autorisé par ce rapport.

Valider l’offre Gratuit/Creator et ses limites avant d’ajouter la facturation. L’hypothèse reste **Creator à 9,99 €/mois**, abonnement logiciel seulement, coûts IA directement payés au fournisseur, sans troisième abonnement. Le paiement demandera son propre lot : état d’abonnement fiable, événements idempotents, résiliation et correspondance avec les droits d’usage.

## Ce qui reste reporté

Récurrence entièrement automatique, mémoire narrative, rédaction IA des épisodes, garantie de continuité parfaite des personnages, éditeur vidéo complet, wallet IA, troisième offre et fonctionnalités sociales secondaires. Ces ajouts ne sont pas nécessaires pour valider le premier parcours commercial de séries préparées en avance.

**Prochaine action recommandée : cadrer puis développer uniquement le lot 1, avec sélection d’épisodes et estimation totale.** Les autres lots restent des étapes recommandées, sans implémentation dans ce rapport.
