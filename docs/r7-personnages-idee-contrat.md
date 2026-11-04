# R7.3b — contrat du premier portrait simulé

Vérification documentaire du 5 novembre 2026, après le [cadrage court](r7-personnages-idee-cadrage.md). **R7.3b reste ouvert : aucun générateur implémenté, aucun accès réel ni rendu validé.** Le candidat ci-dessous est retenu pour préparer les simulations, sans comparaison de qualité ni engagement commercial.

## Modèle et coût retenus pour le contrat

**FLUX.2 [pro], endpoint `fal-ai/flux-2-pro`, texte vers image.** La page officielle annonce **0,03 $ US pour une sortie 1024 × 1024 sans image d’entrée**. Cette dimension représente son unité de facturation initiale ; des dimensions supplémentaires changent le prix. La première version sera donc limitée à ce carré et à une demande de portrait, sans édition d’image ni variante automatique. Le prix est daté, indicatif et facturé par Fal ; il ne constitue ni un prix d’abonnement VydFlow, ni un plafond global de dépenses. [Modèle et tarif officiels](https://fal.ai/models/fal-ai/flux-2-pro).

Les tarifs peuvent changer. Le devis devra conserver sa date et sa version, refuser une confirmation périmée ou différente et être revérifié avant un essai réel. Aucun crédit, permission de clé ou tarif propre au compte n’a été contrôlé. L’API de tarification authentifiée n’a pas été appelée. [Facturation et API de prix](https://fal.ai/docs/documentation/model-apis/pricing).

## Entrées et résultat

Le schéma documente un prompt, des dimensions explicites, le JPEG, le mode asynchrone et les paramètres de sécurité. Il **ne documente pas de champ `num_images`** pour cet endpoint : ne pas en inventer. Le client devra exiger exactement un résultat exploitable ; un résultat multiple inattendu sera conservé pour diagnostic, sans choisir silencieusement une image. [Schéma OpenAPI officiel](https://fal.ai/api/openapi/queue/openapi.json?endpoint_id=fal-ai/flux-2-pro).

Corps prévu pour les simulations, jamais envoyé pendant cette étape :

```json
{
  "prompt": "PROMPT_EXACT_RELU",
  "image_size": {"width": 1024, "height": 1024},
  "output_format": "jpeg",
  "sync_mode": false,
  "enable_safety_checker": true,
  "safety_tolerance": "2"
}
```

Le résultat utilise `images[].url` et un `seed`. Lire les métadonnées éventuelles sans leur faire confiance pour valider le fichier. Conserver la graine retournée pour la traçabilité, sans promettre une image identique lors d’une nouvelle demande. Aucun réglage d’amélioration automatique du prompt n’est ajouté : seuls les champs effectivement documentés seront envoyés. [Contrat API du modèle](https://fal.ai/models/fal-ai/flux-2-pro/api).

## Connexion et cycle de requête

L’API documente une authentification par clé, à garder côté serveur. [Authentification du modèle](https://fal.ai/models/fal-ai/flux-2-pro/api).

**Décision locale : clé API Fal uniquement pour ce premier parcours.** `AiKeys` sait déjà enregistrer une clé chiffrée et détecter son remplacement. Réutiliser cette connexion, pas les objets ou tarifs vidéo. Son empreinte identifie une version de clé : elle ne prouve pas le nom du compte Fal facturé. L’interface devra distinguer connexion enregistrée, permissions réellement vérifiées et compte confirmé par le propriétaire.

`FalMcpVideoApi` extrait actuellement un résultat `video.url` et dépend d’`AiGeneration`/`AiCatalog`. Cela ne valide pas les images via la connexion navigateur. Ne pas la proposer ici tant que son contrat image n’est pas vérifié et adapté ; ce constat porte sur le code du projet, pas sur une impossibilité générale chez Fal.

Soumettre via la queue, enregistrer l’identifiant et les URL de suivi renvoyés, puis lire l’état et le résultat. Les états documentés sont `IN_QUEUE`, `IN_PROGRESS`, `COMPLETED`. Les URL de récupération peuvent utiliser le préfixe application plutôt que l’endpoint complet : les valider, puis utiliser celles reçues, sans les reconstruire depuis le chemin de soumission. [Cycle de queue officiel](https://fal.ai/docs/documentation/model-apis/inference/queue).

Le futur adaptateur devra contrôler HTTPS, hôte, application et identifiant de requête, interdire les redirections et ne jamais transmettre la clé lors du téléchargement de l’image. Une réponse incertaine sans identifiant suspendra la demande ; un identifiant connu permettra de reprendre le suivi. Aucune nouvelle soumission payante automatique ne sera déduite d’un timeout, d’un échec de téléchargement ou d’une erreur de stockage.

## Raccord au stockage privé

`AiReferenceStorage` contrôle déjà type réel, dimensions, taille d’entrée de 10 Mo, normalisation JPEG et quota partagé de 100 Mo, avec fichiers normalisés plafonnés à 2 Mo. Le résultat temporaire devra passer ces validations avant d’être accepté dans une fiche. Un téléchargeur image borné est nécessaire ; `AiVideoDownload` utilise une limite vidéo de 100 Mo et n’est pas directement le contrat d’une image.

Préserver le résultat privé non encore accepté avec sa demande, le compter dans une limite de stockage et l’inventaire de maintenance, puis faire valider explicitement son utilisation. Une fiche existante ne change qu’après validation et contrôle de sa révision. Les copies d’épisodes, devis vidéo et images historiques de R7.3a ne sont pas actualisées automatiquement.

## Premier sous-lot à développer

**Brouillon privé et devis image, sans soumission fournisseur.** Préparer le formulaire idée/nom/apparence/style, le prompt relisible, ce contrat fixe et l’empreinte de devis liée à la révision et à la connexion. Un brouillon ne sera pas une fausse fiche `AiCharacter` sans portrait. Tester propriété, CSRF, prix périmé, changement de clé/texte et conservation de la saisie. Le traitement asynchrone simulé et l’acceptation du résultat viendront ensuite ; aucune migration implicite en dev.

Ce découpage Symfony sépare la saisie du devis, puis la demande payante de la fiche utilisable. Le futur consentement image restera distinct du consentement vidéo. Toute génération réelle exigera encore l’accord du propriétaire sur modèle, compte, quantité et plafond de dépense.

## Vérifications et limites

Sources publiques officielles consultées uniquement ; aucun Playground lancé, SDK installé, clé réelle lue ou API authentifiée appelée. Aucune promesse de ressemblance, tenue, vues cohérentes ou qualité de portrait n’est validée. Aucun code applicatif, schéma, service ou test modifié ; aucune suite/build/navigateur rejoué et aucune revue indépendante nouvelle revendiquée. Les résultats antérieurs ne sont pas présentés comme de nouveaux passages.

Contrôle documentaire et cohérence des paramètres avec le schéma ; `git diff --check`. Pas de coût, activation, génération, email réel, publication, migration, cache dev actualisé, worker redémarré, push ou déploiement. R7.2/R7.3 et R7.3c restent ouverts ; les essais humains demeurent reportés.
