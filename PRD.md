# PRODUCT REQUIREMENTS DOCUMENT (PRD)
# Green Valley Building Management System (BMS)

| | |
|---|---|
| **প্রজেক্ট** | Green Valley BMS |
| **সংস্থা** | Green Valley ফ্ল্যাট মালিক এসোসিয়েশন, ৩৩৫/ঘ/১, টি.ভি রোড, পূর্ব রামপুরা, ঢাকা-১২১৯ |
| **সংস্করণ** | 1.0 (Final) |
| **স্ট্যাক** | Laravel 12 + MySQL 8 (Backend) / React 18 + TypeScript + Tailwind (Frontend) |
| **ভবন** | G+9, ৪৫টি ফ্ল্যাট |

---

## ১. প্রজেক্ট সারসংক্ষেপ

### ১.১ সমস্যা
বর্তমানে Green Valley-এর সব হিসাব Excel ও Word ফাইলে ম্যানুয়ালি রক্ষিত হয়। এতে প্রমাণিত সমস্যা আছে (প্রকৃত ফাইল বিশ্লেষণে পাওয়া গেছে):
- বকেয়া মাস থেকে মাসে ক্যারি-ফরোয়ার্ড করতে গিয়ে টাকা হারিয়ে যাওয়া (৳৩,৫০০ ভুল প্রমাণিত)
- একই মানি রিসিট নম্বর দুই ফ্ল্যাটে ব্যবহার
- ফ্ল্যাট মালিক/ভাড়াটিয়ার তথ্য তিন ফাইলে তিন রকম
- গ্যাস সিস্টেম লস অজানা — ক্রয় ও আদায়ের মধ্যে ফারাক পরিমাপ হয় না
- ৪ লক্ষ টাকার বেশি নগদ হাতে জমা থাকা, দৈনিক নিয়ন্ত্রণ ছাড়া

### ১.২ লক্ষ্য
একটি single-tenant Laravel + React ওয়েব অ্যাপ্লিকেশন যা:
1. সার্ভিস চার্জ ও গ্যাস বিল স্বয়ংক্রিয়ভাবে হিসাব করবে
2. ব্যাংক ও ক্যাশ — দুই লেজার আলাদাভাবে ট্র্যাক করবে, ডাবল-এন্ট্রি অ্যাকাউন্টিং-এর ভিত্তিতে
3. বছরান্তে ট্রায়াল ব্যালেন্স ও ব্যালেন্স শিট অটো তৈরি করবে
4. মালিক ও ভাড়াটিয়ার ডকুমেন্ট নিরাপদে সংরক্ষণ করবে
5. SMS/নোটিশের মাধ্যমে যোগাযোগ স্বয়ংক্রিয় করবে
6. মালিকদের জন্য একটি মোবাইল-বান্ধব পোর্টাল (PWA) দেবে

### ১.৩ সাফল্যের মানদণ্ড (Success Metrics)
- তিন মাসের ঐতিহাসিক ডেটা সিস্টেমে বসিয়ে কাগজের হিসাবের সাথে ১০০% মিল
- মাসিক বিল জেনারেশনে ম্যানুয়াল ক্যালকুলেশন শূন্যে নামা
- গ্যাস সিস্টেম লস প্রতি মাসে দৃশ্যমান হওয়া
- বকেয়া আদায়ের হার (Collection Efficiency) ট্র্যাকযোগ্য হওয়া

---

## ২. ইউজার রোল ও পারমিশন

