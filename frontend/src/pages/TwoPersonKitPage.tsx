import KitProductPage from './KitProductPage';
import { ONE_PERSON_KIT_SLUG, TWO_PERSON_KIT_SLUG } from '../content/kitProducts';
import type { KitKeyFact } from './KitProductPage';

export { TWO_PERSON_KIT_SLUG };

const KEY_FACTS: KitKeyFact[] = [
  { icon: 'users', title: 'Готов старт за двама', text: 'Два пъти пръчици, калъфи и стъргалки - по един комплект за всеки.' },
  { icon: 'heart', title: 'Хигиенично за всеки', text: 'Всеки получава своя собствена стъргалка и калъф.' },
  { icon: 'sparkle', title: 'Най-изгодната опция', text: 'По-голяма отстъпка от едноличния комплект.' },
];

/** The "Комплект за двама" kit's product page - same layout as the bamboo case/scraper pages, see KitProductPage.tsx. */
export default function TwoPersonKitPage() {
  return (
    <KitProductPage
      slug={TWO_PERSON_KIT_SLUG}
      eyebrow="Всичко в един комплект"
      keyFacts={KEY_FACTS}
      crossSell={{ slug: ONE_PERSON_KIT_SLUG, heading: 'Само за теб?' }}
    />
  );
}
