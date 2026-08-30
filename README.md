# hush-hush-php

[![ci](https://github.com/alrayyes/hush-hush-php/actions/workflows/ci.yml/badge.svg)](https://github.com/alrayyes/hush-hush-php/actions/workflows/ci.yml)
[![Codecov](https://codecov.io/gh/alrayyes/hush-hush-php/graph/badge.svg)](https://codecov.io/gh/alrayyes/hush-hush-php)
[![Packagist](https://img.shields.io/packagist/v/alrayyes/hush-hush)](https://packagist.org/packages/alrayyes/hush-hush)
[![release](https://img.shields.io/github/v/release/alrayyes/hush-hush-php)](https://github.com/alrayyes/hush-hush-php/releases)
[![license](https://img.shields.io/github/license/alrayyes/hush-hush-php)](LICENSE)

The official PHP SDK for [hush-hush](https://github.com/alrayyes/hush-hush),
generated from its OpenAPI spec and kept in sync with it automatically.

## Install

```sh
composer require alrayyes/hush-hush
```

Requires PHP 8.2 or newer.

## Quickstart

```php
use HushHush\Client;

$client = new Client('https://hush-hush.example.com', 'your-api-key'); // or set HUSH_HUSH_API_KEY

// Create is a write operation — it needs the credential above.
$client->createObject('my-first-secret', 'already-sealed-ciphertext');

// Get needs no credential — hush-hush's confidentiality boundary is
// "who holds a matching private key," not who's calling this endpoint.
$value = $client->getObject('my-first-secret');
echo 'got ' . strlen($value) . " bytes of sealed ciphertext\n";

// The audit log records every read and write; querying it needs no
// credential either, and returns the full matching result set (there's
// no pagination on this endpoint).
foreach ($client->queryAuditLog() as $entry) {
    echo $entry->getAction() . ' ' . $entry->getObjectId() . ' ' . $entry->getTimestamp()->format(DATE_ATOM) . "\n";
}
```

The API key is only required for write operations (create/update/delete);
reads (get, used-by, audit-log query) work without one. A per-call `$caller`
argument, accepted by create/get/update/delete, is optional. See the
[full API reference](https://alrayyes.github.io/hush-hush-php/) for
everything else.

## Versioning

This SDK's version tracks hush-hush's OpenAPI spec, not this repo's own
commit history — see [CONTRIBUTING.md](CONTRIBUTING.md) for how a spec
change becomes a release.

## License

[MIT](LICENSE)
