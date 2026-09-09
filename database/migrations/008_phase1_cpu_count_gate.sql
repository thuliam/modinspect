USE modinspect;

INSERT IGNORE INTO products (category_id,brand_id,model_name,slug,full_name,generation,release_year,spec_summary,tdp_watt,recommended_psu_watt,socket,memory_type,image_path,is_popular,is_active) VALUES
((SELECT id FROM product_categories WHERE slug='cpu'),(SELECT id FROM brands WHERE slug='intel'),'Core i7-12700F','intel-core-i7-12700f','Intel Core i7-12700F','Alder Lake',2022,'12 cores / 20 threads, LGA1700',65,650,'LGA1700','DDR4/DDR5','assets/images/admin-4.jpg',1,1);

INSERT INTO price_indices (product_id,price_type,price_low,q1,median,q3,price_high,market_min,market_max,sample_size,valid_sample_size,fresh_sample_ratio,confidence_score,confidence_label,last_calculated_at)
SELECT p.id,'asking',6200,6800,7400,8200,9000,6800,8200,12,10,85,76,'medium',NOW()
FROM products p
WHERE p.slug='intel-core-i7-12700f'
  AND NOT EXISTS (SELECT 1 FROM price_indices pi WHERE pi.product_id=p.id AND pi.product_variant_id IS NULL);

INSERT IGNORE INTO product_aliases (product_id,alias_text,normalized_alias,source_note,confidence)
SELECT p.id, a.alias_text, a.normalized_alias, 'Phase 1 POC alias fixture', a.confidence
FROM products p JOIN (
 SELECT 'intel-core-i7-12700f' slug,'Core i7 12700F' alias_text,'corei712700f' normalized_alias,100 confidence UNION ALL
 SELECT 'intel-core-i7-12700f','i7 12700F','i712700f',100 UNION ALL
 SELECT 'intel-core-i7-12700f','12700F','12700f',100 UNION ALL
 SELECT 'intel-core-i7-12700f','Intel 12700F','intel12700f',98 UNION ALL
 SELECT 'intel-core-i7-12700f','i7-12700F','i712700fdash',100
) a ON a.slug=p.slug;
