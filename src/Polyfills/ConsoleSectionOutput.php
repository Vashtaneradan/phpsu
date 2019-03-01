<?php
declare(strict_types=1);

namespace Symfony\Component\Console\Output;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Terminal;

class ConsoleSectionOutput extends StreamOutput
{
    private $content = [];
    private $lines = 0;
    private $sections;
    private $terminal;

    /**
     * @param resource $stream
     * @param ConsoleSectionOutput[] $sections
     * @param int $verbosity
     * @param bool $decorated
     * @param OutputFormatterInterface $formatter
     */
    public function __construct($stream, array &$sections, int $verbosity, bool $decorated, OutputFormatterInterface $formatter)
    {
        parent::__construct($stream, $verbosity, $decorated, $formatter);
        array_unshift($sections, $this);
        $this->sections = &$sections;
        $this->terminal = new Terminal();
    }

    /**
     * Clears previous output for this section.
     *
     * @param int $lines Number of lines to clear. If null, then the entire output of this section is cleared
     */
    public function clear(int $lines = null): void
    {
        if (empty($this->content) || !$this->isDecorated()) {
            return;
        }
        if ($lines) {
            \array_splice($this->content, -($lines * 2)); // Multiply lines by 2 to cater for each new line added between content
        } else {
            $lines = $this->lines;
            $this->content = [];
        }
        $this->lines -= $lines;
        parent::doWrite($this->popStreamContentUntilCurrentSection($lines), false);
    }

    /**
     * Overwrites the previous output with a new message.
     *
     * @param array|string $message
     */
    public function overwrite($message): void
    {
        $this->clear();
        $this->writeln($message);
    }

    public function getContent(): string
    {
        return implode('', $this->content);
    }

    /**
     * @param string $input
     * @internal
     */
    public function addContent(string $input): void
    {
        foreach (explode(PHP_EOL, $input) as $lineContent) {
            $this->lines += ceil($this->getDisplayLength($lineContent) / $this->terminal->getWidth()) ?: 1;
            $this->content[] = $lineContent;
            $this->content[] = PHP_EOL;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doWrite($message, $newline)
    {
        if (!$this->isDecorated()) {
            return parent::doWrite($message, $newline);
        }
        $erasedContent = $this->popStreamContentUntilCurrentSection();
        $this->addContent($message);
        parent::doWrite($message, true);
        parent::doWrite($erasedContent, false);
    }

    /**
     * At initial stage, cursor is at the end of stream output. This method makes cursor crawl upwards until it hits
     * current section. Then it erases content it crawled through. Optionally, it erases part of current section too.
     * @param float|int $numberOfLinesToClearFromCurrentSection
     * @return string
     */
    private function popStreamContentUntilCurrentSection($numberOfLinesToClearFromCurrentSection = 0): string
    {
        $numberOfLinesToClear = (int)$numberOfLinesToClearFromCurrentSection;
        $erasedContent = [];
        foreach ($this->sections as $section) {
            if ($section === $this) {
                break;
            }
            $numberOfLinesToClear += $section->lines;
            $erasedContent[] = $section->getContent();
        }
        if ($numberOfLinesToClear > 0) {
            // move cursor up n lines
            parent::doWrite(sprintf("\x1b[%dA", $numberOfLinesToClear), false);
            // erase to end of screen
            parent::doWrite("\x1b[0J", false);
        }
        return implode('', array_reverse($erasedContent));
    }

    private function getDisplayLength(string $text): string
    {
        return (string)Helper::strlenWithoutDecoration($this->getFormatter(), str_replace("\t", '        ', $text));
    }
}