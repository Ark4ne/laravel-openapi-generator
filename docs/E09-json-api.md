# E9 — Intégration JSON:API

**Objectif.** Documenter les ressources, collections et requêtes de `ark4ne/laravel-json-api` comme une
extension isolée, active seulement quand le paquet est installé.

**Dépend de :** E8.
**Architecture :** [architecture.md](architecture.md) §2 (Integrations).

## Livrables

### Enregistrement (`src/Integrations/JsonApi/`)

- [ ] Fournisseur de l'intégration : extracteurs, mappers de règles et stratégie d'erreur enregistrés si
      `ark4ne/laravel-json-api ^1.9` est présent, rien sinon
- [ ] Type de média `application/vnd.api+json`

### Réponses

- [ ] `JsonApiResourceExtractor` (priorité au-dessus de `JsonResourceExtractor`) : `data` (`id`, `type`,
      `attributes`, `relationships`, `links`, `meta`), `included`
- [ ] Types des attributs lus dans les descripteurs du paquet (`Ark4ne\JsonApi\Descriptors`) avant tout échantillonnage
- [ ] Relations simples et multiples, liens et méta des relations
- [ ] `JsonApiCollectionExtractor` et pagination (liens et méta JSON:API)

### Requêtes

- [ ] Règles `Includes` et `Fields` → paramètres `include` et `fields[type]` (`deepObject`)
- [ ] Paramètres de tri, de filtre et de pagination quand le paquet les déclare

### Erreurs

- [ ] Schéma d'erreur JSON:API (`errors[]`) pour les réponses d'erreur de E7

## Critères de sortie

- [ ] Scénario fonctionnel JSON:API ; parité avec le document attendu v0.6 `openapi-jsonapi.json` (écarts justifiés)
- [ ] Suite complète verte sans le paquet installé (l'intégration ne charge aucune de ses classes)
