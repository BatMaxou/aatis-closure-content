<?php

namespace Aatis;

class ClosureContent implements \Stringable
{
    private const REGEX_PATTERNS = [
        'constructor' => 'new ClosureContent\((?<constructor>.*)\)',
        'of' => 'ClosureContent::of\((?<of>.*)\)',
    ];

    private string $content;

    private function __construct(\Closure $closure)
    {
        $this->retrieveContent($closure);
    }

    private function retrieveContent(\Closure $closure): void
    {
        $lines = $this->getLines(new \ReflectionFunction($closure));

        preg_match_all(
            \sprintf('/%s/s', implode('|', self::REGEX_PATTERNS)),
            implode('', $lines),
            $matches,
        );

        if (!isset($matches['constructor'][0]) && !isset($matches['of'][0])) {
            preg_match_all('/\{(?<content>.*)\}/s', implode('', $lines), $matches);
        } else {
            $anonym = $matches['constructor'][0] ?: $matches['of'][0];
            preg_match_all('/function\s*\(.*\)\s*\{(?<content>.*)\}/s', $anonym, $matches);

            if (!isset($matches['content'][0])) {
                preg_match_all('/fn\s*\(.*\)\s*=>\s*(?<content>.*)/s', $anonym, $matches);
            }
        }

        $unformattedContent = $matches['content'][0] ?? '';
        $spacesToRemove = strlen($unformattedContent) - strlen(ltrim($unformattedContent)) -1;

        $this->content = preg_replace(['/^([\n]+|[ ]{'.$spacesToRemove.'})/m', '/[\s]+$/m'], '', $matches['content'][0] ?? '');
        if (!str_ends_with($this->content, ';')) {
            $this->content .= ';';
        }
    }

    /**
     * @return string[]
     */
    private function getLines(\ReflectionFunction $function): array
    {
        $file = file($function->getFileName());
        $startLine = $function->getStartLine();

        return array_slice($file, $startLine - 1, $function->getEndLine() - $startLine + 1);
    }

    public static function of(callable $callable): static
    {
        return new static(\Closure::fromCallable($callable));
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
