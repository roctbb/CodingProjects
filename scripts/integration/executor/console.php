<?php
require __DIR__.'/bootstrap.php';
[$app,$kernel]=executorQaApp(true);
$status=$kernel->handle(new Symfony\Component\Console\Input\ArgvInput,new Symfony\Component\Console\Output\ConsoleOutput);
$kernel->terminate(new Symfony\Component\Console\Input\ArgvInput,$status);exit($status);
