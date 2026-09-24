<?php

declare(strict_types=1);

namespace MagIRC\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class TranslationExtension extends AbstractExtension
{
    public function getTokenParsers(): array
    {
        return [new TranslationTokenParser(new TwigFilter('trans', $this->translate(...)))];
    }

    public function getFilters(): array
    {
        return [new TwigFilter('trans', $this->translate(...))];
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('translate', $this->translate(...))];
    }

    public function translate(string $message, array $parameters = []): string
    {
        $translated = gettext($message);

        return $parameters === [] ? $translated : strtr($translated, $parameters);
    }
}
