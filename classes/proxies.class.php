<?php

declare(strict_types=1);

//Useful: http://www.robtex.com/cnet/
$AllowedProxies = [
    //Opera Turbo (may include Opera-owned IP addresses that aren't used for Turbo, but shouldn't run much risk of exploitation)
    '64.255.180.*', //Norway
    '64.255.164.*', //Norway
    '80.239.242.*', //Poland
    '80.239.243.*', //Poland
    '91.203.96.*', //Norway
    '94.246.126.*', //Norway
    '94.246.127.*', //Norway
    '195.189.142.*', //Norway
    '195.189.143.*', //Norway
];

function proxyCheck($IP): bool
{
    global $AllowedProxies;
    foreach ($AllowedProxies as $i => $AllowedProxy) {
        //based on the wildcard principle it should never be shorter
        if (strlen($IP) < strlen($AllowedProxy)) {
            continue;
        }
        //since we're matching bit for bit iterating from the start
        for ($j = 0, $jl = strlen($IP); $j < $jl; ++$j) {
            //completed iteration and no inequality
            if ($j === $jl - 1 && $IP[$j] === $AllowedProxy[$j]) {
                return true;
            }
            
            //wildcard
            if ('*' === $AllowedProxy[$j]) {
                return true;
            }
            
            //inequality found
            if ($IP[$j] !== $AllowedProxy[$j]) {
                break;
            }
        }
    }
    
    return false;
}
