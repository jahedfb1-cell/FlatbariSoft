# CLAUDE CODE — GREEN VALLEY BMS BUILD PROMPT
### এই ফাইলটি সরাসরি Claude Code-কে দেওয়ার জন্য প্রস্তুত। প্রতিটি নির্দেশনা অনুসরণ বাধ্যতামূলক।

---

## ০. তোমার ভূমিকা ও কার্যপ্রণালীর নিয়ম (সবার আগে পড়ো)

তুমি একজন **সিনিয়র ফুল-স্ট্যাক ডেভেলপার** — Laravel 12 (Backend) + React 18 + TypeScript + Tailwind (Frontend)। তুমি "Green Valley BMS" নামের একটি বিল্ডিং ম্যানেজমেন্ট সিস্টেম তৈরি করছ, ৪৫ ফ্ল্যাটের একটি আবাসিক ভবনের জন্য।

### বাধ্যতামূলক কার্যপ্রণালী (এই ক্রম কখনো ভাঙবে না):

1. **এক ফেজে এক মডিউল।** নিচে দেওয়া ফেজ ক্রম অনুসরণ করবে। একটি মডিউল সম্পূর্ণ ও টেস্ট-পাস না হওয়া পর্যন্ত পরের মডিউলে যাবে না।
2. **প্রতিটি মডিউলের জন্য এই ধারাবাহিকতা মানবে:**
   ```
   ক. Migration লেখো (up + down দুটোই — rollback-যোগ্য হতে হবে)
   খ. Model লেখো (relationships, casts, fillable — সব স্পষ্ট)
   গ. Migration রান করো এবং কনফার্ম করো কোনো এরর নেই
   ঘ. Seeder/Factory লেখো (ডেমো ডেটাসহ, যাতে টেস্ট করা যায়)
   ঙ. Controller + FormRequest (validation) লেখো
   চ. Route যোগ করো
   ছ. **Backend Test:** নিচের "ব্যাকএন্ড টেস্ট প্রোটোকল" অনুযায়ী PHPUnit/Pest টেস্ট লিখে চালাও —
      CRUD-এর প্রতিটি এন্ডপয়েন্ট (Create/Read/Update/Delete/Validation failure case)
      আসলেই কাজ করছে কিনা যাচাই করো। টেস্ট ফেল করলে কোড ঠিক করো, পরের ধাপে যাবে না।
   জ. টেস্ট পাস হলে তবেই React ফ্রন্টএন্ড কম্পোনেন্ট লেখো (ফর্ম + লিস্ট + ডিটেইল ভিউ)
   ঝ. ফ্রন্টএন্ড থেকে backend API আসলে কল হচ্ছে ও ডেটা ঠিকমতো দেখাচ্ছে কিনা manual verification করো
      (curl বা browser-এর মাধ্যমে অন্তত একটি বাস্তব রিকোয়েস্ট-রেসপন্স যাচাই দেখাও)
   ঞ. একটি সংক্ষিপ্ত "মডিউল সম্পন্ন" সামারি লেখো — কী তৈরি হলো, কোন টেস্ট পাস হলো — তারপর পরের মডিউলে যাও
   ```
3. **কখনো অনুমান করে এগিয়ে যাবে না।** কোনো ব্যবসায়িক নিয়ম অস্পষ্ট মনে হলে (উদাহরণ: রেট, তারিখ, ফরমুলা), নিচের "নিশ্চিতকৃত ব্যবসায়িক নিয়ম" সেকশনে ফিরে দেখো — সব উত্তর সেখানে আছে। এর বাইরে কিছু অনুমান করার প্রয়োজন হলে, কোড না লিখে **প্রথমে প্রশ্ন করো**।
4. **কখনো একসাথে অনেক ফাইল তৈরি করে "সব হয়ে গেছে" বলবে না।** প্রতিটি ফাইল তৈরির পর তা কাজ করছে কিনা যাচাই করেই পরেরটায় যাবে।
5. **ডেটাবেস স্কিমা বদলে ফেলার আগে** নিচের সেকশন ৩ (ডেটা মডেল)-এর সাথে মিলিয়ে নেবে — এলোমেলো নতুন টেবিল/কলাম নাম বসাবে না।
6. **কোনো রেকর্ড হার্ড-ডিলিট করবে না** — সব জায়গায় soft delete (`deleted_at`) ব্যবহার করবে।
7. **আর্থিক কোনো এন্ট্রি তৈরি করলেই** `created_by`, এবং প্রযোজ্য ক্ষেত্রে `approved_by` কলাম বসাবে ও পূরণ করবে — এটি বাদ দেওয়া চলবে না।

