# Calendrier : dates exactes et alignement — 7 novembre 2026

Retour propriétaire après le premier calendrier : champs décalés et choix d’une période trop indirect. Le parcours commence désormais par la date et l’heure propres à chaque épisode.

## Parcours

Le nombre d’épisodes et le fuseau sont placés en tête. « Afficher ce nombre d’épisodes » ajuste la liste en conservant les premières dates saisies, sans créer d’épisode. Chaque ligne indique l’épisode existant ou celui restant à préparer et son champ natif date/heure. Les libellés génériques ne sont plus affichés deux fois.

« Remplir les dates avec un rythme » est un outil facultatif replié : période, jours et heure commune proposent une première liste. Chaque date et chaque heure restent ensuite modifiables individuellement. Les champs de rythme ne sont pas obligatoires pour saisir les dates directement. L’aperçu et sa sauvegarde restent distincts et protégés par révision/hash.

Les dates exactes doivent être dans les 90 prochains jours, distinctes et dans l’ordre des épisodes ; plusieurs épisodes le même jour sont possibles à des heures différentes. Heure locale/fuseau et changements d’heure sont contrôlés par `PublicationTime`. Les publications confirmées restent exclues du nouveau calendrier. Les anciens calendriers sont relus et convertis en champs locaux sans modification en base ; seules les sauvegardes explicites remplacent leur prévision.

L’alignement est corrigé dans le périmètre du calendrier : marges des colonnes neutralisées, champs de même hauteur et empilement mobile. Aucune autre page de formulaire n’est modifiée.

## Fichiers et Symfony

- `SeriesCalendarType` ajoute une collection de champs texte rendus en `datetime-local`. Les validations du rythme appartiennent au groupe Symfony `rhythm`, utilisé seulement pour demander un remplissage.
- `SeriesCalendarController` traite ajustement du nombre, remplissage, aperçu et sauvegarde. Il conserve les dates saisies et recharge les dates enregistrées dans leur fuseau. Tout fonctionne aussi sans JavaScript.
- `SeriesCalendar` valide les dates exactes, puis réutilise les mêmes affectations d’épisodes et protections de sauvegarde. `targets()` fournit les épisodes accessibles ; le client ne choisit pas des identifiants de sources.
- La vue Twig expose la liste et l’aide facultative ; le CSS est limité à `.series-calendar-form` et ses lignes. Le JSON existant `calendarPlan` conserve le résultat, sans nouvelle migration.

Flux : nombre/fuseau → saisie directe (ou remplissage) → aperçu contrôlé → sauvegarde explicite → réouverture avec les mêmes dates. La génération et la publication ne sont pas activées par ces actions.

## Vérifications

**5 tests fonctionnels / 65 assertions** : saisie/aperçu/sauvegarde/reprise, ancien calendrier préservé si erreur, CSRF/propriétaire/révision, publications existantes conservées, remplissage retouché avec horaire propre, rythme laissé vide en saisie manuelle, nombre ajusté sans création d’épisode. **6 unitaires / 26 assertions** : anciens rythmes et dates exactes, dont plusieurs horaires dans une journée, doublons/désordre/absence/date invalide et changements d’heure refusés. Les passages intermédiaires ne sont pas additionnés ; base exclusive `shortflow_test`, clients externes bloqués.

PHPStan trois applicatifs, style cinq PHP, 21 Twig et conteneur Symfony verts. `npm run check` et build réussis ; avertissement Vite de gros chunk déjà connu. Aucun nouveau JS, aucune nouvelle suite PHP/frontend globale ni revue indépendante prétendue.

**14 états Chrome** : initial/personnalisé/enregistré en 320/390/768/1280 et deux vues 390 sans JS. Dates modifiées conservées à l’ouverture/fermeture du rythme et dans FormData ; aucun formulaire imbriqué, débordement, erreur console/JS ou POST réel. Alignement haut **et hauteur** des deux paires de champs mesurés à moins d’un pixel en desktop. Captures finale desktop et mobile relues.

Cache web local actualisé. Onze GET200 authentifiés en transaction READ ONLY, 29 compteurs inchangés et zéro HTTP fournisseur. Aucun worker redémarré, migration, contenu/calendrier propriétaire enregistré, modèle réel, coût, upload, publication, push ou déploiement public.

Essai : Série IA → Calendrier de la série → saisir les dates exactes → aperçu → enregistrer. Le rythme est seulement un raccourci facultatif. Après relecture propriétaire, reprendre la liaison des textes automatiques au calendrier ; ces dates restent des prévisions.
