<?php

namespace App\Simulator;

use App\Model\Elevator;
use App\Model\Call;

use DateTime;
use DatePeriod;
use DateInterval;

/**
 * @author Hector Escriche <krixer@gmail.com>
 */
class Simulator
{
    private $elevators = [];

    private $sequences = [];

    private $totalElevators = 3;

    private $totalFloors = 4;

    private $travelTime = 5;

    private $floorTime = 20;

    private $calls = [];

    private $startTime;

    private $endTime;

    private $rows = [];

    public function __construct()
    {
        // Fix datetime timezone mismatches
        date_default_timezone_set('Europe/Madrid');

        // Add default simulation period
        $this->setStartTime(new DateTime('09:00'));
        $this->setEndTime(new DateTime('20:00'));
    }

    private function initializeElevators(): void
    {
        for ($i = 1; $i <= $this->getTotalElevators(); $i++) {
            $this->addElevator(new Elevator('Elevator ' . $i));
        }
    }

    private function initializeSequences(): void
    {
        if(count($this->getSequences()) === 0){
            $this->setSequences($this->getDefaultSequences());
        }
    }

    public function getTotalElevators(): int
    {
        return $this->totalElevators;
    }

    public function setTotalElevators(int $totalElevators)
    {
        $this->totalElevators = $totalElevators;
    }

    public function getTotalFloors(): int
    {
        return $this->totalFloors;
    }

    public function setTotalFloors(int $totalFloors)
    {
        $this->totalFloors = $totalFloors;
    }

    public function getTravelTime(): int
    {
        return $this->travelTime;
    }

    public function setTravelTime(int $travelTime)
    {
        $this->travelTime = $travelTime;
    }

    public function getFloorTime(): int
    {
        return $this->floorTime;
    }

    public function setFloorTime(int $floorTime): void
    {
        $this->floorTime = $floorTime;
    }

    public function getStartTime(): DateTime
    {
        return $this->startTime;
    }

    public function setStartTime(DateTime $startTime): void
    {
        $this->startTime = $startTime;
    }

    public function getEndTime(): DateTime
    {
        return $this->endTime;
    }

    public function setEndTime(DateTime $endTime): void
    {
        // Fix for correct interval
        $endTime->modify("+1 second");

        $this->endTime = $endTime;
    }

    public function getSequences(): array
    {
        return $this->sequences;
    }

    public function setSequences(array $sequences): void
    {
        $this->sequences = $sequences;
    }

    public function getElevators(): array
    {
        return $this->elevators;
    }

    public function setElevators(array $elevators): void
    {
        $this->elevators = $elevators;
    }

    public function addElevator(Elevator $elevator): void
    {
        $this->elevators[] = $elevator;
    }

    public function getCalls(): array
    {
        return $this->calls;
    }

    public function setCalls(array $calls): void
    {
        $this->calls = $calls;
    }

