<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Requests;

/**
 * Implemented by a request class to complete or replace what its rules describe: headers, cookies, query, bodies
 * of other media types, writer schemas.
 *
 * ```php
 * public function describe(RequestDescriber $request): RequestDescriber
 * {
 *     return $request->header('X-Tenant', 'required|uuid')->query(['include' => 'string']);
 * }
 * ```
 */
interface DescribesRequest
{
    public function describe(RequestDescriber $request): RequestDescriber;
}
