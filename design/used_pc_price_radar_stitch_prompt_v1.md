# Used PC Price Radar — Stitch Master Prompt v1.0

> ใช้ Prompt นี้ร่วมกับไฟล์ `used_pc_price_radar_master_v0.3.md` เพื่อให้ Stitch ออกแบบ UX/UI ของระบบ

---

## COPY-PASTE PROMPT FOR STITCH

Design a complete responsive web application called **“Used PC Price Radar”** for the Thai market.

Use the attached product brief as the source of truth. Do not invent marketplace, payment, escrow, chat, bidding, seller-rating, or transaction features unless explicitly requested later.

The product is a **Used PC Market Intelligence and Price Index Platform**, not a marketplace.

Its purpose is to help:

1. Buyers understand the current asking-price range of used computer parts and evaluate whether a deal is reasonable.
2. Sellers price their products fairly without being aggressively undercut by a single “cheap price” number.
3. Used-PC shops monitor market prices, inventory aging, buy-price risk, and market trends.

The product must feel:

- Trustworthy
- Neutral
- Data-first
- Technically credible
- Friendly to normal PC users
- Useful on mobile for quick price checking
- Powerful on desktop for comparison, charts, tables, and spec planning

The UI language is primarily **Thai**, with English product names, model names, technical terms, and short labels where appropriate.

---

# 1. Core Product Principles

## 1.1 Neutral price communication

Never label a seller or listing as “bad”, “scam”, “too expensive”, or “do not buy” based only on price.

Use neutral labels such as:

- ต่ำกว่าช่วงอ้างอิง
- อยู่ในช่วงตลาด
- สูงกว่าช่วงตลาดเล็กน้อย
- ราคาพรีเมียม
- ข้อมูลยังไม่เพียงพอ

Explain that a premium price may be justified by:

- Remaining warranty
- Box and accessories
- Receipt
- Premium product variant
- Good condition
- Test evidence
- Shop warranty
- After-sales service

## 1.2 Transparency

Every price result must visibly show:

- Price range
- Median
- Sample size
- Last updated date
- Freshness status
- Confidence level
- Data limitations
- Whether the result is based on asking prices or verified sold prices

Do not present asking-price data as completed-sale data.

## 1.3 Independent market data

Sponsored offers, affiliate products, and advertisements must be clearly separated from independent market data.

Use clear labels such as:

- สินค้าแนะนำ
- ลิงก์พาร์ตเนอร์
- โฆษณา
- Sponsored

Do not visually blend sponsored offers into the market price distribution.

## 1.4 Mobile-first utility

The mobile experience should optimize for:

- Searching a product quickly
- Entering a found price
- Uploading a screenshot
- Receiving a result in under one minute
- Saving or sharing the result

The desktop experience should optimize for:

- Product comparison
- Price-history charts
- Data tables
- Used-PC spec building
- Admin review
- Shop dashboards

---

# 2. Target Users

Design for four primary user groups.

## A. Used-PC buyer

Typical needs:

- “ราคานี้แพงไหม?”
- “ควรต่อเหลือเท่าไร?”
- “มีอะไรต้องเช็กก่อนซื้อ?”
- “เพิ่มเงินอีกนิดซื้อของใหม่คุ้มกว่าไหม?”

## B. Casual seller

Typical needs:

- “ควรตั้งราคาเท่าไร?”
- “ถ้าอยากขายเร็วควรตั้งเท่าไร?”
- “กล่อง ประกัน และสภาพเพิ่มมูลค่าได้แค่ไหน?”

## C. Used-PC shop

Typical needs:

- Market movement
- Buy-price guidance
- Inventory aging risk
- Margin visibility
- CSV import and export
- Weekly market report

## D. Administrator / data reviewer

Typical needs:

- Review uncertain observations
- Resolve product aliases
- Detect duplicate listings
- Exclude deposits, defective products, whole-PC listings, and wrong models
- Monitor source freshness and collector health

---

# 3. Required Information Architecture

Create a clear navigation structure containing:

## Public navigation

- หน้าแรก
- เช็กราคา
- เช็กดีล
- จัดสเปกมือสอง
- เปรียบเทียบ
- คู่มือก่อนซื้อ
- วิธีคำนวณราคา

## User utility navigation