    private function addCall(Call $call): void
    {
        $this->calls[] = $call;
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    private function addRow(array $row): void
    {
        $this->rows[] = $row;
    }


    public function simulate(): array
    {
        $this->initializeElevators();
        $this->initializeSequences();

        $interval = new DateInterval('P0YT1S');
        $period = new DatePeriod($this->getStartTime(), $interval, $this->getEndTime());

        foreach ($period as $instant) {
            $this->checkElevators($instant);

            // Every minute
            if((int)$instant->format('s') === 0){
                $this->createRow($instant);

                foreach($this->findSequences($instant) as $sequence){
                    $this->createCalls($sequence);
                }
            }

            $this->processCalls($instant);
        }

        return $this->getRows();
    }


    private function createRow($instant)
    {
        $entry = ['time' => $instant->format('H:i')];
        foreach($this->getElevators() as $elevator){
            $entry[$elevator->getName()] = $elevator->getFloorLabel($instant->format('U'));
        }
        $this->addRow($entry);
    }


    private function createCalls($sequence)
    {
        foreach($sequence['from'] as $from){
            foreach($sequence['to'] as $to){
                $this->addCall(new Call($from, $to));
            }
        }
    }


    private function checkElevators($instant)
    {
        foreach($this->getElevators() as $elevator){
            if(!$elevator->isAvailable()){
                if($instant >= $elevator->getArrivalTime()){
                    $elevator->setAvailable(true);
                    $elevator->setSteps([]);
                }
            }
        }
    }


    private function findSequences($instant)
    {
        $machedSequences = [];
        foreach($this->getSequences() as $sequence){
            $minute = (int) $instant->format('i');
            if($instant >= $sequence['start'] && $instant <= $sequence['end'] && ($minute % $sequence['interval']) === 0){
                $machedSequences[] = $sequence;
            }
        }
        return $machedSequences;
    }


    private function processCalls($instant)
    {
        if(count($this->getCalls()) > 0){
            // dump($this->calls);
            foreach($this->calls as $key => $call){
                $elevator = $this->findNearestElevator($call);

                if(!$elevator){
                    break;
                }

                $this->sendElevator($instant, $call, $elevator);
                unset($this->calls[$key]);
            }
        }
    }


    private function sendElevator($instant, $call, $elevator)
    {
        $time = clone $instant;
        $elevator->setAvailable(false);

        $travels = [];

        // Only if elevator is in another floor
        if($call->getFrom() !== $elevator->getFloor()){
            $travels[] = [
                'from' => $elevator->getFloor(),
                'to' => $call->getFrom()
            ];
        }

        // Add travel time to destination floor
        $travels[] = [
            'from' => $call->getFrom(),
            'to' => $call->getTo()
        ];


        $steps = [];
        $current = $time->format('U');
        $currentFloorsTraveled = $elevator->getFloorsTraveled();

        // Process elevator travels
        foreach($travels as $travel){
            $range = range($travel['from'], $travel['to']);
            $distance = count($range) - 1;

            // Add intrafloors movements
            for($i = 0; $i < $distance; $i++){
                $currentFloorsTraveled++;
                for($k = 0; $k < $this->getTravelTime(); $k++){
                    $steps[$current] = $range[$i] . ' -> '. $range[$i+1] . '|' . $currentFloorsTraveled .' (in movement)';
                    $current++;
                }
            }

            // Add waiting floor time
            for($k = 0; $k < $this->getFloorTime(); $k++){
                $steps[$current] = $range[$i] . '|' . $currentFloorsTraveled . ' (waiting time)';
                $current++;
            }

            $elevator->travelToFloor($travel['to']);
        }


        $seconds = $current - $time->format('U');
        $time->modify('+'.$seconds.' seconds');

        $elevator->setArrivalTime($time);
        $elevator->setSteps($steps);
    }


    private function findNearestElevator($call)
    {
        $byDistance = [];
        foreach($this->getElevators() as $elevator){
            if($elevator->isAvailable()){
                $distance = $elevator->getDistanceFromFloor($call->getFrom());

                if($distance === 0){
                    return $elevator;
                }

                if(!array_key_exists($distance, $byDistance))
                    $byDistance[$distance] = $elevator;
            }
        }

        if(count($byDistance) > 0){
            ksort($byDistance);

            return reset($byDistance);
        }

        return null;
    }


    public function getDefaultSequences(): array
    {
        return [
            [
                'name' => 'Sequence 1',
                'interval' => 5,
                'start' => new DateTime('09:00'),
                'end' => new DateTime('11:00'),
                'from' => [0],
                'to' => [2]
            ],[
                'name' => 'Sequence 2',
                'interval' => 10,
                'start' => new DateTime('09:00'),
                'end' => new DateTime('10:00'),
                'from' => [0],
                'to' => [1]
            ],[
                'name' => 'Sequence 3',
                'interval' => 20,
                'start' => new DateTime('11:00'),
                'end' => new DateTime('18:20'),
                'from' => [0],
                'to' => [1,2,3]
            ],[
                'name' => 'Sequence 4',
                'interval' => 4,
                'start' => new DateTime('14:00'),
                'end' => new DateTime('15:00'),
                'from' => [1,2,3],
                'to' => [0]
            ]
        ];
    }
}