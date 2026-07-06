/**
 * Bulgarian marketing/content copy for the public storefront.
 *
 * Centralized here (rather than a full i18n framework — this is a
 * single-locale site for now) so brand voice lives in one obvious,
 * editable place instead of scattered across page components. All copy
 * is placeholder content: natural/premium in tone, and deliberately
 * avoids medical claims ("cures", "treats", "heals", guaranteed health
 * outcomes) per brand guidelines.
 */

export const siteName = 'Smisul';

export const nav = {
  home: 'Начало',
  browseProducts: 'Продукти',
  searchPlaceholder: 'Търси продукти…',
  searchAria: 'Търсене на продукти',
  login: 'Вход',
  register: 'Регистрация',
  logout: 'Изход',
  profile: 'Профил',
  greeting: (firstName: string) => `Здравей, ${firstName}`,
  mainNavAria: 'Основна навигация',
};

export const footer = {
  tagline: 'Smisul — естествени продукти с ясен смисъл.',
  description: 'Създаваме с грижа за природата, за качеството и за теб.',
  columnsAria: 'Долна навигация',
};

export const auth = {
  login: {
    title: 'Вход',
    email: 'Имейл адрес',
    password: 'Парола',
    rememberMe: 'Запомни ме',
    forgotPassword: 'Забравена парола?',
    submit: 'Вход',
    noAccount: 'Нямаш акаунт?',
    registerLink: 'Регистрирай се',
    error: 'Неуспешен вход. Моля, опитай отново.',
  },

  register: {
    title: 'Създай акаунт',
    firstName: 'Име',
    lastName: 'Фамилия',
    email: 'Имейл адрес',
    phoneOptional: 'Телефон (незадължително)',
    password: 'Парола',
    confirmPassword: 'Потвърди паролата',
    newsletterSubscription: 'Абонирай се за бюлетина',
    marketingConsent: 'Съгласявам се да получавам маркетингови съобщения',
    gdprConsent: 'Съгласявам се с обработката на личните ми данни съгласно Политиката за поверителност *',
    submit: 'Създай акаунт',
    haveAccount: 'Вече имаш акаунт?',
    loginLink: 'Вход',
    success: 'Акаунтът е създаден. Провери имейла си, за да потвърдиш адреса, след което влез в профила си.',
    error: 'Неуспешна регистрация. Моля, провери формата и опитай отново.',
  },

  forgotPassword: {
    title: 'Забравена парола?',
    email: 'Имейл адрес',
    submit: 'Изпрати линк за възстановяване',
    backToLogin: 'Обратно към вход',
    error: 'Неуспешно изпращане на линка за възстановяване. Моля, опитай отново.',
  },

  resetPassword: {
    invalidLinkTitle: 'Възстановяване на парола',
    invalidLink: 'Този линк за възстановяване на парола е невалиден или непълен.',
    requestNewLink: 'Заяви нов линк',
    title: 'Възстанови паролата си',
    email: 'Имейл адрес',
    newPassword: 'Нова парола',
    confirmNewPassword: 'Потвърди новата парола',
    submit: 'Възстанови паролата',
    success: 'Паролата ти беше възстановена. Моля, влез в профила си.',
    error: 'Неуспешно възстановяване на паролата. Възможно е линкът да е изтекъл.',
  },

  verifyEmail: {
    title: 'Потвърждение на имейл',
    verifying: 'Проверка…',
    goToLogin: 'Към вход',
    invalidLink: 'Този линк за потвърждение е невалиден или непълен.',
    error: 'Този линк за потвърждение е невалиден или е изтекъл.',
  },
};

export const profile = {
  details: {
    heading: 'Данни за профила',
    emailNotVerified: 'Имейл адресът ти не е потвърден.',
    resendVerification: 'Изпрати отново имейл за потвърждение',
    firstName: 'Име',
    lastName: 'Фамилия',
    email: 'Имейл адрес',
    phone: 'Телефон',
    newsletterSubscription: 'Абонирай се за бюлетина',
    marketingConsent: 'Съгласявам се да получавам маркетингови съобщения',
    submit: 'Запази промените',
    successEmailChanged: 'Профилът е обновен. Тъй като смени имейла си, моля, потвърди го отново.',
    success: 'Профилът е обновен успешно.',
    error: 'Неуспешно обновяване на профила.',
    resendError: 'Неуспешно изпращане на имейла за потвърждение.',
  },
  changePassword: {
    heading: 'Смяна на парола',
    currentPassword: 'Текуща парола',
    newPassword: 'Нова парола',
    confirmNewPassword: 'Потвърди новата парола',
    submit: 'Обнови паролата',
    error: 'Неуспешна смяна на паролата.',
  },
};