- รายการที่บันทึก
- การแจ้งเตือนราคา
- ประวัติการเช็กดีล

## Seller navigation

- ช่วยตั้งราคาขาย

## Business navigation

- สำหรับร้านค้า
- Market Dashboard

## Admin navigation

- Data Quality Dashboard
- Observation Review
- Product Master
- Product Aliases
- Source Health
- Import Jobs

Keep the public navigation simple. Do not expose admin or B2B complexity to normal users.

---

# 4. Required Screens

Design desktop and mobile versions for the following screens.

## 4.1 Home page

The home page should immediately answer:

- What the platform does
- What the user can check
- Why the result is trustworthy

### Hero section

Headline idea:

**เช็กราคาคอมมือสอง ก่อนจ่ายเงินจริง**

Supporting copy:

**ดูช่วงราคาตลาด จำนวนตัวอย่าง ความสดของข้อมูล และความมั่นใจก่อนตัดสินใจซื้อหรือขาย**

Primary search input placeholder:

**ค้นหา CPU, GPU, RAM, SSD หรือชื่อรุ่น เช่น RTX 3070**

Primary CTA:

**เช็กราคา**

Secondary CTA:

**อัปโหลดรูปประกาศ**

### Home content blocks

Include:

- Popular product categories
- Trending products
- Recently updated prices
- “How it works” in three steps
- Deal Checker teaser
- Used vs New comparison teaser
- Market transparency explanation
- Buying guides
- Clearly separated affiliate or sponsored section

### Popular categories

Use practical categories:

- CPU
- GPU
- RAM
- SSD
- Mainboard
- Power Supply
- Complete PC

---

## 4.2 Search and Price Index page

Create a search experience with:

- Search suggestions
- Category filter
- Brand filter
- Product generation filter
- Condition filter
- Data freshness filter
- Sort by popularity, latest update, price movement, and confidence

Each product result card should show:

- Product name
- Product image or neutral hardware placeholder
- Asking-price range
- Median price
- Sample size
- Last updated date
- Confidence badge
- Price trend direction
- Button: ดูรายละเอียดราคา

Avoid showing only one large “market price” number.

---

## 4.3 Product Price Detail page

This is the most important public screen.

Use **AMD Ryzen 7 5700X3D** as the sample product.

### Sample product data

- Product: AMD Ryzen 7 5700X3D
- Category: CPU
- Socket: AM4
- Condition: Used
- Observed asking-price range: 7,500–8,600 บาท
- Median: 8,250 บาท
- Sample size: 6 observations
- Confidence: Medium-Low
- Data freshness: Updated 2 days ago
- Data type: Asking prices, not verified sold prices

### Required sections

#### A. Product header

Show:

- Product name
- Category and generation
- Save / watch button
- Compare button
- Share button

#### B. Main price summary card

Show:

- Observed market range
- Median
- Minimum and maximum observed price
- Sample size
- Update date
- Confidence

Use Thai Baht formatting and clear numeric hierarchy.

#### C. Price distribution visualization

Create a horizontal distribution bar with zones:

- Below reference
- Market range
- Slightly above market
- Premium

Mark the median and quartile range.

Do not use red and green alone. Combine color, labels, icons, and patterns.

#### D. Price history chart

Show:

- 30 days
- 90 days
- 6 months
- 1 year

Include:

- Median line
- Q1–Q3 band
- Sample-volume indicator

When data is insufficient, show an honest empty state instead of a fake chart.

#### E. Deal input widget

Prompt:

**เจอราคามาเท่าไร?**

Input:

- Price
- Warranty remaining
- Box / receipt
- Product variant
- Shop or individual seller

CTA:

**ประเมินดีลนี้**

#### F. Used vs New comparison

Show:

- Used market range
- Current new-product alternatives
- Price difference
- Warranty difference
- Neutral explanation of trade-offs

Do not automatically claim that used or new is better.

#### G. Buyer checklist

For CPU, include examples:

- Check bent or damaged pins where relevant
- Ask for CPU-Z or benchmark evidence
- Confirm warranty and serial number
- Confirm whether the product is tray or boxed
- Confirm included accessories

#### H. Price methodology card

Briefly explain:

- Data is aggregated from multiple observations
- Median and quartiles are used
- Outliers and invalid listings are filtered
- Asking prices are not sold prices

Link to full methodology page.

