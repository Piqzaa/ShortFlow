# R7.4b — proportions et préparation d’un comparatif avec ambiance

5 novembre 2026. Le propriétaire autorise la poursuite de la préparation après son retour sur les premiers résultats. **Deux nouveaux brouillons privés enregistrés, aucune génération autorisée ou lancée, aucun nouveau budget accepté.** L’accord des huit anciens clips est consommé ; les épisodes 15–18 restent inchangés.

## Améliorations concrètes des consignes

Les anciennes descriptions définissaient les deux personnages comme petits, sans relation explicite de taille. Les scènes demandaient de préserver les proportions mais ne fixaient pas une hauteur commune, une même distance caméra et un même niveau de sol. Cela peut contribuer aux différences observées ; cet essai ne permet pas d’attribuer le défaut au prix du modèle.

Les deux nouveaux plans imposent dans leur texte principal, conservé intégralement dans les prompts finaux : **même hauteur debout et même taille de tête ; pattes sur la même ligne de sol ; même distance de la caméra ; Milo à gauche, Nox à droite ; caméra fixe, corps entiers visibles, aucun déplacement ou croisement**. Un seul geste : Milo lève puis baisse sa patte dans le premier plan ; Nox fait le même geste dans le second, l’autre personnage restant immobile. Les consignes importantes ne sont pas reléguées au contexte facultatif, susceptible d’être raccourci par le compositeur existant.

Ces améliorations concernent ces préparations : aucune règle globale ne force tous les personnages de VydFlow à avoir la même taille. Les fiches Milo/Nox révision 1, les prompts déjà approuvés et les vidéos du premier essai restent inchangés. L’efficacité des nouvelles consignes reste à observer.

## Deux préparations enregistrées

