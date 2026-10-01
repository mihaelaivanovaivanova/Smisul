/**
 * Content specific to the Juun.bg-structured Miswak redesign at
 * /products/miswak (MiswakLandingPage.tsx and its section components) —
 * deliberately NOT wired into the admin CMS yet (see the approved plan):
 * this page is still under construction, so its own copy lives here in
 * code for fast iteration rather than adding a new editable section
 * before the design has settled. Copy shared with the live "/" funnel
 * page (hero trust items, why, science, comparison, faq) is read
 * live from useSettings() instead of being duplicated here.
 */

/** A bold, distinct-font tagline shown beneath the product description — see PurchasePanel.tsx. */
export const miswakTagline =
  'Натурална четка на десетки поколения - естествен корен от Salvadora persica - без паста, без вода, без химия и пластмаса';

export interface MiswakExpert {
  name: string;
  credentials: string;
  photo: string;
  quote: string;
  link?: string;
}

export interface MiswakUgcVideo {
  video_url: string;
  poster: string;
  caption?: string;
}

export interface MiswakUgcPhoto {
  image: string;
  caption?: string;
}

/**
 * Real dentist/expert endorsements — none yet. The section that renders
 * this (ExpertsSection) returns null on an empty array, so the page ships
 * cleanly without it. Fill in as {name, credentials, photo, quote} once
 * real quotes/photos are supplied.
 */
export const miswakExperts: MiswakExpert[] = [];

export interface MiswakExpertCard {
  /** Full pre-designed card graphic (photo + name + credentials + study citation + bullets + button + PMID), cropped to just the card itself — no separate structured fields, since the card's own layout isn't rebuilt in markup. */
  image: string;
  /** Real alt text (not "") — the image carries unique information (who, their role, which study) with no equivalent visible text elsewhere on the page. */
  alt: string;
  /** The card's own "Виж изследването" button has no real link baked into the image — this is layered on top via ExpertCardsSection's absolutely-positioned overlay anchor at that button's own measured position. */
  sourceUrl: string;
}

/**
 * "Експертите и Miswak" — 4 expert/citation cards supplied directly as
 * finished graphics (public/funnel/v2/expert-card-*.png, cropped from the
 * user-supplied doc 1-4.png to remove their outer canvas margin, keeping
 * just the card itself). PMIDs read directly off each card's own footer:
 * 33513418, 14973564 (also cited by AudienceClaimsSection's
 * caries-bacteria claim), 33258168, 37519323.
 */
export const miswakExpertCards: MiswakExpertCard[] = [
  {
    image: '/funnel/v2/expert-card-1.png',
    alt: 'Д-р Фара Азуин Адам, ръководител на Центъра за пародонтологични изследвания, Факултет по дентална медицина, Universiti Teknologi MARA — систематичен преглед и мета-анализ: Salvadora persica L. chewing stick and standard toothbrush as anti-plaque and anti-gingivitis tool',
    sourceUrl: 'https://pubmed.ncbi.nlm.nih.gov/33513418/',
  },
  {
    image: '/funnel/v2/expert-card-2.png',
    alt: 'Проф. Халид Алмас, професор по пародонтология, Imam Abdulrahman Bin Faisal University — клинично изследване: Immediate antimicrobial effect of Miswak on cariogenic bacteria',
    sourceUrl: 'https://pubmed.ncbi.nlm.nih.gov/14973564/',
  },
  {
    image: '/funnel/v2/expert-card-3.png',
    alt: 'Д-р Джаган Кумар Баскарадос, доцент, Kuwait University College of Dentistry — рандомизирано клинично проучване: A clinical investigation into the efficacy of Miswak chewing sticks as an oral hygiene aid',
    sourceUrl: 'https://pubmed.ncbi.nlm.nih.gov/33258168/',
  },
  {
    image: '/funnel/v2/expert-card-4.png',
    alt: 'Доц. Омар Шаалан, доцент по консервативно зъболечение, Cairo University — клинично проучване: Randomized clinical trial on Miswak toothpaste in high caries-risk patients',
    sourceUrl: 'https://pubmed.ncbi.nlm.nih.gov/37519323/',
  },
];

