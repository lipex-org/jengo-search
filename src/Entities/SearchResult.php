<?php

declare(strict_types=1);

namespace Jengo\Search\Entities;

use ArrayAccess;
use JsonSerializable;

class SearchResult implements ArrayAccess, JsonSerializable
{
    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $highlights
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public array $document = [],
        public array $highlights = [],
        public array $metadata = []
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->document[$key] ?? $default;
    }

    public function getHighlight(string $key, ?string $fallback = null): ?string
    {
        if (isset($this->highlights[$key])) {
            return is_array($this->highlights[$key]) ? ($this->highlights[$key]['snippet'] ?? $this->highlights[$key]['value'] ?? null) : (string) $this->highlights[$key];
        }

        if (isset($this->document['_formatted'][$key])) {
            return (string) $this->document['_formatted'][$key];
        }

        if (isset($this->document['_highlights'][$key])) {
            $h = $this->document['_highlights'][$key];
            return is_array($h) ? ($h['snippet'] ?? $h['value'] ?? null) : (string) $h;
        }

        return $fallback ?? ($this->document[$key] ?? null);
    }

    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    public function __isset(string $name): bool
    {
        return isset($this->document[$name]);
    }

    public function getRaw(): array
    {
        return $this->document;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->document[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->document[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->document[$offset]);
    }

    public function jsonSerialize(): array
    {
        return $this->document;
    }

    public function toArray(): array
    {
        return $this->document;
    }
}