export const product = {
  loading: 'Зареждане на продукт…',
  descriptionTitle: 'Описание',
  packageLabel: 'Разфасовка',
  videosTitle: 'Видео',
  downloadsTitle: 'Документи за изтегляне',
  downloadFallbackLabel: 'Изтегли PDF',
  noImage: 'Няма налична снимка',
  galleryThumbAria: (index: number, total: number) => `Покажи снимка ${index} от ${total}`,
};

export const category = {
  loading: 'Зареждане на категория…',
};

export const listing = {
  searchLabel: 'Търсене',
  searchPlaceholder: 'Търси продукти…',
  searchButton: 'Търси',
  sortLabel: 'Подреди по',
  sortOptions: {
    newest: 'Най-нови',
    price_asc: 'Цена: възходящо',
    price_desc: 'Цена: низходящо',
    name: 'Име',
  },
  minPriceLabel: 'Мин. цена',
  maxPriceLabel: 'Макс. цена',
  anyPricePlaceholder: 'Без ограничение',
  emptyTitle: 'Няма намерени продукти',
  categoryEmpty: 'В тази категория все още няма продукти.',
  searchEmpty: 'Опитай с друга дума за търсене или изчисти филтрите.',
  loading: 'Зареждане на продукти…',
  paginationAria: 'Странициране',
  previous: 'Предишна',
  next: 'Следваща',
};

export const stock = {
  inStock: 'В наличност',
  lowStock: (quantity: number) => `Ограничена наличност — остават ${quantity} бр.`,
  outOfStock: 'Изчерпан',
  unknown: 'Наличността не е известна',
};

export const price = {
  unavailable: 'Няма посочена цена',
};

export const states = {
  loadingDefault: 'Зареждане…',
  errorRetry: 'Опитай отново',
  emptyDefaultTitle: 'Все още няма съдържание тук',
};

export const breadcrumbLabels = {
  home: nav.home,
  search: 'Търсене',
};

export const notFound = {
  title: '404',
  lead: 'Страницата, която търсиш, не съществува или е преместена.',
  cta: 'Обратно към началото',
  seoTitle: `Страницата не е намерена — ${siteName}`,
  seoDescription: 'Страницата, която търсиш, не съществува или е преместена.',
};

export const seo = {
  homeTitle: `${siteName} — естествени продукти с ясен смисъл`,
  homeDescription: 'Открий Smisul: естествени продукти с ясен произход, семпъл състав и грижа във всеки детайл.',
  productTitleSuffix: ` — ${siteName}`,
  productDescriptionFallback: 'Естествен продукт от Smisul — семпъл състав, ясен произход и грижа във всеки детайл.',
  categoryTitleSuffix: ` — ${siteName}`,
  categoryDescriptionFallback: 'Разгледай продуктите на Smisul в тази категория — естествени, семпли и създадени с грижа.',
  searchTitleAll: `Всички продукти — ${siteName}`,
  searchTitleQuery: (query: string) => `Резултати за „${query}“ — ${siteName}`,
  searchHeadingAll: 'Всички продукти',
  searchHeadingQuery: (query: string) => `Резултати за „${query}“`,
  searchDescription: 'Разгледай и намери продуктите на Smisul по твоите критерии.',
};

export const cart = {
  title: 'Количка',
  badgeAria: (count: number) => `Количка, ${count} артикула`,
  empty: {
    title: 'Количката е празна',
    message: 'Разгледай продуктите и добави нещо, което ти харесва.',
    cta: 'Разгледай продуктите',
  },
  loading: 'Зареждане на количката…',
  itemsHeading: 'Артикули',
  quantityLabel: 'Количество',
  decreaseAria: 'Намали количеството',
  increaseAria: 'Увеличи количеството',
  remove: 'Премахни',
  removeAria: (name: string) => `Премахни ${name} от количката`,
  clear: 'Изпразни количката',
  unavailable: 'Вече не е налично в заявеното количество',
  outOfStock: 'Изчерпан',
  subtotal: 'Междинна сума',
  discount: 'Отстъпка',
  shipping: 'Доставка',
  tax: 'ДДС',
  grandTotal: 'Общо',
  shippingCalculatedLater: 'изчислява се на следваща стъпка',
  viewCart: 'Виж количката',
  continueShopping: 'Продължи пазаруването',
  checkoutCta: 'Продължи към плащане',
  addToCart: 'Добави в количката',
  addedToCart: 'Добавено в количката.',
  addToCartError: 'Неуспешно добавяне в количката.',
  updateError: 'Неуспешна промяна на количеството.',
  removeError: 'Неуспешно премахване на артикула.',
  clearError: 'Неуспешно изпразване на количката.',
  loadError: 'Неуспешно зареждане на количката.',
  noImage: 'Няма снимка',
  seoTitle: `Количка — ${siteName}`,
  seoDescription: 'Прегледай продуктите в количката си в Smisul.',
};

