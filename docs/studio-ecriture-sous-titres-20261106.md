# Écriture des plans et sous-titres locaux — 6 novembre 2026

## Résultat local

Studio propose « Décrire mon idée à l’IA » ou « Écrire mes plans moi-même ». Le second parcours masque l’idée et la proposition de scénario sans effacer leurs données. Un plan unique présente directement son prompt, sans idée obligatoire ni scénario IA. En passant au plan unique, une idée existante peut remplir un prompt encore vide ; un prompt renseigné n’est jamais remplacé. Effacer ensuite ce prompt ne le remplit pas de nouveau. Les conversions de plusieurs plans gardent leurs contrôles existants sur les textes et références.

Le formulaire accepte les plans manuels sans idée séparée. Le premier prompt sert alors de résumé interne et de titre par défaut ; les prompts des plans restent inchangés. Une préparation manuelle vide reste un brouillon et les contrôles du récapitulatif exigent toujours les prompts avant génération. Le mode IA à plusieurs plans demande une idée. Les anciennes préparations renseignées sont présentées en mode manuel par défaut ; le choix reste modifiable.

Le moteur de sous-titres était déjà installé dans `edit-worker` : faster-whisper et modèle small local de 464 Mo. Le drapeau `LOCAL_CAPTIONS_ENABLED` était désactivé. Activation dans `.env.local` uniquement (configuration locale ignorée par Git), cache dev actualisé et seul `edit-worker` déjà actif redémarré après garde : zéro message, génération active, épisode actif et montage actif. Aucun worker arrêté n’a été démarré. Le modèle reste hors ligne ; aucun téléchargement ni transcription du clip du propriétaire n’a été lancé.

## Vérifications

- Transcripteur réellement exécuté hors ligne sur deux secondes de silence synthétique : sortie `[]`, succès ; fichier temporaire retiré.
- Trois fonctionnels Studio : 87 assertions, plans manuels 1/2 sans idée, refus IA sans idée, récapitulatif vide, durées natives et prix 720/1080 conservés. Deux fonctionnels de sous-titres : 27 assertions, transcription simulée, texte modifiable et conservation en cas d’échec.
- 205 tests frontend, check JS et build verts ; avertissement existant de gros chunk Vite. 37 Twig, style de trois fichiers PHP et PHPStan des deux fichiers applicatifs verts. Pas de suite PHP globale ni de revue indépendante.
- Chrome à 320/390/768/1280 : choix IA/manuellement/unique, conservation de l’idée et du prompt lors du changement de durée, aucun débordement ou erreur JS/console. Capture mobile relue. Réseau navigateur intercepté.
- Banc de lecture seule dans web et worker : `captionsEnabled=true`, quatre GET authentifiés HTTP200 chacun, 27 compteurs inchangés, zéro appel fournisseur. Le banc n’a ni consommé la file ni modifié un montage.

Échecs intermédiaires : le nouveau test attendait HTTP200 pour une validation de formulaire dont le bon statut est 422 ; assertion corrigée. La première recette navigateur a révélé une transition vers un plan unique avant création effective de sa carte : transfert de l’idée différé jusqu’à présence réelle du champ. Reprise des 205 tests frontend et quatre largeurs verte après correction.

## Retour humain et suite

Le propriétaire rapporte un bon français sur sa vidéo de présentation effectivement générée. Retour positif limité à cette vidéo ; modèle exact, épisode, voix et prononciation n’ont pas fait l’objet d’une nouvelle identification ou certification par l’agent.

Prochain essai propriétaire : rouvrir le montage, générer les sous-titres puis relire/corriger le texte et la synchronisation, avant d’ajouter sa capture UI et sa voix off. R7 reste ouvert. Aucune génération vidéo, dépense fournisseur, clé lue, migration, publication, push ou déploiement pour ce lot.
