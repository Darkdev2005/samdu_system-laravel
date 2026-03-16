# O'quv Jarayoni Boshqaruvi

## Foydalanuvchi yo'riqnomasi

Ushbu hujjat `O'quv Jarayoni Boshqaruvi` tizimi uchun amaliy foydalanuvchi yo'riqnomasi hisoblanadi. Hujjat administrator, metodist, dekanat xodimi, kafedra mas'uli va o'quv yuklama bilan ishlovchi foydalanuvchilar uchun yozilgan. Maqsad faqat menyularni sanab o'tish emas, balki tizimdagi ish jarayonini boshidan oxirigacha yagona standart bo'yicha yuritishdir.

Hujjat quyidagi vazifalarni bajaradi:
1. Har bir bo'limning vazifasini tushuntiradi.
2. Har bir sahifadagi maydon, tugma va natijani izohlaydi.
3. Ish ketma-ketligini noto'g'ri buzmaslik uchun bog'liqliklarni ko'rsatadi.
4. Validatsiya va biznes qoidalarni ochib beradi.
5. Qo'lda bajariladigan test ssenariylarini beradi.
6. Word va PDF tayyorlash uchun manba sifatida xizmat qiladi.

## Hujjatdan foydalanish tartibi

1. Avval `Tavsiya etilgan ish ketma-ketligi` bo'limini o'qing.
2. Keyin aynan ishlayotgan modul bo'limiga o'ting.
3. `Oldindan tayyor bo'lishi kerak` qismiga e'tibor bering.
4. Ma'lumot kiritgandan keyin `Test ssenariylari` bo'yicha natijani tasdiqlang.
5. Yangi xodimlarni o'qitishda shu hujjatdan chek-list sifatida foydalaning.

## Terminlar va qisqartmalar

- `Yo'nalish` - ta'lim yo'nalishi.
- `Semestr` - yo'nalishning aniq semestri.
- `Fan` - o'quv rejadagi akademik fan.
- `Bazaviy fan` - keyingi variant yoki biriktirish uchun asos bo'ladigan fan.
- `Variant fan` - bazaviy fanning alohida ko'rinishi.
- `Ishchi reja` - bazaviy fan bilan unga bog'langan variant fanlar to'plami.
- `Qo'shimcha reja` - qo'shimcha dars yoki maxsus hisob-kitoblar bloki.
- `O'quv haftaligi` - haftalar kesimidagi akademik kalendar.
- `Yuklama` - fan, guruh, semestr va dars turi bo'yicha shakllangan ish hajmi.
- `Taqsimot` - yuklamani o'qituvchilarga taqsimlash jarayoni.

## Tavsiya etilgan ish ketma-ketligi

1. Login orqali tizimga kiring.
2. `Sozlamalar` bo'limidagi kataloglarni to'ldiring.
3. `Yo'nalishlar` yaratilgach `Semestrlar`ni avtomatik yarating.
4. `Guruhlar` va `O'qituvchilar`ni kiriting.
5. `O'quv reja yaratish` orqali bazaviy fanlarni yarating.
6. Zarur bo'lsa `Tanlov fan`, `Birlashtiriladigan fan`, `Chet tili`, `Qo'shimcha reja` modullarini to'ldiring.
7. `O'quv haftaligini yaratish` orqali akademik kalendarni belgilang.
8. `O'quv yuklama` bo'limida hisoblangan natijalarni tekshiring.
9. `O'quv taqsimot` bo'limida yuklamani o'qituvchilarga taqsimlang.
10. `O'qituvchilar soat taqsimoti` va `O'qituvchilar bildirgisi` orqali yakuniy nazoratni bajaring.

---

## 1. Tizimga kirish va sessiya boshqaruvi

### 1.1. Login sahifasi

**Menyu yoki URL:** `/login`  
**Maqsad:** tizimga autentifikatsiyadan o'tib kirish.

**Sahifada nimalar bor**
- `Username` maydoni.
- `Password` maydoni.
- `Remember me` belgisi.
- `Log in` tugmasi.
- Parolni unutgan foydalanuvchi uchun havola.

**Foydalanish tartibi**
1. Brauzerda login sahifasini oching.
2. Username ni kiriting.
3. Password ni kiriting.
4. Zarur bo'lsa `Remember me` ni belgilang.
5. `Log in` tugmasini bosing.
6. Tizim foydalanuvchini dashboardga o'tkazishi kerak.

**Validatsiya va qoidalar**
- Username bo'sh bo'lmasligi kerak.
- Password bo'sh bo'lmasligi kerak.
- Noto'g'ri ma'lumotda foydalanuvchi himoyalangan bo'limga kirmasligi kerak.
- Eskirgan formani yuborishda `419 Page Expired` chiqishi mumkin; bunday holatda sahifani yangilab qayta yuborish kerak.

**Kutiladigan natija**
- To'g'ri login/parol bilan foydalanuvchi ichki bo'limlarga kiradi.
- Noto'g'ri login/parolda xatolik xabari ko'rsatiladi.

**Test ssenariylari**
1. To'g'ri login va parol bilan kirib ko'ring. Natija: dashboard ochilishi kerak.
2. Noto'g'ri parol bilan kirib ko'ring. Natija: xatolik xabari chiqishi kerak.
3. Login sahifasini uzoq vaqt ochiq qoldirib formani yuboring. Natija: eskirgan token holati aniqlanishi mumkin.
4. Incognito oynada alohida login oqimini tekshiring.

### 1.2. Chiqish

**Menyu:** sidebar pastidagi `Chiqish`  
**Maqsad:** foydalanuvchi sessiyasini yakunlash.

