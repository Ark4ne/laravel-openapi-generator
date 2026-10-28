# E8 — Écriture du document

**Objectif.** Traduire la description en objets `openapi-writer`, écrire le fichier dans la version OpenAPI
choisie, et rendre compte de sa validité.

**Dépend de :** E7.
**Architecture :** [architecture.md](architecture.md) §7.

## Livrables

### `DocumentBuilder` (`src/Generation/`)

- [ ] `Info` : `title`, `version`, `description`, `summary`, `termsOfService`, `contact`, `license`
- [ ] Serveurs et variables (corrige la v0.6, qui lisait `variables` alors que la configuration écrit `variable`),
      `Server.name` (3.2)
- [ ] `$self` (3.2) et `externalDocs` du document
- [ ] Chemins, opérations (méthode `query` et `additionalOperations` en 3.2), paramètres, corps et leurs encodages,
      réponses (`summary` en 3.2), en-têtes
- [ ] Tags : `summary`, `kind` (3.2), `externalDocs` ; `externalDocs` des opérations
- [ ] Composants, tags, sécurité, `externalDocs`, extensions `x-*` de la configuration
- [ ] Aucune décision métier : test qui vérifie qu'une description complète se traduit champ pour champ

### Version et mode

- [ ] Version OpenAPI par document (3.0.0 → 3.2.0), 3.1.2 par défaut
- [ ] Mode strict ou permissif du writer par document ; diagnostics du writer ajoutés aux `Diagnostics`
- [ ] Extensions de fournisseurs par document (`vendor_extensions` : `none`, `redoc`), passées au writer à l'écriture
      et à la validation (`VendorExtensions`)

### Sortie (`src/Output/`)

- [ ] `DocumentWriter` : disque Laravel et chemin configurables, JSON (lisible) ou YAML (`symfony/yaml`)
- [ ] Nom de fichier par document et par langue (`{document}`, `{locale}`)
- [ ] Validation par le `Validator` du writer (schéma officiel si `opis/json-schema` est installé, règles
      sémantiques sinon) ; violations dans les diagnostics, échec en `--strict`

### Langues

- [ ] Port de traduction injecté (remplace `Support\Trans`) ; un document par langue configurée

### Déterminisme

- [ ] Deux générations successives donnent des fichiers identiques octet par octet (test)

## Critères de sortie

- [ ] Tests unitaires du `DocumentBuilder` et du `DocumentWriter`
- [ ] Chaque scénario fonctionnel écrit et valide ses documents attendus en 3.0, 3.1 et 3.2
