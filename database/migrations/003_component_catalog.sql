USE modinspect;
ALTER TABLE products ADD COLUMN IF NOT EXISTS socket VARCHAR(40) NULL AFTER recommended_psu_watt;
ALTER TABLE products ADD COLUMN IF NOT EXISTS chipset VARCHAR(40) NULL AFTER socket;
ALTER TABLE products ADD COLUMN IF NOT EXISTS memory_type VARCHAR(20) NULL AFTER chipset;
ALTER TABLE products ADD COLUMN IF NOT EXISTS form_factor VARCHAR(30) NULL AFTER memory_type;
ALTER TABLE products ADD COLUMN IF NOT EXISTS capacity_gb INT UNSIGNED NULL AFTER form_factor;

INSERT IGNORE INTO product_categories (name,slug,sort_order) VALUES
('Motherboard','motherboard',2),('PSU','psu',6),('Case','case',7),('Cooling','cooling',8);
INSERT IGNORE INTO brands (name,slug) VALUES
('ASUS','asus'),('MSI','msi'),('Gigabyte','gigabyte'),('ASRock','asrock'),('Corsair','corsair'),('Kingston','kingston'),('Samsung','samsung'),('Western Digital','western-digital'),('Cooler Master','cooler-master'),('Thermaltake','thermaltake');

UPDATE products SET socket='AM4',memory_type='DDR4',image_path='assets/images/admin-2.jpg' WHERE slug='amd-ryzen-7-5700x3d';
UPDATE products SET socket='LGA1700',memory_type='DDR4/DDR5',image_path='assets/images/admin-4.jpg' WHERE slug='intel-core-i5-12400f';
UPDATE products SET recommended_psu_watt=650,image_path='assets/images/admin-3.jpg' WHERE slug='nvidia-geforce-rtx-3070';

INSERT IGNORE INTO products (category_id,brand_id,model_name,slug,full_name,spec_summary,socket,chipset,memory_type,form_factor,image_path,is_active) VALUES
((SELECT id FROM product_categories WHERE slug='motherboard'),(SELECT id FROM brands WHERE slug='asus'),'TUF Gaming B450-PLUS II','asus-tuf-b450-plus-ii','ASUS TUF Gaming B450-PLUS II','AM4, 4 DIMM, PCIe 3.0, ATX','AM4','B450','DDR4','ATX','assets/images/admin-5.jpg',1),
((SELECT id FROM product_categories WHERE slug='motherboard'),(SELECT id FROM brands WHERE slug='msi'),'MAG B550 Tomahawk','msi-mag-b550-tomahawk','MSI MAG B550 Tomahawk','AM4, 4 DIMM, PCIe 4.0, 2.5G LAN','AM4','B550','DDR4','ATX','assets/images/admin-5.jpg',1),
((SELECT id FROM product_categories WHERE slug='motherboard'),(SELECT id FROM brands WHERE slug='gigabyte'),'X470 AORUS Ultra Gaming','gigabyte-x470-aorus-ultra','Gigabyte X470 AORUS Ultra Gaming','AM4, 4 DIMM, Multi-GPU, ATX','AM4','X470','DDR4','ATX','assets/images/admin-5.jpg',1),
((SELECT id FROM product_categories WHERE slug='motherboard'),(SELECT id FROM brands WHERE slug='asrock'),'X570 Steel Legend','asrock-x570-steel-legend','ASRock X570 Steel Legend','AM4, PCIe 4.0, 2x M.2, ATX','AM4','X570','DDR4','ATX','assets/images/admin-5.jpg',1),
((SELECT id FROM product_categories WHERE slug='motherboard'),(SELECT id FROM brands WHERE slug='asus'),'Prime H610M-K D4','asus-prime-h610m-k-d4','ASUS Prime H610M-K D4','LGA1700, 2 DIMM, entry-level','LGA1700','H610','DDR4','Micro-ATX','assets/images/admin-5.jpg',1),
((SELECT id FROM product_categories WHERE slug='motherboard'),(SELECT id FROM brands WHERE slug='msi'),'PRO B660M-A DDR4','msi-pro-b660m-a-ddr4','MSI PRO B660M-A DDR4','LGA1700, 4 DIMM, PCIe 4.0','LGA1700','B660','DDR4','Micro-ATX','assets/images/admin-5.jpg',1),
((SELECT id FROM product_categories WHERE slug='motherboard'),(SELECT id FROM brands WHERE slug='gigabyte'),'B760M DS3H AX DDR4','gigabyte-b760m-ds3h-ax-ddr4','Gigabyte B760M DS3H AX DDR4','LGA1700, Wi-Fi 6E, 4 DIMM','LGA1700','B760','DDR4','Micro-ATX','assets/images/admin-5.jpg',1),
((SELECT id FROM product_categories WHERE slug='motherboard'),(SELECT id FROM brands WHERE slug='asrock'),'Z690 Steel Legend','asrock-z690-steel-legend','ASRock Z690 Steel Legend','LGA1700, PCIe 5.0, ATX','LGA1700','Z690','DDR4','ATX','assets/images/admin-5.jpg',1),
((SELECT id FROM product_categories WHERE slug='ram'),(SELECT id FROM brands WHERE slug='kingston'),'FURY Beast 16GB DDR4-3200','kingston-fury-beast-16gb-ddr4','Kingston FURY Beast 16GB DDR4-3200','2x8GB DDR4-3200 CL16',NULL,NULL,'DDR4',NULL,'assets/images/home-5.jpg',1),
((SELECT id FROM product_categories WHERE slug='ram'),(SELECT id FROM brands WHERE slug='corsair'),'Vengeance LPX 32GB DDR4-3200','corsair-vengeance-lpx-32gb','Corsair Vengeance LPX 32GB DDR4-3200','2x16GB DDR4-3200 CL16',NULL,NULL,'DDR4',NULL,'assets/images/home-5.jpg',1),
((SELECT id FROM product_categories WHERE slug='storage'),(SELECT id FROM brands WHERE slug='samsung'),'970 EVO Plus 1TB','samsung-970-evo-plus-1tb','Samsung 970 EVO Plus 1TB','NVMe PCIe 3.0 x4',NULL,NULL,NULL,'M.2 2280','assets/images/home-3.jpg',1),
((SELECT id FROM product_categories WHERE slug='storage'),(SELECT id FROM brands WHERE slug='western-digital'),'WD Blue SN570 1TB','wd-blue-sn570-1tb','WD Blue SN570 1TB','NVMe PCIe 3.0 x4',NULL,NULL,NULL,'M.2 2280','assets/images/home-3.jpg',1),
((SELECT id FROM product_categories WHERE slug='psu'),(SELECT id FROM brands WHERE slug='corsair'),'RM750x 750W Gold','corsair-rm750x','Corsair RM750x 750W 80+ Gold','Fully modular, 80+ Gold',NULL,NULL,NULL,'ATX','assets/images/home-3.jpg',1),
((SELECT id FROM product_categories WHERE slug='psu'),(SELECT id FROM brands WHERE slug='cooler-master'),'MWE Gold 650 V2','cooler-master-mwe-gold-650','Cooler Master MWE Gold 650 V2','650W, 80+ Gold',NULL,NULL,NULL,'ATX','assets/images/home-3.jpg',1),
((SELECT id FROM product_categories WHERE slug='case'),(SELECT id FROM brands WHERE slug='corsair'),'4000D Airflow','corsair-4000d-airflow','Corsair 4000D Airflow','ATX Mid Tower, airflow front panel',NULL,NULL,NULL,'ATX Mid Tower','assets/images/local-2.jpg',1),
((SELECT id FROM product_categories WHERE slug='cooling'),(SELECT id FROM brands WHERE slug='cooler-master'),'Hyper 212 Halo','cooler-master-hyper-212-halo','Cooler Master Hyper 212 Halo','Tower air cooler, AM4/LGA1700','AM4/LGA1700',NULL,NULL,'Tower','assets/images/local-1.jpg',1);

