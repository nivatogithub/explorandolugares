<?php
declare(strict_types=1);

/**
 * Allowlisted, non-destructive database operations for the authenticated admin panel.
 * This class intentionally does not accept SQL or filesystem paths from a request.
 */
final class DatabaseMaintenance
{
    private const MIGRATIONS = [
        '001-phone-type' => [
            'file' => '001-phone-type.mysql.sql',
            'checks' => [['leads', ['phone_type']]],
            'requires' => ['leads'],
        ],
        '002-participation-version' => [
            'file' => '002-participation-version.mysql.sql',
            'checks' => [['leads', ['participation_version']]],
            'requires' => ['leads'],
        ],
        '003-dynamic-destinations' => [
            'file' => '003-dynamic-destinations.mysql.sql',
            'checks' => [['destinations', ['slug','publication_status']], ['destination_translations', ['destination_id','locale','display_name']],
                ['destination_story_blocks', ['destination_id','section_key']], ['destination_story_translations', ['story_block_id','locale','body']],
                ['destination_reasons', ['destination_id']], ['destination_reason_translations', ['reason_id','locale','body']],
                ['points_of_interest', ['destination_id','slug']], ['point_of_interest_translations', ['point_of_interest_id','locale','name']],
                ['media_assets', ['asset_path','creator']], ['media_asset_translations', ['media_asset_id','locale','alt_text']],
                ['destination_media', ['destination_id','media_asset_id','role']], ['point_of_interest_media', ['point_of_interest_id','media_asset_id']],
                ['destination_sources', ['destination_id','locale','source_url']], ['service_categories', ['code']],
                ['service_category_translations', ['category_code','locale','label']], ['businesses', ['public_id','destination_id','category_code']],
                ['business_translations', ['business_id','locale','display_name']], ['business_contacts', ['business_id','channel','contact_value']],
                ['business_media', ['business_id','media_asset_id']], ['business_sources', ['business_id','source_url']]],
            'requires' => [],
        ],
        '004-media-origin' => [
            'file' => '004-media-origin.mysql.sql',
            'checks' => [['media_assets', ['origin']]],
            'requires' => ['media_assets'],
        ],
        '005-destination-internal-research' => [
            'file' => '005-destination-internal-research.mysql.sql',
            'checks' => [['destinations', ['internal_research_json']]],
            'requires' => ['destinations'],
        ],
        '006-poi-experience-links' => [
            'file' => '006-poi-experience-links.mysql.sql',
            'checks' => [['points_of_interest', ['poi_type']], ['point_of_interest_translations', ['external_url', 'external_label']]],
            'requires' => ['points_of_interest', 'point_of_interest_translations'],
        ],
        '007-media-provenance' => [
            'file' => '007-media-provenance.mysql.sql',
            'checks' => [['media_assets', ['provenance_json']]],
            'requires' => ['media_assets'],
        ],
        '008-business-subscriptions' => [
            'file' => '008-business-subscriptions.mysql.sql',
            'checks' => [
                ['business_media', ['role']],
                ['subscription_plans', ['code','has_business_page']],
                ['subscription_plan_translations', ['plan_code','locale','name']],
                ['subscription_features', ['code']],
                ['subscription_feature_translations', ['feature_code','locale','name']],
                ['subscription_plan_features', ['plan_code','feature_code','included']],
                ['subscription_plan_prices', ['plan_code','billing_frequency','amount']],
                ['business_subscriptions', ['business_id','plan_code','status']],
            ],
            'requires' => ['businesses','business_media'],
        ],
        '009-business-origin' => [
            'file' => '009-business-origin.mysql.sql',
            'checks' => [['businesses', ['record_origin']]],
            'requires' => ['businesses'],
        ],
        '010-business-location-subscription-lifecycle' => [
            'file' => '010-business-location-subscription-lifecycle.mysql.sql',
            'checks' => [
                ['businesses', ['address_text','location_reference','latitude','longitude','location_source','opening_hours','price_range','capacity_count','menu_url','reservations_url']],
                ['subscription_plans', ['max_gallery_photos']],
                ['business_subscriptions', ['publish_not_before','quoted_amount','currency','published_at']],
            ],
            'requires' => ['businesses','subscription_plans','business_subscriptions'],
        ],
        '011-premium-business-story' => [
            'file' => '011-premium-business-story.mysql.sql',
            'checks' => [['business_translations', ['brand_story','featured_services']]],
            'requires' => ['business_translations'],
        ],
        '012-business-image-references' => [
            'file' => '012-business-image-references.mysql.sql',
            'checks' => [['businesses', ['image_assistance_requested']], ['business_image_references', ['business_id','storage_name','review_status']]],
            'requires' => ['businesses'],
        ],
        '013-accounts-location-map' => [
            'file' => '013-accounts-location-map.mysql.sql',
            'checks' => [
                ['businesses', ['location_map_url','location_confirmed']],
                ['accounts', ['public_id','account_type','email','password_hash','status']],
                ['account_identities', ['account_id','provider','provider_subject']],
                ['business_account_memberships', ['account_id','business_id','role','status']],
            ],
            'requires' => ['businesses','business_subscriptions','subscription_plans'],
        ],
        '014-tiered-business-description' => [
            'file' => '014-tiered-business-description.mysql.sql',
            'checks' => [],
            'row_checks' => [['subscription_features','code','extended_description']],
            'requires' => ['subscription_features','subscription_plan_features'],
        ],
        '015-business-media-quotas' => [
            'file' => '015-business-media-quotas.mysql.sql',
            'checks' => [],
            'value_checks' => [
                ['subscription_plans','code','page','max_gallery_photos',0],
                ['subscription_plans','code','page_premium','max_gallery_photos',2],
            ],
            'requires' => ['subscription_plans'],
        ],
        '016-plan-content-limits' => [
            'file' => '016-plan-content-limits.mysql.sql',
            // business_translations.summary predates this migration, so its
            // existence cannot indicate that this migration was applied.
            'checks' => [['subscription_plans', ['max_summary_chars','max_description_chars','max_brand_story_chars','max_featured_services_chars']]],
            'value_checks' => [
                ['subscription_plans','code','basic','max_summary_chars',300],
                ['subscription_plans','code','featured','max_description_chars',1000],
                ['subscription_plans','code','page_premium','max_brand_story_chars',1500],
            ],
            'requires' => ['subscription_plans','business_translations'],
        ],
        '017-business-claims' => [
            'file' => '017-business-claims.mysql.sql',
            'checks' => [['business_claim_requests', ['business_id','claimant_name','email','phone','relationship_code','status']]],
            'requires' => ['businesses'],
        ],
        '018-business-portal' => [
            'file' => '018-business-portal.mysql.sql',
            'checks' => [['business_claim_requests', ['account_id']]],
            'requires' => ['accounts','business_claim_requests'],
        ],
        '019-business-account-applications' => [
            'file' => '019-business-account-applications.mysql.sql',
            'checks' => [['business_account_applications', ['account_id','application_type','business_id','destination_id','category_code','business_name','status']]],
            'requires' => ['accounts','businesses','destinations','service_categories'],
        ],
    ];

