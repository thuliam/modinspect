<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\Catalog\CommonsImages as C;

/** LS-70: the pure rules of the Commons helper (no network): which licences are reusable, how a file title is matched to a model, HTML cleanup, and the download host guard. */
function ok_ci(bool $c, string $m): void { if (!$c) { throw new RuntimeException($m); } }

foreach (['CC BY 4.0', 'CC BY-SA 3.0', 'CC0', 'Public domain', 'CC BY-SA 2.0 de'] as $l) { ok_ci(C::licenceOk($l), "reusable: $l"); }
foreach (['CC BY-NC 2.0', 'CC BY-ND 4.0', 'CC BY-NC-SA 3.0', 'Fair use', 'All rights reserved', '', 'GPL'] as $l) { ok_ci(!C::licenceOk($l), "not reusable: $l"); }

ok_ci(C::modelTokens('AMD Ryzen 5 5600X') === ['5600x'], 'tokens of a CPU: ' . json_encode(C::modelTokens('AMD Ryzen 5 5600X')));
ok_ci(C::modelTokens('NVIDIA GeForce RTX 4070 Ti') === ['4070'], 'tokens of a GPU');
ok_ci(C::titleMatches('File:AMD Ryzen 5 5600X (cropped).jpg', 'AMD Ryzen 5 5600X'), 'a matching title');
ok_ci(!C::titleMatches('File:AMD Ryzen 5 5600.jpg', 'AMD Ryzen 5 5600X'), 'a different model is refused');
ok_ci(!C::titleMatches('File:Intel Core i5-13400F.jpg', 'Intel Core i5-13400'), '13400F is not 13400');
ok_ci(C::titleMatches('File:Intel Core i5-13400F.jpg', 'Intel Core i5-13400F'), 'i5-13400F matches itself');
ok_ci(!C::titleMatches('File:Asus RTX 4070 Ti Super.jpg', 'NVIDIA GeForce RTX 4070'), 'a Ti Super card is not the plain 4070');
ok_ci(C::titleMatches('File:MSI RTX 4070 Ti Gaming.jpg', 'NVIDIA GeForce RTX 4070 Ti'), 'Ti matches Ti');
ok_ci(!C::titleMatches('File:Nokia 5500.jpg', 'AMD Ryzen 5 5500') && !C::titleMatches('File:Meitetsu 5500 Inuyamabashi.jpg', 'AMD Ryzen 5 5500'), 'a bare number alone does not match (Nokia 5500, a train)');
ok_ci(!C::titleMatches('File:A graphics card.jpg', 'NVIDIA GeForce RTX 4070'), 'no model number in the title');
ok_ci(!C::titleMatches('File:Anything.jpg', 'Corsair Vengeance'), 'a name without a model number never matches');

ok_ci(C::plain('<a href="x">Jane &amp; Co</a>  <b>Doe</b>') === 'Jane & Co Doe', 'author HTML is reduced to plain text');
$threw = false; try { C::download('https://evil.test/a.jpg', sys_get_temp_dir() . '/x.jpg'); } catch (\RuntimeException) { $threw = true; }
ok_ci($threw, 'a download from another host is refused');
ok_ci(C::notAProductPhoto('File:Nvidia@12nm@Turing GeForce RTX 2060 DSCx1 polysilicon@50x.jpg') && C::notAProductPhoto('File:RTX 3060 die.jpg') && !C::notAProductPhoto('File:MSI GeForce RTX 3060 Ventus.jpg'), 'die shots are not product photos');
echo "commons_images_test: PASS\n";