| রোল | সংখ্যা | মূল ক্ষমতা |
|---|---|---|
| **Super Admin** | ১ | সব কিছু, রেট পরিবর্তন, ইউজার তৈরি, বছর ক্লোজিং, বিল/জার্নাল আনলক |
| **Admin (কমিটি)** | ১–২ | হিসাব দেখা, ভাউচার/বিল Approve, নোটিশ পাঠানো, রিপোর্ট |
| **Manager/কেয়ারটেকার** | ১ | মিটার রিডিং এন্ট্রি, কালেকশন এন্ট্রি (Pending Approval), খরচ এন্ট্রি, অভিযোগ আপডেট |
| **Flat Owner** | ৪৫ | নিজের বিল, বকেয়া, পেমেন্ট হিস্ট্রি, নোটিশ, অভিযোগ, নিজের ডকুমেন্ট, ভাড়াটিয়া তথ্য |
| **Tenant** | পরিবর্তনশীল | শুধু নিজের গ্যাস বিল ও নোটিশ (রিড-ওনলি) |

**কোর নিয়ন্ত্রণ নীতি:**
- Maker–Checker: Manager এন্ট্রি করবে → Admin Approve করার আগে লেজারে প্রতিফলিত হবে না
- কোনো রেকর্ড হার্ড-ডিলিট নয় — soft delete/void + কারণ আবশ্যক
- প্রতিটি আর্থিক এন্ট্রিতে `created_by`, `approved_by`, timestamp, IP — audit log
- মাস/বছর ক্লোজ হলে লক — Super Admin ছাড়া কেউ সংশোধন করতে পারবে না

---

## ৩. ডেটা মডেল (নিশ্চিতকৃত ব্যবসায়িক নিয়মসহ)

### ৩.১ কোর মাস্টার
```
buildings, flats (occupancy_status, service_charge_rate, empty_flat_rate=2500, gas_meter_no)
persons (name_bn, name_en, mobile, country_code, nid, preferred_channel)
flat_parties (flat_id, person_id, role[OWNER/TENANT], from_date, to_date)
  — একই ব্যক্তির একাধিক ফ্ল্যাট থাকতে পারে (many-to-many)
tenants (flat_id, name, mobile, nid_no, occupation, start_date, end_date,
         monthly_rent, security_deposit, status)
tenant_documents (tenant_id, document_type[NID/PHOTO/POLICE_VERIFICATION], file_path)
owner_documents (person_id, flat_id, document_type[NID/NAMJARI/LAND_TAX_RECEIPT],
                 document_year, file_path, expiry_date)
staff (name, designation, mobile, join_date, monthly_salary)
```

### ৩.২ বিলিং ইঞ্জিন
```
collection_heads (code, name_bn, payer_type[OWNER/OCCUPANT], charge_type[RECURRING/SPECIAL_LEVY/MISC])
billing_periods (year, month, status[DRAFT/GENERATED/LOCKED])
bills (flat_id, service_charge, gas_amount, previous_due, total_payable, due_date=10th, status)
bill_items (bill_id, head, amount)

gas_purchases (date, supplier, quantity_kg, unit_price, total_cost)
gas_meter_readings (flat_id, previous_reading, current_reading, units_used[m³])
gas_rate_settings (rate=330/m³, method=FIXED, m3_per_kg=0.54, system_loss_percent)
gas_consumers (flat_id nullable, name, is_internal) — অফিস/প্রজেক্ট অফিস অভ্যন্তরীণ কনজিউমার

construction_dues (flat_id, category, source_type[INITIAL_CONTRACT/LEVY_RESOLUTION],
                   total_amount, paid_amount, balance)
due_installments (construction_due_id, due_date, amount, status)
```

### ৩.৩ ট্রেজারি ও ডাবল-এন্ট্রি অ্যাকাউন্টিং ⭐
```
bank_accounts (bank_name, account_no, account_title, mandate_rule, opening_balance)
bank_transactions (bank_account_id, txn_date, particulars, debit, credit, balance_after, fund_type)
cash_ledger (txn_date, particulars, in_amount, out_amount, balance_after, fund_type)

chart_of_accounts (code, name_bn, name_en, account_type[ASSET/LIABILITY/INCOME/EXPENSE/FUND], parent_id)
journal_entries (entry_date, narration, fiscal_year, status)
journal_lines (journal_entry_id, account_id, debit, credit)
  — প্রতিটি আদায়/খরচ/ফান্ড ট্রান্সফার অটো journal entry তৈরি করবে

payments (flat_id, bill_id, amount, method[CASH/BANK/BKASH/NAGAD], receipt_no auto-serial, approved_by)
expenses (expense_head_id, payee, amount, treasury_medium[BANK/CASH], voucher_image, approved_by)
salary_payments (staff_id, year, month, net_amount, paid_date)

fund_transfers (from_fund, to_fund, amount, reason, resolution_id nullable)
  — মাসিক Monthly Closing: উদ্বৃত্ত → Sinking Fund
```
**ফান্ড টাইপ:** Operating (সার্ভিস চার্জ) / Gas / Capital (নির্মাণ+লেভি) / Sinking (সংরক্ষিত জরুরি তহবিল)

