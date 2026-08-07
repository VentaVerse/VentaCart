<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by SafeUrlFetcher when a caller-supplied URL fails SSRF validation
 * (disallowed scheme, unresolvable host, or a host/redirect target that
 * resolves to a loopback/private/link-local/reserved IP address).
 *
 * Callers should catch this alongside their existing download-failure
 * handling — it is intentionally a plain RuntimeException so it slots into
 * any `catch (\Exception $e)` / `catch (\Throwable $e)` block already
 * guarding an outbound fetch.
 */
class UnsafeUrlException extends RuntimeException {}
