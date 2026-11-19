<?php
declare(strict_types=1);
namespace App\Services;

final class SpecBuilderService
{
    /** a part counts only with a market price from accepted rows (the same rule the Build page uses to enable a choice and to label a category) */
    public static function isPriced(array $product): bool
    {
        return !empty($product['median']) && (int)($product['valid_sample_size']??0)>0 && (int)($product['accepted_observation_count']??0)>0;
    }

    /** LS-11 A: the parts recommended for the Build page (see SpecRecommender) */
    public function recommend(array $products, float $budget, string $use, string $resolution, string $preset): array { return (new SpecRecommender())->recommend($products, $budget, $use, $resolution, $preset); }

    /**
     * @param array<int,array> $newRefs LS-14 OD-2: latest admin-entered NEW retail price per product id (new_price_references), used only for a chosen
     *                                  part that has no used-market price, and always labelled; a chosen part with neither is kept with no price
     */
    public function generate(array $products, float $budget, string $use, string $resolution, string $mix, array $selectedIds = [], array $newRefs = []): array
    {
        $catalogById=[]; foreach($products as $product) $catalogById[(int)$product['id']]=$product;
        $products=array_values(array_filter($products,[self::class,'isPriced']));
        $byId=[]; foreach($products as $product) $byId[(int)$product['id']]=$product;
        $selected=[];
        // a part the visitor chose counts whatever its price basis; the default plan below still uses used-market prices only
        foreach(array_filter($selectedIds) as $selectedId) if(isset($catalogById[(int)$selectedId])) $selected[]=$catalogById[(int)$selectedId];
        $cpu = !empty($selectedIds['cpu']) ? ($catalogById[(int)$selectedIds['cpu']] ?? null) : null;
        $gpu = !empty($selectedIds['gpu']) ? ($catalogById[(int)$selectedIds['gpu']] ?? null) : null;
        if(!$selected){
            // nothing chosen (LS-13 S-6): the same balanced plan the recommend button gives, built from the priced catalog, instead of three hard-coded models
            $rec=$this->recommend($products,$budget,$use,$resolution,'balanced')['selected'];
            foreach($rec as $recId) if(isset($byId[(int)$recId])) $selected[]=$byId[(int)$recId];
            $cpu=isset($rec['cpu'])?($byId[(int)$rec['cpu']]??null):null;
            $gpu=isset($rec['gpu'])?($byId[(int)$rec['gpu']]??null):null;
        }
        if (!$selected) return ['items'=>[],'low'=>0,'expected'=>0,'high'=>0,'notes'=>'ไม่มีข้อมูลราคาที่เหมาะสมเพียงพอ'];
        $items=[];$prices=[];
        foreach($selected as $p){
            $role=strtolower((string)$p['category_name']);
            $price=\App\Services\Build\BuildPricing::for($p,$newRefs[(int)$p['id']]??null);
            $prices[]=$price;
            $note=$price['basis']==='used'?'อ้างอิงดัชนีราคามือสองที่ผ่านการตรวจแล้ว':($price['basis']==='new_ref'?$price['label'].' ตรวจเมื่อ '.$price['checked_on']:$price['label']);
            $items[]=['product_id'=>(int)$p['id'],'component_role'=>$role,'price_low'=>$price['low'],'price_expected'=>$price['expected'],'price_high'=>$price['high'],'confidence_score'=>$price['basis']==='used'?(float)$p['confidence_score']:0.0,'is_alternative'=>0,'notes'=>$note];
        }
        $totals=\App\Services\Build\BuildPricing::totals($prices);
        $low=$totals['low'];$expected=$totals['expected'];$high=$totals['high'];
        $reserve=max(0,$budget-$expected);
        $motherboard=null;$ram=null;
        foreach($selected as $part){
            $category=strtolower((string)$part['category_name']);
            if($category==='motherboard')$motherboard=$part;
            if($category==='ram')$ram=$part;
        }
        $notes='ตรวจ socket เมนบอร์ด, กำลัง PSU และขนาดเคสก่อนซื้อ';
        $compatibilityError=null;
        // an EMPTY value in the catalog means "unknown", never "different": only two known, different values are an incompatibility
        $known=static fn($v): bool => trim((string)$v)!=='';
        if($cpu&&$motherboard&&$known($cpu['socket'])&&$known($motherboard['socket'])&&$cpu['socket']!==$motherboard['socket']) {$notes='ไม่รองรับ: CPU socket '.$cpu['socket'].' แต่เมนบอร์ดเป็น '.$motherboard['socket'];$compatibilityError=$notes;}
        elseif($cpu&&$motherboard&&(!$known($cpu['socket'])||!$known($motherboard['socket']))) $notes='ไม่ทราบ socket ของ CPU หรือเมนบอร์ด กรุณาตรวจ socket เองก่อนซื้อ';
        elseif($cpu&&$motherboard) $notes='CPU และเมนบอร์ดรองรับ socket '.$cpu['socket'];
        if($ram&&$motherboard&&(!$known($ram['memory_type'])||!$known($motherboard['memory_type']))) $notes.='; ไม่ทราบชนิดหน่วยความจำของ RAM หรือเมนบอร์ด กรุณาตรวจเองว่าเป็นชนิดเดียวกัน';
        elseif($ram&&$motherboard&&$ram['memory_type']!==$motherboard['memory_type']) {$memoryError='RAM '.$ram['memory_type'].' ไม่ตรงกับเมนบอร์ด '.$motherboard['memory_type'];$notes.='; '.$memoryError;$compatibilityError=$compatibilityError?($compatibilityError.'; '.$memoryError):$memoryError;}
        if($gpu && $resolution==='4k' && ($gpu['slug']??'')==='nvidia-geforce-rtx-3070') $notes.='; RTX 3070 เหมาะกับ 1440p มากกว่า 4K และควรพิจารณา GPU ระดับสูงกว่า';
        if($mix==='new') $notes.='; ดัชนีนี้เป็นราคามือสอง จึงควรเทียบราคาของใหม่เพิ่มเติม';
        $selectedCategories=array_map(fn($p)=>strtolower((string)$p['category_name']),$selected);
        $missing=array_values(array_diff(['motherboard','ram','storage','psu','case','cooling'],$selectedCategories));
        // the page's own Thai names for the categories (never raw keys inside Thai text)
        $labels=['motherboard'=>'เมนบอร์ด','ram'=>'RAM','storage'=>'ที่เก็บข้อมูล','psu'=>'พาวเวอร์ซัพพลาย','case'=>'เคส','cooling'=>'ชุดระบายความร้อน'];
        $notes.="; งบคงเหลือโดยประมาณ ฿".number_format($reserve).($missing?' สำหรับ '.implode(', ',array_map(fn($m)=>$labels[$m]??$m,$missing)):' เป็นงบสำรอง');
        if($expected>$budget) $notes.='; เกินงบประมาณ ฿'.number_format($expected-$budget);
        if($totals['new_ref']>0||$totals['none']>0) $notes.='; '.\App\Services\Build\BuildPricing::totalsNote($totals);
        return compact('items','low','expected','high','notes','compatibilityError');
    }
}
