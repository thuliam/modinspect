USE modinspect;
INSERT INTO product_categories (name,slug,sort_order) VALUES ('CPU','cpu',1),('GPU','gpu',2),('RAM','ram',3),('Storage','storage',4);
INSERT INTO brands (name,slug) VALUES ('AMD','amd'),('NVIDIA','nvidia'),('Intel','intel');
INSERT INTO products (category_id,brand_id,model_name,slug,full_name,generation,release_year,spec_summary,tdp_watt,is_popular) VALUES
(1,1,'Ryzen 7 5700X3D','amd-ryzen-7-5700x3d','AMD Ryzen 7 5700X3D','Zen 3',2024,'8 cores / 16 threads, AM4, 96MB L3 cache',105,1),
(2,2,'GeForce RTX 3070','nvidia-geforce-rtx-3070','NVIDIA GeForce RTX 3070','Ampere',2020,'8GB GDDR6, suitable for 1440p gaming',220,1),
(1,3,'Core i5-12400F','intel-core-i5-12400f','Intel Core i5-12400F','Alder Lake',2022,'6 cores / 12 threads, LGA1700',65,1);
INSERT INTO product_aliases (product_id,alias_text,normalized_alias,confidence) VALUES
(1,'5700x3d','5700x3d',100),(1,'r7 5700 x3d','r75700x3d',95),(2,'RTX3070','rtx3070',100),(3,'12400f','12400f',100);
INSERT INTO price_indices (product_id,price_type,price_low,q1,median,q3,price_high,fast_sale_min,fast_sale_max,market_min,market_max,premium_min,premium_max,sample_size,valid_sample_size,fresh_sample_ratio,confidence_score,confidence_label,last_calculated_at) VALUES
(1,'asking',6900,7200,7500,7800,8300,6800,7200,7200,7800,7800,8400,8,6,87.5,68,'medium',NOW()),
(2,'asking',6500,7200,7800,8500,9500,6600,7200,7200,8500,8500,9600,18,15,80,82,'high',NOW()),
(3,'asking',2600,2900,3200,3500,3900,2700,3000,2900,3500,3500,4000,12,10,90,78,'medium',NOW());
INSERT INTO price_histories (product_id,q1,median,q3,sample_size,confidence_score,snapshot_date) VALUES
(1,7600,8000,8400,5,55,'2026-05-01'),(1,7400,7800,8100,6,62,'2026-06-01'),(1,7200,7500,7800,6,68,'2026-07-01');
INSERT INTO data_sources (source_key,name,domain,source_type,access_method,allowed_collection_method,risk_level,reliability_score,evidence_quality,freshness_expectation_days,last_success_at) VALUES
('community_sample','Community sample','community.example','forum','manual','manual','medium',65,'medium',14,NOW()),
('user_submission','User submission',NULL,'user','upload','upload','low',90,'high',30,NOW()),
('shop_partner_sample','Shop partner sample','shop.example','shop','feed','feed','low',85,'high',7,NOW()),
('gemini_google_search','Gemini Google Search POC','googleapis.com','research','api','api','medium',60,'medium',7,NOW());
INSERT INTO shops (name,slug,email,plan) VALUES ('Radar Demo Shop','radar-demo','demo@example.test','professional');
INSERT INTO shop_inventory_items (shop_id,product_id,sku,raw_product_name,cost_price,asking_price,quantity,condition_level,acquired_at) VALUES
(1,1,'CPU-5700X3D-01','Ryzen 7 5700X3D',6500,7900,2,'good','2026-06-12'),
(1,2,'GPU-RTX3070-01','RTX 3070 8GB',6900,8600,1,'fair','2026-05-01'),
(1,3,'CPU-12400F-01','Core i5 12400F',2500,3400,3,'good','2026-07-10');
