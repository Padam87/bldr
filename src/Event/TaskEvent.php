<?php

/**
 * This file is part of Bldr.io
 *
 * (c) Aaron Scherer <aequasi@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE
 */

namespace Bldr\Event;

use Bldr\Application;
use Bldr\Model\Task;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 */
class TaskEvent extends Event implements EventInterface
{
    /**
     * @var Application $application
     */
    private $application;

    /**
     * @var InputInterface $input
     */
    private $input;

    /**
     * @var Task $task
     */
    private $task;

    /**
     * @var Boolean $running
     */
    private $running;

    /**
     * @param Application    $application
     * @param InputInterface $input
     * @param Task           $task
     * @param Boolean        $running
     */
    public function __construct(Application $application, InputInterface $input, Task $task, $running = true)
    {
        $this->application = $application;
        $this->input       = $input;
        $this->task        = $task;
        $this->running     = $running;
    }

    /**
     * @return Application
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * @return InputInterface
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * @return Task
     */
    public function getTask()
    {
        return $this->task;
    }

    /**
     * @param Task $task
     */
    public function setTask(Task $task)
    {
        $this->task = $task;
    }

    /**
     * @return Boolean
     */
    public function isRunning()
    {
        return $this->running;
    }
}