export const checkout = {
  title: 'Плащане',
  seoTitle: `Плащане — ${siteName}`,
  seoDescription: 'Завърши поръчката си в Smisul.',
  emptyCartTitle: 'Количката е празна',
  emptyCartMessage: 'Добави продукти в количката, преди да продължиш към плащане.',
  emptyCartCta: 'Разгледай продуктите',

  steps: {
    customer: 'Данни за поръчката',
    delivery: 'Доставка',
    review: 'Преглед',
    payment: 'Плащане',
  },

  back: 'Назад',
  next: 'Напред',
  placeOrder: 'Направи поръчка',
  placingOrder: 'Изпращане на поръчката…',

  paymentStep: {
    title: 'Плащане',
    description:
      'Плащанията се обработват сигурно от iCard. Ще бъдеш пренасочен/а към страницата на iCard, за да завършиш плащането — ние никога не съхраняваме данните ти за плащане.',
    methodLabel: 'Начин на плащане',
    methodsLoading: 'Зареждане на начините на плащане…',
    methodsLoadError: 'Неуспешно зареждане на начините на плащане — ще продължим с плащане с карта.',
    methods: {
      card: 'Карта (Visa / Mastercard)',
      apple_pay: 'Apple Pay',
      google_pay: 'Google Pay',
    } as Record<string, string>,
    applePayUnavailable: 'Наличен само в Safari на устройства на Apple',
    payButton: 'Плати',
    payButtonWithMethod: (methodLabel: string) => `Плати с ${methodLabel}`,
    payingButton: 'Пренасочване към iCard…',
  },

  customer: {
    firstName: 'Име',
    lastName: 'Фамилия',
    email: 'Имейл адрес',
    phone: 'Телефон',
    company: 'Фирма (незадължително)',
    vatNumber: 'ДДС номер (незадължително)',
    deliveryNotes: 'Бележки към доставката (незадължително)',
  },

  address: {
    title: 'Адрес за доставка',
    country: 'Държава',
    city: 'Град',
    postalCode: 'Пощенски код',
    addressLine: 'Адрес',
    apartment: 'Апартамент / Етаж (незадължително)',
  },

  billing: {
    title: 'Адрес за фактуриране',
    sameAsShipping: 'Адресът за фактуриране е същият като адреса за доставка',
  },

  delivery: {
    title: 'Начин на доставка',
    loading: 'Зареждане на начините за доставка…',
    loadError: 'Неуспешно зареждане на начините за доставка.',
    estimatedDeliveryPrefix: 'Очаквана доставка:',
    officeLabel: 'Офис / автомат',
    officePlaceholder: 'Избери офис или автомат',
    officeLoading: 'Зареждане на офиси…',
    officeLoadError: 'Неуспешно зареждане на офисите.',
    officeEmpty: 'Няма намерени офиси за този град. Опитай с друг град.',
    officeRequired: 'Моля, избери офис или автомат.',
  },

  legal: {
    title: 'Правна информация',
    loading: 'Зареждане…',
    loadError: 'Неуспешно зареждане на правната информация.',
    acceptPrefix: 'Приемам',
  },

  review: {
    title: 'Преглед на поръчката',
    itemsHeading: 'Артикули',
    customerHeading: 'Данни за поръчката',
    addressHeading: 'Адрес за доставка',
    billingAddressHeading: 'Адрес за фактуриране',
    deliveryHeading: 'Доставка',
  },

  errors: {
    firstNameRequired: 'Моля, въведи име.',
    lastNameRequired: 'Моля, въведи фамилия.',
    emailRequired: 'Моля, въведи валиден имейл адрес.',
    phoneRequired: 'Моля, въведи телефон.',
    countryRequired: 'Моля, въведи държава.',
    cityRequired: 'Моля, въведи град.',
    postalCodeRequired: 'Моля, въведи пощенски код.',
    addressLineRequired: 'Моля, въведи адрес.',
    shippingMethodRequired: 'Моля, избери начин на доставка.',
    legalRequired: 'Трябва да приемеш всички изброени документи, за да продължиш.',
    placeOrderFailed: 'Неуспешно създаване на поръчката. Моля, провери данните и опитай отново.',
  },

  confirmation: {
    seoTitle: `Потвърждение на поръчка — ${siteName}`,
    loading: 'Зареждане на поръчката…',
    loadError: 'Неуспешно зареждане на поръчката.',
    thankYou: 'Благодарим ти за поръчката!',
    orderNumberLabel: 'Номер на поръчката',
    statusLabel: 'Статус',
    statusPending: 'Изчаква обработка',
    paymentNotice: 'Ще получиш имейл с потвърждение веднага щом плащането бъде обработено.',
    continueShopping: 'Продължи пазаруването',
  },

  redirectingToPayment: 'Пренасочване към страницата за плащане…',
};

