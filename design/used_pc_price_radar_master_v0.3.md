# Used PC Price Radar — Master Product, Business & Design Brief

**Document version:** v0.3  
**Status:** Product direction locked for UX/UI exploration  
**Primary use:** ส่งต่อให้ Stitch เพื่อออกแบบ UX/UI และใช้เป็นเอกสารกลางสำหรับพัฒนา MVP  
**Technical direction:** Pure PHP + MVC + PDO + MySQL/MariaDB  
**Product direction:** Used PC Market Intelligence / Price Intelligence Platform  
**Last updated:** 23 July 2026

> แพลตฟอร์มข้อมูลราคาตลาดคอมพิวเตอร์มือสอง  
> ช่วยให้ผู้ซื้อเช็กช่วงราคาและความเสี่ยงก่อนซื้อ  
> ช่วยให้ผู้ขายตั้งราคาอย่างมีเหตุผลโดยไม่ถูกกดราคา  
> และสร้างฐานข้อมูลตลาดที่ต่อยอดเป็นเครื่องมือสำหรับร้านค้าได้

---

## Executive Summary

Used PC Price Radar ไม่ควรเริ่มต้นเป็น Marketplace เต็มรูปแบบ เพราะการรับประกาศ จับคู่ผู้ซื้อกับผู้ขาย ยืนยันตัวตน รับเงิน หรือรับเรื่องเคลม จะเพิ่มภาระด้าน Fraud, Trust & Safety, PDPA, Dispute และ Operation มากเกินไปสำหรับผู้พัฒนาคนเดียว

ผลิตภัณฑ์เวอร์ชันแรกจึงควรเริ่มจากบทบาท:

# **Used PC Market Intelligence Platform**

หรือภาษาไทย:

# **แพลตฟอร์มข้อมูลและเครื่องมือวิเคราะห์ตลาดคอมพิวเตอร์มือสอง**

แกนหลักของผลิตภัณฑ์คือ:

1. **Price Index** — แสดงช่วงราคาประกาศของสินค้าแต่ละรุ่น
2. **Price History** — แสดงแนวโน้มราคาเมื่อมีข้อมูลเพียงพอ
3. **Deal Checker** — ผู้ใช้กรอกราคา หรือนำ Screenshot มาประเมิน
4. **Used PC Spec Builder** — จัดสเปกมือสองจากช่วงราคาตลาด
5. **Buyer Guide** — คำแนะนำก่อนซื้อสินค้าแต่ละประเภท
6. **Seller Price Advisor** — ช่วยผู้ขายประเมินราคาที่ตั้งอย่างเป็นกลาง
7. **Market Data Collector** — ระบบรวบรวม Normalize และกรองข้อมูลราคา
8. **Admin Data Quality Dashboard** — ให้ผู้ดูแลตรวจเฉพาะรายการผิดปกติ

สิ่งที่ทำให้ธุรกิจมีโอกาส Scale ไม่ใช่แค่หน้าเว็บไซต์ แต่คือ:

- Product Master และ Product Alias
- ระบบ Normalize ชื่อรุ่น
- ฐานข้อมูล Price Observation
- วิธีกรอง Duplicate / Deposit / Whole PC / Defect
- Price Engine ที่คำนวณ Median, Quartile และ Confidence
- ประวัติราคา
- Search Traffic และ User Intent Data

---

# Table of Contents

