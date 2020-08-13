<?php

namespace Animation;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class RenameFileNameForAnimationCommand extends Command
{
    protected static $defaultName = 'animation:rename_file';

    protected function configure()
    {
        $this
            ->setDescription('Rename image and json file for animation.')
            ->addArgument('lookup', InputArgument::REQUIRED, 'Root dir for lookup')
            ->addArgument('prefix_image_path', InputArgument::REQUIRED, 'Prefix image for web path')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dir = realpath($input->getArgument('lookup'));

        $fs = new Filesystem();
        $io = new SymfonyStyle($input, $output);

        $dirInfo = new \SplFileInfo($dir);
        $dirName = $dirInfo->getFilename();

        $jsonFile = $dir . "/$dirName.json";

        if (!$fs->exists($jsonFile)) {
            throw new FileNotFoundException();
        }

        $jsonFileContent = file_get_contents($jsonFile);

        // change images
        /** @var \SplFileInfo $f */
        $changed = [];
        foreach ((new Finder())->in($dir . '/images') as $f) {
            $fileNamePrefix = $dirName . '-';
            $oldFileName = str_replace($fileNamePrefix, '', $f->getFilename());
            $newFileName = $fileNamePrefix . $oldFileName;

            $newFilePath = $f->getPath() . '/' .  $newFileName;
            if (!$fs->exists($newFilePath)) {
                $fs->rename($f->getRealPath(), $newFilePath);
            }

            $changed[] = [
                $oldFileName,
                $newFileName
            ];
        }

        foreach ($changed as $value) {
            [$from, $to] = $value;

            $jsonFileContent = str_replace($from, $to, $jsonFileContent);
        }

        $jsonFileContent = str_replace('images/', ltrim($input->getArgument('prefix_image_path'), '\/'), $jsonFileContent);

        file_put_contents($jsonFile, $jsonFileContent);

        $io->success('Success');

        return 0;
    }
}
