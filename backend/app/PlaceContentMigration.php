<?php
declare(strict_types=1);

/** One-time, reviewed data transfer. Never invoked by public requests or saves. */
final class PlaceContentMigration
{
    private array $changes = [];
    private string $lock;

    public function __construct(private PDO $pdo)
    {
        $this->lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }

    public function run(array $plan, string $publicRoot, bool $apply = false): array
    {
        if (($plan['schema_version'] ?? null) !== 1 || ($plan['migration'] ?? '') !== '060-destination-content') {
            throw new RuntimeException('Plan de contenido desconocido.');
        }
        $hash = hash('sha256', json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->changes = [];
        $this->pdo->beginTransaction();
        try {
            $record = $this->one('destination_content_migrations', ['migration_key' => $plan['migration']], false);
            if ($record) {
                if (!hash_equals($record['plan_sha256'], $hash)) throw new RuntimeException('El plan aplicado tiene otro hash.');
                $this->pdo->rollBack();
                return ['applied' => false, 'already_applied' => true, 'changes' => 0, 'plan_sha256' => $hash];
            }
            $root = realpath($publicRoot);
            if (!$root) throw new RuntimeException('Falta la raíz pública para verificar los medios.');
            foreach ($plan['destinations'] as $slug => $content) {
                $destination = $this->one('destinations', ['slug' => $slug]);
                $id = (int)$destination['id'];
                $ignored = $plan['ignored_legacy_keys'][$slug];
                foreach (array_unique([...$ignored['absent_places'], ...$ignored['removed_already_absent']]) as $legacySlug) {
                    $legacy = $this->one('points_of_interest', ['destination_id' => $id, 'slug' => $legacySlug], false);
                    if ($legacy && $legacy['publication_status'] !== 'archived') throw new RuntimeException('Existe una tarjeta heredada fuera del catálogo revisado: ' . $slug . '/' . $legacySlug);
                }
                foreach ($content['translations'] as $locale => $fields) {
                    $history = $fields['history'] ?? null;
                    unset($fields['history']);
                    $this->patch('destination_translations', ['destination_id' => $id, 'locale' => $locale], $fields);
                    if ($history) $this->history($id, $locale, $history);
                }
                foreach ($content['places'] as $placeSlug => $translations) {
                    $place = $this->one('points_of_interest', ['destination_id' => $id, 'slug' => $placeSlug]);
                    foreach ($translations as $locale => $fields) {
                        $this->patch('point_of_interest_translations', ['point_of_interest_id' => (int)$place['id'], 'locale' => $locale], $fields);
                    }
                }
                foreach ($content['media'] as $placeSlug => $media) {
                    foreach ($media['files'] as $path => $sha256) {
                        $file = realpath($root . '/' . $path);
                        if (!$file || !str_starts_with($file, $root . '/') || !hash_equals($sha256, hash_file('sha256', $file))) {
                            throw new RuntimeException('Medio aprobado ausente o diferente: ' . $path);
                        }
                    }
                    // Existing media identities and legal provenance are retained, never recreated.
                    $asset = $this->one('media_assets', ['asset_path' => $media['path']]);
                    $mediaId = (int)$asset['id'];
                    $this->patch('media_assets', ['id' => $mediaId], [
                        'width' => $media['width'], 'height' => $media['height'],
                        'responsive_srcset' => ['before' => null, 'after' => $media['srcset']],
                        'responsive_sizes' => ['before' => null, 'after' => $media['sizes']],
                    ]);
                    foreach ($media['translations'] as $locale => $alt) {
                        $this->patch('media_asset_translations', ['media_asset_id' => $mediaId, 'locale' => $locale], ['alt_text' => $alt]);
                    }
                    if ($placeSlug === 'hero') {
                        $table = 'destination_media'; $key = ['destination_id' => $id, 'role' => 'hero'];
                    } else {
                        $place = $this->one('points_of_interest', ['destination_id' => $id, 'slug' => $placeSlug]);
                        $table = 'point_of_interest_media'; $key = ['point_of_interest_id' => (int)$place['id'], 'role' => 'gallery'];
                    }
                    $this->selectMedia($table, $key, $mediaId, $media['before_path']);
                }
            }
            $this->pdo->prepare('INSERT INTO destination_content_migrations(migration_key,plan_sha256,changes_json) VALUES(?,?,?)')->execute([
                $plan['migration'], $hash, json_encode($this->changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);
            $result = ['applied' => $apply, 'already_applied' => false, 'changes' => count($this->changes), 'plan_sha256' => $hash];
            if ($apply) $this->pdo->commit(); else $this->pdo->rollBack();
            return $result;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    private function one(string $table, array $key, bool $required = true): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM ' . $table . ' WHERE ' . $this->where($key) . $this->lock);
        $query->execute(array_values($key));
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 1 || ($required && !$rows)) throw new RuntimeException('Identidad ausente o ambigua: ' . $table . ' ' . json_encode($key));
        return $rows[0] ?? null;
    }

    private function where(array $key): string
    {
        return implode(' AND ', array_map(static fn(string $column): string => $column . '=?', array_keys($key)));
    }

    private function patch(string $table, array $key, array $fields): void
    {
        if (!$fields) return;
        $row = $this->one($table, $key);
        $before = []; $after = [];
        foreach ($fields as $field => $values) {
            $current = $row[$field];
            if ($current === $values['after'] || ($current !== null && $values['after'] !== null && (string)$current === (string)$values['after'])) continue;
            if ($current !== $values['before'] && !($current !== null && $values['before'] !== null && (string)$current === (string)$values['before'])) {
                throw new RuntimeException('Contenido cambiado desde la base; revisar sin sobrescribir: ' . $table . '.' . $field . ' ' . json_encode($key));
            }
            $before[$field] = $current; $after[$field] = $values['after'];
        }
        if (!$after) return;
        $query = $this->pdo->prepare('UPDATE ' . $table . ' SET ' . implode(',', array_map(static fn(string $column): string => $column . '=?', array_keys($after))) . ' WHERE ' . $this->where($key));
        $query->execute([...array_values($after), ...array_values($key)]);
        $this->changes[] = ['table' => $table, 'key' => $key, 'before' => $before, 'after' => $after];
    }

    private function history(int $id, string $locale, array $history): void
    {
        $query = $this->pdo->prepare('SELECT b.id,t.body FROM destination_story_blocks b JOIN destination_story_translations t ON t.story_block_id=b.id WHERE b.destination_id=? AND b.section_key="history" AND t.locale=? ORDER BY b.display_order,b.id' . $this->lock);
        $query->execute([$id, $locale]); $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== count($history['before'])) throw new RuntimeException('La estructura histórica cambió; revisar antes de migrar.');
        foreach ($rows as $index => $row) {
            // Keep block identities; inactive paragraphs remain as empty translations.
            $this->patch('destination_story_translations', ['story_block_id' => (int)$row['id'], 'locale' => $locale], [
                'body' => ['before' => $history['before'][$index], 'after' => $history['after'][$index] ?? ''],
            ]);
        }
    }

    private function selectMedia(string $table, array $key, int $mediaId, string $expectedPath): void
    {
        $query = $this->pdo->prepare('SELECT m.asset_path,r.* FROM ' . $table . ' r JOIN media_assets m ON m.id=r.media_asset_id WHERE ' . implode(' AND ', array_map(static fn(string $column): string => 'r.' . $column . '=?', array_keys($key))) . ' ORDER BY r.display_order,r.media_asset_id' . $this->lock);
        $query->execute(array_values($key)); $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        $selected = $this->one('media_assets', ['id' => $mediaId]);
        if (!$rows || !in_array($rows[0]['asset_path'], [$expectedPath, $selected['asset_path']], true)) throw new RuntimeException('La selección de medios cambió; revisar antes de migrar.');
        if ((int)$rows[0]['media_asset_id'] === $mediaId) return;
        $order = (int)$rows[0]['display_order'] - 1;
        $binding = $this->one($table, [...$key, 'media_asset_id' => $mediaId], false);
        if ($binding) {
            $this->patch($table, [...$key, 'media_asset_id' => $mediaId], ['display_order' => ['before' => $binding['display_order'], 'after' => $order]]);
        } else {
            $record = [...$key, 'media_asset_id' => $mediaId, 'display_order' => $order];
            $this->pdo->prepare('INSERT INTO ' . $table . '(' . implode(',', array_keys($record)) . ') VALUES(' . implode(',', array_fill(0, count($record), '?')) . ')')->execute(array_values($record));
            $this->changes[] = ['table' => $table, 'key' => [...$key, 'media_asset_id' => $mediaId], 'before' => null, 'after' => $record];
        }
    }
}
