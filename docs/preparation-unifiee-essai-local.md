# Préparation commune et essai de rédaction locale — 23 octobre 2026

Ce document décrit le premier lot de préparation commune et son benchmark isolé. La rédaction sur demande a ensuite été intégrée dans un [pilote supervisé](scenario-local-supervise.md), qui décrit l’état courant.

## Objectif et parcours livré

Le studio et les épisodes encore modifiables utilisent le même formulaire : idée, durée finale, modèle, références privées et plans. Le modèle peut être changé ici ; il n’est plus nécessaire de revenir dans la configuration de série. La page de série propose un seul accès « Préparer la vidéo ». Les anciens POST de préparation restent compatibles pour les onglets ouverts, sans constituer un second parcours visible.

Le découpage est proposé sur place. « Répartir la durée » adapte les durées natives et conserve les textes ; lorsque le nombre de plans diminue, les textes supplémentaires sont regroupés dans le dernier champ plutôt que supprimés. Les limites de longueur restent contrôlées. Un total impossible propose des valeurs proches dans le navigateur ; le choix n’est jamais arrondi silencieusement.

Les raccords et le son sont dans les options avancées. Un raccord incompatible est désactivé dans le navigateur avec une aide indiquant comment ajouter une référence et choisir un modèle image sur la même page. Le contrôle serveur reste obligatoire si JavaScript est absent ou contourné. Le son est conservé par défaut. Le nom et la connexion sont repliables ; le catalogue complet reste disponible.

« Voir le récapitulatif » enregistre le scénario et affiche une confirmation en lecture seule avant toute dépense. Le modèle, la recette et les scènes ne sont plus modifiables après lancement. Changer la préparation invalide son ancien consentement. Un formulaire périmé est refusé avec les saisies conservées. La génération, le montage et la publication réutilisent les moteurs précédents.

La série possède maintenant un défaut de **durée finale** distinct de la durée native d’un clip, stocké dans la recette JSON existante. Les anciens épisodes préparés gardent leur recette. Aucune migration supplémentaire ni donnée supprimée.

Les scénarios restent manuels dans le produit : l’essai de rédaction ci-dessous est un benchmark séparé, pas une fonctionnalité annoncée disponible. Aucune récurrence ou publication automatique ajoutée.

## Fichiers et responsabilités

| Composant | Responsabilité |
| --- | --- |
| `src/Form/AiIdeaType.php` | Formulaire commun, collection de plans, durées, champs de raccord/son et validations existantes de modèles/images. |
| `src/Controller/AiStudioController.php` | Préparation du studio ou d’un épisode propriétaire, adaptation sans JavaScript, connexion existante, erreurs et enregistrement. |
| `src/Controller/AiEpisodeController.php` | Délègue les brouillons au formulaire commun ; conserve confirmation, suivi et validation du résultat. |
| `src/Service/AiEpisodeFlow.php` | Validation du total et de la recette, propriété des références et enregistrement atomique sous verrou. |
| `src/Entity/AiEpisode.php` | Reconfiguration limitée aux brouillons, conservation du propriétaire et invalidation du consentement. |
| `AiSeriesType.php`, `AiSeriesStudio.php`, `AiSeriesController.php` | Défaut de durée finale dans la recette, distinct des durées par clip. |
| `templates/ai/studio.html.twig`, `_preparation_plans.html.twig` | Un formulaire lisible, options repliées et découpage personnalisable. |
| `assets/preparation-plan.js`, `controllers/preparation_controller.js` | Découpage borné, adaptation au modèle, aide contextuelle et disponibilité des raccords. |
| `controllers/shot_editor_controller.js` | Ordre, ajout/retrait, coût total et conservation des textes. |
| `scripts/benchmark-story-local.py` | Essai local reproductible, indépendant du produit et des API vidéo. |

À comprendre en Symfony : un même FormType peut servir plusieurs points d’entrée. Le contrôleur charge une ressource autorisée et reçoit la saisie ; le service valide et enregistre sous transaction ; Doctrine conserve la recette et les plans. Un bouton de préparation ne lance pas le worker payant : le consentement est une action distincte. Stimulus améliore l’ergonomie, mais n’est jamais l’autorité pour le coût ou la propriété.

## Essai local réellement effectué

