import KitProductPage from './KitProductPage';
import { ONE_PERSON_KIT_SLUG, TWO_PERSON_KIT_SLUG } from '../content/kitProducts';
import type { KitKeyFact } from './KitProductPage';

export { ONE_PERSON_KIT_SLUG };

const KEY_FACTS: KitKeyFact[] = [
  { icon: 'users', title: 'Готов старт за един', text: 'Пръчици, калъф и стъргалка - всичко за ежедневната ти рутина.' },
  { icon: 'check-badge', title: 'Без излишни покупки', text: 'Най-изгодно е да вземеш комплекта наведнъж, вместо поотделно.' },
  { icon: 'sparkle', title: 'Подаръчно готов', text: 'Спретнат комплект, идеален и за подарък.' },
];

/** The "Единичен комплект" kit's product page - same layout as the bamboo case/scraper pages, see KitProductPage.tsx. */
export default function OnePersonKitPage() {
  return (
    <KitProductPage
      slug={ONE_PERSON_KIT_SLUG}
      eyebrow="Всичко в един комплект"
      keyFacts={KEY_FACTS}
      crossSell={{ slug: TWO_PERSON_KIT_SLUG, heading: 'Купуваш за двама?' }}
    />
  );
}
