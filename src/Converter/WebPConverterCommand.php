<?php

namespace Converter;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\WebPConvert;

class WebPConverterCommand extends Command
{
    const FOLDER_NAME = 'webp';

    protected static $defaultName = 'convert_webp';

    protected function configure()
    {
        $this
            ->setDescription('Convert multiple images to webp.')
            ->addArgument('lookup', InputArgument::REQUIRED, 'Root dir or file for lookup');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!\shell_exec(sprintf("which %s", \escapeshellarg('cwebp')))) {
            throw new \LogicException('Please install `cwebp` before use this command.');
        }

        $fs = new Filesystem();
        $io = new SymfonyStyle($input, $output);
        $lookup = $input->getArgument('lookup');

        if (!$fs->exists($lookup)) {
            $io->error(sprintf("The \"%s\" does not exist.", $lookup));

            return 1;
        }

        if (is_dir($lookup)) {
            $folderBase = $lookup . '/' . self::FOLDER_NAME . '/';

            if (!$fs->exists($folderBase)) {
                $fs->mkdir($folderBase);
            }

            $images = (new Finder())
                ->in($lookup)
                ->files()
                ->exclude(self::FOLDER_NAME);
        } else {
            // file
            $img = new \SplFileInfo($lookup);
            $folderBase = \dirname($img->getRealPath()) . '/';

            $images = [$img];
        }

        /** @var \SplFileInfo $image */
        foreach ($images as $image) {
            try {
                WebPConvert::convert($image->getRealPath(), $folderBase . \explode('.', $image->getFilename())[0] . '.webp');
            } catch (ConversionFailedException $e) {
                $io->error($e->getMessage());

                continue;
            }

            $io->success(\sprintf('Convert %s successfully.', $image->getFilename()));
        }

        return 0;
    }
}
