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
  settings: 'Настройки',
  greeting: (firstName: string) => `Здравей, ${firstName}`,
  mainNavAria: 'Основна навигация',
};

/**
 * The section-anchor nav shown in the header instead of the search bar
 * while funnel mode is on — same labels/targets as the reference site's
 * SiteHeader (D:\Projects\miswak-website), pointing at the funnel
 * landing page's own section ids (see FunnelLandingPage.tsx).
 */
export const funnelNav = {
  home: nav.home,
  benefits: 'Ползи',
  howTo: 'Как се използва',
  faq: 'FAQ',
  /** The header's always-visible buy CTA — takes the old "Продукти" anchor's #buy slot. */
  orderCta: 'Поръчай сега',
};

export const footer = {
  columnsAria: 'Долна навигация',
  legalHeading: 'Правна информация',
  companyHeading: 'Компания',
  about: 'За нас',
  contact: 'Контакти',
  cookieSettings: 'Настройки на бисквитките',
  /** Legal merchant identity block — shown only once the admin fills the settings. */
  merchantHeading: 'Данни за търговеца',
  companyIdLabel: 'ЕИК',
};

export const contactForm = {
  title: 'Свържи се с нас',
  description: 'Попълни формата и ще ти отговорим на посочения имейл възможно най-скоро.',
  name: 'Име',
  email: 'Имейл адрес',
  message: 'Съобщение',
  submit: 'Изпрати',
  submitting: 'Изпращане…',
  cancel: 'Затвори',
  success: 'Съобщението е изпратено. Ще ти отговорим скоро на посочения имейл.',
  error: 'Неуспешно изпращане на съобщението. Моля, опитай отново.',
  nameRequired: 'Моля, въведи име.',
  emailRequired: 'Моля, въведи валиден имейл адрес.',
  messageRequired: 'Моля, въведи съобщение.',
};

