# iran-messenger-bot

[English README](README.md) · [سیاست امنیتی](SECURITY.md) · [تغییرات](CHANGELOG.md)

یک کتابخانهٔ PHP مستقل از فریم‌ورک که برای ساخت ربات روی پیام‌رسان‌های ایرانی و منطقه‌ای ساخته شده است: **تلگرام، بله، ایتا و روبیکا**.

هدف این است که کد ربات شما به یک API تمیز وابسته باشد، نه به تفاوت‌های جزئی هر سرویس‌دهنده. یک `Messenger` واحد، DTOهای تغییرناپذیر (immutable) و کیبوردهای زنجیره‌ای (fluent) در اختیار شما می‌گذاریم و جزئیات هر پلتفرم داخل آداپتر همان پلتفرم می‌ماند.

> این پروژه یک کتابخانهٔ متن‌باز است، نه سرویس میزبانی‌شده. برای دریافت توکن و فعال‌سازی ربات باید در پنل رسمی هر پیام‌رسان حساب داشته باشید.

## چه مشکلی را حل می‌کند؟

اگر برای تلگرام، بله، ایتا و روبیکا ربات می‌سازید، در حالت عادی باید چهار شکل مختلف از درخواست، پاسخ، خطا و صفحه‌کلید را مدیریت کنید. با این کتابخانه:

- یک اینترفیس ثابت (`Messenger`) برای همهٔ پلتفرم‌ها دارید.
- به‌جای آرایه‌های تودرتو، از DTOهای خوانا (`Update`، `Message`، `User`، `Chat`، `CallbackQuery`، `PhotoSize`) استفاده می‌کنید.
- صفحه‌کلیدها را با کد PHP می‌سازید، نه با آرایهٔ دستی.
- خطاهای سرویس‌دهنده به `ApiException` تبدیل می‌شوند و توکن ربات در پیام خطا و لاگ‌ها حذف می‌شود.
- پیام‌های طولانی به‌صورت خودکار و بدون بریدن کلمه به چند بخش تقسیم می‌شوند.
- وابستگی اجباری به Laravel یا WordPress ندارید. اتصال اختیاری Laravel هم وجود دارد.

## پیش‌نیازها

- PHP نسخهٔ ۸.۱ یا بالاتر
- افزونه‌های `curl`، `json` و `mbstring`
- Composer

## نصب

```bash
composer require sirdadfar/iran-messenger-bot
```

اگر بسته هنوز در Packagist منتشر نشده است، می‌توانید آن را به‌صورت محلی یا از طریق یک مخزن Git نصب کنید:

```json
{
    "repositories": [
        { "type": "path", "url": "../iran-messenger-bot" }
    ],
    "require": {
        "sirdadfar/iran-messenger-bot": "*"
    }
}
```

سپس:

```bash
composer update
```

## شروع سریع

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use IranMessenger\MessengerFactory;

$bot = MessengerFactory::make('telegram', '123456:ABC-DEF...');

$bot->sendMessage(123456789, 'سلام! ربات آماده است.');
```

`MessengerFactory::make()` چهار درایور را می‌شناسد: `telegram`، `bale`، `eitaa` و `rubika`. نام درایور به حروف کوچک و بزرگ حساس نیست.

### تنظیمات اختیاری

```php
use IranMessenger\Http\CurlClient;
use IranMessenger\MessengerFactory;

$bot = MessengerFactory::make('telegram', $token, [
    // آدرس پایه؛ برای Bot API سرور محلی یا پروکسی مفید است
    'base_url' => 'https://api.telegram.org',

    // زمان‌انتظار اتصال و درخواست (ثانیه)
    'timeout' => 20,
    'connect_timeout' => 5,

    // سقف طول هر بخش پیام
    'max_message_length' => 4096,

    // نگاشت نام متدهای منطقی به متد سرویس‌دهنده
    'methods' => [
        'editMessage' => 'editMessageText',
    ],

    // تزریق یک HttpClient سفارشی (برای تست یا پروکسی)
    'http_client' => new CurlClient(20),
]);
```

## کیبوردها

### کیبورد شیشه‌ای (Inline Keyboard)

```php
use IranMessenger\Keyboard\InlineKeyboard;

