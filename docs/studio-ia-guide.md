# Studio IA simple et guidé — 20 octobre 2026

## Objectif et périmètre

Lot autorisé par le propriétaire après l'audit : rendre le choix des modèles plus lisible, expliquer les accès, suivre une génération sans actualisation manuelle, reprendre une idée et retrouver tout l'historique. Aucune génération réelle ni publication déclenchée pendant ce lot. Image vers vidéo, montage, plusieurs plans et séries restent les prochains lots.

## Parcours

1. Écrire l'idée, rechercher et choisir un modèle/fournisseur, puis sa résolution.
2. Choisir la durée et le format compatibles, vérifier le résumé de connexion et l'estimation.
3. Préparer le récapitulatif ; confirmer séparément le coût avant toute demande fournisseur.
4. Le worker soumet le travail une fois puis récupère son résultat. La page consulte uniquement l'état local et se recharge lorsque cet état change.
5. Sur le résultat : aperçu, téléchargement, publication ou réutilisation de l'idée. L'historique permet recherche par nom, filtre d'état et pagination.

## Choix du modèle et connexions

Le formulaire amélioré présente 15 choix modèle/fournisseur et une résolution séparée. Les 21 identifiants d'exécution restent inchangés. Le sélecteur Symfony d'origine reste le fallback sans JavaScript et la valeur soumise au serveur ; il n'est masqué qu'après initialisation de Stimulus. Les contrôles améliorés sont désactivés avant cette initialisation pour ne pas bloquer la validation native du fallback.

Changer de modèle filtre les résolutions/durées/formats incompatibles. Un filtre de recherche ne remplace pas le modèle choisi et ne provoque ni soumission ni avertissement de départ. Les favoris ★ se mettent à jour dans les deux sélecteurs. Le catalogue détaillé reste accessible sous le formulaire ; le bloc annonçant le futur montage a été retiré de l'aperçu.

Le volet Connexion et facturation résume fournisseur et méthode enregistrée, puis s'ouvre lorsqu'un accès manque. Le choix API/MCP reste explicite, sans repli automatique en cas d'échec. Les aides repliables dans Connexions expliquent le parcours navigateur Fal et la création d'une clé dédiée chez chaque fournisseur, avec liens officiels. Une clé enregistrée ne prouve ni le crédit disponible ni l'accès à tous les modèles.

## Suivi automatique

`GET /studio/ia/generations/{id}/etat` vérifie le propriétaire et ne renvoie que l'état et sa révision. Aucun prompt, jeton, reçu distant ou appel fournisseur. Réponse privée non stockable. La révision est un timestamp canonique avec microsecondes, identique dans Twig et JSON, pour éviter les rechargements causés par des fuseaux différents.

`generation_watch_controller.js` consulte cette route pour les états queued/submitting/processing, de 6 à 20 secondes d'intervalle. Requête bornée à 10 secondes, pause si l'onglet est caché, annulation à la sortie et arrêt après trois erreurs avec lien manuel conservé. Une transition ou une nouvelle révision provoque le rechargement de la page. Il n'y a aucun POST de génération dans ce contrôleur et aucune progression en pourcentage inventée. Les idées à confirmer et les résultats terminés ne sont pas interrogés périodiquement.

## Historique et reprise

`AiGenerationRepository` porte maintenant la requête réellement nécessaire : propriétaire obligatoire, recherche littérale dans le nom (caractères SQL `%` et `_` échappés), état autorisé, ordre déterministe et 12 résultats par page. Le résultat vidéo est joint pour éviter des requêtes répétées lors de l'affichage des couvertures.

`POST /studio/ia/generations/{id}/reutiliser` vérifie propriétaire et CSRF puis copie titre, prompt, modèle, durée, format et méthode d'accès dans la session du formulaire. Il ne crée aucun travail et ne modifie pas l'original. Le prochain récapitulatif recalcule l'estimation courante ; un nouvel envoi exige toujours une nouvelle confirmation. Reprendre une idée remplace la préparation de formulaire conservée dans cette session.

Les couvertures de l'historique réutilisent le chargement limité des vignettes privées. Une vidéo retirée garde son entrée d'historique et son idée, avec un état explicite.

## Place disponible avant une dépense

`AiGenerations::assertCapacity` vérifie le nombre de vidéos et le quota de bibliothèque avant confirmation, à la reprise d'un accès manquant et juste avant un nouveau POST fournisseur. Chaque génération active compte prudemment pour une place et 100 Mo. Celle que le worker contrôle est incluse une seule fois. Plusieurs générations en attente sont donc prises en compte, plutôt qu'un simple contrôle de bibliothèque non pleine.

