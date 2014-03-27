<?php

/**
 * This file is part of Bldr.io
 *
 * (c) Aaron Scherer <aequasi@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE
 */

namespace Bldr\Extension\Filesystem\Call;

use Symfony\Component\Console\Helper\FormatterHelper;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 */
class RemoveCall extends FilesystemCall
{
    /**
     * Runs the command
     *
     * @param array $arguments
     *
     * @throws \Exception
     * @return mixed
     */
    public function run()
    {
        foreach ($arguments as $file) {
            if (!$this->fileSystem->exists($file)) {
                if ($this->failOnError) {
                    throw new \Exception("File `$file` does not exist.");
                }

                continue;
            }

            $this->fileSystem->remove([$file]);

            /** @var FormatterHelper $formatter */
            $formatter = $this->helperSet->get('formatter');
            $this->output->writeln(
                $formatter->formatSection(
                    $this->task->getName(),
                    "Deleting $file"
                )
            );
        }

        return true;
    }
}