$keyboard = InlineKeyboard::make()
    ->row([
        InlineKeyboard::callback('✅ تأیید', 'confirm:42'),
        InlineKeyboard::url('📚 راهنما', 'https://example.com/help'),
    ])
    ->row([
        InlineKeyboard::callback('❌ لغو', 'cancel:42'),
    ]);

$bot->sendMessage(123456789, 'سفارش خود را تأیید کنید:', [
    'keyboard' => $keyboard,
    'parse_mode' => 'HTML',
]);
```

خروجی `$keyboard->toArray()` دقیقاً همان ساختاری است که تلگرام و بله انتظار دارند:

```php
[
    'inline_keyboard' => [
        [
            ['text' => '✅ تأیید', 'callback_data' => 'confirm:42'],
            ['text' => '📚 راهنما', 'url' => 'https://example.com/help'],
        ],
        [
            ['text' => '❌ لغو', 'callback_data' => 'cancel:42'],
        ],
    ],
]
```

داده‌ی `callback_data` حداکثر ۶۴ بایت است. هر دکمه باید دقیقاً یکی از `callback_data` یا `url` را داشته باشد.

### کیبورد معمولی (Reply Keyboard)

```php
use IranMessenger\Keyboard\ReplyKeyboard;

$menu = ReplyKeyboard::make()
    ->row(['📦 سفارش‌ها', '👤 حساب من'])
    ->row([['text' => '☎️ ارسال شماره', 'request_contact' => true]])
    ->resize()
    ->oneTime()
    ->selective();

$bot->sendMessage(123456789, 'یک گزینه را انتخاب کنید:', [
    'keyboard' => $menu,
]);
```

`resize()`، `oneTime()` و `selective()` پرچم‌های مربوطه را فعال می‌کنند. برای غیرفعال کردن دوباره، همان متد را با `false` صدا بزنید: `->resize(false)`.

هر دو کلاس کیبورد تغییرناپذیر هستند؛ هر متد یک نمونهٔ جدید برمی‌گرداند و نمونهٔ قبلی دست‌نخورده می‌ماند. این رفتار برای ساخت منوهای مشترک و امن در چند درخواست مفید است.

## شروع سریع برای هر پیام‌رسان

### تلگرام

```php
$bot = MessengerFactory::make('telegram', $token);

$bot->sendMessage($chatId, 'سلام از تلگرام');
$bot->sendPhoto($chatId, 'https://example.com/photo.jpg', ['caption' => 'عکس']);
$bot->sendDocument($chatId, 'https://example.com/report.pdf');
$bot->editMessage($chatId, $messageId, 'متن ویرایش‌شده');
$bot->deleteMessage($chatId, $messageId);
```

آدرس پایه: `https://api.telegram.org/bot{token}/{method}`. توکن را از [@BotFather](https://t.me/BotFather) بگیرید.

### بله

```php
$bot = MessengerFactory::make('bale', $token);

$bot->sendMessage($chatId, 'سلام از بله');
$bot->sendPhoto($chatId, 'https://example.com/photo.jpg');
```

آدرس پایه: `https://tapi.bale.ai/bot{token}/{method}`. زیرمجموعهٔ متدهای سازگار با تلگرام پشتیبانی می‌شود. توکن را از BotFather داخل بله دریافت کنید.

### ایتا

```php
use IranMessenger\Adapter\EitaaAdapter;

/** @var EitaaAdapter $bot */
$bot = MessengerFactory::make('eitaa', $token);

$bot->sendMessage($chatId, 'سلام از ایتا');
$bot->sendFile($chatId, 'https://example.com/report.pdf', ['caption' => 'گزارش']);

// معادل sendFile:
$bot->sendDocument($chatId, 'https://example.com/report.pdf');
```

