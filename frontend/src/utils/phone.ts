/** Bulgarian mobile numbers: 9 digits after the country code, starting with 7, 8 or 9 (e.g. 0888123456 / 888123456 / +359888123456 / 988356131). */
const BG_MOBILE_PATTERN = /^\+359[789]\d{8}$/;

export function isValidBgMobile(value: string): boolean {
  return BG_MOBILE_PATTERN.test(value);
}
