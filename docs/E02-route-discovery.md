# E2 — Découverte des routes

**Objectif.** Lire le routeur une seule fois et produire, pour chaque route retenue, un `RouteDefinition` complet
et un chemin OpenAPI correct (paramètres, contraintes, domaines).

**Dépend de :** E1.
**Architecture :** [architecture.md](architecture.md) §1 (Discovery).

## Livrables

### Collecte (`src/Discovery/`)

- [ ] `RouteCollector` : routes du routeur, dans l'ordre d'enregistrement
- [ ] `RouteDefinition` : URI, méthodes, nom, domaine, action, middlewares résolus (groupes compris), contraintes
      `where`, valeurs par défaut, liaisons de modèles, `withTrashed`, `scopeBindings`
- [ ] Actions : `Controller@method`, contrôleur invocable, `Route::controller()`, `resource()` / `apiResource()`,
      closures (documentées, contrairement à la v0.6)
- [ ] Routes exclues d'office : `redirect()`, `view()`, `fallback()`, routes internes des paquets
      (`sanctum/csrf-cookie`, `up`…), liste configurable

### Filtres

- [ ] Inclusion et exclusion par masque sur l'URI, par nom de route, par domaine, par middleware
- [ ] Méthodes ignorées (`HEAD` par défaut) ; `any()` et `match()` dépliés en une opération par méthode
- [ ] Méthode `QUERY` : opération `query` (3.2) ; rejet strict / retrait permissif avant
- [ ] Méthodes non standard (`Route::match(['PURGE'], …)`) : `additionalOperations` (3.2) ; diagnostic et retrait avant
- [ ] `#[Exclude]` sur un contrôleur ou une méthode

### Chemins et paramètres de chemin

- [ ] Paramètre optionnel `{id?}` : un chemin avec et un chemin sans le paramètre
- [ ] Clé personnalisée `{post:slug}` écrite `{post}`
- [ ] Contraintes : `whereNumber` → `integer`, `whereAlpha` / `whereAlphaNumeric` → `pattern`, `whereUuid` →
      `format: uuid`, `whereUlid` → `pattern`, `whereIn` → `enum`, expression libre → `pattern`
- [ ] Liaison implicite d'un modèle : type de la clé (`getKeyType()`, `getRouteKeyName()`, cast de la colonne)
- [ ] Liaison d'une enum adossée (`BackedEnum`) : `enum` des valeurs, composant `$ref`
- [ ] Paramètres de domaine (`{account}.example.com`) : variables de serveur au niveau de l'opération

### Déterminisme

- [ ] Ordre de sortie stable : ordre d'enregistrement par défaut, tri par chemin en option

## Critères de sortie

- [ ] Tests unitaires de `RouteCollector` et des filtres ; scénario fonctionnel « routes » avec documents attendus
- [ ] Toutes les routes de l'application de test v0.6 sont découvertes à l'identique ou mieux (écarts justifiés)