export const tracking = {
  seoTitle: `Проследяване на пратка — ${siteName}`,
  title: 'Проследяване на пратка',
  loading: 'Зареждане на информация за пратката…',
  loadError: 'Неуспешно зареждане на информация за пратката.',
  notShippedYet: 'Поръчката все още не е предадена на куриер.',
  trackingNumberLabel: 'Товарителница №',
  carrierLabel: 'Куриер',
  officeLabel: 'Офис / автомат',
  estimatedDeliveryLabel: 'Очаквана доставка',
  historyHeading: 'История на пратката',
  backToOrder: 'Обратно към поръчката',
};

export const payment = {
  loading: 'Проверка на статуса на плащането…',
  loadError: 'Неуспешно зареждане на статуса на плащането.',

  success: {
    seoTitle: `Успешно плащане — ${siteName}`,
    title: 'Плащането е успешно!',
    message: 'Благодарим ти — поръчката е потвърдена и вече я подготвяме.',
    stillProcessingTitle: 'Обработваме плащането',
    stillProcessingMessage: 'Плащането все още се обработва. Ще получиш имейл веднага щом бъде потвърдено.',
    viewOrder: 'Виж поръчката',
    continueShopping: 'Продължи пазаруването',
  },

  failed: {
    seoTitle: `Неуспешно плащане — ${siteName}`,
    title: 'Плащането не бе успешно',
    message: 'За съжаление плащането не мина. Можеш да опиташ отново или да се свържеш с нас.',
    retry: 'Опитай отново',
    viewOrder: 'Виж поръчката',
  },

  cancelled: {
    seoTitle: `Отказано плащане — ${siteName}`,
    title: 'Плащането е отказано',
    message: 'Отказа плащането. Поръчката е отказана — можеш да опиташ отново по всяко време.',
    retry: 'Опитай отново',
    continueShopping: 'Продължи пазаруването',
  },

  retryError: 'Неуспешно стартиране на ново плащане. Моля, опитай отново.',
};

export const orders = {
  title: 'Моите поръчки',
  seoTitle: `Моите поръчки — ${siteName}`,
  seoDescription: 'Прегледай историята на поръчките си в Smisul.',
  loading: 'Зареждане на поръчките…',
  loadError: 'Неуспешно зареждане на поръчките.',
  emptyTitle: 'Все още нямаш поръчки',
  emptyMessage: 'Разгледай продуктите и направи първата си поръчка.',
  emptyCta: 'Разгледай продуктите',
  orderNumberHeading: 'Номер',
  dateHeading: 'Дата',
  statusHeading: 'Статус',
  totalHeading: 'Сума',
  viewOrder: 'Виж поръчката',
  downloadInvoice: 'Изтегли фактура (примерна)',
  timelineHeading: 'История на поръчката',
  timelineChangedBySystem: 'Системата',
  status: {
    pending: 'Изчаква обработка',
    awaiting_payment: 'Очаква плащане',
    paid: 'Платена',
    processing: 'В обработка',
    packed: 'Опакована',
    shipped: 'Изпратена',
    delivered: 'Доставена',
    completed: 'Завършена',
    cancelled: 'Отказана',
    refunded: 'Възстановена',
    failed: 'Неуспешна',
  } as Record<string, string>,
};

