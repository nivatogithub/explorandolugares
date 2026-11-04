#!/usr/bin/env python3
"""Compare public read models from the old repository and the new lugar selectors."""
from pathlib import Path
import argparse
import json
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PHP = r'''
$i=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
class QueryCountPDO extends PDO {
    public int $reads=0;
    public function prepare(string $q,array $o=[]):PDOStatement|false{$this->reads++;return parent::prepare($q,$o);}
    public function query(string $q,?int $m=null,mixed ...$a):PDOStatement|false{$this->reads++;return $m===null?parent::query($q):parent::query($q,$m,...$a);}
}
$app=$i['package'].'/app';
require $app.($i['legacy']?'/DestinationRepository.php':'/PlaceRepository.php');
require $app.'/HomeAttractions.php';require $app.'/seo/PublicSitemap.php';
require $app.($i['legacy']?'/DestinationAdminService.php':'/PlaceAdminService.php');
$pdo=new QueryCountPDO('sqlite:'.$i['database'],null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$result=['pages'=>[],'previews'=>[],'directories'=>[],'home'=>[]];$counts=[];
foreach(['es','en','pt-BR'] as $locale){
    foreach($i['slugs'] as $slug){
        $pdo->reads=0;
        $service=$i['legacy']?new DestinationRepository($pdo):PlaceRepository::fromDatabase($pdo);
        $result['pages'][$locale][$slug]=$service->findPublished($slug,$locale);
        $counts[$locale][$slug]=$pdo->reads;
        $result['previews'][$locale][$slug]=$service->findPublished($slug,$locale,true);
    }
    $result['directories'][$locale]=$service->listPublished($locale);
    $result['home'][$locale]=HomeAttractions::items($pdo,$locale,$i['package'].'/public');
}
$result['missing']=$service->findPublished('does-not-exist','es');
$result['fallback']=$service->findPublished('jerico','unknown');
$result['sitemap']=(new PublicSitemap($pdo))->xml('https://explorandolugares.com','');
$admin=$i['legacy']?new DestinationAdminService($pdo):new PlaceAdminService($pdo);$result['admin_list']=$admin->all();$result['admin_details']=[];
foreach($result['admin_list'] as $row)$result['admin_details'][(int)$row['id']]=$admin->find((int)$row['id']);
echo json_encode(['models'=>$result,'queries'=>$counts],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
'''


def run(before, after, database, output):
    before, after, database = before.resolve(), after.resolve(), database.resolve()
    assert all(path.is_relative_to(ROOT / '.local') for path in (before, after, database))
    slugs = json.loads((ROOT / 'content/release-places-businesses.json').read_text())['public_places']
    results = []
    for package, legacy in [(before, True), (after, False)]:
        payload = {'package': str(package), 'legacy': legacy, 'database': str(database), 'slugs': slugs}
        process = subprocess.run(['php', '-d', 'display_errors=stderr', '-r', PHP],
                                 input=json.dumps(payload), text=True, capture_output=True, check=True)
        assert not process.stderr.strip(), process.stderr
        results.append(json.loads(process.stdout))
    assert results[0]['models'] == results[1]['models'], 'Place DTO, directory, home, preview or sitemap changed'
    before_counts = results[0]['queries']
    after_counts = results[1]['queries']
    assert all(after_counts[lang][slug] <= before_counts[lang][slug] for lang in before_counts for slug in slugs)
    report = {'database': 'shared disposable SQLite', 'production_accessed': False,
              'public_places': len(slugs), 'locales': ['es', 'en', 'pt-BR'],
              'public_and_preview_models_identical': True, 'directories_identical': True,
              'home_selection_identical': True, 'sitemap_identical': True,
              'private_admin_models_identical': True,
              'queries_before': before_counts, 'queries_after': after_counts}
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
    print(f'OK: {len(slugs)*3} public and {len(slugs)*3} preview models, private admin, directories, home and sitemap unchanged; query counts do not increase.')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--before', type=Path, required=True)
    parser.add_argument('--after', type=Path, required=True)
    parser.add_argument('--database', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    run(args.before, args.after, args.database, args.output)