    public function __construct(private PDO $pdo, private string $appRoot, private string $publicRoot)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function inspect(): array
    {
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $tables = $this->tables();
        $counts = [];
        foreach (['leads', 'destinations', 'businesses', 'media_assets', 'business_image_references', 'business_claim_requests', 'business_account_applications'] as $table) {
            if (in_array($table, $tables, true)) {
                $counts[$table] = (int)$this->pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
            } else {
                $counts[$table] = null;
            }
        }
        $migrations = [];
        foreach (self::MIGRATIONS as $id => $migration) {
            $migrations[$id] = $this->migrationState($migration, $tables);
        }
        return [
            'driver' => $driver,
            'connected' => true,
            'tables' => count($tables),
            'base_schema' => in_array('leads', $tables, true) && in_array('rate_limits', $tables, true),
            'empty_database' => count($tables) === 0,
            'counts' => $counts,
            'migrations' => $migrations,
            'catalog_empty' => $this->catalogIsEmpty($tables),
        ];
    }

    public function installBaseSchema(): void
    {
        $this->requireMysql();
        if ($this->tables() !== []) {
            throw new RuntimeException('La instalación base solo se permite cuando la base de datos no tiene ninguna tabla. No se hicieron cambios.');
        }
        $file = $this->appRoot.'/database/mysql.sql';
        if (!is_file($file)) throw new RuntimeException('No se encontró el esquema base en la aplicación privada.');
        $this->executeSqlFile($file);
        $tables = $this->tables();
        if (!in_array('leads', $tables, true) || !in_array('rate_limits', $tables, true)) {
            throw new RuntimeException('La instalación terminó sin confirmar las tablas base. Revisa el estado antes de repetir.');
        }
    }

