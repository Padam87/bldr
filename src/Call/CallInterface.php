<?php

/**
 * This file is part of Bldr.io
 *
 * (c) Aaron Scherer <aequasi@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE
 */

namespace Bldr\Call;

use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @author Aaron Scherer <aaron@undergroundelephant.com>
 */
interface CallInterface
{
    /**
     * Runs the command
     *
     * @param array $arguments
     *
     * @return mixed
     */
    public function run(array $arguments);

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param HelperSet       $helperSet
     * @param ParameterBag    $config
     *
     * @return CallInterface
     */
    public function initialize(
        InputInterface $input,
        OutputInterface $output,
        HelperSet $helperSet,
        ParameterBag $config
    );

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return CallInterface
     */
    public function setTask($name, array $arguments);

    /**
     * @param Boolean $fail
     *
     * @return CallInterface
     */
    public function setFailOnError($fail);

    /**
     * @param integer[] $codes
     *
     * @return CallInterface
     */
    public function setSuccessStatusCodes(array $codes);
}
