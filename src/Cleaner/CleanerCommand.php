<?php

namespace Cleaner;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

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
        $dir = $input->getArgument('lookup');

        $dir = realpath($dir);

        $allVarSymfonyFolder = (new Finder())->in($dir)->directories()->depth('< 2')->name('var');

        $fs = new Filesystem();
        $io = new SymfonyStyle($input, $output);

        /** @var \SplFileInfo $folder */
        foreach ($allVarSymfonyFolder as $folder) {
            foreach (['sessions', 'log', 'cache/dev/profiler', 'cache/test'] as $subFolder) {
                $removingDir = $folder->getRealPath() . '/' . $subFolder;
                if ($fs->exists($removingDir)) {
                    if (0 !== $size = $this->removeRecursiveInFolder($removingDir)) {
                        $io->success(sprintf('Remove in "%s" -> %s',
                            $removingDir,
                            'Size ' . $this->formatBytes($size)
                        ));
                    }
                }
            }
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

    protected function formatBytes($size, $precision = 2): string
    {
        $base = log($size, 1024);
        $suffixes = array('', 'K', 'M', 'G', 'T');

        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    }
}