    public function applyMigrations(): array
    {
        $this->requireMysql();
        $tables = $this->tables();
        if (!$tables) throw new RuntimeException('La base está vacía. Instala primero el esquema base.');
        $states = [];
        foreach (self::MIGRATIONS as $id => $migration) {
            $state = $this->migrationState($migration, $tables);
            if ($state === 'partial') {
                // Later migrations can appear partial when their prerequisite
                // tables have not been created yet. Let the ordered migration
                // pass reach them after their prerequisites are applied; only
                // block a partial state once all required tables exist.
                $missingRequirements = array_diff($migration['requires'], $tables);
                if ($missingRequirements !== []) {
                    $states[$id] = 'pending';
                    continue;
                }
                throw new RuntimeException('La migración '.$id.' está aplicada parcialmente. Se detuvo sin ejecutar más SQL; revisa columnas y tablas antes de continuar.');
            }
            $states[$id] = $state;
        }

        $this->ensureLedger();
        $results = [];
        foreach (self::MIGRATIONS as $id => $migration) {
            $tables = $this->tables();
            $state = $this->migrationState($migration, $tables);
            if ($state === 'applied') {
                $this->recordMigration($id, $migration['file'], 'detected');
                $results[$id] = 'Ya estaba aplicada; registrada.';
                continue;
            }
            if ($state !== 'pending') throw new RuntimeException('No se puede determinar el estado de la migración '.$id.'.');
            foreach ($migration['requires'] as $requiredTable) {
                if (!in_array($requiredTable, $tables, true)) {
                    throw new RuntimeException('La migración '.$id.' necesita la tabla '.$requiredTable.'. Aplica primero las migraciones anteriores.');
                }
            }
            $file = $this->appRoot.'/database/migrations/'.$migration['file'];
            if (!is_file($file)) throw new RuntimeException('No se encontró el archivo de migración '.$migration['file'].'.');
            $this->executeSqlFile($file);
            $after = $this->migrationState($migration, $this->tables());
            if ($after !== 'applied') throw new RuntimeException('La migración '.$id.' no quedó completa. Se detuvo para revisar el esquema.');
            $this->recordMigration($id, $migration['file'], 'applied');
            $results[$id] = 'Aplicada.';
        }
        return $results;
    }

    public function importInitialCatalog(): array
    {
        $this->requireMysql();
        $report = $this->inspect();
        if (!$report['catalog_empty']) throw new RuntimeException('El catálogo ya contiene registros. La importación inicial no sobrescribe, duplica ni modifica destinos existentes.');
        foreach ($report['migrations'] as $id => $state) {
            if ($state !== 'applied') throw new RuntimeException('Falta completar '.$id.' antes de importar el catálogo.');
        }
        $catalogFile = $this->appRoot.'/content/destinos-dinamicos.json';
        $optionsFile = $this->appRoot.'/contact-options.json';
        if (!is_file($catalogFile) || !is_file($optionsFile)) throw new RuntimeException('Falta el catálogo semilla o las opciones de categorías en la aplicación privada.');
        $catalog = json_decode((string)file_get_contents($catalogFile), true, 512, JSON_THROW_ON_ERROR);
        $options = json_decode((string)file_get_contents($optionsFile), true, 512, JSON_THROW_ON_ERROR);
        foreach (($catalog['media'] ?? []) as $media) {
            $relative = (string)($media['path'] ?? '');
            if (!preg_match('~^assets/[A-Za-z0-9/_-]+\.(?:webp|png|jpe?g|svg)$~D', $relative)) {
                throw new RuntimeException('El catálogo contiene una ruta de imagen no permitida.');
            }
            $file = realpath(rtrim($this->publicRoot, '/').'/'.$relative);
            $base = realpath($this->publicRoot);
            if (!$file || !$base || !str_starts_with($file, $base.DIRECTORY_SEPARATOR) || !is_file($file)) {
                throw new RuntimeException('Falta una imagen del catálogo en la carpeta pública: '.$relative);
            }
        }
        require_once $this->appRoot.'/DestinationImporter.php';
        return DestinationImporter::import($this->pdo, $catalog, $options);
    }