Cette marge est un calcul au moment du contrôle, pas une réservation exclusive persistée : un import parallèle ou un résultat dépassant 100 Mo peut encore empêcher la récupération après facturation. Le contrôle final de `VideoLibrary` reste obligatoire. Le résultat déjà facturé et son reçu sont conservés pour reprendre la même récupération sans nouvelle génération. Le contrôle préalable ne bloque pas une consultation d'état ou une réutilisation d'idée.

## Fichiers et rôles Symfony

- `AiStudioController` : actions de suivi, historique et reprise, avec propriété/CSRF et réponses privées.
- `AiGenerationRepository` et mapping de `AiGeneration` : requête d'historique et libellés d'état communs ; aucun changement de schéma, aucune migration nécessaire.
- `AiGenerations` : contrôle métier de capacité dans le parcours asynchrone existant.
- `templates/ai/studio.html.twig`, `history.html.twig`, `generation.html.twig`, `_reuse.html.twig`, `keys.html.twig` : HTML accessible, fallback et actions utilisateur.
- `creation_controller.js` : sélection modèle/résolution, recherche, favoris et résumé de connexion ; `generation_watch_controller.js` : lecture périodique locale uniquement.
- `assets/styles/ai-studio.css` : réglages, historique et actions adaptés aux petites largeurs ; `assets/app.js` : déclaration du contrôleur.

À comprendre : le FormType et les services Symfony valident toujours la configuration d'exécution. Stimulus ne remplace pas cette validation et ne possède aucune clé. La lecture d'un état par le navigateur et le traitement Messenger sont deux flux distincts. Un repository apparaît ici parce que la recherche paginée exige maintenant une requête métier dédiée.

## Essais manuels sans génération payante

1. Choisir Pika puis 1080p : vérifier estimation et durée de 5 s. Choisir LTX 4K : vérifier 6/8/10 s et invitation LTX sans clé correspondante.
2. Ajouter un favori dans le catalogue : vérifier ★ dans le choix principal. Rechercher un nom inexistant : la sélection reste conservée.
3. Ouvrir l'aide Connexions ; ne coller une vraie clé que dans le champ sécurisé de VydFlow, jamais dans un prompt ou le chat.
4. Ouvrir une création existante et « Réutiliser cette idée » : les champs sont repris mais aucune génération ne démarre.
5. Historique : recherche par nom, état, page suivante, puis accès avec un autre compte ; les créations restent isolées.
6. Sur une génération simulée en cours, vérifier mise à jour automatique, pause de l'onglet caché, reprise et lien manuel si le réseau échoue.

Les essais fournisseur réels, notamment la première génération via MCP, restent séparés et nécessitent la confirmation du modèle, du compte et du coût.

## Ajustements après retour utilisateur : recherche et navigation

Objectif : afficher directement des résultats cliquables lors de la recherche d’un modèle et conserver les choix sans dialogue bloquant au changement d’onglet. Le sélecteur reste disponible sans recherche ; un clic sur un résultat remplit la configuration native et place le focus sur la résolution. Les favoris, résolutions et fournisseurs à connecter restent visibles.

`templates/ai/studio.html.twig` remplace le contrôleur `unsaved` uniquement sur le formulaire de création par `idea-save`. Le nouveau `assets/controllers/idea_save_controller.js`, déclaré dans `assets/app.js`, enregistre les champs nommés du formulaire après 500 ms de saisie ou immédiatement après un choix. `pagehide` envoie les dernières modifications avec `fetch keepalive` ; le statut confirme la réception, et une erreur propose de réessayer. La recherche elle-même n’est pas enregistrée. Sans JavaScript, le formulaire et sa validation restent utilisables, avec une indication d’ouvrir le récapitulatif pour conserver l’idée.

Flux Symfony : `POST /studio/creation/idee` → utilisateur vérifié et formulaire `AiIdeaType` avec CSRF → groupe de validation `autosave` (idée incomplète acceptée, longueurs et choix contrôlés) → session privée `ai_idea` → réponse JSON sans cache. Aucun enregistrement de génération, appel fournisseur ou message Messenger. Les numéros de séquence rejettent les anciennes requêtes d’un même onglet ; une révision renouvelée au récapitulatif ou lors d’une reprise empêche une sauvegarde tardive de les écraser. L’idée est conservée pendant la session, pas comme projet durable après déconnexion/expiration, et la livraison d’une requête de départ dépend du navigateur et du réseau.

À comprendre en Symfony : les groupes de validation permettent de sauvegarder un formulaire inachevé tout en gardant les contrôles complets avant génération. La session sert ici de mémoire temporaire ; elle ne remplace pas une entité de projet durable.

Tester : saisir une idée sans modèle, attendre « enregistrés », ouvrir Calendrier puis revenir au Studio ; vérifier prompt et réglages. Chercher Pika, cliquer sur le résultat, choisir 1080p ; rechercher un nom inexistant sans perdre ce choix. Tester clavier et téléphone. Couper le réseau pendant une modification : le statut propose une nouvelle tentative sans annoncer de réussite.