export const cookieConsent = {
  banner: {
    message:
      'Използваме бисквитки, за да подобрим работата на сайта, да анализираме трафика и да ти предложим по-подходящо съдържание. Можеш да приемеш всички бисквитки или да персонализираш избора си.',
    acceptAll: 'Приеми всички',
    rejectAll: 'Само необходими',
    customize: 'Персонализирай',
    privacyLinkLabel: 'Политика за бисквитки',
  },
  modal: {
    title: 'Настройки на бисквитките',
    description: 'Необходимите бисквитки са винаги активни, защото сайтът не може да функционира правилно без тях. За останалите категории избираш ти.',
    save: 'Запази избора',
    cancel: 'Отказ',
    categories: {
      necessary: {
        title: 'Необходими',
        description: 'Осигуряват основни функции като количката, влизането в профил и сигурността на сайта. Не могат да бъдат изключени.',
      },
      analytics: {
        title: 'Аналитични',
        description: 'Помагат ни да разберем как се използва сайтът, за да го подобряваме.',
      },
      marketing: {
        title: 'Маркетингови',
        description: 'Използват се за показване на по-подходящи реклами и предложения.',
      },
      preferences: {
        title: 'Предпочитания',
        description: 'Запомнят твоите настройки и предпочитания за по-удобно преживяване.',
      },
    },
    alwaysActive: 'Винаги активни',
  },
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

/** The funnel landing page's #buy offer stack (see PackageOffers.tsx). */
export const funnelOffer = {
  perUnit: (formattedPrice: string) => `${formattedPrice} / бр.`,
  fromPrice: (formattedPrice: string) => `от ${formattedPrice}`,
};

/** The funnel landing page's social-proof blocks — hero rating line + testimonial cards. */
export const funnelReviews = {
  title: 'Какво казват клиентите ни',
  average: (value: string) => `${value} / 5`,
  seeAll: (count: number) => `Виж всички отзиви (${count}) →`,
};

/** The funnel landing page's email opt-in block (see LeadCaptureForm.tsx) and exit-intent modal. */
export const funnelLead = {
  title: 'Не си готов/а да поръчаш днес?',
  body: 'Остави своя имейл и ще ти пишем при промоции и специални предложения — нищо повече.',
  exitTitle: 'Преди да си тръгнеш…',
  // Honest offer: the welcome email really does deliver the usage manual.
  exitBody: 'Остави имейл и ще ти изпратим безплатното ръководство за Miswak — и ще ти пишем при промоция.',
  exitCloseAria: 'Затвори',
  placeholder: 'Твоят имейл',
  emailAria: 'Имейл за известия',
  submit: 'Извести ме',
  success: 'Благодарим! Ще се чуем скоро.',
  error: 'Нещо се обърка. Опитай отново след малко.',
  consentPrefix: 'С изпращането се съгласяваш с ',
  consentLinkLabel: 'Политиката за поверителност',
  consentSuffix: '.',
};

/**
 * Concrete delivery/returns facts shown as fine print under the funnel
 * hero's CTA. Every claim must stay true to checkout: the delivery window
 * matches the shipping methods' estimated_delivery. No free-shipping claim
 * (there is no free-shipping threshold) and no payment-method claims here
 * — those live in the DB-seeded #buy trust row (see FunnelSeeder), which
 * must track PaymentService::availablePaymentMethods() (COD is currently
 * withheld there, so the page must not advertise "наложен платеж").
 */
export const funnelAssurance = {
  delivery: 'Доставка 1-2 работни дни',
  returns: '30 дни право на връщане',
  /** Shown only on weekdays before the admin-configured cutoff (see DispatchPromise.tsx). */
  dispatch: (time: string, remaining: string) => `Поръчай до ${time} ч. — изпращаме още днес (остават ${remaining})`,
  paymentLogosAria: 'Приемани начини на плащане',
};

/** The funnel landing page's usage-video section — media comes from the product's own video media. */
export const funnelVideo = {
  title: 'Виж колко е лесно',
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

export const legalPage = {
  loading: 'Зареждане на документа…',
  loadError: 'Този документ не е намерен.',
  seoTitleSuffix: ` — ${siteName}`,
  lastUpdatedLabel: 'Версия',
};

export const about = {
  seoTitle: `За нас — ${siteName}`,
  seoDescription: 'Опознай историята и ценностите на Smisul.',
  title: 'За нас',
  intro:
    'Smisul е създаден от вярата, че естествените продукти трябва да са семпли, честни и достъпни. Работим директно с внимателно подбрани доставчици, за да предложим продукти с ясен произход и състав.',
  // Placeholder copy — replace with the real company story/team/mission before production.
  sections: [
    {
      title: 'Нашата мисия',
      text: 'Стремим се да направим грижата за тялото и ума по-семпла — без излишни съставки и без сложни обещания.',
    },
    {
      title: 'Как работим',
      text: 'Всеки продукт преминава през внимателен подбор на съставки и партньори, с фокус върху качеството и прозрачността.',
    },
  ],
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
  // Shown at "/" instead of homeTitle/homeDescription while funnel mode is
  // on (see FunnelLandingPage) — the funnel page markets a single product,
  // not the general catalog, so it needs its own meta copy.
  funnelTitle: `Miswak — натурална четка за зъби | ${siteName}`,
  funnelDescription: 'Открий Miswak — 100% натурална четка за зъби от Salvadora persica. Без паста, без вода, без пластмаса.',
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

/**
 * Short category-tag eyebrow labels for homepage sections whose heading/
 * body copy comes from the admin-editable CMS (see types/content.ts —
 * BenefitsContent/UsageContent/TrustContent/DeliveryContent/FaqContent
 * have no eyebrow field). These are structural labels, not marketing
 * copy, so they live here as static UI strings instead of going through
 * the CMS.
 */
export const homeSectionEyebrows = {
  benefits: 'Ползи',
  usage: 'Как се използва',
  trust: 'Защо да ни се довериш',
  delivery: 'Доставка',
  faq: 'Въпроси',
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
  // чл. 49, ал. 2 ЗЗП: бутонът, с който се подава поръчката, трябва
  // недвусмислено да указва задължението за плащане.
  placeOrder: 'Поръчай със задължение за плащане',
  placingOrder: 'Изпращане на поръчката…',

  paymentStep: {
    title: 'Плащане',
    description:
      'Плащанията се обработват сигурно от iCard, направо на тази страница — ние никога не съхраняваме данните ти за плащане.',
    methodLabel: 'Начин на плащане',
    methodsLoading: 'Зареждане на начините на плащане…',
    methodsLoadError: 'Неуспешно зареждане на начините на плащане — ще продължим с плащане с карта.',
    methods: {
      cash_on_delivery: 'Наложен платеж',
      card: 'Плащане с карта',
    } as Record<string, string>,
    methodHints: {
      cash_on_delivery: 'Плащаш на куриера при получаване на поръчката.',
      card: 'Ще се отвори защитеният платежен прозорец на iCard.',
    } as Record<string, string>,
    payButton: 'Плати',
    payButtonWithMethod: (methodLabel: string) => `Плати с ${methodLabel}`,
    payingButton: 'Подготвяме защитено плащане...',

    modal: {
      loading: 'Зареждаме защитен прозорец на iCard…',
      loadError: 'Неуспешно зареждане на прозореца за плащане. Моля, опитай отново.',
      paymentError: 'Плащането не беше успешно. Моля, опитай отново или избери друга карта.',
      cancelled: 'Прозорецът за плащане беше затворен. Можеш да опиташ отново.',
      unavailable: 'Плащането не можа да се стартира. Моля, опитай отново.',
      retry: 'Опитай отново',
    },
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
    viewDocument: 'Прочети документа',
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
    phoneInvalid: 'Въведи валиден мобилен номер (напр. 888123456).',
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
