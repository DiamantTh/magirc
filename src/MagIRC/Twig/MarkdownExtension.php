<?php

declare(strict_types=1);

namespace MagIRC\Twig;

use Michelf\MarkdownExtra;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;

final class MarkdownExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('markdown', $this->render(...), ['is_safe' => ['html']])];
    }

    public function render(string $markdown): Markup
    {
        return new Markup(MarkdownExtra::defaultTransform($markdown), 'UTF-8');
    }
}
