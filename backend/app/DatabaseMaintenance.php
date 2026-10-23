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
        '020-account-password-resets' => [
            'file' => '020-account-password-resets.mysql.sql',
            'checks' => [['account_password_resets', ['account_id','token_hash','expires_at','used_at']]],
            'requires' => ['accounts'],
        ],
        '021-business-payment-evidence' => [
            'file' => '021-business-payment-evidence.mysql.sql',
            'checks' => [['business_subscription_payment_evidence', ['subscription_id','account_id','storage_name','mime_type','status','reviewed_at']]],
            'requires' => ['accounts','business_subscriptions'],
        ],
        '022-subscription-prices' => [
            'file' => '022-subscription-prices.mysql.sql',
            'value_checks' => [
                ['subscription_plan_prices','plan_code','basic','amount','19900.00'],
                ['subscription_plan_prices','plan_code','basic','amount','199000.00'],
                ['subscription_plan_prices','plan_code','featured','amount','49900.00'],
                ['subscription_plan_prices','plan_code','featured','amount','499000.00'],
                // The page amounts are superseded by migration 050.
                ['subscription_plan_prices','plan_code','page','currency','COP'],
                ['subscription_plan_prices','plan_code','page_premium','amount','119900.00'],
                ['subscription_plan_prices','plan_code','page_premium','amount','1199000.00'],
            ],
            'requires' => ['subscription_plan_prices'],
        ],
        '023-business-representation-evidence' => [
            'file' => '023-business-representation-evidence.mysql.sql',
            'checks' => [['business_account_application_evidence', ['application_id','evidence_type','storage_name','mime_type','verified_at']]],
            'requires' => ['business_account_applications'],
        ],
        '024-payment-customer-message' => [
            'file' => '024-payment-customer-message.mysql.sql',
            'checks' => [['business_subscription_payment_evidence', ['customer_message']]],
            'requires' => ['business_subscription_payment_evidence'],
        ],
        '025-subscription-renewal-history' => [
            'file' => '025-subscription-renewal-history.mysql.sql',
            'checks' => [['business_subscriptions', ['renewal_of_subscription_id']]],
            'requires' => ['business_subscriptions'],
        ],
        '026-business-research-contacts' => [
            'file' => '026-business-research-contacts.mysql.sql',
            'checks' => [['business_research_contacts', ['business_id','channel','contact_value','source_url','evidence_status','authorization_status','legal_basis_status','campaign_allowed','suppressed','record_hash']]],
            'requires' => ['businesses'],
        ],
        '027-business-bulk-update-audit' => [
            'file' => '027-business-bulk-update-audit.mysql.sql',
            'checks' => [['business_bulk_update_audit', ['batch_id','business_id','actor','before_json','after_json','changes_json']]],
            'requires' => ['businesses'],
        ],
        '028-accounts-business-channels' => [
            'file' => '028-accounts-business-channels.mysql.sql',
            'checks' => [['accounts',['contact_phone']],['business_research_contacts',['recipient_label']]],
            'requires' => ['businesses','accounts','business_account_memberships','business_research_contacts'],
        ],
        '029-business-email-campaigns' => [
            'file' => '029-business-email-campaigns.mysql.sql',
            'checks' => [['business_email_permissions',['email','decision','evidence']],['business_email_campaigns',['recipients_json','content_hash','status','busy','sync_cursor','provider_campaign_id']],['business_email_audit',['campaign_id','actor','action','detail']]],
            'requires' => ['businesses','business_research_contacts'],
        ],
        '030-business-categories' => [
            'file' => '030-business-categories.mysql.sql',
            'checks' => [['business_categories',['business_id','category_code']]],
            'requires' => ['businesses','service_categories'],
        ],
        '031-costs-cancellation-requests' => [
            'file'=>'031-costs-cancellation-requests.mysql.sql',
            'checks'=>[['operating_costs',['cost_date','category','amount_cop','destination_id','voided_at']],['subscription_cancellation_requests',['subscription_id','business_id','account_id','reason_code','status','review_note']]],
            'requires'=>['businesses','business_subscriptions','accounts','destinations'],
        ],
        '046-premium-page-aliases'=>['file'=>'046-premium-page-aliases.mysql.sql','checks'=>[['business_page_aliases',['alias','business_id','is_current','created_at']]],'requires'=>['businesses']],
        '047-lead-business-links'=>['file'=>'047-lead-business-links.mysql.sql','checks'=>[['lead_business_links',['lead_id','business_id','linked_by','created_at']]],'requires'=>['leads','businesses']],
        '048-individual-lead-replies'=>['file'=>'048-individual-lead-replies.mysql.sql','checks'=>[['lead_reply_operations',['id','lead_id','status','recipient','sender_email','sender_name','reply_to','subject','message_text','html_body','text_body','template_version','content_hash','created_by','confirmed_by','claimed_at','completed_at','provider_message_id','result_code','reconciled_at','reconciled_by','reconciliation_note']],['lead_reply_attempts',['operation_id','outcome','http_status']]],'requires'=>['leads']],
        '049-business-editorial'=>['file'=>'049-business-editorial.mysql.sql','checks'=>[['business_editorial_versions',['business_id','account_id','parent_id','status','plan_code','base_hash','payload_json','payload_hash','image_consent','owner_approved_at','published_at']],['business_editorial_events',['version_id','actor','action']],['business_editorial_images',['version_id','storage_name','original_name','mime_type','file_size','width','height']]],'requires'=>['businesses','accounts']],
        '050-commercial-page-price'=>['file'=>'050-commercial-page-price.mysql.sql','value_checks'=>[['subscription_plan_prices','plan_code','page','amount','79900.00'],['subscription_plan_prices','plan_code','page','amount','199750.00'],['subscription_plan_prices','plan_code','page','amount','415480.00'],['subscription_plan_prices','plan_code','page','amount','799000.00']],'requires'=>['subscription_plan_prices']],
        '051-business-evidence-retirement'=>['file'=>'051-business-evidence-retirement.mysql.sql','checks'=>[['business_evidence_retirement',['evidence_id','document_sha256','reviewer','verified_at','deleted_at']]],'requires'=>['business_account_application_evidence']],
        '052-business-onboarding-uploads'=>['file'=>'052-business-onboarding-uploads.mysql.sql','checks'=>[['business_onboarding_uploads',['id','draft_token','upload_kind','slot_code','storage_name','mime_type','expires_at','consumed_at']]],'requires'=>[]],
        '045-business-service-sections'=>['file'=>'045-business-service-sections.mysql.sql','checks'=>[['business_service_sections',['business_id','locale','section_code','description']]],'requires'=>['businesses']],
        '044-subscription-changes'=>['file'=>'044-subscription-changes.mysql.sql','checks'=>[['subscription_changes',['subscription_id','source_subscription_id','change_kind','quoted_at','term_starts_at','effective_at','original_ends_at','source_full_cents','target_full_cents','credit_cents','target_remaining_cents','due_cents','currency']]],'requires'=>['business_subscriptions']],
        '043-account-lifecycle'=>['file'=>'043-account-lifecycle.mysql.sql','checks'=>[['account_access_events',['account_id','event_type','actor_account_id','reason','created_at']],['account_reactivations',['account_id','token_hash','expires_at','used_at']]],'requires'=>['accounts']],
        '041-experience-operator-addon'=>['file'=>'041-experience-operator-addon.mysql.sql','checks'=>[['experience_operator_prices',['billing_frequency','amount','currency']],['business_experience_subscriptions',['subscription_id','experience_id','quoted_amount','currency']]],'requires'=>['business_subscriptions','points_of_interest']],
        '040-payment-transaction-details'=>['file'=>'040-payment-transaction-details.mysql.sql','checks'=>[['business_subscription_payment_evidence',['checkout_reference','transaction_date','paid_amount','payment_method','source_bank']]],'requires'=>['business_subscription_payment_evidence']],
        '039-payment-instructions'=>['file'=>'039-payment-instructions.mysql.sql','checks'=>[['payment_instructions',['enabled','test_mode','holder','bank','account_number','account_type','breb_key','notes','qr_file','updated_at','updated_by']]],'requires'=>[]],
        '038-account-residence'=>['file'=>'038-account-residence.mysql.sql','checks'=>[['account_residences',['account_id','country_code','region_code','region_name','city_code','city_name']]],'requires'=>['accounts']],
        '037-subscription-pricing-policy'=>['file'=>'037-subscription-pricing-policy.mysql.sql','checks'=>[['subscription_pricing_policy',['period_code','billable_months']]],'requires'=>['subscription_plan_prices']],
        '036-public-requests'=>['file'=>'036-public-requests.mysql.sql','checks'=>[['public_requests',['reference','request_type','status','response_note']]],'requires'=>[]],
        '035-account-email-verifications'=>[
            'file'=>'035-account-email-verifications.mysql.sql',
            'checks'=>[['account_email_verifications',['account_id','token_hash','expires_at','used_at']]],
            'requires'=>['accounts'],
        ],
        '034-business-onboarding'=>[
            'file'=>'034-business-onboarding.mysql.sql',
            'checks'=>[['business_account_applications',['business_details_json']],['account_consents',['account_id','consent_version','accepted_at']],['business_application_images',['application_id','storage_name','mime_type']]],
            'requires'=>['accounts','business_account_applications'],
        ],
        '033-account-roles'=>[
            'file'=>'033-account-roles.mysql.sql',
            'checks'=>[['account_roles',['account_id','role','created_at']]],
            'requires'=>['accounts'],
        ],
        '032-operating-cost-evidence'=>[
            'file'=>'032-operating-cost-evidence.mysql.sql',
            'checks'=>[['operating_cost_evidence',['cost_id','storage_name','original_name','mime_type','file_size','sha256','created_by']]],
            'requires'=>['operating_costs'],
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
        foreach (['leads', 'destinations', 'businesses', 'media_assets', 'business_image_references', 'business_claim_requests', 'business_account_applications', 'account_password_resets','business_subscription_payment_evidence'] as $table) {
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

    public function requirementDetails(): array
    {
        $tables=$this->tables();$result=[];
        foreach(self::MIGRATIONS as $id=>$migration){$lines=[];
            foreach($migration['checks']??[] as [$table,$columns]){if(!in_array($table,$tables,true)){$lines[]='Falta tabla: '.$table;continue;}$missing=array_diff($columns,$this->columns($table));$lines[]=$missing?'Faltan columnas en '.$table.': '.implode(', ',$missing):'Tabla y columnas comprobadas: '.$table;}
            foreach($migration['row_checks']??[] as [$table,$column,$value])$lines[]='Registro requerido en '.$table.': '.$column.' = '.$value;
            foreach($migration['value_checks']??[] as [$table,$key,$value,$column,$expected])$lines[]='Configuración requerida en '.$table.': '.$key.' = '.$value.', '.$column.' = '.$expected;
            $result[$id]=$lines;
        }
        return $result;
    }

    public function migrationCatalog(): array
    {
        $result=[];
        foreach(self::MIGRATIONS as $id=>$migration){$path=$this->appRoot.'/database/migrations/'.$migration['file'];if(is_file($path))$result[$id]=['file'=>$migration['file'],'sha256'=>hash_file('sha256',$path)];}
        return $result;
    }

    public function health(): array
    {
        $version=(string)$this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        $result=['engine'=>stripos($version,'MariaDB')!==false?'MariaDB':($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?'MySQL':'SQLite'),'version'=>$version,'tables'=>[]];
        if($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')return $result;
        $tables=$this->pdo->query("SELECT TABLE_NAME,ENGINE,TABLE_ROWS,DATA_LENGTH,INDEX_LENGTH,DATA_FREE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);
        foreach($tables as $row){$q=$this->pdo->prepare('SELECT INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME,NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX');$q->execute([$row['TABLE_NAME']]);$indexes=[];foreach($q as $index)$indexes[$index['INDEX_NAME']][]=$index['COLUMN_NAME'];$row['indexes']=$indexes;$row['warning']=!isset($indexes['PRIMARY'])?'Sin clave primaria: revisar su necesidad.':'Sin alerta estructural básica';$result['tables'][]=$row;}
        return $result;
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

    public function applyMigrations(?array $selected = null): array
    {
        $this->requireMysql();
        if((int)$this->pdo->query("SELECT GET_LOCK(CONCAT('el_schema_',DATABASE()),0)")->fetchColumn()!==1)throw new RuntimeException('Otro proceso está aplicando cambios. Espera y vuelve a validar.');
        try{return $this->runMigrations($selected);}finally{$this->pdo->query("SELECT RELEASE_LOCK(CONCAT('el_schema_',DATABASE()))");}
    }

    /** Only missing complete tables may be resumed; never repair unknown columns silently. */
    private function validateLifecyclePartial(array $tables):void
    {
        foreach(self::MIGRATIONS['043-account-lifecycle']['checks'] as [$table,$columns]){
            if(!in_array($table,$tables,true))continue;
            $actual=$this->pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN);
            if(array_diff(array_merge(['id','created_at'],$columns),$actual))throw new RuntimeException('La tabla '.$table.' tiene una estructura inesperada; no se modificó.');
            $indexes=$this->pdo->query('SHOW INDEX FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
            $names=array_column($indexes,'Key_name');
            if(!in_array('PRIMARY',$names,true)||!in_array($table.'_account',$names,true))throw new RuntimeException('Faltan índices de '.$table.'; no se modificó.');
        }
    }

    private function runMigrations(?array $selected = null): array
    {
        $this->requireMysql();
        if ($selected !== null && (!$selected || array_diff($selected, array_keys(self::MIGRATIONS)))) throw new RuntimeException('Selección de migraciones no permitida.');
        $tables = $this->tables();
        if (!$tables) throw new RuntimeException('La base está vacía. Instala primero el esquema base.');
        $states = [];
        foreach (self::MIGRATIONS as $id => $migration) {
            if ($selected !== null && !in_array($id, $selected, true)) continue;
            $state = $this->migrationState($migration, $tables);
            if ($id==='043-account-lifecycle'&&$state==='partial') {
                $this->validateLifecyclePartial($tables);
                $state='pending';
            }
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
            if ($selected !== null && !in_array($id, $selected, true)) continue;
            $tables = $this->tables();
            $state = $this->migrationState($migration, $tables);
            if ($id==='043-account-lifecycle'&&$state==='partial') {
                $this->validateLifecyclePartial($tables);
                $state='pending';
            }
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
            if($id==='028-accounts-business-channels'){
                $duplicates=$this->pdo->query('SELECT business_id FROM business_account_memberships WHERE status="active" GROUP BY business_id HAVING COUNT(*)>1 LIMIT 1')->fetchColumn();
                if($duplicates!==false)throw new RuntimeException('Hay negocios con más de una cuenta activa. Revisa sus asociaciones antes de habilitar el modelo de cuenta responsable; no se eliminaron asociaciones.');
            }
            if (!is_file($file)) throw new RuntimeException('No se encontró el archivo de migración '.$migration['file'].'.');
            if(in_array($id,['043-account-lifecycle','044-subscription-changes'],true)){
                $wait=(int)$this->pdo->query('SELECT @@SESSION.lock_wait_timeout')->fetchColumn();
                $this->pdo->exec('SET SESSION lock_wait_timeout=5');
                try{$this->executeSqlFile($file);}finally{$this->pdo->exec('SET SESSION lock_wait_timeout='.$wait);}
            }else $this->executeSqlFile($file);
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
        // Remove full-line SQL comments before splitting statements. A semicolon
        // inside a comment is text, not a statement boundary.
        $sql = preg_replace('/^[ \t]*--[^\r\n]*(?:\r?\n|$)/m', '', $sql);
        foreach (explode(';', $sql) as $statement) {
            if (trim($statement) !== '') $this->pdo->exec($statement);
        }
    }
}