#### I. Data limitation notice

Example:

**ข้อมูลรุ่นนี้มีจำนวนตัวอย่างค่อนข้างน้อย จึงควรใช้ร่วมกับสภาพสินค้า ประกัน และหลักฐานการทดสอบ**

---

## 4.4 Deal Checker — input screen

Offer three input methods as equal tabs or cards:

### Method 1: Manual input

Fields:

- Product search
- Found price
- Product condition
- Warranty remaining
- Box
- Receipt
- Seller type
- Optional notes

### Method 2: Screenshot upload

Allow:

- Drag and drop
- Mobile camera upload
- Paste image from clipboard

Explain privacy clearly:

**ระบบจะอ่านเฉพาะข้อมูลที่จำเป็น เช่น รุ่น ราคา สภาพ และประกัน ไม่ใช้ชื่อหรือข้อมูลส่วนตัวของผู้ขายในการคำนวณราคา**

### Method 3: URL input

Explain that some pages may not be readable due to login or platform restrictions.

CTA:

**วิเคราะห์ดีล**

---

## 4.5 Screenshot extraction confirmation

After image upload, show an extraction review screen before calculating.

Display:

- Original screenshot preview
- Detected product
- Detected price
- Product variant
- Condition
- Warranty text
- Listing type
- Confidence per field

Allow the user to correct each field.

Highlight uncertain fields without making the screen feel like an error.

CTA:

**ยืนยันข้อมูลและประเมินราคา**

Secondary action:

**แก้ไขข้อมูล**

---

## 4.6 Deal Checker result

The result should answer four questions immediately:

1. Where is this price relative to the market?
2. What details may justify the price?
3. What should the buyer check next?
4. How reliable is this result?

### Example result for 5700X3D at 8,900 บาท

Headline:

**ราคานี้อยู่เหนือค่ากลางประมาณ 8% และอยู่ในระดับพรีเมียม**

Supporting explanation:

**ราคาอาจสมเหตุสมผลหากมีประกันเหลือ กล่องครบ ใบเสร็จ หรือหลักฐานการทดสอบที่ชัดเจน**

Show:

- User price marker on distribution bar
- Market range
- Median
- Difference in Baht and percentage
- Confidence
- Sample size
- Negotiation guidance as a range, not a command
- Checklist of evidence to request
- Save result
- Share result
- Compare with new alternatives

Avoid wording such as “ซื้อเลย”, “ห้ามซื้อ”, or “โดนโกงแน่นอน”.

---

## 4.7 Product comparison

Allow comparing 2–4 products.

Example comparison:

- Ryzen 7 5700X3D used
- Ryzen 7 5700X used
- Ryzen 5 7600 used
- Current new alternative

Compare:

- Used-price range
- New-price reference
- Platform / socket
- Core count
- Gaming suitability
- Upgrade cost
- Power usage
- Warranty
- Price confidence
- Value notes

Use both table and mobile card layouts.

---

## 4.8 Used-PC Spec Builder

Create a guided spec-building experience.

Inputs:

- Total budget
- Main use: Gaming, Work, Streaming, Office, AI, General use
- Resolution: 1080p, 1440p, 4K
- New / used mix preference
- Existing parts

Output:

- Recommended component list
- Used-price range per component
- Estimated total range
- Compatibility warnings
- Power supply recommendation
- Upgrade path
- Confidence per component
- Alternative parts

Do not show a false single exact total. Show low, expected, and upper estimates.

---

## 4.9 Seller Price Advisor

Design a neutral seller tool.

Inputs:

- Product
- Condition
- Warranty
- Box
- Receipt
- Repair history
- Test evidence
- Desired selling speed

Output three recommended zones:

- ขายไว
- ราคาตลาด
- ราคาพรีเมียม

Explain what evidence supports each zone.

Do not force a seller to lower the price.

Example wording:

**ราคาที่ตั้งสูงกว่าช่วงอ้างอิง 9% แต่ประกันที่เหลือและหลักฐานการทดสอบสามารถช่วยรองรับราคาพรีเมียมได้**

---

## 4.10 Buying Guide article

Create a practical article layout for SEO and buyer education.

Example article:

**ซื้อการ์ดจอมือสอง ต้องเช็กอะไรบ้าง?**

Include:

