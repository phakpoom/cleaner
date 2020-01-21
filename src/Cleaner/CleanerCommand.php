<?php

namespace Cleaner;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Utils\Utils;

class CleanerCommand extends Command
{
    protected static $defaultName = 'clean';

    protected function configure()
    {
        $this
            ->setDescription('Clean Cache And All Fucking files.')
            ->addArgument('lookup', InputArgument::REQUIRED, 'Root dir for lookup');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dir = realpath($input->getArgument('lookup'));

        $allVarSymfonyFolder = (new Finder())->in($dir)->directories()->depth('< 2')->name('var');

        $fs = new Filesystem();
        $io = new SymfonyStyle($input, $output);

        $allSizeSave = 0;

        /** @var \SplFileInfo $folder */
        foreach ($allVarSymfonyFolder as $folder) {
            foreach (['sessions', 'log', 'cache/dev/profiler', 'cache/test'] as $subFolder) {
                $removingDir = $folder->getRealPath() . '/' . $subFolder;
                if ($fs->exists($removingDir)) {
                    if (0 !== $size = $this->removeRecursiveInFolder($removingDir)) {
                        $io->success(sprintf('Remove in "%s" -> %s',
                            $removingDir,
                            'Size ' . Utils::formatBytes($size)
                        ));

                        $allSizeSave += $size;
                    }
                }
            }

            foreach (['node_modules', 'web/assets'] as $subFolder) {
                $removingDir = realpath($folder->getRealPath() . '/..') . '/' . $subFolder;
                if ($fs->exists($removingDir)) {
                    $diffInDay = round((time() - filemtime($removingDir)) / 60 / 60 / 24);

                    if ($diffInDay <= 250) {
                        continue;
                    }

                    if (0 !== $size = $this->removeRecursiveInFolder($removingDir)) {
                        $io->success(sprintf('Remove in "%s" -> %s',
                            $removingDir,
                            'Size ' . Utils::formatBytes($size)
                        ));

                        $allSizeSave += $size;
                    }
                }
            }
        }

        if ($allSizeSave > 0) {
            $io->success(sprintf('Save!! %s',
                Utils::formatBytes($allSizeSave)
            ));
        }

        return 0;
    }

    protected function removeRecursiveInFolder(string $folder): int
    {
        $fileSize = 0;
        /** @var \SplFileInfo $f */
        foreach ((new Finder())->in($folder) as $f) {
            if ($f->isDir()) {
                $fileSize += $this->removeRecursiveInFolder($f->getRealPath());

                continue;
            }

            $fileSize += $f->getSize();

            (new Filesystem())->remove($f->getRealPath());
        }

        return $fileSize;
    }
}
