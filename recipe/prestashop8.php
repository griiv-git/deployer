<?php

declare(strict_types=1);

namespace Deployer;

use Symfony\Component\Console\Input\InputOption;
require 'recipe/common.php';
require 'contrib/rsync.php';


add('recipes', ['prestashop8']);

set('clear_paths', [
        'docker',
        'docker-compose.yml',
        '.gitignore',
        'www/composer.json',
        'www/composer.lock',
        'www/.gitignore']
);

// Shared files/dirs between deploys
set('shared_files', [
    'www/app/config/parameters.php',
    'www/.htaccess',
]);

set('shared_dirs', [
        'www/docs',
        'www/download',
        'www/img',
        'www/js',
        'www/localization',
        'www/mails/themes',
        'www/modules/ps_imageslider/images',
        'www/pdf',
        'www/tools',
        'www/translations',
        'www/upload',
        'www/var',
        'www/app/logs',
        'www/app/Resources/translations/',
        'www/modules/blockreassurance/img',
        'www/modules/blockreassurance/views/img',
        'www/themes/classic/translations/',
        'www/themes/child-theme/translations/',
    ]
);

// Writable dirs by web server
set('writable_dirs', [
        'www/app',
        'www/config',
        'www/config/xml',
        'www/download',
        'www/img',
        'www/mails/themes',
        'www/pdf',
        'www/translations',
        'www/upload',
        'www/var',
        'www/app/Resources/translations/',
        'www/modules/blockreassurance/img',
        'www/modules/blockreassurance/views/img',
        'www/themes/classic/translations/',
        'www/themes/child-theme/translations/',
        'www/themes/hummingbird/translations/',
    ]
);

//Task prestashop cache clear
task('prestashop:cache:clear', function(){
    if (test('[ -d {{deploy_path}}/current/www/var/cache/dev ]')) {
        writeln(run('cd {{deploy_path}}/current/www/ && {{bin/php}} bin/console cache:clear --env=dev'));
    }
    if (test('[ -d {{deploy_path}}/current/www/var/cache/prod ]')) {
        writeln(run('cd {{deploy_path}}/current/www/ && {{bin/php}} bin/console cache:clear --env=prod'));
    }

});

//Task prestashop cache warmum
task('prestashop:cache:warmup', function(){
    // warmup current mode
    // writeln(run('cd {{release_path}}/ && php bin/console cache:warmup'));
    // warmup both
    writeln(run('cd {{deploy_path}}/current/www && php bin/console cache:warmup --env=dev'));
    writeln(run('cd {{deploy_path}}/current/www && php bin/console cache:warmup --env=prod'));
});

//Task prestashop theme build
task('prestashop:theme:build', function(){
    //docker exec -i node16 bash -c "cd lemaitre/www/themes/hummingbird && npm run build"
    writeln('{{bin/docker}} exec -i {{docker_container_node}} bash -c "cd {{application}}/www/themes/{{theme}} && npm run build"');
    runLocally('{{bin/docker}} exec -i {{docker_container_node}} bash -c "cd {{application}}/www/themes/{{theme}} && npm run build"');
});


task('deploy:theme', function(){
    set('rsync_src', '{{prestashop_root}}/themes/{{theme}}/assets');
    set('rsync_dest', '{{release_path}}/www/themes/{{theme}}/assets');
    invoke('rsync');
});


// Tasks deploy vendrods
desc('Deploy your project');
task('deploy:vendors', function () {
    writeln('Installing vendors');
    writeln('{{release_or_current_path}}');
    writeln('cd {{release_or_current_path}}/www && {{bin/composer}} {{composer_action}} {{composer_options}} 2>&1');
    run('cd {{release_or_current_path}}/www && {{bin/composer}} {{composer_action}} {{composer_options}} 2>&1');
});

option('source', null, InputOption::VALUE_REQUIRED, 'rsync source path');
option('destination', null, InputOption::VALUE_REQUIRED, 'rsync destination path');

task('upload', function() {
    $source = input()->getOption('source');
    $destination = input()->getOption('destination');
    set('rsync_src', $source);
    set('rsync_dest', $destination);
    invoke('rsync');
})->desc('upload files or directory with rsync');

// Task deploy:preprare
desc('Prepares a new release');
task('deploy:prepare', [
    'vpn:check',
    'deploy:info',
    'deploy:setup',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:vendors',
    'deploy:shared',
    'deploy:writable',
]);

//task deploy
desc('Deploy');
task('deploy', [
    'deploy:prepare',
    'deploy:clear_paths',
    'deploy:publish',
    'prestashop:cache:clear',
    'prestashop:cache:warmup',
    'prestashop:theme:build',
    'deploy:theme'
]);



after('deploy:failed', 'deploy:unlock');