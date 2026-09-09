USE modinspect;

INSERT IGNORE INTO products (category_id,brand_id,model_name,slug,full_name,generation,release_year,spec_summary,tdp_watt,recommended_psu_watt,image_path,is_popular,is_active) VALUES
((SELECT id FROM product_categories WHERE slug='gpu'),(SELECT id FROM brands WHERE slug='nvidia'),'GeForce GTX 1660 Super','nvidia-geforce-gtx-1660-super','NVIDIA GeForce GTX 1660 Super','Turing',2019,'6GB GDDR6, 1080p gaming',125,450,'assets/images/admin-3.jpg',1,1),
((SELECT id FROM product_categories WHERE slug='gpu'),(SELECT id FROM brands WHERE slug='nvidia'),'GeForce RTX 2060','nvidia-geforce-rtx-2060','NVIDIA GeForce RTX 2060','Turing',2019,'6GB GDDR6, entry ray tracing GPU',160,500,'assets/images/admin-3.jpg',1,1),
((SELECT id FROM product_categories WHERE slug='gpu'),(SELECT id FROM brands WHERE slug='nvidia'),'GeForce RTX 3060 Ti','nvidia-geforce-rtx-3060-ti','NVIDIA GeForce RTX 3060 Ti','Ampere',2020,'8GB GDDR6, strong 1080p/1440p gaming',200,600,'assets/images/admin-3.jpg',1,1),
((SELECT id FROM product_categories WHERE slug='gpu'),(SELECT id FROM brands WHERE slug='nvidia'),'GeForce RTX 3080','nvidia-geforce-rtx-3080','NVIDIA GeForce RTX 3080','Ampere',2020,'10GB GDDR6X, high-end 1440p/4K gaming',320,750,'assets/images/admin-3.jpg',1,1),
((SELECT id FROM product_categories WHERE slug='gpu'),(SELECT id FROM brands WHERE slug='nvidia'),'GeForce RTX 4060 Ti','nvidia-geforce-rtx-4060-ti','NVIDIA GeForce RTX 4060 Ti','Ada Lovelace',2023,'8GB/16GB class, efficient 1080p gaming',160,550,'assets/images/admin-3.jpg',1,1),
((SELECT id FROM product_categories WHERE slug='gpu'),(SELECT id FROM brands WHERE slug='nvidia'),'GeForce RTX 4070','nvidia-geforce-rtx-4070','NVIDIA GeForce RTX 4070','Ada Lovelace',2023,'12GB GDDR6X, efficient 1440p gaming',200,650,'assets/images/admin-3.jpg',1,1),
((SELECT id FROM product_categories WHERE slug='gpu'),(SELECT id FROM brands WHERE slug='nvidia'),'GeForce RTX 4070 Ti','nvidia-geforce-rtx-4070-ti','NVIDIA GeForce RTX 4070 Ti','Ada Lovelace',2023,'12GB GDDR6X, high-refresh 1440p gaming',285,700,'assets/images/admin-3.jpg',1,1),
((SELECT id FROM product_categories WHERE slug='gpu'),(SELECT id FROM brands WHERE slug='amd'),'Radeon RX 6600 XT','amd-radeon-rx-6600-xt','AMD Radeon RX 6600 XT','RDNA 2',2021,'8GB GDDR6, 1080p gaming',160,500,'assets/images/admin-3.jpg',1,1),
((SELECT id FROM product_categories WHERE slug='gpu'),(SELECT id FROM brands WHERE slug='amd'),'Radeon RX 6700 XT','amd-radeon-rx-6700-xt','AMD Radeon RX 6700 XT','RDNA 2',2021,'12GB GDDR6, 1440p gaming',230,650,'assets/images/admin-3.jpg',1,1),
((SELECT id FROM product_categories WHERE slug='gpu'),(SELECT id FROM brands WHERE slug='amd'),'Radeon RX 7800 XT','amd-radeon-rx-7800-xt','AMD Radeon RX 7800 XT','RDNA 3',2023,'16GB GDDR6, 1440p gaming',263,700,'assets/images/admin-3.jpg',1,1);