export interface MiswakSocialComment {
  /** Real screenshot, cropped to just the comment card itself — not recreated as markup, so what's shown is verifiably the actual post. */
  image: string;
  alt: string;
  /** Icon name from components/icons/Icon.tsx for the category pill shown under the card. */
  tagIcon: 'sparkle' | 'truck' | 'leaf' | 'tooth';
  tagLabel: string;
}

/**
 * "Истински коментари. Истинско доверие." — real screenshots of unprompted
 * Facebook/Instagram comments on Miswak posts (public/funnel/v2/comment-
 * *.jpg, supplied directly by the user, uncropped beyond removing each
 * platform's outer chrome). SocialCommentsSection renders these verbatim
 * as images rather than retyping the text as markup, since the screenshot
 * itself — visible platform UI, handle, timestamp — is what makes it
 * verifiable as a real comment and not site copy.
 */
export const miswakSocialComments: MiswakSocialComment[] = [
  {
    image: '/funnel/v2/comment-spasimira.jpg',
    alt: 'Spasimira Petrova: През 2004 та и аз ползвах това дърво! Даде ни го един сенегалец който ни беше приятел! Наистина избелва зъбите',
    tagIcon: 'sparkle',
    tagLabel: 'избелване',
  },
  {
    image: '/funnel/v2/comment-misha.jpg',
    alt: 'misha.ivanova: Коректно отношение! Поръчката пристигна за 2 дни :)',
    tagIcon: 'truck',
    tagLabel: 'бърза доставка',
  },
  {
    image: '/funnel/v2/comment-aysheismailova.jpg',
    alt: 'aysheismailova21: Нарича се Мисвак. Още преди повече от 1400 години хората са го използвали, като ползите от него са много повече отколкото боклуците, които използваме днес.',
    tagIcon: 'leaf',
    tagLabel: 'традиция',
  },
  {
    image: '/funnel/v2/comment-thread.jpg',
    alt: 'Коментарна нишка: Anife пита дали „мисвак“ не е обидна дума, Smisul BG отговаря, че не е и препраща към smisul.bg, Anife потвърждава че го използва и избелва супер.',
    tagIcon: 'sparkle',
    tagLabel: 'избелване',
  },
  {
    image: '/funnel/v2/comment-vencislava.jpg',
    alt: 'Vencislava Filcheva: Ако се използва правилно, почиства зъбите идеално и прави страхотен масаж на венците!',
    tagIcon: 'tooth',
    tagLabel: 'венци / почистване',
  },
];

/**
 * Real customer video clips — none yet (UgcVideoSection returns null on
 * an empty array). Fill in as {video_url, poster, caption?} once real
 * clips are supplied.
 */
export const miswakUgcVideos: MiswakUgcVideo[] = [];

/**
 * Real customer photos — none yet (UgcGallerySection returns null on an
 * empty array). Fill in as {image, caption?} once real photos are
 * supplied.
 */
export const miswakUgcPhotos: MiswakUgcPhoto[] = [];

/** Quick trust/benefit bullets shown above the package selector (TrustBullets). */
export const miswakTrustBullets: string[] = [
  '0% пластмаса',
  'без нужда от паста и вода',
  'биоразградим',
  '100% естествени съставки',
  'естествено избелване до 2 нюанса',
  'компактен и удобен',
];

export interface MiswakAudienceClaim {
  icon: string;
  title: string;
  body: string;
  /** Plural since plaque/gums and caries-bacteria each cite two separate studies — a single source_url/source_label (this array's earlier shape) couldn't express that. */
  sources: { url: string; label: string }[];
  /** The claim's own citation screenshot/infographic — real evidence, not a fabricated chart. */
  image?: string;
}

