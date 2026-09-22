// Shared client-side search normalisation.
const MARKS = /[̀-ͯؐ-ًؚ-ٰٟۖ-ۭ]/g;

export const normaliseSearchText = (value) =>
    (value ?? '')
        .normalize('NFD')
        .replace(MARKS, '')
        .replace(/ـ/g, '') // tatweel
        .replace(/ٱ/g, 'ا')
        .replace(/ى/g, 'ي')
        .replace(/ة/g, 'ه')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
