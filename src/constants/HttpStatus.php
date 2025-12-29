<?php

declare(strict_types=1);

/**
 * HTTP Status Code Constants
 */
final class HttpStatus
{
    // Success
    public const OK = 200;
    public const CREATED = 201;
    public const NO_CONTENT = 204;
    
    // Client Errors
    public const BAD_REQUEST = 400;
    public const UNAUTHORIZED = 401;
    public const FORBIDDEN = 403;
    public const NOT_FOUND = 404;
    public const METHOD_NOT_ALLOWED = 405;
    public const CONFLICT = 409;
    public const UNPROCESSABLE_ENTITY = 422;
    
    // Server Errors
    public const INTERNAL_SERVER_ERROR = 500;
    public const BAD_GATEWAY = 502;
    public const SERVICE_UNAVAILABLE = 503;
}
