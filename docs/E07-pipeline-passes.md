# E7 — Passes du pipeline

**Objectif.** Ajouter au document ce qui ne se voit qu'à l'échelle de toutes les opérations ou des middlewares :
sécurité, réponses d'erreur, tags et groupes, identifiants uniques, composants partagés.

**Dépend de :** E4, E6.
**Architecture :** [architecture.md](architecture.md) §6.

## Conception

- Les passes ne voient que la description (`DocumentDescription`), jamais la route : ce qui dépend de la route ou de
  l'action se décide à l'extraction. Le groupe d'un tag (`GroupStrategy`) est posé par l'extracteur de nommage, les
  serveurs d'une route à domaine par `OperationContext::describe()`, `#[Security]` par l'extracteur d'attributs.
- **Middlewares** : chaque middleware déclaré (`auth:sanctum`, ou sa classe) est lu en `Middleware` (nom, arguments ;
  les classes de Laravel, Sanctum et Passport ramenées à leur alias) et passé aux `MiddlewareMapper` qui le
  reconnaissent ; `middlewares` de la configuration en ajoute ou en remplace, par middleware.
- **Réponses d'erreur** : partagées dans `components.responses` (`Unauthenticated`, `Forbidden`, `NotFound`,
  `PageExpired`, `ValidationFailed`, `TooManyRequests`), ajoutées seulement quand l'opération n'a pas déjà ce statut ;
  leurs corps viennent d'un `ErrorBodies` remplaçable (`LaravelErrorBodies` : composants `Error`, `ValidationError`).
- **Ordre** (`Passes::default()`, testé) : réponses des exceptions → sécurité → réponses d'erreur → tags et groupes →
  `operationId` → composants → paramètres de chemin → nettoyage.

## Livrables

### Chaîne (`src/Generation/Passes/`)

- [x] `Passes::default()` et test de l'ordre

### Sécurité

- [x] `MiddlewareSecurityPass` et ses `MiddlewareMapper`, configurables par middleware (remplacent `Transformers/`) :
    - [x] `auth`, `auth:sanctum` (bearer ; cookie + `X-XSRF-TOKEN` pour une SPA), `auth:api` (Passport, OAuth2),
          `auth.basic`
    - [x] `ValidateCsrfToken` (nom Laravel 11+ de `VerifyCsrfToken`)
    - [x] `signed` (paramètres `signature` et `expires`)
- [x] Scopes : middlewares `scopes:` / `scope:` de Passport, déclarés dans les flux du schéma `passport` (nom pour
      description : Passport n'est pas une dépendance ; `security_schemes` donne les vraies descriptions)
- [x] Abilities Sanctum (`abilities:` / `ability:`) → scopes du bearer en 3.1+, mentionnées dans la description avant
- [x] Schémas de sécurité de la configuration (`security_schemes`) : flux OAuth2 et URLs, `openIdConnect`, champs
      3.2 (`deviceAuthorization`, `oauth2MetadataUrl`, `deprecated`), fusionnés avec les schémas inférés
- [x] Schémas de sécurité rassemblés dans `components.securitySchemes`, sécurité globale en option (clé `security`
      du document)
- [x] `#[Security]` sur l'action ou le contrôleur (lu à l'extraction) : chaque attribut est une alternative

### Réponses d'erreur

- [x] 422 avec le schéma d'erreur de validation de Laravel (`message`, `errors`) quand des règles existent
- [x] 401 sur les routes authentifiées, 403 sur `can:` / `Authorize` ou `authorize()` redéfinie
- [x] 404 sur les liaisons de modèles, 429 sur `throttle` (en-têtes `X-RateLimit-*`, `Retry-After`)
- [x] `ThrownExceptionResponsePass` (avant les autres passes d'erreur) : réponses issues de `@throws` (E6) ; un
      statut donné par plusieurs sources (exceptions, middlewares, liaisons) → une réponse dont la description cite
      les causes, schémas différents réunis en `oneOf` ; `#[Response]` de l'action prioritaire
- [x] Réponses partagées dans `components.responses` ; schéma d'erreur remplaçable (JSON:API en E9)

### Organisation

- [x] `TagPass` : tags issus des stratégies, descriptions, ordre stable
- [x] `TagGroupPass` : `TagDescription::parent()` posé à l'extraction par la stratégie de groupe ; les tags parents
      déclarés. Avant 3.2, le writer écrit `x-tagGroups` avec `vendor_extensions: redoc` (E8), sinon rejette ou retire
      `parent` ; aucune conversion ici
- [x] `OperationIdPass` : identifiants uniques et déterministes, collisions suffixées et signalées

### Composants

- [x] `ComponentExtractionPass` : composants du contexte (ressources, erreurs) et enums des paramètres de chemin en
      `components.schemas`, remplacement par `$ref`, dédoublonnage des schémas identiques, mode en ligne
- [x] Enums en composants (reprend `EnumToRef`), description des cas

### Finalisation

- [x] Serveurs d'opération pour les routes à domaine (`https://{account}.example.com` et ses variables, posés à
      l'extraction) ; les serveurs du document viennent de la configuration à l'écriture (E8)
- [x] `PathParametersPass` : paramètres de chemin communs à toutes les méthodes d'un chemin remontés au Path Item
- [x] Nettoyage : tags d'opération en double retirés, une réponse `default` pour une opération qui n'en a aucune
      (obligatoire en 3.0), ordre des chemins stable

## Critères de sortie

- [x] Un test unitaire par passe et par `MiddlewareMapper`
- [x] Scénario fonctionnel « sécurité et erreurs » (des routes aux passes) ; documents attendus avec l'écriture (E8)

## Notes de réalisation

- **`MiddlewareMapper`** reçoit un `Middleware` (alias et arguments) et déclare les alias qu'il traite (`handles()`),
  au lieu d'une chaîne à tester.
- **Sanctum** : deux alternatives, un jeton (`sanctum`) ou le cookie de session d'une SPA avec son en-tête XSRF
  (`session` + `xsrf`). Le garde `api` est lu comme Passport ; `auth` sans garde, un jeton `bearer`.
- **Sécurité déclarée** : `#[Security]` l'emporte sur celle des middlewares ; une alternative vide rend
  l'authentification facultative (pas de 401).
- **Réponses partagées** : ajoutées dans l'ordre des passes ; une opération qui a déjà le statut le garde.
- **Composants** : le dédoublonnage compare les schémas écrits ; le mode en ligne remplace chaque référence par le
  composant, lui-même mis en ligne.
- **Paramètres de chemin** : seuls ceux qu'ont toutes les opérations d'un chemin, à l'identique, montent au Path Item
  (`DocumentDescription::$pathParameters`).
- **Contrat** : `OperationDescription::$servers` devient une liste de `ServerDescription` (URL et variables) ;
  `DocumentConfiguration::$security` (clé `security` du document) et `use_references`, `security_schemes` sont lus.