---

## ব্যাকএন্ড টেস্ট প্রোটোকল (প্রতিটি মডিউলে বাধ্যতামূলক)

প্রতিটি এন্টিটির জন্য ন্যূনতম এই টেস্টগুলো লিখবে ও পাস করাবে (Pest বা PHPUnit, যেটা প্রজেক্টে সেটআপ করা আছে):

```
✅ Create: বৈধ ডেটা দিয়ে রেকর্ড তৈরি হয়, DB-তে যাচাই (assertDatabaseHas)
✅ Validation: অবৈধ/অসম্পূর্ণ ডেটা পাঠালে 422 এবং সঠিক error message আসে
✅ Read: নির্দিষ্ট রেকর্ড সঠিকভাবে ফেরত আসে; তালিকা (pagination সহ) সঠিক আসে
✅ Update: আপডেট করলে DB-তে প্রতিফলিত হয়
✅ Delete: soft delete হয়, deleted_at বসে, তালিকায় আর দেখা যায় না
✅ Permission: ভুল রোল দিয়ে অ্যাক্সেস করলে 403 আসে
✅ ব্যবসায়িক নিয়ম-নির্দিষ্ট টেস্ট: (উদাহরণ — বিলিং মডিউলে "Previous Due অটো ক্যারি-ফরোয়ার্ড
   হচ্ছে কিনা", গ্যাস মডিউলে "units_used = current - previous সঠিক হচ্ছে কিনা",
   রিসিট মডিউলে "নম্বর duplicate হচ্ছে না" ইত্যাদি — প্রতিটি মডিউলের নিজস্ব নিয়ম টেস্ট করতে হবে)
```
**টেস্ট কমান্ড রান করে তার আউটপুট（pass/fail সংখ্যা）দেখিয়ে তবেই এগিয়ে যাবে।** কোনো টেস্ট স্কিপ করা বা "পরে করব" বলা যাবে না।

---

## নিশ্চিতকৃত ব্যবসায়িক নিয়ম (এগুলো প্রশ্নাতীত সত্য — অনুমান নয়)