**Foydalanish tartibi**
1. Sidebar pastiga o'ting.
2. `Chiqish` tugmasini bosing.
3. Tizim foydalanuvchini login sahifasiga qaytarishi kerak.

**Test ssenariylari**
1. Chiqishdan keyin `dashboard/index.php` ni ochib ko'ring. Natija: qayta login talab qilinishi kerak.
2. Brauzer `Back` tugmasi orqali protected sahifaga qaytib ko'ring. Natija: to'g'ridan-to'g'ri ishlamasligi kerak.

### 1.3. Sessiya bilan ishlash bo'yicha eslatma

- Eski cookie yoki eski tab bilan ishlaganda `419` holati chiqishi mumkin.
- Brauzerda juda ko'p eski ochiq tab bo'lsa, yangi konfiguratsiyadan keyin login sahifasini yangilash kerak.
- Protected URL lar login bo'lmagan foydalanuvchini avtomatik `/login` sahifasiga qaytaradi.

---

## 2. Dashboard

**Menyu:** `Dashboard`  
**Maqsad:** tizimning umumiy statistik ko'rsatkichlari va tezkor navigatsiyasi.

**Sahifada nimalar bor**
- Asosiy bo'limlar bo'yicha ko'rsatkich kartalari.
- Umumiy sonlar.
- Sidebar orqali barcha modullarga tez o'tish.

**Foydalanish tartibi**
1. Login bo'lgach dashboardni oching.
2. Ko'rsatkichlarni tekshiring.
3. Zarur bo'limga kartadan yoki sidebardan o'ting.
4. Sonlar kutilgan natijaga mos kelmasa tegishli modulni tekshiring.

**Test ssenariylari**
1. Dashboard barcha asosiy bo'limlarga olib borishi kerak.
2. Statistikadagi sonlar bazadagi yozuvlar bilan mos bo'lishi kerak.

---

## 3. Sozlamalar bo'limi

`Sozlamalar` bo'limi butun tizim uchun katalog bazasini tayyorlaydi. Bu yerda xato qilingan yozuv keyingi barcha modullarda takrorlanadi. Shuning uchun kataloglar bilan ishlashda nomlash standarti va bog'liqliklar nazorat qilinadi.

### 3.1. Fakultetlar

**Menyu:** `Sozlamalar -> Fakultetlar`  
**Maqsad:** fakultetlar katalogini yuritish.

**Asosiy maydonlar**
- `Fakultet nomi`.

**Foydalanish tartibi**
1. Fakultetlar sahifasiga kiring.
2. Fakultet nomini kiriting.
3. `Saqlash` tugmasini bosing.
4. Jadvalda yozuv paydo bo'lganini tekshiring.
5. Zarur bo'lsa tahrirlash yoki o'chirish amallarini bajaring.

**Validatsiya va qoidalar**
- Bo'sh nom saqlanmasligi kerak.
- Takror yozuvlar nazorat qilinishi kerak.
- Bog'langan obyektlar bo'lsa o'chirishdan oldin ehtiyot bo'lish kerak.

**Test ssenariylari**
1. Yangi fakultet yarating.
2. Fakultet nomini tahrirlang.
3. Fakultet select ishlatiladigan joylarda chiqishini tekshiring.

### 3.2. Akademik darajalar

**Menyu:** `Sozlamalar -> Akademik darajalar`  
**Maqsad:** bakalavr, magistr kabi akademik darajalarni yuritish.

**Asosiy maydonlar**
- `Daraja nomi`.
- `Qisqa nom` yoki tizimdagi ko'rinish nomi.

**Foydalanish tartibi**
1. Yangi daraja kiriting.
2. Saqlang.
3. Yo'nalish yaratish formasida chiqishini tekshiring.

**Test ssenariylari**
1. Yangi daraja yarating.
2. Tahrirlashni tekshiring.
3. Selectlarda yangilanishini tekshiring.

### 3.3. Ta'lim shakllari

**Menyu:** `Sozlamalar -> Ta'lim shakllar`  
**Maqsad:** kunduzgi, sirtqi, kechki va boshqa shakllarni yuritish.

**Test ssenariylari**
1. Yangi ta'lim shakli yarating.
2. Yo'nalish formasida chiqishini tekshiring.
3. Tahrir va o'chirishni tekshiring.

### 3.4. Yo'nalishlar

**Menyu:** `Sozlamalar -> Yo'nalishlar`  
**Maqsad:** ta'lim yo'nalishlarini to'liq parametrlar bilan yaratish va boshqarish.

**Oldindan tayyor bo'lishi kerak**
- Fakultetlar.
- Akademik darajalar.
- Ta'lim shakllari.

**Sahifa tarkibi**
- `Yo'nalish qo'shish` tugmasi.
- Yo'nalishlar jadvali.
- Qidiruv maydoni.
- `Yo'nalish tahrirlar tarixi` jadvali.
- Modal forma.

**Asosiy maydonlar**
- `Yo'nalish nomi`.
- `Yo'nalish kodi`.
- `Ta'lim muddati (yil)`.
- `Kirish yili`.
- `Patok soni`.
- `Katta guruh soni`.
- `Kichik guruh soni`.
- `Akademik daraja`.
- `Ta'lim shakli`.
- `Kvalifikatsiya`.
- `Fakultet`.

**Foydalanish tartibi**
1. `Yo'nalish qo'shish` tugmasini bosing.
2. Barcha maydonlarni to'ldiring.
3. `Patok`, `Katta guruh`, `Kichik guruh` sonlarini real o'quv jarayoniga mos kiriting.
4. Akademik daraja, ta'lim shakli va fakultetni tanlang.
5. Saqlang.
6. Jadvalda yozuv chiqqanini tekshiring.
7. Zarur bo'lsa tahrir qiling va tarix jadvaliga qarang.

