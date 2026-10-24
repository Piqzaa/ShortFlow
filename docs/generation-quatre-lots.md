# Génération IA : réalisation des quatre lots

Autorisation du propriétaire le 24 octobre 2026, après l’audit `audit-generation-studio-series-2026-10-24.md`. Réalisation successive, tests simulés, commits locaux sans push. Aucune génération payante ni publication réelle pendant ce chantier.

## Suivi

- [x] Lot 1 : mêmes rôles Studio/Séries, sélection explicite, retrait programmé de Pika, libellés honnêtes, format final explicite, tests existants réparés sans purge de file.
- [ ] Lot 2 : références de sujets et cadres propres aux plans, conservation des anciennes préparations, récapitulatif des entrées et empreinte d’exécution.
- [ ] Lot 3 : plan unique explicite, multi-plan natif compatible et extension vidéo native avec devis du contexte.
- [ ] Lot 4 : prompts adaptés, aperçus/reprise, audio et entretien sûr des références.

Les détails des fichiers, du flux Symfony, des vérifications et des limites seront ajoutés à chaque lot terminé.

## Lot 1 : harmoniser la préparation

`AiCatalog::referenceMode` distingue texte, premier cadre et personnage. `AiIdeaType`, le contrôleur Studio et le catalogue Twig reprennent ces rôles. Le contrôleur Stimulus ne remplace plus un endpoint lors d’un changement de source ; l’import d’image ne choisit plus Kling côté serveur. Les références restent contrôlées par propriétaire, y compris à l’enregistrement d’un brouillon personnage.

Pika conserve ses identifiants et ses résultats, mais sort des raccourcis recommandés. Ses cartes affichent sa date de retrait ; une validation serveur interdit les nouveaux envois à compter du 29 octobre 2026. LTX 2.3 Fast 720p texte expose les mêmes durées natives à 25 FPS que la variante image.

Un montage exige maintenant 9:16, 16:9 ou 1:1 avant devis. Les anciennes générations simples au format de l’image restent lisibles. La préparation demande un choix explicite plutôt que de transformer un 4:5 en 9:16 sans information. Le raccord d’image est nommé comme tel, sans promesse de continuité native.

Vérifications : 112 tests frontend réussis, build Vite réussi, PHPStan et 88 templates Twig valides. Quatre tests PHP ciblés, 113 assertions réussies : rôles/retrait, propriété des images, recommandations et scénario privé. Les deux échecs de l’audit ont été corrigés par mise à jour du sélecteur pertinent et vérification du nombre de nouveaux messages, sans supprimer les messages existants.

À tester sans coût : changer texte/premier cadre/personnage, constater l’absence de modèle sélectionné par substitution, choisir explicitement le bon modèle et le format final, ouvrir le récapitulatif. Aucune génération ne part à ces étapes. En Symfony, le rôle du modèle est une règle serveur ; Stimulus représente cette règle et ne confirme jamais un coût.
