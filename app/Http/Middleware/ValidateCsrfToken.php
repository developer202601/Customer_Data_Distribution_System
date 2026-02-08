<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken as Middleware;

class ValidateCsrfToken extends Middleware
{
    /**
     * Prevent Laravel from adding the non-HttpOnly XSRF-TOKEN cookie.
     */
    protected $addHttpCookie = false;
}
