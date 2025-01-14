<?php

declare(strict_types=1);

namespace PHPSu\Process;

final class ProcessManager
{
    /** @var Process[] */
    private $processes = [];

    /** @var string[] */
    private $processStates = [];

    /** @var \Closure[] */
    private $outputCallbacks = [];

    /** @var \Closure[] */
    private $stateChangeCallbacks = [];

    /** @var \Closure[] */
    private $tickCallbacks = [];

    public function addOutputCallback(callable $callback): ProcessManager
    {
        $this->outputCallbacks[] = function () use ($callback) {
            return $callback(...func_get_args());
        };
        return $this;
    }

    public function addStateChangeCallback(callable $callback): ProcessManager
    {
        $this->stateChangeCallbacks[] = function () use ($callback) {
            return $callback(...func_get_args());
        };
        return $this;
    }

    public function addTickCallback(callable $callback): ProcessManager
    {
        $this->tickCallbacks[] = function () use ($callback) {
            return $callback(...func_get_args());
        };
        return $this;
    }

    public function mustRun(): ProcessManager
    {
        $this->start();
        $this->wait();
        $this->validateProcesses();
        return $this;
    }

    public function start(): ProcessManager
    {
        foreach ($this->processes as $processId => $process) {
            $this->notifyStateChangeCallbacks($processId, $process, $this->processStates[$processId], $this);
            $process->start(function (string $type, string $data) use ($process) {
                $this->notifyOutputCallbacks($process, $type, $data);
            });
        }
        return $this;
    }

    public function addProcess(Process $process): ProcessManager
    {
        $this->processes[] = $process;
        $this->processStates[count($this->processes) - 1] = $process->getState();
        return $this;
    }

    /**
     * @return Process[]
     */
    public function getProcesses(): array
    {
        return $this->processes;
    }

    public function wait(): ProcessManager
    {
        $count = 0;
        do {
            $isAProcessRunning = false;
            foreach ($this->processes as $processId => $process) {
                if ($process->isRunning()) {
                    $isAProcessRunning = true;
                }
                $newState = $process->getState();
                if ($this->processStates[$processId] !== $newState) {
                    $this->processStates[$processId] = $newState;
                    $this->notifyStateChangeCallbacks($processId, $process, $this->processStates[$processId], $this);
                }
                $this->notifyTickCallbacks($this);
            }
            // Sleep starting with 100ns for very fast Processes.
            // Going up in 100ns steps, until 100ms steps.
            // To keep the wait function "green"
            usleep(min(100 * 1000, $count += 100));
        } while ($isAProcessRunning);
        return $this;
    }

    /**
     * @return void
     */
    private function notifyOutputCallbacks(Process $process, string $type, string $data)
    {
        foreach ($this->outputCallbacks as $callback) {
            $callback($process, $type, $data);
        }
    }

    /**
     * @return void
     */
    private function notifyStateChangeCallbacks(int $processId, Process $process, string $newState, ProcessManager $manager)
    {
        foreach ($this->stateChangeCallbacks as $callback) {
            $callback($processId, $process, $newState, $manager);
        }
    }

    /**
     * @return void
     */
    private function notifyTickCallbacks(ProcessManager $manager)
    {
        foreach ($this->tickCallbacks as $callback) {
            $callback($manager);
        }
    }

    public function getErrorOutputs(): array
    {
        $errors = [];
        foreach ($this->processes as $process) {
            if ($process->getErrorOutput()) {
                $errors[$process->getName()] = $process->getErrorOutput();
            }
        }
        return $errors;
    }

    /**
     * @return void
     */
    public function validateProcesses()
    {
        $errors = [];
        foreach ($this->processes as $process) {
            if ($process->getExitCode() !== 0) {
                $errors[] = $process->getName();
            }
        }
        if ($errors) {
            throw new \Exception(sprintf('Error in Process%s %s', count($errors) > 1 ? 'es' : '', implode(', ', $errors)));
        }
    }

    public function getState(int $processId): string
    {
        if (isset($this->processStates[$processId])) {
            return $this->processStates[$processId];
        }
        throw new \InvalidArgumentException(sprintf('No Process found with id: %d', $processId));
    }
}
