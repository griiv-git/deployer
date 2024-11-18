<?php

namespace Deployer;


use Symfony\Component\HttpFoundation\File\File;

function getDbConf($root_path, $prestashop_version) {
    $db_conf = [];
    if ($prestashop_version === '1.7' || $prestashop_version === '8') {
        $command = "echo(json_encode(require('app/config/parameters.php')));";
    } else {
        $command = "";
    }
    $fullCommand = sprintf('cd %s && %s -r "%s"', $root_path, get('bin/php'), $command);
    //writeln($fullCommand);
    $ret = run($fullCommand);
    return json_decode($ret, true);

}

function getDbConnectionConf($root_path, $prestashop_version) {
    $data = getDbConf($root_path, $prestashop_version);
    if (isset($data['parameters'])) {
        $data = $data['parameters'];

        $host = $data['database_host'];
        $user = $data['database_user'];
        $password = $data['database_password'];
        $database = $data['database_name'];
        $port = null;

        if (isset($conf['database_port'])) {
            $port = $data['database_port'];
        }

        return [
            'host' => $host,
            'user' => $user,
            'password' => $password,
            'database' => $database,
            'port' => $port
        ];
    }

    return [];
}

function execute($query, $user, $password, $db, $host = null, $port = null) {
    $command = 'mysql';

    if (isset($conf['host'])) {
        $command = sprintf("%s -h%s", $command, $host);
    }

    if (isset($conf['port'])) {
        $command = sprintf("%s -P%s", $command, $port);
    }

    $runCommand = sprintf("echo %s | %s -u%s -p%s %s", $query, $command, $user, $password, $db);

    return run($runCommand);
}

task('db:console', function() {
    $conf = getDbConnectionConf(get('WEBAPP_ROOT_PATH'), get('prestashop_version'));
    error_log(PHP_EOL . var_export(debug_print_backtrace(), true), 3,'/home/arnaud/projets/lemaitre/www/deployer/deployer.log');
    if (isset($conf['user'])) {
        $command = "mysql";

        $user = $conf['user'];
        $host = $conf['host'];
        $password = $conf['password'];
        $database = $conf['database'];
        $port = $conf['port'];

        if (isset($conf['host'])) {
            $command = sprintf("%s -h%s", $command, $host);
        }

        if (isset($conf['port'])) {
            $command = sprintf("%s -P%s", $command, $port);
        }
    }
    $command = sprintf("%s -u%s -p%s %s", $command, $user, $password, $database);

    $commandMysql = $command;



    $key = sprintf('-i %s', get('identity_file', ''));
    $port = get('port',22);
    $user = get('remote_user', '');
    $host = get('hostname', '');

    $command = sprintf("ssh -tty %s -p %s %s@%s \"%s\"", $key, $port, $user, $host, $commandMysql);
    writeln($command);

    $empty1=array();
    $empty2=array();
    /*$proc=proc_open($command, $empty1, $empty2);
    $ret = proc_close($proc);*/

    //passthru($command);

})->desc('Open database console');

task('db:dump', function() {
    $conf = getDbConnectionConf(get('WEBAPP_ROOT_PATH'), get('prestashop_version'));

    if (isset($conf['user'])) {
        $user = $conf['user'];
        $host = $conf['host'];
        $password = $conf['password'];
        $database = $conf['database'];
        $port = $conf['port'];

        $mysqlDump = "mysqldump";
        if (isset($conf['host'])) {
            $mysqlDump = sprintf("%s -h%s", $mysqlDump, $host);
        }

        if (isset($conf['port'])) {
            $mysqlDump = sprintf("%s -P%s", $mysqlDump, $port);
        }

        $currentHost = currentHost()->getAlias();

        $command = sprintf("%s -u%s -p%s %s | gzip > %s.%s.sql.gz", $mysqlDump, $user, $password, $database, $currentHost, date('Y-m-d'));
        writeln($command);

        $dumpDbPath = get('deploy_path') . "/dump";

        if (!test('[ -d ' . $dumpDbPath . ' ]')) {
            run('mkdir ' . $dumpDbPath);
        }

        cd($dumpDbPath);

        run($command);
    }
})->desc('Dump database');

task('db:fetch', function() {
    $currentHost = currentHost()->getAlias();
    $dumpFile = sprintf("%s.%s.sql.gz",$currentHost, date("Y-m-d"));
    $dumpDbPath = get('deploy_path') . "/dump";

    if (test('[ -f ' . $dumpDbPath . '/' . $dumpFile . ' ]')) {
        //download($dumpDbPath . '/' . $dumpFile, $dumpFile);
        writeln("file $dumpFile exist...download");
        ///home/arnaud/projets/lemaitre/www
        $localWebRootPath = getenv('CURRENT_PROJECT_PATH');
        writeln($localWebRootPath);
        download($dumpDbPath . '/' . $dumpFile, $localWebRootPath . '/../dump');
    } else {
        $message = $dumpFile . ' not exist';
        $message = "<fg=red;options=bold>Error $message</>";
        writeln($message);
        return;
    }
})->desc('Fetch database');

task('db:restore', function() {
    $conf = getDbConnectionConf(get('WEBAPP_ROOT_PATH'), get('prestashop_version'));
    $source = input()->getOption('source');
    $fileSource = new File($source);

    $destination = get('deploy_path') . "/dump";
    upload($source, $destination);

    if (!test('[ -f ' . $destination . DIRECTORY_SEPARATOR . $fileSource->getFilename() .' ]')) {
        $message = $destination . DIRECTORY_SEPARATOR . $fileSource->getFilename() . ' not exist';
        $message = "<fg=red;options=bold>Error $message</>";
        writeln($message);
        return;
    }

    if (isset($conf['user'])) {
        $user = $conf['user'];
        $host = $conf['host'];
        $password = $conf['password'];
        $database = $conf['database'];
        $port = $conf['port'];

        $dropDatabaseQuery = sprintf("DROP DATABASE %s", $database);
        $createDatabaseQuery = sprintf('CREATE DATABASE %s', $database);

        info(sprintf("Dropping database %s", $database));
        execute("SHOW DATABASES", $user, $password, $database, $host, $port);

        info(sprintf("Creating database %s", $database));
        execute("SHOW DATABASES", $user, $password, $database, $host, $port);

    }


    //clean db
    //execute("DROP DATABASE %s" % (db), user, password, '', host)
    //        puts('Recreating database %s' % (db))
    //        execute("CREATE DATABASE %s" % (db), user, password, '', host)




    //import gz sql file

    //dump_file = os.path.expanduser(dump_file)
    //    gz = False
    //
    //    if dump_file.endswith('gz'):
    //        gz = True

    //                if gz == True:
    //                    restore_command = "gunzip -c %s | %s -u%s -p%s %s" % (filename, command, pipes.quote(user), pipes.quote(password), db)
    //                else:
    //                    restore_command = "%s -u%s -p%s %s < %s" % (command, pipes.quote(user), pipes.quote(password), db, filename)




})->desc('Restore database');


