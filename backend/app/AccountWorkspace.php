<?php
declare(strict_types=1);
final class AccountWorkspace
{
    public static function choose(array &$session,string $space):string
    {
        if(!in_array($space,['explore','business'],true))throw new RuntimeException('Selecciona Explorar o Mis negocios.');
        $session['account_space']=$space;
        return '/negocios/portal/?espacio='.$space;
    }
}
