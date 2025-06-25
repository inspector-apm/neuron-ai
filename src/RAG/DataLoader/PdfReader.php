<?php

declare(strict_types=1);

namespace NeuronAI\RAG\DataLoader;

use NeuronAI\Exceptions\DataReaderException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Requires pdftotext php extension.
 *
 * https://en.wikipedia.org/wiki/Pdftotext
 */
class PdfReader implements ReaderInterface
{
    protected string $pdf;

    protected string $binPath;

    protected array $options = [];

    protected int $timeout = 60;

    protected array $env = [];

    public function __construct(?string $binPath = null)
    {
        $this->binPath = \is_string($binPath) ? $this->setBinPath($binPath) : $this->findPdfToText();
    }

    public function setBinPath(string $binPath): self
    {
        if (!\is_executable($binPath)) {
            throw new DataReaderException("The provided path is not executable.");
        }
        $this->binPath = $binPath;
        return $this;
    }

    protected function findPdfToText(): string
    {
        $commonPaths = [
            '/usr/bin/pdftotext',          // Common on Linux
            '/usr/local/bin/pdftotext',    // Common on Linux
            '/opt/homebrew/bin/pdftotext', // Homebrew on macOS (Apple Silicon)
            '/opt/local/bin/pdftotext',    // MacPorts on macOS
            '/usr/local/bin/pdftotext',    // Homebrew on macOS (Intel)
        ];

        foreach ($commonPaths as $path) {
            if (\is_executable($path)) {
                return $path;
            }
        }

        throw new DataReaderException("The pdftotext binary was not found or is not executable.");
    }

    public function setPdf(string $pdf): self
    {
        if (!\is_readable($pdf)) {
            throw new DataReaderException("Could not read `{$pdf}`");
        }

        $this->pdf = $pdf;

        return $this;
    }

    public function setOptions(array $options): self
    {
        $this->options = $this->parseOptions($options);

        return $this;
    }

    public function addOptions(array $options): self
    {
        $this->options = \array_merge(
            $this->options,
            $this->parseOptions($options)
        );

        return $this;
    }

    protected function parseOptions(array $options): array
    {
        $mapper = function (string $content): array {
            $content = \trim($content);
            if ('-' !== ($content[0] ?? '')) {
                $content = '-' . $content;
            }

            return \explode(' ', $content, 2);
        };

        $reducer = fn (array $carry, array $option): array => \array_merge($carry, $option);

        return \array_reduce(\array_map($mapper, $options), $reducer, []);
    }

    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function text(): string
    {
        $process = new Process(\array_merge([$this->binPath], $this->options, [$this->pdf, '-']));
        $process->setTimeout($this->timeout);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return \trim($process->getOutput(), " \t\n\r\0\x0B\x0C");
    }

    /**
     * @throws \Exception
     */
    public static function getText(
        string $filePath,
        array $options = []
    ): string {
        /** @phpstan-ignore new.static */
        $instance = new static();
        $instance->setPdf($filePath);

        if (\array_key_exists('binPath', $options)) {
            $instance->setBinPath($options['binPath']);
        }

        if (\array_key_exists('options', $options)) {
            $instance->setOptions($options['options']);
        }

        if (\array_key_exists('timeout', $options)) {
            $instance->setTimeout($options['timeout']);
        }

        return $instance->text();
    }
}
