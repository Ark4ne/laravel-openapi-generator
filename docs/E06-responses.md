# E6 — Réponses

**Objectif.** Décrire chaque réponse possible d'une action (statut, en-têtes, contenu) à partir de son type de
retour, de l'exécution des ressources et des attributs `#[Response]`.

**Dépend de :** E5.
**Architecture :** [architecture.md](architecture.md) §4.1 et §5.

## Livrables

### Extracteurs (`src/Extraction/Responses/`)

- [ ] `JsonResourceExtractor` : enveloppe `data` (et `withoutWrapping()`), `with()`, `additional()`, statut
- [ ] `ResourceCollectionExtractor` et `AnonymousResourceCollection` (`UserResource::collection()`, génériques)
- [ ] Pagination : `LengthAwarePaginator`, `Paginator` simple, `CursorPaginator`, avec la forme `meta` / `links`
      de Laravel 13 ; déclarée par `#[Response(paginator: …)]` ou déduite du type de retour
- [ ] `JsonResponseExtractor` : schéma de `#[Response]`, sinon objet libre et diagnostic
- [ ] Modèle, `Collection` Eloquent ou `array{…}` retournés directement (sérialisation de Laravel)
- [ ] `Response` vide et `noContent()` (204), `RedirectResponse` (302 + en-tête `Location`)
- [ ] `BinaryFileResponse` et `download()` (`application/octet-stream`, `Content-Disposition`)
- [ ] `StreamedResponse`, `StreamedJsonResponse` (`streamJson()`)
- [ ] `eventStream()` : `text/event-stream`, schéma des événements en `itemSchema` en 3.2, diagnostic avant
- [ ] `Responsable` : exécution de `toResponse()` sur un échantillon
- [ ] Vues, Inertia et réponses inconnues : ignorées avec diagnostic

### Attribut `#[Response]`

- [ ] Répétable : un attribut par statut ; prioritaire sur l'inférence pour son statut
- [ ] `status`, `description` (phrase HTTP par défaut), `resource` ou `schema`, `mediaType`, `headers`,
      `paginator`, `summary` (3.2), `example`
- [ ] Correction du comportement v0.6 : `#[Response(description: 'Created')]` seul ne change plus le statut

### Polymorphisme

- [ ] Union de ressources pour un même statut (`CatResource|DogResource`) → `oneOf` ; `discriminator` (et `mapping`)
      quand une propriété commune porte une valeur constante différente par membre (`type`)

### En-têtes et composants

- [ ] En-têtes de la réponse échantillonnée et de l'attribut, sans les en-têtes techniques (`Cache-Control`, `Date`)
- [ ] Ressource → composant de schéma nommé (`#[SchemaName]` ou nom de classe), collisions signalées
- [ ] Option `use-ref` de la v0.6 conservée (composants ou schémas en ligne)

## Critères de sortie

- [ ] Un test unitaire par extracteur ; scénarios fonctionnels « ressources », « pagination », « fichiers et flux »
- [ ] Parité avec les réponses du document attendu v0.6 `openapi-jsonresource.json` (écarts justifiés)
