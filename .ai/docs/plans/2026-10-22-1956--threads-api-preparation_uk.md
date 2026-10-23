# План підготовки: підключення Threads API і пошук постів за ключовими словами

- Створено: 2026-10-22 19:56
- Статус: draft
- Запит: Перелічити кроки, які треба виконати до реалізації підключення Threads API і отримання постів за ключовим словом: налаштування застосунку Meta, дозволи, токени, перевірка (review).

## 1. Мета та ключове обмеження

Підготувати все для початку написання коду інтеграції з Threads: робочий застосунок Meta, тестовий акаунт, перевірений потік OAuth і перевірений виклик `keyword_search`.

Доки Meta не схвалить `threads_keyword_search` у App Review, пошук за ключовими словами повертає **лише власні пости авторизованого користувача**. Пошук публічних постів потребує схвалення, а для перегляду потрібен скрінкаст робочого потоку OAuth і UI пошуку. Тому спочатку будуються і тестуються OAuth-потік та пошук на наших власних постах, а подання на перегляд відбувається пізніше.

## 2. Акаунти

- [ ] Акаунт Threads для розробки та тестування (краще окремий). Він має бути публічним.
- [ ] Акаунт Meta for Developers на [developers.facebook.com](https://developers.facebook.com), зареєстрований як розробник, з підтвердженим телефоном і email.
- [ ] Business Portfolio (Meta Business Manager). Він потрібен для Business Verification, а Advanced Access вимагає Business Verification.

## 3. Створення застосунку Meta

- [ ] My Apps → **Create App** → сценарій використання **"Access the Threads API"**.
- [ ] Use cases → Customize. Увімкнути лише такі дозволи:
    - `threads_basic` (обов'язковий);
    - `threads_keyword_search` (пошук).

    Запит дозволів, яких застосунку не потрібно, є поширеною причиною відмови.

- [ ] Settings → Basic: скопіювати **Threads App ID** і **Threads App Secret**. Вони окремі від основного Facebook App ID.
- [ ] Заповнити **Privacy Policy URL** і **Terms of Service URL**, встановити іконку та категорію.
- [ ] Додати такі callback URL:
    - **Redirect Callback URL**, наприклад `https://<domain>/auth/threads/callback`;
    - **Uninstall Callback URL**;
    - **Delete Callback URL**.

## 4. HTTPS для локальної розробки

Meta приймає лише HTTPS redirect URI, а локальний стек працює на `http://localhost:8091`.

- [ ] Обрати одне:
    - тунель (`ngrok` або `cloudflared`) до порту 8091;
    - staging-розгортання з HTTPS.
- [ ] Зробити так, щоб `APP_URL` у `.env` збігався з redirect URL, налаштованим у застосунку.

## 5. Тестувальники

- [ ] App Roles → Roles → **Add Threads Tester** → ввести ім'я користувача Threads.
- [ ] Прийняти запрошення в застосунку Threads: Settings → Account → **Website permissions → Invites**.

OAuth не працює для тестових акаунтів, доки запрошення не прийнято.

## 6. Перевірка потоку вручну перед кодуванням

Використати Postman або Graph API Explorer.

- [ ] Авторизація: `https://threads.net/oauth/authorize?client_id=...&redirect_uri=...&scope=threads_basic,threads_keyword_search&response_type=code`.
- [ ] Обміняти `code` на короткоживучий токен (1 година): `POST https://graph.threads.net/oauth/access_token`.
- [ ] Обміняти його на довгоживучий токен (60 днів): `GET https://graph.threads.net/access_token?grant_type=th_exchange_token`.
- [ ] Оновити довгоживучий токен: `GET https://graph.threads.net/refresh_access_token?grant_type=th_refresh_token`.
- [ ] Перевірити пошук: `GET https://graph.threads.net/v1.0/keyword_search?q=...&search_type=RECENT&fields=id,text,permalink,timestamp,username`.

## 7. Обмеження, з якими треба працювати в дизайні

- **Ліміт запитів: 2 200 запитів на користувача за ковзні 24 години**, спільний для всіх застосунків. Він обмежує частоту отримання × кількість ключових слів. Запити, що не повертають результатів, до ліміту не зараховуються.
- Ключові слова, які Meta вважає чутливими або образливими, мовчки повертають порожній масив.
- Зберігати токени в зашифрованому вигляді (`encrypted` cast). Оновлювати їх запланованою задачею до того, як спливуть 60 днів.
- Викликати API з jobs у черзі (queued jobs), а не всередині HTTP-запитів.
- Звужувати пошук через `since`/`until`, щоб ті самі пости не завантажувалися повторно, і дедуплікувати за `id` посту.
- Meta дозволяє `threads_keyword_search` лише в бізнес-застосунках, які допомагають користувачам керувати присутністю в соцмережах. **У споживчих застосунках це заборонено.** Описувати сценарій використання як бізнес-інструмент: моніторинг можливостей і запитів для фрилансерів або агенцій.

## 8. App Review (Advanced Access)

- [ ] Пройти **Business Verification**.
- [ ] Подати `threads_keyword_search` на перегляд з описом сценарію використання та **скрінкастом**, що показує: вхід через Threads OAuth → прив'язку акаунта → пошук публічних постів за ключовими словами в нашому UI.

## 9. Порядок роботи

1. Розділи 2–6.
2. Реалізація: OAuth-підключення акаунта Threads, зберігання і оновлення токенів, `ThreadsClient`, job пошуку, збереження постів. Тестувати на наших власних постах до схвалення.
3. Записати скрінкаст → App Review.
4. Після схвалення пошук охоплює всі публічні пости.

Далі: написати специфікацію для підключення Threads і пошуку за ключовими словами (`spec-creator`), потім план реалізації.

## Джерела

- [Keyword Search – Threads API](https://developers.facebook.com/docs/threads/keyword-search/)
- [Permissions Reference](https://developers.facebook.com/docs/permissions)
- [Threads API overview](https://developers.facebook.com/documentation/threads/overview)
- [Create an App with Meta](https://developers.facebook.com/documentation/development/create-an-app)
