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

export interface MiswakMyth {
  question: string;
  answer: string;
}

/**
 * Draft copy addressing common objections/misconceptions about Miswak —
 * grounded only in claims already made elsewhere on the live funnel page
 * (funnel.why/funnel.science/funnel.awareness), not new unverified
 * claims. FLAGGED FOR REVIEW before this page goes live, same as other
 * funnel copy started as placeholder-quality pending a final pass.
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
