# Legacy PHP -> Laravel (Current State)

Hozir loyiha yagona Laravel strukturada ishlaydi:

- `artisan`, `app/`, `routes/`, `public/` va boshqa Laravel papkalar root'da.
- Login/logout Laravel orqali ishlaydi.
- Legacy modullar Laravel ichiga ko'chirildi: `legacy/dashboard/*`.
- Static assetlar Laravel public ichida: `public/assets/*`.

## Ishga tushirish

PowerShell:

```powershell
New-Item -ItemType Directory -Force .tmp | Out-Null
C:\OSPanel\modules\php\PHP_8.1\php.exe -d sys_temp_dir="$PWD\.tmp" artisan serve
```

Brauzer: `http://127.0.0.1:8000`

## Muhim route'lar

- `/` - Laravel login sahifasi
- `/dashboard` - dashboard bosh sahifasi
- `/dashboard/{...}` - legacy dashboard modullar (Laravel route orqali)
- `/logout` - chiqish

## DB sozlama

`.env` da:

- `DB_CONNECTION=mysql`
- `DB_DATABASE=lm_db_laravel`
- `DB_USERNAME=root`
- `DB_PASSWORD=`

## Keyingi bosqich (to'liq refactor)

1. `legacy/dashboard/get|insert|api` endpointlarini alohida Laravel Controller'ga ajratish.
2. `legacy/dashboard/*.php` sahifalarni Blade view'ga bosqichma-bosqich o'tkazish.
3. `legacy/dashboard/config.php` dagi `Database` klassini Service/Repository qatlamiga bo'lish.