    private function requireMysql(): void
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('Las operaciones de escritura de esta herramienta requieren MySQL/MariaDB.');
        }
    }

    private function tables(): array
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            return array_values(array_map('strval', $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)));
        }
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return array_values(array_map('strval', $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN)));
        }
        throw new RuntimeException('El controlador de base de datos no está soportado.');
    }

    private function columns(string $table): array
    {
        if (!preg_match('/^[a-z_]+$/D', $table)) throw new RuntimeException('Nombre de tabla no permitido.');
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            return array_values(array_map('strval', $this->pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN)));
        }
        return array_values(array_map('strval', $this->pdo->query('PRAGMA table_info("'.$table.'")')->fetchAll(PDO::FETCH_COLUMN, 1)));
    }

    private function migrationState(array $migration, array $tables): string
    {
        $present = 0; $total = 0;
        foreach (($migration['checks']??[]) as [$table, $columns]) {
            $total++;
            if (!in_array($table, $tables, true)) continue;
            $actual = $columns ? $this->columns($table) : [];
            if (!$columns || count(array_diff($columns, $actual)) === 0) $present++;
        }
        foreach (($migration['row_checks']??[]) as [$table,$column,$value]) {
            $total++;
            if (!in_array($table,$tables,true)||!in_array($column,$this->columns($table),true)) continue;
            $q=$this->pdo->prepare('SELECT COUNT(*) FROM `'.$table.'` WHERE `'.$column.'`=?');$q->execute([$value]);
            if ((int)$q->fetchColumn()>0) $present++;
        }
        foreach (($migration['value_checks']??[]) as [$table,$keyColumn,$keyValue,$valueColumn,$value]) {
            $total++;
            if (!in_array($table,$tables,true)||!in_array($keyColumn,$this->columns($table),true)||!in_array($valueColumn,$this->columns($table),true)) continue;
            $q=$this->pdo->prepare('SELECT COUNT(*) FROM `'.$table.'` WHERE `'.$keyColumn.'`=? AND `'.$valueColumn.'`=?');$q->execute([$keyValue,$value]);
            if ((int)$q->fetchColumn()>0) $present++;
        }
        if ($total===0) throw new RuntimeException('La migración no define controles de estado.');
        if ($present === $total) return 'applied';
        return $present === 0 ? 'pending' : 'partial';
    }

    private function catalogIsEmpty(array $tables): bool
    {
        foreach (['destinations', 'service_categories', 'businesses', 'media_assets'] as $table) {
            if (!in_array($table, $tables, true) || (int)$this->pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn() !== 0) return false;
        }
        return true;
    }

    private function ensureLedger(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS el_schema_migrations (
            migration_id VARCHAR(80) NOT NULL PRIMARY KEY,
            migration_file VARCHAR(120) NOT NULL,
            applied_at DATETIME NOT NULL,
            result VARCHAR(20) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    private function recordMigration(string $id, string $file, string $result): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO el_schema_migrations (migration_id,migration_file,applied_at,result) VALUES (?,?,UTC_TIMESTAMP(),?) ON DUPLICATE KEY UPDATE migration_file=VALUES(migration_file),result=VALUES(result)');
        $stmt->execute([$id, $file, $result]);
    }

    private function executeSqlFile(string $file): void
    {
        $sql = file_get_contents($file);
        if (!is_string($sql)) throw new RuntimeException('No se pudo leer un archivo SQL permitido.');
        foreach (explode(';', $sql) as $statement) {
            if (trim($statement) !== '') $this->pdo->exec($statement);
        }
    }
}