آدرس پایه: `https://eitaayar.ir/api/{token}/{method}`. ایتا API محدودی دارد و کیبورد شیشه‌ای، ویرایش، حذف، callback و webhook در آن پشتیبانی نمی‌شود. برای همین `supports('inline_keyboard')` مقدار `false` برمی‌گرداند و در صورت استفاده استثنا پرتاب می‌شود.

### روبیکا

```php
use IranMessenger\Adapter\RubikaAdapter;

/** @var RubikaAdapter $bot */
$bot = MessengerFactory::make('rubika', $token);

$chats = $bot->getChats(); // متد مخصوص روبیکا
$bot->sendMessage($chatId, 'سلام از روبیکا');
$bot->sendFile($chatId, 'https://example.com/file.pdf');
```

آدرس پایه: `https://botapi.rubika.ir/v3/{token}/{method}`. روبیکا به‌جای `reply_markup` از `chat_keypad` و `chat_keypad_type` استفاده می‌کند؛ آداپتر این تبدیل را انجام می‌دهد. کیبورد شیشه‌ای در شکل عمومی API روبیکا وجود ندارد.

> نام متدها و ساختار دقیق `chat_keypad` ممکن است بین نسخه‌های API روبیکا تغییر کند. آداپتر بر اساس شکل عمومی API v3 نوشته شده است. قبل از استفادهٔ عملیاتی، `base_url` و در صورت نیاز `methods` را با حساب خودتان بررسی کنید.

## وبهوک (Webhook)

نمونهٔ یک فایل ورودی وبهوک برای تلگرام:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use IranMessenger\Exception\ValidationException;
use IranMessenger\MessengerFactory;
use IranMessenger\Support\Webhook;

$bot = MessengerFactory::make('telegram', (string) getenv('TELEGRAM_BOT_TOKEN'));

// ۱) تأیید هدر امنیتی وبهوک، پیش از خواندن و تفسیر بدنه.
//    مقایسه در زمان ثابت (hash_equals) و هدر با هر یک از شکل‌های
//    HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN یا x-telegram-bot-api-secret-token پیدا می‌شود.
$secret = (string) getenv('TELEGRAM_WEBHOOK_SECRET');

if ($secret !== '' && !Webhook::verifyServerHeader($_SERVER, $secret)) {
    http_response_code(403);
    exit;
}

// ۲) خواندن بدنه با سقف ۱ مگابایت و JSON اجباری.
try {
    $update = Webhook::update((string) file_get_contents('php://input'));
} catch (ValidationException) {
    http_response_code(400);
    exit;
}

if ($update->message !== null && $update->message->text === '/start') {
    $bot->sendMessage($update->message->chat->id, 'سلام! خوش آمدید.');
}

if ($update->callbackQuery !== null) {
    $callback = $update->callbackQuery;

    $bot->answerCallbackQuery($callback->id, ['text' => 'ثبت شد ✅']);

    if ($callback->message !== null) {
        $bot->editMessage(
            $callback->message->chat->id,
            $callback->message->messageId,
            'تأیید شد ✅',
        );
    }
}