| Brouillon | Références et montage | Quantité proposée | Estimation |
| --- | --- | --- | --- |
| [21 — Duo proportions Vidu](http://127.0.0.1:8000/studio/series/episodes/21?review=1) | Vidu Q1 Référence de personnage, portraits 1/2 révision 1, muet | 2 × 5 s | 0,80 USD |
| [22 — Duo cadre commun et ambiance Kling](http://127.0.0.1:8000/studio/series/episodes/22?review=1) | Kling 3.0 Pro Image, même image de début pour les deux plans, audio conservé à 100 % | 2 × 5 s | 1,68 USD |

**Total proposé : quatre demandes / vingt secondes, 2,48 USD estimés hors taxes/conversion.** Aucun plafond nouveau accepté, aucun consentement de lancement. Ce sont des brouillons `draft`, `consentHash=null`, sans génération ni message nouveau. Estimations issues des services applicatifs et relues après réhydratation DB. Les textes finaux font 1 313 caractères pour Vidu, 676 pour Kling, et passent les limites actuelles.

Pour Kling, une image existante a été extraite de la **première image du clip 37 / génération 11 / vidéo 16**, épisode 18. Aucun appel IA ni nouvelle image générée : décodage PNG local puis normalisation par `AiReferenceStorage`. [Copie du cadre commun](pilotes/r74-comparatif/cadre-commun-v1.jpg), origine et empreintes conservées dans [le manifeste](fixtures/r74-comparatif-20261105.json). L’agent y observe une hauteur proche et les corps entiers, avec la patte droite de Nox proche du bord ; ce n’est pas une planche de proportions calibrée ni une image approuvée par le propriétaire.

Ce parcours Kling utilise **l’image de départ commune**, sans références `elements` ni sélection des fiches dans ses scènes. Il ne contourne pas les vues supplémentaires requises pour les éléments Kling : cette autre capacité n’est simplement pas utilisée. Les fiches actuelles n’ont pas encore ces vues distinctes. Le même cadre de début est volontairement réutilisé pour deux gestes indépendants, sans prétendre constituer un raccord temporel continu.

## Audio et prix vérifiés sur documentation publique

Le contrat Kling accepte `start_image_url`, une durée de cinq secondes et `generate_audio=true`. Il documente les voix chinoises et anglaises, avec traduction des autres langues vers l’anglais. Le protocole demande donc **une ambiance légère de cuisine et un bruissement de vêtement, sans parole ni musique**. Il ne promet ni voix française ni voix persistante. La configuration Kling existante active l’audio ; ce brouillon ne coupe pas le son au montage. [Contrat officiel](https://fal.ai/models/fal-ai/kling-video/v3/pro/image-to-video/api).

La fiche du modèle et la page officielle image-to-video donnent **0,168 USD/seconde avec audio**, soit 0,84 USD pour cinq secondes ; Vidu Q1 Référence donne 0,40 USD/5 s. La page générique de tarifs Fal présente aussi une valeur de base différente pour Kling : elle n’est pas utilisée pour cet exemple avec audio. [Fiche Kling](https://fal.ai/models/fal-ai/kling-video/v3/pro/image-to-video), [tarifs et exemples image-to-video](https://fal.ai/explore/image-to-video-apis), [fiche Vidu](https://fal.ai/models/fal-ai/vidu/q1/reference-to-video).

**La date de vérification du seul modèle `kling-3-image` a été actualisée du 20 octobre au 5 novembre**, après annonce et vérification publique du tarif avec audio. Taux, endpoint, paramètres et dates des autres modèles inchangés. Le devis du brouillon 22 a été recalculé : son empreinte change, son montant reste 1,68 USD ; le devis Vidu 21 est identique. Ancienne/nouvelle empreinte conservées dans le manifeste. La protection de péremption reste inchangée, aucun consentement accordé ou reporté sur un nouveau devis. Avant un lancement éventuel, obtenir l’accord concret du propriétaire sur les demandes, le compte et le plafond, puis recontrôler tarif/clé/états. Aucune clé déchiffrée, aucun solde/historique ou identité distante consulté. Le compte du connecteur de l’assistant n’est pas assimilé au compte applicatif.

## Ce que la comparaison pourra établir

Les gestes sont les mêmes, mais **modèle, type de référence et audio diffèrent** : comparaison pratique de deux parcours disponibles, pas expérience isolant le modèle ni preuve qu’un prix supérieur améliore la taille. Comparer les premiers et derniers instants de chaque plan, puis la coupe : nombre de personnages, identités/tenues, rapport de taille, hauteur sur le sol, placement, geste exécuté, immobilité de l’autre chat. Pour Kling, ajouter présence d’une ambiance audible, absence de paroles ou musique indésirables et conservation du son au montage. Aucun score n’est encore rempli.

## Contrôles et suite

Prévisualisation des services annulée : **27 compteurs restaurés**, référence temporaire supprimée. Préparation réelle ensuite par les services applicatifs : **seulement `ai_episode` +2 et `ai_shot` +4**, les 25 autres compteurs inchangés ; une nouvelle référence privée normalisée conservée pour le brouillon 22. Contrôle explicite des recettes/consentements/états des épisodes 15–18 avant/après, inchangés. Les identifiants 19/20 ont été consommés par la prévisualisation annulée ; les brouillons réels sont 21/22, pas des doublons.

Kernel de préparation à cache isolé, deux drapeaux de génération forcés à `false` dans ce seul processus, cinq clients HTTP bloqués ; **zéro tentative réseau fournisseur**. Deux pages natives de récapitulatif relues GET 200, devis identiques aux devis enregistrés, aucune génération associée, file générale vide. Session du banc invalidée avant sortie. Cette relecture HTTP n’est pas une nouvelle recette visuelle navigateur.

Prochain point : relire ces deux brouillons et décider séparément d’un éventuel lancement, sans déduire un nouveau budget des 4 USD du premier essai. Seule donnée applicative modifiée : date de vérification Kling décrite ci-dessus ; aucun comportement, configuration, schéma ou fiche de personnage modifié. **8 tests PHP ciblés / 133 assertions verts** (fraîcheur des tarifs, groupes du catalogue, construction des prompts). Pas de suite PHP complète, frontend/build, nouvelle recette visuelle navigateur ou revue indépendante ; les autres résultats restent historiques. Aucun appel IA, coût Fal nouveau, upload Fal, achat de crédits, email, publication, indexation, migration, cache web dev actualisé, worker lancé/redémarré, push ou déploiement. R7.4/R7.5 et grand R7 restent ouverts.
