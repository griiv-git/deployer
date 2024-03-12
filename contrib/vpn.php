<?php

namespace Deployer;


task('vpn:check', function() {
    writeln('vpn connection : {{need_vpn_connection}}');
    if (get('need_vpn_connection')) {
        writeln('VPN connection is needed');
        askConfirmation('Connection to VPN needed, continue ?', false);
    }

})->desc('Check if environnement need VPN connection');