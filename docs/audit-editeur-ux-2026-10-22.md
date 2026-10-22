# Simplifier le mini-éditeur — analyse du 22 octobre 2026

## Conclusion produit

La priorité est la prévisibilité : savoir quel objet va changer et à quel instant. Ajouter des commandes sans expliciter la sélection renforce la confusion. Le mini-éditeur doit conserver un chemin court : sélectionner un objet, déplacer le curseur, appliquer une commande dont la cible est nommée.

## Comparaison de parcours documentés

| Éditeur | Interaction vérifiée | Choix pour VydFlow |
| --- | --- | --- |
| Clipchamp | Sélection du clip surligné, déplacement du curseur, bouton de séparation ou S ; même logique pour l’audio. | Distinguer sélection d’édition et clip lu, afficher le type et le nom de la cible, désactiver la coupe hors du bloc. |
| CapCut | Sélection dans la timeline, coupe au curseur, catégories explicites pour les outils et effets. | Onglet Effets visible directement, filtres sous forme de cartes nommées avec état actif. |
| Canva | Découpe et arrangement des clips directement dans la timeline ; durée propre aux éléments comme le texte. | Conserver les pistes indépendantes et rendre leur sélection explicite ; une coupe de texte ne doit pas toucher la vidéo. |

Sources primaires consultées le 22 octobre : [Clipchamp : couper vidéos/audio](https://support.microsoft.com/en-us/clipchamp/how-to-split-or-cut-videos-and-audio-assets), [CapCut : parcours Desktop](https://www.capcut.com/resource/capcut-desktop-download), [Canva : créer et éditer les vidéos](https://www.canva.com/en_au/help/creating-and-editing-videos/), [Canva : durée des éléments](https://www.canva.com/en_in/help/element-timing-video/). Comparaison des parcours publiés, sans connexion à des comptes concurrents ni prétention à un test complet de leurs applications.

## Changements livrés

- La lecture ne change plus implicitement la cible d’édition. Cliquer un objet le sélectionne sans déplacer le curseur. La barre nomme la cible : Vidéo avec numéro/nom, Texte, Sous-titre ou Audio. Le bouton porte cette cible (« Couper le texte ») ; hors du bloc il est désactivé. S utilise la même commande. Les modifications/suppressions utilisent la sélection d’édition, pas simplement le clip actuellement lu. La suppression avec rapprochement est masquée pour une piste texte/audio.
- Contour facultatif avec interrupteur visible ; les nouveaux textes sobres n’ont ni contour ni ombre. Les réglages explicitement enregistrés restent repris. L’alignement reste disponible dans Options du texte : il règle la disposition des lignes à l’intérieur du bloc, ce qui reste utile pour les textes longs, indépendamment de sa position sur l’image.
- Couleurs : pastille réellement remplie, code hexadécimal lisible et éditable, synchronisés avec l’aperçu. Ce changement corrige notamment le padding hérité qui réduisait la pastille native à une ligne.
- Douze familles locales : Sans, Serif, Mono, Anton, Bebas Neue, Oswald, Inter, Montserrat, Poppins, Roboto, Lato et Open Sans ; trois variantes grasses DejaVu en plus. Pas de chargement distant à l’usage. Les polices commerciales non distribuables ne sont pas copiées.
- Effets directement accessibles depuis les onglets, cartes Original/Chaud/Froid/Vif/Doux/Noir et blanc ; réglages de cadrage et intensité toujours disponibles. L’état actif est visible et accessible au clavier.

## Correction de la continuité

L’aperçu utilisait le temps du lecteur comme point de coupe et avançait avant la fin du fragment. La coupe part désormais du curseur du projet, aligné sur les 30 images/s de l’export. La lecture suit les images affichées via requestVideoFrameCallback, avec fallback requestAnimationFrame. Deux fragments adjacents d’une même source, avec point d’entrée égal au point de sortie et vitesse identique, continuent sans recharger ni chercher dans la vidéo. La sélection d’édition ne suit pas automatiquement cette lecture.

À l’export, EditTimeline fusionne uniquement pour le rendu les passages source contigus de même vitesse et même habillage, sans fondu interne volontaire. Cela évite un réencodage séparé et l’amorçage AAC à une simple coupe. Les deux blocs restent indépendants dans la recette éditable. Les effets différents, les espaces et les fondus internes restent respectés. Les timestamps vidéo sont remis à zéro avant changement de vitesse.

## Fichiers et notions Symfony

Stimulus porte la sélection, le curseur et les couleurs : assets/controllers/edit_controller.js. Les templates edit/show, _clip et _typography exposent les commandes et les champs du formulaire. editor-desk.css présente les états et les pastilles. EditTypography valide la liste des polices et les valeurs serveur ; EditTimeline prépare le rendu et VideoEditRenderer exécute les filtres. Les nouvelles polices et leurs licences sont dans public/fonts/editor.

La séparation à retenir : l’état de sélection est une interaction locale ; la recette validée est persistée par le service Symfony puis Doctrine ; le worker exporte cette recette. La fusion de rendu est une optimisation, elle ne modifie pas les blocs stockés. Aucune nouvelle migration dans ce second lot UX, aucune génération payante ou publication sociale.

## Vérifier manuellement

Sélectionner un clip puis déplacer seulement le curseur ; vérifier le nom et le bouton, couper, et lire le raccord. Sélectionner ensuite un texte et couper au milieu : la vidéo ne doit pas changer. Placer le curseur hors du texte : la coupe doit être désactivée. Désactiver le contour, choisir une couleur hexadécimale et une nouvelle police, enregistrer/recharger puis exporter. Ouvrir Effets et choisir une carte ; vérifier clavier et téléphone.

## Suite raisonnable

Après retour sur cette version : miniature du vrai clip dans les cartes d’effets, aperçu exporté temporaire pour les gros montages, puis raccourcis et aide contextuelle. Les transitions croisées, animations complexes et polices personnelles ne sont pas nécessaires pour résoudre les problèmes actuels. L’aperçu de deux sources différentes peut encore subir le chargement du navigateur ; cette correction garantit spécifiquement le raccord d’une simple coupe dans une même source.


## Simplification complémentaire après essai du propriétaire

Le propriétaire demande le retrait du cadrage horizontal/vertical, de l’intensité et des styles intégrés Sobre/Accroche/Encadré dans les textes et sous-titres. Ces commandes sortent du parcours. Le déplacement du cadrage par glisser l’image est retiré aussi. Les coordonnées/intensités déjà enregistrées restent transmises en champs cachés pour conserver les anciens rendus ; choisir une nouvelle carte de filtre applique désormais son intensité complète. Original remet aussi luminosité, contraste et saturation à leur valeur neutre.

Le sélecteur de styles intégrés disparaît. Les styles personnels enregistrés restent utilisables dans un groupe Mes styles, visible uniquement si le compte en possède. Correction d’une perte des booléens lors de leur application : true/false doivent devenir les valeurs 1/0 des contrôles HTML. Les anciennes polices *-bold deviennent une famille et l’option Gras activée : la liste ne duplique plus les familles, et Gras peut réellement être désactivé. Même présentation pour textes et sous-titres.

Un interrupteur Afficher les sous-titres remplace les catégories de couleur redondantes ; leur apparence est réglée directement par police, couleur, fond et contour. Le contrôle natif historique reste accessible sans JavaScript. Correction aussi de la position numérique des fragments (maximum 32 et non 8) et du message après retrait d’une musique quand une autre reste présente.

Fichiers : _clip et _typography définissent les champs réellement proposés, show organise l’affichage, Stimulus applique les styles et la visibilité, VideoEditController annonce correctement le résultat du retrait musical. La validation Symfony des anciennes recettes reste en place. L’état local des poignées est séparé de la recette ; il ne devient jamais un effet exporté. Les vérifications automatisées et captures sont consignées dans verification.md.

Limites restantes connues : chargement possible entre deux fichiers vidéo distincts dans l’aperçu HTML, pas de transitions croisées ou de montage vidéo superposé. Elles sont distinctes des commandes retirées et ne sont pas présentées comme disponibles dans l’interface.
