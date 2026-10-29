# Réglages compacts et prompt complet — 29 octobre 2026

**Complément suivant :** fin masquée en mode personnage, aide de découpage unique, modèle/connexion rapprochés, nom masqué sans perte et son directement accessible. [État actuel et preuves](r3-studio-simplifie.md). Le bilan ci-dessous décrit le complément précédent.

Complément demandé après essai du Studio, avant R3.4. Le propriétaire souhaite moins de lignes pour durée/mode/nombre de plans et aucun contexte avancé répété dans les cartes. R3.4 reste non commencé ; grand R3 en cours.

## Comportement livré

Sur ordinateur, durée totale, mode et nombre de plans sont alignés. Sur mobile, durée et nombre occupent la première ligne, puis le mode. Les explications longues sont repliées dans « Comprendre le découpage ». Lorsque les cartes correspondent déjà au découpage choisi, le bouton « Appliquer ce découpage » et le second résumé de durée sont masqués. Ils restent disponibles quand une action ou un diagnostic est nécessaire. Sans JavaScript, les champs, explications et bouton serveur restent utilisables.

« Contexte avancé » disparaît des cartes. Le champ contient les consignes complètes de la scène, identité et style compris. Les propositions les préfixaient déjà ; ils ne sont plus ajoutés une seconde fois depuis un contexte caché après enregistrement de ce nouveau format. Les consignes techniques du modèle et les références restent composées par le serveur et visibles au récapitulatif.

Pour un ancien plan, le formulaire reprend le contexte dans le champ s'il manque. Il conserve le texte complet, sans troncature ; au-delà de 1 500 caractères, la validation demande une correction avant sauvegarde. Les données enregistrées ne changent pas pendant un simple GET. Les anciens formulaires soumis sans marqueur gardent leur contrat historique ; les demandes déjà confirmées/transmises ne sont pas réécrites par cette présentation.

L'identité traduite et son empreinte sont conservées dans le JSON du plan comme provenance. Le marqueur `promptOnly` distingue le contrat historique, le prompt complet lié à sa source (`1`), et la saisie reprise explicitement par l'utilisateur (`manual`). Aucun schéma ni migration n'est ajouté. Une identité retirée d'un prompt complet ne réapparaît pas au rechargement.

Après modification du brief, le prompt traduit reste signalé jusqu'à nouvelle proposition ou reprise manuelle. Un seul volet global « Reprise manuelle » permet de relire/corriger les prompts puis de choisir « Utiliser mes prompts relus », sans nouvelle inférence. Cette action CSRF conserve l'archive, change la révision et ne déclenche aucune vidéo. Les contrôles langue/répliques portent toujours sur le prompt effectif ; un nouveau devis et son consentement restent nécessaires. La reprise ne certifie pas la fidélité des scènes.

## Fichiers et rôle des composants

- `templates/ai/_timing.html.twig`, `_plan_count.html.twig`, `_plan_count_status.html.twig` et `studio.html.twig` : regroupement des champs, explications et fallback serveur. `episode.css` définit les trois colonnes puis les deux lignes mobiles.
- `AiShotType`, `AiIdeaType`, `AiStoryType` et les contrôleurs Studio/épisode : construction du champ complet depuis l'ancien contexte, marqueur validé et transport dans le formulaire. `PRE_SET_DATA` adapte la représentation initiale ; il ne sauvegarde pas l'entité.
- `_shot_card.html.twig` et `_preparation_plans.html.twig` : suppression du bloc répété et action globale de reprise. Résumés français, références et images restent conservés.
- `AiPlanInputs`, `AiShot` et `AiEpisodeFlow` : validation/enregistrement du marqueur dans le JSON existant, composition depuis le champ complet, devis/révision et confirmation préservés.
- `VideoPromptLanguage` : contrôle des directions effectives ; le lien à l'ancienne source reste actif jusqu'à la reprise humaine explicite. L'archive ne réinjecte plus d'anciennes instructions dans un prompt complet.
- Contrôleurs Stimulus `preparation`, `story_writer`, `shot_editor` : conservation du marqueur lors des copies/redécoupages, empreinte des saisies et présentation adaptée au mode. Ils ne remplacent pas les validations serveur.