- Table of contents
- Product-specific red flags
- Test checklist
- Warranty checklist
- Common misleading listing patterns
- Embedded Deal Checker
- Related product prices
- Related guides

Keep the article easy to scan on mobile.

---

## 4.11 Methodology and transparency page

Explain clearly, without legal or statistical jargon overload:

- What data is collected
- What is not collected
- Asking price vs sold price
- Median, Q1, Q3, and outliers
- Product normalization
- Duplicate removal
- Confidence calculation
- Data freshness
- Source limitations
- User-submitted data
- Sponsored-content separation

Use diagrams, examples, and a mock price distribution.

---

## 4.12 B2B Shop Dashboard

Create a professional desktop-first dashboard for used-PC shops.

Required modules:

- Market overview
- Products with rising or falling prices
- Inventory aging
- Estimated buy-price range
- Estimated selling range
- Margin risk
- Products with low confidence
- CSV import
- Weekly market summary
- Export report

Suggested widgets:

- Inventory value
- Potential margin
- Stock older than 30 / 60 / 90 days
- Price drop alerts
- Market demand indicator

Use tables with strong filtering and clear status labels.

---

## 4.13 Admin Data Quality Dashboard

Create a desktop-focused admin dashboard.

Summary cards:

- New observations today
- Valid rate
- Duplicate rate
- Low-confidence records
- Unmatched products
- Stale sources
- Failed imports
- Average human-review time

Charts:

- Observation volume by source
- Valid vs rejected observations
- Product-normalization accuracy
- Source freshness
- Confidence distribution

Queues:

- Unknown product
- Whole-PC suspicion
- Deposit-price suspicion
- Defective-product suspicion
- Duplicate suspicion
- Extreme outlier
- Wrong currency
- Missing evidence

---

## 4.14 Admin Observation Review

Use a split-screen review layout.

Left side:

- Screenshot or source evidence
- Raw title
- Raw price
- Source and timestamp

Right side:

- Suggested normalized product
- Product variant
- Condition
- Warranty
- Listing type
- Flags
- Confidence

Actions:

- Approve
- Reject
- Mark duplicate
- Assign product
- Create alias
- Exclude from index
- Send to later review

Include keyboard-friendly review actions for fast administration.

---

# 5. Data Visualization Rules

## 5.1 Price range

Always emphasize a range before a single median number.

Recommended hierarchy:

1. Observed market range
2. Median
3. Quartile band
4. Minimum and maximum
5. Sample count

## 5.2 Confidence

Create confidence badges:

- สูง
- ปานกลาง
- ปานกลางค่อนข้างต่ำ
- ต่ำ
- ข้อมูลไม่เพียงพอ

Confidence must not rely on color only.

Each badge may have a tooltip explaining:

- Sample size
- Source diversity
- Data age
- Evidence quality

## 5.3 Freshness

Create freshness labels:

- อัปเดตวันนี้
- อัปเดตภายใน 7 วัน
- ข้อมูลเริ่มเก่า
- ข้อมูลล้าสมัย
- แหล่งข้อมูลหลักไม่พร้อมใช้งาน

## 5.4 Missing data

Never fabricate a chart or trend.

Use states such as:

**ยังมีข้อมูลไม่เพียงพอสำหรับแสดงแนวโน้มราคา**

---

# 6. Design System Direction

## 6.1 Visual style

Create a clean, modern technology aesthetic.

Avoid:

- Excessive RGB gaming neon
- Cyberpunk visuals
- Overly dark interfaces
- Cryptocurrency-dashboard styling
- Aggressive red “bad deal” warnings
- Dense enterprise UI on public pages

The product should feel closer to a trusted financial comparison tool mixed with a modern PC hardware platform.

## 6.2 Color direction

Use a neutral base with one strong technology accent.

Suggested direction:

- Light neutral background
- Dark navy or charcoal text
- Blue, teal, or indigo as the primary accent
- Amber for caution
- Muted violet or blue for premium
- Red only for errors, invalid evidence, or critical risk—not for “expensive” prices

Do not use green as an automatic “buy” signal.

## 6.3 Typography

Use Thai-first typography with strong readability.

Suggested characteristics:

- Clear Thai numerals and Latin model names
- Strong hierarchy for prices
- Tabular numbers for tables
- Comfortable mobile line height

Possible font direction:

