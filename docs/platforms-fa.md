# تفاوت‌ها و نکات هر پلتفرم

این سند جزئیاتی را پوشش می‌دهد که در README اصلی جا نمی‌شوند: شکل API، محل دریافت توکن، محدودیت‌ها و نکاتی که قبل از استفادهٔ عملیاتی باید بررسی کنید.

> وضعیت کلی: کتابخانه با تست واحد و HTTP جعلی آزمایش شده است، نه با حساب واقعی. هرجا مطمئن نیستیم، به‌صراحت نوشته شده است. قبل از production، حساب آزمایشی و مستندات رسمی همان پلتفرم را بررسی کنید.

## مقایسهٔ سریع

| موضوع | تلگرام | بله | ایتا | روبیکا |
| --- | --- | --- | --- | --- |
| شکل API | Bot API | سازگار با تلگرام | EitaaYar | Bot API v3 با ساختار متفاوت |
| آدرس پایه | `https://api.telegram.org` | `https://tapi.bale.ai` | `https://eitaayar.ir/api` | `https://botapi.rubika.ir/v3` |
| مسیر متد | `/bot{token}/{method}` | `/bot{token}/{method}` | `/{token}/{method}` | `/{token}/{method}` |
| سقف پیام | ۴۰۹۶ | ۴۰۹۶ | حدود ۱۰۰۰ (قابل تنظیم) | ۴۰۹۶ |
| کیبورد شیشه‌ای | دارد | دارد | ندارد | ندارد |
| کیبورد معمولی | دارد | دارد | ندارد | دارد (`chat_keypad`) |
| Webhook | دارد | دارد | ندارد | در این آداپتر پشتیبانی نمی‌شود |
| Polling | دارد | دارد | ندارد | دارد |

---

## تلگرام

