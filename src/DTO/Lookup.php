<?php

declare(strict_types=1);

namespace DTO;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class Lookup
{
    /** @var Filesystem */
    public $fs;

    /** @var SymfonyStyle */
    public $io;

    /** @var string */
    public $folderBase;

    /** @var array */
    public $files;

    public static function create(
        InputInterface  $input,
        OutputInterface $output,
        string          $folderBase,
        ?callable       $applyFinder = null,
        ?callable       $applyLookupIsFile = null
    ): self
    {
        $fs = new Filesystem();
        $io = new SymfonyStyle($input, $output);
        $lookup = $input->getArgument('lookup');
        if (!$fs->exists($lookup)) {
            throw new \LogicException("The \"%s\" does not exist.", $lookup);
        }

        if (is_dir($lookup)) {
            $folderBase = $lookup . '/' . $folderBase . '/';

            if (!$fs->exists($folderBase)) {
                $fs->mkdir($folderBase);
            }

            $files = (new Finder())
                ->in($lookup)
                ->depth('== 0')
                ->files()
                ->exclude($folderBase);

            if ($applyFinder) {
                $files = $applyFinder($files);
            }
        } else {
            // file
            $file = new \SplFileInfo($lookup);
            $folderBase = \dirname($file->getRealPath()) . '/';

            $files = [$file];

            if ($applyLookupIsFile) {
                $files = $applyLookupIsFile($file);
            }
        }

        $lookup = new self();
        $lookup->fs = $fs;
        $lookup->io = $io;
        $lookup->folderBase = $folderBase;
        $lookup->files = $files;

        return $lookup;
    }
}
