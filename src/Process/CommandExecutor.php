<?php
declare(strict_types=1);

namespace PHPSu\Process;

use PHPSu\Exceptions\CommandExecutionException;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CommandExecutor
{
    /**
     * @param string[] $commands
     * @param OutputInterface $logOutput
     * @param OutputInterface $statusOutput
     * @return void
     */
    public function executeParallel(array $commands, OutputInterface $logOutput, OutputInterface $statusOutput): void
    {
        $manager = new ProcessManager();
        foreach ($commands as $name => $command) {
            $logOutput->writeln(sprintf('<fg=yellow>%s:</> <fg=white;options=bold>running command: %s</>', $name, $command), OutputInterface::VERBOSITY_VERBOSE);
            $process = Process::fromShellCommandline($command, null, null, null, null);
            $process->setName($name);
            $manager->addProcess($process);
        }
        $callback = new StateChangeCallback($statusOutput);
        $manager->addStateChangeCallback($callback);
        $manager->addTickCallback($callback->getTickCallback());
        $manager->addOutputCallback(new OutputCallback($logOutput));
        $manager->mustRun();
    }

    public function passthru(string $command, OutputInterface $output): int
    {
        $process = Process::fromShellCommandline($command, null, null, null, null);
        $process->setTty(true);
        $process->run(function ($type, $buffer) use ($output) {
            if ($type === Process::ERR && $output instanceof ConsoleOutputInterface) {
                $output = $output->getErrorOutput();
                $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
            }
            $output->write($buffer);
        });
        return $process->getExitCode();
    }

    public function executeDirectly(string $command, bool $throwOnError = false): array
    {
        $process = Process::fromShellCommandline($command, null, null, null, null);
        $process->run();
        if ($throwOnError && (!empty($process->getErrorOutput()) || $process->getExitCode() !== 0)) {
            throw new CommandExecutionException('Command execution failed - ' . $process->getErrorOutput(), $process->getExitCode());
        }
        return [$process->getOutput(), $process->getErrorOutput(), $process->getExitCode()];
    }
}
