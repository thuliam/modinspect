<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Database;
use App\Core\Csrf;
use App\Models\Dashboard;
use App\Services\Admin\AdminDataTableService;

/** LS-89 BE-4: products, aliases, new-price references, articles and game FPS results. */
final class AdminCatalogController extends AdminBaseController {
    public function products(): void { if(!$this->requirePermission('products.view')) return; $d=new Dashboard();$this->render('products','Product Master',['canManage'=>$this->canManage(),'products'=>$d->products(),'categories'=>$d->rows("SELECT id,name,slug FROM product_categories ORDER BY sort_order,name"),'brands'=>$d->rows("SELECT id,name FROM brands ORDER BY name")]); }
    public function aliases(): void { if(!$this->requirePermission('products.view')) return; $d=new Dashboard();$this->render('aliases','Product Aliases',['canManage'=>$this->canManage(),'aliases'=>$d->aliases(),'products'=>$d->reviewProductOptions()]); }
    /** LS-14 Module 6.5 (OD-2): new retail prices for the Build page, entered by an admin with the shop link and check date */
    public function newPrices(): void {
        if(!$this->requirePermission('products.manage')) return;
        global $config; $db=Database::connection($config['db']);
        try { $rows=(new \App\Services\Admin\NewPriceReferences($db))->list(); $ready=true; } catch(\PDOException) { $rows=[]; $ready=false; }
        $products=(new \App\Models\Product())->search();
        $this->render('new-prices','ราคาของใหม่อ้างอิง',['rows'=>$rows,'ready'=>$ready,'products'=>$products]);
    }
    public function newPriceAction(): void {
        if(!$this->requirePermission('products.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger',t('flash.csrf')); $this->redirect('/admin/new-prices'); }
        global $config; $svc=new \App\Services\Admin\NewPriceReferences(Database::connection($config['db']));
        $actor=(int)$this->user()['id'];
        $messages=['PRODUCT_NOT_FOUND'=>'ไม่พบสินค้านี้','INVALID_PRICE'=>'ราคาต้องมากกว่า 0 และไม่เกิน 10,000,000 บาท','INVALID_URL'=>'ลิงก์ร้านต้องขึ้นต้นด้วย http:// หรือ https://','INVALID_DATE'=>'วันที่ตรวจต้องเป็นวันที่จริง ไม่เกินวันนี้','NOT_FOUND'=>'ไม่พบรายการนี้'];
        $action=(string)($_POST['action']??'');
        if($action==='add') { $r=$svc->add($actor,(int)($_POST['product_id']??0),(string)($_POST['price']??''),(string)($_POST['source_url']??''),(string)($_POST['checked_on']??''),(string)($_POST['note']??'')); $ok='บันทึกราคาของใหม่อ้างอิงแล้ว'; }
        elseif($action==='delete') { $r=$svc->delete($actor,(int)($_POST['id']??0)); $ok='ลบรายการแล้ว'; }
        else { $r=['ok'=>false,'error'=>'NOT_FOUND']; $ok=''; }
        $this->flash($r['ok']?'success':'danger',$r['ok']?$ok:($messages[$r['error']]??'บันทึกไม่สำเร็จ'));
        $this->redirect('/admin/new-prices');
    }
    public function articles(): void { if(!$this->requirePermission('products.view')) return; $this->render('placeholder','Articles',['description'=>'จัดการบทความ SEO, buying guides และสถานะการเผยแพร่']); }
    public function articlesOperational(): void { if(!$this->requirePermission('products.view')) return; $this->render('articles','Articles',['last_import_summary'=>$_SESSION['admin_import_summary'] ?? null]); }
    public function productsData(): void { if(!$this->requirePermission('products.view')) return; $this->json($this->withoutActions((new AdminDataTableService())->products($_GET,$this->baseUrl()))); }
    public function aliasesData(): void { if(!$this->requirePermission('products.view')) return; $this->json($this->withoutActions((new AdminDataTableService())->aliases($_GET,$this->baseUrl()))); }
    public function articlesData(): void { if(!$this->requirePermission('products.view')) return; $this->json($this->withoutActions((new AdminDataTableService())->articles($_GET,$this->baseUrl()))); }
    public function productSave(): void {
        if(!$this->requirePermission('products.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/products'); }
        global $config;
        $db=Database::connection($config['db']);
        $id=filter_input(INPUT_POST,'product_id',FILTER_VALIDATE_INT) ?: null;
        $categoryId=filter_input(INPUT_POST,'category_id',FILTER_VALIDATE_INT) ?: 0;
        $brandId=filter_input(INPUT_POST,'brand_id',FILTER_VALIDATE_INT) ?: 0;
        $model=trim((string)($_POST['model_name'] ?? ''));
        $full=trim((string)($_POST['full_name'] ?? ''));
        $slug=$this->slug(trim((string)($_POST['slug'] ?? ($full ?: $model))));
        if($categoryId<=0 || $brandId<=0 || $model==='' || $full==='' || $slug===''){
            $this->flash('danger','Product requires category, brand, model, full name, and slug.');
            $this->redirect('/admin/products');
        }
        try{
            if($id){
                $stmt=$db->prepare("UPDATE products SET category_id=:category_id,brand_id=:brand_id,model_name=:model_name,slug=:slug,full_name=:full_name,generation=:generation,spec_summary=:spec_summary,image_path=:image_path,is_active=:is_active WHERE id=:id");
                $stmt->execute(['category_id'=>$categoryId,'brand_id'=>$brandId,'model_name'=>$model,'slug'=>$slug,'full_name'=>$full,'generation'=>trim((string)($_POST['generation'] ?? '')) ?: null,'spec_summary'=>trim((string)($_POST['spec_summary'] ?? '')) ?: null,'image_path'=>trim((string)($_POST['image_path'] ?? '')) ?: null,'is_active'=>isset($_POST['is_active']) ? 1 : 0,'id'=>$id]);
                $this->audit('product_updated','product',$id,['slug'=>$slug,'full_name'=>$full]);
                $this->flash('success','Product updated.');
            } else {
                $stmt=$db->prepare("INSERT INTO products (category_id,brand_id,model_name,slug,full_name,generation,spec_summary,image_path,is_active,created_at,updated_at) VALUES (:category_id,:brand_id,:model_name,:slug,:full_name,:generation,:spec_summary,:image_path,:is_active,NOW(),NOW())");
                $stmt->execute(['category_id'=>$categoryId,'brand_id'=>$brandId,'model_name'=>$model,'slug'=>$slug,'full_name'=>$full,'generation'=>trim((string)($_POST['generation'] ?? '')) ?: null,'spec_summary'=>trim((string)($_POST['spec_summary'] ?? '')) ?: null,'image_path'=>trim((string)($_POST['image_path'] ?? '')) ?: null,'is_active'=>isset($_POST['is_active']) ? 1 : 0]);
                $this->audit('product_created','product',(int)$db->lastInsertId(),['slug'=>$slug,'full_name'=>$full]);
                $this->flash('success','Product created.');
            }
        } catch(\PDOException $e){
            $this->flash('danger',$e->getCode()==='23000' ? 'Product slug already exists.' : 'Product save failed.');
        }
        $this->redirect('/admin/products');
    }
    public function productToggle(): void {
        if(!$this->requirePermission('products.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/products'); }
        $id=filter_input(INPUT_POST,'product_id',FILTER_VALIDATE_INT);
        if($id){
            global $config;
            $db=Database::connection($config['db']);
            $db->prepare("UPDATE products SET is_active=IF(is_active=1,0,1),updated_at=NOW() WHERE id=:id")->execute(['id'=>$id]);
            $this->audit('product_status_toggled','product',(int)$id,[]);
            $this->flash('success','Product status updated.');
        }
        $this->redirect('/admin/products');
    }
    public function aliasSave(): void {
        if(!$this->requirePermission('products.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/product-aliases'); }
        global $config;
        $db=Database::connection($config['db']);
        $id=filter_input(INPUT_POST,'alias_id',FILTER_VALIDATE_INT) ?: null;
        $productId=filter_input(INPUT_POST,'product_id',FILTER_VALIDATE_INT) ?: 0;
        $alias=trim((string)($_POST['alias_text'] ?? ''));
        $normalized=$this->normalizeAlias($alias);
        if($productId<=0 || $alias==='' || $normalized===''){
            $this->flash('danger','Alias requires product and alias text.');
            $this->redirect('/admin/product-aliases');
        }
        try{
            if($id){
                $stmt=$db->prepare("UPDATE product_aliases SET product_id=:product_id,alias_text=:alias_text,normalized_alias=:normalized_alias,source_note=:source_note,confidence=:confidence WHERE id=:id");
                $stmt->execute(['product_id'=>$productId,'alias_text'=>$alias,'normalized_alias'=>$normalized,'source_note'=>trim((string)($_POST['source_note'] ?? '')) ?: null,'confidence'=>max(0,min(100,(float)($_POST['confidence'] ?? 100))),'id'=>$id]);
                $this->audit('product_alias_updated','product_alias',$id,['alias_text'=>$alias]);
                $this->flash('success','Alias updated.');
            } else {
                $stmt=$db->prepare("INSERT INTO product_aliases (product_id,alias_text,normalized_alias,source_note,confidence,created_at,updated_at) VALUES (:product_id,:alias_text,:normalized_alias,:source_note,:confidence,NOW(),NOW())");
                $stmt->execute(['product_id'=>$productId,'alias_text'=>$alias,'normalized_alias'=>$normalized,'source_note'=>trim((string)($_POST['source_note'] ?? '')) ?: null,'confidence'=>max(0,min(100,(float)($_POST['confidence'] ?? 100)))]);
                $this->audit('product_alias_created','product_alias',(int)$db->lastInsertId(),['alias_text'=>$alias]);
                $this->flash('success','Alias created.');
            }
        } catch(\PDOException $e){
            $this->flash('danger',$e->getCode()==='23000' ? 'Alias already exists after normalization.' : 'Alias save failed.');
        }
        $this->redirect('/admin/product-aliases');
    }
    public function aliasDelete(): void {
        if(!$this->requirePermission('products.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/product-aliases'); }
        $id=filter_input(INPUT_POST,'alias_id',FILTER_VALIDATE_INT);
        if($id){
            global $config;
            Database::connection($config['db'])->prepare("DELETE FROM product_aliases WHERE id=:id")->execute(['id'=>$id]);
            $this->audit('product_alias_deleted','product_alias',(int)$id,[]);
            $this->flash('success','Alias deleted.');
        }
        $this->redirect('/admin/product-aliases');
    }
    /**
     * LS-26: bulk alias import. Two steps on one endpoint and nothing kept between them: "check" shows what each line would do and writes nothing; "confirm" re-reads the
     * same text, inserts only the lines that are new (one transaction), and audits the batch with the acting admin.
     */
    public function aliasImport(): void {
        if(!$this->requirePermission('products.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/product-aliases'); }
        global $config;
        $db=Database::connection($config['db']);
        $text=substr((string)($_POST['alias_lines'] ?? ''),0,200000);
        $ids=[]; foreach($db->query("SELECT id,slug FROM products")->fetchAll(\PDO::FETCH_ASSOC) as $r){ $ids[strtolower((string)$r['slug'])]=(int)$r['id']; }
        $existing=[]; foreach($db->query("SELECT normalized_alias,product_id FROM product_aliases")->fetchAll(\PDO::FETCH_ASSOC) as $r){ $existing[(string)$r['normalized_alias']]=(int)$r['product_id']; }
        $plan=\App\Services\Catalog\AliasImport::plan($text,$ids,$existing);
        if(($_POST['mode'] ?? '')==='confirm' && $plan['counts']['new']>0){
            $db->beginTransaction();
            try{
                $stmt=$db->prepare("INSERT INTO product_aliases (product_id,alias_text,normalized_alias,source_note,confidence,created_at,updated_at) VALUES (:p,:a,:n,:s,100,NOW(),NOW())");
                $added=0;
                foreach($plan['rows'] as $r){ if($r['status']!=='new') continue; $stmt->execute(['p'=>$ids[$r['slug']],'a'=>$r['alias'],'n'=>$this->normalizeAlias($r['alias']),'s'=>$r['source'] ?? 'Bulk import']); $added++; }
                $db->commit();
            }catch(\Throwable $e){ if($db->inTransaction()) $db->rollBack(); $this->flash('danger','Alias import failed; nothing was saved.'); $this->redirect('/admin/product-aliases'); }
            $this->audit('product_aliases_imported','product_alias',null,['added'=>$added,'skipped'=>array_sum($plan['counts'])-$added]);
            $this->flash('success',t('msg.imported_aliases',['n'=>$added]));
            $this->redirect('/admin/product-aliases');
        }
        $d=new Dashboard();
        $this->render('aliases','Product Aliases',['canManage'=>$this->canManage(),'aliases'=>$d->aliases(),'products'=>$d->reviewProductOptions(),'import'=>$plan,'importText'=>$text]);
    }
    /** LS-55: the optional category of an article, written only when the database has the column (migration 030) */
    private function saveArticleCategory(\PDO $db,int $id): void {
        if($id<1 || !\App\Services\Blog\ArticleCategory::columnExists($db)) return;
        $db->prepare('UPDATE articles SET category=:c WHERE id=:id')->execute(['c'=>\App\Services\Blog\ArticleCategory::normalize($_POST['category'] ?? null),'id'=>$id]);
    }
    public function articleSave(): void {
        if(!$this->requirePermission('products.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/articles'); }
        global $config;
        $db=Database::connection($config['db']);
        $id=filter_input(INPUT_POST,'article_id',FILTER_VALIDATE_INT) ?: null;
        $title=trim((string)($_POST['title'] ?? ''));
        $slug=$this->slug(trim((string)($_POST['slug'] ?? $title)));
        $status=(string)($_POST['status'] ?? 'draft'); if(!in_array($status,['draft','published','archived'],true)){ $status='draft'; }   // LS-20 C4: same fix, a missing status used to be saved as ''
        $body=trim((string)($_POST['body'] ?? ''));
        if($title==='' || $slug==='' || $body===''){
            $this->flash('danger','Article requires title, slug, and body.');
            $this->redirect('/admin/articles');
        }
        $pubAt=\App\Services\Blog\PublishAt::parse(isset($_POST['publish_at']) ? (string)$_POST['publish_at'] : null);   // LS-25: optional future publish time
        try{
            if($id){
                $stmt=$db->prepare("UPDATE articles SET title=:title,slug=:slug,excerpt=:excerpt,body=:body,status=:status,published_at=CASE WHEN :status_pub0='published' AND :pub_at0 IS NOT NULL THEN :pub_at1 WHEN :status_pub='published' AND published_at IS NULL THEN NOW() WHEN :status_pub2='published' THEN published_at ELSE NULL END,updated_at=NOW() WHERE id=:id");
                $stmt->execute(['title'=>$title,'slug'=>$slug,'excerpt'=>trim((string)($_POST['excerpt'] ?? '')) ?: null,'body'=>$body,'status'=>$status,'status_pub'=>$status,'status_pub2'=>$status,'status_pub0'=>$status,'pub_at0'=>$pubAt,'pub_at1'=>$pubAt,'id'=>$id]);
                $this->saveArticleCategory($db,(int)$id);
                $this->audit('article_updated','article',$id,['slug'=>$slug,'status'=>$status]);
                $this->flash('success','Article updated.');
            } else {
                $stmt=$db->prepare("INSERT INTO articles (author_id,title,slug,excerpt,body,status,published_at,created_at,updated_at) VALUES (:author_id,:title,:slug,:excerpt,:body,:status,CASE WHEN :status_pub0='published' AND :pub_at0 IS NOT NULL THEN :pub_at1 WHEN :status_pub='published' THEN NOW() ELSE NULL END,NOW(),NOW())");
                $stmt->execute(['author_id'=>(int)$this->user()['id'],'title'=>$title,'slug'=>$slug,'excerpt'=>trim((string)($_POST['excerpt'] ?? '')) ?: null,'body'=>$body,'status'=>$status,'status_pub'=>$status,'status_pub0'=>$status,'pub_at0'=>$pubAt,'pub_at1'=>$pubAt]);
                $this->saveArticleCategory($db,(int)$db->lastInsertId());
                $this->audit('article_created','article',(int)$db->lastInsertId(),['slug'=>$slug,'status'=>$status]);
                $this->flash('success','Article created.');
            }
        } catch(\PDOException $e){
            $this->flash('danger',$e->getCode()==='23000' ? 'Article slug already exists.' : 'Article save failed.');
        }
        $this->redirect('/admin/articles');
    }
    public function articleArchive(): void {
        if(!$this->requirePermission('products.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/articles'); }
        $id=filter_input(INPUT_POST,'article_id',FILTER_VALIDATE_INT);
        if($id){
            global $config;
            Database::connection($config['db'])->prepare("UPDATE articles SET status='archived',published_at=NULL,updated_at=NOW() WHERE id=:id")->execute(['id'=>$id]);
            $this->audit('article_archived','article',(int)$id,[]);
            $this->flash('success','Article archived.');
        }
        $this->redirect('/admin/articles');
    }
    /** LS-60: add, list and delete in-game FPS results (CPU/GPU pages); same validation as cli/game_fps_import.php */
    public function gameFpsPage(): void {
        if(!$this->requirePermission('products.view')) return;
        global $config; $db=Database::connection($config['db']);
        $has=\App\Services\Catalog\GameFpsImport::tableExists($db);
        $rows=$has ? $db->query("SELECT r.id,r.game_key,r.resolution,r.quality,r.avg_fps,r.low1_fps,r.paired_part,r.source_name,r.source_url,r.sponsor_name,p.full_name FROM game_fps_results r JOIN products p ON p.id=r.product_id ORDER BY r.id DESC LIMIT 200")->fetchAll() : [];
        $this->render('game-fps','Game FPS',['has_table'=>$has,'rows'=>$rows,'products'=>$has ? \App\Services\Catalog\GameFpsImport::products($db) : [],'can_manage'=>$this->canManage()]);
    }
    public function gameFpsSave(): void {
        if(!$this->requirePermission('products.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/game-fps'); }
        global $config; $db=Database::connection($config['db']);
        if(!\App\Services\Catalog\GameFpsImport::tableExists($db)) { $this->flash('danger','Migration 031 is not applied on this database.'); $this->redirect('/admin/game-fps'); }
        $pid=(int)($_POST['product_id'] ?? 0);
        $known=array_map('intval',array_column(\App\Services\Catalog\GameFpsImport::products($db),'id'));
        $c=\App\Services\Catalog\GameFpsImport::clean($_POST);
        if(!in_array($pid,$known,true)) { $this->flash('danger','Choose a CPU or GPU model.'); }
        elseif($c['data']===null) { $this->flash('danger',t('msg.not_saved',['e'=>$c['error']])); }
        else {
            \App\Services\Catalog\GameFpsImport::store($db,$pid,$c['data']);
            $this->audit('game_fps_saved','product',$pid,['game'=>$c['data']['game_key'],'resolution'=>$c['data']['resolution'],'sponsor'=>$c['data']['sponsor_name']]);
            $this->flash('success','Result saved.');
        }
        $this->redirect('/admin/game-fps');
    }
    public function gameFpsDelete(): void {
        if(!$this->requirePermission('products.manage')) return;
        if(!Csrf::verify($_POST['_token']??null)) { $this->flash('danger','Session expired. Please retry.'); $this->redirect('/admin/game-fps'); }
        $id=filter_input(INPUT_POST,'result_id',FILTER_VALIDATE_INT);
        if($id){
            global $config;
            Database::connection($config['db'])->prepare("DELETE FROM game_fps_results WHERE id=:id")->execute(['id'=>$id]);
            $this->audit('game_fps_deleted','game_fps_result',(int)$id,[]);
            $this->flash('success','Result deleted.');
        }
        $this->redirect('/admin/game-fps');
    }
    private function slug(string $value): string {
        $slug=preg_replace('/[^a-z0-9ก-๙]+/iu','-',mb_strtolower(trim($value),'UTF-8')) ?? '';
        return trim($slug,'-');
    }
    private function normalizeAlias(string $value): string { return \App\Services\Catalog\AliasImport::normalize($value); }
}