**Validatsiya va qoidalar**
- Majburiy maydonlar bo'sh bo'lmasligi kerak.
- `Kirish yili` va `Ta'lim muddati` mantiqan to'g'ri bo'lishi kerak.
- Patok va guruh sonlari `1` dan kichik bo'lmasligi kerak.
- Tahrirlar tarixga yozilishi kerak.

**Bog'liqliklar**
- Semestr yaratish shu yozuvlarga tayanadi.
- Guruhlar shu yo'nalish bilan bog'lanadi.
- O'quv reja va haftaliklar shu yo'nalishdan foydalanadi.

**Test ssenariylari**
1. Yangi yo'nalish yarating.
2. Yo'nalishni tahrirlab tarix jadvalini tekshiring.
3. Yaratilgan yo'nalish keyingi modullarda chiqishini tekshiring.

### 3.5. Kafedralar

**Menyu:** `Sozlamalar -> Kafedralar`  
**Maqsad:** fan va o'qituvchilarni bog'lash uchun kafedralar katalogini yuritish.

**Foydalanish tartibi**
1. Yangi kafedra qo'shing.
2. Saqlang.
3. Fan yaratish va o'qituvchi formalarida selectga tushishini tekshiring.

**Test ssenariylari**
1. Kafedra qo'shing.
2. Kafedra nomini tahrirlang.
3. Bog'liq fanlar bo'lsa o'chirish holatini tekshiring.

### 3.6. O'quv shakllar

**Menyu:** `Sozlamalar -> O'quv shakllar`  
**Maqsad:** ichki o'quv shakli kataloglarini yuritish.

**Amaliy tavsiya**
- Nomlash bir xil standartda bo'lsin.
- Keyingi modullarda ishlatiladigan qiymatlarni tartibli yuriting.

**Test ssenariylari**
1. Yozuv qo'shing.
2. Select ishlatiladigan joylarda chiqishini tekshiring.
3. Tahrir va o'chirishni tekshiring.

### 3.7. Dars soat turlari

**Menyu:** `Sozlamalar -> Dars soat turlar`  
**Maqsad:** ma'ruza, amaliyot, laboratoriya kabi soat turlarini yuritish.

**Nega bu bo'lim muhim**
- O'quv reja yaratishda har bir fan uchun shu katalogdan dars turi tanlanadi.
- Qo'shimcha reja va yuklama hisoblari shu qiymatlarga tayanadi.

**Test ssenariylari**
1. `Ma'ruza`, `Amaliyot`, `Laboratoriya` kabi yozuvlar yarating.
2. O'quv reja yaratish formasida selectda chiqishini tekshiring.

### 3.8. Semestrlar

**Menyu:** `Sozlamalar -> Semestrlar`  
**Maqsad:** yo'nalishlar asosida semestrlarni avtomatik yaratish va boshqarish.

**Sahifa tarkibi**
- `Semestrlarni avtomatik yaratish` tugmasi.
- Semestrlar jadvali.
- Qidiruv maydoni.
- Tahrirlash va o'chirish amallari.

**Foydalanish tartibi**
1. Yo'nalishlar tayyor bo'lgach semestrlar sahifasiga kiring.
2. `Semestrlarni avtomatik yaratish` tugmasini bosing.
3. Tizim ta'lim muddati asosida semestrlarni hosil qiladi.
4. Jadvalda fakultet, yo'nalish va semestrlar to'g'riligini tekshiring.
5. Zarur bo'lsa semestr raqamini tahrirlang.

**Test ssenariylari**
1. Yangi yo'nalishdan keyin avtomatik yaratishni ishlating.
2. Tahrirlash va qidiruvni tekshiring.

### 3.9. Guruhlar

**Menyu:** `Sozlamalar -> Guruhlar`  
**Maqsad:** yo'nalishlarga bog'langan guruhlar va ulardagi talaba sonini yuritish.

**Sahifa tarkibi**
- `Guruh qo'shish` tugmasi.
- Guruhlar jadvali.
- Qidiruv maydoni.
- `Guruh tahrirlar tarixi` jadvali.
- Modal forma.

**Asosiy maydonlar**
- `Yo'nalish`.
- `Guruh nomeri`.
- `Talaba soni`.

**Foydalanish tartibi**
1. `Guruh qo'shish` ni bosing.
2. Yo'nalishni tanlang.
3. Guruh nomini kiriting.
4. Talaba sonini kiriting.
5. Saqlang.
6. Jadval va tarixni tekshiring.

**Nima uchun muhim**
- Chet tili taqsimoti guruhlar kesimida ishlaydi.
- Yuklama hisoblari guruh soni va talabalar soniga tayanadi.

**Test ssenariylari**
1. Bitta yo'nalish uchun bir nechta guruh yarating.
2. Turli talaba sonlari bilan yozuv kiriting.
3. Tahrir qiling va tarix yozuvini tekshiring.

### 3.10. O'quv haftaligi turlari

**Menyu:** `Sozlamalar -> O'quv haftalik turlar`  
**Maqsad:** akademik kalendarda ishlatiladigan hafta turlari katalogini yuritish.

**Test ssenariylari**
1. Qisqa nom va to'liq nom bilan hafta turi yarating.
2. O'quv haftaligi yaratish sahifasidagi select va legendada chiqishini tekshiring.

### 3.11. Qo'shimcha dars turlari

