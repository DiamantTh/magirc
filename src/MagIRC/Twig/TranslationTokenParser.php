<?php

declare(strict_types=1);

namespace MagIRC\Twig;

use Twig\Error\SyntaxError;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\Node\PrintNode;
use Twig\Node\TextNode;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use Twig\TwigFilter;

/**
 * Keeps the legacy `{% trans %}` syntax available while using Twig 3 nodes.
 *
 * The old templates use block translations for messages containing a value,
 * such as "User info for {{ target }}". Those values are represented as
 * `%1`, `%2`, ... before gettext is called, matching the existing PO files.
 */
final class TranslationTokenParser extends AbstractTokenParser
{
    public function __construct(private readonly TwigFilter $filter)
    {
    }

    public function parse(Token $token): Node
    {
        $line = $token->getLine();
        $stream = $this->parser->getStream();

        if ($stream->test(Token::BLOCK_END_TYPE)) {
            $stream->next();
            $body = $this->parser->subparse($this->decideTransEnd(...), true);
            $stream->expect(Token::BLOCK_END_TYPE);
            [$message, $parameters] = $this->messageExpression($body, $line);
        } else {
            $message = $this->parser->parseExpression();
            $stream->expect(Token::BLOCK_END_TYPE);
            $parameters = new Nodes([], $line);
        }

        $translated = new FilterExpression($message, $this->filter, $parameters, $line);

        return new PrintNode($translated, $line);
    }

    public function decideTransEnd(Token $token): bool
    {
        return $token->test('endtrans');
    }

    public function getTag(): string
    {
        return 'trans';
    }

    /**
     * @return array{0: AbstractExpression, 1: Nodes}
     */
    private function messageExpression(Node $body, int $line): array
    {
        $nodes = $body instanceof Nodes ? iterator_to_array($body) : [$body];
        $message = '';
        $parameters = [];
        $placeholder = 1;

        foreach ($nodes as $node) {
            if ($node instanceof TextNode) {
                $message .= $node->getAttribute('data');
                continue;
            }

            if ($node instanceof PrintNode) {
                $expression = $node->getNode('expr');
                if (!$expression instanceof AbstractExpression) {
                    throw new SyntaxError(
                        'The trans block output must be a Twig expression.',
                        $node->getTemplateLine(),
                        $node->getSourceContext(),
                    );
                }
                $message .= '%' . $placeholder;
                $parameters[] = [
                    new ConstantExpression('%' . $placeholder, $line),
                    $expression,
                ];
                ++$placeholder;
                continue;
            }

            throw new SyntaxError(
                'The trans block can contain text and output expressions only.',
                $node->getTemplateLine(),
                $node->getSourceContext(),
            );
        }

        $expression = new ConstantExpression($message, $line);
        $arguments = new ArrayExpression([], $line);
        foreach ($parameters as [$key, $value]) {
            $arguments->addElement($value, $key);
        }

        return [$expression, new Nodes($parameters === [] ? [] : [$arguments], $line)];
    }
}