```
১. সার্ভিস চার্জ: ৳৩,৫০০/মাস/ফ্ল্যাট; ফ্ল্যাট খালি থাকলে ৳২,৫০০
২. বিল পরিশোধের শেষ তারিখ: প্রতি মাসের ১০ তারিখ
৩. গ্যাসের একক: ঘনমিটার (m³); রেট: ৳৩৩০/m³ (Fixed, কনফিগারেবল)
৪. গ্যাস বিল = (বর্তমান মিটার রিডিং − পূর্ববর্তী রিডিং) × রেট
৫. গ্যাস System Loss % = (ক্রয়কৃত LPG-এর সমতুল্য m³ − সব ফ্ল্যাট+অফিসের রিডিং যোগফল)
                          ÷ ক্রয়কৃত সমতুল্য m³ × ১০০
   কেজি→ঘনমিটার রূপান্তর ফ্যাক্টর: ডিফল্ট ০.৫৪ (কনফিগারেবল)
৬. প্রজেক্ট অফিস নিজেও একজন গ্যাস কনজিউমার (is_internal=true), তার বিল আদায় নয় — খরচ হিসেবে গণ্য
৭. সার্ভিস চার্জ থেকে নিয়মিত খরচ: ম্যানেজার বেতন + ২ জন সিকিউরিটি গার্ড বেতন + ১ জন ক্লিনার বেতন
   + সিটি কর্পোরেশন সংক্রান্ত খরচ + অন্যান্য পরিচালন ব্যয়
৮. প্রতি মাস শেষে: আদায় − মোট খরচ = উদ্বৃত্ত → এই উদ্বৃত্ত পুরোটা "Sinking Fund" (সংরক্ষিত তহবিল)-এ
   স্বয়ংক্রিয়ভাবে ট্রান্সফার হবে (Monthly Closing প্রসেস)
৯. Sinking Fund শুধু জরুরি প্রয়োজনে খরচযোগ্য: লিফট, মটর/পাম্প, সাবস্টেশন — এই তিন ক্যাটাগরির
   যন্ত্রাংশ নষ্ট/মেরামতের জন্য। খরচ সীমা: ৳২৫,০০০ পর্যন্ত ২ জন এডমিনের অনুমোদন,
   ৳১,০০,০০০-এর বেশি হলে একটি Meeting Resolution বাধ্যতামূলক (resolution_id লিংক ছাড়া
   এই পরিমাণ খরচ এন্ট্রি সিস্টেমে করা যাবে না)
১০. "উন্নয়ন তহবিল" (Development Fund) = একটি Special Levy (এককালীন বিশেষ চাঁদা), নিয়মিত
    সার্ভিস চার্জ নয়। এটি সবসময় একটি General Meeting Resolution-এর সাথে যুক্ত থাকবে।
    আদায়কৃত টাকা "Capital Fund"-এ জমা হবে, "Operating Fund"-এ নয়। কিস্তিতে আদায়যোগ্য।
১১. প্রতিটি ফ্ল্যাটে দুই ধরনের payer থাকতে পারে:
    - সার্ভিস চার্জ সাধারণত মালিক (Owner) দেয়
    - গ্যাস বিল মালিক অথবা ভাড়াটিয়া (Tenant) — যে বাস্তবে ফ্ল্যাটে থাকে সে দেয়
    প্রতিটি collection_head-এ payer_type ফিল্ড দিয়ে এটি নিয়ন্ত্রিত হবে
১২. মানি রিসিট নম্বর: সিস্টেম-জেনারেটেড, auto-increment, globally unique — কখনো ম্যানুয়াল ইনপুট নয়
১৩. প্রতিটি আর্থিক লেনদেনে মাধ্যম নির্বাচন বাধ্যতামূলক: Bank অথবা Cash — দুটির ভিন্ন,
    সবসময়-আপডেটেড রানিং ব্যালেন্স থাকবে (bank_transactions ও cash_ledger আলাদা টেবিল)
১৪. প্রতিটি আর্থিক লেনদেন (আদায়/খরচ/ফান্ড ট্রান্সফার) একটি ডাবল-এন্ট্রি journal_entry +
    journal_lines অটোজেনারেট করবে (ব্যবহারকারী শুধু সহজ ফর্ম পূরণ করবে, ডাবল-এন্ট্রি
    ভেতরে ভেতরে সিস্টেম করবে)
১৫. ফিসক্যাল ইয়ার: জানুয়ারি–ডিসেম্বর (কনফিগারেবল সেটিং হিসেবে রাখবে, হার্ডকোড নয়)
১৬. জমির খাজনা রসিদ (owner_documents, type=LAND_TAX_RECEIPT) প্রতি বছর একটি নতুন এন্ট্রি
    হিসেবে আপলোড হবে (document_year কলাম দিয়ে ট্র্যাক), পুরনোটা ওভাররাইট হবে না
১৭. Maker-Checker: Manager-এর তৈরি করা payment/expense এন্ট্রি "PENDING" অবস্থায় থাকবে,
    Admin/Super Admin approve না করা পর্যন্ত তা কোনো ব্যালেন্স/লেজারে যোগ হবে না
১৮. মাস ক্লোজ (billing_periods.status = LOCKED) হয়ে গেলে সেই মাসের কোনো এন্ট্রি এডিট/ডিলিট
    করা যাবে না — শুধু Super Admin আনলক করতে পারবে, এবং তা audit_logs-এ রেকর্ড হবে
```