**Menyu:** `Sozlamalar -> Qo'shimcha dars turlar`  
**Maqsad:** qo'shimcha reja hisoblarida ishlatiladigan dars turlarini va koeffitsientlarni yuritish.

**Test ssenariylari**
1. Qo'shimcha dars turi yarating.
2. Koeffitsient bilan saqlang.
3. Qo'shimcha o'quv reja yaratish sahifasida ta'sirini tekshiring.

### 3.12. O'qituvchilar

**Menyu:** `Sozlamalar -> O'qituvchilar`  
**Maqsad:** o'qituvchilar katalogi va shtat parametrlarini yuritish.

**Oldindan tayyor bo'lishi kerak**
- Fakultetlar.
- Kafedralar.
- Ilmiy unvonlar.
- Ilmiy darajalar.
- Ish turlari.

**Sahifa tarkibi**
- `Fakultet` va `Kafedra` filterlari.
- Qidiruv maydoni.
- O'qituvchilar jadvali.
- Modal forma.

**Asosiy maydonlar**
- `Fakultet`.
- `Kafedra`.
- `F.I.O`.
- `Lavozim`.
- `Shtat birligi`.
- `Shtat turi`.
- `Ilmiy unvon`.
- `Ilmiy daraja`.

**Foydalanish tartibi**
1. `O'qituvchi qo'shish` tugmasini bosing.
2. Fakultet va kafedrani tanlang.
3. F.I.O va lavozimni kiriting.
4. Shtat birligi va shtat turini tanlang.
5. Ilmiy unvon va darajani tanlang.
6. Saqlang.

**Test ssenariylari**
1. O'qituvchi yarating.
2. Filterlar ishlashini tekshiring.
3. Keyin taqsimot modalida shu o'qituvchi chiqishini tekshiring.

---

## 4. O'quv reja bo'limi

Bu bo'lim tizimning markaziy qismi. Fanlar, variant fanlar, birlashtirishlar, chet tili taqsimoti va haftaliklar shu yerda tayyorlanadi. Ketma-ketlik buzilsa keyingi `Yuklama` va `Taqsimot` modullari bo'sh yoki noto'g'ri chiqadi.

### 4.1. O'quv reja yaratish

**Menyu:** `O'quv reja -> O'quv reja yaratish`  
**Maqsad:** semestr bo'yicha bazaviy fanlarni yaratish va dars soatlarini belgilash.

**Oldindan tayyor bo'lishi kerak**
- Fakultetlar.
- Semestrlar.
- Kafedralar.
- Dars soat turlari.

**Sahifa tarkibi**
- `Fakultet filtri`.
- `Semestr` selecti.
- Fan kartalari.
- Fan turi tugmalari: `Majburiy fan`, `Tanlov fan`, `Chet tili`, `Birlashtiriladigan fan`.
- `Fan kodi`.
- `Fan nomi`.
- `Kafedra`.
- `Dars turi`.
- `Dars soati`.
- `Yana fan` va `O'chirish`.
- `Izoh`.
- `Saqlash`.
- `Yaratilgan fanlar ro'yxati`.
- Ro'yxatda `Yangilash` va `Tahrirlash`.

**Fan turi mantig'i**
- `Majburiy fan` - oddiy bazaviy fan.
- `Tanlov fan` - keyin variant fanlar yaratiladigan baza.
- `Chet tili` - keyin guruhlar kesimida tillarga bo'linadigan baza.
- `Birlashtiriladigan fan` - turli yo'nalish/semestrlar uchun umumiy birlashtirish bazasi.

**Foydalanish tartibi**
1. Fakultet filtrini tanlang.
2. Kerakli semestrni tanlang.
3. Fan turi tugmasidan mos turini belgilang.
4. `Fan kodi` va `Fan nomi`ni kiriting.
5. Kafedrani tanlang.
6. Kamida bitta `Dars turi + Dars soati` qatorini kiriting.
7. Zarur bo'lsa `Yana fan` bilan qo'shimcha kartalar qo'shing.
8. `Saqlash` tugmasini bosing.
9. Pastdagi ro'yxatda yozuvni tekshiring.
10. Tahrir kerak bo'lsa ro'yxatdan `Tahrirlash`ni ishlating.

**Validatsiya va qoidalar**
- Semestr tanlanishi shart.
- Fan kodi va fan nomi bo'sh bo'lmasligi kerak.
- Kamida bitta dars turi bo'lishi kerak.
- Dars soati manfiy bo'lmasligi kerak.
- Kamida bitta soat `0` dan katta bo'lishi kerak.

**Natija**
- Fan katalogga yoziladi.
- Dars soatlari o'quv reja yozuvlari sifatida saqlanadi.
- Keyingi modullar shu bazaviy fanlardan foydalanadi.

**Qo'lda test ssenariylari**
1. Bitta majburiy fan yarating.
2. Shu semestrda bitta `Tanlov fan` bazasi yarating.
3. `Chet tili` bazasini yarating.
4. Bitta fan uchun ikki xil dars turi kiriting.
5. Fakultet filtri ishlashini tekshiring.

### 4.2. Tanlov fan yaratish

**Menyu:** `O'quv reja -> Tanlov fan yaratish`  
**Maqsad:** bazaviy tanlov faniga variant fanlarni bog'lash.

**Oldindan tayyor bo'lishi kerak**
- `O'quv reja yaratish` da `Tanlov fan` bazasi yaratilgan bo'lishi kerak.
- Kafedralar va dars soat turlari tayyor bo'lishi kerak.

