<?php

declare(strict_types=1);

namespace Torii\Backend\Internal;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Tiny in-process PSR-6 cache pool used as the default JWKS cache backing
 * store. We deliberately don't pull in a heavy cache library — this is the
 * "process-local, fine for serverless cold-starts" default. Apps that want
 * Redis/memcached pass in their own PSR-6 pool to {@see verify_token()}.
 *
 * Not thread-safe; PHP request-scoped use only.
 *
 * @internal
 */
final class ArrayCachePool implements CacheItemPoolInterface
{
    /** @var array<string, array{value: mixed, expiresAt: ?DateTimeInterface}> */
    private array $items = [];

    /** @var array<string, CacheItem> */
    private array $deferred = [];

    public function getItem(string $key): CacheItemInterface
    {
        if (!isset($this->items[$key])) {
            return new CacheItem($key, false, null);
        }
        $entry = $this->items[$key];
        if ($entry['expiresAt'] !== null && $entry['expiresAt'] < new DateTimeImmutable()) {
            unset($this->items[$key]);
            return new CacheItem($key, false, null);
        }
        return new CacheItem($key, true, $entry['value']);
    }

    /** @param list<string> $keys */
    public function getItems(array $keys = []): iterable
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->getItem($key);
        }
        return $out;
    }

    public function hasItem(string $key): bool
    {
        return $this->getItem($key)->isHit();
    }

    public function clear(): bool
    {
        $this->items = [];
        $this->deferred = [];
        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->items[$key]);
        return true;
    }

    /** @param list<string> $keys */
    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->items[$key]);
        }
        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        if (!$item instanceof CacheItem) {
            return false;
        }
        $this->items[$item->getKey()] = [
            'value' => $item->get(),
            'expiresAt' => $item->getExpiresAt(),
        ];
        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        if (!$item instanceof CacheItem) {
            return false;
        }
        $this->deferred[$item->getKey()] = $item;
        return true;
    }

    public function commit(): bool
    {
        foreach ($this->deferred as $item) {
            $this->save($item);
        }
        $this->deferred = [];
        return true;
    }
}

/**
 * Minimal CacheItem implementation paired with {@see ArrayCachePool}.
 *
 * @internal
 */
final class CacheItem implements CacheItemInterface
{
    private ?DateTimeInterface $expiresAt = null;

    public function __construct(
        private readonly string $key,
        private bool $isHit,
        private mixed $value,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->isHit ? $this->value : null;
    }

    public function isHit(): bool
    {
        return $this->isHit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->isHit = true;
        return $this;
    }

    public function expiresAt(?DateTimeInterface $expiration): static
    {
        $this->expiresAt = $expiration;
        return $this;
    }

    public function expiresAfter(DateInterval|int|null $time): static
    {
        if ($time === null) {
            $this->expiresAt = null;
        } elseif ($time instanceof DateInterval) {
            $this->expiresAt = (new DateTimeImmutable())->add($time);
        } else {
            $this->expiresAt = (new DateTimeImmutable())->add(new DateInterval('PT' . $time . 'S'));
        }
        return $this;
    }

    public function getExpiresAt(): ?DateTimeInterface
    {
        return $this->expiresAt;
    }
}
