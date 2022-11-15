<?php

namespace Converter;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use WebPConvert\WebPConvert;

class TranslationConverterCommand extends Command
{
    protected static $defaultName = 'convert_translation';

    protected function configure()
    {
        $this
            ->setDescription('Convert translation for translating.')
            ->addArgument('input', InputArgument::REQUIRED, 'A file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = realpath($input->getArgument('input'));
        $fileInfo = new \SplFileInfo($file);

        if ('json' === $fileInfo->getExtension()) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            // set header
            $r = 'A';
            foreach (['code', 'original', 'translate'] as $i) {
                $sheet->setCellValue("{$r}1", $i);
                ++$r;
            }

            // set content
            $json = \json_decode(\file_get_contents($fileInfo->getRealPath()), true);

            if (null === $json) {
                throw new \InvalidArgumentException("The file is a wrong json.");
            }

            $resolved = [];
            $this->walk($json, $resolved);

            $r = 2;
            foreach ($resolved as $k => $v) {
                $sheet->setCellValue("A{$r}", $k);
                $sheet->setCellValue("B{$r}", $v);
                ++$r;
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save("{$fileInfo->getFilename()}.xlsx");

            $io->success("Convert to xlsx successfully.");
        }

        if ('xlsx' === $fileInfo->getExtension()) {
            $reader =  new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $content = $reader->load($fileInfo->getRealPath());

            $translateContent = [];
            foreach ($content->getActiveSheet()->toArray() as $k => $v) {
                if (0 === $k) continue; // header
                [$code, $origin, $translate] = $v;

                $this->setWithKeyDot($code, (string) $translate, $translateContent);
            }

            \file_put_contents("{$fileInfo->getPath()}/translation.json", \json_encode($translateContent, \JSON_PRETTY_PRINT));

            $io->success("Convert to json successfully.");
        }

        return 0;
    }

    private function walk($data, array &$resolved, string $prefix = ''): void
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                if (\is_array($v)) {
                    $this->walk($v, $resolved, \trim("$prefix.$k", '.'));

                    continue;
                }

                $resolved["$prefix.$k"] = $v;
            }

            return;
        }

        $resolved[$prefix] = $data;
    }

    private function setWithKeyDot(string $k, string $v, array &$translateContent): void
    {
        $exploded = \explode('.', $k);

        $memoTranslateContent = &$translateContent;
        foreach ($exploded as $kk => $c) {
            if ($kk === \count($exploded) - 1) {
                $memoTranslateContent[$c] = $v;

                continue;
            }

            if (!\array_key_exists($c, $memoTranslateContent)) {
                $memoTranslateContent[$c] = [];
            }

            $memoTranslateContent = &$memoTranslateContent[$c];
        }
    }
}