http_response_code(200);
```

ثبت وبهوک:

```php
$bot->setWebhook('https://example.com/bot/webhook.php', [
    'secret_token' => $webhookSecret,
    'allowed_updates' => ['message', 'callback_query'],
]);
```

حذف وبهوک:

```php
$bot->deleteWebhook(['drop_pending_updates' => true]);
```

> آدرس وبهوک باید عمومی و HTTPS باشد. برای Bale هدر امنیتی ممکن است متفاوت باشد؛ آن را مطابق مستندات رسمی همان سرویس بررسی کنید.

### تولید secret و محدودیت‌ها

```php
// ۳۲ بایت تصادفی، خروجی URL-safe و سازگار با secret_token تلگرام
$webhookSecret = IranMessenger\Support\Webhook::generateSecret();
```

- `Webhook::verifyServerHeader($_SERVER, $secret)` مقدار خالی یا نادرست را رد می‌کند.
- `Webhook::decode()` بدنهٔ بزرگ‌تر از ۱ مگابایت، JSON نامعتبر و بدنهٔ غیرآبجکت (مثل رشته یا عدد) را با `ValidationException` رد می‌کند.
- `Webhook::update()` همان دو مرحله را یک‌جا انجام می‌دهد.
- برای سرویس‌هایی که هدر امنیتی ندارند، یک توکن تصادفی در مسیر URL بگذارید و همان را با `Webhook::verify()` مقایسه کنید؛ این کتابخانه نمی‌تواند امضایی بسازد که سرویس ارسال نمی‌کند.

## دریافت پیام با Polling

```php
$offset = 0;

while (true) {
    $response = $bot->getUpdates([
        'offset' => $offset,
        'timeout' => 30,
        'allowed_updates' => ['message', 'callback_query'],
    ]);

    foreach ($response['result'] ?? [] as $rawUpdate) {
        $update = $bot->parseUpdate($rawUpdate);

        if ($update->message?->isCommand()) {
            $bot->sendMessage($update->message->chat->id, 'دستور دریافت شد.');
        }

        $offset = $update->updateId + 1;
    }
}
```

`getUpdates()` پاکت کامل پاسخ سرویس‌دهنده را برمی‌گرداند؛ نتیجهٔ به‌روزرسانی‌ها در کلید `result` قرار دارد. برای Eitaa و Rubika وضعیت polling را در جدول پشتیبانی ببینید.

## پیام‌های طولانی

سرویس‌دهنده‌ها سقف طول پیام دارند. `sendMessage()` متن را خودکار به بخش‌های مجاز تقسیم می‌کند، ترجیحاً از محل خط جدید یا فاصله می‌شکند و فقط در صورت نبود فاصله، برش سخت می‌زند. کیبورد و `reply_to_message_id` فقط روی بخش اول قرار می‌گیرد.

```php
$responses = $bot->sendMessageChunked($chatId, $longText);
// همهٔ پاسخ‌ها

$first = $bot->sendMessage($chatId, $longText);
// پاسخ بخش اول؛ همهٔ بخش‌ها ارسال می‌شوند
```

اگر می‌خواهید فقط تقسیم‌بندی را ببینید:

```php
$chunks = $bot->splitMessage($longText);
$chunks = $bot->splitMessage($longText, 1000); // سقف دلخواه
```

سقف پیش‌فرض: تلگرام و بله ۴۰۹۶ کاراکتر، ایتا ۱۰۰۰ کاراکتر (قابل تنظیم) و روبیکا ۴۰۹۶ کاراکتر.

## خطاها و امنیت

```php
use IranMessenger\Exception\ApiException;
use IranMessenger\Exception\NetworkException;
use IranMessenger\Exception\UnsupportedFeatureException;
use IranMessenger\Exception\ValidationException;

