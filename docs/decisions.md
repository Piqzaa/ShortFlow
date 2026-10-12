# Décisions techniques

1. **Symfony 7.4 LTS / PHP 8.4 Docker** : cible demandée, support long et environnement reproductible. Initialisation Composer faite avec PHP 8.3 installé, car PHP 8.1 est le défaut Windows ; exécution applicative vérifiée sous PHP 8.4 Docker.
2. **Monolithe modulaire classique** : contrôleurs, services, entités et formulaires. Pas de couches métier spéculatives.
3. **Vite plutôt qu’AssetMapper** : bundling npm, découpage des imports Three.js/GSAP et polices locales ; pipeline unique. Coût : une étape de compilation et un outil Node. Twig référence les entrées app.js/app.css stables ; chunks hachés. En production future, déploiement atomique/cache des entrées à définir.
4. **PHP intégré en développement** : minimal et portable, port lié à 127.0.0.1. À remplacer pour une production.
5. **Liste d’attente simple** : table email/date, CSRF, validation, index unique, réponse neutre, honeypot et limiteur Symfony. Pas de mail envoyé, de double opt-in ni d’abonnement payant. Le cache antispam est local à l’instance.
6. **Messenger préparé sans worker** : aucune tâche asynchrone réelle ne justifie encore un transport.
7. **Three.js procédural et poster original** : aucun actif tiers ou téléchargement de vidéo obligatoire. Fallback prioritaire, animations progressives et GPU nettoyé.
8. **Tests sur PostgreSQL séparé** : vérifier le comportement réel de validation et persistence sans toucher aux données de développement.
9. **Pas de service externe** : pas de cloud, analytics, réseau social ou dépense. Confidentialité actuelle décrite comme locale, à finaliser avant lancement public.
