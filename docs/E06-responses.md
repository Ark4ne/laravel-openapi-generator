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

### Exceptions déclarées (`src/Extraction/Exceptions/`)

Une exception de `@throws` (E3) devient une réponse par une chaîne de correspondances : la première qui la
reconnaît l'emporte (registre `ExceptionMappers`, priorité décroissante).

- [ ] Contrat : `ExceptionMapper`, `ExceptionMappers`, nommage `*ExceptionMapper` vérifié par `phpat`, cas
      `ResponseOrigin::Exception`, clé globale `exceptions` (`read_throws`, `map`) ; `#[Response]` accepté sur une
      **classe d'exception** (`TARGET_CLASS`), où il décrit la réponse que Laravel en fait
- [ ] `AttributeExceptionMapper` : `#[Response]` de la classe de l'exception ou d'une classe parente (statut,
      description, ressource ou schéma)
- [ ] `ConfiguredExceptionMapper` : clé `exceptions.map` (`Classe::class => 402` ou `[status, description,
      resource]`), pour les exceptions tierces et celles que `bootstrap/app.php` transforme par des closures
- [ ] `LaravelExceptionMapper` : table de `prepareException()`, sous-classes comprises — `AuthenticationException`
      401, `AuthorizationException` 403, `ModelNotFoundException` / `RecordsNotFoundException` /
      `BackedEnumCaseNotFoundException` 404, `TokenMismatchException` 419, `ValidationException` 422,
      `ThrottleRequestsException` 429
- [ ] `SymfonyHttpExceptionMapper` : classes HTTP de Symfony à statut fixe (`NotFoundHttpException` 404,
      `ConflictHttpException` 409, `TooManyRequestsHttpException` 429…)
- [ ] `HttpExceptionInstanceMapper` : sous-classe de `HttpException` dont le constructeur n'a aucun argument obligatoire,
      construite pour lire `getStatusCode()` ; échec → mapper suivant
- [ ] Exception sans correspondance : diagnostic `Info`, aucune réponse (jamais de 500 inventé)
- [ ] Corps par défaut : erreur JSON de Laravel `{message}` en composant partagé, remplaçable (schéma d'erreur
      d'E7) ; `ValidationException` → schéma 422 d'E7
- [ ] Réponses produites avec l'origine `ResponseOrigin::Exception`, fusionnées en E7
- [ ] Un test unitaire par mapper ; scénario fonctionnel « exceptions » (exception annotée, configurée, de Laravel,
      de Symfony, `HttpException` maison, inconnue)

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