/**
 * The 6-tab "Кой има полза" claim switcher (AudienceClaimsSection) — icons
 * and supporting infographics all supplied directly for this redesign
 * (public/funnel/v2/icon-claim-*.svg and science-source-*.png).
 *
 * All 6 have their own directly-supplied copy and real source(s) now (not
 * restated from memory) - plaque/gums and caries-bacteria each cite two
 * separate studies, hence `sources` being an array rather than a single
 * field. whitening's and antibacterial's sources happen to be the same
 * studies funnel.science's ScienceSection cites for the shared "/" funnel
 * page, but their title/body here were rewritten with this redesign's own
 * directly-supplied copy rather than left as the earlier
 * ScienceSection-verbatim text (whitening's image is still that page's
 * own older citation screenshot, predating this redesign).
 */
export const miswakAudienceClaims: MiswakAudienceClaim[] = [
  {
    icon: '/funnel/v2/icon-claim-plaque-gums.svg',
    title: 'По-малко плака. По-спокойни венци.',
    body: 'Натрупването на зъбна плака е ключов фактор за развитието на гингивит и възпаление на венците. Рандомизирано клинично проучване с 68 пациенти установява статистически значимо намаляване на плаката при използване на активен Miswak. По-голям систематичен преглед и мета-анализ от 2022 г., включващ 10 рандомизирани проучвания, също заключава, че Miswak ефективно намалява плаката и гингивита. При правилна техника той може да бъде ефективен инструмент за поддържане на пародонталното здраве.',
    sources: [
      { url: 'https://pubmed.ncbi.nlm.nih.gov/21798329/', label: 'ВИЖ ИЗТОЧНИК 1 ↗' },
      { url: 'https://pubmed.ncbi.nlm.nih.gov/35944735/', label: 'ВИЖ ИЗТОЧНИК 2 ↗' },
    ],
    image: '/funnel/v2/science-source-plaque-gums.png',
  },
  {
    icon: '/funnel/v2/icon-claim-antibacterial.svg',
    title: 'Антибактериален и антибиофилмен потенциал',
    body: 'Зъбната плака представлява организиран бактериален биофилм, който се прикрепя към зъбните повърхности. Систематичен преглед от 2025 г. разглежда проучвания върху Miswak и Salvadora persica и описва антибактериални и антибиофилмни свойства. Данните сочат потенциал за ограничаване на бактериалния биофилм и микроорганизми, свързани с орални заболявания. Авторите обаче отбелязват, че са необходими още добре стандартизирани клинични изследвания.',
    sources: [{ url: 'https://pubmed.ncbi.nlm.nih.gov/40475057/', label: 'ВИЖ НАУЧНИЯ ИЗТОЧНИК ↗' }],
    image: '/funnel/v2/science-source-antibacterial.png',
  },
  {
    icon: '/funnel/v2/icon-claim-whitening.svg',
    title: 'Нежен избелващ потенциал',
    body: 'Естественият вид на зъбите зависи не само от цвета им, а и от състоянието на повърхността и външните оцветявания. В лабораторно изследване върху екстрахирани и изкуствено оцветени зъби продукти със Salvadora persica показват избелващ ефект. Това е интересен резултат за потенциала на Miswak при повърхностни оцветявания, но е важно да се подчертае, че данните са лабораторни, а не клинично изпитване върху хора. Затова тази полза е най-коректно да се комуникира като „избелващ потенциал“.',
    sources: [{ url: 'https://doi.org/10.7324/JAPS.2017.71217', label: 'ВИЖ НАУЧНИЯ ИЗТОЧНИК ↗' }],
    image: '/funnel/v2/science-source-whitening.png',
  },
  {
    icon: '/funnel/v2/icon-claim-caries-bacteria.svg',
    title: 'Кариес-свързани бактерии',
    body: 'Streptococcus mutans е сред най-изследваните бактерии, свързани с развитието на кариес. Мета-анализ на продукти със Salvadora persica отчита статистически значим ефект върху кариогенни стрептококи и лактобацили, както и върху плаката. Отделно клинично проучване при хора установява непосредствено намаляване на S. mutans след използване на Miswak. Така научният интерес към Miswak не се ограничава само до физическото почистване на зъбите.',
    sources: [
      { url: 'https://pubmed.ncbi.nlm.nih.gov/31029127/', label: 'ВИЖ ИЗТОЧНИК 1 ↗' },
      { url: 'https://pubmed.ncbi.nlm.nih.gov/14973564/', label: 'ВИЖ ИЗТОЧНИК 2 ↗' },
    ],
    image: '/funnel/v2/science-source-caries-bacteria.png',
  },
  {
    icon: '/funnel/v2/icon-claim-oral-ph.svg',
    title: 'Miswak и pH на плаката',
    body: 'След прием на кисели храни и напитки pH на зъбната плака временно се понижава. In vivo проучване установява, че изплакване с екстракт от Miswak води до по-продължително повишаване на pH над 6.0 в сравнение с вода. Статистически значима разлика е отчетена и на 30-тата минута. Изследването установява и стимулиране на паротидната слюнчена секреция. Важно е, че тук се изследва Miswak екстракт, а не директно самата пръчица.',
    sources: [{ url: 'https://pubmed.ncbi.nlm.nih.gov/17823507/', label: 'ВИЖ НАУЧНИЯ ИЗТОЧНИК ↗' }],
    image: '/funnel/v2/science-source-oral-ph.png',
  },
  {
    icon: '/funnel/v2/icon-claim-bacterial-balance.svg',
    title: 'По-благоприятен бактериален профил',
    body: 'Устната кухина естествено съдържа множество видове бактерии - целта не е те да бъдат „унищожени“, а да се поддържа по-благоприятен баланс между тях. В рандомизирано клинично проучване при 94 деца с висок риск от кариес употребата на Miswak е свързана със значително намаляване на плаката и промени в състава на слюнчената микрофлора. След 3 месеца при Miswak групата се наблюдава увеличаване на S. sanguinis, вид с по-нисък кариогенен риск. Това предполага, че Miswak може да влияе не само механично, но и върху бактериалния профил в устата.',
    sources: [{ url: 'https://pubmed.ncbi.nlm.nih.gov/32054468/', label: 'ВИЖ НАУЧНИЯ ИЗТОЧНИК ↗' }],
    image: '/funnel/v2/science-source-bacterial-balance.png',
  },
];

