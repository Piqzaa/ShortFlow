# R3.5 — Récapitulatif et corrections accessibles

Le 29 octobre 2026, le propriétaire demande de poursuivre les simplifications déjà faites, sans refaire l’interface. R3.5 complète l’accès aux informations et aux actions essentielles avant génération. R3.6 et la revue globale R3.7 restent distincts.

## Résultat

Le récapitulatif commence par un encadré commun : nombre de plans, durée, modèle/résolution/format, fournisseur et méthode de connexion, coût total estimé. Deux liens permettent d’atteindre les plans/références ou la zone de connexion/confirmation. Ce sont des liens de navigation : ils ne valident rien et ne lancent aucun appel.

Dans chaque appel du devis, le rôle des références reste visible : personnage ou début, image du clip précédent, fin importée ou issue d’une version existante, nombre de références d’identité. Les images, vues et prompt exact sont repliés ensemble par plan. L’ancienne seconde liste de prompts bruts est retirée : le texte à relire est celui calculé dans le devis. Cette présentation couvre aussi le remplacement d’un plan et les plans restants, dont les références peuvent différer des scènes originales.

Le fournisseur et la méthode accompagnent également la confirmation. Une clé API ne vaut pas une connexion Fal par navigateur. Si l’accès choisi manque, l’interface indique comment le connecter et désactive la confirmation ; la garde serveur reste indispensable et inchangée. La désactivation locale des générations conserve son message distinct. La recette figée de l’épisode fournit ces informations, même si la série a changé depuis.

Une correction de prompt refusée conserve maintenant le texte humain dans la réponse, associé à l’erreur et protégé par l’avertissement de sortie. Cela concerne le remplacement d’un plan et la correction des plans non transmis, y compris l’identité traduite. Si le devis est aussi indisponible, la correction de remplacement reste récupérable dans son champ. Aucune saisie refusée n’est enregistrée automatiquement ; aucun ancien texte n’est écrasé. Twig échappe ces valeurs.

## Fichiers et flux Symfony

- `AiEpisodeController` lit la disponibilité de l’accès du propriétaire ; après un refus métier, il transmet uniquement la saisie à réafficher pour le plan concerné. Le contrôle de propriété et le CSRF précèdent cette récupération.
- `templates/ai/_review_summary.html.twig` est partagé entre devis d’épisode et ancienne préparation d’un seul clip.
- `templates/ai/_reference_review.html.twig` présente les références et le prompt exact issus de `AiEpisodeFlow::quote()`. Il est désormais inclus pour tous les devis.
- `_repair_prompt.html.twig` et `_remaining_prompt_correction.html.twig` réaffichent la saisie refusée du bon plan ; `unsaved` protège son départ sans la persister.
- `assets/styles/episode.css` adapte l’encadré au mobile et garde les destinations des liens accessibles au focus clavier.

Le flux reste : préparation enregistrée → devis calculé par le serveur → relecture → case de coût et confirmation explicite → garde métier → traitement existant. La vue n’est jamais une autorisation de coût. Les services de génération, devis, confirmations et le schéma ne changent pas.

## Contrôles

Les tests utilisent exclusivement `shortflow_test`, migrations appliquées avant PHPUnit et aucune suite DB concurrente. Les HTTP fournisseurs sont simulés. Les nouveaux cas vérifient coût/références/empreinte inchangés en GET, clé manquante et différence API/MCP, correction rejetée sans perte ni persistance, et récupération quand le devis échoue aussi. Le cas de remplacement existant est renforcé, ainsi que la confirmation d’une ancienne préparation Studio.

Premier ciblage : 3 tests, 55 assertions, une erreur de fixture détachée et un échec de comparaison des blancs HTML. Références d’entités rechargées et comparaison du texte exact sans normalisation HTML ; aucune attente métier affaiblie. Rejeu : **3 tests / 89 assertions réussis**, puis **1 test / 9 assertions** sur le devis indisponible.

Résultats finaux : **794 tests PHP / 9 101 assertions**, **173 frontend** réussis ; schémas dev/test, YAML/Twig/conteneur, PHPStan, style, syntaxe JS et build verts. Un premier passage complet (794 / 9 094) a révélé une erreur de fixture API/MCP : le constructeur historique de découpage ne prend pas le mode `single`. Fixture corrigée vers `independent`, garde applicative conservée, ciblage **1 / 8** puis suite complète verte. Aucun test ignoré. Avertissement Vite connu sur Three.js.

Commandes : `npm run test:ui`, `npm run check`, `npm run build`, `php scripts/check.php` dans un `edit-worker` temporaire avec `APP_ENV=test` et `APP_DEBUG=0`. Copie Linux des sources/vendor pour éviter la lenteur du montage Windows, Kernel chargé vérifié, caches/artifacts/variables locales exclus. Le script applique les migrations de test puis exécute les contrôles et la suite PHPUnit. Aucun schéma applicatif ajouté.

Recette : vrais GET Symfony authentifiés en test, exportés avec un propriétaire synthétique puis servis sur boucle locale avec les assets construits. Huit plans / 60 s avec raccords et image synthétique, et ancienne préparation d’un clip. Desktop et mobile 375 × 812 sans débordement horizontal ; liens activables au clavier avec focus sur `review-plans`, `story-quote` et `ai-state-title`, images/prompts repliés ouvrables, image chargée et aucune erreur console relevée. Le serveur de recette remplace uniquement la lecture de l’image synthétique ; aucun POST navigateur ni connexion réelle. Les erreurs et sauvegardes sont vérifiées fonctionnellement. Deux premières fixtures de recette ont été refusées correctement (découpage non proposé, puis cadres manquants) ; passage par la préparation publique avec raccords, sans affaiblir les gardes. Captures locales : `artifacts/r3-light/r35-review-desktop.png`, `r35-review-mobile.png`, `r35-single-mobile.png`.

Revue indépendante finale : aucun blocage restant identifié après ajout des références exactes pour tous les devis et du champ de récupération sans devis. Cache dev et quatre workers actualisés après garde à zéro traitement actif. Commit local `feat: keep generation review and rejected edits accessible`, aucun push.

## Essai manuel

Ouvrir un devis de plusieurs plans : vérifier l’encadré puis ouvrir les détails d’un plan. Les liens doivent atteindre les références et la confirmation au clavier. Sans clé vidéo, la connexion manquante reste explicite et la génération désactivée. Ouvrir également un devis de remplacement ; vérifier les références de cet appel. Une correction refusée doit rester dans son champ. Aucun lancement payant n’est nécessaire pour vérifier ces points.

## Limites et reprise

Aucun appel modèle réel, coût, génération vidéo réelle, publication, activation, migration applicative ou push. La recette synthétique ne valide pas une qualité audiovisuelle ni un compte fournisseur réel. Prochain point après clôture : **R3.6**, navigation après `forward`, libellés et informations de disponibilité/reprise. Grand R3 en cours ; sa revue globale reste R3.7.