### ৩.৪ গভর্নেন্স
```
assets (name, category[LIFT/SOLAR/SUBSTATION/PUMP], purchase_cost, amc_expiry, next_service_date)
asset_service_logs (asset_id, service_date, description, cost)
meetings (title, meeting_date, agenda, minutes)
resolutions (meeting_id, description, decision, status)
  — Special Levy (উন্নয়ন তহবিল) সবসময় একটি resolution_id-এর সাথে যুক্ত
complaints (flat_id, category, description, voice_note, images, status)
```

### ৩.৫ যোগাযোগ
```
sms_templates (event_key, body_bn)
sms_logs (mobile, message, event_key, gateway, status[QUEUED_PENDING_GATEWAY/SENT/FAILED], cost)
notices (title, body, attachment, audience, published_at)
```

---

## ৪. মূল ব্যবসায়িক নিয়ম (Confirmed Business Rules)

| # | নিয়ম |
|---|---|
| ১ | সার্ভিস চার্জ ৳৩,৫০০/মাস; খালি ফ্ল্যাট ৳২,৫০০; শেষ তারিখ প্রতি মাসের ১০ তারিখ |
| ২ | গ্যাস রেট ৳৩৩০/ঘনমিটার (Fixed Rate); System Loss % = (ক্রয় সমতুল্য m³ − মিটার রিডিং যোগফল) / ক্রয় সমতুল্য m³ |
| ৩ | সার্ভিস চার্জ থেকে খরচ: ম্যানেজার বেতন, ২ সিকিউরিটি, ১ ক্লিনার, সিটি কর্পোরেশন খরচ, ইউটিলিটি — বাকি পুরোটা **Sinking Fund**-এ ট্রান্সফার |
| ৪ | Sinking Fund শুধু জরুরি যন্ত্রাংশ (লিফট/মটর/সাবস্টেশন) মেরামতে ব্যবহারযোগ্য; ২৫,০০০+ হলে ২ জনের অনুমোদন, ১,০০,০০০+ হলে মিটিং রেজল্যুশন আবশ্যক |
| ৫ | "উন্নয়ন তহবিল" = Special Levy, প্রতিটি মিটিং রেজল্যুশনের সাথে যুক্ত, Capital Fund-এ জমা, কিস্তিতে আদায়যোগ্য |
| ৬ | এক ফ্ল্যাটে দুই ধরনের দেনাদার হতে পারে — গ্যাস বিল বহনকারী (Owner বা Tenant) `payer_type` দিয়ে নির্ধারিত |
| ৭ | রিসিট নম্বর সিস্টেম-জেনারেটেড, unique, সিরিয়াল — কখনো ম্যানুয়াল টাইপ নয় |
| ৮ | প্রতিটি আর্থিক এন্ট্রি মাধ্যম নির্বাচন করবে (Bank/Cash) — দুটি লেজার সবসময় আলাদা থাকবে |
| ৯ | ফিসক্যাল ইয়ার কনফিগারেবল (ডিফল্ট জানুয়ারি–ডিসেম্বর) |
| ১০ | জমির খাজনা রসিদ প্রতি বছর নতুন এন্ট্রি আবশ্যক — কমপ্লায়েন্স রিপোর্টে ট্র্যাক হবে |

---

