<?php
declare(strict_types=1);

/** Disposable ordinary tables on the reserved dev's existing MariaDB server. */
final class LocalMariaDbFixture
{
    private PDO $root;
    private string $database;
    private string $user;
    private string $password;
    private bool $databaseCreated=false;
    private bool $userCreated=false;
    public int $foreignKeys=0;

    public function __construct(array $config,private string $owner)
    {
        if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1'||empty($config['local_test'])||
           !preg_match('/host=127\.0\.0\.1;dbname=(el_test_destination_[a-f0-9]+)(?:;|$)/D',$config['dsn'],$match))throw new RuntimeException('Reserved synthetic dev required.');
        $source=$match[1];$suffix=bin2hex(random_bytes(8));
        $this->database='el_fixture_places_'.$suffix;$this->user='el_fixture_'.$suffix;$this->password=bin2hex(random_bytes(32));
        $this->root=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        try{
            preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?([a-z_]+)/',file_get_contents('/srv/portal/app/database/destinations.sqlite.sql'),$names);
            $tables=array_fill_keys($names[1],true);$pending=array_keys($tables);
            $references=$this->root->prepare('SELECT REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL');
            while($pending){$table=array_pop($pending);$references->execute([$source,$table]);
                foreach($references->fetchAll(PDO::FETCH_NUM) as [$schema,$related]){
                    if($schema!==$source)throw new RuntimeException('Source schema has an external dependency.');
                    if(!isset($tables[$related])){$tables[$related]=true;$pending[]=$related;}
                }
            }
            $definitions=[];
            foreach(array_keys($tables) as $table){
                if(!preg_match('/^[a-z0-9_]+$/D',$table))throw new RuntimeException('Unexpected schema identifier.');
                $definition=$this->root->query('SHOW CREATE TABLE `'.$source.'`.`'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
                $definitions[]=str_replace('`'.$source.'`.','`'.$this->database.'`.',$definition);
            }
            if(count($definitions)<20)throw new RuntimeException('Incomplete source schema.');
            $this->root->exec('CREATE DATABASE `'.$this->database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$this->databaseCreated=true;
            $this->receipt();
            $this->root->exec('USE `'.$this->database.'`');$this->root->exec('SET FOREIGN_KEY_CHECKS=0');
            try{foreach($definitions as $sql)$this->root->exec($sql);}finally{$this->root->exec('SET FOREIGN_KEY_CHECKS=1');}
            $foreign=$this->root->prepare('SELECT REFERENCED_TABLE_SCHEMA FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_NAME IS NOT NULL');$foreign->execute([$this->database]);
            $references=$foreign->fetchAll(PDO::FETCH_COLUMN);$this->foreignKeys=count($references);
            if(!$references||array_diff($references,[$this->database]))throw new RuntimeException('Fixture foreign keys escape isolated database.');
            $this->root->exec('CREATE TABLE el_fixture_owner(owner_token VARCHAR(64) NOT NULL) ENGINE=InnoDB');
            $this->root->prepare('INSERT INTO el_fixture_owner VALUES(?)')->execute([$this->owner]);
            $account=$this->root->quote($this->user)."@'127.0.0.1'";
            $this->root->exec('CREATE USER '.$account.' IDENTIFIED BY '.$this->root->quote($this->password));$this->userCreated=true;$this->receipt();
            $this->root->exec('GRANT ALL ON `'.$this->database.'`.* TO '.$account);
        }catch(Throwable $error){$this->close();throw $error;}
    }

    /** Test-only credentials are consumed by the local gateway, never reported. */
    public function connection():array
    {return ['dsn'=>'mysql:host=127.0.0.1;dbname='.$this->database.';charset=utf8mb4','user'=>$this->user,'password'=>$this->password,'owner'=>$this->owner];}

    public static function reset(array $connection):PDO
    {
        $pdo=new PDO($connection['dsn'],$connection['user'],$connection['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $name=$pdo->query('SELECT DATABASE()')->fetchColumn();
        if(!preg_match('/^el_fixture_places_[a-f0-9]{16}$/D',$name)||!hash_equals($connection['owner'],(string)$pdo->query('SELECT owner_token FROM el_fixture_owner')->fetchColumn()))throw new RuntimeException('Not an owned disposable fixture.');
        $tables=$pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' AND TABLE_NAME<>'el_fixture_owner'")->fetchAll(PDO::FETCH_COLUMN);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try{foreach($tables as $table){if(!preg_match('/^[a-z0-9_]+$/D',$table))throw new RuntimeException('Unexpected fixture table.');$pdo->exec('TRUNCATE TABLE `'.$table.'`');}}
        finally{$pdo->exec('SET FOREIGN_KEY_CHECKS=1');}
        return $pdo;
    }

    private function receipt():void
    {
        file_put_contents('/srv/state/place-mariadb-fixture-receipt.json',json_encode(['database'=>$this->database,'user'=>$this->user,'owner_sha256'=>hash('sha256',$this->owner),'database_created'=>$this->databaseCreated,'user_created'=>$this->userCreated],JSON_THROW_ON_ERROR));
    }

    public function close():void
    {
        if(!preg_match('/^el_fixture_places_[a-f0-9]{16}$/D',$this->database)||!preg_match('/^el_fixture_[a-f0-9]{16}$/D',$this->user))throw new RuntimeException('Refusing unrelated cleanup.');
        $this->root=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        try{if($this->databaseCreated){$this->root->exec('DROP DATABASE `'.$this->database.'`');$this->databaseCreated=false;}}
        finally{if($this->userCreated){$this->root->exec('DROP USER '.$this->root->quote($this->user)."@'127.0.0.1'");$this->userCreated=false;}if(!$this->databaseCreated&&!$this->userCreated)@unlink('/srv/state/place-mariadb-fixture-receipt.json');}
    }
}
