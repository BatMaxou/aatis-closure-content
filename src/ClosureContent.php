<?php

namespace Aatis;

class ClosureContent implements \Stringable
{
    private const REGEX_PATTERNS = [
        'constructor' => 'new ClosureContent\((?<constructor>.*)\)',
        'of' => 'ClosureContent::of\((?<of>.*)\)',
    ];

    private string $parameters;

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
            $lines,
            $matches,
        );

        $anonym = null;
        if (!isset($matches['constructor'][0]) && !isset($matches['of'][0])) {
            preg_match_all('/function\s*\((?<parameter>.*)\)\s*\{(?<content>.*)\}/s', $lines, $matches);

            if (!isset($matches['content'][0])) {
                $anonym = $lines;
            }
        } else {
            $anonym = $matches['constructor'][0] ?: $matches['of'][0];
        }

        $spacesToRemove = 0;
        if ($anonym) {
            preg_match_all('/function\s*(.*\s*)\((?<parameter>.*)\)(.*)\{(?<content>.*)\}/s', $anonym, $matches);

            if (!isset($matches['content'][0])) {
                $spacesToRemove = $this->calculateSpaceToRemove($anonym);
                preg_match_all('/fn\s*\((?<parameter>.*)\)\s*=>\s*(?<content>.*)/s', $anonym, $matches);
                if (isset($matches['content'][0])) {
                    $matches['content'][0] = $this->cleanContent($matches['content'][0]);
                }
            }
        }

        $unformattedContent = $matches['content'][0] ?? '';
        if (0 === $spacesToRemove) {
            // TODO: Check why -1
            $spacesToRemove = $this->calculateSpaceToRemove($unformattedContent) - 1;
        }

        $this->parameters = $matches['parameter'][0] ?? '';
        $this->content = preg_replace([sprintf('/^([\n]+|[ ]{%d})/m', $spacesToRemove), '/[\s]+$/m'], '', $matches['content'][0] ?? '') ?? '';

        if (str_ends_with($this->content, ',')) {
            $this->content = rtrim($this->content, ',');
        }

        if (!str_ends_with($this->content, ';') && !empty($this->content)) {
            $this->content .= ';';
        }
    }

    private function getLines(\ReflectionFunction $function): string
    {
        if (!$fileName = $function->getFileName()) {
            return '';
        }

        $file = file($fileName);
        $startLine = $function->getStartLine();
        $endLine = $function->getEndLine();

        if (!$file || false === $startLine || false === $endLine) {
            return '';
        }

        return implode('', array_slice($file, $startLine - 1, $endLine - $startLine + 1));
    }

    private function cleanContent(string $content): string
    {
        $content = trim($content);
        $openParens = substr_count($content, '(');
        $closeParens = substr_count($content, ')');

        if ($closeParens > $openParens) {
            $diff = $closeParens - $openParens;
            /** @var string $content */
            $content = preg_replace(\sprintf('/([\)]{%d});?$/', $diff), '', $content);
        }

        return $content;
    }

    private function calculateSpaceToRemove(string $content): int
    {
        return strlen($content) - strlen(ltrim($content));
    }

    public static function of(callable $callable): self
    {
        return new self(\Closure::fromCallable($callable));
    }

    public function getParameters(): string
    {
        return $this->parameters;
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