INSERT INTO price_indices (product_id,price_type,price_low,q1,median,q3,price_high,market_min,market_max,sample_size,valid_sample_size,fresh_sample_ratio,confidence_score,confidence_label,last_calculated_at)
SELECT p.id,'asking',v.low,v.q1,v.med,v.q3,v.high,v.q1,v.q3,12,10,85,76,'medium',NOW()
FROM products p JOIN (
 SELECT 'asus-tuf-b450-plus-ii' slug,1800 low,2100 q1,2300 med,2500 q3,2800 high UNION ALL
 SELECT 'msi-mag-b550-tomahawk',2800,3200,3500,3900,4300 UNION ALL SELECT 'gigabyte-x470-aorus-ultra',2100,2400,2700,3000,3400 UNION ALL SELECT 'asrock-x570-steel-legend',3200,3600,4000,4400,4900 UNION ALL
 SELECT 'asus-prime-h610m-k-d4',1600,1800,2000,2200,2500 UNION ALL SELECT 'msi-pro-b660m-a-ddr4',2600,2900,3200,3500,3900 UNION ALL SELECT 'gigabyte-b760m-ds3h-ax-ddr4',3300,3700,4100,4500,5000 UNION ALL SELECT 'asrock-z690-steel-legend',3900,4300,4800,5300,5900 UNION ALL
 SELECT 'kingston-fury-beast-16gb-ddr4',800,950,1100,1250,1450 UNION ALL SELECT 'corsair-vengeance-lpx-32gb',1500,1750,2000,2300,2600 UNION ALL
 SELECT 'samsung-970-evo-plus-1tb',1200,1400,1600,1800,2100 UNION ALL SELECT 'wd-blue-sn570-1tb',1000,1200,1400,1600,1850 UNION ALL
 SELECT 'corsair-rm750x',2200,2500,2800,3100,3500 UNION ALL SELECT 'cooler-master-mwe-gold-650',1500,1750,2000,2250,2500 UNION ALL
 SELECT 'corsair-4000d-airflow',1400,1600,1800,2100,2400 UNION ALL SELECT 'cooler-master-hyper-212-halo',650,800,950,1100,1300
) v ON v.slug=p.slug
WHERE NOT EXISTS (SELECT 1 FROM price_indices pi WHERE pi.product_id=p.id);
