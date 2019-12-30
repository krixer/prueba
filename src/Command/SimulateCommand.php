<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ChoiceQuestion;


use App\Simulator\Simulator;
use App\Validator\Validator;

use InvalidArgumentException;

/**
 * @author Hector Escriche <krixer@gmail.com>
 */
class SimulateCommand extends Command
{
    protected static $defaultName = 'app:simulate';

    private $simulator;

    public function __construct(Simulator $simulator)
    {
        $this->simulator = $simulator;

        parent::__construct();
    }


    protected function configure()
    {
        $this->setDescription('Simulate elevators travels');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Elevators simulator');
        $io->text('Enter the requested variables to start the simulation, leave them empty to use the default values.');
        $io->newLine();

        // Get values from user or use default.
        $this->simulator->setTotalElevators($io->ask('Number of elevators for simulation?', $this->simulator->getTotalElevators(), [Validator::class, 'validateNumber']));
        $this->simulator->setTotalFloors($io->ask('Number of floors for simulation?', $this->simulator->getTotalFloors(), [Validator::class, 'validateNumber']));
        $this->simulator->setTravelTime($io->ask('Travel time in seconds between floors?', $this->simulator->getTravelTime(), [Validator::class, 'validateNumber']));
        $this->simulator->setFloorTime($io->ask('Floor waiting time in seconds?', $this->simulator->getFloorTime(), [Validator::class, 'validateNumber']));
        $this->simulator->setStartTime($io->ask('Simulation start time (24h format time, example: 09:00)', $this->simulator->getStartTime()->format('H:i'), [Validator::class, 'validateTime']));
        $this->simulator->setEndTime($io->ask('Simulation end time (24h format time, example: 20:00)', $this->simulator->getEndTime()->format('H:i'), [Validator::class, 'validateTime']));

        // Ask for custom sequences
        $io->section('Adding sequences');

        $sequences = [];
        $isFirstSequence = true;
        while(true){
            $newSequence = $this->askForNextSequence($io, $sequences, $isFirstSequence, $this->simulator->getTotalFloors());
            $isFirstSequence = false;

            if (null === $newSequence || !isset($newSequence['name'])) {
                break;
            }

            $sequences[$newSequence['name']] = $newSequence;
        }

        $this->simulator->setSequences($sequences);


        // Run simulation
        $rows = $this->simulator->simulate();


        // Prepare table headers output
        $headers = ['Time'];
        foreach($this->simulator->getElevators() as $elevator){
            $headers[] = $elevator->getName() . ' (Floor|Total)';
        }

        // Create and render table
        $table = new Table($output);
        $table
            ->setStyle('box-double')
            ->setHeaders($headers)
            ->setRows($rows)
            ->render();

        $io->success('Simulation completed successfully!');
        return 0;
    }



    private function askForNextSequence(SymfonyStyle $io, array $sequences, bool $isFirstSequence, int $floors)
    {
        $io->writeln('');

        if ($isFirstSequence) {
            $questionText = 'New sequence name (press <return> to use default sequences)';
        } else {
            $questionText = 'Add another sequence? Enter the sequence name (or press <return> to stop adding sequences)';
        }

        $sequenceName = $io->ask($questionText, null, function ($name) use ($sequences) {
            if (!$name) {
                return $name;
            }

            if (\array_key_exists($name, $sequences)) {
                throw new InvalidArgumentException(sprintf('The "%s" sequence already exists.', $name));
            }

            return $name;
        });

        if (!$sequenceName) {
            return null;
        }

        $floorsArray = [];
        for ($i = 0; $i < $floors; $i++){
            $floorsArray[$i] = $i;
        }

        $data = ['name' => $sequenceName];
        $data['interval'] = $io->ask('Sequence interval in minutes', '5', [Validator::class, 'validateNumber']);
        $data['start'] = $io->ask('Sequence start time (24h format time, example: 13:30)', '09:00', [Validator::class, 'validateTime']);
        $data['end'] = $io->ask('Sequence end time (24h format time, example: 13:30)', '11:00', [Validator::class, 'validateTime']);
        $data['from'] = $this->makeQuestion($io, 'Please select start floor (you can choose several values separated by commas)', $floorsArray, 0);
        $data['to'] = $this->makeQuestion($io, 'Please select end floor (you can choose several values separated by commas)', $floorsArray, 3);

        $data['running'] = false;
        $data['travelingFrom'] = null;
        $data['travelingTo'] = null;

        return $data;
    }


    private function makeQuestion($io, $question, $choices, $default)
    {
        $ask = new ChoiceQuestion($question, $choices, $default);
        $ask->setMultiselect(true);
        $ask->setErrorMessage('Floor %s is invalid.');
        return $io->askQuestion($ask);
    }
}
