# Audit du Studio IA — 20 octobre 2026

Analyse du code actuel et des guides officiels de Runway, Krea, Google, LTX, Fal et Replicate. Ce document propose les prochains lots ; il ne les implémente pas et n'autorise aucune dépense. Les interfaces concurrentes sont étudiées par leur documentation publique, sans essai de leurs comptes payants.

## Verdict produit

La base permet de préparer un plan, d'en confirmer le coût, de suivre une génération asynchrone, de récupérer une vidéo privée puis de préparer sa publication. Elle ne constitue pas encore le parcours complet de production de vidéos courtes prévu par la roadmap : références visuelles, plusieurs plans, montage et séries restent à construire.

Le prochain gain vient du parcours et de l'itération. Continuer d'enrichir le catalogue par lots vérifiés, mais conserver un écran principal simple. La promesse est de créer, préparer et publier depuis VydFlow, avec le compte fournisseur de l'utilisateur.

## État effectivement vérifié

- 21 configurations d'exécution, regroupées en 15 cartes modèle/fournisseur ; Fal, Replicate et LTX direct.
- Résolution, durée, format et audio explicites ; prix indicatif avant confirmation ; filtres de prix, résolution et favoris.
- Connexion réelle Fal par clé API et connexion navigateur OAuth/MCP confirmées. Une génération réelle Kling 2.5 Turbo Pro par clé a réussi. Une connexion MCP réussie ne valide pas encore une génération MCP.
- Les autres configurations ont une intégration locale et des contrôles simulés ; elles n'ont pas toutes été générées réellement avec un compte approvisionné.
- Conservation du prompt pendant une connexion, secrets chiffrés, propriété des ressources et CSRF, reprise du résultat existant sans nouveau POST facturable lorsqu'un reçu fournisseur existe.
- Le mode API/MCP choisi reste explicite : aucun remplacement silencieux après erreur. Le compte actif MCP Fal est vérifié avant soumission.

## Enseignements des autres générateurs

Krea place le prompt au centre, les réglages près de lui et l'historique dans des sessions. Son parcours documente texte, image de départ/fin et prolongation. Runway expose les réglages compatibles avec le modèle puis les actions sur le résultat : favori, téléchargement, agrandissement et réutilisation. Ces choix suggèrent pour VydFlow : une entrée simple, des options progressives et une continuité entre résultat et prochaine action.

Ne pas reprendre les tableaux marketing de « meilleur modèle » comme un classement objectif. Les capacités et modèles cités dans les guides concurrents peuvent également être en retard sur leurs API.

