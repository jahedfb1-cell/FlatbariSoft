# Green Valley BMS — Android অ্যাপ

একটা ছোট (~২০ KB) **WebView অ্যাপ**: ফোনে ইনস্টল করলে আইকন চাপলেই ওয়েবসাইট খোলে।
ওয়েবসাইট বদলালে অ্যাপ আপডেট লাগে না — অ্যাপ শুধু একটা খোলস।

## অ্যাপ যা যা করে
- সার্ভারের ঠিকানা প্রথমবার জিজ্ঞেস করে মনে রাখে (মেনু ⋮ → "সার্ভার বদলান"); লাইভ বিল্ডে ঠিকানা বিল্ডের সাথেই বসানো থাকে।
- ইনভয়েসের ছবি: ক্যামেরায় তোলা বা গ্যালারি থেকে বাছা — দুটোই চলে।
- PDF/Excel/অডিট-প্যাক: ফোনের **Downloads/GreenValley** ফোল্ডারে সেভ হয়ে খোলে (WebView-তে নিজে থেকে এটা হয় না, তাই অ্যাপের `window.GVApp` bridge ব্যবহার হয়)।
- শুধু নিজের সাইটের ঠিকানা খোলে; অন্য লিংক ফোনের ব্রাউজারে যায়। সংযোগ না থাকলে বাংলায় "আবার চেষ্টা করুন" পেজ।
- সর্বনিম্ন Android 10। অনুমতি: শুধু ইন্টারনেট।

## ১. WiFi-তে ফোনে টেস্ট (এখনই)
```powershell
# একবার, Administrator হিসেবে (Windows Firewall ফোনকে আটকায়):
netsh advfirewall firewall add rule name="GreenValley dev" dir=in action=allow protocol=TCP localport=4000,8100 profile=private

scripts\start-lan.ps1                                                        # সার্ভার চালু, ঠিকানা দেখায়
scripts\build-android.ps1 -ServerUrl http://<পিসির-IP>:4000 -AllowHttp       # টেস্ট-অ্যাপ
```
ফোনে (একই WiFi) `http://<পিসির-IP>:8100/download/app` খুলে ডাউনলোড → ইনস্টল।
> `-AllowHttp` বিল্ড শুধু টেস্টের জন্য — সাধারণ http চলে। এটা কাউকে দেবেন না।

> **পোর্ট:** Green Valley ব্যাকএন্ড **৮১০০**, ফ্রন্টএন্ড **৪০০০**। ৮০০০ WBL Soft-এর।

## ২. লাইভ হোস্টিংয়ের জন্য
সাইটের ঠিকানা (HTTPS) ঠিক হলে:
1. ফ্রন্টএন্ড বিল্ড: `VITE_API_BASE_URL=https://<ডোমেইন>/api` সেট করে `npm run build`, আপলোড।
2. ব্যাকএন্ড আপলোড (সাথে `config/mobile_app.php`, `routes/web.php`, `resources/views/download-app.blade.php`)।
3. অ্যাপ বিল্ড:
   ```powershell
   scripts\build-android.ps1 -ServerUrl https://<ডোমেইন> -VersionName 1.0.0 -VersionCode 1
   ```
   লাইভ বিল্ড **শুধু HTTPS** চলে (`http://` দিলে স্ক্রিপ্টই থামিয়ে দেয়)।
4. `public/downloads/green-valley-bms.apk` ও `app-version.json` হোস্টিংয়ের `public/downloads/`-এ আপলোড।
5. ফোনে `https://<ডোমেইন>/download/app` — লগইন পেজেও "অ্যাপ ডাউনলোড" লিংক আছে।

নতুন সংস্করণ: প্রতিবার `-VersionCode` **বাড়ান** (২, ৩…), নইলে ফোন আপডেট নেবে না।

## ⚠️ সই করার চাবি (keystore) — হারালে বিপদ
প্রথম বিল্ডে `..\android-toolchain\green-valley-release.jks` তৈরি হয় (পাসওয়ার্ড `android-app\keystore.properties`-এ)।
Android শুধু **একই চাবিতে সই করা** আপডেট ইনস্টল করে। হারালে প্রতিটা ফোনে আগে অ্যাপ আনইনস্টল করতে হবে।
**দুটো ফাইলই নিরাপদ জায়গায় ব্যাকআপ রাখুন, কখনো git-এ দেবেন না** (`.gitignore`-এ আছে)।

## বিল্ড টুল (এই পিসিতে ইনস্টল করা)
`D:\building mbs soft\android-toolchain\` — JDK 17, Android SDK 34, Gradle। প্রজেক্টের বাইরে, git-এ নেই।
অন্য পিসিতে: JDK 17 + Android SDK (platform 34, build-tools 34.0.0) বসিয়ে `-ToolchainDir` দিন।
