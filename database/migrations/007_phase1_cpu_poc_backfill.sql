USE modinspect;

INSERT IGNORE INTO products (category_id,brand_id,model_name,slug,full_name,generation,release_year,spec_summary,tdp_watt,recommended_psu_watt,socket,memory_type,image_path,is_popular,is_active) VALUES
((SELECT id FROM product_categories WHERE slug='cpu'),(SELECT id FROM brands WHERE slug='amd'),'Ryzen 5 5600','amd-ryzen-5-5600','AMD Ryzen 5 5600','Zen 3',2022,'6 cores / 12 threads, unlocked',65,550,'AM4','DDR4','assets/images/admin-2.jpg',1,1),
((SELECT id FROM product_categories WHERE slug='cpu'),(SELECT id FROM brands WHERE slug='amd'),'Ryzen 7 5800X','amd-ryzen-7-5800x','AMD Ryzen 7 5800X','Zen 3',2020,'8 cores / 16 threads, unlocked',105,650,'AM4','DDR4','assets/images/admin-2.jpg',1,1),
((SELECT id FROM product_categories WHERE slug='cpu'),(SELECT id FROM brands WHERE slug='amd'),'Ryzen 5 7600','amd-ryzen-5-7600','AMD Ryzen 5 7600','Zen 4',2023,'6 cores / 12 threads, integrated graphics',65,550,'AM5','DDR5','assets/images/admin-2.jpg',1,1),
((SELECT id FROM product_categories WHERE slug='cpu'),(SELECT id FROM brands WHERE slug='amd'),'Ryzen 7 7800X3D','amd-ryzen-7-7800x3d','AMD Ryzen 7 7800X3D','Zen 4',2023,'8 cores / 16 threads, 3D V-Cache',120,650,'AM5','DDR5','assets/images/admin-2.jpg',1,1),
((SELECT id FROM product_categories WHERE slug='cpu'),(SELECT id FROM brands WHERE slug='intel'),'Core i3-12100F','intel-core-i3-12100f','Intel Core i3-12100F','Alder Lake',2022,'4 cores / 8 threads',58,500,'LGA1700','DDR4/DDR5','assets/images/admin-4.jpg',0,1),
((SELECT id FROM product_categories WHERE slug='cpu'),(SELECT id FROM brands WHERE slug='intel'),'Core i5-13400F','intel-core-i5-13400f','Intel Core i5-13400F','Raptor Lake',2023,'10 cores / 16 threads',65,600,'LGA1700','DDR4/DDR5','assets/images/admin-4.jpg',1,1),
((SELECT id FROM product_categories WHERE slug='cpu'),(SELECT id FROM brands WHERE slug='intel'),'Core i5-13600K','intel-core-i5-13600k','Intel Core i5-13600K','Raptor Lake',2022,'14 cores / 20 threads, unlocked',125,700,'LGA1700','DDR4/DDR5','assets/images/admin-4.jpg',1,1);

INSERT INTO price_indices (product_id,price_type,price_low,q1,median,q3,price_high,market_min,market_max,sample_size,valid_sample_size,fresh_sample_ratio,confidence_score,confidence_label,last_calculated_at)
SELECT p.id,'asking',v.low,v.q1,v.med,v.q3,v.high,v.q1,v.q3,16,13,88,80,'high',NOW()
FROM products p JOIN (
 SELECT 'amd-ryzen-5-5600' slug,2300 low,2600 q1,2900 med,3200 q3,3500 high UNION ALL
 SELECT 'amd-ryzen-7-5800x',4200,4600,5000,5500,6000 UNION ALL
 SELECT 'amd-ryzen-5-7600',5200,5700,6200,6800,7400 UNION ALL
 SELECT 'amd-ryzen-7-7800x3d',10500,11200,11900,12600,13400 UNION ALL
 SELECT 'intel-core-i3-12100f',1900,2200,2500,2800,3100 UNION ALL
 SELECT 'intel-core-i5-13400f',4700,5200,5700,6200,6800 UNION ALL
 SELECT 'intel-core-i5-13600k',7300,7900,8500,9200,9900
) v ON v.slug=p.slug
WHERE NOT EXISTS (SELECT 1 FROM price_indices pi WHERE pi.product_id=p.id AND pi.product_variant_id IS NULL);
