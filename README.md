# Yuniq.ai

> دستیار هوشمندی که از محتوای خودِ سایت شما پاسخ می‌دهد.
> An AI assistant for WordPress that answers from your own site content.

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)](LICENSE)

Yuniq.ai سایت وردپرسی شما را به یک دستیار تعاملی تبدیل می‌کند. افزونه محتوای سایت را ایندکس می‌کند و آن را به‌عنوان پایگاه دانش در اختیار مدل هوش مصنوعی می‌گذارد، بنابراین به‌جای پاسخ‌های عمومی، جواب‌ها به محصولات، خدمات و مقالات خودِ شما ارجاع دارند.

Yuniq.ai indexes your WordPress content into a knowledge base and passes it to an AI model as grounding context, so the chat widget answers with your own products, services and articles instead of generic model knowledge.

**[English ↓](#english)**

---

## امکانات

- **اتصال به کارشناس انسانی** — دکمه‌ی ثابت «صحبت با کارشناس» + پیشنهاد خودکار وقتی هوش مصنوعی نمی‌تواند کمک کند؛ پنل «پشتیبانی زنده» در مدیریت با اعلان ایمیل و تلگرام
- **کارت محصول هوشمند** — معرفی محصول ووکامرس با تصویر، قیمت و وضعیت موجودی واقعی، درست داخل گفتگو
- **فرم دریافت سرنخ داخل گفتگو** — فرم مشاوره/تماس قابل‌تنظیم که هوش مصنوعی در لحظه‌ی مناسب نمایش می‌دهد
- **حالت صفحه‌ی کامل** — شورت‌کد `[yuniq_ai_page]` برای نمایش دستیار به‌صورت یک صفحه‌ی اختصاصی
- **رابط کاربری مدرن** — طراحی Clean SaaS، حالت تاریک، حلقه‌ی درخشان اختصاصی روی دکمه شناور، و انیمیشن اختیاری با GSAP که فقط هنگام باز شدن پنل لود می‌شود
- **بدون هیچ درخواست خارجی** — فونت وزیرمتن و کتابخانه انیمیشن از دامنه‌ی خود سایت سرو می‌شوند؛ مناسب برای ایران و بدون مشکل GDPR
- **پاسخ تدریجی (streaming)** — جواب کلمه‌به‌کلمه با Server-Sent Events نمایش داده می‌شود و در صورت پشتیبانی‌نکردن سرور، خودکار به حالت عادی برمی‌گردد
- **ایندکس دسته‌ای** — سایت‌های بزرگ بدون timeout ایندکس می‌شوند، با نوار پیشرفت واقعی و امکان توقف
- **جست‌وجوی بهینه‌شده برای فارسی** — یکسان‌سازی ی/ي، ک/ك، نیم‌فاصله و ارقام فارسی/عربی
- **دسترسی‌پذیری** — ناوبری کامل با کیبورد، تله‌ی فوکوس، `aria-modal` و احترام به `prefers-reduced-motion`
- پایگاه دانش خودکار از نوشته‌ها، برگه‌ها، محصولات ووکامرس (با قیمت، SKU و موجودی) و دسته‌بندی‌ها
- سازگار با OpenAI و هر سرویس سازگار با `chat/completions` (از جمله سرویس‌های ایرانی ریالی)
- ویجت شناور با موقعیت قابل تنظیم، فیلتر مسیرها، کارت‌های اقدام سریع و داشبورد آمار گفتگوها
- محدودیت نرخ درخواست بر اساس IP و نشست روی endpoint عمومی

## نصب

1. پوشه `yuniq-ai` را در `/wp-content/plugins/` آپلود کنید.
2. افزونه را از منوی افزونه‌ها فعال کنید.
3. به **Yuniq.ai ← تنظیمات** بروید و کلید API و آدرس سرویس هوش مصنوعی خود را وارد کنید.
4. در تب **خزنده محتوا** مشخص کنید چه محتوایی ایندکس شود.
5. به **پایگاه دانش** بروید و **شروع ایندکس‌گذاری** را بزنید.

> **افزونه هیچ کلید API همراه خود ندارد.** باید کلید خودتان را وارد کنید و هزینه مصرف بر عهده صاحب همان کلید است.

## توسعه

افزونه کلاس‌محور، با namespace `Yuniq\Ai` و بارگذاری خودکار PSR-4 نوشته شده است.

```bash
composer install     # نصب ابزارهای توسعه
composer lint        # بررسی با استاندارد کدنویسی وردپرس
composer lint:fix    # اصلاح خودکار
```

### ساختار

| مسیر | توضیح |
| --- | --- |
| `src/Ai/` | کلاینت هوش مصنوعی و ارائه‌دهنده‌ها (`OpenAiProvider`) |
| `src/Kb/` | ایندکس‌گذار و مخزن پایگاه دانش |
| `src/Rest/` | endpointهای REST در namespace `yuniq-ai/v1` |
| `src/Admin/` | صفحات مدیریت و کنترلر AJAX خزنده |
| `src/Frontend/` | ویجت شناور گفتگو و شورت‌کد صفحه‌ی کامل |
| `src/LiveSupport/` | مخزن گفتگوهای پشتیبانی زنده و سرنخ‌ها |
| `src/Notifications/` | ارسال اعلان (ایمیل و تلگرام) برای درخواست کارشناس و سرنخ جدید |
| `src/Setup/` | فعال‌سازی، غیرفعال‌سازی و تعریف جدول‌ها |
| `src/Support/` | محدودکننده نرخ و ابزار نرمال‌سازی متن فارسی |

### REST API

| متد | مسیر | کاربرد |
| --- | --- | --- |
| `POST` | `/wp-json/yuniq-ai/v1/chat` | ارسال پیام و دریافت پاسخ کامل |
| `POST` | `/wp-json/yuniq-ai/v1/chat/stream` | پاسخ تدریجی با SSE |
| `GET` | `/wp-json/yuniq-ai/v1/config` | تنظیمات عمومی ویجت |
| `POST` | `/wp-json/yuniq-ai/v1/escalate` | درخواست اتصال به کارشناس |
| `POST` | `/wp-json/yuniq-ai/v1/lead` | ثبت فرم سرنخ داخل گفتگو |
| `GET`/`POST` | `/wp-json/yuniq-ai/v1/conversation/{session_id}/messages` | دریافت/ارسال پیام در گفتگوی اسکالیت‌شده |

### هوک‌ها

| فیلتر | کاربرد |
| --- | --- |
| `yuniq_ai_provider` | جایگزینی کامل ارائه‌دهنده با پیاده‌سازی `AiProviderInterface` |
| `yuniq_ai_system_prompt` | تغییر پرامپت سیستم و متن پایگاه دانش |
| `yuniq_ai_api_request_args` | تغییر پارامترهای درخواست HTTP |
| `yuniq_ai_is_active` | کنترل نمایش ویجت در هر درخواست |
| `yuniq_ai_powered_by_html` | تغییر یا حذف امضای زیر پنل |
| `yuniq_ai_client_ip` | تعیین IP پشت پراکسی یا CDN |
| `yuniq_ai_booted` | دسترسی به سرویس‌کانتینر پس از راه‌اندازی |
| `yuniq_ai_notify_channels` | افزودن کانال اعلان سفارشی برای پشتیبانی زنده |

### داده‌ها

شش جدول ساخته می‌شود: `{prefix}yuniq_ai_knowledge`، `{prefix}yuniq_ai_analytics`، `{prefix}yuniq_ai_crawl_log`، `{prefix}yuniq_ai_conversations`، `{prefix}yuniq_ai_messages` و `{prefix}yuniq_ai_leads`. غیرفعال‌سازی داده‌ها را نگه می‌دارد؛ حذف کامل افزونه تمام جدول‌ها و تنظیمات را پاک می‌کند (با پشتیبانی از مالتی‌سایت).

---

<a name="english"></a>

## English

Yuniq.ai turns a WordPress site into an interactive assistant. It crawls your content into a knowledge base and grounds an AI model on it, so answers reference your actual products, services and articles.

### Highlights

- **Human handoff** — a persistent "talk to a human" control plus automatic escalation when the AI can't help, with a dedicated Live Support inbox in wp-admin and email/Telegram notifications
- **AI-triggered product cards** — grounded in real indexed WooCommerce price/stock, not model guesses
- **In-chat lead-capture form** — a configurable consultation/contact form the AI surfaces at the right moment
- **Full-page mode** — the `[yuniq_ai_page]` shortcode renders the assistant as a dedicated page instead of a floating widget
- **No external requests at runtime** — Vazirmatn font and GSAP are bundled and served from your own domain (no Google Fonts, no CDN)
- **Streaming responses** over Server-Sent Events, with automatic fallback when the host does not support them
- **Batched, resumable indexing** so large sites index without timeouts
- **Persian-aware search** — normalises ی/ي, ک/ك, ZWNJ and Persian/Arabic digits
- **Accessible** — keyboard navigation, focus trap, `aria-modal`, honours `prefers-reduced-motion`
- WooCommerce products indexed with price, SKU and stock status
- Works with OpenAI and any `chat/completions`-compatible endpoint
- Per-IP and per-session rate limiting on the public chat endpoint
- Fully Persian UI with RTL support

### Requirements

WordPress 6.0+ (tested to 6.7) · PHP 7.4+ · your own AI provider API key

### Quick start

Upload the `yuniq-ai` folder to `/wp-content/plugins/`, activate it, then add your API key and endpoint under **Yuniq.ai → Settings**, pick what to crawl, and run the indexer from the **Knowledge Base** page.

No API key ships with the plugin — usage is billed to whoever owns the key you configure.

### Extending

The plugin is namespaced (`Yuniq\Ai`) and PSR-4 autoloaded. Swap the AI backend entirely by returning your own `AiProviderInterface` implementation from the `yuniq_ai_provider` filter; see the hooks table above for the other extension points.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

Vazirmatn font — SIL Open Font License 1.1. GSAP — see [its own license](https://gsap.com/licensing/).
