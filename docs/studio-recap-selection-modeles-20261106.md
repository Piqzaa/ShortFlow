# Récapitulatif, sélection des modèles et import — 6 novembre 2026

## Erreur 500 et conservation des saisies

Log web observé : `AiShot::revise(): Argument #1 ($prompt) must be of type string, null given`, depuis `AiEpisodeFlow::save`. Une zone de texte facultative vide est normalisée en `null` par Symfony. La sauvegarde convertit maintenant cette absence en chaîne vide ; l’estimation conserve sa validation existante « Complète le prompt de chaque plan ». Le brouillon et les autres prompts restent disponibles, aucune génération vide n’est envoyée.

Nouveau test par vrai formulaire : deux plans, premier vide, deuxième avec dialogue français ; POST 303 puis GET 200 avec erreur de validation, texte du deuxième intact, état draft, aucune génération. La recette complète Omni avec prompts remplis reste verte.

## Choix éditorial après comparaison

Cette sélection remplace les raccourcis précédents. Elle combine les contrats déjà intégrés, leurs prix, les contrôles de référence disponibles et les caractéristiques publiées. Elle ne constitue pas un classement audiovisuel issu d’essais payants. Les exigences de qualité de voix française ne peuvent pas être certifiées par un schéma ou par le prix. Aucun nouveau message générique français/argot n’est ajouté dans le produit.

| Point de départ | Économique | Équilibré | Qualité & dialogues |
|---|---|---|---|
| Texte | Grok Imagine Video 720p | Omni 1.2 Flash Texte 720p | Seedance 2.5 Texte 720p |
| Image de départ | Grok Imagine Video Image 720p | Omni 1.2 Flash Image 720p | Seedance 2.5 US Image 720p |
| Image de départ et fin | LTX 2.3 Fast Image 720p | Omni 1.2 Flash Image 720p | Seedance 2.5 US Image 720p |
| Personnage, image importée ou fiche | Wan 3.0 Référence 480p | Omni 1.2 Flash Référence 720p | Seedance 2.5 US Référence 720p |

Exemples affichés par plan, audio inclus : Grok texte 5 s 0,35 USD, image 0,36 USD avec frais fixes ; LTX début-fin 6 s 0,18 USD ; Wan Référence 5 s 0,25 USD ; Omni 5 s 0,41 USD ; Seedance 5 s environ 2,37 USD. Les durées restent explicites ; les 6 s LTX ne sont pas présentées comme 5 s. Le sélecteur de résolution demeure visible et Omni propose aussi 1080p. Aucun modèle n’est sélectionné automatiquement.

### Arbitrages

- **Grok** : candidat économique texte/image, audio natif et faible prix. L’accès image choisi n’accepte pas de dernière image ; exclu du raccourci début-fin. Grok 1.5 Référence 720p revient à environ 0,71 USD/5 s avec une référence, contre 0,41 USD pour Omni : pas retenu comme économie dans ce mode.
- **LTX Fast** : début/fin déjà pris en charge, 0,03 USD/s en 720p, mais durées natives commençant à 6 s. Connexion LTX distincte de Fal indiquée sur la carte ; aucun accès connecté inventé.
- **Wan 3.0 Référence** : audio et références à 0,05 USD/s en 480p, moins cher qu’Omni. Le compromis est une définition plus basse, affichée clairement. Les versions 720p/1080p à 0,10/0,20 USD/s n’améliorent pas le positionnement économique.
- **Omni 1.2** : candidat équilibré récent, audio inclus, référence multiple et amélioration de suivi des références/dialogues revendiquée par Google. 720p à 0,0819 USD/s, 1080p à 0,1229 USD/s ; assez proche du coût des modèles d’essai pour rester polyvalent. Image avec fin limitée à 10 s dans le parcours actuel.
- **Seedance 2.5** : profil pour scènes exigeantes, continuité sur un même clip, direction de scène et dialogue synchronisé ; autre famille que le choix équilibré. Prix nettement supérieur, facturation aux tokens. Le choix ne repose pas seulement sur une montée de résolution. Les références image/audio, l’édition et les clips longs sont documentés ; seules les entrées déjà gérées par VydFlow sont utilisées.
- **Vidu Q1** : reste dans le catalogue pour du visuel sans voix, mais ne devient pas le raccourci économique d’une histoire dialoguée quand un accès avec audio moins cher existe. Son 1080p seul ne justifie pas un classement premium.
- **Kling 3/O3** : contrôles visuels utiles, mais limitation chinois/anglais documentée pour Kling 3, et retour humain français défavorable sur l’ancien essai O3 Standard. Pas le raccourci pour ce parcours orienté dialogues.
- **Veo Référence Fal** : retrait annoncé le 17 novembre, exclu des nouvelles mises en avant ; aucun remplacement silencieux d’identifiant. Les autres accès restent distincts.
- **Seedance 2.0/Fast, autres variantes Seedance, modèles silencieux/spécialisés, variantes haute résolution et anciens accès Wan/Kling** : ne pas multiplier les raccourcis à partir d’une disponibilité catalogue. Les variantes non intégrées ne sont pas ajoutées par cette comparaison ; les autres choix restent accessibles dans les options avancées. Une résolution supérieure, une popularité ou un nom « Pro » ne sont pas une preuve de meilleure voix.