Poste : Windows, Ryzen 7 7800X3D (8 cœurs / 16 threads), environ 33,24 Go de RAM physique déclarée. Ollama 0.24.0 déjà installé ; Mistral disponible au départ. Qwen3 4B Q4_K_M téléchargé depuis le registre Ollama (environ 2,5 Go de fichier). Les deux modèles fonctionnaient sur CPU : `size_vram = 0` dans les observations.

Trois histoires synthétiques de Milo par modèle, chacune en trois plans de 15 secondes, avec résumé des épisodes précédents réinjecté. JSON contraint par schéma, sortie bornée à 1 000 tokens. Aucune donnée du propriétaire ou clé transmise ; toutes les requêtes de rédaction vont à `127.0.0.1:11434`.

| Modèle | Épisode 1 | Épisode 2 | Épisode 3, avec charge FFmpeg | Allocation signalée par Ollama |
| --- | --- | --- | --- | --- |
| Mistral 7.2B Q4_K_M | 34,296 s | 32,157 s | 39,875 s | 4,833 Go, soit environ 4,50 Gio |
| Qwen3 4B Q4_K_M, raisonnement désactivé | 28,484 s | 27,000 s | 35,547 s | 3,497 Go, soit environ 3,26 Gio |

La mémoire indiquée est l’allocation résidente rapportée par l’API du moteur, pas un pic de RAM totale du poste ni une mesure du seul processus. Les vitesses de sortie étaient de 9,61–10,63 tokens/s pour Mistral et 14,71–16,96 tokens/s pour Qwen3. Ce n’est pas un benchmark isolé ni un test de débit multi-utilisateurs ; les résultats ne prédisent pas ceux d’un VPS partagé.

La charge parallèle encodait une mire animée verticale 1080×1920 de 45 secondes via le conteneur `edit-worker`, libx264, deux threads, sortie nulle. Les deux encodages ont terminé avec code 0. C’est une charge synthétique, pas un montage utilisateur. Le temps de scénario inclut bien cette concurrence ; le champ de durée du rendu dans le JSON mesure l’intervalle jusqu’à sa collecte après le texte, et ne doit pas servir de durée isolée d’export.

**Structure : 6 sorties sur 6 conformes au contrôle de base** (JSON, trois plans, 45 secondes). **Qualité narrative : non validée pour la production.** Les prompts sont restés en français malgré la demande d’anglais ; Mistral répète le météore et termine une première histoire sans résolution nette ; Qwen3 répète fleur/porte/diamant, avec des transitions parfois difficiles à rendre. Un JSON valide ne prouve pas une histoire nouvelle ou une identité visuelle cohérente.

Conclusion : Qwen3 est un meilleur candidat pour poursuivre l’essai à coût fixe, mais pas pour activer un pilote autonome. Il faut travailler la séparation des consignes de langue et de récit, garder une mémoire des événements, détecter les répétitions et imposer la validation humaine. La prochaine mesure de capacité doit utiliser des limites CPU/mémoire proches de l’hébergement envisagé.

Les modèles ont été déchargés après l’essai ; aucune instance de modèle ne restait chargée à la vérification `ollama ps`. Qwen3 reste dans le cache local. Aucun abonnement, paiement, service cloud, accès public ou fallback vers une API de texte payante ajouté. Le programme Ollama existant peut rester lancé, sans modèle chargé.

## Reproduire et tester

Avec Ollama démarré et le modèle déjà installé :

```powershell
python -X utf8 scripts/benchmark-story-local.py --model qwen3:4b --output artifacts/story-local/qwen3-4b.json --with-render
```

Le script ne télécharge pas de modèle. Sans `--with-render`, il n’utilise pas Docker. Les résultats JSON sont ignorés dans `artifacts/story-local/` ; l’essai actuel Mistral est dans `result.json`. Les modèles absents sont refusés. Le déchargement concerne seulement le modèle de l’essai s’il n’était pas chargé au départ.

Recette du produit : ouvrir un épisode à préparer ou à confirmer, changer de modèle sans quitter la page, vérifier que les textes restent présents, ajouter une référence privée pour activer les raccords, choisir un total exact ou proche explicitement, puis examiner le récapitulatif sans confirmer un essai vidéo payant. Tester aussi sans JavaScript, avec un autre propriétaire et avec un ancien formulaire ouvert avant la modification.

Contrôles automatisés et résultats exacts : [verification.md](verification.md). Les tests vidéo utilisent exclusivement `shortflow_test` et des fournisseurs simulés.