## ৫. মডিউল তালিকা (স্কোপ)

1. ফ্ল্যাট, মালিক, ভাড়াটিয়া মাস্টার + ডকুমেন্ট ভল্ট
2. সার্ভিস চার্জ বিলিং ইঞ্জিন
3. গ্যাস বিলিং ইঞ্জিন (মিটার রিডিং + সিস্টেম লস)
4. আদায় ও রিসিট (Bank/Cash dual ledger)
5. খরচ ব্যবস্থাপনা ও পে-রোল
6. ডাবল-এন্ট্রি অ্যাকাউন্টিং (Chart of Accounts, Journal, Trial Balance, Balance Sheet)
7. নির্মাণ বকেয়া ও Special Levy
8. সম্পদ ও মেইনটেন্যান্স রেজিস্টার (Sinking Fund সহ)
9. মিটিং, রেজল্যুশন, নোটিশ
10. SMS/নোটিফিকেশন ইঞ্জিন (Driver Pattern — Gateway ছাড়া কাজ শুরু)
11. অভিযোগ ব্যবস্থাপনা
12. মালিক পোর্টাল (PWA)
13. এডমিন ড্যাশবোর্ড ও রিপোর্টিং

---

## ৬. UI/UX ডিজাইন স্ট্যান্ডার্ড

- **ভিজ্যুয়াল মান:** Modern, creative, clean — flat design নয়, subtle depth (shadow, soft gradient), whitespace-heavy, কোনো টেমপ্লেট-সুলভ ডিফল্ট বুটস্ট্র্যাপ লুক নয়
- **Micro-interaction:** সব ইন্টারেক্টিভ এলিমেন্টে (বাটন, কার্ড, রো) স্পষ্ট **hover state** — রঙ পরিবর্তন, সূক্ষ্ম lift/shadow, transition ১৫০-২০০ms
- **টাইপোগ্রাফি:** বাংলা টেক্সট — Noto Sans Bengali/Hind Siliguri; সংখ্যা/ইংরেজি — Inter; line-height ১.৬-১.৭
- **রঙ:** নেভি/টিল প্রাইমারি; সবুজ=পরিশোধিত, অ্যাম্বার=আংশিক, লাল=বকেয়া
- **রেসপন্সিভ:** এডমিন প্যানেল ডেস্কটপ-প্রথম, মালিক পোর্টাল মোবাইল-প্রথম (PWA)
- **অ্যাক্সেসিবিলিটি:** টাচ টার্গেট ন্যূনতম ৪৮×৪৮px, কন্ট্রাস্ট AA মানসম্মত

---

## ৭. আউট অফ স্কোপ (Phase 1-এ নয়)
- bKash/Nagad পেমেন্ট গেটওয়ে API ইন্টিগ্রেশন
- ভিজিটর ম্যানেজমেন্ট/QR গেট পাস
- অনলাইন ভোটিং
- মাল্টি-বিল্ডিং SaaS
- নেটিভ মোবাইল অ্যাপ (শুধু PWA)

---

## ৮. রিলিজ পরিকল্পনা

Phase 1 (MVP, ৬ সপ্তাহ) → Phase 2 (গভর্নেন্স+পোর্টাল, ৪ সপ্তাহ) → Phase 3 (অ্যাকাউন্টিং+রিপোর্টিং, ৫ সপ্তাহ — Chart of Accounts যুক্ত হওয়ায় বর্ধিত) → Phase 4 (ভবিষ্যৎ, চাহিদাভিত্তিক)

সমান্তরাল রিলিজ কৌশল: প্রথম ২ মাস কাগজ ও সিস্টেম পাশাপাশি চলবে, টোটাল মিললে কাগজ বন্ধ।

---

*এই PRD "Green Valley BMS — Master Build Plan" ডকুমেন্টের সম্প্রসারিত ও চূড়ান্ত সংস্করণ। Claude Code Build Prompt (পৃথক ফাইল) এই PRD-কে ভিত্তি ধরেই লেখা হয়েছে।*
