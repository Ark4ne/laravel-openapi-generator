# E6 — Réponses

**Objectif.** Décrire chaque réponse possible d'une action (statut, en-têtes, contenu) à partir de son type de
retour, de l'exécution des ressources et des attributs `#[Response]`.

**Dépend de :** E5.
**Architecture :** [architecture.md](architecture.md) §4.1 et §5.

## Conception

```
type de retour ──► ResponseExtractors (le premier qui reconnaît, priorité décroissante) ──┐
#[Response] (DeclaredResponses) ─────────────────────────────── remplace par statut ──────┼──► OperationResponses
@throws (ExceptionResponses : ExceptionMappers) ───────────────────── ajoutées ───────────┘
```

- **Ressources** (`ResourceSchemas`) : modèle de la ressource (E5), échantillon, `toArray()` exécuté deux fois, sans
  puis avec les relations du modèle chargées (`RelationLoader`, échantillons `make()` sur un niveau) ; les deux
  schémas sont réunis : une clé présente dans les deux exécutions est obligatoire, une clé qui n'apparaît qu'avec les
  relations est facultative mais décrite. La ressource devient un composant (`#[SchemaName]` ou nom de classe sans
  `Resource`), référencé par `$ref`, ou écrit en ligne quand `use_references` est faux.
- **Enveloppe** : `data` (`$wrap`, `withoutWrapping()`), `with()`, `additional()` ; pagination de Laravel 13 écrite
  d'après la forme réelle de `LengthAwarePaginator`, `Paginator` et `CursorPaginator` (`links`, `meta`).
- **Statut** : 201 pour une ressource renvoyée par `POST` (Laravel répond 201 pour un modèle tout juste créé), 200
  sinon ; `Response` sans corps : 204 pour `DELETE`, 200 sinon.
- **Même statut, plusieurs types** (`CatResource|DogResource`) : `oneOf`, avec `discriminator` quand une propriété
  commune prend une valeur différente dans chaque échantillon.
- **Exceptions** : `ExceptionMappers` (priorité décroissante) ; corps par défaut, composants `Error` (`message`) et
  `ValidationError` (`message`, `errors`).

## Livrables

### Extracteurs (`src/Extraction/Responses/`)

- [x] `JsonResourceExtractor` : enveloppe `data` (et `withoutWrapping()`), `with()`, `additional()`, statut
- [x] `ResourceCollectionExtractor` et `AnonymousResourceCollection` (`UserResource::collection()`, génériques)
- [x] Pagination : `LengthAwarePaginator`, `Paginator` simple, `CursorPaginator`, avec la forme `meta` / `links`
      de Laravel 13 ; déclarée par `#[Response(paginator: …)]` ou déduite du type de retour
