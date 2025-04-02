<?php

namespace Converter;

use DTO\Lookup;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\WebPConvert;

class PngToJpgConverterCommand extends Command
{
    const FOLDER_NAME = 'jpg';
    protected static $defaultName = 'convert_png_to_jpg';

    protected function configure()
    {
        $this
            ->setDescription('Convert multiple png images to jpg.')
            ->addArgument('lookup', InputArgument::REQUIRED, 'Root dir or file for lookup');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $lookup = Lookup::create($input, $output, self::FOLDER_NAME);

        /** @var \SplFileInfo $f */
        foreach ($lookup->files as $f) {
            $image = \imagecreatefrompng($f->getRealPath());

            $bg = \imagecreatetruecolor(\imagesx($image), \imagesy($image));
            $white = \imagecolorallocate($bg, 255, 255, 255);
            \imagefill($bg, 0, 0, $white);
            imagecopy($bg, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

            \imagejpeg($bg, $lookup->folderBase . \explode('.', $f->getFilename())[0] . '.jpg', 90);

            \imagedestroy($image);
            \imagedestroy($bg);

            $lookup->io->success(\sprintf('Convert %s successfully.', $f->getFilename()));
        }

        return 0;
    }
}