**Sahifa tarkibi**
- `Semestr`.
- `Tanlov fan (kod + nomi)` selecti.
- Har variant uchun `Tanlov fan nomi`.
- `Kafedra`.
- `Dars turi`.
- `Dars soati`.
- `Yana tanlov varianti`.

**Foydalanish tartibi**
1. Semestrni tanlang.
2. Shu semestrdagi bazaviy tanlov fanni selectdan tanlang.
3. Har variant uchun nom kiriting.
4. Har variantga kafedra tanlang.
5. Dars turi va soatini kiriting.
6. Zarur bo'lsa qo'shimcha variant qo'shing.
7. Saqlang.

**Validatsiya va qoidalar**
- Bazaviy tanlov fan tanlanishi kerak.
- Variant fan nomi bo'sh bo'lmasligi kerak.
- Kafedra tanlanishi kerak.
- Dars soatlari mantiqan to'g'ri bo'lishi kerak.

**Test ssenariylari**
1. Bitta bazaviy fan uchun 2 ta variant yarating.
2. `Barcha ishchi o'quv rejalar` sahifasida natijani tekshiring.
3. Variantlardan birini boshqa kafedraga bog'lab ko'ring.

### 4.3. Birlashtiriladigan fanlarni biriktirish

**Menyu:** `O'quv reja -> Birlashtiriladigan fanlarni biriktirish`  
**Maqsad:** turli yo'nalish yoki semestrlardagi mos fanlarni bitta umumiy fan sifatida biriktirish.

**Oldindan tayyor bo'lishi kerak**
- `Birlashtiriladigan fan` turida bazaviy fanlar yaratilgan bo'lishi kerak.

**Sahifa tarkibi**
- `Yo'nalish + semestr` selecti.
- `Fan` selecti.
- Qo'shimcha qator qo'shish tugmalari.
- `Birlashtirish` tugmasi.
- Pastda biriktirilgan fanlar ro'yxati.

**Foydalanish tartibi**
1. Birinchi qatorda yo'nalish + semestrni tanlang.
2. Shu qatorda fanni tanlang.
3. Zarur bo'lsa yana qator qo'shing.
4. `Birlashtirish` tugmasini bosing.
5. Pastdagi jadvalda biriktirishlar ustunini tekshiring.

**Validatsiya va qoidalar**
- Har qator to'liq bo'lishi kerak.
- Dublikat biriktirishlar ko'payib ketmasligi kerak.
- Birlashtiriladigan fan selecti semestrga mos fanlardan to'lishi kerak.

**Test ssenariylari**
1. Ikki xil yo'nalishdagi bir xil mazmundagi fanlarni birlashtiring.
2. Jadvalda bitta umumiy fan va uning biriktirishlari ko'rinishini tekshiring.
3. Tahrirlash va o'chirishni tekshiring.

### 4.4. Chet tilini biriktirish

**Menyu:** `O'quv reja -> Chet tilini biriktirish`  
**Maqsad:** bazaviy chet tili fanini yaratish, variant tillarni bog'lash va guruhlar kesimida talabalarni tillar bo'yicha taqsimlash.

**Biznes mantig'i**
1. Avval bazaviy chet tili fan yaratiladi.
2. Shu bazaviy fan uchun variant tillar yaratiladi.
3. Keyin `Yo'nalish + semestr` tanlanadi.
4. Tizim shu yo'nalishga tegishli guruhlar va talaba sonini olib chiqadi.
5. Har guruh bo'yicha talabalar variant tillarga bo'linadi.
6. Har guruh kesimida yig'indi guruhdagi jami talaba soniga teng bo'lishi shart.

**Sahifaning asosiy qismlari**
- 1-tab: bazaviy chet tili fanini yaratish.
- 2-tab: guruhlar kesimida chet tilini biriktirish.
- `Yo'nalish + semestr`.
- `Bazaviy chet tili fan`.
- Guruhlar va talaba soni jadvali.
- Variant tillar ustunlari.
- `Yana yo'nalish`.
- `Biriktirishni saqlash`.
- `Biriktirilgan chet tili fanlari` batafsil jadvali.

**1-tab bo'yicha foydalanish tartibi**
1. Semestrni tanlang.
2. Bazaviy chet tili fan kodi va nomini yarating.
3. Variant til nomlarini kiriting.
4. Har variantga kafedra biriktiring.
5. Dars turi va dars soatini kiriting.
6. Saqlang.