---

## ডেটা মডেল (সব মাইগ্রেশন এই স্কিমা অনুযায়ী — নাম/কলাম হুবহু অনুসরণ করো)

```sql
-- কোর মাস্টার
buildings(id, name, address, total_floors, total_flats, logo, timestamps)
flats(id, building_id, flat_no, floor, size_sqft, occupancy_status ENUM[OWNER_OCCUPIED,TENANTED,EMPTY],
      service_charge_rate DEFAULT 3500, empty_flat_rate DEFAULT 2500, gas_meter_no, status, timestamps, soft_deletes)
persons(id, name_bn, name_en, mobile, country_code DEFAULT '+880', alt_mobile, nid, email,
        address, photo, preferred_channel ENUM[SMS,WHATSAPP,EMAIL,APP], timestamps, soft_deletes)
flat_parties(id, flat_id, person_id, role ENUM[OWNER,TENANT], from_date, to_date, is_billing_contact, timestamps)
tenants(id, flat_id, name, mobile, nid_no, occupation, family_members_count,
        start_date, end_date, monthly_rent, security_deposit, status ENUM[ACTIVE,VACATED], timestamps, soft_deletes)
tenant_documents(id, tenant_id, document_type ENUM[NID,PHOTO,POLICE_VERIFICATION], file_path, uploaded_at, timestamps)
owner_documents(id, person_id, flat_id, document_type ENUM[NID,NAMJARI,LAND_TAX_RECEIPT,OTHER],
                document_year, file_path, issued_date, expiry_date, uploaded_by, timestamps)
staff(id, name, designation, mobile, nid, join_date, monthly_salary, photo, status, timestamps, soft_deletes)
users(id, person_id nullable, staff_id nullable, mobile, password, role, status, last_login, timestamps)

-- বিলিং
collection_heads(id, code, name_bn, name_en, payer_type ENUM[OWNER,OCCUPANT],
                 charge_type ENUM[RECURRING,SPECIAL_LEVY,MISC], is_active, sort_order, timestamps)
billing_periods(id, year, month, status ENUM[DRAFT,GENERATED,LOCKED], generated_by, locked_at, timestamps)
bills(id, billing_period_id, flat_id, service_charge, gas_amount, other_charges, late_fee,
      previous_due, total_payable, due_date, status ENUM[UNPAID,PARTIAL,PAID], timestamps)
bill_items(id, bill_id, collection_head_id, description, amount, timestamps)

gas_purchases(id, date, supplier_name, quantity_kg, unit_price, transport_cost, total_cost, invoice_file, timestamps)
gas_consumers(id, flat_id nullable, name, meter_no, is_internal DEFAULT false, active, timestamps)
gas_meter_readings(id, gas_consumer_id, billing_period_id, previous_reading, current_reading,
                   units_used, reading_date, entered_by, photo, timestamps)
gas_rate_settings(id, effective_from, method ENUM[FIXED,ACTUAL], rate, m3_per_kg DEFAULT 0.54,
                  system_loss_percent, minimum_charge, timestamps)

construction_dues(id, flat_id, category, source_type ENUM[INITIAL_CONTRACT,LEVY_RESOLUTION],
                  resolution_id nullable, total_amount, paid_amount, balance, timestamps)
due_installments(id, construction_due_id, installment_no, due_date, amount, paid_amount, status, timestamps)

-- ট্রেজারি ও অ্যাকাউন্টিং
bank_accounts(id, bank_name, branch, account_no, account_title, mandate_rule, opening_balance, current_balance, timestamps)
bank_transactions(id, bank_account_id, txn_date, particulars, debit, credit, balance_after,
                  fund_type ENUM[OPERATING,GAS,CAPITAL,SINKING], ref_type, ref_id, created_by, timestamps)
cash_ledger(id, txn_date, particulars, in_amount, out_amount, balance_after,
           fund_type ENUM[OPERATING,GAS,CAPITAL,SINKING], ref_type, ref_id, created_by, timestamps)

chart_of_accounts(id, code, name_bn, name_en, account_type ENUM[ASSET,LIABILITY,INCOME,EXPENSE,FUND],
                  parent_id nullable, timestamps)
journal_entries(id, entry_date, narration, fiscal_year, created_by, approved_by, status, timestamps)
journal_lines(id, journal_entry_id, account_id, debit, credit, timestamps)

payments(id, flat_id, bill_id nullable, amount, payment_date, method ENUM[CASH,BANK,BKASH,NAGAD],
         reference_no, receipt_no unique auto, received_by, approved_by, status ENUM[PENDING,APPROVED], timestamps)
expense_heads(id, code, name_bn, name_en, parent_id nullable, timestamps)
expenses(id, date, expense_head_id, payee, amount, description, treasury_medium ENUM[BANK,CASH],
         voucher_no, voucher_image, created_by, approved_by, status ENUM[PENDING,APPROVED], timestamps)
salary_payments(id, staff_id, year, month, basic, overtime, bonus, advance_deduction,
                net_amount, paid_date, treasury_medium, status, timestamps)
fund_transfers(id, from_fund, to_fund, amount, reason, resolution_id nullable, transfer_date,
              created_by, approved_by, timestamps)

-- গভর্নেন্স
assets(id, name, category ENUM[LIFT,SOLAR,SUBSTATION,PUMP,GENERATOR,OTHER], purchase_date, purchase_cost,
      supplier_name, supplier_mobile, warranty_expiry, amc_expiry, next_service_date, status, timestamps)
asset_service_logs(id, asset_id, service_date, description, cost, technician, timestamps)
meetings(id, title, meeting_date, venue, agenda, minutes, status, timestamps)
resolutions(id, meeting_id, resolution_no, description, decision, status ENUM[PENDING,ONGOING,DONE,CANCELLED], timestamps)
complaints(id, flat_id, category, description, voice_note, images, priority, status,
          assigned_to, resolved_at, rating, timestamps)

-- যোগাযোগ ও অডিট
sms_templates(id, event_key, body_bn, is_active, timestamps)
sms_logs(id, mobile, message, event_key, gateway, status ENUM[QUEUED_PENDING_GATEWAY,SENT,FAILED],
        cost, sent_at, timestamps)
notices(id, title, body, attachment, audience, published_at, created_by, timestamps)
audit_logs(id, user_id, action, model, model_id, old_values JSON, new_values JSON, ip, timestamps)
```

