<?php declare(strict_types=1);
namespace Signori\Exceptions;
/** Thrown on 401 / 403 — invalid or missing API key, insufficient permissions. */
class AuthException extends SignoriException {}
