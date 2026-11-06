# Séries : parité des données modèle avec Studio — 6 novembre 2026

## Cause et correction

Le sélecteur commun Studio/Séries attend les capacités audio et la tarification du catalogue. La page de configuration des Séries lui transmettait une projection ancienne contenant seulement nom, résolution, durées, formats et quelques indicateurs d’image. En JavaScript, l’audio absent était interprété comme faux et les tarifs absents pouvaient produire un exemple à zéro.

La configuration transmet maintenant le même catalogue public de présentation que Studio, enrichi uniquement des deux alias `subject/end` déjà utilisés par le contrôleur Séries. Audio, prix à la durée, frais fixes, prix par référence, forfaits, limites, langue et autres métadonnées utiles restent disponibles. Aucun contrat durable fournisseur, tarif, identifiant ni comportement de confirmation n’est modifié.

Le moteur de génération et l’écran de préparation des épisodes étaient déjà communs. Ce correctif porte sur l’écran initial de configuration de la série, pas sur une nouvelle génération ou une nouvelle automatisation.

## Vérifications

- Trois tests fonctionnels ciblés Séries, 186 assertions : intégrité de la projection pour chaque modèle, audio positif/négatif, tarifs 720p/1080p, frais Grok, forfaits LTX, conservation des configurations non connectées et des erreurs ; choix 1080p réellement enregistré puis repris dans le formulaire de l’épisode. Aucune génération ni message créé, client fournisseur bloqué. Base de test isolée.
- Première reprise : le nouveau test tentait de rafraîchir une entité détachée après la requête ; le banc relit désormais l’entité en base. Aucun code métier modifié pour contourner ce défaut de test.
- PHPStan/style du test et Twig de configuration verts ; cache dev actualisé. Pas de suite PHP globale ni nouveau frontend/build : JavaScript/CSS inchangés, mêmes ressources que le Studio.
- Douze états navigateur : texte/image/personnage × 320/390/768/1280, exemples non nuls, trois badges audio corrects, sélection Omni 720p à 0,41 USD/5 s puis 1080p à 0,62 USD/5 s, montant de la carte et sélection cohérents. Sélection Vidu sans audio vérifiée séparément, nom de série conservé, aucun débordement/erreur JS ou console. Capture mobile relue. HTML synthétique et réseau intercepté ; zéro POST dans cette recette navigateur.

Aucun worker redémarré, aucune migration, clé réelle lue, génération payante, upload fournisseur, email, publication, push ou déploiement. R7 et la validation réelle du français restent ouverts.
