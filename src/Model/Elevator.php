<?php

namespace App\Model;

use DateTime;

/**
 * @author Hector Escriche <krixer@gmail.com>
 */
class Elevator{

	private $floor = 0;

    private $floorsTraveled = 0;

    private $available = true;

    private $arrivalTime = null;

    private $steps = [];

    private $name;


    public function __construct(string $name)
    {
        $this->setName($name);
    }

    public function getFloor(): int
    {
        return $this->floor;
    }

    public function getFloorLabel($timestamp): string
    {
        return $this->isAvailable() ? $this->getFloor().'|'.$this->getFloorsTraveled() : $this->getStep($timestamp);
    }

    public function setFloor(int $floor): void
    {
        $this->floor = $floor;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function setAvailable(bool $available): void
    {
        $this->available = $available;
    }

    public function setName($name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFloorsTraveled(): int
    {
        return $this->floorsTraveled;
    }

    public function travelToFloor(int $floor): void
    {
        $this->floorsTraveled += $this->getDistanceFromFloor($floor);
        $this->setFloor($floor);
    }

    public function getDistanceFromFloor($floor): int
    {
        return (count(range($floor, $this->getFloor())) - 1);
    }

    public function setArrivalTime(DateTime $arrivalTime): void
    {
        $this->arrivalTime = $arrivalTime;
    }

    public function getArrivalTime(): DateTime
    {
        return $this->arrivalTime;
    }

    public function setSteps(array $steps): void
    {
        $this->steps = $steps;
    }

    public function getSteps(): array
    {
        return $this->steps;
    }

    public function getStep($key): string
    {
        return array_key_exists($key, $this->steps) ? $this->steps[$key] : 'in movement...';
    }
}