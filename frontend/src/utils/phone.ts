/** Bulgarian mobile numbers: 9 digits after the country code, starting with 8 (e.g. 0888123456 / 888123456 / +359888123456). */
const BG_MOBILE_PATTERN = /^\+3598\d{8}$/;

export function isValidBgMobile(value: string): boolean {
  return BG_MOBILE_PATTERN.test(value);
}
