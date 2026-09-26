<?php

declare(strict_types=1);

namespace MagIRC\Bootstrap;

/** Resolves private and public paths from one installation root. */
final readonly class ApplicationPaths
{
    public function __construct(public string $root)
    {
        if (!is_dir($root)) {
            throw new \InvalidArgumentException('MagIRC project root does not exist.');
        }
    }

    public function private(string ...$parts): string
    {
        return $this->join($this->root, $parts);
    }

    public function public(string ...$parts): string
    {
        return $this->join($this->root . DIRECTORY_SEPARATOR . 'httpdocs', $parts);
    }

    private function join(string $base, array $parts): string
    {
        foreach ($parts as $part) {
            if (in_array($part, ['', '.', '..'], true) || str_contains($part, DIRECTORY_SEPARATOR)) {
                throw new \InvalidArgumentException('Path segments must be simple names.');
            }
            $base .= DIRECTORY_SEPARATOR . $part;
        }
        return $base;
    }
}
