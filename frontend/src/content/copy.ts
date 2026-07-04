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

export const hero = {
  eyebrow: 'Естествено. Българско. Автентично.',
  title: 'Смисъл в най-чистия си вид',
  subtitle:
    'Създаваме продукти с грижа към природата и към теб — прости съставки, ясен произход и качество, на което може да се разчита всеки ден.',
  cta: 'Разгледай продуктите',
};

export const featured = {
  eyebrow: 'Препоръчваме',
  title: 'Продуктът на месеца',
  description: 'Внимателно подбран състав и грижа във всяка стъпка — открий любимеца на нашите клиенти.',
  loading: 'Зареждане на препоръчания продукт…',
  empty: 'В момента няма наличен продукт — очаквай ни съвсем скоро.',
  cta: 'Разгледай продукта',
};

export const benefits = {
  title: 'Защо да избереш Smisul',
  lead: 'Малки, обмислени решения във всяка стъпка от избора на съставки до опаковката, която държиш в ръцете си.',
  items: [
    {
      icon: 'leaf' as const,
      title: 'Естествен състав',
      text: 'Подбираме съставки с ясен произход и минимална обработка.',
    },
    {
      icon: 'shield' as const,
      title: 'Прозрачност',
      text: 'Знаеш какво съдържа всеки продукт — без скрити добавки.',
    },
    {
      icon: 'heart' as const,
      title: 'Грижа в детайла',
      text: 'От опаковката до доставката — всяка стъпка е обмислена.',
    },
    {
      icon: 'sparkle' as const,
      title: 'Малки серии',
      text: 'Произвеждаме внимателно, вместо да бързаме с количеството.',
    },
  ],
};

export const usage = {
  title: 'Как да го включиш в деня си',
  steps: [
    { title: 'Избери своя пакет', text: 'Подбери разфасовката, която пасва на твоя ритъм.' },
    { title: 'Направи го навик', text: 'Стани част от малък, спокоен ежедневен ритуал.' },
    { title: 'Бъди последователен', text: 'Хубавите неща идват с постоянство, не с бързане.' },
  ],
};

export const trust = {
  title: 'На какво можеш да разчиташ',
  items: [
    { icon: 'leaf' as const, text: 'Ясен произход на съставките' },
    { icon: 'shield' as const, text: 'Внимателно опаковане и съхранение' },
    { icon: 'heart' as const, text: 'Отговори на въпроси — винаги на разположение' },
  ],
};

export const delivery = {
  icon: 'truck' as const,
  title: 'Доставка с грижа за детайла',
  text: 'Подготвяме всяка пратка внимателно, за да стигне до теб в перфектно състояние. Онлайн поръчките предстои да заработят съвсем скоро.',
};

export const bio = {
  eyebrow: 'Природата, поднесена с уважение',
  title: 'Просто, честно, устойчиво',
  text: 'Вярваме, че истинското качество тръгва от избора на съставки и уважението към процеса на създаване. Затова работим с малки партиди, ясен произход и минимална намеса — за продукти, които усещаш, че наистина имат стойност.',
};

export const faq = {
  title: 'Често задавани въпроси',
  items: [
    {
      question: 'От какво са направени продуктите на Smisul?',
      answer: 'Подбираме съставки с ясен произход и наблягаме на естествената им форма, с минимална преработка.',
    },
    {
      question: 'Как да избера правилния пакет?',
      answer: 'Всеки продукт предлага няколко разфасовки — избери тази, която пасва на твоето темпо на употреба.',
    },
    {
      question: 'Кога ще мога да поръчвам онлайн?',
      answer: 'Работим по онлайн поръчките и ще обявим старта им съвсем скоро.',
    },
    {
      question: 'Как да се свържа с вас?',
      answer: 'Ще намериш начини за контакт в долната част на сайта — с удоволствие ще отговорим на въпросите ти.',
    },
  ],
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
    title: 'Плащане с карта',
    description:
      'Плащанията се обработват сигурно от iCard. Ще бъдеш пренасочен/а към страницата на iCard, за да въведеш данните на картата си — ние никога не съхраняваме тези данни.',
    methodLabel: 'Начин на плащане',
    methodValue: 'Карта (Visa / Mastercard) чрез iCard',
    payButton: 'Плати с карта',
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