Flux Symfony : formulaire construit depuis la préparation → ancien contexte rendu dans le champ si nécessaire → édition ou application volontaire → POST validé et sauvegarde révisionnée → devis construit depuis le prompt complet → confirmation distincte → génération existante. Le HTML caché transporte des métadonnées ; il ne constitue pas une preuve d'autorisation ou de fidélité.

## Vérifications exécutées

- Nouveaux tests unitaires de formulaire : contexte visible une seule fois, archive non réinjectée après édition/reprise, ancien POST sans marqueur, ancien contexte conservé et dépassement non tronqué. Premier passage **47 / 163, un échec d'attente** sur la valeur d'un champ absent (`null`, pas chaîne vide) ; attente corrigée, **47 / 163 verts**.
- Premier passage complet : **787 tests / 8 919 assertions**, **une erreur et trois échecs**. Erreur : rafraîchissement d'une série détachée devenu inutile pour un plan sans provenance active ; filtre rétabli. Attentes ajustées au contexte désormais visible, au bouton déplacé et au texte complet conservé pendant une modification de durée. Aucun test retiré ni protection désactivée.
- Rejeu des six cas concernés et de reprise : **6 tests / 93 assertions verts**. Revue indépendante : ancien blocage manuel après changement de brief identifié puis corrigé par l'action explicite ; seconde lecture sans blocage concret restant identifié.
- Suite complète suivante : **787 tests / 8 971 assertions verts**, **2 min 03 s / 78,5 Mio**. Conteneur dédié, sources/dépendances copiées sur disque Linux, chemin réel du Kernel vérifié ; base exclusivement `shortflow_test`, migrations avant PHPUnit, aucune suite DB concurrente. Validations des schémas dev/test, 19 YAML, 108 Twig, conteneur, PHPStan et style réussies. Avertissements de métadonnées tar/volume Compose déjà connus, conteneur temporaire retiré automatiquement.
- Après les derniers libellés/espacements et le message de reprise : **7 tests / 50 assertions ciblés verts**, **165 frontend**, `npm run check` et build Vite verts. **30 Twig** du dossier AI lintés après séparation du message de découpage. Pas de troisième passage PHP complet pour ces finitions. Avertissements Vite de médias/polices et taille Three.js conservés.

Recette sur les fragments Twig réels et assets compilés, quatre scènes synthétiques et serveur loopback GET uniquement : trois sélecteurs alignés en desktop ; mode unique limitant les choix natifs et cachant le nombre, retour multiscènes ; édition/focus clavier ; passage 40/4 à 40/5 produisant cinq durées de 8 s, quatre textes conservés et cinquième champ vide, puis retour à quatre sans perte. Aucun bloc de contexte séparé. Mobile 390 × 844 : largeur utile/scroll **375/375**, aucune erreur/alerte console capturée. Cette recette n'est pas une inspection de l'onglet propriétaire ni un essai fournisseur. Capture locale `artifacts/r3-light/reglages-compacts.png`, non versionnée. Onglet fermé, viewport restauré, serveur arrêté.

Cache dev/test et assets actualisés, quatre workers relancés après garde zéro messages/rédactions/occurrences/générations/montages actifs. Aucun appel de modèle réel, vidéo, coût, publication, activation, migration applicative ou push. Commit local : `feat: compact video settings and use complete scene prompts`.

## Comment essayer

Recharger avec Ctrl+F5. Vérifier les trois réglages, changer le mode et le nombre de plans, puis modifier un prompt : une ancienne identité supprimée ne doit pas être ajoutée au récapitulatif. Si le brief a changé, utiliser une nouvelle proposition ou la reprise manuelle après relecture. Vérifier le texte exact et le coût avant toute confirmation ; aucun essai payant n'est nécessaire pour constater ces changements.

**Prochaine tâche : R3.4**, après retour sur ces détails. La revue globale R3.7 reste à réaliser.