INSERT INTO price_indices (product_id,price_type,price_low,q1,median,q3,price_high,market_min,market_max,sample_size,valid_sample_size,fresh_sample_ratio,confidence_score,confidence_label,last_calculated_at)
SELECT p.id,'asking',v.low,v.q1,v.med,v.q3,v.high,v.q1,v.q3,10,8,80,72,'medium',NOW()
FROM products p JOIN (
 SELECT 'nvidia-geforce-gtx-1660-super' slug,2800 low,3300 q1,3800 med,4300 q3,5000 high UNION ALL
 SELECT 'nvidia-geforce-rtx-2060',3600,4200,4800,5400,6200 UNION ALL
 SELECT 'nvidia-geforce-rtx-3060-ti',5800,6600,7300,8200,9200 UNION ALL
 SELECT 'nvidia-geforce-rtx-3080',10500,11800,13000,14500,16500 UNION ALL
 SELECT 'nvidia-geforce-rtx-4060-ti',9500,10800,11900,13200,15000 UNION ALL
 SELECT 'nvidia-geforce-rtx-4070',15500,17000,18500,20500,23000 UNION ALL
 SELECT 'nvidia-geforce-rtx-4070-ti',22000,24000,26000,28500,31500 UNION ALL
 SELECT 'amd-radeon-rx-6600-xt',4200,4800,5400,6100,7000 UNION ALL
 SELECT 'amd-radeon-rx-6700-xt',7500,8400,9300,10400,11800 UNION ALL
 SELECT 'amd-radeon-rx-7800-xt',15000,16500,18200,20000,22500
) v ON v.slug=p.slug
WHERE NOT EXISTS (SELECT 1 FROM price_indices pi WHERE pi.product_id=p.id AND pi.product_variant_id IS NULL);