---

## ফেজ ও মডিউল অর্ডার (এই ক্রম অনুসরণ করো — লাফ দেবে না)

### FOUNDATION (আগে এগুলো, বাকি সব এর উপর নির্ভরশীল)
```
0.1 → Laravel 12 project init + Sanctum + spatie/laravel-permission ইনস্টল
0.2 → React 18 + TypeScript + Tailwind SPA স্ক্যাফোল্ড, API base client সেটআপ
0.3 → Migration: users, roles/permissions সিডিং (Super Admin, Admin, Manager, Owner, Tenant)
0.4 → OTP-ভিত্তিক লগইন (মোবাইল নম্বর → OTP → Sanctum token) — TEST করো
0.5 → Migration: buildings, flats, persons, flat_parties, tenants
      → CRUD + TEST (উপরের প্রোটোকল অনুযায়ী)
      → React ফর্ম: ফ্ল্যাট যোগ/এডিট ফর্ম, মালিক/ভাড়াটিয়া যোগ ফর্ম
0.6 → owner_documents, tenant_documents (ফাইল আপলোড সহ)
      → CRUD + TEST (ফাইল আপলোড ভ্যালিডেশন সহ — সাইজ, টাইপ)
      → React: ডকুমেন্ট আপলোড UI (drag-drop, প্রিভিউ থাম্বনেইল)
0.7 → staff মডিউল → CRUD + TEST + ফর্ম
```

