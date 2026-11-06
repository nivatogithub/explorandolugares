<?php
declare(strict_types=1);
require __DIR__.'/../app/AccountAuthReturn.php';
$cases=[
 ['/lugares/barichara/','','localhost:8086','/lugares/barichara/'],
 ['/lugares/?q=Barichara&guardados=1','','localhost:8086','/lugares/?q=Barichara'],
 [null,'http://localhost:8086/lugares/barichara/','localhost:8086','/lugares/barichara/'],
 [null,'https://evil.invalid/lugares/','localhost:8086','/'],
 ['//evil.invalid/','','localhost:8086','/'],
 ['/\\evil.invalid/','','localhost:8086','/'],
 ['/negocios/acceso/','','localhost:8086','/'],
];
foreach($cases as [$pending,$referer,$host,$expected])if(AccountAuthReturn::path($pending,$referer,$host)!==$expected)throw new RuntimeException('Unsafe account cancellation return');
echo "OK account return: guide, filters, same-origin reference, external/backslash/auth loop rejection\n";
