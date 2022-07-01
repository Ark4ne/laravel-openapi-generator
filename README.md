OpenApi - Laravel Documentation Generator
=========================================

A OpenApi documentation generator for Laravel.

![example branch parameter](https://github.com/Ark4ne/laravel-openapi-generator/actions/workflows/php.yml/badge.svg)
[![codecov](https://codecov.io/gh/Ark4ne/laravel-openapi-generator/branch/master/graph/badge.svg?token=F7XBLAGTDP)](https://codecov.io/gh/Ark4ne/laravel-openapi-generator)

# Installation
```shell
composer require ark4ne/laravel-openapi-generator
```

# Usage
```shell
php artisan openapi:generate
```

# Config
...

## Versions
The "Versions" section allows you to specify different configurations for each version of your API.

### `output-file`
Output-file name.

### `title`
Document title.

### `description`
Document description.

### `routes`
Define which routes will be processed. The pattern must be a shell mask.

```
'routes' => [
    'api/*'
],
```

### `groupBy`
Document description.
Used to group API routes.

#### `by`
Define a group by according to :
- controller : The route controller class
- uri : The route uri
- name : The route name

#### `regex`
Define a regex that will retrieve the name of the group.

ex: 
```
'groupBy' => [
    'by' => 'controller',
    'regex' => '/^App\\\\Http\\\\(\w+)/'
],
```

With this, routes will be grouped by controller class name.

### `ignore-verbs`
HTTP verbs to ignore.

## Parses
Define all parser used for documentate a specific class.  

For each object, only one parser will be used.  
For instance :
```
class MyResourceCollection extends ResourceCollection {}
```
`ResourceCollectionParser` and `JsonResourceParser` are eligible, because
`MyResourceCollection` extends from `ResourceCollection` which extends from `JsonResource`.

The order of the parsers will define which parser we will use: 1st eligible => parser used.

For `MyResourceCollection` we will therefore use `ResourceCollection`.

### `requests`
Requests parsers.

### `rules`
Request rules parsers.

### `responses`
Responses parsers.

## Format 
### `date`
Defines how date formats will be understood.

## Connections
### `use-transaction`
Defines whether to use transactions on different database connections.

Using transaction will create and save models via factories. The saved
models will be deleted at the end of the generation via a rollback.

Without transactions, models will be created but not saved, which can
lead to errors during generation.
