# Plan unique et prix de la résolution — 6 novembre 2026

Reprise explicitement demandée par le propriétaire après le relais de fin de journée. Deux corrections locales, sans génération payante.

## Un seul plan accessible directement

Le serveur sait déjà préparer un clip unique, mais le champ `planCount` ne proposait que 2–8 plans et était masqué dans le mode unique. Il propose maintenant 1–8 avec le libellé « 1 plan ». Le nombre reste accessible en mode unique ; choisir 1 active ce mode, et choisir plusieurs plans permet d’en sortir.

Pour des cartes vides, le découpage se réduit à un seul plan uniquement si le modèle accepte exactement la durée demandée. Un plan de 5 s peut devenir 10 s sur changement explicite de durée, sans réécrire son prompt. Aucun arrondi de durée, changement de fournisseur ou génération implicite.

Des cartes déjà renseignées utilisent la conversion serveur existante : elles ne sont pas supprimées par le navigateur. Ses protections restent actives pour le total, les textes, les images et les contextes distincts. Si la réunion est impossible, les cartes restent présentes et le récapitulatif est bloqué avec explication. Une préparation existante avec une seule carte réaffiche le nombre 1 côté serveur.

## Prix et variante réellement choisie

Le raccourci de la famille sélectionnée suit maintenant sa variante : nom, résolution, sélection visuelle et prix sont actualisés ensemble. Le sélecteur de résolution présente un exemple de prix pour chaque variante compatible, avec une indication visible sous le champ.

La comparaison reprend la durée du premier plan si le modèle la supporte ; sinon l’exemple utilise une durée native clairement affichée. Cela reste un exemple par clip, pas un total inventé pour plusieurs plans. Frais fixes, tarif par durée et frais de référence d’exemple restent inclus ; lorsqu’un coût par référence existe, l’exemple indique une référence. Le devis de récapitulatif reste le calcul des entrées réelles.

Le prix suit aussi les changements de durée. La recette navigateur a révélé un conflit d’événements : le `input` natif du sélecteur provoquait son rafraîchissement avant `change`, rétablissant la variante précédente. Le contrôleur attend maintenant que l’action de résolution applique le nouveau modèle avant ce rafraîchissement. Le changement 720p → 1080p ne se contente donc pas d’afficher une autre valeur : le choix envoyé et les prix suivent effectivement.

## Vérifications

- Deux fonctionnels personnages/Studio, 140 assertions : quatre parcours réels de formulaire, un plan de 5/10 s en 720p/1080p, texte français conservé, modèle et durée exacts, devis 41/82/62/123 cents USD, états draft, aucune génération. Le second scénario contrôle les trois recommandations compatibles.
- Trois régressions fonctionnelles du plan unique, 50 assertions : durée native, refus de perte de durée/texte et refus d’effacer les images propres aux plans.
- 204 frontend, check et build verts. PHPStan/style de trois fichiers PHP et 37 Twig verts ; cache dev actualisé. Pas de suite PHP globale ni de revue indépendante.
- Quatre largeurs navigateur 320/390/768/1280 : création d’une carte unique via le champ nombre, passage 5 → 10 → 5, changement 720p ↔ 1080p, carte et montant actualisés, prompt inchangé, récapitulatif accessible, aucun débordement ni erreur JS/console. Capture mobile relue. HTML synthétique, réseau intercepté, aucun appel de génération.

Le banc navigateur utilise une origine de boucle locale reconnue comme contexte sûr. Une première tentative sur un nom HTTP fictif faisait échouer `crypto.randomUUID` du contrôleur autosave ; cela a été corrigé dans le banc. La lecture de dernière proposition de scénario est simulée explicitement. Les erreurs intermédiaires ne sont pas présentées comme des passages verts.

Aucun contrat/tarif fournisseur modifié, aucune migration, clé réelle lue, dépense, upload fournisseur, génération, publication, email, push ou déploiement. Aucun worker redémarré ; seules interface/formulaire/présentation changent. Les nouveaux modèles et dialogues français restent à tester par le propriétaire sur son devis et son accord. R7 reste ouvert.
