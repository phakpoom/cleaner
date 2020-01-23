<?php

namespace Resizer;

use Imagine\Filter\Basic\Autorotate;
use Imagine\Filter\Basic\WebOptimization;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Imagick\Imagine;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Utils\Utils;

class ResizeImageCommand extends Command
{
    const FOLDER_NAME = 'resized';

    protected static $defaultName = 'resize';

    protected function configure()
    {
        $this
            ->setDescription('Resize multiple images.')
            ->addArgument('lookup', InputArgument::REQUIRED, 'Root dir for lookup')
            ->addArgument('size', InputArgument::OPTIONAL, 'Size in Pixel')
            ->addOption('quality', 'quality', InputOption::VALUE_OPTIONAL, 'quality', 95);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dir = realpath($input->getArgument('lookup'));

        $images = (new Finder())
            ->in($dir)
            ->files()
            ->name('*.{png,jpg,jpeg,dif}')
            ->exclude(self::FOLDER_NAME);

        $fs = new Filesystem();
        $io = new SymfonyStyle($input, $output);

        /** @var \SplFileInfo $image */
        if (!empty($input->getArgument('size'))) {
            $size = explode('x', $input->getArgument('size'));
            $width = $size[0];
            $height = $size[1] ?? $width;
        }

        $folderBase = $dir . '/' . self::FOLDER_NAME . '/';

        if (!$fs->exists($folderBase)) {
            $fs->mkdir($folderBase);
        }

        $save = 0;
        foreach ($images as $image) {
            $imagine = new Imagine();
            $optimizer = OptimizerChainFactory::create()->useLogger(new ConsoleLogger($output));
            $beforeSize = $image->getSize();
            $imagineImage = $imagine->open($image->getRealPath());

            if (isset($width, $height)) {
                $imagineImage = $imagineImage->thumbnail((new Box($width, $height)), ImageInterface::THUMBNAIL_INSET);

                $imagineImage = (new Autorotate())->apply($imagineImage);
                $imagineImage = (new WebOptimization($newFileRealPath = $folderBase . $image->getBasename(), [
                    'quality' => (int) $input->getOption('quality'),
                ]))->apply($imagineImage);

                $imagineImage->getImagick()->clear();

                $optimizer->optimize($newFileRealPath);
            } else {
                $newFileRealPath = $folderBase . $image->getBasename();

                $optimizer->optimize($image->getRealPath(), $newFileRealPath);
            }

            $afterSize = filesize($newFileRealPath);
            $compare = $afterSize / $beforeSize * 100;
            $compare = round($compare, 1);

            $message = sprintf('Resize! %s | %s -> %s (%d%%)',
                $image->getFilename(),
                Utils::formatBytes($beforeSize),
                Utils::formatBytes($afterSize),
                $compare
            );

            $currentSave = $beforeSize - $afterSize;

            if ($currentSave < 0) {
                $io->warning($message);
            } else {
                $io->success($message);
            }

            $save += $currentSave;
        }

        if (0 < $save) {
            $io->success(sprintf('Save! %s',
                Utils::formatBytes($save)
            ));
        }

        return 0;
    }
}
