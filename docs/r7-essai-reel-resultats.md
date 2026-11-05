# R7.4 — huit clips réels récupérés, quatre épisodes prêts

5 novembre 2026. Le propriétaire a répondu **« Oui, lancer ces 8 clips »** à l’accord explicite des seuls brouillons 15–18 : Vidu Q1 Référence de personnage, huit demandes de cinq secondes, Milo/Nox, clé VydFlow attestée du compte Fal Alex Berrel, **3,20 USD estimés, plafond 4 USD hors taxes/conversion**, sans voix ni publication. Cet accord est consommé ; aucune reprise payante ou variante supplémentaire n’est autorisée.

**Les huit demandes ont réussi et les quatre épisodes sont `ready`. R7.4 reste ouvert pour la relecture humaine et le bilan visuel ; grand R7, R7.2/R7.3/R7.3c/R7.5 ne sont pas clôturés.** Ce premier essai confirme un parcours technique réel, avec des limites de rendu observées.

## Résultats dans VydFlow

| Épisode | Clips / générations | Montage / vidéo finale | Relecture privée |
| --- | --- | --- | --- |
| 15 — Milo seul 1 | 31/5, 32/8 | 51 / 17 | [Ouvrir](http://127.0.0.1:8000/studio/series/episodes/15?review=1) |
| 16 — Milo seul 2 | 33/6, 34/9 | 52 / 18 | [Ouvrir](http://127.0.0.1:8000/studio/series/episodes/16?review=1) |
| 17 — Milo et Nox 1 | 35/7, 36/10 | 53 / 19 | [Ouvrir](http://127.0.0.1:8000/studio/series/episodes/17?review=1) |
| 18 — Milo et Nox 2 | 37/11, 38/12 | 54 / 21 | [Ouvrir](http://127.0.0.1:8000/studio/series/episodes/18?review=1) |

Tous les fichiers sont privés dans `var/storage/videos/dev`. Les chemins, tailles, métadonnées et SHA-256 des douze médias figurent dans [le manifeste](fixtures/r74-execution-20261105.json). Les vidéos ne sont pas ajoutées à Git.

## Exécution bornée

Un processus CLI isolé a utilisé les services existants `AiEpisodeFlow`, `AiGenerations` et `VideoEdits`, avec un cache séparé et un bus qui retient exclusivement les messages des épisodes 15–18. Aucun consommateur général ni worker lancé/redémarré. Les anciens travaux, dont une génération `prepared`, n’ont pas été traités. La file générale est restée vide.

Le garde HTTP vérifie avant chaque envoi l’empreinte de la version de clé approuvée, le modèle, les prompts exacts des devis figés, les empreintes des portraits, le format 9:16, l’absence de musique et la limite de huit POST. Il écrit l’intention avant le POST et refuse une seconde soumission du même travail. Les images JPEG ont été transmises dans les huit requêtes approuvées ; aucun upload ou générateur de portrait distinct.

L’application a déchiffré la clé côté serveur pour ces appels autorisés ; elle n’a été ni affichée ni exportée. Le rattachement à Alex Berrel reste l’attestation du propriétaire, sans preuve d’identité API. Aucun solde ni historique de facturation consulté.

Du 13:30:59 au 13:43:03 UTC : **8 POST acceptés HTTP 200, 45 GET de suivi/résultat (29 HTTP 202, 16 HTTP 200), 8 téléchargements HTTP 200 ; zéro renvoi, échec ou variante.** Les lectures ne sont pas de nouvelles générations. Estimation engagée : **3,20 USD** selon les devis ; débit réel non vérifié sur facture. Aucun achat de crédits.

Par rapport à l’état immédiatement avant lancement, 27 tables comptées : **`ai_generation` +8, `video_asset` +12, `video_edit` +4**, les 24 autres compteurs inchangés. Les épisodes et plans existants ont naturellement changé d’état. Anciennes générations toujours 2 `available`, 1 `prepared`, 1 `replaced` ; aucun autre épisode actif ni message général final.

Le drapeau vidéo est forcé à `true` dans le processus dédié et portrait à `false`. Aucun fichier de configuration ou drapeau global modifié ; le CLI dev avait déjà été observé à vidéo `true` lors de la préparation. Les valeurs des processus web/workers ne sont pas déduites de ce processus.

## Contrôles des médias et limites visuelles

Les douze fichiers passent un décodage FFmpeg intégral et leurs empreintes sont conservées. Huit sources H.264 **1080 × 1920, 24 images/s, 5,375 s**, sans piste audio. Les quatre montages H.264 sont à **30 images/s, piste vidéo 10,000 s** ; la durée de conteneur est **10,022 s** avec une piste AAC de silence (niveau maximal mesuré −91 dB). Le montage retient cinq secondes de chaque source. La durée source renvoyée dépasse donc celle demandée ; aucune qualité sonore/voix n’est validée.

Inspection par l’agent de cinq images par clip, à 0–4 s, conservées dans [r74-videos](pilotes/r74-videos). Ce sondage ne constitue ni une lecture humaine ni un jugement temporel exhaustif :

- Les identités principales restent reconnaissables sur les images : chat orange/sweat bleu, chat charbon/tablier orange ; pas d’échange de vêtements observé.
- 31 : rotation du corps et mouvements de patte plutôt qu’un simple mouvement de tête ; cadre plus large que demandé.
- 32 : patte levée, puis déplacement supplémentaire et dérive de cadre.
- 33 : marche visible ; le nombre exact de trois pas puis arrêt n’est pas établi par les images échantillonnées.
- 34 : Milo saisit et repose un bol supplémentaire, au lieu de simplement regarder le bol puis la caméra.
- 35–36 : taille relative des personnages fortement inversée entre plans ; Nox s’approche de la caméra et Milo est partiellement coupé.
- 37 : Nox passe devant Milo, qui devient partiellement masqué, au lieu d’un pas commun vers l’avant.
- 38 : Nox beaucoup plus petit que dans le plan précédent ; accroupissement supplémentaire et patte levée tardivement.

**La cohérence des proportions, le cadrage et le respect précis des actions restent insuffisants pour annoncer une série homogène prête à publier.** Aucun reroll pour masquer ces défauts. Le stockage/import des portraits ne valide toujours pas une génération image réelle par le pipeline Fal R7.3b.

## Suite sans nouvelle dépense

Le propriétaire peut regarder les quatre épisodes et indiquer, pour chacun : personnages reconnaissables, geste compréhensible, défaut le plus gênant, résultat utilisable ou non. Aucun retour humain n’est encore enregistré. Ensuite, établir le bilan R7.4–R7.5 et préparer les améliorations de cadrage/proportions/prompts ; tout nouvel essai payant nécessite un autre accord concret.

Aucun code applicatif/frontend/schéma modifié. Pas de nouvelle suite PHP/frontend/build/lint, recette navigateur, revue indépendante ou essai matériel pour cette exécution de contenu ; les 36 PHP/627 assertions et 196 frontend restent historiques. Aucun cache web dev actualisé, email, publication, activation d’indexation, migration, push ou déploiement.
