<?php declare(strict_types=1);
namespace SignVault\Exceptions;
/** Thrown on 401 / 403 — invalid or missing API key, insufficient permissions. */
class AuthException extends SignVaultException {}