- Prompt
- Noto Sans Thai
- IBM Plex Sans Thai

## 6.4 Components

Create reusable components for:

- Global search
- Product card
- Price summary card
- Price distribution bar
- Price-history chart
- Confidence badge
- Freshness badge
- Sample-size tooltip
- Product specification table
- Deal result card
- Evidence checklist
- Screenshot upload
- Extraction field confirmation
- Comparison table
- Affiliate card
- Sponsored card
- Empty state
- Stale-data state
- Loading skeleton
- Error state
- Admin review queue

---

# 7. Responsive Behaviour

## Mobile

- Sticky bottom primary action on Deal Checker and Product Detail
- Large upload area
- One-hand friendly controls
- Collapsible charts and methodology details
- Price summary visible before scrolling
- Horizontal swipe for comparison cards
- Avoid wide tables; convert to stacked cards

## Desktop

- 12-column grid
- Sticky right-side Deal Checker widget on Product Detail
- Full comparison table
- Side navigation for admin and B2B dashboards
- Multi-panel review workflows
- Rich hover and tooltip states

---

# 8. Required UX States

Design all important states, not only ideal states.

Include:

- Loading search
- No search result
- Product not found
- Low sample size
- Low confidence
- Stale data
- Source temporarily unavailable
- Screenshot uploading
- Screenshot processing
- Screenshot extraction success
- Screenshot extraction partially uncertain
- Screenshot unreadable
- Wrong product detected
- Duplicate submission
- Invalid deposit price
- Whole-PC listing detected
- Defective product detected
- URL cannot be accessed
- Logged-out save attempt
- Empty watchlist
- Empty admin review queue
- Import failed
- Import partially completed

---

# 9. Content Tone

Use Thai copy that is:

- Direct
- Friendly
- Honest
- Technically credible
- Non-judgmental

Good examples:

- **ราคานี้อยู่ในช่วงตลาดที่ตรวจพบ**
- **ข้อมูลรุ่นนี้ยังมีตัวอย่างไม่มาก ควรตรวจสภาพและประกันประกอบการตัดสินใจ**
- **ราคาสูงกว่าค่ากลางเล็กน้อย แต่ประกันและอุปกรณ์ที่ครบอาจช่วยรองรับส่วนต่างได้**
- **เราอ้างอิงจากราคาประกาศ ไม่ใช่ราคาปิดดีลจริง**

Avoid:

- **ซื้อเลย**
- **ห้ามซื้อ**
- **โดนโกงแน่นอน**
- **แพงเกินจริง**
- **ราคาถูกที่สุดในตลาด**

---

# 10. Technical Design Constraints

The final interface will be implemented using:

- Pure PHP 8.1+
- MVC architecture
- Bootstrap 5
- Vanilla JavaScript or minimal jQuery
- MySQL / MariaDB

Therefore:

- Prefer implementable layouts and common web components
- Avoid interactions that require heavy 3D, WebGL, or highly custom animation
- Use realistic tables, filters, modals, drawers, tabs, accordions, and charts
- Keep components reusable across PHP templates
- Define desktop, tablet, and mobile breakpoints

---

# 11. Deliverables

Produce:

1. Complete sitemap
2. Desktop and mobile Home page
3. Desktop and mobile Product Price Detail page
4. Desktop and mobile Deal Checker input
5. Screenshot extraction confirmation flow
6. Deal Checker result
7. Product comparison
8. Used-PC Spec Builder
9. Seller Price Advisor
10. Buying Guide article
11. Methodology page
12. B2B Shop Dashboard
13. Admin Data Quality Dashboard
14. Admin Observation Review
15. Reusable component library
16. Loading, empty, stale, low-confidence, processing, and error states
17. Navigation and responsive behavior
18. Thai sample copy inside the designs

Start by generating the **Home page, Product Price Detail, Deal Checker, and Deal Result** as the first design set. These four screens define the core product experience.

After those screens are consistent, expand to the remaining screens using the same design system.

---

# 12. Final Design Goal

The final product should communicate this promise within five seconds:

> **เช็กราคาคอมมือสองจากข้อมูลหลายตัวอย่าง พร้อมดูความสดและความมั่นใจก่อนซื้อหรือขาย**

The experience must not feel like another classified-ad website.

It should feel like:

> **A trusted data layer for Thailand’s used-PC market.**