- **محل دریافت توکن:** [@BotFather](https://t.me/BotFather) در تلگرام.
- **آدرس پایه:** `https://api.telegram.org/bot{token}/{method}`.
- **سقف پیام:** ۴۰۹۶ کاراکتر. اگر متن طولانی‌تر باشد، `sendMessage()` آن را خودکار تقسیم می‌کند.
- **فایل‌ها:** `sendPhoto` و `sendDocument` یک `file_id` یا URL عمومی می‌پذیرند. آپلود فایل محلی در این کتابخانه پیاده‌سازی نشده است.
- **وبهوک:** می‌توانید `secret_token` بدهید و هدر `X-Telegram-Bot-Api-Secret-Token` را با `hash_equals()` بررسی کنید.
- **Polling:** `getUpdates()` پاکت کامل `{"ok": true, "result": [...]}` را برمی‌گرداند.
- **سرور محلی Bot API:** با گزینهٔ `base_url` قابل استفاده است. مسیر متد همچنان `/bot{token}/{method}` است.

نکتهٔ مهم: پیام‌های طولانی تقسیم می‌شوند، اما هر بخش یک پیام جداگانه است. اگر ربات شما stateful است، ترتیب و هم‌زمانی را خودتان مدیریت کنید.

---

## بله

- **محل دریافت توکن:** BotFather داخل پیام‌رسان بله. اگر حساب شما مسیر متفاوتی دارد، از پنل رسمی بله استفاده کنید.
- **آدرس پایه:** `https://tapi.bale.ai/bot{token}/{method}`.
- **سازگاری:** Bale API زیرمجموعهٔ متدهای تلگرام را با همان نام‌ها و ساختار payload ارائه می‌دهد. به همین دلیل `BaleAdapter` از منطق مشترک `AbstractAdapter` با آدرس پایهٔ متفاوت استفاده می‌کند.
- **تفاوت‌های احتمالی:** همهٔ متدهای تلگرام روی Bale تضمینی نیستند. اگر متدی پاسخ متفاوت داد، اول `base_url` و بعد `methods` را بررسی کنید.
- **وبهوک:** ثبت وبهوک با `setWebhook()` انجام می‌شود. نام هدر امنیتی را از مستندات رسمی Bale بررسی کنید؛ کد نمونهٔ README هدر تلگرام را بررسی می‌کند.
- **سقف پیام:** پیش‌فرض ۴۰۹۶. با `max_message_length` قابل تغییر است.

---

## ایتا

- **محل دریافت توکن:** پنل [EitaaYar](https://eitaayar.ir). این API ارسال‌محور است و توکن از حساب کاربری همان سرویس گرفته می‌شود.
- **آدرس پایه:** `https://eitaayar.ir/api/{token}/{method}`.
- **متدهای اصلی:** `sendMessage`، `sendFile`، `getMe`.
- **سقف پیام:** پیش‌فرض ۱۰۰۰ کاراکتر. این عدد بر اساس محدودیت عمومی سرویس انتخاب شده و قابل تنظیم است:

```php
$bot = MessengerFactory::make('eitaa', $token, [
    'max_message_length' => 900,
]);
```

- **فایل:** `sendFile($chatId, $file, $options)` که `file` می‌تواند شناسهٔ فایل یا URL عمومی باشد. `sendDocument()` هم به `sendFile` نگاشت می‌شود.
- **کیبورد شیشه‌ای:** پشتیبانی نمی‌شود. `supports('inline_keyboard')` مقدار `false` است و استفاده از `InlineKeyboard` باعث `UnsupportedFeatureException` می‌شود.
- **ویرایش/حذف/callback:** در شکل عمومی این API وجود ندارند و آداپتر آن‌ها را unsupported اعلام می‌کند.
- **Webhook و Polling:** پشتیبانی نمی‌شوند. `getUpdates()` و `setWebhook()` استثنا پرتاب می‌کنند.
- **عکس:** `sendPhoto()` unsupported است؛ از `sendFile()` استفاده کنید. ممکن است فایل به‌صورت سند تحویل داده شود، نه عکس درون‌خطی.

عدم قطعیت: نام دقیق متدها و فیلدهای EitaaYar ممکن است بین نسخه‌ها متفاوت باشد. آداپتر بر اساس شکل عمومی مستندات نوشته شده است. اگر حساب شما نام دیگری دارد:

```php
$bot = MessengerFactory::make('eitaa', $token, [
    'base_url' => 'https://eitaayar.ir/api',
    'methods' => [
        'sendMessage' => 'sendMessage',
        'getMe' => 'getMe',
    ],
]);
```

---

## روبیکا

- **محل دریافت توکن:** BotFather رسمی روبیکا. مسیر و نام دقیق آن را در پنل/مستندات روبیکا بررسی کنید.
- **آدرس پایه:** `https://botapi.rubika.ir/v3/{token}/{method}`.
- **شکل پاسخ:** پاسخ موفق معمولاً `{"status": "OK", "data": {...}}` است. آداپتر هر وضعیتی غیر از `OK` را به `ApiException` تبدیل می‌کند.
- **متدهای کلیدی:** `sendMessage`، `sendFile`، `getChats`، `getMe`، `getUpdates`.
- **شناسهٔ چت:** برای ارسال پیام باید `chat_id` را داشته باشید. `getChats()` مخصوص آداپتر روبیکا است و در اینترفیس `Messenger` نیست:

```php
use IranMessenger\Adapter\RubikaAdapter;

/** @var RubikaAdapter $bot */
$bot = MessengerFactory::make('rubika', $token);

$response = $bot->getChats();
$chats = $response['data']['chats'] ?? [];
```

- **فایل:** `sendFile($chatId, $file)` و معادل‌های `sendPhoto()` و `sendDocument()` که به `sendFile` نگاشت می‌شوند.
- **کیبورد:** روبیکا `reply_markup` ندارد. آداپتر `ReplyKeyboard` را به `chat_keypad` و `chat_keypad_type: "New"` تبدیل می‌کند. فقط متن دکمه‌ها منتقل می‌شود؛ فیلدهای اضافی مثل `request_contact` در تبدیل روبیکا حفظ نمی‌شوند. ساختار تقریبی:

```php
[
    'chat_keypad' => [
        'rows' => [
            ['buttons' => [['button_text' => 'بله'], ['button_text' => 'خیر']]],
        ],
    ],
    'chat_keypad_type' => 'New',
]
```

- **کیبورد شیشه‌ای:** در این آداپتر unsupported است.
- **ویرایش/حذف/callback:** در شکل عمومی API v3 که این آداپتر هدف گرفته، پشتیبانی نمی‌شوند.
- **Webhook:** در این آداپتر unsupported است؛ از `getUpdates()` استفاده کنید.
- **عدم قطعیت:** ممکن است نسخهٔ API حساب شما فیلدهای بیشتری برای `chat_keypad` بخواهد یا نام متدها متفاوت باشد. با `methods` می‌توانید نگاشت را تغییر دهید:

```php
$bot = MessengerFactory::make('rubika', $token, [
    'methods' => [
        'sendMessage' => 'sendMessage',
        'sendFile' => 'sendFile',
    ],
]);
```

---

## تنظیم آدرس پایه و نام متدها

هر آداپتر یک آدرس پیش‌فرض دارد، اما می‌توانید آن را در `options` عوض کنید:

```php
$bot = MessengerFactory::make('telegram', $token, [
    'base_url' => 'http://127.0.0.1:8081',
]);
```

برخی متدهای منطقی نام متفاوتی در سرویس‌دهنده دارند. مثلاً ویرایش پیام در تلگرام `editMessageText` است. برای تغییر:

```php
$bot = MessengerFactory::make('telegram', $token, [
    'methods' => [
        'editMessage' => 'editMessageText',
    ],
]);
```

این دو گزینه برای پروکسی، سرور محلی Bot API و نسخه‌های متفاوت API طراحی شده‌اند.

## آپلود فایل

در نسخهٔ فعلی همهٔ درخواست‌ها با بدنهٔ JSON ارسال می‌شوند. برای عکس و سند یکی از این‌ها را بدهید:

- `file_id` فایلی که قبلاً در همان پیام‌رسان آپلود شده است؛
- URL عمومی و مستقیم فایل.

آپلود multipart از فایل محلی پیاده‌سازی نشده است. اگر به آن نیاز دارید، یک `HttpClient` سفارشی یا PR اضافه کنید؛ اینترفیس `IranMessenger\Http\HttpClient` برای همین منظور قابل تزریق است.

## امنیت توکن

- توکن را در `.env` نگه دارید و `.env` را commit نکنید.
- اگر توکن لو رفت، فوراً از BotFather/پنل مربوطه آن را باطل و توکن جدید صادر کنید.
- `ApiException` توکن را از پیام، `__toString()`، payload و response حذف می‌کند؛ با این حال payload ممکن است داده‌های حساس دیگری داشته باشد. لاگ‌ها را محدود کنید.
- وبهوک را روی HTTPS و با بررسی secret قرار دهید.

## تست کردن بدون حساب واقعی

همهٔ آداپترها `HttpClient` را از سازنده می‌پذیرند. در تست‌ها از `IranMessenger\Tests\Fake\FakeHttpClient` استفاده شده است:

```php
use IranMessenger\Adapter\TelegramAdapter;
use IranMessenger\Tests\Fake\FakeHttpClient;

$http = new FakeHttpClient([
    FakeHttpClient::ok(['message_id' => 10]),
]);

$bot = new TelegramAdapter('test-token', $http);
$response = $bot->sendMessage(123, 'hello');
```

این کار باعث می‌شود تست‌ها بدون شبکه و بدون توکن واقعی اجرا شوند.
