<?php
spl_autoload_register(static function(string $class):void {if(str_starts_with($class,'MaxMind\\Db\\')){ $file=__DIR__.'/src/'.str_replace('\\','/',$class).'.php';if(is_file($file))require $file;}});