**2-tab bo'yicha foydalanish tartibi**
1. `Yo'nalish + semestr` ni tanlang.
2. `Bazaviy chet tili fan` ni tanlang.
3. Tizim guruhlar va talaba sonini chiqarsin.
4. Har guruh bo'yicha variant tillar ustuniga son kiriting.
5. Har qator `Yig'indi`si guruhdagi jami talaba soniga teng ekanini tekshiring.
6. Zarur bo'lsa `Yana yo'nalish` bilan qo'shimcha yo'nalish qo'shing.
7. `Biriktirishni saqlash` tugmasini bosing.
8. Pastdagi batafsil jadvalda natijani tekshiring.

**Qattiq validatsiya qoidalari**
- Har bir son `0` yoki undan katta bo'lishi kerak.
- Har bir guruh bo'yicha variantlar yig'indisi guruh talabalar soniga teng bo'lishi shart.
- Bazaviy fanga variant fanlar bog'lanmagan bo'lsa taqsimot jadvali ma'nosiz bo'ladi.
- Bazaviy fanga bog'liq bo'lmagan fanlar bu selectda chiqmasligi kerak.

**Amaliy misol**
- `DI-01 (30)` -> Ingliz `13`, Fransuz `7`, Nemis `10`
- `DI-02 (30)` -> Ingliz `25`, Fransuz `2`, Nemis `3`
- `DI-03 (28)` -> Ingliz `17`, Fransuz `3`, Nemis `8`

**Test ssenariylari**
1. Bazaviy chet tili fan yarating.
2. Kamida 3 ta variant til yarating.
3. 3 ta guruhli yo'nalish tanlang.
4. Guruhlar kesimida son kiriting.
5. Bir qator yig'indisini noto'g'ri kiriting. Natija: saqlash bloklanishi kerak.
6. To'g'ri yig'indi bilan saqlang. Natija: batafsil jadvalda yozuv chiqishi kerak.

### 4.5. Qo'shimcha o'quv reja yaratish

**Menyu:** `O'quv reja -> Qo'shimcha reja yaratish`  
**Maqsad:** mavjud fanlar uchun qo'shimcha yuklama va maxsus hisob-kitoblarni yuritish.

**Oldindan tayyor bo'lishi kerak**
- Semestrlar.
- Fanlar.
- Qo'shimcha dars turlari.
- Kafedralar.

**Sahifa tarkibi**
- `Semestr`.
- `Fan (kod + nomi)`.
- `Qo'shimcha dars turi`.
- `Hisoblangan fan soati`.
- Qo'shimcha parametrlar: `Hafta soni`, `Texnik yo'nalish`, `Yakuniy test`, `YADAK turi`, `YADAK o'qituvchi soni`, `YADAK fan soni`, `Ochiq dars soni`.
- Kafedra bo'yicha soat taqsimlash qatori.

**Foydalanish tartibi**
1. Semestrni tanlang.
2. Fanni tanlang.
3. Qo'shimcha dars turini tanlang.
4. Tizim chiqargan qo'shimcha parametrlarni to'ldiring.
5. Hisoblangan soatni tekshiring.
6. Zarur bo'lsa soatni bir nechta kafedra orasida bo'ling.
7. Saqlang.

**Validatsiya va qoidalar**
- Fan tanlanishi kerak.
- Qo'shimcha dars turi tanlanishi kerak.
- Hisoblangan soat manfiy bo'lmasligi kerak.
- Kafedralar bo'yicha bo'lingan soatlar umumiy soatga teng bo'lishi kerak.

**Test ssenariylari**
1. Oddiy qo'shimcha dars yozuvi yarating.
2. `YADAK` yoki maxsus tipdagi qo'shimcha maydonlarni tekshiring.
3. Bir nechta kafedra bilan saqlab ko'ring.

### 4.6. Barcha o'quv rejalar

**Menyu:** `O'quv reja -> Barcha o'quv rejalar`  
**Maqsad:** yaratilgan bazaviy fan va reja yozuvlarini ko'rish hamda nazorat qilish.

**Amaliy foydalanish**
- Fan kodi, fan nomi, semestr va kafedra mosligini tekshirish.
- Noto'g'ri yozuvlarni topish.
- Nazorat uchun umumiy ro'yxatdan foydalanish.

**Test ssenariylari**
1. Yangi yaratilgan fanlar ro'yxatda chiqishini tekshiring.
2. Tahrirlash yoki o'chirish ishlashini tekshiring.

### 4.7. Barcha ishchi o'quv rejalar

**Menyu:** `O'quv reja -> Barcha ishchi o'quv rejalar`  
**Maqsad:** bazaviy fan va variant fanlar orasidagi bog'lanishni nazorat qilish.

**Test ssenariylari**
1. Tanlov fan variantlari chiqishini tekshiring.
2. Chet tili variantlari chiqishini tekshiring.
3. Noto'g'ri yoki dublikat bog'lanish yo'qligini nazorat qiling.

### 4.8. O'quv haftaligini yaratish

**Menyu:** `O'quv reja -> O'quv haftaligini yaratish`  
**Maqsad:** yo'nalish bo'yicha kurslar va haftalar kesimida akademik kalendarni yaratish.

**Oldindan tayyor bo'lishi kerak**
- Yo'nalishlar.
- O'quv haftaligi turlari.

**Sahifa tarkibi**
- `Yo'nalish`.
- `So'ra tanlash`.
- `Hammasini tozalash`.
- `Hammasi uchun tanlash`.
- Kurslar bo'yicha kalendar jadvali.
- `Saqlash`.

**Foydalanish tartibi**
1. Yo'nalishni tanlang.
2. Tizim kurslar va haftalarni ko'rsatsin.
3. Har haftaga tegishli hafta turini tanlang.
4. Katta bloklar uchun `So'ra tanlash` yoki bulk tanlashdan foydalaning.
5. Saqlang.

**Test ssenariylari**
1. 4 yillik yo'nalishda 4 kurs chiqishini tekshiring.
2. Range tanlashni sinab ko'ring.
3. Saqlagandan keyin `Barcha o'quv haftaliklar` sahifasida natijani tekshiring.

### 4.9. Barcha o'quv haftaliklar

**Menyu:** `O'quv reja -> Barcha o'quv haftaliklar`  
**Maqsad:** yaratilgan haftaliklarni ko'rish va filtrlash.

**Sahifa tarkibi**
- `Yo'nalish bo'yicha filtrlash`.
- Tezkor filter tugmalari.
- Hafta turlari legendasi.
- Har bir yo'nalish uchun kalendar ko'rinishi.

**Test ssenariylari**
1. `Barchasi` filterida barcha haftaliklar chiqishi kerak.
2. Bitta yo'nalish filterida faqat shu yo'nalish qolishi kerak.
3. Legendadagi qisqa nomlar kalendardagi ranglar bilan mos bo'lishi kerak.

---

## 5. O'quv yuklama

