# Research staging notes

Moved from the top of `PROJECT_STATUS.md` on 2026-11-28 (LS-90) so the status file starts with the status. Content unchanged; newest first.

Research collection milestone (2026-11-28): staged 1,927 source rows across RAM 178, SSD 603, HDD 199, motherboard 213, PSU 218, case 200 and cooling 316, exceeding the requested 1,000 collected entries. The 1,552 normalized name candidates are not verified unique products; observations may share samples, configurations or sources. The collection includes qualitative compatibility and reliability data. Two disputed rows remain quarantined. Source validation, reuse rights and Product Master matching remain future work; no staging data was imported as market prices. See database/reference/benchmark_research_2026-11-22/SESSION_HANDOFF_TH_2026-11-28.md.

SSD research continuation: 45 model/capacity/variant summaries from Gen5 and 4TB comparative tables were staged. Unusual write values and cross-guide overlap are flagged; identity, test context and rights remain unreviewed.

Internal SSD research continuation: 41 1TB model/variant summaries were staged from a comparative CrystalDiskMark table. Capacity is separate from the existing 2TB results; platform, identity, overlap and reuse rights remain unreviewed.

Internal SSD research: 33 2TB model/variant summaries from Tweakers were staged, pairing CrystalDiskMark sequential read and write tables. Drive state, platform, product identity, overlap and rights remain unreviewed.

Portable SSD research: four model/capacity summaries were added with multiple benchmark measurements; one low Lexar write value is flagged for verification. Conflicting Crucial X6 results were not merged. Identities and rights remain unreviewed.

Portable storage research: nine model/capacity summaries from comparative SSD reviews were staged with source and benchmark results. Reused comparison columns were counted once; settings, host, identity and rights require review.

Case research: 19 sourced thermal observations across nine case names and varied configurations were staged. Some use borrowed fans or removed panels; test methods and reuse rights require review. The 1,000-item goal remains unverified.

Motherboard research: 34 Z890 DDR5-8800 Gear 2 validation outcomes were staged (15 passed one-hour stress and 19 failed POST). These are configuration-specific compatibility results, not performance scores or permanent product claims; identity and rights remain unreviewed.

Motherboard research continuation: 29 peak-PCB observations from X870 and AM5 mini-ITX comparative tests are staged; the shared Taichi variant result is one row. Different CPUs and power limits require separate interpretation; identities and reuse rights remain unreviewed.

B850 research: 25 configurations across 24 board names were staged with measured temperature, clock, wall power and Cinebench score from one comparative test. Power limits and the unrestricted run remain distinct; identity, rights and overlap need review.

Motherboard research: 30 X870/X870E rear-PCB temperature observations from one comparative review were staged with source and methodology. They are not internal VRM sensor temperatures. Product matching, rights and overlap remain unreviewed; 1,000-item completion remains unverified.

PSU research continuation: 26 efficiency observations across eight reviewed models were staged with input voltage and temperature context. Some models already have cold-test observations elsewhere. Average load methods and rights require review; the 1,000-item target remains unverified.

Cooling research: twelve additional maximum-fan configurations from the PCMasters table were staged; six previously recorded rows were excluded. The source uses the same coolers under other fan conditions elsewhere in staging. Review and rights assessment remain open.

DDR3 research continuation: fourteen AIDA64 configurations across six additional memory kit families were staged, retaining settings and overclock context. The data are unreviewed and do not establish 1,000 distinct products.

DDR3 research: 11 AIDA64 system configurations across three RAM kits were staged; overclock and platform distinctions are preserved. One duplicate external HDD result was removed. Product identity and reuse rights remain unreviewed.

Research continuation: six PSU efficiency observations and four RAM AIDA64 configurations were staged. Some are configurations of products already represented; cross-source duplication and rights remain open. The 1,000-item objective is unverified.

RAM research: six family-level PassMark scores and seven AIDA64 configurations or repeated runs are staged; three are from one anonymous kit and community results are self-reported. Model matching, platform context, overlap and rights need review. The 1,000-item goal remains unverified.

External storage research: 15 configurations across four external HDD/SSD products were staged with benchmark names and provenance; measurements of a single sample across tools are not separate products. Host details and reuse rights require review; no production import.

Cooling research: 17 cooler configurations from the PCMasters 1000 rpm table are staged with CPU clock and thermal deltas. They use the same roundup as earlier maximum-fan observations, but different fan settings. Identities and reuse rights remain unreviewed; the 1,000-record objective is not yet verified.

Cooling and case research continuation: 15 sourced metric observations were staged with test conditions. Some rows describe the same product under different conditions; duplication, identities and reuse rights remain unreviewed. The requested 1,000 performance records remain unverified.

Seven-category research update: four PSU efficiency observations, two motherboard thermal sensor observations and one RAM test configuration were added to private staging. Sources and test conditions are recorded; matching and reuse rights remain unreviewed. The requested 1,000 performance-test records have not yet been established.



