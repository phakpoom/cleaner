<?php

namespace Resizer;

use Imagine\Filter\Basic\Autorotate;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Imagick\Imagine;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Utils\Utils;

class ResizeImageCommand extends Command
{
    protected static $defaultName = 'resize';

    protected function configure()
    {
        $this
            ->setDescription('Resize multiple images.')
            ->addArgument('lookup', InputArgument::REQUIRED, 'Root dir for lookup')
            ->addArgument('size', InputArgument::REQUIRED, 'Size in Pixel');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dir = realpath($input->getArgument('lookup'));

        $images = (new Finder())->in($dir)->files();

        $fs = new Filesystem();
        $io = new SymfonyStyle($input, $output);

        /** @var \SplFileInfo $image */
        $size = explode('x', $input->getArgument('size'));
        $width = $size[0];
        $height = $size[1] ?? $width;
        $imagine = new Imagine();

        $folderBase = $dir . '/resized/';

        if (!$fs->exists($folderBase)) {
            $fs->mkdir($folderBase);
        }

        foreach ($images as $image) {
            $beforeSize = $image->getSize();
            $imagineImage = $imagine->open($image->getRealPath())
                ->thumbnail((new Box($width, $height)), ImageInterface::THUMBNAIL_INSET);

            $imagineImage = (new Autorotate())->apply($imagineImage);

            $imagineImage->save($newFileRealPath = $folderBase . $image->getBasename());

            $io->success(sprintf('Resize! %s | %s -> %s',
                $image->getFilename(),
                Utils::formatBytes($beforeSize),
                Utils::formatBytes(filesize($newFileRealPath))
            ));
        }

        return 0;
    }
}