export const favorites = {
  title: 'Любими',
  seoTitle: `Любими — ${siteName}`,
  seoDescription: 'Продуктите, които си запазил/а в любими.',
  loading: 'Зареждане на любими продукти…',
  loadError: 'Неуспешно зареждане на любимите продукти.',
  emptyTitle: 'Все още нямаш любими продукти',
  emptyMessage: 'Разгледай продуктите и запази тези, които харесваш.',
  emptyCta: 'Разгледай продуктите',
  add: 'Добави в любими',
  remove: 'Премахни от любими',
  addAria: 'Добави в любими',
  removeAria: 'Премахни от любими',
  loginToSave: 'Влез, за да запазиш в любими',
  addError: 'Неуспешно добавяне в любими.',
  removeError: 'Неуспешно премахване от любими.',
  duplicateError: 'Този продукт вече е в любимите ти.',
  // Same underlying favorite — just relabeled on a product page when the
  // item is currently unavailable, since "save this for later" reads as
  // "notify me when it's back" rather than "I like this" in that context.
  addToWishlist: 'Добави в списък за изчакване',
  removeFromWishlist: 'Премахни от списъка за изчакване',
  wishlistNotice: 'Ще ти изпратим имейл, когато продуктът отново е наличен.',
};

export const reviews = {
  title: 'Отзиви',
  seoTitle: `Моите отзиви — ${siteName}`,
  seoDescription: 'Прегледай отзивите, които си оставил/а за продукти на Smisul.',
  loading: 'Зареждане на отзиви…',
  loadError: 'Неуспешно зареждане на отзивите.',
  summaryLoadError: 'Неуспешно зареждане на обобщението на отзивите.',
  emptyTitle: 'Все още няма отзиви',
  emptyMessage: 'Бъди първият, който ще сподели мнение за този продукт.',
  reviewCount: (count: number) => `${count} ${count === 1 ? 'отзив' : 'отзива'}`,
  verifiedPurchase: 'Потвърдена покупка',
  helpfulQuestion: 'Полезен ли беше този отзив?',
  helpful: 'Полезен',
  helpfulCount: (count: number) => `Полезен (${count})`,
  helpfulError: 'Неуспешно гласуване.',
  adminReplyLabel: 'Отговор от екипа на Smisul',

  sortLabel: 'Подреди по',
  sortOptions: {
    newest: 'Най-нови',
    highest: 'Най-висока оценка',
    lowest: 'Най-ниска оценка',
    helpful: 'Най-полезни',
  },

  distributionTitle: 'Разпределение на оценките',
  starLabel: (n: number) => `${n} ${n === 1 ? 'звезда' : 'звезди'}`,

  writeReview: 'Напиши отзив',
  editReview: 'Редактирай отзива',
  delete: 'Изтрий',
  cancel: 'Отказ',
  ratingLabel: 'Оценка',
  titleLabel: 'Заглавие',
  bodyLabel: 'Отзив',
  submit: 'Изпрати отзива',
  submitting: 'Изпращане…',
  saveChanges: 'Запази промените',
  submitSuccess: 'Благодарим за отзива!',
  submitError: 'Неуспешно изпращане на отзива.',
  updateError: 'Неуспешно запазване на промените.',
  deleteConfirm: 'Сигурен/а ли си, че искаш да изтриеш отзива?',
  deleteError: 'Неуспешно изтриване на отзива.',
  ratingRequired: 'Моля, избери оценка.',
  titleRequired: 'Моля, въведи заглавие.',
  bodyRequired: 'Моля, въведи текст на отзива.',

  yourReviewTitle: 'Твоят отзив',
  statusPending: 'Изчаква одобрение',
  statusApproved: 'Одобрен',
  statusRejected: 'Отхвърлен',
  statusHidden: 'Скрит',

  myReviews: {
    title: 'Моите отзиви',
    emptyTitle: 'Все още нямаш написани отзиви',
    emptyMessage: 'Прегледай доставените си поръчки, за да оставиш отзив за продукт.',
    emptyCta: 'Виж поръчките си',
  },

  writePrompt: {
    title: 'Продуктът е доставен — сподели мнение',
    cta: 'Напиши отзив',
    alreadyReviewed: 'Вече си оставил/а отзив за този продукт',
    editCta: 'Редактирай отзива си',
  },
};

/**
 * Bulgarian placeholder copy for backend-owned seed content (product
 * name/description/short_description). NOT applied automatically — these
 * fields live in the backend's ProductSeeder, which this sprint's brief
 * asked us not to touch without confirming first. Kept here so the copy
 * is ready to drop in once that's approved (see the sprint report's
 * "known limitations" section).
 */
export const productSeedContentDraft = {
  shortDescription: 'Оригиналната смес на Smisul — чиста, семпла и направена с грижа.',
  description:
    'Създадена от внимателно подбрани съставки, оригиналната смес на Smisul е за хората, които ценят простотата и прозрачността. Без излишни добавки, без сложни списъци — само това, което наистина има значение. Достъпна в четири разфасовки, за да намериш темпото, което пасва на твоя ден.',
};
