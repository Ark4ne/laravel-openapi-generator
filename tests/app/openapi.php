<?php

$config = include __DIR__ . '/../../config/openapi.php';

$config['output-dir'] = __DIR__ . '/dist';
$config['versions']['v1']['routes'] = ['*'];
$config['versions']['v1']['tag-by']['regex'] = '/app\\\\Http\\\\(\w+)Controller/';
$config['versions']['v1']['group-by']['regex'] = '/app\\\\Http\\\\(\w+)/';

return $config;
