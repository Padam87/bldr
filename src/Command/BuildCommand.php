<?php

/**
 * This file is part of Bldr.io
 *
 * (c) Aaron Scherer <aequasi@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE
 */

namespace Bldr\Command;

use Bldr\Application;
use Bldr\Call\CallInterface;
use Bldr\Helper\DialogHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 */
class BuildCommand extends Command implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface|ContainerBuilder $container
     */
    private $container;

    /**
     * Sets the Container.
     *
     * @param ContainerInterface|null $container A ContainerInterface instance or null
     *
     * @api
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->setName('build')
            ->setDescription("Builds the project for the directory you are in. Must contain a .bldr.yml file.")
            ->addOption('profile', 'p', InputOption::VALUE_REQUIRED, 'Profile to run', 'default')
            ->addOption('tasks', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Tasks to run')
            ->setHelp(
                <<<EOF

The <info>%command.name%</info> builds the current project, using the .bldr.yml file in the root directory.

To use:

    <info>$ bldr %command.full_name% </info>

To specify a profile:

    <info>$ bldr %command.full_name% profile_name</info>

To specify tasks to run:

    <info>$ bldr %command.full_name% --tasks=task_name</info>
    <info>$ bldr %command.full_name% --tasks=task_name -t second_task</info>
    <info>$ bldr %command.full_name% --tasks=task_name,second_task</info>

EOF
            );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(["\n", Application::$logo, "\n"]);

        /** @var ParameterBag $config */
        $config      = $this->getApplication()->getConfig();
        if ([] === $tasks = $input->getOption('tasks')) {

            $profileName = $input->getOption('profile');
            $profile     = $config->get('profiles')[$profileName];
            $tasks       = $profile['tasks'];

            /** @var DialogHelper $dialog */
            $dialog = $this->getHelper('dialog');
            /** @var FormatterHelper $formatter */
            $formatter = $this->getHelper('formatter');

            $output->writeln(
                [
                    "",
                    $formatter->formatBlock(
                        [
                            sprintf("Building the '%s' project", $config->get('name')),
                            sprintf(" - %s - ", $config->get('description'))
                        ],
                        'bg=blue;fg=white',
                        true
                    ),
                    "",
                    $formatter->formatBlock(
                        [
                            sprintf("Using the '%s' profile", $profileName),
                            sprintf(" - %s - ", $profile['description'])
                        ],
                        'bg=green;fg=white',
                        true
                    ),
                    ""
                ]
            );
        } else {
            if (sizeof($tasks) === 1 && strpos($tasks[0], ',') !== false) {
                $tasks = explode(',', $tasks[0]);
            }
        }

        try {
            $this->runTasks($input, $output, $tasks);
        } catch (\Exception $e) {
            return $this->failBuild($input, $output, $e);
        }

        return $this->succeedBuild($input, $output);
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param array           $tasks
     */
    private function runTasks(InputInterface $input, OutputInterface $output, array $tasks)
    {
        foreach ($tasks as $task) {
            $this->runTask($input, $output, $task);
        }
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param string          $taskName
     *
     * @throws \Exception
     */
    private function runTask(InputInterface $input, OutputInterface $output, $taskName)
    {
        /** @var ParameterBag $config */
        $config = $this->getApplication()
            ->getConfig();
        $task   = $config->get('tasks')[$taskName];

        /** @var DialogHelper $dialog */
        $dialog = $this->getHelper('dialog');
        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');

        $output->writeln(
            [
                "",
                sprintf(
                    "<info>Running the %s task</info>\n<comment>%s</comment>",
                    $taskName,
                    isset($task['description']) ? '> ' . $task['description'] : ''
                ),
                ""
            ]
        );

        foreach ($task['calls'] as $call) {
            $services = array_keys($this->container->findTaggedServiceIds($call['type']));
            if (sizeof($services) > 1) {
                throw new \Exception("Multiple calls exist with the 'exec' tag.");
            }
            if (sizeof($services) === 0) {
                throw new \Exception("No task type found for {$call['type']}.");
            }

            /** @var CallInterface $service */
            $service = $this->container->get($services[0]);
            $service->initialize($input, $output, $this->getHelperSet(), $config);
            $service->setTask($taskName, $task);
            $service->setFailOnError(isset($call['failOnError']) ? $call['failOnError'] : false);
            $service->setSuccessStatusCodes(isset($call['successCodes']) ? $call['successCodes'] : [0]);

            if (method_exists($service, 'setFileset') && isset($call['fileset'])) {
                $service->setFileset($call['fileset']);
            }

            $service->run($call['arguments']);
            $output->writeln("");
        }
        $output->writeln("");
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param \Exception      $exception
     *
     * @throws \Exception
     */
    private function failBuild(InputInterface $input, OutputInterface $output, \Exception $exception)
    {
        throw $exception;
    }

    public function succeedBuild(InputInterface $input, OutputInterface $output)
    {
        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');

        $output->writeln(
            [
                "",
                $formatter->formatBlock(
                    [
                        "",
                        "Build Success!",
                        ""
                    ],
                    'bg=green;fg=white'
                ),
                ""
            ]
        );

        return 0;
    }
}

