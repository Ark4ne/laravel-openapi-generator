# E11 — Migration, documentation et version 1.0.0

**Objectif.** Publier la 1.0.0 avec un guide de migration depuis la v0.6, une documentation complète et un skill
pour les agents.

**Dépend de :** E0 à E10.

## Livrables

### Dépendances

- [ ] `ark4ne/openapi-writer` 1.0.0 publié sur Packagist ; dépôt `path` retiré de `composer.json`
- [ ] Contraintes vérifiées au plus bas et au plus haut ; Laravel 14 ajouté s'il est sorti

### Documentation

- [ ] `UPGRADE.md` : configuration v0.6 → V1 (table de E1), balises `@oa-*` → attributs, parsers → extracteurs,
      transformateurs → `MiddlewareMapper`, `DescribableRequest`
- [ ] `README.md` réécrit : installation, configuration, attributs, extensions, FAQ
- [ ] Skill pour agents `skills/laravel-openapi-generator/` (en anglais, livré dans le paquet, exemples testés),
      sur le modèle de celui du writer
- [ ] `CHANGELOG.md` de la 1.0.0

### Qualité

- [ ] Couverture ≥ 90 % des lignes, MSI ≥ 80 %
- [ ] CI verte sur toute la matrice
- [ ] Génération vérifiée sur une application réelle (Sanctum + JSON:API), documents validés en 3.0, 3.1 et 3.2

### Publication

- [ ] Branche `v1.0` fusionnée dans `master`, tag `v1.0.0`
- [ ] Politique de maintenance de la v0.6 décidée et annoncée

## Critères de sortie

- [ ] `composer require ark4ne/laravel-openapi-generator:^1.0` fonctionne sur un projet Laravel 13 neuf
