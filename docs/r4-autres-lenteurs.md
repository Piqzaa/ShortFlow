# R4.3 — investigation des autres lenteurs

1er novembre 2026. **R4.3 terminé.** Correctif local `6a8bbb4` : catalogue partagé pour la préparation. R4.4 et la revue globale R4.5 restent à faire. La section d'investigation ci-dessous conserve les constats initiaux ; les contrôles et résultats finaux suivent.

## Constats et décisions

Les campagnes R4.1/R4.2 démontrent le gain de la pagination du détail de série. Les autres routes du banc restent à volume HTML presque constant entre les deux jeux. Elles ne démontrent pas une autre lenteur croissant avec le nombre d'épisodes. Les 41 formulaires rendus du Studio incluent les favoris du catalogue : leur nombre seul ne prouve pas un problème de latence et ne justifie pas de supprimer leur fonctionnement natif.

`templates/ai/studio.html.twig` sérialise le catalogue deux fois sur la même section, pour les valeurs Stimulus `preparation` et `creation`. C'est une piste de réduction du HTML, pas encore une mesure de gain serveur ou navigateur. Vérifier aussi les données de l'éditeur de plans avant de choisir un partage : chaque contrôleur doit continuer de fonctionner après modification de modèle, conversion et réponse refusée. Ne pas changer les contrats sans mesurer et vérifier ces parcours.

Le build local Vite 6.4.4 produit `app.js` à 262,35 kB (71,07 kB gzip) et `app.css` à 222,32 kB (39,33 kB gzip). Le chunk Three.js fait 704,89 kB (181,19 kB gzip). Ces chiffres sont des tailles de build, pas des temps d'affichage ni une mesure réseau. Le contrôleur `studio_controller.js` importe déjà GSAP/ScrollTrigger et Three.js dynamiquement ; conserver le chargement conditionnel, le reduced motion et les fallbacks. L'avertissement de chunk supérieur à 500 kB ne suffit pas à justifier un nouveau découpage par écran.

## Contrôles de l'investigation initiale (avant correctif)

- `npm run check` : code 0.
- `npm run test:ui` : **182 tests réussis**, zéro échec, exclusion ou annulation.
- `npm run build` : code 0 ; avertissement de taille Three.js et références absolues médias/polices à résoudre au runtime. Le build ne vérifie pas leur disponibilité HTTP.
- `docker compose ps` : échec, moteur Docker Desktop Linux absent. La demande de démarrage de Docker Desktop a été refusée ; aucun autre lancement tenté.
- PHP hôte détecté : **8.1.10**, inférieur au PHP >= 8.4 requis. Il ne remplace donc pas l'environnement de mesure/test du projet.

Aucune suite PHP, mesure serveur nouvelle, recette navigateur ou revue indépendante de correctif réalisée. Les **811 PHP / 9 328 assertions** de R4.2 restent historiques. Aucun appel IA, fournisseur ou social, migration, activation, push ou modification des sources applicatives.

## Reprise exacte

Le blocage Docker initial a été levé après la demande de poursuite. **Prochain point : R4.4**, isolation, états de publication, historique long, reprise et lectures sans nouveaux appels externes. Aucun découpage d'assets décidé sur les seules tailles du build.

## Correctif et pédagogie

`templates/ai/studio.html.twig` ne rend plus `data-preparation-models-value`. La copie `data-creation-models-value` reste sur la même section. Dans `preparation_controller.js`, le getter du modèle interroge le contrôleur Stimulus `creation` de cette section. Il lit le modèle sélectionné à chaque fois et ne mémorise pas une ancienne sélection. Le rafraîchissement initial était déjà différé en microtask ; la valeur du catalogue est indépendante du `connect()` de creation. L'ancien attribut preparation reste prioritaire s'il existe, pour les anciennes pages. Aucun changement de formulaire, POST, contrôle PHP, consentement ou données humaines.

Symfony/Twig sérialise une fois de moins le tableau côté serveur ; Stimulus continue de décoder sa valeur à la lecture. Ce correctif ne garantit donc pas un objet partagé en mémoire ni un parsing JSON unique. Sans JS, les champs natifs et validations serveur restent inchangés.

