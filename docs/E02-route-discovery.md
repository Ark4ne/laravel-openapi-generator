# E2 — Découverte des routes

**Objectif.** Lire le routeur une seule fois et produire, pour chaque route retenue, un `RouteDefinition` complet
et un chemin OpenAPI correct (paramètres, contraintes, domaines).

**Dépend de :** E1.
**Architecture :** [architecture.md](architecture.md) §1 (Discovery).

## Livrables

### Socle d'E1 nécessaire à E2

- [x] `Description::copy()` (reconstruit la classe finale depuis ses propriétés promues), `Diagnostics`,
      `Diagnostic::__toString()` (`[warning] discovery GET /users: …`), `DiagnosticLevel::isAtLeast()`
- [x] `InvalidConfigurationException::forKey()` : clé, valeur attendue, valeur reçue
- [x] `RouteFilterConfiguration::fromArray()` et `RouteMasks` : clés inconnues et types refusés avec leur clé

### Collecte (`src/Discovery/`)

- [x] `RouteCollector` : routes du routeur, dans l'ordre d'enregistrement
- [x] `RouteDefinition` : **une par méthode et par chemin** ; méthode, chemin OpenAPI, nom, domaine, action,
      paramètres de chemin et de domaine typés, middlewares déclarés (groupes dépliés, alias gardés) et résolus en
      classes (`MiddlewareResolver`), `withTrashed`, `scopeBindings`
- [x] Actions : `Controller@method`, contrôleur invocable, `Route::controller()`, `resource()` / `apiResource()`,
      closures (documentées, contrairement à la v0.6)
- [x] Routes exclues d'office : `redirect()`, `permanentRedirect()`, `view()`, `fallback()`, routes internes de
      Laravel et des paquets courants (`up`, `sanctum/csrf-cookie`, `storage/*`, `telescope/*`…), liste configurable
      (`routes.internal`)

### Filtres

- [x] Inclusion et exclusion par masque sur l'URI, par nom de route, par domaine, par middleware (`RouteFilter`)
- [x] Méthodes ignorées (`HEAD` par défaut) ; `any()` et `match()` dépliés en une opération par méthode
- [x] Méthode `QUERY` : `HttpMethod::Query` (3.2) ; avant 3.2, retrait avec avertissement en mode permissif,
      conservée en mode strict pour que le writer la rejette à l'écriture
- [x] Méthodes non standard (`Route::match(['PURGE'], …)`) : méthode en majuscules, pour `additionalOperations`
      (3.2) ; avant 3.2, même règle que `QUERY`
- [x] `#[Exclude]` sur un contrôleur, une méthode ou une closure

### Chemins et paramètres de chemin

- [x] Paramètre optionnel `{id?}` : un chemin sans le paramètre, puis un chemin avec (un de plus à chaque
      paramètre optionnel)
- [x] Clé personnalisée `{post:slug}` écrite `{post}`
- [x] Contraintes : `whereNumber` → `integer`, `whereAlpha` / `whereAlphaNumeric` → `pattern`, `whereUuid` →
      `format: uuid`, `whereUlid` → `pattern`, `whereIn` → `enum`, expression libre → `pattern` ancré
- [x] Liaison implicite d'un modèle : type de la clé (`getKeyType()`, `getRouteKeyName()`, cast de la colonne de
      la clé personnalisée, `HasUuids` / `HasUlids`) ; `boundTo` = le modèle
- [x] Liaison d'une enum adossée (`BackedEnum`) : `enum` des valeurs, `boundTo` = l'enum (le `$ref` vient de la
      passe des composants, E7)
- [x] Paramètres de domaine (`{account}.example.com`) : lus et typés à part des paramètres de chemin ; les
      variables de serveur de l'opération sont écrites en E7

### Déterminisme

- [x] Ordre de sortie stable : ordre d'enregistrement par défaut, tri par chemin en option (`routes.order: path`)

## Critères de sortie

- [x] Tests unitaires de `RouteCollector`, des filtres et des résolveurs ; scénario fonctionnel « routes »
      (définitions découvertes) — ses documents attendus viennent avec l'écriture (E8)
- [x] Toutes les routes de l'application de test v0.6 sont découvertes à l'identique ou mieux (écarts justifiés)

## Notes de réalisation

- **Masques** : `include` et `exclude` acceptent une liste de masques d'URI (`['api/*']`, forme de la v0.6) ou une
  map `uri` / `name` / `domain` / `middleware`. Une route correspond dès qu'un masque, de n'importe quel type, la
  reconnaît. `include` vide garde toutes les routes. Un masque de middleware se compare au nom déclaré
  (`auth:sanctum`) et à la classe résolue.
- **Méthodes avant 3.2** : en mode strict, `QUERY` et les méthodes non standard restent dans la description et le
  writer lève son `UnsupportedFeatureException` à l'écriture, comme pour toute fonctionnalité absente de la version.
  En mode permissif, la découverte les retire avec un avertissement rattaché à la route. Depuis une version mineure de
  Laravel 13 (absente de 13.0), `any()` inclut `QUERY` : une route `any()` donne alors un avertissement en 3.0 / 3.1
  permissif.
- **Parité v0.6** : les 18 opérations de `openapi-jsonresource.json` sont découvertes dans l'ordre
  d'enregistrement. Écarts : les paramètres liés à un modèle à clé entière ou déclarés `int` sont des `integer`
  (la v0.6 écrivait toujours `string`) ; les closures sont documentées.
- **Comportements de Laravel pris en compte** : `signatureParameters()` renvoie une liste vide, sans erreur, pour
  une méthode de contrôleur inexistante (la découverte le détecte et émet l'avertissement) ; Laravel construit le
  contrôleur pour lire ses middlewares quand il a `getMiddleware()` : s'il échoue, seuls les middlewares de la route
  sont lus ; les closures ne sont pas acceptées comme middlewares de route.
- `#[Exclude]` accepte désormais la cible `TARGET_FUNCTION`, pour les closures.
- `OperationDescription::$method` et `OperationContext::$method` sont typés `HttpMethod|string` : une chaîne est une
  méthode non standard, en majuscules.
