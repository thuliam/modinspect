# Handoff: request to GPT for performance data (ModInspect)

คำแนะนำสำหรับเจ้าของ: คัดลอกส่วน "Prompt to send" ทั้งหมด (ตั้งแต่เส้นคั่นถึงท้ายเอกสาร) ไปวางให้ GPT แล้วนำผลลัพธ์ CSV กลับมาให้ผม ก่อนนำเข้าระบบผมจะตรวจตัวเลขสุ่มกับหน้าต้นทางอีกครั้ง GPT อาจสร้างตัวเลขที่ไม่มีอยู่จริงได้ จึงมีกติกาให้ระบุ URL และ "ไม่พบ" แทนการเดา เอกสารนี้ไม่มีข้อมูลส่วนตัวหรือความลับของโครงการ

สิ่งที่มีอยู่แล้ว: `docs/PERF_SCORE_RESEARCH.md` (แหล่งที่หนึ่ง: Tom's Hardware, GPU 25 จาก 29 รุ่น, CPU เกม 7 จาก 27 รุ่น)

---

## Prompt to send

You are helping build a used-PC-parts price-check website. I need **published benchmark figures** for specific CPUs and GPUs so the site can show a relative performance index. The most important rule: **never invent or estimate a number.** Every value must be one you actually read on a public web page, with its URL. If you cannot find a value, leave it empty and say why.

### Goal
For each model in the lists below, collect performance figures from **at least two independent public sources**, so I can cross-check them. One source has already been read (Tom's Hardware), so I need **different** sources. Prioritise the models marked MISSING, then the rest.

### Source rules
1. Use only pages freely readable without login or payment.
2. Read pages one at a time as a person would. Do not run scrapers or bulk downloads. Respect robots.txt and each site's terms of use. If a site forbids reuse of its data, or blocks you, **stop using that site** and list it under "blocked or restricted sources". Do not try to get around a block.
3. Prefer sources that publish a table with a stated test method. Examples to consider (you may suggest others): PassMark (CPU Mark, single-thread rating, G3D Mark), TechPowerUp (relative performance in GPU reviews and the GPU database), GamersNexus, Hardware Unboxed, Guru3D, Notebookcheck desktop tables, Geekbench browser. Tell me for each source what its terms say about reuse, with a link to the terms page.
4. Do not copy paragraphs, images or whole tables. I only need the single numbers asked for below.
5. The figures must come from the page. Do not average, normalise or convert them yourself. If a page gives a percentage against another card, record it as given and say which card is 100.

### What to record per model and per source
- **CPU:** (a) a gaming figure if the source has one, (b) single-thread score, (c) multi-thread score. Record whichever exist.
- **GPU:** (a) 1080p raster, (b) 1440p raster, (c) 4K raster where available; for PassMark also the G3D Mark. Ray tracing is optional.
- For each figure also record: the unit or scale, which product is the 100 baseline (if any), the test setup in one line (games or benchmark name, resolution, the other hardware, DDR generation for CPUs), the date shown on the page, and the date you read it.

### Output format
One CSV, UTF-8, with this header and one row per model, per source, per metric:

`category,model_slug,model_name,metric,value,scale_or_unit,baseline_100_is,source_name,source_url,page_date,date_read,test_setup,terms_url,reuse_note,note`

- `category` is CPU or GPU. `model_slug` must be copied exactly from the lists below.
- If a model is on the page under a close sibling name (for example 14700K instead of 14700KF), use the sibling's number and write `sibling: 14700K` in `note`.
- If a model has two memory variants (for example 8GB and 16GB) and the catalog name does not say which, record both as separate rows and say so in `note`.
- If a figure is not available, write the row with `value` empty and the reason in `note` (for example "model not on page", "page needs login").
- After the CSV, add three short lists: **blocked or restricted sources**, **models for which only one source was found**, and **anything you were unsure about**.

### Models (slug, name)

CPUs, MISSING from the first source (do these first):
- amd-ryzen-5-5600, Ryzen 5 5600
- amd-ryzen-5-5600x, Ryzen 5 5600X
- amd-ryzen-7-5800x, Ryzen 7 5800X
- amd-ryzen-7-5700x3d, Ryzen 7 5700X3D
- amd-ryzen-5-7600, Ryzen 5 7600
- amd-ryzen-5-7500f, Ryzen 5 7500F
- amd-ryzen-5-8500g, Ryzen 5 8500G
- amd-ryzen-7-2700x, Ryzen 7 2700X
- amd-ryzen-5-2600x, Ryzen 5 2600X
- amd-ryzen-3-3400g, Ryzen 3 3400G
- intel-core-i5-14400f, Core i5-14400F
- intel-core-i5-13500, Core i5-13500
- intel-core-i7-12700f, Core i7-12700F (the first source lists only the 12700K; use a sibling only if you note it)

CPUs, partly covered by the first source (still need a second source):
- amd-ryzen-9-9800x3d, Ryzen 7 9800X3D
- amd-ryzen-7-9700x, Ryzen 7 9700X
- amd-ryzen-9-7900x, Ryzen 9 7900X
- amd-ryzen-7-7800x3d, Ryzen 7 7800X3D
- amd-ryzen-7-5700x, Ryzen 7 5700X
- amd-ryzen-5-5500, Ryzen 5 5500
- intel-core-i9-13900k, Core i9-13900K
- intel-core-i7-14700kf, Core i7-14700KF
- intel-core-i5-14400, Core i5-14400
- intel-core-i5-13600k, Core i5-13600K
- intel-core-i5-13400f, Core i5-13400F
- intel-core-i5-12400f, Core i5-12400F
- intel-core-i3-14100f, Core i3-14100F
- intel-core-i3-13100f, Core i3-13100F
- intel-core-i3-12100f, Core i3-12100F

GPUs, MISSING from the first source (do these first):
- nvidia-geforce-gtx-1650, GeForce GTX 1650
- nvidia-geforce-gtx-1660-super, GeForce GTX 1660 Super
- nvidia-geforce-rtx-2060, GeForce RTX 2060
- amd-radeon-rx-6500-xt, Radeon RX 6500 XT

GPUs, covered by the first source (need a second source):
- nvidia-geforce-rtx-5090, RTX 5090
- nvidia-geforce-rtx-5080, RTX 5080
- nvidia-geforce-rtx-5070-ti, RTX 5070 Ti
- nvidia-geforce-rtx-5070, RTX 5070
- nvidia-geforce-rtx-5060-ti, RTX 5060 Ti (8GB and 16GB)
- nvidia-geforce-rtx-5060, RTX 5060
- nvidia-geforce-rtx-4090, RTX 4090
- nvidia-geforce-rtx-4070-ti, RTX 4070 Ti
- nvidia-geforce-rtx-4070-super, RTX 4070 Super
- nvidia-geforce-rtx-4070, RTX 4070
- nvidia-geforce-rtx-4060-ti, RTX 4060 Ti (8GB and 16GB)
- nvidia-geforce-rtx-4060, RTX 4060
- nvidia-geforce-rtx-3090, RTX 3090
- nvidia-geforce-rtx-3080-ti, RTX 3080 Ti
- nvidia-geforce-rtx-3080, RTX 3080
- nvidia-geforce-rtx-3070-ti, RTX 3070 Ti
- nvidia-geforce-rtx-3070, RTX 3070
- nvidia-geforce-rtx-3060-ti, RTX 3060 Ti
- nvidia-geforce-rtx-3060, RTX 3060 (12GB)
- nvidia-geforce-rtx-3050, RTX 3050
- amd-radeon-rx-9070-xt, RX 9070 XT
- amd-radeon-rx-9060-xt, RX 9060 XT (8GB and 16GB)
- amd-radeon-rx-7800-xt, RX 7800 XT
- amd-radeon-rx-6700-xt, RX 6700 XT
- amd-radeon-rx-6600-xt, RX 6600 XT

### Final check before you answer
Reread your CSV and confirm that every non-empty `value` has a `source_url` you actually opened. Delete any row you cannot back with a page. Tell me plainly how many models ended with two or more sources.

---

## Notes for the owner and for me (not part of the prompt)

- Do not send GPT anything else from this repository. No prices, no seller data, no credentials.
- When the CSV comes back I will: check a random sample against the source pages, reject rows without a working URL, compare the two sources per model and flag models where they disagree strongly, then update `docs/PERF_SCORE_RESEARCH.md`. Nothing goes into the database before you accept the sources and their terms.
- The legal question (may we show an index derived from these sources) stays open and is yours to decide. A source whose terms forbid reuse is dropped.
