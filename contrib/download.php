<?php


namespace Deployer;

use Deployer\Host\Localhost;
use Deployer\Task\Context;
use Symfony\Component\Console\Input\InputOption;

option('source', null, InputOption::VALUE_REQUIRED, 'rsync source path');
option('destination', null, InputOption::VALUE_REQUIRED, 'rsync destination path');


desc('download remote->local');
task('download', function() {
    $source = input()->getOption('source');
    $destination = input()->getOption('destination');

    download($source, $destination);
});