### Prix Seedance et portée

Schémas actuels texte, US image et US référence relus avec Fal : duration en chaîne, generate_audio, références et fin selon endpoint. Prix 0,0214 USD/1 000 tokens confirmé sur ces trois accès. Le guide Fal publie la formule `hauteur × largeur × durée × 24 / 1024` et un plafond indicatif arrondi d’environ 0,4730 USD/s en 720p 16:9. Portrait 9:16 a la même surface ; le raccourci utilise cette approximation existante, sans affirmer un débit final exact. Aucun tarif d’un autre fournisseur n’est substitué.

Le tarif numérique n’est pas modifié. Seul le contrôle daté de `seedance-25` est actualisé et son caractère facturé aux tokens explicité dans le catalogue. Cela change son contrat durable : les anciens consentements ne sont pas transposés. Les autres configurations recommandées étaient déjà datées du 5/6 novembre.

Sources primaires consultées : [Google Omni 1.2](https://blog.google/innovation-and-ai/technology/developers-tools/introducing-gemini-omni-1-2-flash/), [Omni Référence Fal](https://fal.ai/models/google/gemini-omni-flash/v1.2/reference-to-video), [Seedance 2.5 présentation](https://fal.ai/seedance-2.5), [prix et formule Seedance](https://fal.ai/learn/tools/how-to-access-seedance-2-5-on-fal), [Seedance US Référence](https://fal.ai/models/bytedance/seedance-2.5/us/reference-to-video), [Wan Référence Fal](https://fal.ai/models/alibaba/wan-3.0/reference-to-video), [Wan Alibaba](https://modelstudio.alibabacloud.com/intl/blog/wan3-ai-video-generation-model/), [Grok Fal](https://fal.ai/models/xai/grok-imagine-video/text-to-video), [LTX prix](https://docs.ltx.io/pricing/). Découverte/recommandation et schémas/prix uniquement : les instructions de soumission suggérées par le connecteur n’ont pas été suivies.

## Deux demandes d’interface supplémentaires

La phrase « Référence d’apparence : elle ne fixe ni le début ni la fin du clip. » est retirée du contrôleur d’aperçu. Les indications utiles de cadrage des véritables images de début/fin restent disponibles.

La carte « Importer une vidéo » est le premier élément de la grille de l’onglet Vidéos, avant les vidéos existantes. Le lien conserve les paramètres de publication/date/fuseau. Le bouton de première importation dans une bibliothèque vide reste disponible. Le contrôle fonctionnel existant vérifie désormais que cette carte est le premier enfant de la grille.

## Vérifications

- Trois fonctionnels personnages/récapitulatif/recommandations : 119 assertions ; un fonctionnel espace Vidéos/contexte publication : 16 assertions.
- 188 unitaires contrats Fal/références/catalogue : 7 676 assertions. Le premier passage élargi avait quatre erreurs parce que le banc général texte n’incluait pas encore les schémas Omni ; il les réutilise maintenant depuis la fixture vérifiée existante. Aucun champ fournisseur n’a été changé pour contourner le test.
- 202 frontend, check et build verts ; avertissements Vite existants conservés. PHPStan des cinq fichiers PHP concernés vert ; 45 Twig verts. La première assertion de déplacement utilisait encore l’ancien cache Twig test, actualisé avant le passage vert.
- Seize états navigateur : quatre points de départ × 320/390/768/1280. Trois familles/profils exacts, exemples de coûts/audio visibles, sélection premium et équilibrée, changement Omni 720/1080, phrase de référence et message générique absents, aucun débordement ni erreur JS. Capture mobile relue.
- Quatre vues bibliothèque dev à 320/390/768/1280 sans JavaScript : import premier dans la grille, lien visible, aucun débordement ; capture mobile relue. Contrôle de style des cinq fichiers PHP vert ; dernier ajustement rédactionnel des cartes relinté et cache dev reconstruit.
- Quatre GET authentifiés dev HTTP 200, transaction de lecture seule : 27 compteurs égaux, zéro HTTP fournisseur. Rapport `artifacts/recap-tiers-native.json`. Aucun POST de propriétaire rejoué.

Cache dev actualisé. Seul background-worker déjà actif redémarré après contrôle de zéro message, génération, épisode et export actifs ; autres workers inchangés. Aucune migration, génération réelle, coût, upload fournisseur, clé réelle lue/déchiffrée, email, publication, push ou déploiement. Pas de suite PHP globale ni de revue indépendante ; grand R7 reste ouvert. La prochaine génération reste une action distincte du propriétaire avec son devis et son accord.
