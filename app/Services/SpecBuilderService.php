<?php
declare(strict_types=1);
namespace App\Services;

final class SpecBuilderService
{
    public function generate(array $products, float $budget, string $use, string $resolution, string $mix, array $selectedIds = []): array
    {
        $bySlug=[];
        $products=array_values(array_filter($products,fn($product)=>!empty($product['median']) && (int)($product['valid_sample_size']??0)>0 && (int)($product['accepted_observation_count']??0)>0));
        foreach($products as $product) $bySlug[$product['slug']]=$product;
        $byId=[]; foreach($products as $product) $byId[(int)$product['id']]=$product;
        $selected=[];
        foreach(array_filter($selectedIds) as $selectedId) if(isset($byId[(int)$selectedId])) $selected[]=$byId[(int)$selectedId];
        $cpu = !empty($selectedIds['cpu']) ? ($byId[(int)$selectedIds['cpu']] ?? null) : null;
        $gpu = !empty($selectedIds['gpu']) ? ($byId[(int)$selectedIds['gpu']] ?? null) : null;
        if(!$selected){
            $cpu=$budget<22000?($bySlug['intel-core-i5-12400f']??null):($bySlug['amd-ryzen-7-5700x3d']??null);
            $gpu=in_array($use,['gaming','streaming','ai'],true)?($bySlug['nvidia-geforce-rtx-3070']??null):null;
            $selected=array_values(array_filter([$cpu,$gpu]));
        }
        if (!$selected) return ['items'=>[],'low'=>0,'expected'=>0,'high'=>0,'notes'=>'ไม่มีข้อมูลราคาที่เหมาะสมเพียงพอ'];
        $items=[];$low=$expected=$high=0;
        foreach($selected as $p){
            $role=strtolower((string)$p['category_name']);
            $items[]=['product_id'=>(int)$p['id'],'component_role'=>$role,'price_low'=>(float)$p['q1'],'price_expected'=>(float)$p['median'],'price_high'=>(float)$p['q3'],'confidence_score'=>(float)$p['confidence_score'],'is_alternative'=>0,'notes'=>'อ้างอิงดัชนีราคาประกาศล่าสุด'];
            $low+=(float)$p['q1'];$expected+=(float)$p['median'];$high+=(float)$p['q3'];
        }
        $reserve=max(0,$budget-$expected);
        $motherboard=null;$ram=null;
        foreach($selected as $part){
            $category=strtolower((string)$part['category_name']);
            if($category==='motherboard')$motherboard=$part;
            if($category==='ram')$ram=$part;
        }
        $notes='ตรวจ socket เมนบอร์ด, กำลัง PSU และขนาดเคสก่อนซื้อ';
        $compatibilityError=null;
        if($cpu&&$motherboard&&$cpu['socket']!==$motherboard['socket']) {$notes='ไม่รองรับ: CPU socket '.$cpu['socket'].' แต่เมนบอร์ดเป็น '.$motherboard['socket'];$compatibilityError=$notes;}
        elseif($cpu&&$motherboard) $notes='CPU และเมนบอร์ดรองรับ socket '.$cpu['socket'];
        if($ram&&$motherboard&&$ram['memory_type']!==$motherboard['memory_type']) {$memoryError='RAM '.$ram['memory_type'].' ไม่ตรงกับเมนบอร์ด '.$motherboard['memory_type'];$notes.='; '.$memoryError;$compatibilityError=$compatibilityError?($compatibilityError.'; '.$memoryError):$memoryError;}
        if($gpu && $resolution==='4k') $notes.='; RTX 3070 เหมาะกับ 1440p มากกว่า 4K และควรพิจารณา GPU ระดับสูงกว่า';
        if($mix==='new') $notes.='; ดัชนีนี้เป็นราคามือสอง จึงควรเทียบราคาของใหม่เพิ่มเติม';
        $selectedCategories=array_map(fn($p)=>strtolower((string)$p['category_name']),$selected);
        $missing=array_values(array_diff(['motherboard','ram','storage','psu','case','cooling'],$selectedCategories));
        $notes.="; งบคงเหลือโดยประมาณ ฿".number_format($reserve).($missing?' สำหรับ '.implode(', ',$missing):' เป็นงบสำรอง');
        return compact('items','low','expected','high','notes','compatibilityError');
    }
}
