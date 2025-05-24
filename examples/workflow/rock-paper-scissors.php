<?php 

/**
 * Rock-Paper-Scissors Game Workflow Example.
 * 
 * This example demonstrates a simple rock-paper-scissors game using NeuronAI's workflow capabilities.
 * 
 * Contributed by: [Peter Ivanov](https://github.com/peter-mw)
 * 
 * Usage:
 * 
 *   php  examples/workflow/rock-paper-scissors.php
 * 
 * TODO:
 * You must uncomment and adapt one of the provider sections below to use either OpenAI or Ollama.
 * You may also use other providers supported by NeuronAI.
 */

declare(strict_types=1);

require_once __DIR__.'/../../vendor/autoload.php';

use NeuronAI\Agent;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Workflow\StateGraph;
use NeuronAI\Workflow\WorkflowAgent;

// ----- OPENAI provider
// $provider = new OpenAI(
//     key: 'YOUR-OPENAI-API-KEY',      // TODO: adapt to match your OpenAI API key
//     model: 'gpt-4o',
// );

// ----- OLLAMA provider
// $provider = new \NeuronAI\Providers\Ollama\Ollama(
//     url: 'http://localhost:11434/api',  // TODO: adapt to match your Ollama server URL
//     model: 'qwen2.5:3b',                // TODO: Adapt to match your Ollama model 
// );

if (!isset($provider)) {
    die('Please uncomment and adapt the provider section to use either OpenAI or Ollama.' . PHP_EOL);
}

// Create a tool for making a choice
$makeChoiceTool = Tool::make('make_choice', 'Generate a choice between rock, paper, or scissors.')
    ->setCallable(function () {
        $choices = ['rock', 'paper', 'scissors'];
        return $choices[array_rand($choices)];
    });

// Create a tool to determine the winner
$determineWinnerTool = Tool::make('determine_winner', 'Determine the winner of rock, paper, scissors.')
    ->addProperty(
        new ToolProperty(
            name: 'player1_choice',
            type: 'string',
            description: 'Player 1\'s choice (rock, paper, or scissors).',
            required: true,
        )
    )
    ->addProperty(
        new ToolProperty(
            name: 'player2_choice',
            type: 'string',
            description: 'Player 2\'s choice (rock, paper, or scissors).',
            required: true,
        )
    )
    ->setCallable(function (string $player1_choice, string $player2_choice) {
        if ($player1_choice === $player2_choice) {
            return "It's a tie! Both chose $player1_choice.";
        }

        $winConditions = [
            'rock' => 'scissors',
            'paper' => 'rock',
            'scissors' => 'paper'
        ];

        if ($winConditions[$player1_choice] === $player2_choice) {
            return "Player 1 wins! $player1_choice beats $player2_choice.";
        } else {
            return "Player 2 wins! $player2_choice beats $player1_choice.";
        }
    });

// Create agent for Player 1
$player1Agent = Agent::make()
    ->withProvider($provider)
    ->withInstructions('You are Player 1 in a rock-paper-scissors game. Generate a choice.')
    ->addTool($makeChoiceTool);

// Create agent for Player 2
$player2Agent = Agent::make()
    ->withProvider($provider)
    ->withInstructions('You are Player 2 in a rock-paper-scissors game. Generate a choice.')
    ->addTool($makeChoiceTool);

// Create agent for determining the winner
$determineWinnerAgent = Agent::make()
    ->withProvider($provider)
    ->withInstructions('You determine the winner of a rock-paper-scissors game.')
    ->addTool($determineWinnerTool);

// Build the workflow graph
$graph = (new StateGraph())
    ->addNode('player1', $player1Agent)
    ->addNode('player2', $player2Agent)
    ->addNode('determine_winner', $determineWinnerAgent)
    ->addEdge(StateGraph::START_NODE, 'player1')
    ->addEdge('player1', 'player2')
    ->addEdge('player2', 'determine_winner')
    ->addEdge('determine_winner', StateGraph::END_NODE);

// Create the workflow agent and process the game
$workflowAgent = WorkflowAgent::make($graph);
$reply = $workflowAgent->chat(new UserMessage("Determine the winner."));

echo "Game Result:\n";
echo $reply->getContent() . "\n";