export interface MiswakMyth {
  question: string;
  answer: string;
}

/**
 * Draft copy addressing common objections/misconceptions about Miswak —
 * grounded only in claims already made elsewhere on the live funnel page
 * (funnel.why/funnel.science/funnel.awareness), not new unverified
 * claims. Not currently rendered anywhere — the "Митове за Miswak"
 * section (MythsSection.tsx) that used to show this was removed from
 * MiswakLandingPage.tsx by request; kept here rather than deleted in
 * case the section comes back, since this is real drafted copy, not a
 * placeholder.
 */
export const miswakMyths: MiswakMyth[] = [
  {
    question: 'Не е ли просто клечка от дърво?',
    answer:
      'Miswak е пръчица от корена на Salvadora persica - растение, използвано с векове заради естествените си почистващи свойства. Влакната, които се образуват при дъвчене, действат като мека четка, а самото растение съдържа съединения, познати от народната медицина с антибактериално действие.',
  },
  {
    question: 'Хигиенично ли е да се дъвче пръчица?',
    answer:
      'Всяка пръчица е за еднократна употреба до износване на влакната - отрязваш използвания край и подготвяш нов, вместо да преизползваш стар. Съхранена суха и проветрива, между употреби не задържа влага по начина, по който го прави четка с четина.',
  },
  {
    question: 'Замества ли напълно четката и пастата?',
    answer:
      'Miswak е създаден да допълва ежедневната грижа за зъбите, не задължително да я замества изцяло - особено удобен е точно там, където четка и паста не са под ръка: след кафе, на път, в офиса.',
  },
  {
    question: 'Не е ли по-неудобен от обикновена четка?',
    answer:
      'Първите няколко пъти отнемат малко повече внимание, докато свикнеш с обелването и дъвченето на края. След това цялият процес отнема секунди - без паста, без вода, без чакане на място с мивка.',
  },
];
