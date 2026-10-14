<?php declare(strict_types=1);
namespace Signori\Exceptions;
/** Thrown when the client is missing required configuration (API key, base URL). */
class ConfigurationException extends SignoriException {}
