# Séries IA : dates prévues — 7 novembre 2026

## Parcours livré

Depuis une série IA, « Calendrier de la série » ouvre une page dédiée. L’utilisateur choisit début, fin facultative, jours de la semaine, heure, fuseau et nombre d’épisodes. Tous les jours correspondent à un rythme quotidien ; un seul jour à un rendez-vous hebdomadaire. Il y a au plus un créneau par jour choisi, dans les 90 prochains jours, conformément à la limite temporelle du moteur de publication existant.

« Voir les dates proposées » calcule l’aperçu côté serveur, sans sauvegarde ni tâche. Les épisodes existants sont affectés dans leur ordre. Un épisode ayant déjà une publication confirmée est exclu, y compris si son envoi nécessite une reprise ; son brouillon garde sa propre date. Quand le nombre demandé dépasse les épisodes disponibles, l’aperçu indique les épisodes à créer/préparer, sans les créer.

« Enregistrer ces dates prévues » conserve le calendrier dans la série. Si les paramètres, les épisodes disponibles ou la révision de série ont changé depuis l’aperçu, l’utilisateur doit vérifier le nouvel aperçu ou recharger la page. Une saisie invalide n’efface pas le calendrier précédent. Le calendrier enregistré est un instantané daté : un nouveau GET ne décale pas silencieusement ses dates. Les réglages et l’aperçu restent utilisables sans JavaScript.

Ce lot est **une prévision, pas une programmation de publication**. Il ne crée ni épisode, brouillon, récurrence, génération, texte IA, message ni publication. Aucun moteur ne consomme encore ces prévisions. Les récurrences déjà présentes conservent leur parcours d’activation distinct. La préparation automatique des textes et leur liaison à ce calendrier restent les lots suivants, après relecture propriétaire.

## Comprendre les composants Symfony

- `SeriesCalendarType` décrit les champs et validations Symfony. Les cases sont regroupées avec les styles existants, sans nouveau JavaScript ou CSS. La validation serveur fonctionne avec et sans script.
- `SeriesCalendarController`, route privée `/studio/series/{id}/calendrier`, contrôle le propriétaire, le CSRF et les actions aperçu/enregistrement. Il conserve un hash de l’aperçu dans le formulaire rendu pour détecter une modification avant sauvegarde.
- `SeriesCalendar` normalise les règles, réutilise `PublicationTime` pour les dates locales/fuseaux et construit l’aperçu. Les heures ambiguës ou inexistantes sont refusées sur les jours effectivement choisis, jamais décalées automatiquement. Le passage heure d’été/hiver conserve l’heure locale souhaitée.
- À l’enregistrement, les verrous du propriétaire et de la série, la révision et le hash protègent les modifications concurrentes. Le hash sert à relire le bon aperçu ; il ne constitue aucune autorisation de coût ou d’envoi.
- `AiSeries.calendarPlan`, JSON privé, contient règles, créneaux et date de sauvegarde. Sa modification avance la révision de série. `Version20261107030000` ajoute cette seule colonne et refuse un retour arrière destructeur si un calendrier est renseigné.
- `templates/series/calendar.html.twig` affiche formulaire, aperçu et instantané enregistré ; `series/show` fournit le lien. Les textes sont échappés par Twig et les avertissements de saisie non enregistrée réutilisent le contrôleur existant.

Flux : GET privé → formulaire → POST aperçu validé → calcul des créneaux → affichage → POST enregistrement → contrôle de révision et recalcul → comparaison du hash → sauvegarde Doctrine → redirection GET. La base ne change qu’à cette dernière sauvegarde explicite.

## Vérifications

| Banc final, sans addition des passages intermédiaires | Résultat |
|---|---:|
| Dates hebdomadaires/jours choisis, période inclusive, DST et entrées invalides | 4 unitaires / 19 assertions |
| Aperçu/sauvegarde/reprise, aucune tâche, CSRF/propriété/révision, ancien calendrier conservé, publication existante protégée | 3 fonctionnels / 45 assertions |
| Parcours Séries → publication existant | 6 fonctionnels / 102 assertions |
| Activation explicite de la préparation récurrente existante | 1 fonctionnel / 13 assertions |

Soit **10 fonctionnels ciblés / 160 assertions et 4 unitaires / 19**, en passages distincts. Tests exclusivement dans `shortflow_test`, clients externes bloqués. Première recette fonctionnelle : une erreur et un échec dus aux clés non contiguës des cases Symfony ; normalisation corrigée, régression ajoutée et passage final complet vert.

**PHPStan quatre applicatifs**, **CS-Fixer sept PHP**, **21 Twig**, conteneur et schémas test/dev verts ; `npm run check`/`npm run build` réussis. Avertissement Vite de gros chunk déjà connu ; pas de nouvelle suite frontend globale, aucun JS/CSS ajouté. Pas de nouvelle suite PHP globale ni de revue indépendante prétendue.

**14 états Chrome** : initial/aperçu/enregistré en 320/390/768/1280, puis deux vues 390 sans JS. Champs, cases au clavier, fuseau, FormData et actions contrôlés ; aucun débordement, formulaire imbriqué, erreur console/JS ou POST réel. Capture mobile initiale puis finition des jours compacts et aperçu final relus.

## Activation locale et limites

Migration additive appliquée test/dev : une requête applicative, toujours 29 tables, anciens compteurs et empreintes de valeurs identiques avant/après. Cache dev actualisé, quatre workers actifs actualisés après garde file/générations/épisodes/montages à zéro. Onze GET200 authentifiés (dont le calendrier) en transaction READ ONLY, 29 compteurs égaux et zéro tentative HTTP fournisseur.

Aucun contenu propriétaire créé/modifié par l’agent, secret réel déchiffré, modèle réel appelé, coût, upload fournisseur, publication, push ou déploiement public. Les sauvegardes du banc fonctionnel sont synthétiques et uniquement dans la base test.

## Essai propriétaire et suite

Ouvrir une série IA → Calendrier de la série. Choisir une période et deux jours, afficher les dates, vérifier l’ordre et enregistrer. Revenir sur la page pour retrouver les mêmes dates. Ce test n’active aucun envoi et ne nécessite aucun budget. Après validation : relier la préparation des textes à ces prévisions, puis cadrer activation, budget de génération et confirmation de publication sans reprendre un ancien accord consommé.