try {
    $bot->sendMessage($chatId, 'سلام');
} catch (ApiException $exception) {
    // message و __toString هر دو توکن را حذف کرده‌اند
    logger()->error((string) $exception, [
        'method' => $exception->getMethod(),
        'http_status' => $exception->getHttpStatus(),
        'provider_code' => $exception->getProviderCode(),
    ]);
} catch (ValidationException $exception) {
    // ورودی نامعتبر است و هیچ درخواستی ارسال نشده
} catch (NetworkException $exception) {
    // خطای شبکه یا cURL؛ آدرس حاوی توکن در پیام قرار نمی‌گیرد
} catch (UnsupportedFeatureException $exception) {
    // مثلاً inline keyboard روی ایتا
}
```

- `ApiException` برای پاسخ غیر ۲xx، پاکت `{"ok": false}` یا وضعیت ناموفق روبیکا پرتاب می‌شود و متد، payload و پاسخ سرویس‌دهنده را در اختیار شما می‌گذارد.
- `ValidationException` قبل از ارسال درخواست پرتاب می‌شود: توکن خالی، متن خالی، شناسهٔ صفر، URL نامعتبر و مانند آن.
- `UnsupportedFeatureException` وقتی پرتاب می‌شود که قابلیت موردنظر روی آن پلتفرم وجود ندارد.
- توکن ربات در پیام استثنا، `__toString()`، payload ذخیره‌شده و پاسخ ذخیره‌شده حذف می‌شود؛ خطای cURL (که می‌تواند آدرس حاوی توکن را داشته باشد) هم پاک‌سازی می‌شود.
- `CurlClient` تأیید گواهی و نام میزبان TLS را صریحاً فعال می‌کند، redirect را دنبال نمی‌کند و فقط `http`/`https` را می‌پذیرد.
- `base_url` باید URL مطلق باشد و نام کاربری/رمز داخل آن مجاز نیست؛ بازنویسی نام متد (`methods`) فقط شناسهٔ حروف‌وعدد می‌پذیرد تا درخواست از مسیر `/bot{token}/` خارج نشود.
- وبهوک: `Webhook::verifyServerHeader()` با `hash_equals` مقایسه می‌کند و `Webhook::decode()` بدنهٔ بیش از ۱ مگابایت یا JSON غیرآبجکت را رد می‌کند. جزئیات در [SECURITY.md](SECURITY.md).

**هرگز توکن را در کد، مخزن Git یا خروجی مرورگر قرار ندهید.** از متغیر محیطی استفاده کنید:

```bash
export TELEGRAM_BOT_TOKEN="123456:ABC..."
```

و در production مطمئن شوید که خطاها و لاگ‌ها فقط برای افراد مجاز قابل مشاهده‌اند.

## اتصال اختیاری به Laravel

کتابخانه هیچ وابستگی به Laravel ندارد. اگر `illuminate/support` نصب باشد، Service Provider و Facade اختیاری در دسترس هستند و Composer آن‌ها را به‌صورت خودکار کشف می‌کند.

```bash
php artisan vendor:publish --tag=iran-messenger-config
```

`.env`:

```dotenv
IRAN_MESSENGER_DRIVER=telegram
IRAN_MESSENGER_TOKEN=123456:ABC...
```

استفاده با Facade:

```php
use IranMessenger\Laravel\Messenger;

Messenger::sendMessage($chatId, 'سلام از Laravel');
```

یا تزریق اینترفیس:

```php
use IranMessenger\Messenger as Bot;

final class WelcomeController
{
    public function __construct(private readonly Bot $bot)
    {
    }

