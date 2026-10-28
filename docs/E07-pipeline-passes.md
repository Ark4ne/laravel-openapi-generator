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
- [ ] Schémas de sécurité rassemblés dans `components.securitySchemes`, sécurité globale en option

### Réponses d'erreur

- [ ] 422 avec le schéma d'erreur de validation de Laravel (`message`, `errors`) quand des règles existent
- [ ] 401 sur les routes authentifiées, 403 sur `can:` / `Authorize` ou `authorize()` redéfinie
- [ ] 404 sur les liaisons de modèles, 429 sur `throttle` (en-têtes `X-RateLimit-*`, `Retry-After`)
- [ ] Réponses partagées dans `components.responses` ; schéma d'erreur remplaçable (JSON:API en E9)

### Organisation

- [ ] `TagPass` : tags issus des stratégies, descriptions, ordre stable
- [ ] `TagGroupPass` : `Tag.parent` et `kind` en 3.2, extension `x-tagGroups` avant (comme la v0.6)
- [ ] `OperationIdPass` : identifiants uniques et déterministes, collisions suffixées et signalées

### Composants

- [ ] `ComponentExtractionPass` : ressources et enums en `components.schemas`, remplacement par `$ref`,
      dédoublonnage des schémas identiques, mode en ligne
- [ ] Enums en composants (reprend `EnumToRef`), description des cas

### Finalisation

- [ ] Serveurs globaux et par document, serveurs d'opération pour les routes à domaine
- [ ] Nettoyage : collections vides retirées, ordre des chemins stable

## Critères de sortie

- [ ] Un test unitaire par passe et par `MiddlewareMapper`
- [ ] Scénario fonctionnel « sécurité et erreurs » avec documents attendus en 3.0, 3.1 et 3.2