Sources : [Krea Video](https://www.krea.ai/docs/user-guide/features/video), [Runway Gen-4.5](https://help.runwayml.com/hc/en-us/articles/46974685288467-Creating-with-Gen-4-5).

## À simplifier

1. Unifier le choix : les cartes regroupent les résolutions mais le formulaire présente encore 21 options. Cible : un sélecteur recherchable de modèles, favoris visibles, puis résolution indépendante et compatible. Conserver un fallback HTML accessible.
2. Placer le catalogue détaillé derrière « Changer de modèle ». Il est déjà replié ; garder le prompt et les réglages essentiels au premier plan. Sur téléphone, éviter la succession de tous les filtres avant les résultats.
3. Afficher la méthode de connexion comme une préférence modifiable, principalement dans Connexions. Dans la création, montrer un résumé du compte facturé ; proposer le changement si les deux méthodes sont disponibles. Ne pas changer automatiquement un accès déjà confirmé.
4. Réduire la place du bandeau 4K et du bloc annonçant le futur montage. Garder la résolution près du choix et les nouveautés dans une aide discrète.
5. Réserver les badges à un sens vérifiable : « Favori », « Testé dans VydFlow », « Le moins cher pour cette durée parmi ces résultats ». « Recommandé » ne doit pas prétendre démontrer la meilleure qualité du marché.

## À ajouter, par priorité

| Priorité | Besoin | Proposition |
| --- | --- | --- |
| P1 | Comprendre la connexion | Guide court par fournisseur, lien direct, état enregistré/vérifié distinct, crédit et compte facturé explicites. |
| P1 | Comprendre l'attente | Suivi actualisé automatiquement, sans pourcentage ni délai inventés. Le rafraîchissement consulte le travail existant, jamais un nouveau lancement. |
| P1 | Reprendre une idée | Modifier une préparation non soumise et réutiliser un prompt/réglages dans une nouvelle préparation. Toute nouvelle dépense repasse par le récapitulatif. |
| P1 | Retrouver ses créations | Historique paginé et filtrable avec vignettes/états ; actuellement seules les 12 dernières créations sont affichées. |
| P1 | Agir sur le résultat | Aperçu, téléchargement, réutiliser et préparer la publication au même endroit ; le téléchargement existe actuellement via la bibliothèque. |
| P1 | Éviter une génération irrécupérable | Vérifier quota/place avant départ, traiter les résultats trop volumineux et conserver une récupération du même résultat lorsque possible. |
| P2 | Contrôler l'apparence | Image vers vidéo, puis références de personnage/produit et image finale sur les seuls endpoints compatibles. Upload privé et transmission au fournisseur expliquée. |
| P2 | Mieux écrire le prompt | Exemples et trame facultative : sujet, action, décor, style, caméra, sons/dialogue. Aucun appel IA payant implicite pour reformuler. |
| P2 | Régler l'audio | État visible ; interrupteur seulement si le modèle choisi le permet. Voix et sous-titres relèvent de capacités distinctes. |
| P3 | Vidéo de 30–60 secondes | Projet à plusieurs plans : prompt et durée par plan, références communes, estimation totale, validation puis assemblage. Ne pas confondre un plan long et une histoire montée. |
| P3 | Montage puis récurrence | Recettes simples (coupes, format, sous-titres, voix/musique autorisées), puis séries avec préparation en avance, validation par défaut, pause et plafonds. |

La référence visuelle facilite la continuité d'un personnage sans la garantir. Les futures fonctions de voix doivent indiquer les langues réellement prises en charge et distinguer audio natif, voix ajoutée et synchronisation labiale.

## Connexion et aide à afficher

L'utilisateur doit pouvoir réussir sans connaître MCP ni les détails d'une API. Dans Connexions, proposer deux entrées lorsque réellement disponibles : « Connecter mon compte Fal » et « Utiliser une clé API ». Garder MCP comme explication secondaire. Un serveur MCP chez un fournisseur n'implique pas automatiquement une connexion OAuth utilisable sans clé dans VydFlow.

Aide repliable « Comment connecter ce fournisseur ? » :

- **Fal navigateur** : cliquer dans VydFlow, autoriser chez Fal, revenir dans VydFlow. Vérifier le compte actif et ses crédits. Aucune clé à copier pour cette méthode.
- **Fal API** : [ouvrir les clés](https://fal.ai/dashboard/keys), créer une clé dédiée VydFlow avec les droits API nécessaires, la copier dans le champ VydFlow puis enregistrer. [Guide officiel](https://fal.ai/docs/documentation/setting-up/authentication).
- **Replicate** : [ouvrir les jetons](https://replicate.com/account/api-tokens), créer un jeton dédié puis l'enregistrer dans VydFlow. [Guide officiel](https://replicate.com/docs/topics/security/api-tokens).
- **LTX** : [ouvrir la console développeur](https://console.ltx.io/), créer une clé puis l'enregistrer dans VydFlow. [Guide officiel](https://docs.ltx.io/authentication).

Expliquer que la clé d'un fournisseur n'ouvre que ses modèles intégrés, que son accès/crédit reste nécessaire et que la génération est facturée chez lui. Un accès enregistré ne prouve ni le solde ni les droits sur chaque modèle. Ne jamais annoncer un solde ou une validation non lus réellement. Les clés ne doivent pas transiter par le chat.

## Autres modèles 4K

| Offre documentée | Conditions utiles | État VydFlow |
| --- | --- | --- |
| LTX 2.3 Fast / LTX 2.5 Fast direct | Configurations 4K de 6, 8 ou 10 secondes intégrées. | Catalogue présent ; sortie 4K réelle encore à tester. |
| LTX 2.5 Pro direct | 4K de 6, 8 ou 10 secondes selon la documentation. | Variante supplémentaire candidate, non intégrée. |
| Veo 3.1 / Veo 3.1 Fast via Gemini API | 4K pour 8 secondes ; Lite exclu. | Intégration Google directe absente ; la configuration Replicate actuelle reste à 720p. |

Sources : [LTX 2.5](https://docs.ltx.io/models/ltx-2-5/), [LTX 2.3](https://docs.ltx.io/models/ltx-2-3/), [Veo API](https://ai.google.dev/gemini-api/docs/veo?hl=en).

La capacité de l'éditeur du modèle ne prouve pas qu'un intermédiaire expose les mêmes résolutions. Pour chaque ajout : vérifier l'endpoint exact, ses paramètres, son prix, ses limites et son téléchargement. L'agrandissement vers 4K est une opération séparée : l'afficher comme tel avec son coût éventuel. La résolution décrit les dimensions, pas une garantie de meilleure cohérence ou de qualité narrative. Pour les vidéos sociales, proposer le 1080p comme compromis éditorial lorsque disponible, sans retirer 720p ni 4K.

## Points techniques avant ouverture plus large

- `AiGenerations` bloque après 14 jours sans tarif revérifié. Prévoir une procédure de maintenance du catalogue et un message actionnable ; augmenter le nombre d'entrées multiplie cette charge.
- `VideoLibrary` limite à 100 Mo par fichier, 1 Go et 100 vidéos. Le contrôle actuel intervient à l'import ; une génération peut déjà être facturée à ce moment. Un contrôle préalable de quota aide sans garantir la taille finale inconnue.
- Trois générations simultanées maximum ne constituent pas un plafond de dépense. Prévoir des limites locales par travail/projet et, pour les séries, par période ; une estimation ne constitue pas un plafond ferme de facturation fournisseur.
- Annulation : possible localement avant soumission, et après départ uniquement selon le fournisseur. Ne pas promettre de remboursement ou d'arrêt d'une génération déjà exécutée.
- Conserver l'idempotence et distinguer nouvelle génération, reprise d'une récupération et contrôle de statut. Une erreur réseau ambiguë ne doit jamais déclencher automatiquement une deuxième dépense.
- Avant le prochain essai payant MCP : modèle/résolution/durée/audio, compte et estimation explicitement confirmés. Aucun essai supplémentaire n'est réalisé par cet audit.

## Prochain lot recommandé

Commencer par « Studio simple et guidé » : choix unifié et favoris, aide aux connexions, résumé de facturation, suivi automatique, réutilisation et historique, contrôles de récupération/quota. Puis image vers vidéo, puis plusieurs plans et montage, puis séries. Ajouter les variantes 4K vérifiées à côté de ces lots, sans en faire un prérequis du parcours.

Seule petite modification d'interface faite dans cet audit : l'étoile du favori apparaît aussi dans la liste native des modèles, pour toutes les variantes du groupe, et s'actualise immédiatement après enregistrement.

## Repères Symfony et vérification de l'étoile

`AiStudioController` transmet les favoris du propriétaire au formulaire ; `AiIdeaType` transforme cette option en libellés préfixés par ★. Aucun identifiant d'exécution ni coût ne change. `creation_controller.js` actualise les libellés après succès de la sauvegarde, sans changer la sélection. Le rendu serveur conserve l'étoile au rechargement et sans JavaScript.

À essayer : mettre Pika en favori, vérifier ★ en 720p et 1080p dans la liste, sélectionner 1080p, retirer le favori et vérifier que la sélection reste identique. Recharger et comparer avec un autre compte : les favoris sont propres au propriétaire. Aucun bouton de génération n'est nécessaire pour ce contrôle.