- [x] `JsonResponseExtractor` : schéma de `#[Response]`, sinon objet libre et diagnostic
- [x] Modèle, `Collection` Eloquent ou `array{…}` retournés directement (sérialisation de Laravel)
- [x] `Response` vide et `noContent()` (204), `RedirectResponse` (302 + en-tête `Location`)
- [x] `BinaryFileResponse` et `download()` (`application/octet-stream`, `Content-Disposition`)
- [x] `StreamedResponse`, `StreamedJsonResponse` (`streamJson()`)
- [x] `eventStream()` : `text/event-stream` déclaré par `#[Response(mediaType:)]` (le type de retour ne le distingue
      pas d'un flux), schéma d'un événement en `itemSchema` (écrit en 3.2 par E8)
- [x] `Responsable` : exécution de `toResponse()` sur un échantillon
- [x] Vues, Inertia et réponses inconnues : ignorées avec diagnostic

### Corps de requête décrit par une ressource

- [x] `#[RequestBody(resource:)]` : schéma de la ressource échantillonnée (diagnostic `Info` depuis E4)

### Attribut `#[Response]`

- [x] Répétable : un attribut par statut ; prioritaire sur l'inférence pour son statut
- [x] `status`, `description` (phrase HTTP par défaut), `resource` ou `type`, `mediaType` (`text/event-stream` →
      schéma d'un événement), `headers`, `paginator`, `summary` (3.2), `example` ; sans `resource` ni `type`, le
      contenu inféré du statut est gardé
- [x] Correction du comportement v0.6 : `#[Response(description: 'Created')]` seul ne change plus le statut

### Exceptions déclarées (`src/Extraction/Exceptions/`)

Une exception de `@throws` (E3) devient une réponse par une chaîne de correspondances : la première qui la
reconnaît l'emporte (registre `ExceptionMappers`, priorité décroissante).

- [x] Contrat : `ExceptionMapper`, `ExceptionMappers`, nommage `*ExceptionMapper` vérifié par `phpat`, cas
      `ResponseOrigin::Exception`, clé globale `exceptions` (`read_throws`, `map`) ; `#[Response]` accepté sur une
      **classe d'exception** (`TARGET_CLASS`), où il décrit la réponse que Laravel en fait
- [x] `AttributeExceptionMapper` : `#[Response]` de la classe de l'exception ou d'une classe parente (statut,
      description, ressource ou schéma)
- [x] `ConfiguredExceptionMapper` : clé `exceptions.map` (`Classe::class => 402` ou `[status, description,
      resource]`), pour les exceptions tierces et celles que `bootstrap/app.php` transforme par des closures
- [x] `LaravelExceptionMapper` : table de `prepareException()`, sous-classes comprises — `AuthenticationException`
      401, `AuthorizationException` 403, `ModelNotFoundException` / `RecordsNotFoundException` /
      `BackedEnumCaseNotFoundException` 404, `TokenMismatchException` 419, `ValidationException` 422,
      `ThrottleRequestsException` 429
- [x] `SymfonyHttpExceptionMapper` : classes HTTP de Symfony à statut fixe (`NotFoundHttpException` 404,
      `ConflictHttpException` 409, `TooManyRequestsHttpException` 429…)
- [x] `InstantiableHttpExceptionMapper` : sous-classe de `HttpException` dont le constructeur n'a aucun argument obligatoire,
      construite pour lire `getStatusCode()` ; échec → mapper suivant
- [x] Exception sans correspondance : diagnostic `Info`, aucune réponse (jamais de 500 inventé)
- [x] Corps par défaut : erreur JSON de Laravel `{message}` en composant partagé, remplaçable (schéma d'erreur
      d'E7) ; `ValidationException` → schéma 422 d'E7
- [x] Réponses produites avec l'origine `ResponseOrigin::Exception`, fusionnées en E7
- [x] Un test unitaire par mapper ; scénario fonctionnel « exceptions » (exception annotée, configurée, de Laravel,
      de Symfony, `HttpException` maison, inconnue)

### Polymorphisme

- [x] Union de ressources pour un même statut (`CatResource|DogResource`) → `oneOf` ; `discriminator` (et `mapping`)
      quand une propriété commune porte une valeur constante différente par membre (`type`)

### En-têtes et composants

- [x] En-têtes de la réponse échantillonnée et de l'attribut, sans les en-têtes techniques (`Cache-Control`, `Date`)
- [x] Ressource → composant de schéma nommé (`#[SchemaName]` ou nom de classe), collisions signalées
- [x] Option `use-ref` de la v0.6 conservée (composants ou schémas en ligne)

### Relations (décision du 2026-10-28)

- [x] `RelationLoader` : méthodes de relation du modèle (type de retour `Relation`), modèle lié échantillonné et
      attaché par `setRelation()` sur un niveau ; `whenLoaded()` décrit la relation, facultative

## Critères de sortie

- [x] Tests de chaque extracteur et de chaque mapper ; scénarios « ressources », « pagination », « fichiers et flux »,
      « exceptions » (`OperationResponsesTest`, sous Testbench) ; documents attendus avec l'écriture (E8)
- [x] Parité avec les réponses du document attendu v0.6 `openapi-jsonresource.json` : mêmes statuts, sauf `POST`
      (201, comme Laravel) ; ressources en composants (la v0.6 recopiait les schémas)

## Notes de réalisation

- **Laravel 13** : `RedirectResponse` implémente `Responsable` ; l'extracteur des `Responsable` laisse donc les réponses
  Symfony à leurs extracteurs.
- **Modèles renvoyés tels quels** : écrits en ligne, sans composant (le nom du modèle est souvent celui de sa
  ressource).
- **Discriminateur** : posé quand une propriété commune aux composants d'un même statut prend, dans chaque
  échantillon, une valeur texte différente (`type: cat` / `type: dog`).
- **Exceptions** : la description nomme l'exception (`Not Found (ModelNotFoundException)`), pour que la passe d'E7
  puisse citer les causes ; `Error` et `ValidationError` sont des composants partagés.
- **Flux d'événements** : `eventStream()` renvoie un `StreamedResponse`, indiscernable d'un flux binaire par son
  type : il se déclare par `#[Response(mediaType: 'text/event-stream', type: …)]`.
- `#[Response]` accepte `example` et la cible `TARGET_CLASS` (exceptions).

