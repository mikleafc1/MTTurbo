<div dir="rtl">

# ⚡ MTTurbo

**پروکسی MTProto ضد فیلتر، با سرعت بشدت بالا + کانال اسپانسری — یک دستور نصب**

اسکریپت مدیریت و نصب پروکسی تلگرام برای Ubuntu / Debian.
MTTurbo از پایه بازنویسی شده تا مشکلات اسکریپت‌های مشابه (مثل MTPulse) که پروکسی‌شون **وصل نمی‌شد** رو حل کنه.

</div>

---

## 🚀 One-line install

```bash
bash <(curl -Ls https://raw.githubusercontent.com/mikleafc1/MTTurbo/main/mtturbo.sh)
```

<div dir="rtl">

(بعد از نصب، هر زمان با دستور `sudo mtturbo` اجراش کن.)

## ❓ چرا MTPulse وصل نمی‌شد؟

| مشکل MTPulse | راه‌حل MTTurbo |
|---|---|
| سکرت ساده ۱۶ بایتی **بدون رمزنگاری** → شناسایی آنی توسط DPI | فقط سکرت **FakeTLS (ee)** با دامنه استتار |
| موتور MTProxy رسمی قدیمی و بی‌به‌روزرسانی | **mtg v2 (Go)** و **telemt (Rust)** — موتورهای مدرن |
| بدون تیونینگ → سرعت پایین | **BBR + بافرهای هسته + TCP FastOpen** |
| بدون عیب‌یابی | **Health Check** داخلی با تشخیص مشکل |
| اسپانسری فقط تگ | تگ رسمی **@MTProxybot** + پارامتر `channel` در لینک |

## ✨ امکانات

- 🛡 **FakeTLS اجباری** — سکرت `ee` با دامنه استتار ضد-DPI (۱۰ دامنه آماده + دامنه دلخواه)
- ⚡ **موتور Turbo** — [mtg v2](https://github.com/9seconds/mtg): سریع‌ترین پیاده‌سازی MTProto با تکنیک Doppelganger
- 🏷 **موتور Sponsor** — [telemt](https://github.com/telemt/telemt): پشتیبانی کامل adtag برای کانال اسپانسری رسمی
- 🌐 تیونینگ خودکار BBR، بافرها، somaxconn و ...
- 🧱 تشخیص UFW و باز کردن پورت خودکار
- 📡 پشتیبانی IPv4 و IPv6
- 🩺 Health Check داخلی (سرویس، پورت، BBR، تعداد کانکشن‌ها)
- 🔁 تعویض پورت / چرخش سکرت بدون نصب مجدد
- ⚙️ سرویس systemd با hardening و Restart خودکار

## 📋 نصب در ۴ قدم

1. یک سرور **Ubuntu 20.04+ یا Debian 11+** (amd64/arm64) آماده کن — پورت ۴۴۳ آزاد باشه
2. دستور نصب بالا رو با root اجرا کن
3. تو منو گزینه **۱ — Quick Install** رو بزن (فقط ۲ سوال می‌پرسه!)
4. لینک‌های `tg://proxy` ساخته‌شده رو به دوستات بفرست 🎉

## 🏷 کانال اسپانسری رسمی

1. تو منوی اسکریپت گزینه **۳** رو بزن
2. با ربات [@MTProxybot](https://t.me/MTProxybot) پروکسی‌ت رو ثبت کن و تگ ۳۲ کاراکتری بگیر
3. تگ رو وارد اسکریپت کن — تموم! (موتور telemt خودش مدیریت می‌کنه)

> ⚠️ **نکته مهم:** وقتی ربات سکرت خواست، فقط **سکرت ۳۲ رقمی (Bot Key)** رو بفرست — نه سکرت بلند FakeTLS رو!
> اسکریپت از نسخه ۱.۱.۰ هر دو سکرت رو جدا نشون میده: سکرت بلند `ee...` مخصوص لینک‌های اتصاله و سکرت `Bot Key` مخصوص ربات.

روش ساده‌تر: لینک‌های ساخته‌شده خودشون پارامتر `&channel=` دارن و تلگرام کانالت رو پیشنهاد میده.

## 🩺 اگه وصل نشد؟

منوی **۷ (Health Check)** رو اجرا کن. چیزهایی که چک میشه:
- وضعیت سرویس systemd و پورت
- BBR فعال یا نه
- فایروال UFW
- تعداد کانکشن‌های فعال

اگه همه چیز سبز بود ولی باز وصل نشدی → آی‌پی سرورت بلاک شده؛ پورت رو عوض کن (منوی ۴) یا سرور جدید بگیر.

</div>

## ⚠️ Disclaimer

This tool is intended to restore access to free internet and Telegram in censored environments. Use responsibly and in accordance with your local laws.

## License

MIT — see [LICENSE](LICENSE)