1. [Locked Decisions](#1-locked-decisions)
2. [Vision, Mission and Purpose](#2-vision-mission-and-purpose)
3. [Business Questions](#3-business-questions)
4. [Target Users](#4-target-users)
5. [User Behaviour Hypotheses](#5-user-behaviour-hypotheses)
6. [Product Positioning and Neutrality](#6-product-positioning-and-neutrality)
7. [Value Proposition](#7-value-proposition)
8. [Business Model](#8-business-model)
9. [Validation and Numeric Indicators](#9-validation-and-numeric-indicators)
10. [Product Scope](#10-product-scope)
11. [User Journeys](#11-user-journeys)
12. [Information Architecture](#12-information-architecture)
13. [Screen Requirements for Stitch](#13-screen-requirements-for-stitch)
14. [Design Direction](#14-design-direction)
15. [Market Data Strategy](#15-market-data-strategy)
16. [Collector Architecture](#16-collector-architecture)
17. [AI-Assisted Data Collection](#17-ai-assisted-data-collection)
18. [Source and Evidence Policy](#18-source-and-evidence-policy)
19. [Price Calculation and Fairness](#19-price-calculation-and-fairness)
20. [5700X3D Prototype Case](#20-5700x3d-prototype-case)
21. [Trust, Safety and Platform Role](#21-trust-safety-and-platform-role)
22. [SEO Strategy](#22-seo-strategy)
23. [Technical Architecture](#23-technical-architecture)
24. [Database Design](#24-database-design)
25. [Security Requirements](#25-security-requirements)
26. [Roadmap](#26-roadmap)
27. [Risks and Stop Conditions](#27-risks-and-stop-conditions)
28. [Stitch Handoff Prompt](#28-stitch-handoff-prompt)
29. [Open Questions](#29-open-questions)

---

# 1. Locked Decisions

## 1.1 Product Role

ผลิตภัณฑ์เริ่มต้นเป็น:

> **Price Intelligence Platform สำหรับตลาดคอมพิวเตอร์มือสอง**

ยังไม่เป็น Marketplace เต็มรูปแบบใน MVP

## 1.2 Core User Promise

> ช่วยให้ผู้ใช้เข้าใจว่า “ราคาที่พบอยู่ตรงไหนของตลาด”  
> โดยไม่ฟันธงว่าผู้ขายดีหรือไม่ดี และไม่กดราคาผู้ขายอย่างไม่เป็นธรรม

## 1.3 Data Promise

ระบบแสดง:

- ช่วงราคาประกาศที่ตรวจพบ
- ค่ากลางของราคาประกาศ
- จำนวนตัวอย่าง
- วันที่อัปเดต
- ความสดของข้อมูล
- ระดับความมั่นใจ
- หมายเหตุข้อจำกัดของข้อมูล

ระบบไม่ควรเรียกข้อมูลว่า “ราคาขายจริง” จนกว่าจะมีหลักฐานการปิดดีล

## 1.4 Revenue Direction

ไม่ตั้งสมมติฐานว่าผู้ซื้อทั่วไปจะยอมจ่ายเพื่อดูราคากลาง

รายได้หลักที่ต้องทดลองคือ:

1. Affiliate จากสินค้ามือหนึ่งและอุปกรณ์เสริม
2. Sponsored Offer ที่ติดป้ายชัดเจน
3. Seller / Shop Dashboard
4. Market Report / CSV Export / API
5. Lead Generation ไปยังร้านซ่อม ร้านประกอบ ร้านรับซื้อ
6. Display Ads เมื่อ Traffic มากพอ
7. Manual Review เป็น Experiment ไม่ใช่แกนรายได้หลัก

## 1.5 Data Collection Direction

ใช้หลายช่องทางพร้อมกัน:

- Automated / Agent-assisted Sampling
- AI Web Research
- Screenshot Extraction
- User-assisted Submission
- Seller / Shop Feed
- Allowed Public Sources
- External Benchmark เช่น eBay
- ราคามือหนึ่งสำหรับเปรียบเทียบ ไม่ใช้ปนกับราคามือสอง

## 1.6 Platform Dependency Rule

Facebook Marketplace และ Shopee เป็นแหล่ง Bootstrap ที่สำคัญ แต่ถือเป็นแหล่งที่:

- ไม่มี SLA
- อาจถูก Block
- อาจเปลี่ยน Terms
- อาจเปลี่ยน UI
- ต้องหยุดทันทีหากถูกจำกัดหรือได้รับคำขอให้หยุด
- ไม่ควรเป็น Dependency เดียวของ Price Engine

## 1.7 Technical Direction

เริ่มด้วย:

```text
Pure PHP 8.1+
MVC Structure
PDO Prepared Statements
MySQL / MariaDB
Bootstrap 5
Vanilla JavaScript / jQuery เท่าที่จำเป็น
Apache Rewrite
```

---

# 2. Vision, Mission and Purpose

## 2.1 Vision

เป็นแหล่งอ้างอิงข้อมูลตลาดคอมพิวเตอร์มือสองของไทยที่ผู้ซื้อ ผู้ขาย และร้านค้าใช้ประกอบการตัดสินใจได้

## 2.2 Mission

- ลดเวลาที่คนต้องไล่ค้นราคาเองหลายแพลตฟอร์ม
- ลดความเสี่ยงจากดีลที่ข้อมูลไม่ครบหรือราคาผิดปกติ
- ช่วยให้ผู้ขายเข้าใจตำแหน่งราคาของตนในตลาด
- สร้างฐานข้อมูลและเครื่องมือวิเคราะห์ที่มีมูลค่าเชิงธุรกิจ
- รักษาความเป็นกลางระหว่าง Buyer และ Seller

## 2.3 Product Purpose

ผลิตภัณฑ์ไม่ได้ทำมาเพื่อบอกว่า:

> “Seller คนนี้ขายแพง”

แต่ทำมาเพื่อบอกว่า:

> “ราคานี้อยู่เหนือช่วงอ้างอิงเล็กน้อย และอาจสมเหตุสมผลหากมีประกัน สภาพดี รุ่นย่อยพรีเมียม หรือมีหลักฐานการทดสอบครบ”

---

# 3. Business Questions

## 3.1 ขายใคร

### กลุ่มหลักระยะแรก

#### A. Buyer มือใหม่และคนอัปคอมงบจำกัด

ลักษณะ:

- กำลังซื้อ CPU, GPU, RAM, SSD หรือคอมทั้งเครื่อง
- ใช้งบประมาณประมาณ 5,000–30,000 บาท
- กลัวซื้อแพง
- กลัวสินค้ามีปัญหา
- ต้องการคำตอบเร็วว่า “คุ้มไหม”

สิ่งที่ให้ฟรี:

- Price Index
- Deal Checker
- Buying Guide
- Used vs New Comparison

รายได้ที่เกี่ยวข้อง:

- Affiliate
- Lead Generation
- Manual Review แบบจำกัดคิวในอนาคต

#### B. Seller บุคคลทั่วไป

ลักษณะ:

- ต้องการตั้งราคาให้ขายได้
- ไม่ต้องการถูกระบบกดราคา
- อาจรับของมาแพงหรือมีต้นทุน
- ต้องการอธิบายเหตุผลของราคาพรีเมียม

สิ่งที่ให้:

- Seller Price Advisor
- ปัจจัยสนับสนุนราคา
- Fast-sale / Market / Premium Range
- Checklist หลักฐานที่ควรแนบ

#### C. ร้านคอมมือสอง ร้านรับซื้อ ร้านประกอบ และ Reseller

ลักษณะ:

- มี Inventory หลายรายการ
- ต้องประเมินราคารับซื้อและราคาขาย
- ต้องการลดเวลาเช็กราคา
- ต้องการดู Trend และ Margin

สิ่งที่อาจขาย:

- Dashboard
- Price Alert
- CSV Export
- Market Report
- API
- Inventory Price Comparison

### Beachhead Market

กลุ่มที่ควรจับก่อนคือ:

> คนไทยที่กำลังซื้อหรืออัปเกรด Gaming PC มือสอง  
> โดยเฉพาะ CPU และ GPU ช่วงราคา 3,000–15,000 บาทต่อชิ้น

เหตุผล:

- มี Buyer Intent ชัด
- ราคาผันผวน
- คนค้นข้อมูลก่อนซื้อ
- มี Pain เรื่องความคุ้มและความเสี่ยงสูง
- แตกเป็น SEO Long-tail ได้จำนวนมาก

---

## 3.2 ทำมาเพื่ออะไร

Job-to-be-done หลัก:

> “ก่อนโอนเงิน อยากรู้ว่าราคานี้อยู่ตรงไหนของตลาด มีความเสี่ยงอะไร และควรขอหลักฐานอะไรเพิ่ม”

Job-to-be-done ฝั่ง Seller:

> “ก่อนตั้งขาย อยากรู้ว่าราคานี้มีโอกาสขายเร็วแค่ไหน และต้องเพิ่มหลักฐานอะไรเพื่ออธิบายราคา”

Job-to-be-done ฝั่งร้านค้า:

> “ก่อนรับของเข้าร้าน อยากรู้ช่วงราคาตลาดปัจจุบันและ Margin ที่เป็นไปได้ โดยไม่ต้องไล่เช็กหลายแพลตฟอร์ม”

---

## 3.3 ดีกว่าที่อื่นยังไง

| ทางเลือกเดิม | สิ่งที่ผู้ใช้ได้ | ช่องว่าง |
|---|---|---|
| Facebook Marketplace | ประกาศจำนวนมาก | ไม่มีช่วงราคาที่ Normalize และกรองแล้ว |
| Shopee / Lazada | รายการสินค้าและราคาขาย | ปะปนมือหนึ่ง มือสอง ร้านค้า มัดจำ และหลาย Variant |
| กลุ่ม Facebook | ความเห็นจากสมาชิก | ข้อมูลกระจัดกระจาย ขึ้นกับคนตอบ |
| YouTube / Review | ความรู้และ Benchmark | ราคาไม่สดและไม่ใช่ฐานข้อมูล |
| เว็บจัดสเปกมือหนึ่ง | Compatibility และราคาของใหม่ | ไม่ได้ออกแบบเพื่อความเสี่ยงของมือสอง |
| Google AI / Chatbot | คำตอบเร็ว | อาจไม่มี Sample, Evidence หรือวิธีคำนวณชัดเจน |

จุดต่างของเรา:

> ที่อื่นบอกว่า “มีอะไรขาย”  
> เราบอกว่า “ข้อมูลที่พบสะท้อนช่วงราคาอย่างไร และมีเงื่อนไขอะไรที่ควรพิจารณา”

Moat ที่ต้องสร้าง:

- Structured Observation
- Product Normalization
- Variant Awareness
- Evidence Level
- Freshness
- Confidence
- Neutral Price Communication
- Thai Used-PC Specific Knowledge

---

# 4. Target Users

## 4.1 Persona A — First-time Used Buyer

**เป้าหมาย:** ซื้ออะไหล่มือสองโดยไม่พลาด  
**อุปกรณ์:** มือถือเป็นหลัก  
**Entry point:** Google, Facebook, Reels, YouTube Shorts  
**Pain:**

- ไม่รู้ราคากลาง
- แยกรุ่นย่อยไม่ออก
- ไม่รู้ว่าต้องขอดูอะไร
- กลัวโอนแล้วโดนโกง

**Action หลักในเว็บ:**

- Search รุ่น
- Upload Screenshot
- ใช้ Deal Checker
- เปิด Checklist
- เปรียบเทียบมือสองกับมือหนึ่ง

---

## 4.2 Persona B — PC Enthusiast

**เป้าหมาย:** หา Best Value และติดตามราคา  
**อุปกรณ์:** Desktop + Mobile  
**Pain:**

- ต้องเปิดหลายแท็บ
- อยากดู Price History
- อยาก Compare รุ่น
- อยากแชร์ผลให้เพื่อน

**Action หลัก:**

- Compare
- Price History
- Spec Builder
- Save / Share
- ดูข้อมูล Sample และ Confidence

---

## 4.3 Persona C — Casual Seller

**เป้าหมาย:** ตั้งราคาที่ขายได้โดยไม่ขาดทุนเกินไป  
**อุปกรณ์:** มือถือ  
**Pain:**

- ไม่รู้ควรตั้งเท่าไร
- กลัวโดนต่อแรง
- ต้นทุนรับมาแพง
- สินค้ามีประกันและสภาพดีแต่ผู้ซื้อดูแค่เลขกลาง

**Action หลัก:**

- Seller Price Advisor
- ดูปัจจัยสนับสนุนราคา
- ดู Fast-sale / Market / Premium Range
- สร้างภาพสรุปสำหรับแชร์

---

## 4.4 Persona D — Used PC Shop / Reseller

**เป้าหมาย:** บริหารราคาสินค้าหลายรุ่น  
**อุปกรณ์:** Desktop หน้าร้าน  
**Pain:**

- ราคาตลาดเปลี่ยน
- รับของมาแล้วราคาตก
- เช็กทีละรายการเสียเวลา
- อยากรู้ Margin และ Inventory Aging

**Action หลักในอนาคต:**

- Upload CSV
- Dashboard
- Compare Inventory vs Market
- Alert
- Export Report
- API

---

# 5. User Behaviour Hypotheses

## 5.1 Buyer Willingness to Pay

สมมติฐาน:

- ผู้ซื้อของมือสองไม่ค่อยยอมจ่ายเพื่อ “ดูราคากลาง”
- ผู้ซื้ออาจยอมจ่ายเมื่อกำลังจะโอนเงินก้อนใหญ่และต้องการ Manual Review
- Free tool ต้องเป็น Acquisition Layer
- รายได้ที่ Scale กว่าคือ Affiliate และ B2B Tool

ทดลอง Manual Review ได้ แต่ต้องมี Gate:

| Indicator | เกณฑ์ผ่าน |
|---|---:|
| Deal Checker ใช้งานสะสม | ≥ 300 ครั้ง |
| ผู้ใช้กดขอรีวิวละเอียด | ≥ 15 ครั้ง |
| จ่ายเงินจริง | ≥ 5 เคส |
| Conversion | ≥ 1.5% |
| เวลาต่อเคส | ≤ 10 นาที |
| รายได้ต่อชั่วโมง | ≥ 600 บาท |

หากไม่ผ่าน ไม่ควรลงทุนสร้าง Workflow ซับซ้อนสำหรับบริการนี้

## 5.2 Discovery Behaviour

- คนพบเว็บจาก Mobile Search และ Social Content
- คนเช็กราคาเร็วบนมือถือ
- คน Compare หลายรุ่นและจัดสเปกบน Desktop
- Seller ถ่ายรูปและลงข้อมูลผ่านมือถือ
- ร้านค้าใช้ Dashboard บน Desktop

ดังนั้นผลิตภัณฑ์ต้อง:

> Desktop ดีสำหรับข้อมูลเชิงลึก  
> Mobile ดีมากสำหรับ Search, Screenshot และ Deal Checker

## 5.3 Trust Behaviour

ผู้ใช้จะเชื่อข้อมูลมากขึ้นเมื่อเห็น:

- วันที่อัปเดต
- จำนวนตัวอย่าง
- ช่วงราคาแทนเลขเดียว
- Confidence Score
- แหล่งข้อมูลแบบสรุป
- วิธีคำนวณ
- ข้อจำกัด
- เหตุผลที่ราคาสูงหรือต่ำกว่าช่วงตลาดได้

---

# 6. Product Positioning and Neutrality

## 6.1 Positioning Statement

> Used PC Price Radar คือแพลตฟอร์มข้อมูลตลาดที่ช่วยให้ Buyer และ Seller เข้าใจตำแหน่งราคา ไม่ใช่เครื่องมือกดราคาและไม่ใช่ผู้ตัดสินคุณภาพผู้ขาย

## 6.2 Neutral Language Policy

หลีกเลี่ยง:

- แพงเกินไป
- อย่าซื้อ
- Seller ขายแพง
- โดนฟัน
- กดเหลือ
- การันตีคุ้ม
- ปลอดภัยแน่นอน

ใช้:

- ต่ำกว่าช่วงอ้างอิง
- อยู่ในช่วงตลาด
- สูงกว่าช่วงตลาดเล็กน้อย
- ราคาพรีเมียม
- มีปัจจัยสนับสนุนราคา
- ควรขอข้อมูลเพิ่มเติม
- ช่วงราคาต่อรองที่สมเหตุสมผล
- ข้อมูลยังไม่เพียงพอสำหรับประเมิน

## 6.3 Neutral Price Labels

| Label | ความหมาย |
|---|---|
| ต่ำกว่าช่วงอ้างอิง | อาจเป็นดีลน่าสนใจ หรือมีเงื่อนไข/ความเสี่ยงที่ต้องตรวจ |
| อยู่ในช่วงตลาด | ใกล้เคียงข้อมูลส่วนใหญ่ที่ตรวจพบ |
| สูงกว่าช่วงตลาดเล็กน้อย | อาจมีเหตุผลจากสภาพ ประกัน หรือรุ่นย่อย |
| ราคาพรีเมียม | ต้องมีคุณค่าเพิ่มที่อธิบายส่วนต่าง |
| ข้อมูลไม่พอประเมิน | Sample หรือ Evidence ยังไม่เพียงพอ |

สีไม่ควรสื่อแค่ “เขียว = ซื้อ” และ “แดง = ห้ามซื้อ”  
ต้องมีข้อความอธิบายเสมอ เพื่อไม่ให้ระบบกลายเป็นตัวกดราคา Seller

## 6.4 Seller Fairness

ระบบต้องยอมรับว่า Seller อาจ:

- รับสินค้ามาแพง
- มีค่าซ่อม/ดูแล
- ให้ประกันร้าน
- นัดรับเทสต์ได้
- มีประวัติและบริการหลังการขาย
- สินค้าเป็นรุ่นย่อยพรีเมียม
- กล่อง ใบเสร็จ และอุปกรณ์ครบ

อย่างไรก็ตาม:

> ต้นทุนที่ Seller รับมา ไม่ควรถูกนำไปดัน Market Index

ต้องแยก:

### Market Price Index

สะท้อนข้อมูลตลาด ไม่สนต้นทุนส่วนบุคคล

### Seller Price Advisor

ให้ Seller กรอกข้อมูลส่วนตัว เช่น ต้นทุน กำไร และเวลาที่ต้องการขาย แล้วประเมินความเป็นไปได้ของราคานั้น

---

# 7. Value Proposition

## 7.1 Buyer Value

- ลดเวลาค้นข้อมูล
- เข้าใจช่วงราคา
- เห็นความสดและความมั่นใจของข้อมูล
- รู้สิ่งที่ต้องถามผู้ขาย
- เปรียบเทียบมือสองกับมือหนึ่ง
- ประเมินดีลจาก Screenshot
- ลดโอกาสซื้อผิดรุ่นหรือผิดสภาพ

## 7.2 Seller Value

- ตั้งราคาได้มีเหตุผล
- รู้ว่าราคาอยู่ในระดับ Fast-sale / Market / Premium
- รู้ว่าต้องแนบหลักฐานอะไร
- ไม่ถูกตัดสินจากราคาเพียงตัวเลขเดียว
- สร้างภาพสรุปราคาไว้ตอบผู้ซื้อ

## 7.3 Shop Value

- ลดเวลาตรวจตลาด
- ดูแนวโน้มราคา
- ประเมินราคารับซื้อ
- เปรียบเทียบ Inventory
- ลดความเสี่ยงของ Stock Aging
- ใช้ข้อมูลประกอบการตั้ง Margin

---

# 8. Business Model

## 8.1 Layer 1 — Consumer Free

ฟีเจอร์:

- Price Index
- Deal Checker
- Price History
- Used vs New
- Spec Builder
- Buying Guides
- Comparison
- Screenshot Checker

รายได้:

- Affiliate
- Sponsored Offer
- Display Ads เมื่อ Traffic มากพอ
- Lead Generation

## 8.2 Layer 2 — Seller Tools

ฟีเจอร์:

- Seller Price Advisor
- Fast-sale / Market / Premium Suggestion
- Evidence Checklist
- Shareable Price Card
- Price Alert
- Historical Trend

รูปแบบรายได้ในอนาคต:

- Freemium
- Pro Features
- Subscription

## 8.3 Layer 3 — Shop / B2B Dashboard

ตัวอย่างฟีเจอร์:

- Inventory Upload
- Market Comparison
- Suggested Buy Price
- Suggested Sell Range
- Margin Estimation
- Price Drop Alert
- Stock Aging
- Export CSV
- Weekly Report

ราคาเพื่อใช้ทดสอบสมมติฐาน:

| Package | สมมติฐานราคา |
|---|---:|
| Seller Lite | 299 บาท/เดือน |
| Shop Dashboard | 990 บาท/เดือน |
| Data Export / API | 1,500–3,000 บาท/เดือน |

ตัวเลขนี้ยังไม่ใช่ราคาที่ผ่านการพิสูจน์ ต้อง Validate กับร้านจริง

## 8.4 Layer 4 — Market Data Product

สินค้าที่อาจขาย:

- Weekly Market Report
- Monthly Price Trend
- API ราคากลาง
- Model Liquidity Score
- Used-to-New Price Ratio
- Supply Heatmap
- Market Volatility

ลูกค้าเป้าหมาย:

- ร้านคอมมือสอง
- ร้านรับซื้อ
- ร้านประกอบ
- Content Creator
- เว็บไซต์จัดสเปก
- ผู้ให้บริการประกัน/สินเชื่อในอนาคต

## 8.5 Layer 5 — Optional Marketplace Expansion

ทำเมื่อมี:

- Traffic
- First-party Data
- Seller Supply
- Trust & Safety Capacity
- ทีม Operation
- Legal Review

ไม่ใช่ MVP และไม่ใช่เงื่อนไขที่ทำให้ธุรกิจระยะแรกอยู่รอด

---

## 8.6 Unit Economics Assumptions

### ค่าใช้จ่ายรายเดือนช่วง Prototype

| รายการ | ช่วงประมาณการ |
|---|---:|
| VPS / Database / Backup | 800–1,500 บาท |
| Storage / Monitoring | 200–500 บาท |
| AI Search / Vision / Collector | 300–1,500 บาท |
| Domain เฉลี่ยรายเดือน | 50–100 บาท |
| Tools อื่น ๆ | 200–800 บาท |
| รวมโดยประมาณ | 1,550–4,400 บาท |

ยังไม่รวมค่าแรงเจ้าของโปรเจกต์

### Revenue Scenario เพื่อแตะ 6,000 บาท/เดือน

| ช่องทาง | ตัวอย่างสมมติฐาน |
|---|---:|
| Affiliate | 1,500 |
| Sponsored / Lead | 1,000 |
| Shop Dashboard 2 ร้าน × 990 | 1,980 |
| Market Report / Export 1 ราย | 1,500 |
| รวม | 5,980 |

นี่เป็น Scenario สำหรับวัดความเป็นไปได้ ไม่ใช่การการันตีรายได้

---

# 9. Validation and Numeric Indicators

## 9.1 North Star Metric

ระยะแรก:

> **จำนวน Valid Price Checks ต่อเดือน**

ประกอบด้วย:

- Product Price Page View ที่มี Intent
- Deal Checker Completed
- Screenshot Checker Completed
- Compare Completed

ระยะ Data Product:

> **จำนวน Fresh Valid Observations ที่ผ่านการกรองต่อเดือน**

## 9.2 30-Day Data Prototype Gate

| Indicator | เป้าหมาย |
|---|---:|
| รุ่นที่ทดสอบ | 20 รุ่น |
| Raw observations | ≥ 600 |
| Valid observations | ≥ 400 |
| Valid rate หลังกรอง | ≥ 65% |
| Normalize รุ่นถูกต้อง | ≥ 90% |
| อ่านราคาถูกต้อง | ≥ 95% |
| แยก Whole PC / Component | ≥ 90% |
| แยก Deposit / รับซื้อ / ของเสีย | ≥ 85% |
| Duplicate หลังกรอง | ≤ 10% |
| เวลาตรวจด้วยคน | ≤ 30 นาที/วัน |
| ต้นทุนต่อ Valid Observation | ต้องวัดจริง |

## 9.3 90-Day Product Gate

| Indicator | เป้าหมาย |
|---|---:|
| รุ่นที่มี Price Index | ≥ 50 |
| Valid observations | ≥ 1,000 |
| รุ่นยอดนิยมมี Sample | ≥ 20 รายการ/รุ่น |
| ข้อมูลอายุไม่เกิน 14 วัน | ≥ 70% |
| Monthly sessions | ≥ 2,000 |
| Price checks | ≥ 1,000/เดือน |
| Deal Checker completed | ≥ 200 |
| Returning users | ≥ 10% |
| Outbound affiliate clicks | ≥ 200/เดือน |
| Revenue แรก | ≥ 1,000 บาท |
| ร้านที่สนใจ Dashboard | ≥ 3 ร้าน |
| ร้านที่ทดลองใช้ | ≥ 1 ร้าน |

## 9.4 6-Month Gate

| Indicator | เป้าหมาย |
|---|---:|
| Product Index | ≥ 100 รุ่น |
| Monthly sessions | ≥ 10,000 |
| Fresh observations | ≥ 3,000/เดือน |
| Returning users | ≥ 15–20% |
| Organic traffic | เติบโตต่อเนื่อง 3 เดือน |
| Affiliate conversions | ≥ 20/เดือน |
| B2B paying customers | ≥ 2 |
| Monthly revenue | ≥ 6,000 บาท |
| Human operation | ≤ 60 นาที/วันโดยเฉลี่ย |

## 9.5 Funnel Metrics

```text
Search / Social
→ Product Page
→ Deal Checker / Compare
→ Affiliate / Lead / Save
→ Return Visit
```

| Funnel | KPI เริ่มต้น |
|---|---:|
| Product Page → Deal Checker | 5–10% |
| Product Page → Compare | 5–12% |
| Product Page → Affiliate Click | 3–8% |
| Deal Checker Start → Complete | ≥ 40% |
| Visitor → Returning User | 10–20% |
| Seller Advisor Start → Complete | ≥ 50% |

## 9.6 Solo Founder Operational KPI

| Indicator | Target |
|---|---:|
| Manual review queue | ≤ 10% ของ observations |
| เวลา review 100 รายการ | ≤ 30 นาที |
| Support รวมต่อวัน | ≤ 30 นาทีใน MVP |
| Collector errors ที่ต้องแก้เอง | ≤ 3 ครั้ง/สัปดาห์ |
| Price update workload | ≤ 20 นาที/วัน |
| คิวค้างเกิน 24 ชม. | ≤ 10 รายการ |

---

# 10. Product Scope

## 10.1 MVP v1 — Required

### Public

1. Home
2. Search / Price Index
3. Product Price Detail
4. Deal Checker
5. Screenshot Checker
6. Compare Products
7. Used vs New Comparison
8. Buying Guide
9. Budget Build Landing Pages
10. About Methodology
11. Disclaimer / Terms / Privacy

### Admin

1. Product Master
2. Product Alias
3. Price Observation Queue
4. Human Review Queue
5. Price Index Calculation
6. Source Registry
7. Collector Status
8. Data Freshness Dashboard
9. Article / SEO Content
10. Audit Log

## 10.2 MVP v1.5 — Important

1. Price History
2. Seller Price Advisor
3. Shareable Result Card
4. User-assisted URL / Screenshot Submission
5. Save / Watchlist
6. Price Alert แบบ Email
7. Public Methodology Page
8. Confidence Breakdown

## 10.3 Phase 2 — B2B Pilot

1. Shop CSV Upload
2. Inventory vs Market Dashboard
3. Suggested Buy Price
4. Price Drop Alert
5. Export
6. Weekly Report
7. Multi-user Shop Account

## 10.4 Future / Optional

1. Seller Listing
2. Verified Seller
3. Seller Profile
4. Contact Lead
5. Marketplace
6. Payment
7. Escrow
8. Review
9. Dispute
10. Native Application

---

# 11. User Journeys

## 11.1 Buyer — Search a Model

```text
Google Search
→ Product Price Page
→ เห็นช่วงราคา + Sample + Freshness
→ ดูรุ่นใกล้เคียง
→ กรอกราคาที่เจอ
→ Deal Checker Result
→ เปิด Buying Checklist
→ กด Affiliate หรือแชร์ผล
```

## 11.2 Buyer — Upload Screenshot

```text
Home / Deal Checker
→ Upload Screenshot
→ AI อ่านรุ่น ราคา และเงื่อนไข
→ User ตรวจความถูกต้อง
→ ระบบ Normalize
→ Deal Checker
→ แสดงผล + สิ่งที่ต้องถาม Seller
→ User ยินยอมส่ง Observation แบบไม่ระบุตัวตน
```

## 11.3 Seller — Set a Fair Price

```text
Seller Price Advisor
→ เลือกรุ่น
→ กรอกสภาพ ประกัน รุ่นย่อย ต้นทุน และเวลาที่ต้องการขาย
→ ระบบแสดง Fast-sale / Market / Premium Range
→ แสดงสิ่งที่ช่วยอธิบายราคา
→ Generate Share Card
```

## 11.4 Shop — Compare Inventory

```text
Login
→ Upload CSV / Sheet
→ Map Product Names
→ Compare Current Price vs Market
→ แสดง Stock ที่สูง/ต่ำกว่าช่วงตลาด
→ ดู Margin และ Aging
→ Export Report
```

## 11.5 Admin — Review Data

```text
Collector runs
→ Raw Observations
→ Auto Normalize / Filter
→ Confidence < threshold เข้า Review Queue
→ Admin Approve / Reject / Correct
→ Recalculate Index
→ Publish Snapshot
```

---

# 12. Information Architecture

```text
/
├── /price
│   ├── /price/{slug}
│   └── /price/{slug}/history
├── /deal-checker
│   ├── /deal-checker/result/{uuid}
│   └── /deal-checker/screenshot
├── /compare
│   └── /compare/{slug-a}-vs-{slug-b}
├── /build
│   └── /build/{slug}
├── /seller-price-advisor
├── /guide
│   └── /guide/{slug}
├── /methodology
├── /about
├── /privacy
├── /terms
├── /disclaimer
├── /admin
│   ├── /admin/products
│   ├── /admin/product-aliases
│   ├── /admin/price-observations
│   ├── /admin/review-queue
│   ├── /admin/price-indices
│   ├── /admin/sources
│   ├── /admin/collector-jobs
│   ├── /admin/articles
│   └── /admin/audit-logs
└── /shop
    ├── /shop/dashboard
    ├── /shop/inventory
    └── /shop/reports
```

---

# 13. Screen Requirements for Stitch

ส่วนนี้เป็น Design Brief โดยตรง

## 13.1 Screen 01 — Home

### Goal

ทำให้ผู้ใช้เข้าใจภายใน 5 วินาทีว่าเว็บใช้ทำอะไร

### Hero

Headline:

> เช็กราคากลางคอมมือสอง ก่อนซื้อ ก่อนขาย ก่อนโดนงง

Subheadline:

> ดูช่วงราคาประกาศล่าสุด จำนวนตัวอย่าง ความสดของข้อมูล และประเมินดีลจากราคา หรือ Screenshot ได้ในหน้าเดียว

Primary CTA:

- `ค้นหารุ่นสินค้า`

Secondary CTA:

- `เช็กดีลจาก Screenshot`

### Home Sections

1. Search Bar
2. Popular Products
3. Market Movers
4. Deal Checker CTA
5. How It Works
6. Used vs New Highlights
7. Buying Guide
8. Data Transparency
9. Seller Price Advisor CTA
10. Footer

### Data Transparency Card

แสดง:

- รุ่นที่ติดตาม
- จำนวน observations
- อัปเดตล่าสุด
- สูตร Median / Quartile
- Link ไป Methodology

---

## 13.2 Screen 02 — Search / Price Index

### Required Elements

- Search with autocomplete
- Category filters
- Brand filters
- Price update freshness
- Confidence filter
- Sort:
  - Popular
  - Latest updated
  - Price drop
  - Most searched
- Product cards / table toggle

### Product Card

- Product name
- Category / Generation
- Market range
- Median
- Trend arrow
- Sample size
- Freshness
- Confidence badge
- CTA `ดูรายละเอียด`
- CTA `เช็กดีล`

### Mobile

ใช้ Card แนวตั้ง ไม่ใช้ตารางกว้าง

### Desktop

มี Table View สำหรับ Power User

---

## 13.3 Screen 03 — Product Price Detail

### Above the Fold

- Product name
- Product image / generic illustration
- Market range
- Median
- Updated date
- Sample size
- Confidence
- Price trend mini chart
- CTA `กรอกราคาที่คุณเจอ`
- CTA `เปรียบเทียบรุ่น`

### Price Distribution

แสดงช่วง:

```text
Below Reference | Market Range | Premium Range
```

ไม่ใช้สีอย่างเดียว ต้องมี Label และคำอธิบาย

### Recommended Sections

1. Price Summary
2. Price History
3. Variant / Condition Adjustment
4. Used vs New Comparison
5. Buyer Checklist
6. Seller Fairness Note
7. Similar Products
8. Methodology
9. FAQ
10. Affiliate / Sponsored Offer แบบติดป้าย

### Example Seller Fairness Note

> ราคาที่สูงกว่าช่วงอ้างอิงอาจสมเหตุสมผลได้ หากเป็นรุ่นย่อยพรีเมียม สภาพดี มีประกันเหลือ อุปกรณ์ครบ หรือผู้ขายมีบริการเพิ่ม

---

## 13.4 Screen 04 — Deal Checker Form

### Input Modes

Tabs:

1. กรอกข้อมูลเอง
2. Upload Screenshot
3. Paste URL

### Manual Fields

- Product
- Asking price
- Condition
- Variant
- Warranty
- Box
- Receipt
- Test evidence
- Intended use
- Current CPU / GPU / PSU optional

### Screenshot Flow

1. Upload
2. Processing
3. AI extracted fields
4. User confirmation
5. Result

### Consent

Checkbox:

> ยินยอมให้นำข้อมูลรุ่น ราคา และวันที่จากดีลนี้ไปใช้ปรับปรุงดัชนีแบบไม่ระบุตัวตน

ไม่ควรบังคับ

---

## 13.5 Screen 05 — Deal Checker Result

### Result Summary

- Position in market
- Price score
- Risk score
- Value score
- Fit score
- Suggested negotiation range
- Confidence
- Data used

### Neutral Result Example

> ราคานี้สูงกว่าช่วงตลาดเล็กน้อย แต่ยังอาจสมเหตุสมผล หากสินค้ามีประกันเหลือและมีผลเทสต์ครบ

### Action List

- สิ่งที่ควรถาม Seller
- หลักฐานที่ควรขอ
- จุดเสี่ยงของรุ่น
- รุ่นอื่นที่ควรเทียบ
- Used vs New option
- Share result

### Disclaimer

> ผลประเมินเป็นข้อมูลประกอบการตัดสินใจจากข้อมูลที่ผู้ใช้ให้และข้อมูลตลาดที่ระบบรวบรวม ไม่ใช่การรับประกันสินค้า ผู้ขาย หรือธุรกรรม

---

## 13.6 Screen 06 — Compare Products

### Desktop

Comparison table

Rows:

- Used market range
- New price reference
- Performance class
- VRAM / Core / Generation
- Power use
- Recommended PSU
- Used risk
- Warranty availability
- Value score
- Best for

### Mobile

Stacked Compare Cards + Sticky product selector

---

## 13.7 Screen 07 — Used PC Spec Builder

### Steps

1. Choose budget
2. Choose use case
3. Select CPU
4. Select GPU
5. Select remaining components
6. Review compatibility
7. Price range summary

### Output

- Low estimate
- Market estimate
- Premium estimate
- Compatibility warnings
- PSU warning
- Used risk summary
- New alternative comparison
- Shareable build

---

## 13.8 Screen 08 — Seller Price Advisor

### Inputs

- Product
- Variant
- Condition
- Warranty
- Accessories
- Benchmark
- Cost basis optional and private
- Target profit optional
- Desired sale speed

### Output

- Fast-sale Range
- Market Range
- Premium Range
- Current target position
- Estimated selling difficulty
- Evidence suggestions
- Shareable seller card

### Important UX

ห้ามใช้ข้อความที่ทำให้ Seller รู้สึกถูกตำหนิ

---

## 13.9 Screen 09 — Buying Guide

### Layout

- Article header
- Updated date
- Reading time
- Table of contents
- Checklist cards
- Warning cards
- Product links
- Related Price Index
- FAQ
- Sticky CTA `เช็กดีล`

---

## 13.10 Screen 10 — Methodology

ต้องออกแบบให้อ่านง่ายและสร้าง Trust

แสดง:

1. Data sources แบบสรุป
2. Asking price vs sold price
3. วิธี Normalize
4. วิธีกรอง Duplicate
5. วิธีกรอง Outlier
6. Median / Q1 / Q3
7. Confidence score
8. Freshness
9. Neutrality policy
10. Data correction / report

ใช้ Diagram มากกว่าข้อความยาว

---

## 13.11 Screen 11 — Admin Data Quality Dashboard

### KPI Cards

- Raw observations today
- Valid observations
- Auto-approved
- Human review
- Rejected
- Source errors
- Products stale > 14 days
- Low confidence products

### Charts

- Valid rate by source
- Observations by category
- Price movement
- Collector health
- Review workload
- Cost per valid observation

### Queues

- Unmatched product
- Suspected duplicate
- Whole PC / Deposit
- Outlier
- Low confidence
- Source blocked

---

## 13.12 Screen 12 — Admin Observation Review

Split view:

### Left

- Raw title
- Screenshot or source evidence
- Asking price
- Source
- Captured time

### Right

- Suggested product
- Variant
- Condition
- Flags
- Confidence
- Approve / Correct / Reject
- Reason
- Keyboard shortcuts

เป้าหมาย: Review 100 รายการไม่เกิน 30 นาที

---

## 13.13 Screen 13 — Shop Dashboard Pilot

### Sections

- Inventory summary
- Current market value
- Items above market
- Items below market
- Margin risk
- Price drop alert
- Aging inventory
- Upload CSV
- Export report

---

## 13.14 Required States

Stitch ต้องออกแบบ State ต่อไปนี้ด้วย:

- Loading
- Empty
- No data
- Low confidence
- Stale data
- Source temporarily unavailable
- Screenshot processing
- Screenshot extraction failed
- Product not found
- User correction required
- Error
- Success
- Mobile upload permission
- Offline / retry

---

# 14. Design Direction

## 14.1 Visual Personality

- Technology
- Trustworthy
- Data-driven
- Friendly
- Neutral
- Practical
- Gaming-aware แต่ไม่เป็น Neon Gaming จนอ่านยาก

## 14.2 Suggested Mood

> “Data platform สำหรับคนเล่นคอมจริง”  
> ไม่ใช่ Marketplace ตลาดนัด  
> ไม่ใช่เว็บองค์กรแข็งทื่อ  
> ไม่ใช่ร้าน Gaming RGB เต็มจอ

## 14.3 Color Direction

แนวทางแนะนำ:

- Base: Dark navy / Graphite หรือ Light neutral
- Primary: Blue / Cyan / Teal
- Accent: Amber สำหรับ Warning / Premium
- Positive: Green แบบ muted
- Risk: Red ใช้เฉพาะกรณีความเสี่ยงชัดเจน
- Confidence: Scale 3 ระดับที่ไม่พึ่งสีอย่างเดียว

Stitch ควรเสนอทั้ง:

1. Light Theme
2. Dark Theme หรือ Dark Hero + Light Content

## 14.4 Typography

- ภาษาไทยอ่านง่าย
- รองรับตัวเลขราคาเด่น
- Numeric alignment สำหรับตาราง
- ไม่ใช้ฟอนต์แนว Gaming ที่อ่านยาก

## 14.5 Layout Principles

- Mobile-first
- Data hierarchy ชัด
- Above-the-fold ตอบคำถามหลักทันที
- ใช้ Card, Chips, Distribution bar และ Mini chart
- ตารางต้องมี Mobile alternative
- CTA ไม่เยอะเกินไป
- Affiliate/Sponsor ต้องแยกจาก Data ชัดเจน

## 14.6 Accessibility

- Contrast ผ่านมาตรฐาน
- ไม่ใช้สีเป็นตัวสื่อความหมายเพียงอย่างเดียว
- Keyboard navigation
- Form label ชัด
- Error message อธิบายได้
- Touch target อย่างน้อย 44px
- Chart ต้องมี text summary

---

# 15. Market Data Strategy

## 15.1 Data Types

ต้องแยกข้อมูล:

```text
asking_price
reported_final_price
verified_sale_price
shop_buying_price
trade_in_price
new_retail_price
```

MVP ส่วนใหญ่จะมี `asking_price`

หน้าเว็บต้องใช้คำว่า:

> ช่วงราคาประกาศที่ตรวจพบ

ไม่ใช่:

> ราคาซื้อขายจริง

## 15.2 Data Source Categories

1. Platform Sampling
2. AI Web Search
3. Screenshot Evidence
4. User Submission
5. Seller Submission
6. Shop Feed
7. Public Allowed Source
8. External Benchmark
9. New Retail / Affiliate Feed

## 15.3 Bootstrap Reality

ช่วง 0–3 เดือน Facebook Marketplace และ Shopee อาจมีสัดส่วนข้อมูลสูง เพราะเป็นตลาดที่ผู้ใช้จริงอยู่

เป้าหมาย Dependency:

| ระยะ | สัดส่วนจากแหล่งหลัก 2 เจ้า | First-party / Other |
|---|---:|---:|
| 0–3 เดือน | 70–90% | 10–30% |
| 6 เดือน | ≤ 70% | ≥ 30% |
| 12 เดือน | ไม่มีแหล่งเดียวเกิน 40% | ≥ 60% |

ตัวเลขเป็นเป้าหมายลดความเสี่ยง ไม่ใช่ข้อบังคับตายตัว

## 15.4 Data Retention

Raw evidence:

- เก็บเฉพาะที่จำเป็น
- ตั้ง TTL เช่น 30–90 วัน
- ใช้ Audit / Dedup / Validation
- ลบหรือ Mask ข้อมูลส่วนบุคคล
- Aggregate result เก็บระยะยาว

ไม่เก็บหากไม่จำเป็น:

- ชื่อ Seller
- รูปโปรไฟล์
- เบอร์โทร
- Messenger
- ที่อยู่ละเอียด
- Chat
- ข้อความประกาศเต็ม
- รูปทั้งหมด

---

# 16. Collector Architecture

## 16.1 Pipeline

```text
Source Adapter
→ Raw Observation
→ Evidence Validation
→ Listing Type Classification
→ Product Normalization
→ Variant Detection
→ Duplicate Detection
→ Invalid Listing Filter
→ Outlier Detection
→ Weight Assignment
→ Price Engine
→ Confidence Calculation
→ Human Review Queue
→ Price Index Snapshot
```

## 16.2 Adapter Pattern

```text
FacebookAgentAdapter
ShopeeSamplingAdapter
PerplexitySearchAdapter
OpenAIWebSearchAdapter
GeminiSearchAdapter
ScreenshotVisionAdapter
UserSubmissionAdapter
ShopCsvAdapter
EbayApiAdapter
ManualImportAdapter
```

แต่ละ Adapter เปิด/ปิดได้แยกกัน

ห้ามให้ Price Engine ผูกกับ HTML หรือ Selector ของ Source ใด Source หนึ่ง

## 16.3 If a Source Is Blocked

1. Stop adapter immediately
2. Do not rotate IP/account to evade
3. Mark source as unavailable
4. Keep last known snapshot
5. Reduce freshness/confidence
6. Increase AI Search sampling
7. Increase Screenshot/User-assisted collection
8. Increase other public sources
9. Display stale-data status honestly
10. Investigate official/partner route later

## 16.4 Collector Health Metrics

- Success rate
- Valid rate
- Duplicate rate
- Cost per valid observation
- Source latency
- Last successful run
- Parse/extraction failure
- Human review percentage
- Blocked status

---

# 17. AI-Assisted Data Collection

## 17.1 OpenAI / Gemini / Perplexity Role

AI ไม่ใช่ Source of Truth

AI ทำหน้าที่:

- Search candidate URLs
- อ่าน Screenshot
- Extract fields
- Normalize product names
- Classify listing type
- Detect deposit / whole PC / defect
- Return structured JSON
- Suggest confidence

Price Engine ของเราเป็นคนคำนวณราคา

## 17.2 Deep Research / Web Search Workflow

```text
Product Queue
→ AI Web Search
→ Candidate observations with URL
→ URL/evidence validator
→ Structured observation
→ Price Engine
```

ห้ามรับผลแบบ:

```json
{
  "average_price": 7900
}
```

ควรรับ Observation รายตัว:

```json
{
  "product_query": "Ryzen 7 5700X3D มือสอง ราคา ไทย",
  "observations": [
    {
      "raw_title": "Ryzen 7 5700X3D มือสอง",
      "asking_price": 7900,
      "currency": "THB",
      "source_url": "https://example.com/item",
      "source_domain": "example.com",
      "captured_at": "2026-07-23",
      "evidence_type": "source_page",
      "confidence": 0.82
    }
  ]
}
```

กฎ:

- ไม่มี URL หรือภาพ → ไม่เข้า Index
- URL เปิดไม่ได้ → Unverified
- Search snippet → น้ำหนักต่ำ
- URL context ยืนยันรุ่นและราคา → น้ำหนักกลาง
- Screenshot เห็นข้อมูลชัด → น้ำหนักสูง
- AI สรุปโดยไม่มีหลักฐาน → Weight 0

## 17.3 Perplexity

เหมาะกับ:

- Discovery
- หา URL
- Search Result JSON
- Cross-source candidate

ไม่เหมาะกับ:

- ใช้เลขสรุปเป็น Price Index โดยตรง
- เข้าถึง Marketplace ที่ต้อง Login

## 17.4 NotebookLM

เหมาะกับ:

- Bootstrap
- Seed Data
- Validation Lab
- Screenshot batch → Table
- Export Google Sheets

ไม่เหมาะกับ:

- Production pipeline ระยะยาว
- อัปเดตอัตโนมัติประจำวัน

## 17.5 Screenshot + Vision API

ระยะยาวควรใช้:

```text
Screenshot
→ Gemini/OpenAI Vision
→ JSON Schema
→ PHP Validator
→ Database
```

## 17.6 Browser Agent Sampling

Claude / Browser Agent ที่ทำงานบนหน้าที่เปิดอยู่:

เหมาะกับ:

- Prototype
- Sample collection
- Facebook pages ที่ Search ไม่เห็น
- ไม่ต้องเขียน Parser รอบแรก

ข้อจำกัด:

- ยังถือเป็น Automated Collection
- ยังถูก Block ได้
- ไม่ควรข้าม Login/CAPTCHA
- ไม่ควรใช้ Mass Scale
- ต้องใช้ Browser Profile แยก
- มี Prompt Injection / Privacy Risk

สถานะในระบบ:

> Interactive Market Sampler ไม่ใช่ Production Crawler หลัก

## 17.7 User-assisted Data Collection

แนวคิด:

# **Give Data, Get Insight**

User ส่ง:

- URL
- Screenshot
- Copy text

User ได้:

- Deal Checker Result
- Price range
- Risk checklist

ระบบได้:

- First-party observation
- Buyer intent
- Evidence
- Timestamp

ช่องทาง:

- Web Upload
- PWA Share Target
- Browser Extension / Bookmarklet ในอนาคต
- LINE OA / Bot ในอนาคต

---

# 18. Source and Evidence Policy

## 18.1 Source Risk Matrix

| Level | Source | แนวทาง |
|---|---|---|
| Green | Partner API/Feed, Shop CSV, User submitted | ใช้เป็นแกนได้ |
| Amber | Public page ที่เข้าถึงได้และเก็บขั้นต่ำ | ใช้แบบจำกัดและติดตาม Terms |
| Orange | Search snippet, AI discovery | ใช้หา candidate / น้ำหนักต่ำ |
| Red | Bypass login, CAPTCHA, anti-bot, rotate account | ไม่ทำ |

## 18.2 Evidence Levels

| Level | Evidence | Weight เริ่มต้น |
|---|---|---:|
| A | Screenshot/หน้าเว็บเห็นรุ่นและราคาชัด | 1.00 |
| B | URL เปิดได้ ระบบยืนยันข้อมูลได้ | 0.85 |
| C | AI Search + URL Context ยืนยันบางส่วน | 0.65 |
| D | Search snippet เท่านั้น | 0.30 |
| E | AI summary ไม่มี URL/ภาพ | 0.00 |

น้ำหนักต้องทดสอบและปรับจากข้อมูลจริง

## 18.3 External Benchmark

### eBay

ใช้เป็น:

- Global used benchmark
- Variant reference
- Sanity check

ไม่ใช้เป็น Thai market price โดยตรง

### Lazada / Alibaba / AliExpress

ใช้เป็น:

- New retail benchmark
- Replacement parts
- Accessories
- Used-to-New Ratio
- Affiliate candidate

ไม่ใช้ปนกับ Thai used-price index

---

# 19. Price Calculation and Fairness

## 19.1 Why Not Simple Average

Average ถูกลากด้วย:

- Scam price
- Deposit
- Whole PC
- Wrong product
- Premium variant
- Defect
- Outdated listing
- Duplicate listing

จึงใช้:

- Median
- Q1
- Q3
- IQR
- Weighted Median
- Source Weight
- Freshness Weight
- Evidence Weight

## 19.2 Suggested Metrics

```text
price_min_observed
q1
median
q3
price_max_observed
sample_size
valid_sample_size
fresh_sample_ratio
source_mix
confidence_score
```

## 19.3 Outlier Rule

เริ่มต้นทดลอง:

```text
IQR = Q3 - Q1
Lower Fence = Q1 - 1.5 * IQR
Upper Fence = Q3 + 1.5 * IQR
```

รายการนอกช่วงไม่จำเป็นต้องลบทันที แต่ Flag เพื่อ Review

## 19.4 Weight Formula — Draft

```text
observation_weight =
  evidence_weight
  × freshness_weight
  × source_quality_weight
  × classification_confidence
```

ตัวอย่าง Freshness:

| อายุข้อมูล | Weight |
|---|---:|
| 0–7 วัน | 1.00 |
| 8–14 วัน | 0.90 |
| 15–30 วัน | 0.70 |
| 31–60 วัน | 0.40 |
| > 60 วัน | 0.10 หรือไม่นำเข้าดัชนี |

ต้องปรับตามความผันผวนของแต่ละ Category

## 19.5 Confidence Score

ปัจจัย:

- Sample size
- Source diversity
- Evidence quality
- Freshness
- Variance
- Variant coverage
- Asking vs sold data
- Human-reviewed ratio

ตัวอย่าง Label:

| Score | Label |
|---:|---|
| 1–3 | ต่ำ |
| 4–6 | ปานกลาง |
| 7–8 | ดี |
| 9–10 | สูง |

## 19.6 Product Variant Awareness

ต้องแยก:

```text
product_family: RTX 3070
variant: ASUS TUF Gaming OC
condition
warranty_months
box_status
receipt_status
repair_history
test_evidence
```

มิฉะนั้น Premium model จะถูกเฉลี่ยกับ OEM / หมดประกันแบบไม่เป็นธรรม

## 19.7 Seller Justification Score

ปัจจัยสนับสนุนราคา:

- Warranty
- Box
- Receipt
- Benchmark
- Serial/date evidence
- Test available
- Premium variant
- Shop warranty
- Service / Delivery

ผลลัพธ์ตัวอย่าง:

> ราคาสูงกว่าช่วงตลาด 8%  
> แต่มีปัจจัยสนับสนุนราคา 5 จาก 7 ข้อ  
> จัดอยู่ในระดับพรีเมียมที่พออธิบายได้

---

# 20. 5700X3D Prototype Case

ส่วนนี้เป็น **ตัวอย่างการทดสอบในขั้น Research** ไม่ใช่ราคาที่พร้อมเผยแพร่ และต้อง Re-run ก่อนใช้จริง

## 20.1 Prototype Observations

ตัวอย่างข้อมูลที่เคยค้นพบในการทดลอง:

```text
7,000
7,290
7,990
8,499
9,790
10,990
```

## 20.2 Prototype Calculation

```text
Sample size: 6
Mean: 8,593
Median: 8,245
Q1–Q3: ประมาณ 7,465–9,467
Prototype market display: 7,500–8,600
Prototype confidence: 5/10
```

## 20.3 Why Confidence Is Low

- Sample น้อย
- Source mix ต่ำ
- หลายประกาศอาจหมดแล้ว
- ยังไม่มี Facebook Marketplace sample
- ยังไม่มี verified sold price
- Condition / Warranty แตกต่างกัน
- อาจมีร้านค้าและบุคคลปะปนกัน

## 20.4 UI Example

```text
AMD Ryzen 7 5700X3D มือสอง

ช่วงราคาประกาศที่ตรวจพบ:
7,500–8,600 บาท

ค่ากลาง:
ประมาณ 8,250 บาท

ข้อมูล:
6 รายการ
ความมั่นใจ: ปานกลางค่อนข้างต่ำ
```

## 20.5 Lesson

- AI answer ที่บอกช่วงราคาอย่างเดียวไม่เพียงพอ
- ต้องเก็บ Observation รายตัว
- ต้องมี Evidence
- ต้องให้ Price Engine คำนวณเอง
- ต้องโชว์ Sample, Freshness และ Confidence

---

# 21. Trust, Safety and Platform Role

## 21.1 MVP Role

เราเป็น:

- Price Reference
- Decision Tool
- Market Intelligence

เราไม่เป็น:

- Seller
- Buyer
- Broker
- Payment Processor
- Escrow
- Product Inspector
- Guarantor
- Transaction Party

## 21.2 Disclaimer Principle

> ข้อมูลและผลประเมินเป็นข้อมูลประกอบการตัดสินใจ ไม่ใช่การรับประกันสินค้า ผู้ขาย หรือธุรกรรม

## 21.3 If Seller Listing Is Added Later

ต้องแยก Badge:

- Phone Verified
- Email Verified
- Identity Verified
- Bank Name Matched
- Product Evidence Submitted

ข้อความบังคับ:

> Identity Verified ไม่ได้หมายความว่าสินค้าได้รับการตรวจสอบหรือธุรกรรมปลอดภัยแน่นอน

## 21.4 Incident Workflow — Future Listing

```text
Report
→ Risk Triage
→ Temporary Hide
→ Seller Clarification
→ Review Evidence
→ Restore / Suspend
→ Audit Log
→ Appeal
```

## 21.5 Legal and Policy Note

เอกสารนี้ไม่ใช่คำปรึกษากฎหมาย

ก่อนเปิดฟีเจอร์:

- Seller Listing
- Identity Verification
- Payment
- Escrow
- Buyer Protection

ต้องให้ผู้เชี่ยวชาญตรวจ:

- Terms
- Privacy Notice
- PDPA
- Platform obligations
- Retention
- Incident response

---

# 22. SEO Strategy

## 22.1 Core Direction

เน้น Long-tail buyer intent:

```text
[รุ่น] + มือสอง + ราคา
[รุ่น] + มือสอง + คุ้มไหม
[รุ่น] + ยังน่าใช้ไหม
[หมวด] + มือสอง + เช็กยังไง
คอมมือสอง + งบ
[รุ่น A] vs [รุ่น B] + มือสอง
```

## 22.2 First Page Clusters

### Price

- RTX 3070 มือสอง ราคา
- RTX 3060 Ti มือสอง ราคา
- RX 6600 มือสอง ราคา
- Ryzen 5 3600 มือสอง ราคา
- Ryzen 5 5600 มือสอง ราคา
- Ryzen 7 5700X3D มือสอง ราคา

### Guide

- การ์ดจอมือสองเช็กยังไง
- SSD มือสองซื้อได้ไหม
- PSU มือสองควรซื้อไหม
- RAM มือสองเทสต์ยังไง
- ซื้อคอมมือสองต้องเช็กอะไร

### Budget

- คอมมือสองงบ 10000
- คอมมือสองงบ 15000
- คอมมือสองงบ 20000

### Compare

- RTX 3070 vs RTX 4060 มือสอง
- RTX 3060 Ti vs RTX 4060 มือสอง
- Ryzen 5 3600 vs Ryzen 5 5600 มือสอง

## 22.3 Programmatic SEO Guard

ห้ามสร้างหน้าเพียงเปลี่ยนชื่อรุ่น

ทุกหน้าต้องมี:

- Actual data
- Sample size
- Freshness
- Unique risk
- Variant notes
- Similar products
- Deal Checker
- FAQ
- Internal links

## 22.4 Initial SEO Metrics

| Metric | 30 วัน | 90 วัน | 6 เดือน |
|---|---:|---:|---:|
| Indexed pages | 30 | 80 | 150+ |
| Products | 20–30 | 50 | 100+ |
| Organic impressions | เริ่มมี | โตต่อเนื่อง | สม่ำเสมอ |
| Organic clicks | Baseline | Daily | Growth |
| Long-tail page 1–3 | ทดลอง | บางคำ | หลายคำ |

---

# 23. Technical Architecture

## 23.1 Stack

```text
PHP 8.1+
MySQL / MariaDB
PDO
Bootstrap 5
Vanilla JS / jQuery
Apache
Cron / Queue table
AI API adapters
```

## 23.2 Project Structure

```text
used-pc-price-radar/
├── app/
│   ├── Core/
│   │   ├── Router.php
│   │   ├── Controller.php
│   │   ├── Model.php
│   │   ├── Database.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── Validator.php
│   │   └── Auth.php
│   ├── Controllers/
│   │   ├── HomeController.php
│   │   ├── PriceController.php
│   │   ├── DealCheckerController.php
│   │   ├── CompareController.php
│   │   ├── BuildController.php
│   │   ├── GuideController.php
│   │   ├── SellerPriceAdvisorController.php
│   │   └── Admin/
│   ├── Models/
│   ├── Services/
│   │   ├── PriceEngine.php
│   │   ├── ProductNormalizer.php
│   │   ├── DuplicateDetector.php
│   │   ├── ObservationValidator.php
│   │   ├── ConfidenceCalculator.php
│   │   ├── ScreenshotExtractor.php
│   │   └── Collectors/
│   ├── Views/
│   └── Helpers/
├── config/
├── public/
├── storage/
│   ├── logs/
│   ├── cache/
│   ├── evidence/
│   └── exports/
├── cron/
├── database/
│   ├── migrations/
│   └── seeds/
└── docs/
```

## 23.3 Request Flow

```text
URL
→ Router
→ Controller
→ Service / Model
→ View
```

## 23.4 Background Job Flow

```text
Cron
→ collector_jobs
→ Adapter
→ raw_price_observations
→ processing queue
→ normalized observation
→ review queue
→ price snapshot
```

## 23.5 Required Routes

### Public

```text
GET  /
GET  /price
GET  /price/{slug}
GET  /price/{slug}/history
GET  /deal-checker
POST /deal-checker
POST /deal-checker/screenshot
GET  /deal-checker/result/{uuid}
GET  /compare/{slug}
GET  /build
GET  /build/{slug}
GET  /seller-price-advisor
POST /seller-price-advisor
GET  /guide/{slug}
GET  /methodology
```

### Admin

```text
GET  /admin
GET  /admin/products
GET  /admin/product-aliases
GET  /admin/price-observations
GET  /admin/review-queue
POST /admin/observations/{id}/approve
POST /admin/observations/{id}/reject
GET  /admin/price-indices
GET  /admin/sources
GET  /admin/collector-jobs
GET  /admin/articles
GET  /admin/audit-logs
```

---

# 24. Database Design

## 24.1 Core Tables

```text
users
product_categories
brands
products
product_variants
product_aliases
data_sources
collector_jobs
raw_price_observations
price_observations
price_indices
price_histories
deal_checks
spec_builds
spec_build_items
articles
faqs
audit_logs
```

## 24.2 products

```text
id
category_id
brand_id
model_name
slug
full_name
generation
release_year
spec_summary
tdp_watt
recommended_psu_watt
image_path
is_popular
is_active
created_at
updated_at
```

## 24.3 product_variants

```text
id
product_id
brand_id
variant_name
slug
cooler_type
is_premium
notes
created_at
updated_at
```

## 24.4 product_aliases

```text
id
product_id
product_variant_id nullable
alias_text
normalized_alias
source_note
confidence
created_at
updated_at
```

ตัวอย่าง:

```text
5700x3d
ryzen5700x3d
r7 5700 x3d
RTX3070
3070 TUF OC
```

## 24.5 data_sources

```text
id
name
domain
source_type
access_method
risk_level
terms_note
robots_note
is_active
last_success_at
last_blocked_at
created_at
updated_at
```

## 24.6 collector_jobs

```text
id
source_id
job_type
product_id nullable
query_text nullable
status
started_at
completed_at
raw_count
valid_count
error_message
api_cost
created_at
updated_at
```

## 24.7 raw_price_observations

```text
id
source_id
collector_job_id
external_reference_hash
raw_title
raw_price_text
raw_condition_text
raw_warranty_text
source_url_encrypted nullable
evidence_path nullable
captured_at
expires_at
processing_status
created_at
updated_at
```

## 24.8 price_observations

```text
id
raw_observation_id nullable
product_id
product_variant_id nullable
price_type
asking_price
currency
condition_level
warranty_months nullable
has_box nullable
has_receipt nullable
has_benchmark nullable
listing_type
is_deposit
is_defective
is_duplicate
evidence_level
evidence_weight
freshness_weight
source_weight
classification_confidence
final_weight
verified_status
observed_at
created_at
updated_at
```

## 24.9 price_indices

```text
id
product_id
product_variant_id nullable
price_type
price_low
q1
median
q3
price_high
fast_sale_min
fast_sale_max
market_min
market_max
premium_min
premium_max
sample_size
valid_sample_size
fresh_sample_ratio
confidence_score
confidence_label
last_calculated_at
created_at
updated_at
```

## 24.10 price_histories

```text
id
product_id
product_variant_id nullable
q1
median
q3
sample_size
confidence_score
snapshot_date
created_at
updated_at
```

## 24.11 deal_checks

```text
id
uuid
product_id
product_variant_id nullable
user_price
condition_level
warranty_months nullable
has_box
has_receipt
has_benchmark
intended_use nullable
price_score
risk_score
value_score
fit_score
result_label
suggested_price_min
suggested_price_max
recommendation
warning_note
consent_to_anonymous_observation
created_at
updated_at
```

## 24.12 Future B2B Tables

```text
shops
shop_users
shop_inventory_imports
shop_inventory_items
shop_reports
price_alerts
api_keys
api_usage_logs
```

---

# 25. Security Requirements

Pure PHP MVC ต้องมีตั้งแต่ต้น:

- PDO prepared statements
- Output escaping
- CSRF token
- Secure session cookie
- Session regeneration
- Password hashing
- Role-based access
- Rate limiting
- Login brute-force protection
- CAPTCHA สำหรับ upload/report
- File type validation
- MIME validation
- Size limit
- Random file names
- Storage นอก public เมื่อทำได้
- Remove EXIF
- Virus scanning ตามความเหมาะสม
- Audit log
- Config / API key นอก Git
- Backup
- Restore test
- Log retention
- Cron lock
- Retry/backoff
- API budget limit
- Screenshot TTL
- Encrypt sensitive source URLs หากต้องเก็บ
- Privacy-by-design

---

# 26. Roadmap

## Phase 0 — Data Feasibility Prototype (2–4 Weeks)

### Goal

พิสูจน์ว่าสามารถได้ข้อมูลสะอาดโดยใช้เวลาคนไม่มากเกินไป

### Scope

- 20 รุ่น
- 600 raw observations
- AI extraction
- Product Alias
- Basic filter
- Median / Q1 / Q3
- Admin review sheet/dashboard

### Go / No-Go

ผ่านเมื่อ:

- Valid ≥ 400
- Normalize ≥ 90%
- Human review ≤ 30 นาที/วัน
- Price result สมเหตุสมผล ≥ 80% จากการตรวจแบบคนรู้ตลาด
- ไม่มี source block ที่ทำให้ pipeline ทั้งหมดหยุด

---

## Phase 1 — Public MVP (4–8 Weeks)

### Scope

- Home
- Search
- Product Price Detail
- Deal Checker
- Screenshot Checker
- Methodology
- 20–30 Product Pages
- Admin Data Quality
- SEO basics
- Analytics

### Revenue

- Affiliate test
- Sponsored slot ยังไม่เปิดจนมี Traffic

---

## Phase 1.5 — Retention (Month 3–4)

- Price history
- Compare
- Save / Watchlist
- Seller Price Advisor
- Share card
- Email alert
- User-assisted data collection

---

## Phase 2 — B2B Pilot (Month 4–6)

- คุยร้าน 5–10 ร้าน
- ให้ 1–3 ร้านทดลอง Dashboard ฟรี
- Inventory upload
- Market compare
- Weekly report
- Validate willingness to pay

---

## Phase 3 — Data Product (After Validation)

- Paid Dashboard
- CSV Export
- API
- Market Report
- Lead Generation

---

## Phase 4 — Optional Marketplace

เริ่มเฉพาะเมื่อ:

- Monthly traffic มีนัยสำคัญ
- มี Seller demand
- มีทีมดู Fraud/Support
- มี Legal review
- มี Trust & Safety system
- มีงบและเวลารองรับ

---

# 27. Risks and Stop Conditions

## 27.1 Data Source Risk

Risk:

- Block
- Terms change
- UI change
- Search coverage ลดลง

Response:

- Stop source
- Degrade confidence
- Switch adapter mix
- Increase user-assisted data
- Keep transparent freshness

## 27.2 Data Quality Risk

Stop / Pivot Condition:

- Valid rate < 40%
- Normalize < 80%
- Human review > 60 นาที/วัน
- Price outputs ถูกผู้รู้ตลาดปฏิเสธ > 30%
- Sample ต่ำเกินไปต่อเนื่อง

## 27.3 Business Risk

Pivot Condition:

- 90 วัน Monthly sessions < 1,000
- Deal Checker completed < 100
- ไม่มี affiliate conversion
- ไม่มีร้านสนใจ Dashboard
- Cost > value created
- User does not return

## 27.4 Solo Founder Risk

Stop or Reduce Scope when:

- Support > 1 ชั่วโมง/วัน
- Collector maintenance > 3 ชั่วโมง/สัปดาห์
- Manual review > 20% ของ observations
- ทำให้กระทบงานประจำ
- ค่าใช้จ่ายเกิน Budget ที่ตั้งไว้โดยไม่มีสัญญาณรายได้

## 27.5 Legal / Policy Risk

Stop immediately when:

- Source blocks access
- CAPTCHA / access control appears
- Formal request to stop
- Data contains personal information unexpectedly
- Legal complaint
- Platform policy conflict becomes clear

ห้ามแก้ด้วยการ:

- Rotate account
- Rotate IP เพื่อหลบ
- Bypass CAPTCHA
- Fake user sessions
- Circumvent access control

---

# 28. Stitch Handoff Prompt

สามารถใช้ข้อความนี้เป็น Prompt หลักร่วมกับเอกสารทั้งหมด

```text
Design a responsive web product called “Used PC Price Radar”.

It is a Thai used-computer market intelligence and price index platform, not a marketplace.

The platform helps:
1. Buyers understand the current asking-price range and evaluate a deal.
2. Sellers price products fairly without being aggressively undercut.
3. Used-PC shops monitor market prices and inventory risk.

Core screens:
- Home
- Product search / price index
- Product price detail with price range, median, sample size, freshness, confidence and price-history chart
- Deal Checker with manual input, screenshot upload and URL input
- Deal Checker result
- Product comparison
- Used-PC spec builder
- Seller Price Advisor
- Buying guide
- Methodology and data transparency page
- Admin data-quality dashboard
- Admin observation review
- B2B shop dashboard

Design principles:
- Data-first, trustworthy, neutral and practical
- Modern technology aesthetic, but avoid excessive gaming neon
- Excellent mobile experience for quick price checking and screenshot upload
- Rich desktop experience for comparison, charts, tables and spec building
- Price labels must be neutral: Below Reference, Market Range, Slightly Above Market, Premium, Insufficient Data
- Do not use red/green alone to communicate purchase decisions
- Clearly show sample size, update date, freshness and confidence
- Sponsored and affiliate offers must be visibly separated from independent market data
- Thai-first typography and accessible numeric presentation
- Include loading, empty, stale-data, low-confidence, processing, extraction-error and source-unavailable states

Suggested tone:
“Data platform for real PC users — friendly, transparent and technically credible.”

Please produce:
- Desktop and mobile layouts
- Core component library
- Navigation
- Page hierarchy
- Data cards
- Distribution bar
- Confidence badges
- Price-history chart
- Deal Checker form and result
- Screenshot extraction confirmation flow
- Admin review workflow
```

---

# 29. Open Questions

สิ่งที่ยังต้องตัดสินใจก่อนเขียน Production Code เต็มรูปแบบ:

1. ชื่อแบรนด์และ Domain
2. Light, Dark หรือ Dual Theme
3. Product Category แรก 20 รุ่น
4. Source mix ของ Data Prototype
5. AI provider ที่ใช้:
   - Gemini
   - OpenAI
   - Perplexity
   - Multi-provider
6. Budget API ต่อเดือน
7. Retention ของ Screenshot
8. Consent wording สำหรับ Anonymous Observation
9. วิธีเก็บ Source URL ภายใน
10. วิธี Validate ราคามือหนึ่ง
11. เกณฑ์ Confidence ที่เผยแพร่ได้
12. B2B Pilot จะเริ่มจากร้านประเภทใด
13. Affiliate network ที่จะทดลอง
14. Revenue target 90 วัน
15. ชื่อไทยที่ใช้หน้าเว็บ

---

# Final Product Statement

> Used PC Price Radar ไม่ได้พยายามเป็นตลาดซื้อขายอีกแห่งตั้งแต่วันแรก  
> แต่สร้าง “ชั้นข้อมูลกลาง” ที่ช่วยให้ตลาดคอมมือสองตัดสินใจได้ดีขึ้น

MVP ที่ต้องพิสูจน์คือ:

```text
Market Data Collector
+ Product Normalization
+ Price Index
+ Screenshot Deal Checker
+ Transparent Confidence
+ SEO Distribution
```

หาก Data Engine ทำงานได้และผู้ใช้เข้ามาเช็กจริง ธุรกิจสามารถต่อยอดได้โดยไม่ต้องแบกความเสี่ยงของ Marketplace ทันที ผ่าน:

```text
Affiliate
+ Seller Tools
+ Shop Dashboard
+ Market Report
+ Data Export / API
+ Lead Generation
```

ความสำเร็จของโปรเจกต์นี้จึงไม่ใช่การมีฟีเจอร์เยอะที่สุด แต่คือ:

1. ข้อมูลสะอาดพอ
2. อัปเดตได้โดยไม่ใช้แรงคนมาก
3. สื่อสารราคาอย่างเป็นกลาง
4. ผู้ใช้เชื่อและกลับมาใช้
5. มี Revenue Model ที่ไม่ผูกกับเวลาของผู้พัฒนา