INSERT IGNORE INTO product_aliases (product_id,alias_text,normalized_alias,source_note,confidence)
SELECT p.id, a.alias_text, a.normalized_alias, 'Phase 1 POC alias fixture', a.confidence
FROM products p JOIN (
 SELECT 'amd-ryzen-7-5700x3d' slug,'Ryzen 7 5700X3D' alias_text,'ryzen75700x3d' normalized_alias,100 confidence UNION ALL
 SELECT 'amd-ryzen-7-5700x3d','R7 5700X3D','r75700x3d',98 UNION ALL
 SELECT 'amd-ryzen-7-5700x3d','AMD 5700X3D','amd5700x3d',98 UNION ALL
 SELECT 'amd-ryzen-7-5700x3d','Ryzen5700X3D','ryzen5700x3d',96 UNION ALL
 SELECT 'amd-ryzen-7-5700x3d','5700 X3D','5700x3d2',95 UNION ALL
 SELECT 'intel-core-i5-12400f','Core i5 12400F','corei512400f',100 UNION ALL
 SELECT 'intel-core-i5-12400f','i5 12400F','i512400f',100 UNION ALL
 SELECT 'intel-core-i5-12400f','Intel 12400F','intel12400f',98 UNION ALL
 SELECT 'intel-core-i5-12400f','i5-12400F','i512400fdash',100 UNION ALL
 SELECT 'intel-core-i5-12400f','12400F tray','12400ftray',96 UNION ALL
 SELECT 'nvidia-geforce-rtx-3070','RTX 3070','rtx3070base',100 UNION ALL
 SELECT 'nvidia-geforce-rtx-3070','3070 8GB','30708gb',98 UNION ALL
 SELECT 'nvidia-geforce-rtx-3070','GeForce 3070','geforce3070',98 UNION ALL
 SELECT 'nvidia-geforce-rtx-3070','NVIDIA 3070','nvidia3070',96 UNION ALL
 SELECT 'nvidia-geforce-rtx-3070','RTX3070 8G','rtx30708g',96 UNION ALL
 SELECT slug,alias_text,normalized_alias,confidence FROM (
  SELECT 'amd-ryzen-5-5600' slug,'Ryzen 5 5600' alias_text,'ryzen55600' normalized_alias,100 confidence UNION ALL SELECT 'amd-ryzen-5-5600','R5 5600','r55600',98 UNION ALL SELECT 'amd-ryzen-5-5600','5600 AM4','5600am4',95 UNION ALL SELECT 'amd-ryzen-5-5600','AMD 5600','amd5600',96 UNION ALL SELECT 'amd-ryzen-5-5600','Ryzen5600','ryzen5600',95 UNION ALL
  SELECT 'amd-ryzen-7-5800x','Ryzen 7 5800X','ryzen75800x',100 UNION ALL SELECT 'amd-ryzen-7-5800x','R7 5800X','r75800x',98 UNION ALL SELECT 'amd-ryzen-7-5800x','5800X','5800x',100 UNION ALL SELECT 'amd-ryzen-7-5800x','AMD 5800X','amd5800x',97 UNION ALL SELECT 'amd-ryzen-7-5800x','Ryzen5800X','ryzen5800x',95 UNION ALL
  SELECT 'amd-ryzen-5-7600','Ryzen 5 7600','ryzen57600',100 UNION ALL SELECT 'amd-ryzen-5-7600','R5 7600','r57600',98 UNION ALL SELECT 'amd-ryzen-5-7600','7600 AM5','7600am5',95 UNION ALL SELECT 'amd-ryzen-5-7600','AMD 7600','amd7600',96 UNION ALL SELECT 'amd-ryzen-5-7600','Ryzen7600','ryzen7600',95 UNION ALL
  SELECT 'amd-ryzen-7-7800x3d','Ryzen 7 7800X3D','ryzen77800x3d',100 UNION ALL SELECT 'amd-ryzen-7-7800x3d','R7 7800X3D','r77800x3d',98 UNION ALL SELECT 'amd-ryzen-7-7800x3d','7800 X3D','7800x3d',100 UNION ALL SELECT 'amd-ryzen-7-7800x3d','AMD 7800X3D','amd7800x3d',98 UNION ALL SELECT 'amd-ryzen-7-7800x3d','Ryzen7800X3D','ryzen7800x3d',96 UNION ALL
  SELECT 'intel-core-i3-12100f','Core i3 12100F','corei312100f',100 UNION ALL SELECT 'intel-core-i3-12100f','i3 12100F','i312100f',100 UNION ALL SELECT 'intel-core-i3-12100f','12100F','12100f',100 UNION ALL SELECT 'intel-core-i3-12100f','Intel 12100F','intel12100f',98 UNION ALL SELECT 'intel-core-i3-12100f','i3-12100F','i312100fdash',100 UNION ALL
  SELECT 'intel-core-i5-13400f','Core i5 13400F','corei513400f',100 UNION ALL SELECT 'intel-core-i5-13400f','i5 13400F','i513400f',100 UNION ALL SELECT 'intel-core-i5-13400f','13400F','13400f',100 UNION ALL SELECT 'intel-core-i5-13400f','Intel 13400F','intel13400f',98 UNION ALL SELECT 'intel-core-i5-13400f','i5-13400F','i513400fdash',100 UNION ALL
  SELECT 'intel-core-i5-13600k','Core i5 13600K','corei513600k',100 UNION ALL SELECT 'intel-core-i5-13600k','i5 13600K','i513600k',100 UNION ALL SELECT 'intel-core-i5-13600k','13600K','13600k',100 UNION ALL SELECT 'intel-core-i5-13600k','Intel 13600K','intel13600k',98 UNION ALL SELECT 'intel-core-i5-13600k','i5-13600K','i513600kdash',100 UNION ALL
  SELECT 'nvidia-geforce-gtx-1660-super','GTX 1660 Super','gtx1660super',100 UNION ALL SELECT 'nvidia-geforce-gtx-1660-super','1660 Super','1660super',100 UNION ALL SELECT 'nvidia-geforce-gtx-1660-super','GTX1660S','gtx1660s',96 UNION ALL SELECT 'nvidia-geforce-gtx-1660-super','1660S','1660s',95 UNION ALL SELECT 'nvidia-geforce-gtx-1660-super','GeForce 1660 Super','geforce1660super',98 UNION ALL
  SELECT 'nvidia-geforce-rtx-2060','RTX 2060','rtx2060',100 UNION ALL SELECT 'nvidia-geforce-rtx-2060','2060 6GB','20606gb',96 UNION ALL SELECT 'nvidia-geforce-rtx-2060','GeForce 2060','geforce2060',97 UNION ALL SELECT 'nvidia-geforce-rtx-2060','NVIDIA 2060','nvidia2060',96 UNION ALL SELECT 'nvidia-geforce-rtx-2060','RTX2060 6G','rtx20606g',96 UNION ALL
  SELECT 'nvidia-geforce-rtx-3060-ti','RTX 3060 Ti','rtx3060ti',100 UNION ALL SELECT 'nvidia-geforce-rtx-3060-ti','3060Ti','3060ti',100 UNION ALL SELECT 'nvidia-geforce-rtx-3060-ti','3060 Ti 8GB','3060ti8gb',96 UNION ALL SELECT 'nvidia-geforce-rtx-3060-ti','GeForce 3060 Ti','geforce3060ti',98 UNION ALL SELECT 'nvidia-geforce-rtx-3060-ti','RTX3060Ti 8G','rtx3060ti8g',96 UNION ALL
  SELECT 'nvidia-geforce-rtx-3080','RTX 3080','rtx3080',100 UNION ALL SELECT 'nvidia-geforce-rtx-3080','3080 10GB','308010gb',97 UNION ALL SELECT 'nvidia-geforce-rtx-3080','GeForce 3080','geforce3080',98 UNION ALL SELECT 'nvidia-geforce-rtx-3080','NVIDIA 3080','nvidia3080',96 UNION ALL SELECT 'nvidia-geforce-rtx-3080','RTX3080 10G','rtx308010g',96 UNION ALL
  SELECT 'nvidia-geforce-rtx-4060-ti','RTX 4060 Ti','rtx4060ti',100 UNION ALL SELECT 'nvidia-geforce-rtx-4060-ti','4060Ti','4060ti',100 UNION ALL SELECT 'nvidia-geforce-rtx-4060-ti','4060 Ti 8GB','4060ti8gb',96 UNION ALL SELECT 'nvidia-geforce-rtx-4060-ti','GeForce 4060 Ti','geforce4060ti',98 UNION ALL SELECT 'nvidia-geforce-rtx-4060-ti','RTX4060Ti 8G','rtx4060ti8g',96 UNION ALL
  SELECT 'nvidia-geforce-rtx-4070','RTX 4070','rtx4070',100 UNION ALL SELECT 'nvidia-geforce-rtx-4070','4070 12GB','407012gb',97 UNION ALL SELECT 'nvidia-geforce-rtx-4070','GeForce 4070','geforce4070',98 UNION ALL SELECT 'nvidia-geforce-rtx-4070','NVIDIA 4070','nvidia4070',96 UNION ALL SELECT 'nvidia-geforce-rtx-4070','RTX4070 12G','rtx407012g',96 UNION ALL
  SELECT 'nvidia-geforce-rtx-4070-ti','RTX 4070 Ti','rtx4070ti',100 UNION ALL SELECT 'nvidia-geforce-rtx-4070-ti','4070Ti','4070ti',100 UNION ALL SELECT 'nvidia-geforce-rtx-4070-ti','4070 Ti 12GB','4070ti12gb',96 UNION ALL SELECT 'nvidia-geforce-rtx-4070-ti','GeForce 4070 Ti','geforce4070ti',98 UNION ALL SELECT 'nvidia-geforce-rtx-4070-ti','RTX4070Ti 12G','rtx4070ti12g',96 UNION ALL
  SELECT 'amd-radeon-rx-6600-xt','RX 6600 XT','rx6600xt',100 UNION ALL SELECT 'amd-radeon-rx-6600-xt','6600XT','6600xt',100 UNION ALL SELECT 'amd-radeon-rx-6600-xt','Radeon 6600 XT','radeon6600xt',98 UNION ALL SELECT 'amd-radeon-rx-6600-xt','6600 XT 8GB','6600xt8gb',96 UNION ALL SELECT 'amd-radeon-rx-6600-xt','RX6600XT 8G','rx6600xt8g',96 UNION ALL
  SELECT 'amd-radeon-rx-6700-xt','RX 6700 XT','rx6700xt',100 UNION ALL SELECT 'amd-radeon-rx-6700-xt','6700XT','6700xt',100 UNION ALL SELECT 'amd-radeon-rx-6700-xt','Radeon 6700 XT','radeon6700xt',98 UNION ALL SELECT 'amd-radeon-rx-6700-xt','6700 XT 12GB','6700xt12gb',96 UNION ALL SELECT 'amd-radeon-rx-6700-xt','RX6700XT 12G','rx6700xt12g',96 UNION ALL
  SELECT 'amd-radeon-rx-7800-xt','RX 7800 XT','rx7800xt',100 UNION ALL SELECT 'amd-radeon-rx-7800-xt','7800XT','7800xt',100 UNION ALL SELECT 'amd-radeon-rx-7800-xt','Radeon 7800 XT','radeon7800xt',98 UNION ALL SELECT 'amd-radeon-rx-7800-xt','7800 XT 16GB','7800xt16gb',96 UNION ALL SELECT 'amd-radeon-rx-7800-xt','RX7800XT 16G','rx7800xt16g',96
 ) more_aliases
) a ON a.slug=p.slug;