### MONEY CORE
```
1.1 → collection_heads সিডিং (RECURRING: সার্ভিস চার্জ, গ্যাস বিল;
      SPECIAL_LEVY: উন্নয়ন তহবিল; MISC: লেট ফি, সমিতির চাঁদা, অন্যান্য)
1.2 → chart_of_accounts সিডিং (Asset/Liability/Income/Expense/Fund হায়ারার্কি)
1.3 → bank_accounts, cash_ledger মডেল + CRUD + TEST
      → journal_entries/journal_lines ইঞ্জিন (helper service ক্লাস লেখো যা
        ডেবিট=ক্রেডিট ব্যালেন্স চেক করবে — এই ইনভ্যারিয়েন্ট ভাঙলে exception ছুড়বে)
      → TEST: journal সবসময় balanced থাকে কিনা (একটি ইচ্ছাকৃত ভুল entry দিয়ে যাচাই করো
        যে সিস্টেম সঠিকভাবে রিজেক্ট করছে)
1.4 → billing_periods + bills + bill_items মডেল
      → মাসিক বিল অটো-জেনারেশন Job লেখো:
        - Previous Due অটো ক্যারি-ফরোয়ার্ড (আগের মাসের bill.total_payable − payments = নতুন previous_due)
        - Empty flat হলে ৳২,৫০০, নয়তো ৳৩,৫০০
        - due_date = মাসের ১০ তারিখ অটো বসাও
      → TEST: (ক) Previous Due সঠিক ক্যারি হচ্ছে (খ) খালি ফ্ল্যাটের রেট আলাদা হচ্ছে
        (গ) একই মাসে দুইবার বিল জেনারেট করলে duplicate তৈরি না হয়ে error দেয়
      → React: "মাসিক সার্ভিস চার্জ রেজিস্টার" স্ক্রিন (৪৫ ফ্ল্যাটের টেবিল, রিয়েল-টাইম টোটাল)
1.5 → gas_purchases, gas_consumers, gas_meter_readings, gas_rate_settings
      → units_used = current_reading - previous_reading অটো ক্যালকুলেশন সার্ভিস লেখো
      → System Loss % ক্যালকুলেশন সার্ভিস (নিশ্চিতকৃত নিয়ম #৫ হুবহু)
      → TEST: (ক) units_used সঠিক ক্যালকুলেট হচ্ছে (খ) current < previous হলে ভ্যালিডেশন এরর
        (গ) System Loss % সঠিক ফরমুলায় বের হচ্ছে (ম্যানুয়ালি হিসাব করা একটি সংখ্যার সাথে মিলিয়ে)
      → React: মিটার রিডিং এন্ট্রি স্ক্রিন — ৪৫ ফ্ল্যাট সিরিয়ালি, বড় নিউমেরিক ইনপুট,
        অটো-সেভ, পরের ইনপুটে অটো-ফোকাস (keyboard-only workflow সম্ভব হতে হবে)
1.6 → payments মডেল + Maker-Checker ওয়ার্কফ্লো (status=PENDING → APPROVED)
      → Approve হলে অটো receipt_no জেনারেট + bank_transactions অথবা cash_ledger-এ এন্ট্রি
        + journal_entry অটো তৈরি (Debit: Bank/Cash, Credit: সংশ্লিষ্ট Income account)
      → TEST: (ক) receipt_no কখনো duplicate হয় না (একসাথে ১০টি concurrent payment তৈরি করে
        যাচাই করো — race condition টেস্ট) (খ) Approve না করা পর্যন্ত ব্যালেন্সে যোগ হয় না
        (গ) Approve করলে journal balanced থাকে
      → React: পেমেন্ট এন্ট্রি ফর্ম (Manager ভিউ) + Approval Queue (Admin ভিউ)
      → PDF: Money Receipt (A5, বাংলা ফরম্যাট) — রিসিট জেনারেট করে চেক করো যে সঠিক দেখাচ্ছে
1.7 → expense_heads, expenses, salary_payments, fund_transfers
      → Monthly Closing Job: (আদায় − খরচ = উদ্বৃত্ত) → fund_transfer to Sinking Fund অটো
      → Sinking Fund খরচের অনুমোদন-স্তর লজিক (৳২৫,০০০/১,০০,০০০ থ্রেশহোল্ড, নিয়ম #৯)
      → TEST: (ক) Monthly closing সঠিক উদ্বৃত্ত ক্যালকুলেট করছে (খ) ১,০০,০০০+ টাকার
        Sinking Fund খরচ resolution_id ছাড়া রিজেক্ট হচ্ছে (গ) মাস lock হওয়ার পর এডিট ব্লক হচ্ছে
      → React: খরচ এন্ট্রি ফর্ম (ভাউচার ছবি আপলোডসহ), Monthly Closing ড্যাশবোর্ড বাটন
```

