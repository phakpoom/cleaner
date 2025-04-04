#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new \Cleaner\CleanerCommand());
$application->add(new \Resizer\ResizeImageCommand());
$application->add(new \Animation\RenameFileNameForAnimationCommand());
$application->add(new \Converter\WebPConverterCommand());
$application->add(new \Converter\PngToJpgConverterCommand());
$application->add(new \Converter\TranslationConverterCommand());

$application->run();
