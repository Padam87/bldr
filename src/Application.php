<?php

/**
 * This file is part of Bldr.io
 *
 * (c) Aaron Scherer <aequasi@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE
 */

namespace Bldr;

use Bldr\Command as Commands;
use Bldr\DependencyInjection\AbstractExtension;
use Bldr\DependencyInjection\ContainerBuilder;
use Bldr\Helper\DialogHelper;
use Dflydev\EmbeddedComposer\Core\EmbeddedComposerAwareInterface;
use Dflydev\EmbeddedComposer\Core\EmbeddedComposerInterface;
use Dflydev\EmbeddedComposer\Console\Command as Composer;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

class Application extends BaseApplication implements EmbeddedComposerAwareInterface
{
    /**
     * @var string $BUILD_NAME
     */
    public static $BUILD_NAME;

    public static $logo = <<<EOF
  ______    __       _______   ______
 |   _  \  |  |     |       \ |   _  \
 |  |_)  | |  |     |  .--.  ||  |_)  |
 |   _  <  |  |     |  |  |  ||      /
 |  |_)  | |  `----.|  `--`  ||  |\  \
 |______/  |_______||_______/ | _| `._|
EOF;

    /**
     * @var ContainerInterface $container
     */
    private $container;

    /**
     * @var EmbeddedComposerInterface $embeddedComposer
     */
    private $embeddedComposer;

    /**
     * @return Application
     */
    public static function create(EmbeddedComposerInterface $embeddedComposer)
    {
        return new static($embeddedComposer);
    }

    /**
     * @param string $name
     * @param string $version
     */
    public function __construct(EmbeddedComposerInterface $embeddedComposer)
    {
        $this->embeddedComposer = $embeddedComposer;

        $version = '@package_version@';
        if ($version === '@'.'package_version@') {
            $version = $embeddedComposer->findPackage('bldr-io/bldr')->getPrettyVersion();
        }

        parent::__construct('Bldr', $version);

        $this->buildContainer();

        $this->addCommands($this->getCommands());

        $this->run($this->container->get('input'), $this->container->get('output'));
    }

    /**
     * @return Command[]
     */
    public function getCommands()
    {
        $commands = [
            new Commands\InitCommand(),
            new Commands\BuildCommand(),
            new Commands\Task\ListCommand(),
            new Commands\Task\InfoCommand(),
            new Composer\DumpAutoloadCommand(''),
            new Composer\InstallCommand(''),
            new Composer\UpdateCommand(''),
        ];

        return $commands;
    }

    /**
     * @todo Fix config references
     */
    public function setBuildName()
    {
        $date = new \DateTime('now');

        if (getenv('TRAVIS') === 'true') {
            $name = sprintf(
                "travis_%s",
                getenv('TRAVIS_JOB_NUMBER')
            );
        } else {
            $name = sprintf(
                'local_%s_%s',
                str_replace('/', '_', $this->container->getParameter('name')),
                $date->format("Y-m-d_H-i-s")
            );
        }

        static::$BUILD_NAME = $name;
    }

    /**
     * {@inheritDoc}
     *
     * @codeCoverageIgnore
     */
    public function getHelp()
    {
        return "\n".self::$logo."\n\n".parent::getHelp();
    }

    /**
     * Loads the config for the necessary commands, and sets the container for classes that need it.
     *
     * @param Command         $command
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|void
     */
    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output)
    {
        if ($command instanceof ContainerAwareInterface) {
            $command->setContainer($this->container);
        }

        parent::doRunCommand($command, $input, $output);
    }

    /**
     * Builds the container with extensions
     *
     * @throws InvalidArgumentException
     */
    private function buildContainer()
    {
        $this->container = new ContainerBuilder();

        return $this->container;
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultHelperSet()
    {
        $helperSet = parent::getDefaultHelperSet();

        $helperSet->set(new DialogHelper());

        return $helperSet;
    }

    /**
     * @return EmbeddedComposerInterface
     */
    public function getEmbeddedComposer()
    {
        return $this->embeddedComposer;
    }
}