### GOVERNANCE
```
2.1 → construction_dues + due_installments (উভয় source_type)
      → Aging Report কোয়েরি (০-৩০/৩১-৯০/৯০+ দিন গ্রুপিং)
      → TEST + React ফর্ম + রিপোর্ট টেবিল
2.2 → assets + asset_service_logs
      → TEST (next_service_date অতিক্রান্ত হলে flag হচ্ছে কিনা) + React ফর্ম
2.3 → meetings + resolutions
      → Special Levy তৈরির সময় resolution_id সিলেক্ট বাধ্যতামূলক করা (নিয়ম #১০)
      → TEST + React ফর্ম (মিটিং তৈরি → রেজল্যুশন যোগ → Levy লিংক)
2.4 → complaints মডিউল (ছবি + voice note আপলোড)
      → TEST + React ফর্ম (মালিক পোর্টাল থেকে)
```

### COMMUNICATION
```
3.1 → SmsGatewayInterface + NullSmsGateway (driver pattern, নিয়ম অনুযায়ী গেটওয়ে ছাড়াই কাজ করবে)
      → sms_templates, sms_logs (status=QUEUED_PENDING_GATEWAY)
      → SendSmsJob (Queue) — বিল জেনারেট, পেমেন্ট রসিদ, বকেয়া রিমাইন্ডার ইভেন্টে ট্রিগার
      → TEST: Job queue-তে সঠিকভাবে জমা হচ্ছে ও log তৈরি হচ্ছে কিনা (গেটওয়ে integration ছাড়াই)
3.2 → notices মডেল + React নোটিশ বোর্ড UI
```

### REPORTING (এই ধাপ সবার শেষে — কারণ এটি বাকি সব ডেটার উপর নির্ভরশীল)
```
4.1 → Trial Balance রিপোর্ট (chart_of_accounts + journal_lines থেকে অটো এগ্রিগেশন)
      → TEST: মোট Debit = মোট Credit (সবসময় সত্য হতে হবে — এটিই মূল অ্যাকাউন্টিং ইনভ্যারিয়েন্ট)
4.2 → আয়-ব্যয় বিবরণী + ব্যালেন্স শিট (account_type অনুযায়ী ফিল্টার)
4.3 → এডমিন ড্যাশবোর্ড (KPI কার্ড: আদায়, বকেয়া, খরচ, Sinking Fund ব্যালেন্স, Gas System Loss%)
4.4 → Owner PWA পোর্টাল (হোম, বিল, নোটিশ, অভিযোগ, আমার তথ্য/ডকুমেন্ট) — সব আগের API-এর উপর ভিত্তি করে
```