    public function __invoke(): void
    {
        $this->bot->sendMessage(123456789, 'خوش آمدید');
    }
}
```

اگر کشف خودکار غیرفعال است، `IranMessenger\Laravel\MessengerServiceProvider` را دستی در `config/app.php` ثبت کنید و alias `IranMessenger` را به `IranMessenger\Laravel\Messenger` اختصاص دهید.

## ماتریس پشتیبانی

| قابلیت | تلگرام | بله | ایتا | روبیکا |
| --- | --- | --- | --- | --- |
| `sendMessage` | ✅ | ✅ | ✅ | ✅ |
| `sendPhoto` | ✅ | ✅ | ❌ (از `sendFile` استفاده کنید) | ✅ (از طریق `sendFile`) |
| `sendDocument` | ✅ | ✅ | ✅ (از طریق `sendFile`) | ✅ (از طریق `sendFile`) |
| کیبورد شیشه‌ای | ✅ | ✅ | ❌ | ❌ |
| کیبورد معمولی | ✅ | ✅ | ❌ | ✅ (به‌صورت `chat_keypad`) |
| `editMessage` | ✅ | ✅ | ❌ | ❌ |
| `deleteMessage` | ✅ | ✅ | ❌ | ❌ |
| `answerCallbackQuery` | ✅ | ✅ | ❌ | ❌ |
| Webhook | ✅ | ✅ | ❌ | ❌ |
| Polling (`getUpdates`) | ✅ | ✅ | ❌ | ✅ |
| `getMe` | ✅ | ✅ | ✅ | ✅ |
| `getChats` | ❌ | ❌ | ❌ | ✅ (متد مخصوص آداپتر) |

برای بررسی در کد:

```php
$bot->supports('inline_keyboard'); // true یا false
```

## وضعیت و محدودیت‌ها

این کتابخانه با تست‌های واحد و یک `HttpClient` جعلی آزمایش شده است؛ **هیچ تستی روی حساب واقعی چهار سرویس انجام نشده** و ادعای تأیید عملیاتی نداریم.

- **آپلود فایل محلی پیاده‌سازی نشده است.** همهٔ درخواست‌ها JSON هستند؛ برای `sendPhoto` و `sendDocument` یک `file_id` موجود یا URL عمومی بدهید. ارسال multipart در نسخهٔ فعلی وجود ندارد.
- API ایتا (EitaaYar) ارسال‌محور است. نام متدها و آدرس پایه بر اساس شکل عمومی مستندات آن انتخاب شده‌اند؛ اگر حساب شما نسخهٔ دیگری دارد، با `base_url` و `methods` تنظیم کنید.
- ساختار `chat_keypad` روبیکا بر اساس شکل عمومی API v3 پیاده شده است. ممکن است نسخهٔ حساب شما فیلدهای بیشتری بخواهد.
- Bale زیرمجموعهٔ سازگار با تلگرام را پوشش می‌دهد؛ برخی متدهای تلگرام ممکن است روی Bale وجود نداشته باشند.
- آلبوم/مدیا گروپ، آپلود هم‌زمان، صف و retry خودکار پیاده‌سازی نشده‌اند. پاسخ `429` به‌صورت `ApiException` برمی‌گردد؛ backoff را در برنامهٔ خودتان اعمال کنید (کتابخانه خودش retry نمی‌کند تا پیام دوبار ارسال نشود).
- هر آپدیت ممکن است دوبار تحویل شود؛ بر اساس `Update::$updateId` در سمت شما deduplicate کنید.
- طول پیام با `mb_strlen` (کدپوینت یونیکد) سنجیده می‌شود. برخی سرویس‌دهنده‌ها ایموجی را دو واحد حساب می‌کنند؛ برای متن پرایموجی سقف کمتری تنظیم کنید.
- آداپترهای Eitaa و Rubika در حالت واقعی تست نشده‌اند؛ برای production ابتدا یک محیط آزمایشی بگیرید.

اگر برای هر پلتفرم رفتار متفاوتی دیدید، `base_url` و `methods` اولین نقاط تنظیم هستند. مستندات جزئی‌تر: [docs/platforms-fa.md](docs/platforms-fa.md).

## اجرای تست‌ها

```bash
composer install
vendor/bin/phpunit
```

وضعیت فعلی: **۹۰ تست و ۳۶۹ assertion**، همه سبز. lint همهٔ فایل‌های PHP، `composer validate --strict` و `composer audit` هم در CI اجرا می‌شوند (ماتریس PHP 8.1 تا 8.4).

## مشارکت

- issue یا pull request با توضیح رفتار واقعی سرویس‌دهنده بفرستید.
- برای تغییرات API، تست و مستندات فارسی/انگلیسی را با هم به‌روز کنید.
- توکن واقعی، شماره تلفن یا دادهٔ کاربران را در issue قرار ندهید.

## مجوز

MIT © 2026 sirdadfar. جزئیات در [LICENSE](LICENSE).
