<?php

namespace App\Model;

/**
 * @author Hector Escriche <krixer@gmail.com>
 */
class Call
{
    private $from;
    private $to;

    public function __construct(int $from, int $to)
    {
        $this->setFrom($from);
        $this->setTo($to);
    }

    public function setFrom(int $from): void
    {
        $this->from = $from;
    }

    public function getFrom(): int
    {
        return $this->from;
    }


    public function setTo(int $to): void
    {
        $this->to = $to;
    }

    public function getTo(): int
    {
        return $this->to;
    }
}