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

            // Initialize stops array
            $stops = [];

            // Merge calls in two arrays
            $from = [];
            $to = [];
            foreach($this->calls as $key => $call){
                $from[] = $call->getFrom();
                $to[] = $call->getTo();
            }

            // Remove duplicate calls
            $from = array_unique($from);
            $to = array_unique($to);


            /**
             * Check 4 posible scenarios
             *
             *   1# 1 Origin  <-> 1 Destination
             *   2# 1 Origin  <-> N Destinations
             *   3# N Origins <-> 1 Destination
             *   4# N Origins <-> N Destinations
             */

            if (count($from) === 1 && count($to) === 1) { // Scenario 1

                $stops[] = [
                    'from' => $from[0],
                    'to' => $to[0]
                ];

            } else if (count($from) === 1 && count($to) > 1) { // Scenario 2

                sort($to);

                $previous = $from[0];
                foreach($to as $stop){
                    $stops[] = [
                        'from' => $previous,
                        'to' => $stop
                    ];

                    $previous = $stop;
                }


            } else if (count($from) > 1 && count($to) === 1) { // Scenario 3

                sort($from);

                foreach($from as $key => $stop){
                    $next_key = $key+1;
                    $next = array_key_exists($next_key, $from) ? $from[$next_key] : $to[0];

                    $stops[] = [
                        'from' => $stop,
                        'to' => $next
                    ];
                }

            } else if (count($from) > 1 && count($to) > 1) { // Scenario 4

                sort($from);
                rsort($to);

                foreach($from as $key => $stop){
                    $next_key = $key+1;
                    $next = array_key_exists($next_key, $from) ? $from[$next_key] : null;

                    if($next){
                        $stops[] = [
                            'from' => $stop,
                            'to' => $next
                        ];
                    }
                }

                $previous = $from[$key];
                foreach($to as $stop){
                    if($previous !== $stop){
                        $stops[] = [
                            'from' => $previous,
                            'to' => $stop
                        ];
                    }

                    $previous = $stop;
                }
            }




            if(count($stops) > 0) {
                $elevator = $this->findNearestElevator($stops[0]);

                if(!$elevator){
                    return null;
                }

                if($elevator->getFloor() !== $stops[0]['from']){
                    array_unshift($stops, [
                        'from' => $elevator->getFloor(),
                        'to' => $stops[0]['from']
                    ]);
                }

                $this->sendElevator($instant, $stops, $elevator);
                $this->setCalls([]);
            }
        }
    }


    private function findStops($primaryCall)
    {
        $from = [];
        $top = [];
        $stops = [];
        foreach($this->calls as $key => $call){
            if($primaryCall->getFrom() === $call->getFrom()){

            }
        }
    }


    private function sendElevator($instant, $stops, $elevator)
    {
        $time = clone $instant;
        $elevator->setAvailable(false);

        $steps = [];
        $current = $time->format('U');
        $currentFloorsTraveled = $elevator->getFloorsTraveled();

        // Process elevator travels
        foreach($stops as $travel){
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


    private function findNearestElevator($stop)
    {
        $byDistance = [];
        foreach($this->getElevators() as $elevator){
            if($elevator->isAvailable()){
                $distance = $elevator->getDistanceFromFloor($stop['from']);

                if($distance === 0){
                    return $elevator;
                }

                if(!array_key_exists($distance, $byDistance)){
                    $byDistance[$distance] = [];
                }
                $byDistance[$distance][$elevator->getFloorsTraveled()] = $elevator;
            }
        }

        if(count($byDistance) > 0){
            ksort($byDistance);

            $elevators = reset($byDistance);
            ksort($elevators);

            return reset($elevators);
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