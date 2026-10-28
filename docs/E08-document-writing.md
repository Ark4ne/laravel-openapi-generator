# E8 — Écriture du document

**Objectif.** Traduire la description en objets `openapi-writer`, écrire le fichier dans la version OpenAPI
choisie, et rendre compte de sa validité.

**Dépend de :** E7.
**Architecture :** [architecture.md](architecture.md) §7.

## Conception

```
Generator : routes (E2) → contexte d'opération (E3) → métadonnées, requête (E4), réponses (E6) → DocumentDescription
          → passes (E7) → DocumentBuilder → OpenApi ── DocumentWriter (Serializer du writer, disque) / DocumentValidator
```

- **`Generator`** (`src/Generation/`) assemble la chaîne pour un document et une langue, dans une session
  d'échantillonnage ; le fournisseur de services (E10) le construit depuis le conteneur.
- **`DocumentBuilder`** traduit la description champ pour champ ; la version, le mode strict et les extensions de
  fournisseurs ne servent qu'à l'écriture et à la validation.
- **`DocumentWriter`** (`src/Output/`) écrit par le `Serializer` du writer : ses diagnostics rejoignent les `Diagnostics`
  (`converted` → info, `dropped` → avertissement) ; un échec strict (`UnsupportedFeatureException`) devient une
  `GenerationFailedException` qui porte les diagnostics.
- **Documents attendus** : chaque scénario fonctionnel génère son document en 3.0.4, 3.1.2 et 3.2.0, le valide
  (schéma officiel par `opis/json-schema`, zéro erreur) et le compare à `tests/Feature/<Scénario>/expected/<ligne>.json` ;
  `composer fixtures:update` les réécrit.

## Livrables

### `DocumentBuilder` (`src/Generation/`)

- [x] `Info` : `title`, `version`, `description`, `summary`, `termsOfService`, `contact`, `license`
- [x] Serveurs et variables du document (configuration globale et par document ; corrige la v0.6, qui lisait
      `variables` alors que la configuration écrit `variable`), `Server.name` (3.2), serveurs d'opération
      (`ServerDescription`)
- [x] Paramètres partagés d'un chemin (`DocumentDescription::$pathParameters`) écrits sur son Path Item
- [x] `$self` (3.2) et `externalDocs` du document
- [x] Paramètre `querystring` (3.2) écrit en `content` `application/x-www-form-urlencoded`, avec le schéma de la
      description
- [x] Chemins, opérations (méthode `query` et `additionalOperations` en 3.2), paramètres, corps et leurs encodages,
      réponses (`summary` en 3.2), en-têtes
- [x] Tags : `summary`, `kind` (3.2), `externalDocs` ; `externalDocs` des opérations
- [x] Composants, tags, sécurité, `externalDocs`, extensions `x-*` de la configuration
- [x] Aucune décision métier : test qui vérifie qu'une description complète se traduit champ pour champ

### Version et mode

- [x] Version OpenAPI par document (3.0.0 → 3.2.0), 3.1.2 par défaut
- [x] Mode strict ou permissif du writer par document ; diagnostics du writer ajoutés aux `Diagnostics`
- [x] Extensions de fournisseurs par document (`vendor_extensions` : `none`, `redoc`), passées au writer à l'écriture
      et à la validation (`VendorExtensions`)

### Sortie (`src/Output/`)

- [x] `DocumentWriter` : disque Laravel et chemin configurables, JSON (lisible) ou YAML (`symfony/yaml`)
- [x] Nom de fichier par document et par langue (`{document}`, `{locale}`)
- [x] Validation par le `Validator` du writer (schéma officiel si `opis/json-schema` est installé, règles
      sémantiques sinon) ; violations dans les diagnostics, échec en `--strict`

### Chaîne et configuration

- [x] `Generator` : un document par document configuré et par langue (`languages`), dans une session d'échantillonnage
- [x] Configuration lue pour l'écriture : `openapi`, `strict`, `vendor_extensions`, `info`, `self`, `external_docs`,
      `servers` (globaux et du document), `output` (global fusionné avec celui du document), `extensions`, `languages`

### Langues

- [x] Port de traduction injecté (contrat `Translator`, remplace `Support\Trans`) ; un document par langue configurée

### Déterminisme

- [x] Deux générations successives donnent des fichiers identiques octet par octet (test)

## Critères de sortie

- [x] Tests unitaires du `DocumentBuilder` et du `DocumentWriter`
- [x] Chaque scénario fonctionnel écrit et valide ses documents attendus en 3.0, 3.1 et 3.2, y compris les scénarios
      « routes » d'E2 (`tests/Feature/Routes/`) et « métadonnées » d'E3 (`tests/Feature/Metadata/`)

## Notes de réalisation

- **Collections vides** : un setter du writer appelé sans argument écrit une collection vide ; `DocumentBuilder`
  n'appelle un setter de collection que lorsqu'elle a des éléments.
- **Méthodes** : `GET`… et `QUERY` (3.2) sont des opérations fixes ; une autre méthode (`PURGE`) rejoint
  `additionalOperations`. Les réponses partagées sont écrites en `$ref` (`Response::ref()`).
- **Validation** : le `Validator` du writer s'arrête aux fonctionnalités que la version ne sait pas écrire, déjà
  signalées par l'écriture ; `DocumentValidator` valide donc le document tel qu'il est écrit en permissif (schéma
  officiel puis règles sémantiques), pour la version et les extensions de fournisseurs du document.
- **Chemins équivalents** : la validation des documents attendus a révélé `/user/{id}` à côté de `/user/{user}`,
  interdit par OpenAPI ; `EquivalentPathsPass` (E7) les réunit sous le premier chemin.
- **Sortie** : JSON lisible (barres obliques et Unicode non échappés, saut de ligne final) ou YAML ; la langue
  s'insère avant l'extension quand le nom ne porte pas `{locale}`.
- **Échec strict** : `GenerationFailedException::fromDiagnostics()` liste les erreurs et garde les diagnostics.
