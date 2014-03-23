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
use Bldr\Helper\DialogHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 */
class InitCommand extends Command
{
    /**
     * @var array $gitConfig
     */
    private $gitConfig;

    /**
     * @var array $tasks
     */
    private $tasks = [];

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $config = Application::$CONFIG;

        $this->setName('init')
            ->setDescription("Builds the project {$config} file for a project.")
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name of the package')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Description of the package')
            ->addOption('delete', 'd', InputOption::VALUE_NONE, "Delete existing {$config}")
            ->addOption('dist', null, InputOption::VALUE_NONE, "Create {$config}.dist file")
            ->setHelp(
                <<<EOF

The <info>%command.name%</info> builds the {$config} file in the root directory.

To use:

    <info>$ bldr %command.full_name%</info>

To delete the existing file:

    <info>$ bldr %command.full_name% -d</info>
    <info>$ bldr %command.full_name% --delete</info>

To create a dist file:

    <info>$ bldr %command.full_name% --dist</info>

EOF
            );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = Application::$CONFIG . ($input->getOption('dist') ? '.dist' : '');

        /** @var DialogHelper $dialog */
        $dialog = $this->getHelper('dialog');

        $whitelist = ['name', 'description'];
        $options   = array_filter(array_intersect_key($input->getOptions(), array_flip($whitelist)));

        if ($dialog->askConfirmation(
            $output,
            $dialog->getQuestion('Would you like to define your profiles?', 'yes', '?'),
            true
        )
        ) {
            $options['profiles'] = $this->determineProfiles($input, $output);
            foreach ($this->tasks as $taskName => $task) {
                $options['tasks'][$taskName] = array_merge_recursive($task, ['calls' => []]);
            }
        }

        $yaml = Yaml::dump($options, 12);
        file_put_contents(getcwd() . '/' . $config, $yaml);

        $output->writeln(
            [
                "",
                $config . " file generated.",
                "",
                $yaml
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $config = Application::$CONFIG . ($input->getOption('dist') ? '.dist' : '');


        $dir = getcwd();
        if (file_exists($dir . '/' . $config)) {
            if (!$input->getOption('delete')) {
                throw new \Exception(
                    "You already have a {$config} file. Delete it first or run with the -d flag."
                );
            }

            unlink($dir . '/' . $config);
        }

        /** @var DialogHelper $dialog */
        $dialog = $this->getHelper('dialog');
        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');

        $output->writeln(
            [
                '',
                $formatter->formatBlock('Welcome to the Bldr.io config generator', 'bg=blue;fg=white', true),
                ''
            ]
        );

        $output->writeln("Attempting to create a {$config} file for you. Follow along!");

        $this->getNameOption($output, $input);

        $description = $input->getOption('description') ? : false;
        $input->setOption(
            'description',
            $dialog->ask($output, $dialog->getQuestion('Description', $description), $description)
        );
    }

    /**
     * @param OutputInterface $output
     * @param InputInterface  $input
     */
    private function getNameOption(OutputInterface $output, InputInterface $input)
    {
        $git = $this->getGitConfig();
        /** @var DialogHelper $dialog */
        $dialog = $this->getHelperSet()
            ->get('dialog');

        if (!$name = $input->getOption('name')) {
            $name = $this->getPackageName();
        }

        $name = $dialog->ask($output, $dialog->getQuestion('Project Name (<vendor>/<name>)', $name), $name);
        $input->setOption('name', $name);
    }

    /**
     * @return string
     */
    private function getPackageName()
    {
        $name = basename(getcwd());
        $name = preg_replace('{(?:([a-z])([A-Z])|([A-Z])([A-Z][a-z]))}', '\\1\\3-\\2\\4', $name);
        $name = strtolower($name);
        if (isset($git['github.user'])) {
            return $git['github.user'] . '/' . $name;
        } elseif (!empty($_SERVER['USERNAME'])) {
            return $_SERVER['USERNAME'] . '/' . $name;
        } elseif (get_current_user()) {
            return get_current_user() . '/' . $name;
        } else {
            return $name . '/' . $name;
        }
    }

    /**
     * @return array
     * @codeCoverageIgnore
     */
    private function getGitConfig()
    {
        $finder = new ExecutableFinder();
        $gitBin = $finder->find('git');

        $cmd = new Process(sprintf('%s config -l', escapeshellarg($gitBin)));
        $cmd->run();

        if ($cmd->isSuccessful()) {
            $this->gitConfig = array();
            preg_match_all('{^([^=]+)=(.*)$}m', $cmd->getOutput(), $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $this->gitConfig[$match[1]] = $match[2];
            }

            $this->gitConfig;
        }

        return array();
    }

    private function determineProfiles(InputInterface $input, OutputInterface $output)
    {
        /** @var DialogHelper $dialog */
        $dialog = $this->getHelper('dialog');

        $output->writeln(["", "Defining Profiles", ""]);

        $profiles = [];
        do {
            $profile = [];

            $name = $dialog->ask($output, $dialog->getQuestion('Profile Name <default>', 'default'), 'default');
            if ($name === 'default' && array_key_exists('default', $profiles)) {
                break;
            }
            $description = $dialog->ask($output, $dialog->getQuestion('Description', false), false);
            if ($description !== false) {
                $profile['description'] = $description;
            }

            $output->writeln(["", "Task Ordering"]);
            $tasks = [];
            do {
                $task = [];

                $taskName = $dialog->ask($output, $dialog->getQuestion('Task name', false), false);
                if ($taskName === false) {
                    break;
                }
                $desc = $dialog->ask($output, $dialog->getQuestion('Task Description', false), false);
                if ($desc !== false) {
                    $task['description'] = $desc;
                }

                $this->tasks[$taskName] = $task;
                $tasks[]                = $taskName;
            } while (true);
            if (!empty($tasks)) {
                $profile['tasks'] = $tasks;
            }

            $profiles[$name] = $profile;
        } while (true);

        return $profiles;
    }
}

