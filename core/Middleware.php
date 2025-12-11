<?php
namespace Core;

interface Middleware
{
    /**
     * Handle an incoming request.
     * If the check fails, this method should output an error and exit().
     */
    public function handle();
}