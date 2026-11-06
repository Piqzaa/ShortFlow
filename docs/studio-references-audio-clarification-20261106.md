# Clarification des références et de l’audio — 6 novembre 2026

Retour propriétaire : audio trop discret dans la ligne du prix ; référence principale présentée sous « autres références », nom générique pris pour un deuxième personnage ; rôle objet inexpliqué ; présence globale sans plan identifié.

Finition suivante : la ligne de prix ne répète plus l’audio, désormais indiqué uniquement dans le badge du raccourci. Les nouveaux ajouts prennent le nom « Sujet secondaire », puis un suffixe si nécessaire ; aucun nom automatique n’est ajouté dans le champ vide de la référence principale. Les noms déjà renseignés restent conservés. Roadmap R7 actualisée avec les essais déjà exécutés et la suite réelle, sans cocher les validations de voix ou de testeurs encore absentes.

Changements d’interface :

- Badge distinct « Avec audio » ou « Sans audio · aucune voix » sur chaque raccourci, mis à jour avec le modèle. Capacités et limites audio du catalogue conservées.
- Section renommée « Personnages et références de cette vidéo ». Le profil principal indique explicitement qu’il décrit l’image importée au début : il ne demande pas une seconde photo. Le nom sert à désigner ce personnage dans les prompts ; aucun nom n’est déduit de l’image par IA.
- Ajout renommé « Ajouter un autre personnage ou objet » ; photo supplémentaire clairement distinguée de l’image principale.
- « Rôle » devient « Cette image représente » : personnage (humain, animal ou imaginaire) ou objet (voiture, produit…). Les valeurs de contrat `character/object` restent identiques.
- Nouveaux profils : plus de sélecteur de présence commun à tous les plans. Valeur `unspecified` conservée dans le formulaire, avec consigne de préciser qui apparaît et quand dans chaque plan. Une photo importée reste une référence commune ; elle n’est pas affectée à un sous-ensemble de plans par ce correctif. Les fiches enregistrées conservent leurs cases de présence par plan.
- Données anciennes avec `visible/enters` : réglage préservé dans un bloc replié explicitement nommé « Ancien réglage commun à tous les plans », avec possibilité de revenir à la présence décrite dans les scènes. Aucun changement silencieux du sens d’un ancien brouillon.
- Visibilité coordonnée des contrôleurs : pas de bloc d’import redondant en mode bibliothèque, pas de faux avertissement de modèle incompatible avant choix d’un modèle. Les références déjà saisies restent récupérables au changement d’origine/modèle.

Vérifications : 203 tests frontend, check et build verts ; trois fonctionnels ciblés / 126 assertions et un parcours upload/bibliothèque / 20 assertions. 37 Twig, PHPStan/style du formulaire verts. Quatre largeurs navigateur 320/390/768/1280 : référence principale expliquée, présence globale masquée pour nouveaux profils, ajout supplémentaire, changement bibliothèque/upload sans perte des champs, badges audio visibles, aucun débordement ni erreur JS. Capture mobile relue. Routes/images/soumissions navigateur interceptées ; aucun upload réel.

Cache web dev actualisé, aucun worker redémarré. Aucun changement de contrat fournisseur, tarification, schéma ou génération. Aucun coût, appel fournisseur réel, clé réelle lue, migration, email, publication, push ou déploiement. Pas de suite PHP globale ni de revue indépendante ; R7 reste ouvert.