### 5.1. Barcha o'quv yuklamalar

**Menyu:** `O'quv yuklama -> Barcha o'quv yuklamalar`  
**Maqsad:** o'quv reja va guruh ma'lumotlari asosida hisoblangan yuklamani ko'rish va nazorat qilish.

**Sahifa tarkibi**
- `Kafedra` filtri.
- `Yo'nalish` filtri.
- `O'quv yili` filtri.
- `Semestr turi` filtri.
- `Filtrlash`, `Tozalash`, `Chop etish`, `Excel`.
- Natija jadvali.

**Foydalanish tartibi**
1. Kerakli filterlarni tanlang.
2. `Filtrlash` tugmasini bosing.
3. Jadvalni tekshiring.
4. Zarur bo'lsa chop etish yoki Excel eksportini bajaring.

**Test ssenariylari**
1. Kafedra filtrini qo'llang.
2. Yo'nalish va o'quv yili filtrlari bilan kombinatsion tekshiruv qiling.
3. Excel eksport va chop etishni ishlating.

---

## 6. O'quv taqsimot

### 6.1. Barcha o'quv taqsimotlar

**Menyu:** `O'quv taqsimot -> Barcha o'quv taqsimotlar`  
**Maqsad:** yuklamani o'qituvchilarga soat kesimida taqsimlash.

**Sahifa tarkibi**
- `Kafedra` filtri.
- `Semestr` filtri.
- Taqsimot jadvali.
- O'qituvchi biriktirish modali.

**Modal tarkibi**
- Fan nomi.
- Soat turi.
- Maksimal soat.
- Yo'nalish, kafedra, o'quv shakli, kurs, guruh, semestr, talabalar soni kabi ma'lumotlar.
- Oldingi taqsimotlar.
- `Ajratilgan`, `Qolgan`, `Maksimal` ko'rsatkichlar.
- Bir nechta o'qituvchi qatori.

**Foydalanish tartibi**
1. Filterlarni tanlang.
2. Tegishli satr bo'yicha taqsimot modalini oching.
3. O'qituvchilarni tanlang.
4. Har biriga soat kiriting.
5. Jami soat maksimal qiymatdan oshmasligini tekshiring.
6. Saqlang.

**Validatsiya va qoidalar**
- Jami ajratilgan soat maksimal soatdan oshmasligi kerak.
- O'qituvchi tanlanmasdan satr saqlanmasligi kerak.
- Soat manfiy bo'lmasligi kerak.

**Test ssenariylari**
1. Bitta fan soatini bitta o'qituvchiga to'liq bering.
2. Shu soatni 2 o'qituvchiga bo'lib bering.
3. Maksimaldan katta qiymat kiriting va bloklanishini tekshiring.

### 6.2. O'qituvchilar soat taqsimoti

**Menyu:** `O'quv taqsimot -> O'qituvchilar soat taqsimoti`  
**Maqsad:** o'qituvchi kesimida jami ajratilgan soatlarni ko'rish.

**Sahifa tarkibi**
- `Kafedra`, `Semestr`, `O'qituvchi`, `Shtat turi` filterlari.
- `Filtrlash`, `Tozalash`, `Chop etish`, `Excel`.
- Natija jadvali.

**Test ssenariylari**
1. Bitta kafedra bo'yicha filtrlashni tekshiring.
2. Bitta o'qituvchi bo'yicha filtrlashni tekshiring.
3. Natijani taqsimot bilan solishtiring.

### 6.3. O'qituvchilar bildirgisi

**Menyu:** `O'quv taqsimot -> O'qituvchilar bildirgisi`  
**Maqsad:** o'qituvchilar uchun yakuniy bildirgi ko'rinishini olish.

**Sahifa tarkibi**
- `Kafedra`, `Semestr`, `O'qituvchi`, `Shtat turi` filterlari.
- `Filtrlash`, `Tozalash`, `Chop etish`, `Excel`.
- Hisobot jadvali.

**Test ssenariylari**
1. Bitta o'qituvchi bo'yicha bildirgi chiqaring.
2. Olingan natijani `O'qituvchilar soat taqsimoti` bilan solishtiring.

---

## 7. Boshidan oxirigacha ishchi ssenariy

### 7.1. Minimal ishchi ssenariy

1. Fakultet yarating.
2. Akademik daraja yarating.
3. Ta'lim shakli yarating.
4. Kafedra yarating.
5. Yo'nalish yarating.
6. Semestrlarni avtomatik yarating.
7. Guruhlar yarating.
8. Dars soat turlarini yarating.
9. O'qituvchilarni kiriting.
10. O'quv reja yaratishda bazaviy fanlarni yarating.
11. O'quv haftaligini yarating.
12. O'quv yuklamani tekshiring.
13. O'quv taqsimotda o'qituvchilarga soat bering.
14. O'qituvchilar soat taqsimoti va bildirgisini tekshiring.

### 7.2. Chet tili bo'yicha kengaytirilgan ssenariy

1. `O'quv reja yaratish` da `Chet tili` bazasini yarating.
2. `Chet tilini biriktirish` 1-tabida variant tillarni yarating.
3. `Guruhlar` modulida yo'nalish guruhlarini tayyorlang.
4. `Chet tilini biriktirish` 2-tabida guruhlar kesimida talabalarni tillarga bo'ling.
5. Har qator yig'indisini tekshirib saqlang.
6. Batafsil jadvalda natijani tekshiring.

### 7.3. Tanlov fan bo'yicha kengaytirilgan ssenariy

