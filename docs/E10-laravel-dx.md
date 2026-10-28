# E10 — Intégration Laravel et expérience développeur

**Objectif.** Un paquet qui s'installe, se configure et se débogue comme un paquet Laravel moderne : fournisseur
de services, configuration publiable, commandes claires, extensions faciles à brancher.

**Dépend de :** E8 (E9 pour l'enregistrement de l'intégration).
**Architecture :** [architecture.md](architecture.md) §2 (Laravel) et §8.

## Livrables

### Fournisseur (`src/Laravel/`)

- [ ] `OpenApiServiceProvider` (découverte automatique) : configuration fusionnée et publiable
      (`vendor:publish --tag=openapi-config`), registres construits depuis la configuration
- [ ] Extensions de l'application : extracteurs, mappers de règles, samplers, passes, stratégies, déclarés dans
      `extensions`, et par les tags du conteneur (`openapi.response-extractors`…) pour les paquets tiers
- [ ] Entrée dans `php artisan about`

### Commandes

- [ ] `openapi:generate {--document=*} {--strict} {--dry-run} {--format=} {--stdout}` : sortie par
      `$this->components` (tâches, avertissements groupés par route), codes de retour documentés
- [ ] `openapi:inspect {route}` : ce que le générateur a compris d'une route (description, diagnostics), pour
      déboguer une documentation inattendue
- [ ] Verbosité : `-v` affiche les diagnostics `Info`, `-q` n'affiche que les erreurs

### Configuration

- [ ] Erreurs de configuration explicites au chargement (clé, valeur attendue, valeur reçue)
- [ ] Chaque clé documentée dans `config/openapi.php` et dans le README

### Performance

- [ ] Mesure sur une application de 500 routes (`composer benchmark`) : temps et mémoire consignés dans les décisions

## Critères de sortie

- [ ] Tests des commandes (Testbench) et du fournisseur
- [ ] Installation vérifiée sur un squelette Laravel 13 neuf (`composer require`, `openapi:generate`)

## Hors périmètre V1

- Interface de consultation (Swagger UI, Scalar, Redoc) et route qui sert le document : laissées à des paquets dédiés.
