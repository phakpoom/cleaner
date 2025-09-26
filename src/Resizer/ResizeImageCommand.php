<?php

namespace Resizer;

use Converter\WebPConverterCommand;
use DTO\Lookup;
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
            ->addArgument('lookup', InputArgument::REQUIRED, 'Root dir or file for lookup')
            ->addArgument('size', InputArgument::OPTIONAL, 'Size in Pixel')
            ->addOption('quality', 'quality', InputOption::VALUE_OPTIONAL, 'quality', 95)
            ->addOption('include_bigger', 'include_bigger', InputOption::VALUE_NONE, 'include_bigger')
            ->addOption('pattern', 'pattern', InputOption::VALUE_OPTIONAL, '', '*.{png,jpg,jpeg,gif,JPG,JPEG}')
            ->addOption('name', 'name', InputOption::VALUE_OPTIONAL, 'new_file_name_COUNT *** COUNT = index file')
            ->addOption('webp', 'webp', InputOption::VALUE_NONE, 'and convert to webp.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $lookup = Lookup::create(
            $input,
            $output,
            self::FOLDER_NAME,
            function (Finder $finder) use ($input) {
                return $finder->name($input->getOption('pattern'));
            },
            function (\SplFileInfo $img) {
                \copy($img->getRealPath(), \dirname($img->getRealPath()) . '/original_' . $img->getFilename());

                return [$img];
            }
        );

        /** @var \SplFileInfo $image */
        if (!empty($input->getArgument('size'))) {
            $size = explode('x', $input->getArgument('size'));
            $width = $size[0];
            $height = $size[1] ?? $width;
        }

        $save = 0;
        $counting = 0;
        foreach ($lookup->files as $image) {
            $counting++;
            $imagine = new Imagine();
            $optimizer = OptimizerChainFactory::create()->useLogger(new ConsoleLogger($output));
            $beforeSize = $image->getSize();
            $imagineImage = $imagine->open($image->getRealPath());
            $isGif = 'gif' === $image->getExtension();

            $newFileRealPath = $this->getNewFileName($image, $input, $lookup->folderBase, $counting);

            if (isset($width, $height)) {
                $imagineImage = $imagineImage
                    ->thumbnail((new Box($width, $height)), ImageInterface::THUMBNAIL_INSET);

                if ($isGif) {
                    $imagineImage->save($newFileRealPath, [
                        'animated' => true,
                    ]);
                } else {
                     $imagineImage = (new Autorotate())->apply($imagineImage);
                     $imagineImage = (new WebOptimization($newFileRealPath, [
                        'quality' => (int)$input->getOption('quality'),
                    ]))->apply($imagineImage);
                }

                $imagineImage->getImagick()->clear();

                $optimizer->optimize($newFileRealPath);
            } else {
                $optimizer->optimize($image->getRealPath(), $newFileRealPath);
            }

            if ($input->getOption('webp')) {
                WebPConverterCommand::doConvert($input, $output, $newFileRealPath);
            }

            $afterSize = filesize($newFileRealPath);
            if (!$input->getOption('include_bigger') && $afterSize > $beforeSize) {
                $lookup->fs->remove($newFileRealPath);

                continue;
            }

            $compare = 100 - ($afterSize / $beforeSize * 100);
            $compare = round($compare, 1);

            $message = sprintf('Resize! %s | %s -> %s (%d%%)',
                $image->getFilename(),
                Utils::formatBytes($beforeSize),
                Utils::formatBytes($afterSize),
                $compare
            );

            $currentSave = $beforeSize - $afterSize;

            if ($currentSave < 0) {
                $lookup->io->warning($message);
            } else {
                $lookup->io->success($message);
            }

            $save += $currentSave;
        }

        if (0 < $save) {
            $lookup->io->success(sprintf('Save! %s',
                Utils::formatBytes($save)
            ));
        }

        return 0;
    }

    private function getNewFileName(\SplFileInfo $image, InputInterface $input, string $folderBase, int $c)
    {
        $fileName = $image->getBasename();
        if ($input->getOption('name')) {
            if (false === \strpos($input->getOption('name'), 'COUNT')) {
                throw new \InvalidArgumentException('Option name must be `COUNT`');
            }
            $fileName = (\str_replace('COUNT', $c, $input->getOption('name'))) . '.' . $image->getExtension();
        }

        return $newFileRealPath = $folderBase . $fileName;
    }
}