1. `O'quv reja yaratish` da `Tanlov fan` bazasini yarating.
2. `Tanlov fan yaratish` da kamida 2 ta variant yarating.
3. `Barcha ishchi o'quv rejalar`da bog'lanishni tekshiring.
4. Yuklama hisobiga ta'sirini tekshiring.

---

## 8. Kengaytirilgan test rejasi

### 8.1. Katalog testlari

1. Har bir katalog modulida `Qo'shish` ishlashi kerak.
2. `Tahrirlash` ishlashi kerak.
3. `O'chirish` yoki bog'liqlik nazorati ishlashi kerak.
4. Qidiruv maydonlari ishlashi kerak.

### 8.2. O'quv reja testlari

1. Bazaviy fan yaratish ishlashi kerak.
2. Dars turi va dars soati bir nechta qatorda saqlanishi kerak.
3. Fakultet filtri ishlashi kerak.
4. Yaratilgan fanlar ro'yxati filtrga mos yangilanishi kerak.
5. Tahrirlash ishlashi kerak.

### 8.3. Tanlov fan testlari

1. Bazaviy fan bo'lmasa select bo'sh bo'lishi kerak.
2. Bazaviy fan bo'lsa variant qo'shish ishlashi kerak.
3. Variantlar bir necha dona bo'lishi mumkin.

### 8.4. Chet tili testlari

1. Bazaviy fan bo'lmasa taqsimot jadvali hosil bo'lmasligi kerak.
2. Guruhlar va talaba soni to'g'ri chiqishi kerak.
3. Yig'indi nazorati ishlashi kerak.
4. Batafsil ro'yxat to'g'ri ko'rinishi kerak.

### 8.5. Yuklama testlari

1. Filterlar jadvalni yangilashi kerak.
2. `Tozalash` filtrlari boshlang'ich holatga qaytishi kerak.
3. `Excel` eksport ishlashi kerak.
4. `Chop etish` ishlashi kerak.

### 8.6. Taqsimot testlari

1. Modal ochilishi kerak.
2. Bir nechta o'qituvchi qatori qo'shilishi kerak.
3. Jami soat progress barda ko'rinishi kerak.
4. Maksimal soatdan oshganda saqlash bloklanishi kerak.

---

## 9. Ko'p uchraydigan xatolar va yechimlar

### 9.1. Select bo'sh chiqyapti

**Sabablar**
- Oldingi kataloglar to'ldirilmagan.
- Filtr juda tor qo'yilgan.
- Bazaviy fan hali yaratilmagan.

**Yechim**
1. Oldingi modul ma'lumotini tekshiring.
2. Filterlarni tozalang.
3. Bazaviy fan yaratilganini tasdiqlang.

### 9.2. Chet tili saqlanmayapti

**Sabablar**
- Guruh bo'yicha yig'indi noto'g'ri.
- Bazaviy fanga variant fanlar bog'lanmagan.
- Guruhlar va talaba soni tayyor emas.

**Yechim**
1. Har qator yig'indisini tekshiring.
2. 1-tabdagi variant fanlarni tekshiring.
3. `Guruhlar` modulidagi ma'lumotni tekshiring.

### 9.3. Yuklama bo'sh chiqyapti

**Sabablar**
- O'quv reja yaratilmagan.
- Guruhlar yoki semestrlar tayyor emas.
- Qo'shimcha ma'lumot yetarli emas.

**Yechim**
1. Bazaviy fanlar mavjudligini tekshiring.
2. Semestr va guruh bog'liqligini tekshiring.
3. Filterlarni kengaytirib qayta ko'ring.

### 9.4. Taqsimotda o'qituvchi chiqmayapti

**Sabablar**
- O'qituvchi katalogga kiritilmagan.
- Kafedra yoki fakultet bog'lanishi noto'g'ri.
- Filterlar tor qo'yilgan.

**Yechim**
1. `O'qituvchilar` modulini tekshiring.
2. Filterlarni tozalang.
3. O'qituvchining fakultet va kafedra ma'lumotini tekshiring.

---

## 10. Yakuniy acceptance checklist

1. Login va logout oqimi ishlaydi.
2. Dashboard statistikasi real ma'lumotga mos.
3. Fakultet, yo'nalish, kafedra, semestr, guruh va o'qituvchi kataloglari ishlaydi.
4. Yo'nalish va guruh tahrirlar tarixi yoziladi.
5. O'quv reja yaratish ishlaydi.
6. Fakultet filtri va yaratilgan fanlar ro'yxati ishlaydi.
7. Tanlov fan variantlari yaratiladi.
8. Birlashtiriladigan fanlarni biriktirish ishlaydi.
9. Chet tili bazaviy fan yaratish va guruhlar kesimida taqsimlash ishlaydi.
10. Qo'shimcha o'quv reja hisoblari ishlaydi.
11. O'quv haftaligini yaratish va ko'rish ishlaydi.
12. O'quv yuklama filtrlari, chop etish va Excel eksport ishlaydi.
13. O'quv taqsimot modalida bir nechta o'qituvchiga soat berish ishlaydi.
14. O'qituvchilar soat taqsimoti va bildirgisi to'g'ri natija beradi.
15. Muhim modullardagi tahrirlash va o'chirish amallari ishlaydi.

## 11. Hujjatni Word va PDF ga tayyorlash

1. Generatsiya qilingan HTML faylni brauzerda oching.
2. `Ctrl+P` orqali PDF ga chiqaring.
3. Word uchun HTML matnini to'g'ridan-to'g'ri ko'chirish yoki chop etish rejimidan foydalaning.
4. Rasmiy topshirishdan oldin sarlavhalar, sahifa tartibi va modul nomlarini tekshirib chiqing.
