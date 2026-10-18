// Background patterns for the teal slogan banner, one picked at random on each visit.
// Drawn here as small SVG tiles (white lines, see-through) so they fit the banner color and need no
// attribution. To add one: a name, the SVG tile and its size.

const svg = (width, height, body) =>
    `url("data:image/svg+xml,${encodeURIComponent(
        `<svg xmlns='http://www.w3.org/2000/svg' width='${width}' height='${height}' viewBox='0 0 ${width} ${height}'>` +
            `<g fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'>${body}</g></svg>`,
    )}")`;

export const heroPatterns = [
    {
        // The original one
        name: 'squares',
        image: "url('/images/protruding-squares.png')",
        size: 'contain',
        opacity: 0.3,
    },
    {
        name: 'mountains',
        image: svg(120, 60, "<path d='M0 55 L20 30 L32 42 L55 12 L78 40 L90 28 L120 55'/><path d='M50 18 L55 12 L60 18' stroke-width='1.5'/>"),
        size: '120px 60px',
        opacity: 0.22,
    },
    {
        name: 'waves',
        image: svg(80, 24, "<path d='M0 12 C 10 2, 30 2, 40 12 S 70 22, 80 12'/>"),
        size: '80px 24px',
        opacity: 0.25,
    },
    {
        name: 'topography',
        image: svg(
            160,
            160,
            "<path d='M20 80 C 20 40, 60 20, 90 30 S 150 60, 140 100 S 90 150, 60 140 S 20 120, 20 80 Z'/>" +
                "<path d='M40 80 C 40 55, 65 42, 88 48 S 125 68, 120 95 S 88 128, 66 122 S 40 105, 40 80 Z'/>" +
                "<path d='M60 82 C 60 70, 72 62, 86 66 S 104 78, 100 92 S 84 106, 74 102 S 60 94, 60 82 Z'/>",
        ),
        size: '160px 160px',
        opacity: 0.2,
    },
    {
        name: 'dots',
        image: `url("data:image/svg+xml,${encodeURIComponent("<svg xmlns='http://www.w3.org/2000/svg' width='22' height='22'><circle cx='11' cy='11' r='2.2' fill='white'/></svg>")}")`,
        size: '22px 22px',
        opacity: 0.3,
    },
    {
        name: 'diagonals',
        image: svg(24, 24, "<path d='M-6 6 L6 -6 M0 24 L24 0 M18 30 L30 18'/>"),
        size: '24px 24px',
        opacity: 0.18,
    },
    {
        name: 'zigzag',
        image: svg(40, 20, "<path d='M0 15 L10 5 L20 15 L30 5 L40 15'/>"),
        size: '40px 20px',
        opacity: 0.22,
    },
    {
        name: 'circles',
        image: svg(60, 60, "<circle cx='30' cy='30' r='18'/><circle cx='0' cy='0' r='18'/><circle cx='60' cy='0' r='18'/><circle cx='0' cy='60' r='18'/><circle cx='60' cy='60' r='18'/>"),
        size: '60px 60px',
        opacity: 0.18,
    },
    {
        name: 'honeycomb',
        image: svg(56, 98, "<path d='M28 66 L0 50 L0 16 L28 0 L56 16 L56 50 Z M28 66 L28 98'/><path d='M0 82 L0 98 M56 82 L56 98' />"),
        size: '56px 98px',
        opacity: 0.16,
    },
    {
        name: 'plus',
        image: svg(36, 36, "<path d='M18 12 L18 24 M12 18 L24 18'/>"),
        size: '36px 36px',
        opacity: 0.25,
    },
];

export function randomHeroPattern() {
    return heroPatterns[Math.floor(Math.random() * heroPatterns.length)];
}
