# مجوز اجزای شخص ثالث / Third-Party Licenses

افزونه‌ی Yuniq.ai تحت **GPL-2.0-or-later** منتشر می‌شود (متن کامل در فایل `LICENSE`).

دو جزء زیر ساخته‌ی دیگران‌اند، به‌صورت محلی داخل افزونه قرار گرفته‌اند (هیچ‌کدام از CDN یا سرور خارجی بارگذاری نمی‌شوند) و مجوز خودشان را دارند:

Yuniq.ai itself is released under **GPL-2.0-or-later** (full text in `LICENSE`). The two components below are the work of others, are bundled locally (nothing is loaded from a CDN or any external server), and carry their own licenses:

---

## ۱. GSAP (GreenSock Animation Platform)

| | |
|---|---|
| نسخه / Version | 3.15.0 |
| مسیر / Path | `assets/vendor/gsap/gsap.min.js` |
| حق نشر / Copyright | GreenSock, Inc. |
| مجوز / License | GSAP Standard License — <https://gsap.com/standard-license> |
| خانه / Home | <https://gsap.com> |

از آوریل ۲۰۲۵ و پس از خریداری GreenSock توسط Webflow، کل GSAP — شامل افزونه‌های پیش‌تر پولی — برای همه رایگان شد و مجوز استاندارد آن استفاده‌ی تجاری را هم پوشش می‌دهد. متن کامل و به‌روز مجوز در نشانی بالاست. هدر مجوز داخل خود فایل `gsap.min.js` دست‌نخورده باقی مانده است.

Since April 2025, following Webflow's acquisition of GreenSock, all of GSAP — including the formerly paid plugins — became free for everyone, and its standard license covers commercial use. The license header inside `gsap.min.js` has been left intact.

**این جزء اختیاری است.** اگر پوشه‌ی `assets/vendor/gsap/` را حذف کنید، افزونه بدون هیچ تغییری در کد به انیمیشن CSS برمی‌گردد (`assets/js/motion.js` نبودِ GSAP را تشخیص می‌دهد و کنار می‌کشد).

**This component is optional.** Delete `assets/vendor/gsap/` and the plugin falls back to CSS animation with no code change (`assets/js/motion.js` detects GSAP's absence and bows out).

---

## ۲. فونت وزیرمتن / Vazirmatn font

| | |
|---|---|
| مسیر / Path | `assets/fonts/vazirmatn-{400,500,600,700}.woff2` |
| حق نشر / Copyright | 2015 The Vazirmatn Project Authors |
| مجوز / License | SIL Open Font License 1.1 — متن کامل در `assets/fonts/OFL.txt` |
| خانه / Home | <https://github.com/rastikerdar/vazirmatn> |

مجوز OFL اجازه‌ی استفاده، تغییر و بازتوزیع (از جمله در محصول تجاری) را می‌دهد، به شرط آن‌که متن مجوز همراه فونت توزیع شود — که فایل `assets/fonts/OFL.txt` همین کار را می‌کند — و فونت به‌تنهایی فروخته نشود.

The OFL permits use, modification and redistribution — including inside a commercial product — provided the license text travels with the font, which `assets/fonts/OFL.txt` does, and that the font is not sold on its own.
