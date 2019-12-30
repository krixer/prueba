<?php

namespace App\Controller;

use App\Simulator\Simulator;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SimulatorController extends AbstractController
{
    private $simulator;

    public function __construct(Simulator $simulator)
    {
        $this->simulator = $simulator;
    }

    /**
     * @Route("/", name="simulator")
     */
    public function simulator()
    {
        $rows = $this->simulator->simulate();

        // Prepare table headers
        $headers = ['Time'];
        foreach($this->simulator->getElevators() as $elevator){
            $headers[] = $elevator->getName() . ' (Floor|Total)';
        }

        return $this->render('simulator/index.html.twig', [
            'headers' => $headers,
            'rows' => $rows
        ]);
    }
}