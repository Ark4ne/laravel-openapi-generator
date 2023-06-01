<?php

$config = include __DIR__ . '/../../config/openapi.php';

$config['output-dir'] = __DIR__ . '/dist';
$config['versions']['v1']['routes'] = ['*'];
$config['versions']['v1']['tag-by']['regex'] = '/app\\\\Http\\\\(\w+)Controller/';
$config['versions']['v1']['group-by']['regex'] = '/app\\\\Http\\\\(\w+)/';
$config['versions']['v1']['middlewares']['auth:sanctum'] = [\Ark4ne\OpenApi\Transformers\Middlewares\ApplyBearerTokenSecurity::class];

return $config;
