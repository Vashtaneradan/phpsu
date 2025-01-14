<?php

declare(strict_types=1);

namespace PHPSu\Process;

final class Spinner
{
    const PONG = [
        '(      )',
        '(●     )',
        '( ●    )',
        '(  ●   )',
        '(   ●  )',
        '(    ● )',
        '(     ●)',
        '(      )',
        '(      )',
    ];

    private $state = 0;

    public function spin(): string
    {
        if ($this->state >= count(static::PONG)) {
            $this->state = 0;
        }
        return static::PONG[$this->state++];
    }
}