`tests/Frontend/preparation.test.js` exerce le vrai getter, le changement de modèle/catalogue, l'identifiant inconnu, l'ancien attribut et le contrôleur temporairement absent. `AiStudioTest` vérifie le catalogue présent et la copie retirée sur un vrai GET authentifié.

## Mesures finales comparables

[Quatre campagnes brutes](fixtures/r4-catalogue-20261101.json) : deux avant sur `6fa30173717807365d464a087b658faabcbec2cb`, deux après sur `6a8bbb42bf2f6458d7c168d18ae543793e2b815e`. Programme R4.1 inchangé, mêmes volumes/préchauffages/échantillons, copie Linux `/tmp/r43` dans edit-worker, PHP 8.4.27, PostgreSQL 16.15, test sans debug, caches préchauffés et aucun test DB concurrent dans ces quatre campagnes retenues. Les dix empreintes après concordent avec les sources locales. Onze comptages avant/après identiques et zéro HTTP interdit pour chaque campagne ; séquences de test consommées comme en R4.1.

Grand jeu, première page ; temps serveur médians A/B, HTML du premier échantillon A/B :

| Route | HTML avant | HTML après | Serveur avant | Serveur après | SQL avant/après |
| --- | ---: | ---: | ---: | ---: | ---: |
| Studio | 278 562 / 278 657 o | 221 544 / 221 459 o | 10,17 / 10,49 ms | 11,67 / 9,26 ms | 5 / 5 |
| Préparation épisode | 308 684 / 308 765 o | 251 622 / 251 682 o | 14,54 / 13,82 ms | 13,02 / 14,00 ms | 7 / 7 |

Environ **57 ko retirés**, soit 20 % du HTML Studio et 18 % de la préparation d'épisode. Les tokens CSRF variables expliquent les petits écarts entre échantillons. Les autres routes/volumes restent consignés, sans gain extrapolé. Pas d'amélioration stable de temps serveur démontrée ; aucun délai utilisateur ou gain de compression réseau promis. Aucun besoin supplémentaire de découpage d'assets démontré.

Incidents écartés : la première campagne après correction réutilisait le cache Twig propre au Kernel du benchmark, distinct du cache test. Ce cache a été déplacé dans la copie temporaire puis régénéré. Un rejeu prématuré pendant PHPUnit a échoué au contrôle des comptages de nettoyage ; il est exclu. Les campagnes finales ont été relancées seulement après fin de PHPUnit. Ces essais ne sont pas mélangés aux quatre résultats retenus.

## Validation finale et limites

- `php scripts/check.php` dans la copie Linux : **811 PHP / 9 330 assertions**, schémas dev/test, lints YAML/Twig/conteneur, PHPStan et style réussis. Migrations test déjà à jour ; aucune migration applicative ajoutée. Le chevauchement exploratoire mentionné ci-dessus n'a pas produit d'échec PHPUnit ; il interdit seulement de retenir cette mesure exploratoire.
- `npm run check`, `npm run test:ui` (**183 tests**) et `npm run build` réussis. Avertissements de build inchangés : chunk Three.js et chemins médias/polices résolus au runtime.
- Revue indépendante en lecture seule : aucun blocage identifié sur le partage, ordre de connexion, compatibilité et tests ; aucune suite DB lancée par le relecteur.
- Deux réponses Symfony réelles exportées avec propriétaire synthétique : GET Studio 200 et POST CSRF refusé 422 ; transaction annulée, HTTP fournisseurs interdits. Un serveur de recette GET uniquement a été préparé sur boucle locale. **La recette navigateur n'a pas abouti** : le navigateur intégré ne peut joindre le port local Windows (timeout), aucun navigateur local n'est proposé par son inventaire. Pas de nouvelle capture desktop/mobile ni de vérification visuelle revendiquée. La recette interactive élargie reste à faire en R4.4 ; les tests automatisés couvrent ici le changement sans modification visuelle.

Essai manuel à la reprise : ouvrir Studio puis une préparation d'épisode, changer de modèle et de durée, vérifier les limites proposées, revenir à un ancien modèle, puis contrôler une saisie refusée et les champs natifs sans JS. Ne confirmer aucune génération pour cet essai.

Aucun service PHP modifié : pas de redémarrage des workers ni changement d'architecture. Aucun appel IA réel, dépense, publication, activation, déploiement ou push. Le grand R4 n'est pas clôturé.