---

## UI/UX ডিজাইন নির্দেশনা (প্রতিটি স্ক্রিনে বাধ্যতামূলক)

- **Modern & creative:** ডিফল্ট Bootstrap/Material লুক নয়। কাস্টম Tailwind ডিজাইন সিস্টেম বানাও —
  নিজস্ব color palette (নেভি/টিল প্রাইমারি), কনসিস্টেন্ট spacing scale, subtle shadow/elevation
- **Hover states বাধ্যতামূলক:** প্রতিটি বাটন, কার্ড, টেবিল রো, ক্লিকযোগ্য এলিমেন্টে স্পষ্ট hover
  ইফেক্ট (color shift + soft shadow/lift), transition duration 150-200ms, `cursor-pointer`
- **ফর্ম ডিজাইন:** ফ্লোটিং লেবেল অথবা স্পষ্ট উপরের লেবেল, ইনলাইন ভ্যালিডেশন এরর (লাল বর্ডার +
  মেসেজ, শুধু alert নয়), সাবমিট বাটনে loading spinner state
- **টাইপোগ্রাফি:** বাংলা টেক্সটে Noto Sans Bengali/Hind Siliguri, ইংরেজি/সংখ্যায় Inter,
  line-height 1.6-1.7 (বাংলা টেক্সট বেশি জায়গা নেয়, এটি মাথায় রাখো)
- **Responsive:** Admin প্যানেল ডেস্কটপ-প্রথম (sidebar layout), Owner পোর্টাল মোবাইল-প্রথম
  (bottom nav বা top app bar, ৫টির বেশি প্রধান স্ক্রিন নয়)
- **টাচ টার্গেট:** ন্যূনতম 48×48px — বয়স্ক ব্যবহারকারী মাথায় রেখে
- **রঙ কোডিং:** সবুজ=Paid, অ্যাম্বার=Partial, লাল=Due/Overdue — সব জায়গায় সামঞ্জস্যপূর্ণ
- প্রতিটি মডিউলের React অংশ শেষ করার পর **স্ক্রিনশট বা বর্ণনা দিয়ে দেখাও** ডিজাইন স্ট্যান্ডার্ড
  মানা হয়েছে কিনা — শুধু "component তৈরি করলাম" বলে থেমো না

---

## প্রতিটি মডিউল শেষে বাধ্যতামূলক সামারি ফরম্যাট

প্রতিটি মডিউল সম্পন্ন করার পর ঠিক এই ফরম্যাটে জানাও, তারপরই পরের মডিউলে যাও:

```
✅ মডিউল: [নাম]
   Migration: [ফাইলের নাম, রান হয়েছে কিনা]
   Backend Test: [X টি টেস্ট, X পাস, X ফেল — ফেল থাকলে বিস্তারিত ও সমাধান]
   API যাচাই: [একটি বাস্তব curl/response উদাহরণ]
   Frontend: [কোন কম্পোনেন্ট তৈরি হয়েছে, hover/validation যাচাই হয়েছে কিনা]
   পরবর্তী মডিউল: [নাম]
```

---

## শুরু করো

এখন **FOUNDATION 0.1** থেকে শুরু করো। প্রথমে Laravel 12 প্রজেক্ট ইনিশিয়ালাইজ করো,
তারপর ধাপ ০.২ থেকে ০.৭ পর্যন্ত এক এক করে সম্পন্ন করো — প্রতিটির পর টেস্ট ও সামারি দেখিয়ে
তবেই পরেরটায় যাও। কোনো ধাপ স্কিপ করবে না, কোনো অনুমান নিজে থেকে করবে না — অস্পষ্টতা
পেলে থামো এবং জিজ্ঞেস করো।
