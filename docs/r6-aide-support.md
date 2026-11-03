# R6.4 — aide et support de la bêta

R6.4 terminé le 3 novembre 2026. Décision explicite du propriétaire : **aucun délai de réponse annoncé pendant la bêta**. Aucun engagement chiffré ni permanence de support n’est ajouté.

Le contact public et connecté présente l’adresse centralisée `dev@alexbuild.fr`, sélectionnable/copiable, et trois mailto à sujets encodés. Le support par email est indiqué et un accès au guide « Comment ça marche » complète la page. La landing garde ses accès publics ; dans le Studio, l’aide et le contact passent par Mon compte → Besoin d’aide, sans lien Contact dans la sidebar ni le menu. Six destinations métier conservées.

La réception de la boîte est déjà confirmée par le propriétaire. Cela ne valide pas le transport email applicatif de production : Mailpit reste utilisé en local, R8.4 demeure ouvert. Aucun email réel n’a été envoyé pour cette étape.

## Vérifications adaptées

- Migrations test : base déjà à jour, aucune nouvelle migration applicative.
- `phpunit tests/Functional/AccountTest.php --filter 'testContactAndSuggestions|testPublicMobileMenu|testGuide'` : **4 tests / 68 assertions réussis**, accès publics/connectés, GET/POST405, compte, guide et menus. **120 Twig** valides.
- Contact public réel à 320/390/768/1280 px avec JavaScript désactivé : HTTP200, aucune largeur document excédentaire, adresse et trois mailto encodés, aucun formulaire ; clic du guide réussi à chaque largeur. Captures 390 et 1280 inspectées.
- Trois réponses connectées exportées avec un propriétaire synthétique en base test puis rollback : onze compteurs inchangés, zéro HTTP fournisseur. Relecture à quatre largeurs sans JavaScript : aide du compte, clic Contact, présence du nouvel accès guide ; aucune réintroduction dans sidebar/menu et six liens bas conservés. Ancien cache Twig test actualisé avant export final.
- Pas de nouvelle suite globale PHP, frontend ou build : changement limité au texte et à un lien Twig, sans JS/CSS/service métier. Les chiffres historiques restent historiques. Pas de nouvelle recette matérielle iPhone ni de revue globale R6 ; celle-ci est prévue en R6.6.

[Bilan machine](fixtures/r64-support-20261103.json). Aucun push, coût, appel fournisseur réel, activation, publication, migration applicative, worker redémarré ou déploiement.

Prochain point exact : **R6.5**, métadonnées publiques, partage social et indexation volontaire pour le domaine retenu. **Grand R6 toujours en cours**, revue R6.6 requise avant clôture.
