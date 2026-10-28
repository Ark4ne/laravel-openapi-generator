# E7 — Passes du pipeline

**Objectif.** Ajouter au document ce qui ne se voit qu'à l'échelle de toutes les opérations ou des middlewares :
sécurité, réponses d'erreur, tags et groupes, identifiants uniques, composants partagés.

**Dépend de :** E4, E6.
**Architecture :** [architecture.md](architecture.md) §6.

## Livrables

### Chaîne (`src/Generation/Passes/`)

- [ ] `Passes::default()` et test de l'ordre

### Sécurité

- [ ] `MiddlewareSecurityPass` et ses `MiddlewareMapper`, configurables par middleware (remplacent `Transformers/`) :
    - [ ] `auth`, `auth:sanctum` (bearer ; cookie + `X-XSRF-TOKEN` pour une SPA), `auth:api` (Passport, OAuth2),
          `auth.basic`
    - [ ] `ValidateCsrfToken` (nom Laravel 11+ de `VerifyCsrfToken`)
    - [ ] `signed` (paramètres `signature` et `expires`)
- [ ] Scopes : middlewares `scopes:` / `scope:` de Passport (descriptions de `Passport::tokensCan()`)
- [ ] Abilities Sanctum (`abilities:` / `ability:`) → scopes du bearer en 3.1+, mentionnées dans la description avant
- [ ] Schémas de sécurité de la configuration (`security_schemes`) : flux OAuth2 et URLs, `openIdConnect`, champs
      3.2 (`deviceAuthorization`, `oauth2MetadataUrl`, `deprecated`), fusionnés avec les schémas inférés
- [ ] Schémas de sécurité rassemblés dans `components.securitySchemes`, sécurité globale en option

### Réponses d'erreur

- [ ] 422 avec le schéma d'erreur de validation de Laravel (`message`, `errors`) quand des règles existent
- [ ] 401 sur les routes authentifiées, 403 sur `can:` / `Authorize` ou `authorize()` redéfinie
- [ ] 404 sur les liaisons de modèles, 429 sur `throttle` (en-têtes `X-RateLimit-*`, `Retry-After`)
- [ ] `ThrownExceptionResponsePass` (avant les autres passes d'erreur) : réponses issues de `@throws` (E6) ; un
      statut donné par plusieurs sources (exceptions, middlewares, liaisons) → une réponse dont la description cite
      les causes, schémas différents réunis en `oneOf` ; `#[Response]` de l'action prioritaire
- [ ] Réponses partagées dans `components.responses` ; schéma d'erreur remplaçable (JSON:API en E9)

### Organisation

- [ ] `TagPass` : tags issus des stratégies, descriptions, ordre stable
- [ ] `TagGroupPass` : `TagDescription::parent()` depuis la stratégie de groupe. Avant 3.2, le writer écrit
      `x-tagGroups` avec `vendor_extensions: redoc` (E8), sinon rejette ou retire `parent` ; aucune conversion ici
- [ ] `OperationIdPass` : identifiants uniques et déterministes, collisions suffixées et signalées

### Composants

- [ ] `ComponentExtractionPass` : ressources et enums en `components.schemas`, remplacement par `$ref`,
      dédoublonnage des schémas identiques, mode en ligne
- [ ] Enums en composants (reprend `EnumToRef`), description des cas

### Finalisation

- [ ] Serveurs globaux et par document, serveurs d'opération pour les routes à domaine
- [ ] `PathParametersPass` : paramètres de chemin communs à toutes les méthodes d'un chemin remontés au Path Item
- [ ] Nettoyage : collections vides retirées, ordre des chemins stable

## Critères de sortie

- [ ] Un test unitaire par passe et par `MiddlewareMapper`
- [ ] Scénario fonctionnel « sécurité et erreurs » avec documents attendus en 3.0, 3.1 et 3.2
