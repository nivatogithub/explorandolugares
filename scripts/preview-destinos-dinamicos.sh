#!/bin/sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$ROOT"
mkdir -p .local
DB=${EL_DESTINATIONS_PREVIEW_DB:-"$ROOT/.local/destinos-dinamicos-preview.sqlite"}
export EL_DESTINATIONS_DSN="sqlite:$DB"
export EL_LOCAL_CONFIG_PATH="${EL_LOCAL_CONFIG_PATH:-$ROOT/.local/destinos-admin-config.php}"
export EL_ADMIN_MEDIA_ROOT="${EL_ADMIN_MEDIA_ROOT:-$ROOT/.local/destinos-media}"
mkdir -p "$EL_ADMIN_MEDIA_ROOT"
php -r '$dsn=getenv("EL_DESTINATIONS_DSN");$config=["dsn"=>$dsn,"db_user"=>null,"db_password"=>null,"app_key"=>bin2hex(random_bytes(32)),"admin_user"=>"localadmin","admin_password_hash"=>password_hash("DestinosLocal8014!",PASSWORD_DEFAULT),"registration_enabled"=>false,"local_test"=>true,"privacy"=>["operator"=>"Local","contact"=>"","retention"=>"","retention_en"=>""]];file_put_contents(getenv("EL_LOCAL_CONFIG_PATH"),"<?php\nreturn ".var_export($config,true).";\n");chmod(getenv("EL_LOCAL_CONFIG_PATH"),0600);'

python3 scripts/extraer-contenido-destinos.py
php -r '$pdo=new PDO(getenv("EL_DESTINATIONS_DSN"),null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); $leads=$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type=\"table\" AND name=\"leads\"")->fetchColumn(); if(!$leads){$pdo->exec(file_get_contents("backend/database/sqlite.sql"));} $exists=$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type=\"table\" AND name=\"destinations\"")->fetchColumn(); if(!$exists){$pdo->exec(file_get_contents("backend/database/destinations.sqlite.sql"));}'
php -r '$pdo=new PDO(getenv("EL_DESTINATIONS_DSN"),null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$columns=$pdo->query("PRAGMA table_info(media_assets)")->fetchAll(PDO::FETCH_COLUMN,1);if(!in_array("origin",$columns,true))$pdo->exec(file_get_contents("backend/database/migrations/004-media-origin.sqlite.sql"));$columns=$pdo->query("PRAGMA table_info(points_of_interest)")->fetchAll(PDO::FETCH_COLUMN,1);if(!in_array("poi_type",$columns,true))$pdo->exec(file_get_contents("backend/database/migrations/006-poi-experience-links.sqlite.sql"));'
php -r '$pdo=new PDO(getenv("EL_DESTINATIONS_DSN"),null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$columns=$pdo->query("PRAGMA table_info(destinations)")->fetchAll(PDO::FETCH_COLUMN,1);if(!in_array("internal_research_json",$columns,true))$pdo->exec(file_get_contents("backend/database/migrations/005-destination-internal-research.sqlite.sql"));'
php -r '$pdo=new PDO(getenv("EL_DESTINATIONS_DSN"),null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); $count=(int)$pdo->query("SELECT COUNT(*) FROM destinations")->fetchColumn(); if($count===0){passthru(escapeshellarg(PHP_BINARY)." ".escapeshellarg("backend/bin/importar-destinos.php"),$code); exit($code);}'
PORT=${PORT:-8011}
exec php -S "127.0.0.1:$PORT" -t ensayos scripts/php-destinations-router.php
