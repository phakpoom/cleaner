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

        $lookup = Lookup::create($input, $output, self::FOLDER_NAME);

        /** @var \SplFileInfo $image */
        foreach ($lookup->files as $image) {
            try {
                WebPConvert::convert($image->getRealPath(), $lookup->folderBase . \explode('.', $image->getFilename())[0] . '.webp');
            } catch (\Exception $e) {
                $lookup->io->error(\sprintf('Convert %s error %s.', $image->getFilename(), $e->getMessage()));

                continue;
            }

            $lookup->io->success(\sprintf('Convert %s successfully.', $image->getFilename()));
        }

        return 0;
    }
}