Research staging (2026-11-22): web search identified openly licensed external performance datasets, saved under `database/reference/benchmark_research_2026-11-22/`: 3,171 InnoReviews calculated ranking rows and 803 LLM Speed workload results. These are unreviewed source records, not matched to Product Master or imported into the database; they must not affect price snapshots. InnoReviews values are derived scores, not raw benchmark results. Category coverage is incomplete; see the staging README for attribution, validation and limitations. This research does not establish a completed product milestone.

Seven-category research continuation: 116 sourced metric observations across RAM, SSD, HDD, motherboard, PSU, case and cooling, plus 31 HDD fleet reliability aggregates, are staged separately with source and limitations in `database/reference/benchmark_research_2026-11-22/SEVEN_CATEGORY_RESEARCH.md`. Three SSD metrics come from one anonymous research run and cannot yet be matched to a product. This is not yet the requested 1,000 records; identities and reuse remain unreviewed. No production import.

RAM research continuation: 14 additional DFI SD170 AIDA64 system configurations covering six reported memory part numbers have been staged separately, with read/write/copy results and CPU/channel context. They are unreviewed, may overlap other research and are not 14 distinct products; matching and reuse rights remain open.

Case and cooling research continuation: four Noctua case-clearance measurements and 13 PC Gamer fan metric observations have been staged with test context; clearance is a compatibility measurement, not a thermal benchmark. Data remain unreviewed and are not imported.

Storage research continuation: seven SSD SlowMark model/capacity summaries have been staged with source and explicit uncertainty flags; test setup is incomplete and one source-reported interface conflicts with its throughput. No matching or production import.

Additional memory research: four kit-level AIDA64 read/latency summaries have been staged from an external review; complete per-kit platform details and product identities require verification. No matching or production import.

Mixed-memory research: four AIDA64 configurations were staged with both DIMM identities, CPU, motherboard and profile; the measurements apply to the entire configuration, and one module was overclocked. Review and rights assessment remain open.

Case thermal research: eight sourced temperature-above-ambient observations covering four case models were staged with workload and fan context; system configurations differ and no production import was performed.

Board and cooler research: four rear-PCB VRM-area observations and six cooler temperature deltas were staged with source and test configuration. The former are surface measurements and the latter differences from ambient; review remains open.

External HDD research: four CrystalDiskMark read/write summaries are staged by enclosure model and capacity; test settings are incomplete, internal drive models are unknown, and review remains open.

Small-form-factor cooling research: six LOUQE RAW S1 cooler/fan configurations were staged with system noise above ambient and test context. Values include case fans and are not standalone cooler noise; review remains open.

Portable SSD research: eight sourced CrystalDiskMark read/write summaries have been staged with gaps and source discrepancies flagged; portable enclosures are not identified bare drives and matching remains open.

PSU research continuation: 27 efficiency observations across fourteen PSU models are staged with input voltage, hot/cold test and load-range caveats; source averaging methods differ and review remains open.

Motherboard research continuation: three reported MOS sensor observations from two motherboard models are staged with CPU and configuration; sensor details and some workload context need review. Two additional search sources returned HTTP 403 and collection from them stopped, as recorded in the research notes.

Additional case research: six thermal observations for four case models were staged with workload and configuration context. Antec P8 measurements used a moved stock fan and the GPU throttled; review remains open.

SSD manufacturer research: six SSD452K capacities are staged with Transcend's reported maximum CrystalDiskMark speeds and test setup; these are manufacturer maxima, not independent review measurements.

Additional manufacturer research: five MTE470A-I capacity variants are staged with Transcend-reported maximum speeds and test context; results remain separate from independent tests and unreviewed.

Further manufacturer research: ten capacity variants of Transcend MTE662T2 and MTS952T2 were staged with manufacturer-reported maximum speeds and different test setups; results remain unreviewed and separate from independent tests.

Additional thermal research: six observations across three motherboard models and one cooler are staged with sensor type and workload; they are not directly interchangeable and remain unreviewed.

DDR4 configuration research: six AIDA64 observations covering three kit identities and two/four-stick layouts were staged with manual or XMP timings and test platform; they are system-level results and remain unreviewed.

Further research adds Lexar SL500 and two USB configurations of Fanxiang PS2000W, two Thermaltake A500 TG thermal readings, and two Gigabyte X870 AORUS ELITE WIFI7 VRM/heatsink observations. All are staged and unreviewed; interface and sensor distinctions are retained.

Internal HDD research: nine review-based CrystalDiskMark summaries have been staged, including exact and incomplete model identities; missing settings, identity resolution and reuse rights remain open.

Lexar and motherboard research: three vendor-run NM610 PRO benchmark configurations and one externally probed Z890M VRM heatsink observation were staged; source context and limitations are recorded. A Neoseeker page returned HTTP 403 and collection from it stopped.

Further cooling research: five system-temperature observations across Lancool 207, Fractal Summit and Corsair 3200D cases, plus one PCCooler W120 cooler observation, were staged with workload and configuration caveats.

Linux Hardware SMART research staging: 3,000 model-level HDD/SSD/NVMe health-statistic rows (three pinned CC BY 4.0 reports) have been added. These are self-selected SMART-based estimates, not controlled performance benchmarks or verified independent samples; do not mix them with performance measurements. See the seven-category research note for limitations. Performance-test coverage remains incomplete.
